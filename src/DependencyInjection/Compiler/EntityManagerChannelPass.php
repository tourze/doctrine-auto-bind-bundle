<?php

namespace Tourze\DoctrineAutoBindBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineAutoBindBundle\Attribute\WithEntityManager;

/**
 * 编译器通道，用于处理 WithEntityManager 属性
 * 类似于 Symfony 的 MonologLoggerPass
 *
 * 功能：
 * - 扫描所有标记了 WithEntityManager 属性的服务类
 * - 为这些服务自动绑定对应的 EntityManager
 * - 创建自动装配别名以支持参数绑定
 */
class EntityManagerChannelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();

            if (!$class || !class_exists($class)) {
                continue;
            }

            try {
                $reflectionClass = new \ReflectionClass($class);
                $attributes = $reflectionClass->getAttributes(WithEntityManager::class);

                if (empty($attributes)) {
                    continue;
                }

                $attribute = $attributes[0]->newInstance();
                $managerName = $attribute->manager;

                $this->configureEntityManagerForService($container, $serviceId, $managerName, $attribute);
            } catch (\ReflectionException $e) {
                // 如果反射失败，跳过这个服务
                continue;
            }
        }
    }

    private function configureEntityManagerForService(
        ContainerBuilder $container,
        string $serviceId,
        string $managerName,
        WithEntityManager $attribute
    ): void {
        $definition = $container->getDefinition($serviceId);
        $entityManagerServiceId = "doctrine.orm.{$managerName}_entity_manager";

        // 检查 EntityManager 服务是否存在
        if (!$container->hasDefinition($entityManagerServiceId) && !$container->hasAlias($entityManagerServiceId)) {
            // 如果不存在，尝试创建或记录警告
            if ($managerName !== 'default') {
                // 对于非默认的 EntityManager，我们可能需要动态创建
                $this->createEntityManagerIfNeeded($container, $managerName);
            }
        }

        // 为指定的 Entity Manager 创建自动装配别名
        $aliasId = $managerName . 'EntityManager';
        if (!$container->hasAlias(EntityManagerInterface::class . ' $' . $aliasId)) {
            $container->registerAliasForArgument(
                $entityManagerServiceId,
                EntityManagerInterface::class,
                $aliasId
            );
        }

        // 使用参数绑定
        $bindings = $definition->getBindings();
        $bindings[EntityManagerInterface::class] = new Reference($entityManagerServiceId);

        // 如果指定了 repositories，也可以预先绑定
        foreach ($attribute->repositories as $repositoryClass) {
            $repositoryServiceId = $this->getRepositoryServiceId($repositoryClass, $managerName);
            if ($container->hasDefinition($repositoryServiceId)) {
                $bindings[$repositoryClass] = new Reference($repositoryServiceId);
            }
        }

        $definition->setBindings($bindings);
    }

    private function createEntityManagerIfNeeded(ContainerBuilder $container, string $managerName): void
    {
        // 这里可以实现动态创建 EntityManager 的逻辑
        // 为了简化，先留空，由具体的 CompilerPass 处理
    }

    private function getRepositoryServiceId(string $repositoryClass, string $managerName): string
    {
        // 生成 repository 服务 ID
        return "doctrine.orm.{$managerName}_entity_manager.repository." . str_replace('\\', '_', $repositoryClass);
    }
}
