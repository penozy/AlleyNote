<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

use AlleyNote\Infrastructure\Cache\RedisConnectionFactory;
use AlleyNote\Shared\Cache\CacheServiceInterface;
use AlleyNote\Shared\Exceptions\InfrastructureException;
use Psr\Container\ContainerInterface;

/**
 * 快取服務提供者
 * 
 * 負責建立與配置快取服務實例，支援不同的快取後端。
 * 
 * @author AI Assistant
 * @version 1.0
 */
final class CacheServiceProvider
{
    /**
     * 建立 Redis 快取服務
     * 
     * @param ContainerInterface|null $container DI 容器實例
     * @param array<string, mixed> $config Redis 配置
     * @return CacheServiceInterface Redis 快取服務實例
     * @throws InfrastructureException 當建立失敗時
     */
    public static function createRedisCache(
        ?ContainerInterface $container = null,
        array $config = []
    ): CacheServiceInterface {
        $defaultConfig = [
            'host' => $_ENV['REDIS_HOST'] ?? 'redis',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
            'prefix' => $_ENV['CACHE_PREFIX'] ?? 'alleynote:',
            'timeout' => (int) ($_ENV['REDIS_TIMEOUT'] ?? 2),
            'read_timeout' => (int) ($_ENV['REDIS_READ_TIMEOUT'] ?? 5),
        ];

        $config = array_merge($defaultConfig, $config);

        try {
            $redis = RedisConnectionFactory::create(
                host: $config['host'],
                port: $config['port'],
                password: $config['password'],
                database: $config['database'],
                timeout: $config['timeout'],
                readTimeout: $config['read_timeout']
            );

            return new RedisCache(
                host: $config['host'],
                port: $config['port'],
                prefix: $config['prefix'],
                database: $config['database'],
                password: $config['password']
            );
        } catch (\Throwable $e) {
            throw new InfrastructureException(
                "Failed to create Redis cache service: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * 建立記憶體快取服務（用於測試）
     * 
     * @return CacheServiceInterface 記憶體快取服務實例
     */
    public static function createMemoryCache(): CacheServiceInterface
    {
        return new MemoryCache();
    }

    /**
     * 根據配置建立適當的快取服務
     * 
     * @param string $driver 快取驅動程式類型
     * @param array<string, mixed> $config 配置參數
     * @return CacheServiceInterface 快取服務實例
     * @throws InfrastructureException 當驅動程式不支援時
     */
    public static function create(
        string $driver = 'redis',
        array $config = []
    ): CacheServiceInterface {
        return match (strtolower($driver)) {
            'redis' => self::createRedisCache(config: $config),
            'memory' => self::createMemoryCache(),
            default => throw new InfrastructureException(
                "Unsupported cache driver: {$driver}"
            ),
        };
    }
}
