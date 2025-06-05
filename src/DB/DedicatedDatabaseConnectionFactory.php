<?php

namespace Tourze\DoctrineAutoBindBundle\DB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * 通用的专用数据库连接工厂
 * 可以为不同的bundle创建独立的数据库连接和EntityManager
 *
 * 使用示例：
 * ```php
 * // 创建工厂实例
 * $factory = new DedicatedDatabaseConnectionFactory(
 *     defaultConnection: $defaultConnection,
 *     servicePrefix: 'my_service',
 *     entityPath: __DIR__ . '/../Entity',
 *     defaultDatabaseSuffix: '_my_service'
 * );
 *
 * // 创建专用连接
 * $connection = $factory->createConnection();
 *
 * // 创建专用EntityManager
 * $entityManager = $factory->createEntityManager($connection);
 * ```
 *
 * 环境变量配置：
 * - MY_SERVICE_DB_HOST - 数据库主机
 * - MY_SERVICE_DB_PORT - 数据库端口
 * - MY_SERVICE_DB_NAME - 数据库名称
 * - MY_SERVICE_DB_USER - 数据库用户
 * - MY_SERVICE_DB_PASSWORD - 数据库密码
 * - MY_SERVICE_DB_DRIVER - 数据库驱动
 * - MY_SERVICE_DB_CHARSET - 字符集
 *
 * 如果未设置MY_SERVICE_DB_NAME，将使用默认数据库名称加上后缀
 */
class DedicatedDatabaseConnectionFactory
{
    private ?Connection $dedicatedConnection = null;
    private ?EntityManager $dedicatedEntityManager = null;

    public function __construct(
        private readonly Connection $defaultConnection,
        private readonly string $servicePrefix,
        private readonly string $entityPath,
        private readonly string $defaultDatabaseSuffix = '',
    ) {
    }

    /**
     * 创建专用的数据库连接
     */
    public function createConnection(): Connection
    {
        if ($this->dedicatedConnection !== null) {
            return $this->dedicatedConnection;
        }

        // 读取默认连接的配置参数
        $defaultParams = $this->defaultConnection->getParams();

        // 创建新的连接配置，可以根据环境变量自定义数据库
        $dedicatedParams = $this->buildDedicatedConnectionParams($defaultParams);

        // 创建专门的连接
        $this->dedicatedConnection = DriverManager::getConnection($dedicatedParams);

        return $this->dedicatedConnection;
    }

    /**
     * 创建专用的EntityManager
     */
    public function createEntityManager(Connection $connection): EntityManager
    {
        if ($this->dedicatedEntityManager !== null) {
            return $this->dedicatedEntityManager;
        }

        // 配置 ORM
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [$this->entityPath],
            isDevMode: $_ENV['APP_ENV'] === 'dev',
        );

        // 创建 EntityManager
        $this->dedicatedEntityManager = new EntityManager($connection, $config);

        return $this->dedicatedEntityManager;
    }

    /**
     * 构建专用连接参数
     * 支持通过环境变量自定义数据库配置
     */
    private function buildDedicatedConnectionParams(array $defaultParams): array
    {
        $params = $defaultParams;
        $envPrefix = strtoupper($this->servicePrefix);

        // 支持通过环境变量覆盖数据库配置
        if (isset($_ENV["{$envPrefix}_DB_HOST"])) {
            $params['host'] = $_ENV["{$envPrefix}_DB_HOST"];
        }

        if (isset($_ENV["{$envPrefix}_DB_PORT"])) {
            $params['port'] = (int) $_ENV["{$envPrefix}_DB_PORT"];
        }

        if (isset($_ENV["{$envPrefix}_DB_NAME"])) {
            $params['dbname'] = $_ENV["{$envPrefix}_DB_NAME"];
        }

        if (isset($_ENV["{$envPrefix}_DB_USER"])) {
            $params['user'] = $_ENV["{$envPrefix}_DB_USER"];
        }

        if (isset($_ENV["{$envPrefix}_DB_PASSWORD"])) {
            $params['password'] = $_ENV["{$envPrefix}_DB_PASSWORD"];
        }

        if (isset($_ENV["{$envPrefix}_DB_DRIVER"])) {
            $params['driver'] = $_ENV["{$envPrefix}_DB_DRIVER"];
        }

        if (isset($_ENV["{$envPrefix}_DB_CHARSET"])) {
            $params['charset'] = $_ENV["{$envPrefix}_DB_CHARSET"];
        }

        // 如果没有环境变量配置，使用默认连接但加上后缀区分
        if (!isset($_ENV["{$envPrefix}_DB_NAME"]) && isset($params['dbname'])) {
            $suffix = $this->defaultDatabaseSuffix ?: "_{$this->servicePrefix}";
            $params['dbname'] = $params['dbname'] . $suffix;
        }

        return $params;
    }

    /**
     * 获取当前的专用连接
     */
    public function getConnection(): ?Connection
    {
        return $this->dedicatedConnection;
    }

    /**
     * 获取当前的专用EntityManager
     */
    public function getEntityManager(): ?EntityManager
    {
        return $this->dedicatedEntityManager;
    }
}
