<?php

namespace Tourze\DoctrineAutoBindBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * EntityManager 服务定位器
 * 用于管理和获取多个 EntityManager 实例
 *
 * 使用示例：
 * ```php
 * class SomeService
 * {
 *     public function __construct(
 *         private EntityManagerLocator $emLocator
 *     ) {
 *     }
 *
 *     public function doSomething(): void
 *     {
 *         $defaultEm = $this->emLocator->get('default');
 *         $customerEm = $this->emLocator->get('customer');
 *         $analyticsEm = $this->emLocator->get('analytics');
 *     }
 * }
 * ```
 */
class EntityManagerLocator
{
    public function __construct(
        #[AutowireLocator([
            'default' => 'doctrine.orm.default_entity_manager',
        ])]
        private ServiceLocator $entityManagers
    ) {}

    /**
     * 获取指定名称的 EntityManager
     * 
     * @param string $name EntityManager 名称
     * @return EntityManagerInterface
     * @throws \InvalidArgumentException 如果 EntityManager 不存在
     */
    public function get(string $name): EntityManagerInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf(
                'EntityManager "%s" not found. Available: %s',
                $name,
                implode(', ', array_keys($this->entityManagers->getProvidedServices()))
            ));
        }

        return $this->entityManagers->get($name);
    }

    /**
     * 检查是否存在指定名称的 EntityManager
     */
    public function has(string $name): bool
    {
        return $this->entityManagers->has($name);
    }

    /**
     * 获取所有可用的 EntityManager 名称
     *
     * @return array<string>
     */
    public function getAvailable(): array
    {
        return array_keys($this->entityManagers->getProvidedServices());
    }

    /**
     * 获取默认的 EntityManager
     */
    public function getDefault(): EntityManagerInterface
    {
        return $this->get('default');
    }
}
