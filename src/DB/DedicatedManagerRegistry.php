<?php

namespace Tourze\DoctrineAutoBindBundle\DB;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

/**
 * 通用的专用ManagerRegistry
 * 可以为不同的bundle提供独立的ManagerRegistry实现
 *
 * 使用示例：
 * ```php
 * // 创建专用ManagerRegistry
 * $registry = new DedicatedManagerRegistry(
 *     dedicatedEntityManager: $entityManager,
 *     servicePrefix: 'my_service',
 *     entityNamespace: 'App\\MyBundle\\Entity'
 * );
 *
 * // 获取EntityManager
 * $em = $registry->getManager();
 *
 * // 获取Repository
 * $repo = $registry->getRepository(MyEntity::class);
 *
 * // 检查是否管理某个实体类
 * $manager = $registry->getManagerForClass('App\\MyBundle\\Entity\\MyEntity');
 * ```
 *
 * 注意：
 * - 此ManagerRegistry只管理指定命名空间下的实体
 * - 连接名称和管理器名称都使用servicePrefix
 * - 不支持管理器重置操作
 */
class DedicatedManagerRegistry implements ManagerRegistry
{
    public function __construct(
        private readonly EntityManager $dedicatedEntityManager,
        private readonly string $servicePrefix,
        private readonly string $entityNamespace,
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return $this->servicePrefix;
    }

    public function getConnection(?string $name = null): object
    {
        return $this->dedicatedEntityManager->getConnection();
    }

    public function getConnections(): array
    {
        return [$this->servicePrefix => $this->dedicatedEntityManager->getConnection()];
    }

    public function getConnectionNames(): array
    {
        return [$this->servicePrefix];
    }

    public function getDefaultManagerName(): string
    {
        return $this->servicePrefix;
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->dedicatedEntityManager;
    }

    public function getManagers(): array
    {
        return [$this->servicePrefix => $this->dedicatedEntityManager];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        // 专用EntityManager不支持重置，直接返回
        return $this->dedicatedEntityManager;
    }

    public function getAliasNamespace(string $alias): string
    {
        return $this->entityNamespace;
    }

    public function getManagerNames(): array
    {
        return [$this->servicePrefix];
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        // 只管理指定命名空间下的实体
        if (str_starts_with($class, $this->entityNamespace . '\\')) {
            return $this->dedicatedEntityManager;
        }
        return null;
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->dedicatedEntityManager->getRepository($persistentObject);
    }
}
