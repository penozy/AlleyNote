<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Providers;

use App\Shared\Contracts\CacheInterface;
use DI\Container;
use Psr\Container\ContainerInterface;

/**
 * 快取服務提供者.
 *
 * 註冊快取相關的服務到 DI 容器中
 */
class CacheServiceProvider
{
    /**
     * 取得服務定義.
     *
     * @return array<string, mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            // 註冊快取介面實作
            CacheInterface::class => function (ContainerInterface $container): CacheInterface {
                // 從環境變數取得 Redis 設定
                $host = $_ENV['REDIS_HOST'] ?? 'redis';
                $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
                $prefix = $_ENV['REDIS_PREFIX'] ?? 'alleynote:';
                $database = (int) ($_ENV['REDIS_DATABASE'] ?? 0);
                $password = $_ENV['REDIS_PASSWORD'] ?? null;

                return new AppRedisCache(
                    host: $host,
                    port: $port,
                    prefix: $prefix,
                    database: $database,
                    password: $password,
                );
            },

            // 別名註冊
            'cache' => \DI\get(CacheInterface::class),
        ];
    }

    /**
     * 註冊服務.
     */
    public function register(Container $container): void
    {
        foreach (self::getDefinitions() as $key => $definition) {
            $container->set($key, $definition);
        }
    }
}
