<?php

declare(strict_types=1);

namespace App\Domains\Security\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Shared\Contracts\CacheInterface;

/**
 * 快取增強的活動記錄服務.
 *
 * 使用 Decorator 模式為原有的 ActivityLoggingService 增加快取功能。
 * 快取策略：
 * - 使用者活動記錄快取（15分鐘 TTL）
 * - 統計資料快取（1小時 TTL）
 * - 安全事件快取（5分鐘 TTL）
 * - 配置設定快取（1小時 TTL）
 *
 * @author AI Assistant
 * @version 1.0
 */
final class CachedActivityLoggingService implements ActivityLoggingServiceInterface
{
    private const int USER_ACTIVITY_CACHE_TTL = 900; // 15 分鐘

    private const int STATS_CACHE_TTL = 3600; // 1 小時

    private const int SECURITY_EVENT_CACHE_TTL = 300; // 5 分鐘

    private const int CONFIG_CACHE_TTL = 3600; // 1 小時

    private const string CACHE_KEY_PREFIX = 'activity_log:';

    public function __construct(
        private readonly ActivityLoggingServiceInterface $decoratedService,
        private readonly CacheInterface $cache,
    ) {}

    public function log(CreateActivityLogDTO $dto): bool
    {
        $result = $this->decoratedService->log($dto);

        if ($result) {
            $this->invalidateUserCache($dto->getUserId());
            $this->invalidateStatsCache();

            // 如果是安全事件，也清理安全事件快取
            if ($this->isSecurityEvent($dto->getActionType())) {
                $this->invalidateSecurityEventCache();
            }
        }

        return $result;
    }

    public function logSuccess(
        ActivityType $actionType,
        ?int $userId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $metadata = null,
    ): bool {
        $result = $this->decoratedService->logSuccess(
            $actionType,
            $userId,
            $targetType,
            $targetId,
            $metadata,
        );

        if ($result) {
            if ($userId !== null) {
                $this->invalidateUserCache($userId);
            }
            $this->invalidateStatsCache();

            if ($this->isSecurityEvent($actionType)) {
                $this->invalidateSecurityEventCache();
            }
        }

        return $result;
    }

    public function logFailure(
        ActivityType $actionType,
        ?int $userId = null,
        string $reason = '',
        ?array $metadata = null,
    ): bool {
        $result = $this->decoratedService->logFailure(
            $actionType,
            $userId,
            $reason,
            $metadata,
        );

        if ($result) {
            if ($userId !== null) {
                $this->invalidateUserCache($userId);
            }
            $this->invalidateStatsCache();

            // 失敗記錄通常是安全相關事件
            $this->invalidateSecurityEventCache();
        }

        return $result;
    }

    public function logSecurityEvent(
        ActivityType $actionType,
        string $description,
        ?array $metadata = null,
    ): bool {
        $result = $this->decoratedService->logSecurityEvent(
            $actionType,
            $description,
            $metadata,
        );

        if ($result) {
            $this->invalidateSecurityEventCache();
            $this->invalidateStatsCache();
        }

        return $result;
    }

    public function logBatch(array $dtos): int
    {
        $count = $this->decoratedService->logBatch($dtos);

        if ($count > 0) {
            // 清理所有相關的快取
            $this->invalidateAllCaches();
        }

        return $count;
    }

    public function enableLogging(ActivityType $actionType): void
    {
        $this->decoratedService->enableLogging($actionType);

        // 清理配置相關快取
        $this->invalidateConfigCache();
    }

    public function disableLogging(ActivityType $actionType): void
    {
        $this->decoratedService->disableLogging($actionType);

        // 清理配置相關快取
        $this->invalidateConfigCache();
    }

    public function isLoggingEnabled(ActivityType $actionType): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "config:enabled:{$actionType->value}";

        // 嘗試從快取獲取
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // 從原始服務獲取並快取結果
        $enabled = $this->decoratedService->isLoggingEnabled($actionType);
        $this->cache->set($cacheKey, $enabled, self::CONFIG_CACHE_TTL);

        return $enabled;
    }

    public function setLogLevel(int $level): void
    {
        $this->decoratedService->setLogLevel($level);

        // 清理配置相關快取
        $this->invalidateConfigCache();
    }

    public function cleanup(): int
    {
        $count = $this->decoratedService->cleanup();

        if ($count > 0) {
            // 清理所有快取，因為資料可能已被刪除
            $this->invalidateAllCaches();
        }

        return $count;
    }

    /**
     * 獲取快取統計資訊.
     *
     * @return array<string, mixed> 快取統計資料
     */
    public function getCacheStats(): array
    {
        return [
            'cache_backend' => get_class($this->cache),
            'cache_ttl' => [
                'user_activity' => self::USER_ACTIVITY_CACHE_TTL,
                'stats' => self::STATS_CACHE_TTL,
                'security_events' => self::SECURITY_EVENT_CACHE_TTL,
                'config' => self::CONFIG_CACHE_TTL,
            ],
        ];
    }

    /**
     * 清理特定使用者的快取.
     *
     * @param int $userId 使用者 ID
     * @return bool 清理是否成功
     */
    public function clearUserCache(int $userId): bool
    {
        return $this->invalidateUserCache($userId);
    }

    /**
     * 檢查是否為安全事件.
     */
    private function isSecurityEvent(ActivityType $actionType): bool
    {
        return match ($actionType) {
            ActivityType::LOGIN_FAILED,
            ActivityType::LOGOUT,
            ActivityType::PASSWORD_CHANGED,
            ActivityType::ACCOUNT_LOCKED => true,
            default => false,
        };
    }

    /**
     * 清理使用者快取.
     */
    private function invalidateUserCache(?int $userId): bool
    {
        if ($userId === null) {
            return true;
        }

        $key = self::CACHE_KEY_PREFIX . "user:{$userId}:activities";

        return $this->cache->delete($key);
    }

    /**
     * 清理統計快取.
     */
    private function invalidateStatsCache(): bool
    {
        $statsKeys = [
            self::CACHE_KEY_PREFIX . 'stats:daily',
            self::CACHE_KEY_PREFIX . 'stats:hourly',
            self::CACHE_KEY_PREFIX . 'stats:summary',
        ];

        $success = true;
        foreach ($statsKeys as $key) {
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 清理安全事件快取.
     */
    private function invalidateSecurityEventCache(): bool
    {
        $securityKeys = [
            self::CACHE_KEY_PREFIX . 'security:recent',
            self::CACHE_KEY_PREFIX . 'security:alerts',
            self::CACHE_KEY_PREFIX . 'security:summary',
        ];

        $success = true;
        foreach ($securityKeys as $key) {
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 清理配置快取.
     */
    private function invalidateConfigCache(): bool
    {
        $configKeys = [
            self::CACHE_KEY_PREFIX . 'config:log_level',
        ];

        $success = true;

        // 清理已知配置鍵
        foreach ($configKeys as $key) {
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }

        // 清理所有已知的 ActivityType 配置
        foreach (ActivityType::cases() as $activityType) {
            $key = self::CACHE_KEY_PREFIX . "config:enabled:{$activityType->value}";
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 清理所有快取.
     */
    private function invalidateAllCaches(): bool
    {
        // 清理已知的快取群組
        $success = true;

        if (!$this->invalidateStatsCache()) {
            $success = false;
        }

        if (!$this->invalidateSecurityEventCache()) {
            $success = false;
        }

        if (!$this->invalidateConfigCache()) {
            $success = false;
        }

        return $success;
    }
}
