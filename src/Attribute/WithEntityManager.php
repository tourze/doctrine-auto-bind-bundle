<?php

namespace Tourze\DoctrineAutoBindBundle\Attribute;

/**
 * 用于标记服务需要特定的EntityManager
 * 类似于Symfony的 WithMonologChannel 模式
 *
 * 使用示例：
 * ```php
 * #[WithEntityManager('customer')]
 * class CustomerService
 * {
 *     public function __construct(
 *         private EntityManagerInterface $entityManager
 *     ) {
 *         // 自动注入 customer EntityManager
 *     }
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WithEntityManager
{
    public function __construct(
        public readonly string $manager = 'default',
        public readonly bool $lazy = false,
        public readonly array $repositories = []
    ) {}
}
