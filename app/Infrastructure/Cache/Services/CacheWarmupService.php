<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Enums\ActivityType;
use App\Shared\Contracts\CacheInterface;
use Throwable;

/**
 * 快取暖機服務.
 *
 * 負責在系統啟動時預載入常用資料到快取中，提高首次查詢效能
 */
final class CacheWarmupService
{
    private const string WARMUP_STATUS_KEY = 'cache:warmup:status';

    private const string WARMUP_TIMESTAMP_KEY = 'cache:warmup:timestamp';

    private const int WARMUP_TTL = 3600; // 1 小時

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ActivityLoggingServiceInterface $activityLoggingService,
    ) {}

    /**
     * 執行快取暖機
     *
     * @return array<string, mixed> 暖機結果統計
     */
    public function warmup(): array
    {
        $startTime = microtime(true);
        $stats = [
            'started_at' => date('Y-m-d H:i:s'),
            'activity_types_preloaded' => 0,
            'config_items_cached' => 0,
            'errors' => [],
            'duration_ms' => 0,
        ];

        try {
            // 預載入活動類型啟用狀態
            $stats['activity_types_preloaded'] = $this->preloadActivityTypeConfigs();

            // 預載入系統配置
            $stats['config_items_cached'] = $this->preloadSystemConfigs();

            // 記錄暖機完成狀態
            $this->markWarmupComplete();
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
        }

        $stats['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $stats;
    }

    /**
     * 預載入所有活動類型的啟用狀態配置.
     */
    private function preloadActivityTypeConfigs(): int
    {
        $count = 0;

        foreach (ActivityType::cases() as $actionType) {
            try {
                // 觸發快取載入（如果服務支援快取）
                $this->activityLoggingService->isLoggingEnabled($actionType);
                $count++;
            } catch (Throwable) {
                // 忽略個別載入錯誤，繼續處理其他項目
            }
        }

        return $count;
    }

    /**
     * 預載入系統配置項目.
     */
    private function preloadSystemConfigs(): int
    {
        $configs = [
            'system:default_log_level' => 3,
            'system:max_records_per_user' => 1000,
            'system:cleanup_interval_days' => 90,
            'system:security_alert_threshold' => 5,
        ];

        $count = 0;
        foreach ($configs as $key => $value) {
            try {
                // 設定系統配置快取
                if ($this->cache->set("config:$key", $value, self::WARMUP_TTL)) {
                    $count++;
                }
            } catch (Throwable) {
                // 忽略個別設定錯誤
            }
        }

        return $count;
    }

    /**
     * 標記暖機完成.
     */
    private function markWarmupComplete(): void
    {
        try {
            $timestamp = time();
            $this->cache->set(self::WARMUP_STATUS_KEY, 'completed', self::WARMUP_TTL);
            $this->cache->set(self::WARMUP_TIMESTAMP_KEY, $timestamp, self::WARMUP_TTL);
        } catch (Throwable) {
            // 暖機狀態記錄失敗不影響主要功能
        }
    }

    /**
     * 檢查是否需要執行暖機
     *
     * @param int $maxAge 最大暖機結果有效時間（秒），預設 1 小時
     */
    public function shouldWarmup(int $maxAge = 3600): bool
    {
        try {
            $status = $this->cache->get(self::WARMUP_STATUS_KEY);
            $timestamp = $this->cache->get(self::WARMUP_TIMESTAMP_KEY);

            // 如果沒有暖機記錄，需要暖機
            if ($status !== 'completed' || $timestamp === null) {
                return true;
            }

            // 檢查暖機是否過期
            return (time() - (int) $timestamp) > $maxAge;
        } catch (Throwable) {
            // 檢查失敗時，預設執行暖機
            return true;
        }
    }

    /**
     * 取得暖機狀態資訊.
     *
     * @return array<string, mixed>
     */
    public function getWarmupStatus(): array
    {
        try {
            $status = $this->cache->get(self::WARMUP_STATUS_KEY) ?? 'never_run';
            $timestamp = $this->cache->get(self::WARMUP_TIMESTAMP_KEY);

            $result = [
                'status' => $status,
                'last_run' => null,
                'age_seconds' => null,
                'should_warmup' => $this->shouldWarmup(),
            ];

            if ($timestamp !== null) {
                $lastRun = (int) $timestamp;
                $result['last_run'] = date('Y-m-d H:i:s', $lastRun);
                $result['age_seconds'] = time() - $lastRun;
            }

            return $result;
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'should_warmup' => true,
            ];
        }
    }

    /**
     * 清除暖機狀態（強制重新暖機）.
     */
    public function clearWarmupStatus(): bool
    {
        try {
            $this->cache->delete(self::WARMUP_STATUS_KEY);
            $this->cache->delete(self::WARMUP_TIMESTAMP_KEY);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 智能暖機 - 只在需要時執行暖機
     *
     * @return array<string, mixed>|null 暖機結果，如果不需要暖機則返回 null
     */
    public function smartWarmup(): ?array
    {
        if (!$this->shouldWarmup()) {
            return null;
        }

        return $this->warmup();
    }
}
