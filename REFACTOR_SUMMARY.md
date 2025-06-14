# 重构总结：移除 YAML 配置架构

## 重构目标

根据用户要求，移除所有 YAML 配置相关代码，改为纯代码配置方式，实现完整的 Doctrine DBAL 和 EntityManager 自动注册功能。

## 已删除的组件

### 1. Configuration.php
- 删除了复杂的 YAML 配置树定义
- 移除了 `entity_managers` 配置节点
- 简化了配置处理逻辑

### 2. 复杂的扩展逻辑
- 简化了 `DoctrineAutoBindExtension`，只保留服务加载
- 移除了配置处理和 CompilerPass 注册逻辑
- 删除了 `DynamicDoctrineCompilerPass`

## 新的架构设计

### 1. 核心组件
```
EntityManagerFactory (新增)
├── createFromEnvironment() - 从环境变量创建
├── createFromParams() - 从参数创建
└── buildConnectionParamsFromEnv() - 环境变量解析

EntityManagerLocator (保留)
├── get() - 获取指定 EntityManager
├── has() - 检查是否存在
└── getDefault() - 获取默认 EntityManager

WithEntityManager (保留)
└── 属性注入支持

DatabaseServicesCompilerPass (保留)
└── 在其他 Bundle 中使用的专用服务注册
```

### 2. 使用方式

#### 方式 A：属性注入（最简单）
```php
#[WithEntityManager('customer')]
class CustomerService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
}
```

#### 方式 B：服务定位器（多 EntityManager）
```php
class MultiService
{
    public function __construct(
        private EntityManagerLocator $locator
    ) {}
    
    public function useCustomer(): void
    {
        $em = $this->locator->get('customer');
    }
}
```

#### 方式 C：工厂模式（动态创建）
```php
class DatabaseService
{
    public function __construct(
        private EntityManagerFactory $factory
    ) {}
    
    public function setupDatabase(): void
    {
        $em = $this->factory->createFromEnvironment('CUSTOM', $paths);
    }
}
```

#### 方式 D：CompilerPass（Bundle集成）
```php
class YourBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DatabaseServicesCompilerPass(
            servicePrefix: 'your_service',
            entityPath: __DIR__ . '/Entity',
            entityNamespace: 'YourBundle\\Entity',
            serviceNamespace: 'YourBundle'
        ));
    }
}
```

## 环境变量配置

### 支持的环境变量模式
```bash
# 客户数据库
CUSTOMER_DB_HOST=localhost
CUSTOMER_DB_PORT=3306
CUSTOMER_DB_NAME=customer_db
CUSTOMER_DB_USER=customer_user
CUSTOMER_DB_PASSWORD=customer_pass
CUSTOMER_DB_DRIVER=pdo_mysql
CUSTOMER_DB_CHARSET=utf8mb4

# 分析数据库  
ANALYTICS_DB_HOST=analytics.example.com
ANALYTICS_DB_NAME=analytics_db
# ... 其他配置
```

## 优势

### 1. 简化配置
- 无需复杂的 YAML 配置文件
- 直接通过代码和环境变量配置
- 更直观的配置方式

### 2. 灵活性增强
- 支持运行时动态创建 EntityManager
- 多种使用模式适应不同场景
- 更好的类型安全和IDE支持

### 3. 减少复杂性
- 移除了复杂的配置树解析
- 简化了 Bundle 结构
- 更少的抽象层次

### 4. 更好的测试支持
- 易于Mock和测试
- 支持内存数据库测试
- 独立的组件测试

## 兼容性

### 保持的功能
- ✅ WithEntityManager 属性注入
- ✅ EntityManagerLocator 服务定位器
- ✅ DatabaseServicesCompilerPass 集成
- ✅ 环境变量支持
- ✅ 多租户支持

### 移除的功能
- ❌ YAML 配置支持
- ❌ 自动 CompilerPass 注册
- ❌ 复杂的配置树定义

## 迁移指南

### 从 YAML 配置迁移到代码配置

**旧方式（YAML）：**
```yaml
doctrine_auto_bind:
    entity_managers:
        customer:
            env_prefix: 'CUSTOMER'
            mappings:
                - type: attribute
                  paths: ['src/Entity/Customer']
```

**新方式（代码）：**
```php
// 方式1：使用工厂
$em = $factory->createFromEnvironment('CUSTOMER', ['src/Entity/Customer']);

// 方式2：在Bundle中使用CompilerPass
$container->addCompilerPass(new DatabaseServicesCompilerPass(
    servicePrefix: 'customer',
    entityPath: 'src/Entity/Customer',
    entityNamespace: 'App\\Entity\\Customer',
    serviceNamespace: 'App\\Customer'
));
```

## 总结

这次重构成功移除了所有 YAML 配置依赖，提供了更灵活、更简单的代码配置方式。新架构保持了原有功能的完整性，同时提供了更好的开发体验和测试支持。 