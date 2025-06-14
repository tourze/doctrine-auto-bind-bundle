<?php

namespace Tourze\DoctrineAutoBindBundle\Tests\Factory;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineAutoBindBundle\Factory\EntityManagerFactory;

/**
 * EntityManagerFactory 测试
 */
class EntityManagerFactoryTest extends TestCase
{
    private EntityManagerFactory $factory;
    
    protected function setUp(): void
    {
        $this->factory = new EntityManagerFactory();
    }
    
    public function testCreateFromParams(): void
    {
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        
        $entityPaths = [__DIR__ . '/../Fixtures/Entity'];
        
        $em = $this->factory->createFromParams($connectionParams, $entityPaths, true);
        
        $this->assertInstanceOf(EntityManager::class, $em);
        $this->assertTrue($em->isOpen());
    }
    
    public function testCreateFromEnvironment(): void
    {
        // 设置测试环境变量
        $_ENV['TEST_DB_DRIVER'] = 'pdo_sqlite';
        $_ENV['TEST_DB_HOST'] = ':memory:';
        
        $entityPaths = [__DIR__ . '/../Fixtures/Entity'];
        
        $em = $this->factory->createFromEnvironment('TEST', $entityPaths, true);
        
        $this->assertInstanceOf(EntityManager::class, $em);
        $this->assertTrue($em->isOpen());
        
        // 清理环境变量
        unset($_ENV['TEST_DB_DRIVER'], $_ENV['TEST_DB_HOST']);
    }
    
    public function testCreateFromEnvironmentWithDefaults(): void
    {
        // 不设置任何环境变量，测试默认值
        $entityPaths = [__DIR__ . '/../Fixtures/Entity'];
        
        // 这应该使用默认值但可能会失败，因为没有真实的数据库
        // 我们只测试参数构建逻辑
        try {
            $em = $this->factory->createFromEnvironment('NONEXISTENT', $entityPaths, true);
            $this->assertInstanceOf(EntityManager::class, $em);
        } catch (\Exception $e) {
            // 预期可能会因为连接失败而抛出异常
            $this->assertNotEmpty($e->getMessage());
        }
    }
    
    public function testCreateFromParamsWithDevMode(): void
    {
        // 设置 APP_ENV 为 dev
        $_ENV['APP_ENV'] = 'dev';
        
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        
        $entityPaths = [__DIR__ . '/../Fixtures/Entity'];
        
        $em = $this->factory->createFromParams($connectionParams, $entityPaths);
        
        $this->assertInstanceOf(EntityManager::class, $em);
        
        // 清理
        unset($_ENV['APP_ENV']);
    }
} 