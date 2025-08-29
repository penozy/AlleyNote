<?php

declare(strict_types=1);

namespace App\Domains\Security\Providers;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Domains\Security\Repositories\ActivityLogRepository;
use App\Domains\Security\Services\ActivityLoggingService;
use App\Domains\Security\Services\CachedActivityLoggingService;
use App\Shared\Contracts\CacheInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Security 領域服務提供者.
 *
 * 負責註冊所有 Security 相關服務到 DI 容器
 */
class SecurityServiceProvider
{
    /**
     * 取得所有 Security 服務定義.
     */
    public static function getDefinitions(): array
    {
        return [
            // Activity Log Repository Interface
            ActivityLogRepositoryInterface::class => \DI\create(ActivityLogRepository::class),

            // Base Activity Logging Service
            'base_activity_logging_service' => \DI\factory([self::class, 'createActivityLoggingService']),

            // Cached Activity Logging Service (預設實作)
            ActivityLoggingServiceInterface::class => \DI\factory([self::class, 'createCachedActivityLoggingService']),
        ];
    }

    /**
     * 建立 ActivityLoggingService 實例.
     */
    public static function createActivityLoggingService(ContainerInterface $container): ActivityLoggingService
    {
        $repository = $container->get(ActivityLogRepositoryInterface::class);

        // 使用簡單的 error_log 作為 logger（暫時解決方案）
        $logger = new class implements LoggerInterface {
            public function emergency(Stringable|string $message, array $context = []): void
            {
                error_log("[EMERGENCY] $message");
            }

            public function alert(Stringable|string $message, array $context = []): void
            {
                error_log("[ALERT] $message");
            }

            public function critical(Stringable|string $message, array $context = []): void
            {
                error_log("[CRITICAL] $message");
            }

            public function error(Stringable|string $message, array $context = []): void
            {
                error_log("[ERROR] $message");
            }

            public function warning(Stringable|string $message, array $context = []): void
            {
                error_log("[WARNING] $message");
            }

            public function notice(Stringable|string $message, array $context = []): void
            {
                error_log("[NOTICE] $message");
            }

            public function info(Stringable|string $message, array $context = []): void
            {
                error_log("[INFO] $message");
            }

            public function debug(Stringable|string $message, array $context = []): void
            {
                error_log("[DEBUG] $message");
            }

            public function log($level, Stringable|string $message, array $context = []): void
            {
                error_log("[$level] $message");
            }
        };

        return new ActivityLoggingService($repository, $logger);
    }

    /**
     * 建立 CachedActivityLoggingService 實例.
     */
    public static function createCachedActivityLoggingService(ContainerInterface $container): CachedActivityLoggingService
    {
        $baseService = $container->get('base_activity_logging_service');
        $cache = $container->get(CacheInterface::class);

        return new CachedActivityLoggingService($baseService, $cache);
    }
}
