<?php

namespace Tourze\DoctrineAutoBindBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Tourze\DoctrineAutoBindBundle\Service\EntityManagerLocator;

/**
 * EntityManagerLocator 测试
 */
class EntityManagerLocatorTest extends TestCase
{
    public function testGetEntityManager(): void
    {
        $defaultEM = $this->createMock(EntityManagerInterface::class);
        $customerEM = $this->createMock(EntityManagerInterface::class);
        
        $serviceLocator = new ServiceLocator([
            'default' => fn() => $defaultEM,
            'customer' => fn() => $customerEM,
        ]);
        
        $locator = new EntityManagerLocator($serviceLocator);
        
        $this->assertSame($defaultEM, $locator->get('default'));
        $this->assertSame($customerEM, $locator->get('customer'));
        $this->assertSame($defaultEM, $locator->getDefault());
    }
    
    public function testHasEntityManager(): void
    {
        $serviceLocator = new ServiceLocator([
            'default' => fn() => $this->createMock(EntityManagerInterface::class),
        ]);
        
        $locator = new EntityManagerLocator($serviceLocator);
        
        $this->assertTrue($locator->has('default'));
        $this->assertFalse($locator->has('nonexistent'));
    }
    
    public function testGetAvailable(): void
    {
        $serviceLocator = new ServiceLocator([
            'default' => fn() => $this->createMock(EntityManagerInterface::class),
            'customer' => fn() => $this->createMock(EntityManagerInterface::class),
        ]);
        
        $locator = new EntityManagerLocator($serviceLocator);
        
        $available = $locator->getAvailable();
        $this->assertContains('default', $available);
        $this->assertContains('customer', $available);
        $this->assertCount(2, $available);
    }
    
    public function testGetNonexistentEntityManagerThrowsException(): void
    {
        $serviceLocator = new ServiceLocator([]);
        $locator = new EntityManagerLocator($serviceLocator);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EntityManager "nonexistent" not found/');
        
        $locator->get('nonexistent');
    }
} 