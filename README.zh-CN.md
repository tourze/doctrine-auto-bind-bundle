# Doctrine Auto Bind Bundle

一个 Symfony Bundle，提供多个 Doctrine Entity Manager 的自动注册和管理，支持智能依赖注入。

## 功能特性

- 🚀 **自动 Entity Manager 注册**：基于配置动态注册多个 Entity Manager
- 🏷️ **属性注入**：使用 `#[WithEntityManager]` 属性实现干净的依赖注入
- 🔧 **环境变量支持**：通过环境变量配置数据库连接
- 📦 **服务定位器模式**：通过集中式定位器访问多个 Entity Manager
- 🏗️ **灵活配置**：支持多数据库连接和实体映射
- 🧪 **测试友好**：提供专用的测试工具

## 安装

```bash
composer require tourze/doctrine-auto-bind-bundle
```

## 快速开始

### 1. 使用 WithEntityManager 属性

```php
use Doctrine\ORM\EntityManagerInterface;
use Tourze\DoctrineAutoBindBundle\Attribute\WithEntityManager;

#[WithEntityManager('customer')]
class CustomerService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        // 自动注入 'customer' EntityManager
    }
    
    public function findCustomers(): array
    {
        return $this->entityManager->getRepository(Customer::class)->findAll();
    }
}
```

### 2. 使用 EntityManagerLocator

```php
use Tourze\DoctrineAutoBindBundle\Service\EntityManagerLocator;

class MultiDatabaseService
{
    public function __construct(
        private EntityManagerLocator $emLocator
    ) {
    }
    
    public function syncData(): void
    {
        $defaultEm = $this->emLocator->getDefault();
        
        if ($this->emLocator->has('customer')) {
            $customerEm = $this->emLocator->get('customer');
            // 使用 customer EntityManager
        }
    }
}
```

### 3. 使用 EntityManagerFactory

```php
use Tourze\DoctrineAutoBindBundle\Factory\EntityManagerFactory;

class DatabaseSetupService
{
    public function __construct(
        private EntityManagerFactory $factory
    ) {
    }
    
    public function setupDatabases(): void
    {
        // 从环境变量创建
        $customerEM = $this->factory->createFromEnvironment(
            'CUSTOMER', 
            ['src/Entity/Customer']
        );
        
        // 从连接参数创建
        $analyticsEM = $this->factory->createFromParams([
            'driver' => 'pdo_mysql',
            'host' => 'analytics.example.com',
            'dbname' => 'analytics',
            'user' => 'analytics_user',
            'password' => 'analytics_pass'
        ], ['src/Entity/Analytics']);
    }
}
```

### 4. 设置环境变量

```bash
# .env
CUSTOMER_DB_HOST=localhost
CUSTOMER_DB_NAME=customer_db
CUSTOMER_DB_USER=user
CUSTOMER_DB_PASSWORD=password

ANALYTICS_DB_HOST=analytics.example.com
ANALYTICS_DB_NAME=analytics_db
ANALYTICS_DB_USER=analytics_user
ANALYTICS_DB_PASSWORD=analytics_password
```

## 环境变量配置

用于数据库配置的环境变量设置示例：

```bash
# .env
# 客户数据库配置
CUSTOMER_DB_HOST=localhost
CUSTOMER_DB_PORT=3306
CUSTOMER_DB_NAME=customer_db
CUSTOMER_DB_USER=customer_user
CUSTOMER_DB_PASSWORD=customer_pass
CUSTOMER_DB_DRIVER=pdo_mysql
CUSTOMER_DB_CHARSET=utf8mb4

# 分析数据库配置
ANALYTICS_DB_HOST=analytics.example.com
ANALYTICS_DB_PORT=3306
ANALYTICS_DB_NAME=analytics_db
ANALYTICS_DB_USER=analytics_user
ANALYTICS_DB_PASSWORD=analytics_pass
```

对于每个设置了环境变量前缀的数据库，你可以设置：

- `{PREFIX}_DB_HOST` - 数据库主机
- `{PREFIX}_DB_PORT` - 数据库端口
- `{PREFIX}_DB_NAME` - 数据库名称
- `{PREFIX}_DB_USER` - 数据库用户
- `{PREFIX}_DB_PASSWORD` - 数据库密码
- `{PREFIX}_DB_DRIVER` - 数据库驱动（默认：pdo_mysql）
- `{PREFIX}_DB_CHARSET` - 数据库字符集（默认：utf8mb4）

## 高级用法

### 使用 DatabaseServicesCompilerPass

如果你需要更多的注册过程控制，可以直接使用 `DatabaseServicesCompilerPass`：

```php
use Tourze\DoctrineAutoBindBundle\DependencyInjection\DatabaseServicesCompilerPass;

public function build(ContainerBuilder $container): void
{
    parent::build($container);
    
    $container->addCompilerPass(new DatabaseServicesCompilerPass(
        servicePrefix: 'my_service',
        entityPath: __DIR__ . '/../Entity',
        entityNamespace: 'App\\MyBundle\\Entity',
        serviceNamespace: 'App\\MyBundle',
        defaultDatabaseSuffix: '_my_service'
    ));
}
```

### 多租户应用

Bundle 通过动态连接切换支持多租户场景：

```php
$factory = new DedicatedDatabaseConnectionFactory(
    $defaultConnection,
    'tenant',
    $entityPath,
    '_tenant'
);

$connection = $factory->createConnection();
$entityManager = $factory->createEntityManager($connection);
```

## 可用服务

Bundle 注册了以下服务：

- `Tourze\DoctrineAutoBindBundle\Service\EntityManagerLocator` - EntityManager 服务定位器
- `Tourze\DoctrineAutoBindBundle\Factory\EntityManagerFactory` - EntityManager 创建工厂
- `Tourze\DoctrineAutoBindBundle\DB\DedicatedDatabaseConnectionFactory` - 专用连接工厂

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit
```

## 设计原理

基于指南文档《在 Symfony Bundle 中动态注册 Doctrine Entity Manager 的完整实施指南》的设计思路：

1. **CompilerPass 动态注册**：通过编译器通道在容器构建时动态注册服务
2. **属性驱动配置**：类似 Symfony MonologBundle 的 Channel 模式
3. **环境变量支持**：现代化的配置管理方式
4. **服务定位器模式**：提供灵活的多 EntityManager 访问方式
5. **最佳实践遵循**：符合 Symfony 和 Doctrine 的官方约定

## 许可证

本 Bundle 在 MIT 许可证下发布。详情请查看 [LICENSE](LICENSE) 文件。

## 致谢

- 灵感来源于 Symfony 的 MonologBundle channel 模式
- 遵循 Symfony 最佳实践和约定构建
- 实现了官方 Doctrine Bundle 的模式

## 相关文档

- [Symfony 依赖注入](https://symfony.com/doc/current/service_container.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [多个 Entity Manager](https://symfony.com/doc/current/doctrine/multiple_entity_managers.html)
