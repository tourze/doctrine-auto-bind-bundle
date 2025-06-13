# 在 Symfony Bundle 中动态注册 Doctrine Entity Manager 的完整实施指南

本研究提供了在 Symfony Bundle 中动态注册 Doctrine Entity Manager 的全面解决方案，涵盖了通过 CompilerPass 实现动态注册、类似 WithMonologChannel 的依赖注入模式、环境变量配置以及 Symfony 和 Doctrine 的最佳实践。

## 通过 CompilerPass 动态注册 DBAL 连接和 Entity Manager

### CompilerPass 生命周期和时机

CompilerPass 在容器编译期间执行，这使其成为动态注册服务的理想机制。执行顺序为：配置加载 → CompilerPass 执行 → 容器优化 → 运行时使用。这种时机让我们能够在所有扩展加载后但在容器优化前操作服务定义。

### 核心实现：动态 Entity Manager CompilerPass

```php
<?php
namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DynamicDoctrineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // 查找需要自定义 Entity Manager 的服务
        $taggedServices = $container->findTaggedServiceIds('app.custom_entity_manager');
        
        foreach ($taggedServices as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $this->ensureConnection($container, $tag);
                $this->createEntityManager($container, $tag);
                $this->configureRepositories($container, $tag);
            }
        }
        
        $this->updateDoctrineRegistry($container);
    }
    
    private function ensureConnection(ContainerBuilder $container, array $config): void
    {
        $connectionName = $config['connection'];
        $connectionId = sprintf('doctrine.dbal.%s_connection', $connectionName);
        
        if ($container->hasDefinition($connectionId)) {
            return;
        }
        
        $connectionDef = new Definition('%doctrine.dbal.connection.class%');
        $connectionDef->setFactory([
            new Reference('doctrine.dbal.connection_factory'),
            'createConnection'
        ]);
        $connectionDef->setArguments([
            $config['connection_params'],
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager')
        ]);
        
        $container->setDefinition($connectionId, $connectionDef);
    }
    
    private function createEntityManager(ContainerBuilder $container, array $config): void
    {
        $name = $config['name'];
        
        // 创建 ORM 配置
        $configId = sprintf('doctrine.orm.%s_configuration', $name);
        $configDef = new Definition('%doctrine.orm.configuration.class%');
        
        // 配置元数据驱动
        if (isset($config['mappings'])) {
            $driverDef = new Definition('%doctrine.orm.metadata.driver.annotation.class%');
            $driverDef->setArguments([
                new Reference('doctrine.orm.metadata.annotation_reader'),
                $config['mappings']['paths'] ?? []
            ]);
            $configDef->addMethodCall('setMetadataDriverImpl', [$driverDef]);
        }
        
        // 设置命名策略和代理配置
        $configDef->addMethodCall('setNamingStrategy', [
            new Reference('doctrine.orm.naming_strategy.underscore')
        ]);
        $configDef->addMethodCall('setProxyDir', ['%doctrine.orm.proxy_dir%']);
        $configDef->addMethodCall('setProxyNamespace', ['%doctrine.orm.proxy_namespace%']);
        
        $container->setDefinition($configId, $configDef);
        
        // 创建 Entity Manager
        $emId = sprintf('doctrine.orm.%s_entity_manager', $name);
        $emDef = new Definition('%doctrine.orm.entity_manager.class%');
        $emDef->setFactory(['%doctrine.orm.entity_manager.class%', 'create']);
        $emDef->setArguments([
            new Reference(sprintf('doctrine.dbal.%s_connection', $config['connection'])),
            new Reference($configId)
        ]);
        
        $container->setDefinition($emId, $emDef);
    }
    
    private function updateDoctrineRegistry(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine')) {
            return;
        }
        
        $entityManagers = [];
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if (preg_match('/^doctrine\.orm\.(.+)_entity_manager$/', $serviceId, $matches)) {
                $entityManagers[$matches[1]] = $serviceId;
            }
        }
        
        $doctrineDef = $container->findDefinition('doctrine');
        $doctrineDef->replaceArgument(2, $entityManagers);
    }
}
```

### Bundle 集成

```php
<?php
namespace App\MyBundle;

use App\DependencyInjection\Compiler\DynamicDoctrineCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class MyBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DynamicDoctrineCompilerPass());
    }
}
```

## 实现类似 WithMonologChannel 的使用模式

### WithEntityManager 属性定义

```php
<?php
namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class WithEntityManager
{
    public function __construct(
        public readonly string $manager = 'default',
        public readonly bool $lazy = false,
        public readonly array $repositories = []
    ) {
    }
}
```

### Entity Manager Channel CompilerPass

```php
<?php
namespace App\DependencyInjection\Compiler;

use App\Attribute\WithEntityManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Doctrine\ORM\EntityManagerInterface;

class EntityManagerChannelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            
            if (!$class || !class_exists($class)) {
                continue;
            }
            
            $reflectionClass = new \ReflectionClass($class);
            $attributes = $reflectionClass->getAttributes(WithEntityManager::class);
            
            if (empty($attributes)) {
                continue;
            }
            
            $attribute = $attributes[0]->newInstance();
            $managerName = $attribute->manager;
            
            $this->configureEntityManagerForService($container, $serviceId, $managerName);
        }
    }
    
    private function configureEntityManagerForService(
        ContainerBuilder $container, 
        string $serviceId, 
        string $managerName
    ): void {
        $definition = $container->getDefinition($serviceId);
        $entityManagerServiceId = "doctrine.orm.{$managerName}_entity_manager";
        
        // 为指定的 Entity Manager 创建自动装配别名
        $container->registerAliasForArgument(
            $entityManagerServiceId,
            EntityManagerInterface::class,
            $managerName . 'EntityManager'
        );
        
        // 使用参数绑定
        $definition->setBindings([
            EntityManagerInterface::class => new Reference($entityManagerServiceId)
        ]);
    }
}
```

### 自动配置支持

```php
// src/Kernel.php
class Kernel extends BaseKernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            WithEntityManager::class,
            static function (
                ChildDefinition $definition, 
                WithEntityManager $attribute, 
                \ReflectionClass $reflector
            ): void {
                $managerServiceId = "doctrine.orm.{$attribute->manager}_entity_manager";
                $definition->setBindings([
                    EntityManagerInterface::class => new Reference($managerServiceId)
                ]);
            }
        );
    }
}
```

### 使用示例

```php
<?php
namespace App\Service;

use App\Attribute\WithEntityManager;
use Doctrine\ORM\EntityManagerInterface;

#[WithEntityManager('customer')]
class CustomerService 
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        // 自动注入 customer Entity Manager
    }
}
```

## Bundle 配置与环境变量支持

### 现代化的 AbstractBundle 实现

```php
<?php
namespace Acme\DataBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AcmeDataBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('database')
                    ->children()
                        ->scalarNode('url')
                            ->defaultValue('%env(resolve:DATABASE_URL)%')
                            ->info('Database connection URL with environment variable support')
                        ->end()
                        ->scalarNode('entity_manager')
                            ->defaultValue('%env(string:default:ACME_ENTITY_MANAGER)%')
                            ->info('Entity manager name, defaults to "default" if env var not set')
                        ->end()
                        ->scalarNode('driver')
                            ->defaultValue('pdo_mysql')
                        ->end()
                        ->scalarNode('charset')
                            ->defaultValue('utf8mb4')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
    
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.xml');
        
        $container->parameters()
            ->set('acme_data.database_url', $config['database']['url'])
            ->set('acme_data.entity_manager_name', $config['database']['entity_manager'])
        ;
    }
    
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configs = $builder->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);
        
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    $config['database']['entity_manager'] => [
                        'url' => $config['database']['url'],
                        'driver' => $config['database']['driver'],
                        'charset' => $config['database']['charset'],
                    ]
                ]
            ],
            'orm' => [
                'entity_managers' => [
                    $config['database']['entity_manager'] => [
                        'connection' => $config['database']['entity_manager'],
                        'mappings' => [
                            'AcmeDataBundle' => [
                                'is_bundle' => true,
                                'type' => 'attribute',
                                'dir' => 'Entity',
                                'prefix' => 'Acme\\DataBundle\\Entity',
                                'alias' => 'AcmeData'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }
}
```

### 环境变量配置示例

```yaml
# config/packages/acme_data.yaml
acme_data:
    database:
        url: '%env(resolve:ACME_DATABASE_URL)%'              # 主环境变量
        entity_manager: '%env(string:default:ACME_EM_NAME)%' # 默认值为 "default"
        driver: '%env(string:pdo_mysql:ACME_DB_DRIVER)%'     # 默认值为 "pdo_mysql"
        charset: '%env(string:utf8mb4:ACME_DB_CHARSET)%'     # 默认值为 "utf8mb4"
```

```bash
# .env 文件
DATABASE_URL=mysql://user:pass@localhost/main_db        # 主数据库（可被 Bundle 覆盖）
ACME_DATABASE_URL=mysql://user:pass@localhost/acme_db  # Bundle 专用数据库
ACME_EM_NAME=acme_manager
```

## 最佳实践和示例代码

### Service Locator 模式

```php
<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class EntityManagerLocator
{
    public function __construct(
        #[AutowireLocator([
            'default' => 'doctrine.orm.default_entity_manager',
            'customer' => 'doctrine.orm.customer_entity_manager',
            'analytics' => 'doctrine.orm.analytics_entity_manager',
        ])]
        private ServiceLocator $entityManagers
    ) {
    }
    
    public function get(string $name): EntityManagerInterface
    {
        return $this->entityManagers->get($name);
    }
}
```

### 多租户实现模式

基于流行的多租户 Bundle 研究，推荐以下模式：

```php
class TenantConnectionWrapper extends Connection
{
    public function changeDatabase(string $database): void
    {
        $this->close();
        $params = $this->getParams();
        $params['dbname'] = $database;
        $this->__construct($params, $this->_driver, $this->_config);
    }
}
```

### 测试策略

```php
class MultipleEntityManagerTest extends KernelTestCase
{
    private EntityManagerInterface $defaultEm;
    private EntityManagerInterface $customEm;
    
    protected function setUp(): void
    {
        self::bootKernel();
        $this->defaultEm = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->customEm = self::getContainer()->get('doctrine.orm.custom_entity_manager');
    }
    
    private function createSchemas(): void
    {
        foreach (['default', 'custom'] as $emName) {
            $em = self::getContainer()->get("doctrine.orm.{$emName}_entity_manager");
            $schemaTool = new SchemaTool($em);
            $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
        }
    }
}
```

## 关键要点和建议

**性能优化**：CompilerPass 在编译时运行，不会产生运行时开销。使用服务定位器模式实现延迟加载，避免不必要的连接初始化。

**命名约定**：遵循 Doctrine Bundle 的标准命名模式 - Entity Manager 使用 `doctrine.orm.{name}_entity_manager`，连接使用 `doctrine.dbal.{name}_connection`。

**错误处理**：始终验证所需服务是否存在，在 CompilerPass 中提供清晰的错误消息，使用适当的异常处理保护动态配置。

**安全考虑**：确保多租户环境中的数据隔离，使用环境变量存储敏感配置，考虑使用 Symfony 的 secrets vault 管理生产环境密钥。

这种实现方法特别适合多租户应用、动态微服务配置和需要灵活数据库连接的复杂企业系统。通过结合 CompilerPass、属性注解和环境变量配置，可以创建一个强大而灵活的动态 Entity Manager 注册系统。