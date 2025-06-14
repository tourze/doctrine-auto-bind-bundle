<?php

namespace Tourze\DoctrineAutoBindBundle;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineAutoBindBundle\Attribute\WithEntityManager;
use Tourze\DoctrineAutoBindBundle\DependencyInjection\Compiler\EntityManagerChannelPass;

/**
 * DoctrineAutoBindBundle 主类
 * 负责注册编译器通道和自动配置
 */
class DoctrineAutoBindBundle extends Bundle
{
    public function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
    {
        parent::build($container);

        // 添加编译器通道
        $container->addCompilerPass(new EntityManagerChannelPass());

        // 注册 WithEntityManager 属性的自动配置
        $this->registerWithEntityManagerAutoconfiguration($container);
    }

    /**
     * 注册 WithEntityManager 属性的自动配置
     */
    private function registerWithEntityManagerAutoconfiguration(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            WithEntityManager::class,
            static function (
                ChildDefinition $definition,
                WithEntityManager $attribute
            ): void {
                $managerServiceId = "doctrine.orm.{$attribute->manager}_entity_manager";

                // 绑定 EntityManager
                $bindings = $definition->getBindings();
                $bindings[EntityManagerInterface::class] = new Reference($managerServiceId);
                $definition->setBindings($bindings);

                // 添加标签以便编译器通道处理
                $definition->addTag('doctrine_auto_bind.with_entity_manager', [
                    'manager' => $attribute->manager,
                    'lazy' => $attribute->lazy,
                ]);
            }
        );
    }
}
