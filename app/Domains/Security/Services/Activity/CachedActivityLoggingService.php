<?php

declare(strict_types=1);

namespace App\Domains\Security\Services\Activity;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Shared\Contracts\CacheInterface;
use Throwable;

/**
 * 快取版活動記錄服務.
 *
 * 透過快取提升活動記錄服務的效能
 */
class CachedActivityLoggingService implements ActivityLoggingServiceInterface
{
    private const CACHE_TTL_STATS = 300; // 5 分鐘

    private const CACHE_TTL_USER_ACTIVITIES = 60; // 1 分鐘

    private const CACHE_PREFIX_STATS = 'activity_stats:';

    private const CACHE_PREFIX_USER = 'user_activities:';

    public function __construct(
        private ActivityLoggingServiceInterface $activityLoggingService,
        private CacheInterface $cache,
    ) {}

    public function log(CreateActivityLogDTO $dto): bool
    {
        // 記錄活動（這個操作不快取，確保資料一致性）
        $result = $this->activityLoggingService->log($dto);

        if ($result && $dto->getUserId() !== null) {
            // 清除相關快取
            $this->invalidateUserCache($dto->getUserId());
            $this->invalidateStatsCache();
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
        $result = $this->activityLoggingService->logSuccess($actionType, $userId, $targetType, $targetId, $metadata);

        if ($result && $userId !== null) {
            $this->invalidateUserCache($userId);
            $this->invalidateStatsCache();
        }

        return $result;
    }

    public function logFailure(
        ActivityType $actionType,
        ?int $userId = null,
        string $reason = '',
        ?array $metadata = null,
    ): bool {
        $result = $this->activityLoggingService->logFailure($actionType, $userId, $reason, $metadata);

        if ($result && $userId !== null) {
            $this->invalidateUserCache($userId);
            $this->invalidateStatsCache();
        }

        return $result;
    }

    public function logSecurityEvent(
        ActivityType $actionType,
        string $description,
        ?array $metadata = null,
    ): bool {
        $result = $this->activityLoggingService->logSecurityEvent($actionType, $description, $metadata);

        if ($result) {
            $this->invalidateStatsCache();
        }

        return $result;
    }

    public function logBatch(array $dtos): int
    {
        $result = $this->activityLoggingService->logBatch($dtos);

        if ($result > 0) {
            // 批次操作後清除所有相關快取
            $this->clearAllActivityCache();
        }

        return $result;
    }

    public function enableLogging(ActivityType $actionType): void
    {
        $this->activityLoggingService->enableLogging($actionType);
    }

    public function disableLogging(ActivityType $actionType): void
    {
        $this->activityLoggingService->disableLogging($actionType);
    }

    public function isLoggingEnabled(ActivityType $actionType): bool
    {
        return $this->activityLoggingService->isLoggingEnabled($actionType);
    }

    public function setLogLevel(int $level): void
    {
        $this->activityLoggingService->setLogLevel($level);
    }

    public function cleanup(): int
    {
        $result = $this->activityLoggingService->cleanup();

        if ($result > 0) {
            // 清理後清除所有快取
            $this->clearAllActivityCache();
        }

        return $result;
    }

    /**
     * 取得使用者活動統計（帶快取）.
     *
     * @param int $userId 使用者 ID
     * @param int $days 天數
     * @return array<string, mixed>
     */
    public function getUserActivityStats(int $userId, int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX_USER . "{$userId}:stats:{$days}";

        $stats = $this->cache->get($cacheKey);
        if ($stats !== null) {
            return $stats;
        }

        // 這裡應該調用實際的統計方法，暫時回傳模擬資料
        $stats = $this->calculateUserActivityStats($userId, $days);

        $this->cache->set($cacheKey, $stats, self::CACHE_TTL_STATS);

        return $stats;
    }

    /**
     * 取得系統活動統計（帶快取）.
     *
     * @param int $days 天數
     * @return array<string, mixed>
     */
    public function getSystemActivityStats(int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX_STATS . "system:{$days}";

        $stats = $this->cache->get($cacheKey);
        if ($stats !== null) {
            return $stats;
        }

        $stats = $this->calculateSystemActivityStats($days);

        $this->cache->set($cacheKey, $stats, self::CACHE_TTL_STATS);

        return $stats;
    }

    /**
     * 取得熱門活動類型（帶快取）.
     *
     * @param int $limit 限制數量
     * @param int $days 天數
     * @return array<array<string, mixed>>
     */
    public function getPopularActivityTypes(int $limit = 10, int $days = 7): array
    {
        $cacheKey = self::CACHE_PREFIX_STATS . "popular:{$limit}:{$days}";

        $activities = $this->cache->get($cacheKey);
        if ($activities !== null) {
            return $activities;
        }

        $activities = $this->calculatePopularActivityTypes($limit, $days);

        $this->cache->set($cacheKey, $activities, self::CACHE_TTL_STATS);

        return $activities;
    }

    /**
     * 清除使用者相關快取.
     */
    private function invalidateUserCache(int $userId): void
    {
        try {
            // 清除使用者統計快取
            $commonKeys = [
                self::CACHE_PREFIX_USER . "{$userId}:stats:7",
                self::CACHE_PREFIX_USER . "{$userId}:stats:30",
                self::CACHE_PREFIX_USER . "{$userId}:recent",
            ];

            $this->cache->deleteMultiple($commonKeys);
        } catch (Throwable) {
            // 快取清除失敗不影響主要功能
        }
    }

    /**
     * 清除統計快取.
     */
    private function invalidateStatsCache(): void
    {
        try {
            $commonKeys = [
                self::CACHE_PREFIX_STATS . 'system:7',
                self::CACHE_PREFIX_STATS . 'system:30',
                self::CACHE_PREFIX_STATS . 'popular:10:7',
                self::CACHE_PREFIX_STATS . 'popular:10:30',
            ];

            $this->cache->deleteMultiple($commonKeys);
        } catch (Throwable) {
            // 快取清除失敗不影響主要功能
        }
    }

    /**
     * 清除所有活動相關快取.
     */
    private function clearAllActivityCache(): void
    {
        try {
            // 在生產環境中，這裡應該使用更精確的快取清除策略
            // 避免清除整個快取資料庫
            $this->invalidateStatsCache();
        } catch (Throwable) {
            // 快取清除失敗不影響主要功能
        }
    }

    /**
     * 計算使用者活動統計.
     *
     * @param int $userId 使用者 ID
     * @param int $days 天數
     * @return array<string, mixed>
     */
    private function calculateUserActivityStats(int $userId, int $days): array
    {
        // 這裡應該實作實際的統計邏輯
        // 暫時回傳模擬資料
        return [
            'user_id' => $userId,
            'total_activities' => 0,
            'success_rate' => 0.0,
            'most_active_hour' => 9,
            'activity_types' => [],
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 計算系統活動統計.
     *
     * @param int $days 天數
     * @return array<string, mixed>
     */
    private function calculateSystemActivityStats(int $days): array
    {
        return [
            'total_activities' => 0,
            'total_users' => 0,
            'success_rate' => 0.0,
            'security_events' => 0,
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 計算熱門活動類型.
     *
     * @param int $limit 限制數量
     * @param int $days 天數
     * @return array<array<string, mixed>>
     */
    private function calculatePopularActivityTypes(int $limit, int $days): array
    {
        return [];
    }

    /**
     * 取得快取狀態.
     *
     * @return array<string, mixed>
     */
    public function getCacheStatus(): array
    {
        try {
            return [
                'cache_enabled' => true,
                'cache_type' => 'redis',
                'stats_ttl' => self::CACHE_TTL_STATS,
                'user_ttl' => self::CACHE_TTL_USER_ACTIVITIES,
            ];
        } catch (Throwable) {
            return [
                'cache_enabled' => false,
                'error' => 'Cache service unavailable',
            ];
        }
    }
}
