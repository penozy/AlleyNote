<?php

declare(strict_types=1);

namespace App\Application\Services\Monitoring;

use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Shared\Contracts\CacheServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * 系統指標收集服務
 * 
 * 負責收集各種系統運行指標，包括效能、資源使用、業務指標等
 */
final readonly class MetricsCollectorService
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CacheServiceInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * 收集所有系統指標
     */
    public function collectAllMetrics(): array
    {
        $metrics = [
            'timestamp' => time(),
            'system' => $this->collectSystemMetrics(),
            'application' => $this->collectApplicationMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'business' => $this->collectBusinessMetrics()
        ];

        // 快取指標資料
        $this->cache->set('system.metrics.latest', $metrics, 300); // 5分鐘快取

        return $metrics;
    }

    /**
     * 收集系統資源指標
     */
    public function collectSystemMetrics(): array
    {
        return [
            'memory' => $this->getMemoryMetrics(),
            'cpu' => $this->getCpuMetrics(),
            'disk' => $this->getDiskMetrics(),
            'network' => $this->getNetworkMetrics()
        ];
    }

    /**
     * 收集應用程式指標
     */
    public function collectApplicationMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'uptime' => $this->getApplicationUptime(),
            'error_rate' => $this->calculateErrorRate(),
            'response_time' => $this->calculateAverageResponseTime(),
            'active_sessions' => $this->getActiveSessionCount()
        ];
    }

    /**
     * 收集資料庫指標
     */
    public function collectDatabaseMetrics(): array
    {
        $startTime = microtime(true);
        
        try {
            // 簡單的資料庫查詢測試連線速度
            $this->activityLogRepository->findById(1);
            $connectionTime = microtime(true) - $startTime;
            $isHealthy = true;
        } catch (\Exception $e) {
            $connectionTime = microtime(true) - $startTime;
            $isHealthy = false;
            $this->logger->error('Database health check failed', ['error' => $e->getMessage()]);
        }

        return [
            'connection_time' => round($connectionTime * 1000, 2), // ms
            'is_healthy' => $isHealthy,
            'query_count' => $this->getDatabaseQueryCount(),
            'slow_query_count' => $this->getSlowQueryCount(),
            'table_sizes' => $this->getTableSizes()
        ];
    }

    /**
     * 收集快取指標
     */
    public function collectCacheMetrics(): array
    {
        $startTime = microtime(true);
        $testKey = 'health_check_' . time();
        
        try {
            // 測試快取讀寫
            $this->cache->set($testKey, 'test', 10);
            $value = $this->cache->get($testKey);
            $this->cache->delete($testKey);
            
            $responseTime = microtime(true) - $startTime;
            $isHealthy = $value === 'test';
        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;
            $isHealthy = false;
            $this->logger->error('Cache health check failed', ['error' => $e->getMessage()]);
        }

        return [
            'response_time' => round($responseTime * 1000, 2), // ms
            'is_healthy' => $isHealthy,
            'hit_rate' => $this->calculateCacheHitRate(),
            'memory_usage' => $this->getCacheMemoryUsage()
        ];
    }

    /**
     * 收集業務指標
     */
    public function collectBusinessMetrics(): array
    {
        $now = new \DateTimeImmutable();
        $oneDayAgo = $now->modify('-1 day');
        
        return [
            'daily_activities' => $this->getDailyActivityCount($oneDayAgo, $now),
            'daily_users' => $this->getDailyActiveUserCount($oneDayAgo, $now),
            'error_activities' => $this->getErrorActivityCount($oneDayAgo, $now),
            'security_events' => $this->getSecurityEventCount($oneDayAgo, $now),
            'top_activity_types' => $this->getTopActivityTypes(5)
        ];
    }

    /**
     * 獲取記憶體使用指標
     */
    private function getMemoryMetrics(): array
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        return [
            'current' => $memoryUsage,
            'peak' => $peakMemoryUsage,
            'limit' => $memoryLimit,
            'usage_percentage' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0
        ];
    }

    /**
     * 獲取 CPU 使用指標（簡化版）
     */
    private function getCpuMetrics(): array
    {
        $load = sys_getloadavg();
        
        return [
            'load_1min' => $load[0] ?? 0,
            'load_5min' => $load[1] ?? 0,
            'load_15min' => $load[2] ?? 0
        ];
    }

    /**
     * 獲取磁碟使用指標
     */
    private function getDiskMetrics(): array
    {
        $rootPath = '/';
        $totalBytes = disk_total_space($rootPath);
        $freeBytes = disk_free_space($rootPath);
        $usedBytes = $totalBytes - $freeBytes;
        
        return [
            'total' => $totalBytes ?: 0,
            'free' => $freeBytes ?: 0,
            'used' => $usedBytes,
            'usage_percentage' => $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 2) : 0
        ];
    }

    /**
     * 獲取網路指標（簡化版）
     */
    private function getNetworkMetrics(): array
    {
        return [
            'connections' => $this->getActiveConnectionCount(),
            'requests_per_minute' => $this->getRequestsPerMinute()
        ];
    }

    /**
     * 獲取應用程式運行時間
     */
    private function getApplicationUptime(): int
    {
        $startTimeFile = __DIR__ . '/../../../../storage/logs/app_start_time.txt';
        
        if (file_exists($startTimeFile)) {
            $startTime = (int)file_get_contents($startTimeFile);
            return time() - $startTime;
        }

        // 如果檔案不存在，建立它
        file_put_contents($startTimeFile, (string)time());
        return 0;
    }

    /**
     * 計算錯誤率
     */
    private function calculateErrorRate(): float
    {
        // 從快取中獲取最近的錯誤統計
        $errorStats = $this->cache->get('metrics.error_rate.hourly');
        
        if ($errorStats === null) {
            // 計算最近一小時的錯誤率
            $now = new \DateTimeImmutable();
            $oneHourAgo = $now->modify('-1 hour');
            
            // 使用實際存在的方法
            $activities = $this->activityLogRepository->findByTimeRange($oneHourAgo, $now, 10000);
            $totalActivities = count($activities);
            
            $failedActivities = $this->activityLogRepository->findFailedActivities(10000);
            $errorActivities = count($failedActivities);
            
            $errorRate = $totalActivities > 0 ? ($errorActivities / $totalActivities) * 100 : 0;
            
            // 快取 5 分鐘
            $this->cache->set('metrics.error_rate.hourly', $errorRate, 300);
            return round($errorRate, 2);
        }
        
        return round($errorStats, 2);
    }

    /**
     * 計算平均回應時間
     */
    private function calculateAverageResponseTime(): float
    {
        // 模擬計算，實際應該從 APM 工具或日誌中獲取
        $responseTime = $this->cache->get('metrics.avg_response_time');
        
        if ($responseTime === null) {
            // 執行簡單的效能測試
            $startTime = microtime(true);
            $this->activityLogRepository->findById(1);
            $endTime = microtime(true);
            
            $responseTime = ($endTime - $startTime) * 1000; // ms
            $this->cache->set('metrics.avg_response_time', $responseTime, 60);
        }
        
        return round($responseTime, 2);
    }

    /**
     * 獲取活動會話數
     */
    private function getActiveSessionCount(): int
    {
        // 簡化實作，實際應該從會話儲存中計算
        return random_int(5, 50);
    }

    /**
     * 獲取資料庫查詢數量
     */
    private function getDatabaseQueryCount(): int
    {
        // 簡化實作，實際應該從資料庫統計中獲取
        return random_int(1000, 5000);
    }

    /**
     * 獲取慢查詢數量
     */
    private function getSlowQueryCount(): int
    {
        // 簡化實作
        return random_int(0, 10);
    }

    /**
     * 獲取資料表大小
     */
    private function getTableSizes(): array
    {
        return [
            'user_activity_logs' => $this->getTableRecordCount('user_activity_logs'),
            'posts' => $this->getTableRecordCount('posts'),
            'users' => $this->getTableRecordCount('users')
        ];
    }

    /**
     * 計算快取命中率
     */
    private function calculateCacheHitRate(): float
    {
        $stats = $this->cache->get('cache.hit_rate.stats');
        
        if ($stats === null) {
            // 簡化實作，實際應該追蹤命中和未命中次數
            return 85.5;
        }
        
        return $stats;
    }

    /**
     * 獲取快取記憶體使用量
     */
    private function getCacheMemoryUsage(): int
    {
        // 簡化實作，實際應該從 Redis 等快取系統獲取
        return 1024 * 1024 * 50; // 50MB
    }

    /**
     * 獲取每日活動數量
     */
    private function getDailyActivityCount(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $activities = $this->activityLogRepository->findByTimeRange($from, $to, 10000);
        return count($activities);
    }

    /**
     * 獲取每日活躍使用者數量
     */
    private function getDailyActiveUserCount(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        // 計算唯一使用者數量的簡化實現
        $activities = $this->activityLogRepository->findByTimeRange($from, $to, 10000);
        $uniqueUsers = [];
        foreach ($activities as $activity) {
            if (isset($activity['user_id']) && $activity['user_id'] !== null) {
                $uniqueUsers[$activity['user_id']] = true;
            }
        }
        return count($uniqueUsers);
    }

    /**
     * 獲取錯誤活動數量
     */
    private function getErrorActivityCount(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $failedActivities = $this->activityLogRepository->findFailedActivities(10000);
        return count($failedActivities);
    }

    /**
     * 獲取安全事件數量
     */
    private function getSecurityEventCount(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $securityEvents = $this->activityLogRepository->findSecurityEvents(10000);
        return count($securityEvents);
    }

    /**
     * 獲取熱門活動類型
     */
    private function getTopActivityTypes(int $limit): array
    {
        return $this->activityLogRepository->getPopularActivityTypes($limit);
    }

    /**
     * 獲取活動連線數
     */
    private function getActiveConnectionCount(): int
    {
        // 簡化實作
        return random_int(10, 100);
    }

    /**
     * 獲取每分鐘請求數
     */
    private function getRequestsPerMinute(): int
    {
        // 簡化實作
        return random_int(50, 500);
    }

    /**
     * 獲取資料表記錄數
     */
    private function getTableRecordCount(string $tableName): int
    {
        try {
            // 簡化實作，實際應該執行 SQL 查詢
            return match($tableName) {
                'user_activity_logs' => 1000,
                'posts' => 50,
                'users' => 10,
                default => 0
            };
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * 解析記憶體限制
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        if ($memoryLimit === '-1') {
            return 0; // 無限制
        }

        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int)substr($memoryLimit, 0, -1);

        return match($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value
        };
    }
}