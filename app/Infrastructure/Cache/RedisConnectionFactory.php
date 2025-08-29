<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

use AlleyNote\Shared\Exceptions\InfrastructureException;
use Redis;
use RedisException;

/**
 * Redis 連線工廠
 * 
 * 負責建立與管理 Redis 連線實例，支援連接池、重連機制等功能。
 * 
 * @author AI Assistant
 * @version 1.0
 */
final class RedisConnectionFactory
{
    private const int DEFAULT_TIMEOUT = 2;
    private const int DEFAULT_READ_TIMEOUT = 5;
    private const int DEFAULT_RETRY_INTERVAL = 100;

    /**
     * 建立 Redis 連線
     * 
     * @param string $host Redis 主機地址
     * @param int $port Redis 埠號
     * @param string|null $password Redis 密碼
     * @param int $database 資料庫編號
     * @param int $timeout 連線逾時（秒）
     * @param int $readTimeout 讀取逾時（秒）
     * @param int $retryInterval 重試間隔（毫秒）
     * @return Redis Redis 連線實例
     * @throws InfrastructureException 當連線失敗時
     */
    public static function create(
        string $host = 'redis',
        int $port = 6379,
        ?string $password = null,
        int $database = 0,
        int $timeout = self::DEFAULT_TIMEOUT,
        int $readTimeout = self::DEFAULT_READ_TIMEOUT,
        int $retryInterval = self::DEFAULT_RETRY_INTERVAL
    ): Redis {
        $redis = new Redis();

        try {
            // 設定連線參數
            $connected = $redis->connect(
                $host,
                $port,
                $timeout,
                null,
                $retryInterval,
                $readTimeout
            );

            if (!$connected) {
                throw new InfrastructureException(
                    "Unable to connect to Redis server at {$host}:{$port}"
                );
            }

            // 驗證密碼（如果有）
            if ($password !== null && !$redis->auth($password)) {
                throw new InfrastructureException('Redis authentication failed');
            }

            // 選擇資料庫
            if (!$redis->select($database)) {
                throw new InfrastructureException(
                    "Unable to select Redis database {$database}"
                );
            }

            // 設定序列化模式
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            
            // 設定讀取逾時
            $redis->setOption(Redis::OPT_READ_TIMEOUT, $readTimeout);

            return $redis;
        } catch (RedisException $e) {
            throw new InfrastructureException(
                "Failed to initialize Redis connection: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * 測試 Redis 連線
     * 
     * @param Redis $redis Redis 實例
     * @return bool 連線正常返回 true
     */
    public static function testConnection(Redis $redis): bool
    {
        try {
            $response = $redis->ping();
            return $response === true || $response === '+PONG';
        } catch (RedisException) {
            return false;
        }
    }

    /**
     * 取得 Redis 連線資訊
     * 
     * @param Redis $redis Redis 實例
     * @return array<string, mixed> 連線資訊
     */
    public static function getConnectionInfo(Redis $redis): array
    {
        try {
            return [
                'connected' => $redis->isConnected(),
                'host' => $redis->getHost(),
                'port' => $redis->getPort(),
                'database' => $redis->getDBNum(),
                'timeout' => $redis->getTimeout(),
                'read_timeout' => $redis->getReadTimeout(),
                'persistent_id' => $redis->getPersistentID(),
                'auth' => $redis->getAuth(),
            ];
        } catch (RedisException) {
            return [
                'connected' => false,
                'error' => 'Unable to retrieve connection info',
            ];
        }
    }
}