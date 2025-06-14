# 使用示例

## 基本用法

### 1. 使用 WithEntityManager 属性

```php
<?php

use Doctrine\ORM\EntityManagerInterface;
use Tourze\DoctrineAutoBindBundle\Attribute\WithEntityManager;

#[WithEntityManager('customer')]
class CustomerService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        // 自动注入 customer EntityManager
    }

    public function findCustomers(): array
    {
        return $this->entityManager->getRepository(Customer::class)->findAll();
    }
}
```

### 2. 使用 EntityManagerLocator

```php
<?php

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

### 3. 使用 EntityManagerFactory 直接创建

```php
<?php

use Tourze\DoctrineAutoBindBundle\Factory\EntityManagerFactory;

class DatabaseSetupService
{
    public function __construct(
        private EntityManagerFactory $factory
    ) {
    }
    
    public function setupCustomerDatabase(): void
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

## 在 Bundle 中使用

### 在你的 Bundle 中注册专用 EntityManager

```php
<?php

namespace YourBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineAutoBindBundle\DependencyInjection\DatabaseServicesCompilerPass;

class YourBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        
        // 注册专用的数据库服务
        $container->addCompilerPass(new DatabaseServicesCompilerPass(
            servicePrefix: 'your_service',
            entityPath: __DIR__ . '/Entity',
            entityNamespace: 'YourBundle\\Entity',
            serviceNamespace: 'YourBundle',
            defaultDatabaseSuffix: '_your_service'
        ));
    }
}
```

### 在你的服务中使用

```php
<?php

namespace YourBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class YourService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $registry
    ) {
        // 这些参数会通过 DatabaseServicesCompilerPass 自动绑定
    }
    
    public function doSomething(): void
    {
        // 使用专用的 EntityManager
        $repository = $this->entityManager->getRepository(YourEntity::class);
        
        // 或者使用 ManagerRegistry
        $em = $this->registry->getManager();
    }
}
```

## 环境变量配置

```bash
# .env 文件
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

## 多租户场景

```php
<?php

use Tourze\DoctrineAutoBindBundle\DB\DedicatedDatabaseConnectionFactory;

class TenantService
{
    public function __construct(
        private Connection $defaultConnection
    ) {
    }
    
    public function createTenantEntityManager(string $tenantId): EntityManager
    {
        $factory = new DedicatedDatabaseConnectionFactory(
            $this->defaultConnection,
            $tenantId,
            __DIR__ . '/Entity',
            "_{$tenantId}"
        );
        
        $connection = $factory->createConnection();
        return $factory->createEntityManager($connection);
    }
} 