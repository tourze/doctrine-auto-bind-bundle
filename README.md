# Doctrine Auto Bind Bundle

A Symfony Bundle that provides automatic registration and management of multiple Doctrine Entity Managers with smart dependency injection.

## Features

- 🚀 **Automatic Entity Manager Registration**: Dynamically register multiple Entity Managers through code
- 🏷️ **Attribute-based Injection**: Use `#[WithEntityManager]` attribute for clean dependency injection
- 🔧 **Environment Variable Support**: Configure databases using environment variables
- 📦 **Service Locator Pattern**: Access multiple Entity Managers through a centralized locator
- 🏗️ **Flexible Configuration**: Support for multiple database connections and entity mappings
- 🧪 **Test-Friendly**: Easy to test with dedicated test utilities

## Installation

```bash
composer require tourze/doctrine-auto-bind-bundle
```

## Quick Start

### 1. Using WithEntityManager Attribute

```php
use Doctrine\ORM\EntityManagerInterface;
use Tourze\DoctrineAutoBindBundle\Attribute\WithEntityManager;

#[WithEntityManager('customer')]
class CustomerService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        // Automatically injects the 'customer' EntityManager
    }
    
    public function findCustomers(): array
    {
        return $this->entityManager->getRepository(Customer::class)->findAll();
    }
}
```

### 2. Using EntityManagerLocator

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
            // Use customer EntityManager
        }
    }
}
```

### 3. Using EntityManagerFactory

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
        // Create from environment variables
        $customerEM = $this->factory->createFromEnvironment(
            'CUSTOMER', 
            ['src/Entity/Customer']
        );
        
        // Create from connection parameters
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

## Environment Variables

For database configuration, set environment variables with prefixes:

```bash
# .env
# Customer database
CUSTOMER_DB_HOST=localhost
CUSTOMER_DB_PORT=3306
CUSTOMER_DB_NAME=customer_db
CUSTOMER_DB_USER=customer_user
CUSTOMER_DB_PASSWORD=customer_pass
CUSTOMER_DB_DRIVER=pdo_mysql
CUSTOMER_DB_CHARSET=utf8mb4

# Analytics database
ANALYTICS_DB_HOST=analytics.example.com
ANALYTICS_DB_PORT=3306
ANALYTICS_DB_NAME=analytics_db
ANALYTICS_DB_USER=analytics_user
ANALYTICS_DB_PASSWORD=analytics_pass
```

## Advanced Usage

### Using DatabaseServicesCompilerPass in Your Bundle

For more control over Entity Manager registration, use the `DatabaseServicesCompilerPass` directly in your bundle:

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

This will automatically bind the following parameters for services in your namespace:
- `$entityManager` - Your dedicated EntityManager
- `$registry` - Your dedicated ManagerRegistry
- `$connection` - Your dedicated database Connection

### Multi-tenant Applications

The bundle supports multi-tenant scenarios through dynamic connection switching:

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

## Available Services

The bundle registers the following services:

- `Tourze\DoctrineAutoBindBundle\Service\EntityManagerLocator` - Service locator for Entity Managers
- `Tourze\DoctrineAutoBindBundle\Factory\EntityManagerFactory` - Factory for creating Entity Managers
- `Tourze\DoctrineAutoBindBundle\DB\DedicatedDatabaseConnectionFactory` - Factory for dedicated connections

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

- Inspired by Symfony's MonologBundle channel pattern
- Built following Symfony best practices and conventions
- Implements patterns from the official Doctrine Bundle

## Related Documentation

- [Symfony Dependency Injection](https://symfony.com/doc/current/service_container.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Multiple Entity Managers](https://symfony.com/doc/current/doctrine/multiple_entity_managers.html)
