<?php

namespace Tourze\DoctrineAutoBindBundle\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * EntityManager 工厂类
 * 用于直接创建和管理 EntityManager 实例
 *
 * 使用示例：
 * ```php
 * $factory = new EntityManagerFactory();
 *
 * // 从环境变量创建
 * $customerEM = $factory->createFromEnvironment('CUSTOMER', ['src/Entity/Customer']);
 *
 * // 从连接参数创建
 * $analyticsEM = $factory->createFromParams([
 *     'driver' => 'pdo_mysql',
 *     'host' => 'localhost',
 *     'dbname' => 'analytics',
 *     'user' => 'user',
 *     'password' => 'pass'
 * ], ['src/Entity/Analytics']);
 * ```
 */
class EntityManagerFactory
{
    /**
     * 从环境变量创建 EntityManager
     *
     * @param string $envPrefix 环境变量前缀（如 'CUSTOMER'）
     * @param array $entityPaths 实体路径数组
     * @param bool $isDevMode 是否开发模式
     * @return EntityManager
     */
    public function createFromEnvironment(string $envPrefix, array $entityPaths, bool $isDevMode = null): EntityManager
    {
        $connectionParams = $this->buildConnectionParamsFromEnv($envPrefix);
        return $this->createFromParams($connectionParams, $entityPaths, $isDevMode);
    }

    /**
     * 从连接参数创建 EntityManager
     *
     * @param array $connectionParams 连接参数
     * @param array $entityPaths 实体路径数组
     * @param bool $isDevMode 是否开发模式
     * @return EntityManager
     */
    public function createFromParams(array $connectionParams, array $entityPaths, bool $isDevMode = null): EntityManager
    {
        if ($isDevMode === null) {
            $isDevMode = $_ENV['APP_ENV'] === 'dev';
        }

        // 创建连接
        $connection = DriverManager::getConnection($connectionParams);

        // 创建 ORM 配置
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $entityPaths,
            isDevMode: $isDevMode
        );

        // 创建 EntityManager
        return new EntityManager($connection, $config);
    }

    /**
     * 从环境变量构建连接参数
     */
    private function buildConnectionParamsFromEnv(string $envPrefix): array
    {
        $params = [];
        $envPrefix = strtoupper($envPrefix);

        $envMappings = [
            'driver' => "{$envPrefix}_DB_DRIVER",
            'host' => "{$envPrefix}_DB_HOST",
            'port' => "{$envPrefix}_DB_PORT",
            'dbname' => "{$envPrefix}_DB_NAME",
            'user' => "{$envPrefix}_DB_USER",
            'password' => "{$envPrefix}_DB_PASSWORD",
            'charset' => "{$envPrefix}_DB_CHARSET",
        ];

        foreach ($envMappings as $param => $envVar) {
            if (isset($_ENV[$envVar])) {
                $value = $_ENV[$envVar];
                if ($param === 'port') {
                    $value = (int) $value;
                }
                $params[$param] = $value;
            }
        }

        // 设置默认值
        $params['driver'] = $params['driver'] ?? 'pdo_mysql';
        $params['charset'] = $params['charset'] ?? 'utf8mb4';

        return $params;
    }
}
