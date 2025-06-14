<?php

namespace Tourze\DoctrineAutoBindBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 动态 Doctrine 编译器通道
 * 负责动态注册 DBAL 连接和 Entity Manager
 *
 * 功能：
 * - 扫描标记了特定标签的服务
 * - 动态创建 DBAL 连接
 * - 动态创建 Entity Manager
 * - 配置 Repository
 * - 更新 Doctrine Registry
 */
class DynamicDoctrineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // 查找需要自定义 Entity Manager 的服务
        $taggedServices = $container->findTaggedServiceIds('doctrine_auto_bind.custom_entity_manager');

        foreach ($taggedServices as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $this->ensureConnection($container, $tag);
                $this->createEntityManager($container, $tag);
                $this->configureRepositories($container, $tag);
            }
        }

        $this->updateDoctrineRegistry($container);
    }

    /**
     * 确保 DBAL 连接存在
     */
    private function ensureConnection(ContainerBuilder $container, array $config): void
    {
        $connectionName = $config['connection'] ?? $config['name'];
        $connectionId = sprintf('doctrine.dbal.%s_connection', $connectionName);

        if ($container->hasDefinition($connectionId)) {
            return;
        }

        // 创建连接定义
        $connectionDef = new Definition(Connection::class);
        $connectionDef->setFactory([
            new Reference('doctrine.dbal.connection_factory'),
            'createConnection'
        ]);

        // 构建连接参数
        $connectionParams = $this->buildConnectionParams($config);

        $connectionDef->setArguments([
            $connectionParams,
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager')
        ]);

        $container->setDefinition($connectionId, $connectionDef);
    }

    /**
     * 创建 Entity Manager
     */
    private function createEntityManager(ContainerBuilder $container, array $config): void
    {
        $name = $config['name'];
        $connectionName = $config['connection'] ?? $name;

        // 创建 ORM 配置
        $configId = sprintf('doctrine.orm.%s_configuration', $name);
        $configDef = new Definition('%doctrine.orm.configuration.class%');

        // 配置元数据驱动
        if (isset($config['mappings'])) {
            $this->configureMappings($configDef, $config['mappings']);
        }

        // 设置命名策略和代理配置
        $configDef->addMethodCall('setNamingStrategy', [
            new Reference('doctrine.orm.naming_strategy.underscore')
        ]);
        $configDef->addMethodCall('setProxyDir', ['%doctrine.orm.proxy_dir%']);
        $configDef->addMethodCall('setProxyNamespace', ['%doctrine.orm.proxy_namespace%']);
        $configDef->addMethodCall('setAutoGenerateProxyClasses', ['%doctrine.orm.auto_generate_proxy_classes%']);

        $container->setDefinition($configId, $configDef);

        // 创建 Entity Manager
        $emId = sprintf('doctrine.orm.%s_entity_manager', $name);
        $emDef = new Definition(EntityManager::class);
        $emDef->setFactory([EntityManager::class, 'create']);
        $emDef->setArguments([
            new Reference(sprintf('doctrine.dbal.%s_connection', $connectionName)),
            new Reference($configId)
        ]);

        $container->setDefinition($emId, $emDef);
    }

    /**
     * 配置映射
     */
    private function configureMappings(Definition $configDef, array $mappings): void
    {
        foreach ($mappings as $mappingConfig) {
            $type = $mappingConfig['type'] ?? 'attribute';
            $paths = $mappingConfig['paths'] ?? [];

            if ($type === 'attribute') {
                $driverDef = new Definition('%doctrine.orm.metadata.driver.attribute.class%');
                $driverDef->setArguments([$paths]);
                $configDef->addMethodCall('setMetadataDriverImpl', [$driverDef]);
            }
        }
    }

    /**
     * 配置 Repository
     */
    private function configureRepositories(ContainerBuilder $container, array $config): void
    {
        $name = $config['name'];
        $emServiceId = sprintf('doctrine.orm.%s_entity_manager', $name);

        if (!isset($config['entities'])) {
            return;
        }

        foreach ($config['entities'] as $entityClass) {
            $repositoryServiceId = sprintf(
                'doctrine.orm.%s_entity_manager.repository.%s',
                $name,
                str_replace('\\', '_', $entityClass)
            );

            $repositoryDef = new Definition('%doctrine.orm.entity_repository.class%');
            $repositoryDef->setFactory([
                new Reference($emServiceId),
                'getRepository'
            ]);
            $repositoryDef->setArguments([$entityClass]);

            $container->setDefinition($repositoryServiceId, $repositoryDef);
        }
    }

    /**
     * 更新 Doctrine Registry
     */
    private function updateDoctrineRegistry(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine')) {
            return;
        }

        $entityManagers = [];
        $connections = [];

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if (preg_match('/^doctrine\.orm\.(.+)_entity_manager$/', $serviceId, $matches)) {
                $entityManagers[$matches[1]] = $serviceId;
            }
            if (preg_match('/^doctrine\.dbal\.(.+)_connection$/', $serviceId, $matches)) {
                $connections[$matches[1]] = $serviceId;
            }
        }

        // 更新 Doctrine Registry 的参数
        $doctrineDef = $container->findDefinition('doctrine');

        // 第一个参数是连接名称映射
        if (count($connections) > 0) {
            $doctrineDef->replaceArgument(0, 'default');
            $doctrineDef->replaceArgument(1, array_keys($connections));
        }

        // 第三个参数是实体管理器名称映射
        if (count($entityManagers) > 0) {
            $doctrineDef->replaceArgument(2, 'default');
            $doctrineDef->replaceArgument(3, array_keys($entityManagers));
        }
    }

    /**
     * 构建连接参数
     */
    private function buildConnectionParams(array $config): array
    {
        $params = [];

        // 从配置中读取连接参数
        if (isset($config['connection_params'])) {
            $params = $config['connection_params'];
        }

        // 支持从环境变量读取
        if (isset($config['env_prefix'])) {
            $envPrefix = strtoupper($config['env_prefix']);

            $envMappings = [
                'host' => "{$envPrefix}_DB_HOST",
                'port' => "{$envPrefix}_DB_PORT",
                'dbname' => "{$envPrefix}_DB_NAME",
                'user' => "{$envPrefix}_DB_USER",
                'password' => "{$envPrefix}_DB_PASSWORD",
                'driver' => "{$envPrefix}_DB_DRIVER",
                'charset' => "{$envPrefix}_DB_CHARSET",
            ];

            foreach ($envMappings as $param => $envVar) {
                if (isset($_ENV[$envVar])) {
                    $value = $_ENV[$envVar];
                    if ($param === 'port') {
                        $value = (int) $value;
                    }
                    $params[$param] = $value;
                }
            }
        }

        // 设置默认值
        $params['driver'] = $params['driver'] ?? 'pdo_mysql';
        $params['charset'] = $params['charset'] ?? 'utf8mb4';

        return $params;
    }
}
