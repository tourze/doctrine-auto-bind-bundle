<?php

namespace Tourze\DoctrineAutoBindBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineAutoBindBundle\DB\DedicatedDatabaseConnectionFactory;
use Tourze\DoctrineAutoBindBundle\DB\DedicatedManagerRegistry;

/**
 * 用于注册专用数据库服务的通用编译器通道
 * 可以为不同的bundle注册独立的数据库连接、EntityManager和ManagerRegistry
 *
 * 使用示例：
 * ```php
 * // 在Bundle的build方法中使用
 * public function build(ContainerBuilder $container): void
 * {
 *     parent::build($container);
 *
 *     $container->addCompilerPass(new DatabaseServicesCompilerPass(
 *         servicePrefix: 'json_rpc_log',                    // 服务前缀
 *         entityPath: __DIR__ . '/../Entity',              // 实体目录路径
 *         entityNamespace: 'Tourze\\JsonRPCLogBundle\\Entity', // 实体命名空间
 *         serviceNamespace: 'Tourze\\JsonRPCLogBundle',     // 服务命名空间，用于自动绑定
 *         defaultDatabaseSuffix: '_json_rpc_log',          // 可选，默认数据库后缀
 *         defaultConnectionReference: 'doctrine.dbal.default_connection' // 可选，默认连接引用
 *     ));
 * }
 * ```
 *
 * 将会注册以下服务：
 * - {servicePrefix}.database_connection_factory - 数据库连接工厂
 * - doctrine.dbal.{servicePrefix}_connection - 专用数据库连接
 * - doctrine.orm.{servicePrefix}_entity_manager - 专用EntityManager
 * - {servicePrefix}.manager_registry - 专用ManagerRegistry
 *
 * 自动参数绑定：
 * - 为指定命名空间下的所有服务自动绑定 $registry 参数到专用的ManagerRegistry
 * - 为指定命名空间下的所有服务自动绑定 $entityManager 参数到专用的EntityManager
 * - 为指定命名空间下的所有服务自动绑定 $connection 参数到专用的Connection
 * - 智能检查：只为真正需要这些参数的服务添加绑定（通过反射检查构造函数参数）
 * - 自动跳过：service locator、代理类、抽象类和接口等特殊服务类型
 *
 * 环境变量支持：
 * - {SERVICE_PREFIX_UPPER}_DB_HOST - 数据库主机
 * - {SERVICE_PREFIX_UPPER}_DB_PORT - 数据库端口
 * - {SERVICE_PREFIX_UPPER}_DB_NAME - 数据库名称
 * - {SERVICE_PREFIX_UPPER}_DB_USER - 数据库用户
 * - {SERVICE_PREFIX_UPPER}_DB_PASSWORD - 数据库密码
 * - {SERVICE_PREFIX_UPPER}_DB_DRIVER - 数据库驱动
 * - {SERVICE_PREFIX_UPPER}_DB_CHARSET - 字符集
 */
class DatabaseServicesCompilerPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $servicePrefix,
        private readonly string $entityPath,
        private readonly string $entityNamespace,
        private readonly string $serviceNamespace,
        private readonly string $defaultDatabaseSuffix = '',
        private readonly string $defaultConnectionReference = 'doctrine.dbal.default_connection'
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        // 注册数据库连接工厂服务
        $this->registerDatabaseConnectionFactory($container);

        // 注册专门的数据库连接
        $this->registerDedicatedConnection($container);

        // 注册专门的 EntityManager
        $this->registerDedicatedEntityManager($container);

        // 注册专门的 ManagerRegistry
        $this->registerDedicatedManagerRegistry($container);

        // 为相关服务自动绑定参数
        $this->bindParametersToServices($container);
    }

    private function registerDatabaseConnectionFactory(ContainerBuilder $container): void
    {
        $definition = new Definition(DedicatedDatabaseConnectionFactory::class);
        $definition->setArguments([
            new Reference($this->defaultConnectionReference),
            $this->servicePrefix,
            $this->entityPath,
            $this->defaultDatabaseSuffix,
        ]);
        $definition->setAutowired(true);

        $container->setDefinition($this->getDatabaseConnectionFactoryServiceId(), $definition);
    }

    private function registerDedicatedConnection(ContainerBuilder $container): void
    {
        $definition = new Definition(Connection::class);
        $definition->setFactory([
            new Reference($this->getDatabaseConnectionFactoryServiceId()),
            'createConnection'
        ]);

        $container->setDefinition($this->getConnectionServiceId(), $definition);
    }

    private function registerDedicatedEntityManager(ContainerBuilder $container): void
    {
        $definition = new Definition(EntityManager::class);
        $definition->setFactory([
            new Reference($this->getDatabaseConnectionFactoryServiceId()),
            'createEntityManager'
        ]);
        $definition->setArguments([
            new Reference($this->getConnectionServiceId()),
        ]);

        $container->setDefinition($this->getEntityManagerServiceId(), $definition);
    }

    private function registerDedicatedManagerRegistry(ContainerBuilder $container): void
    {
        $definition = new Definition(DedicatedManagerRegistry::class);
        $definition->setArguments([
            new Reference($this->getEntityManagerServiceId()),
            $this->servicePrefix,
            $this->entityNamespace,
        ]);

        $container->setDefinition($this->getManagerRegistryServiceId(), $definition);
    }

    /**
     * 为指定命名空间下的服务自动绑定专用数据库相关参数
     */
    private function bindParametersToServices(ContainerBuilder $container): void
    {
        $bindings = [
            '$registry' => new Reference($this->getManagerRegistryServiceId()),
            '$entityManager' => new Reference($this->getEntityManagerServiceId()),
            '$connection' => new Reference($this->getConnectionServiceId()),
        ];

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();

            // 跳过非类定义和抽象定义
            if (!$class || $definition->isAbstract()) {
                continue;
            }

            // 跳过特殊的服务类型
            if ($this->shouldSkipService($serviceId, $class)) {
                continue;
            }

            // 检查是否属于指定的服务命名空间
            if (str_starts_with($class, $this->serviceNamespace . '\\')) {
                // 只为真正需要这些参数的服务添加绑定
                $neededBindings = $this->getNeededBindings($class, $bindings);

                if (!empty($neededBindings)) {
                    // 获取现有的绑定
                    $existingBindings = $definition->getBindings();

                    // 合并新的绑定，但不覆盖已存在的绑定
                    $newBindings = array_merge($neededBindings, $existingBindings);
                    $definition->setBindings($newBindings);
                }
            }
        }
    }

    /**
     * 检查是否应该跳过某个服务
     */
    private function shouldSkipService(string $serviceId, string $class): bool
    {
        // 跳过service locator
        if (str_contains($serviceId, '.service_locator.') || str_contains($class, 'ServiceLocator')) {
            return true;
        }

        // 跳过代理类
        if (str_contains($class, 'Proxy')) {
            return true;
        }

        // 跳过抽象类和接口
        if (class_exists($class)) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查类的构造函数需要哪些绑定参数
     */
    private function getNeededBindings(string $class, array $availableBindings): array
    {
        $neededBindings = [];

        try {
            if (!class_exists($class)) {
                return $neededBindings;
            }

            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $neededBindings;
            }

            $parameters = $constructor->getParameters();

            foreach ($parameters as $parameter) {
                $paramName = '$' . $parameter->getName();

                // 检查是否有对应的绑定可用
                if (isset($availableBindings[$paramName])) {
                    $neededBindings[$paramName] = $availableBindings[$paramName];
                }
            }
        } catch (\ReflectionException $e) {
            // 如果反射失败，跳过这个类
        }

        return $neededBindings;
    }

    private function getDatabaseConnectionFactoryServiceId(): string
    {
        return "{$this->servicePrefix}.database_connection_factory";
    }

    private function getConnectionServiceId(): string
    {
        return "doctrine.dbal.{$this->servicePrefix}_connection";
    }

    private function getEntityManagerServiceId(): string
    {
        return "doctrine.orm.{$this->servicePrefix}_entity_manager";
    }

    private function getManagerRegistryServiceId(): string
    {
        return "{$this->servicePrefix}.manager_registry";
    }
}
