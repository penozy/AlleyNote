<?php

declare(strict_types=1);

namespace App\Application\Services\Monitoring;

use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Shared\Contracts\CacheServiceInterface;
use App\Domains\Security\Enums\ActivityCategory;
use Psr\Log\LoggerInterface;

/**
 * 系統健康檢查服務
 * 
 * 檢查系統各個組件的健康狀態
 */
final readonly class HealthCheckService
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CacheServiceInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * 執行完整的健康檢查
     */
    public function performFullHealthCheck(): array
    {
        $startTime = microtime(true);
        
        $healthChecks = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'filesystem' => $this->checkFilesystemHealth(),
            'memory' => $this->checkMemoryHealth(),
            'disk' => $this->checkDiskHealth(),
            'application' => $this->checkApplicationHealth()
        ];

        $overallStatus = $this->determineOverallStatus($healthChecks);
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $overallStatus,
            'timestamp' => time(),
            'response_time' => $totalTime,
            'checks' => $healthChecks,
            'summary' => $this->generateHealthSummary($healthChecks)
        ];
    }

    /**
     * 檢查資料庫健康狀態
     */
    public function checkDatabaseHealth(): array
    {
        $startTime = microtime(true);
        
        try {
            // 測試資料庫連線
            $testResult = $this->activityLogRepository->findById(1);
            $connectionTime = microtime(true) - $startTime;
            
            // 測試基本查詢效能
            $performanceStartTime = microtime(true);
            $this->activityLogRepository->countByCategory(ActivityCategory::AUTHENTICATION);
            $queryTime = microtime(true) - $performanceStartTime;
            
            $status = 'healthy';
            $details = [
                'connection_time' => round($connectionTime * 1000, 2),
                'query_time' => round($queryTime * 1000, 2),
                'connection_status' => 'connected'
            ];
            
            // 檢查效能是否在可接受範圍內
            if ($connectionTime > 0.5 || $queryTime > 1.0) {
                $status = 'degraded';
            }
            
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $details = [
                'error' => $e->getMessage(),
                'connection_time' => round((microtime(true) - $startTime) * 1000, 2),
                'connection_status' => 'failed'
            ];
            
            $this->logger->error('Database health check failed', $details);
        }

        return [
            'status' => $status,
            'details' => $details
        ];
    }

    /**
     * 檢查快取系統健康狀態
     */
    public function checkCacheHealth(): array
    {
        $startTime = microtime(true);
        $testKey = 'health_check_' . time() . '_' . random_int(1000, 9999);
        $testValue = 'test_' . time();
        
        try {
            // 測試寫入
            $writeStartTime = microtime(true);
            $this->cache->set($testKey, $testValue, 30);
            $writeTime = microtime(true) - $writeStartTime;
            
            // 測試讀取
            $readStartTime = microtime(true);
            $retrievedValue = $this->cache->get($testKey);
            $readTime = microtime(true) - $readStartTime;
            
            // 測試刪除
            $deleteStartTime = microtime(true);
            $this->cache->delete($testKey);
            $deleteTime = microtime(true) - $deleteStartTime;
            
            // 驗證資料完整性
            $dataIntegrity = $retrievedValue === $testValue;
            $totalTime = microtime(true) - $startTime;
            
            $status = $dataIntegrity ? 'healthy' : 'unhealthy';
            
            // 檢查效能
            if ($totalTime > 0.1) {
                $status = 'degraded';
            }
            
            $details = [
                'write_time' => round($writeTime * 1000, 2),
                'read_time' => round($readTime * 1000, 2),
                'delete_time' => round($deleteTime * 1000, 2),
                'total_time' => round($totalTime * 1000, 2),
                'data_integrity' => $dataIntegrity
            ];
            
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $details = [
                'error' => $e->getMessage(),
                'total_time' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            
            $this->logger->error('Cache health check failed', $details);
        }

        return [
            'status' => $status,
            'details' => $details
        ];
    }

    /**
     * 檢查檔案系統健康狀態
     */
    public function checkFilesystemHealth(): array
    {
        $testDir = __DIR__ . '/../../../../storage/logs';
        $testFile = $testDir . '/health_check_' . time() . '.tmp';
        $testContent = 'health_check_' . time();
        
        try {
            // 檢查目錄是否存在且可寫
            if (!is_dir($testDir)) {
                throw new \RuntimeException("Directory {$testDir} does not exist");
            }
            
            if (!is_writable($testDir)) {
                throw new \RuntimeException("Directory {$testDir} is not writable");
            }
            
            // 測試檔案寫入
            $writeStartTime = microtime(true);
            file_put_contents($testFile, $testContent);
            $writeTime = microtime(true) - $writeStartTime;
            
            // 測試檔案讀取
            $readStartTime = microtime(true);
            $retrievedContent = file_get_contents($testFile);
            $readTime = microtime(true) - $readStartTime;
            
            // 測試檔案刪除
            $deleteStartTime = microtime(true);
            unlink($testFile);
            $deleteTime = microtime(true) - $deleteStartTime;
            
            $dataIntegrity = $retrievedContent === $testContent;
            
            $status = $dataIntegrity ? 'healthy' : 'unhealthy';
            $details = [
                'directory_writable' => is_writable($testDir),
                'write_time' => round($writeTime * 1000, 2),
                'read_time' => round($readTime * 1000, 2),
                'delete_time' => round($deleteTime * 1000, 2),
                'data_integrity' => $dataIntegrity
            ];
            
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $details = [
                'error' => $e->getMessage(),
                'directory_exists' => is_dir($testDir),
                'directory_writable' => is_dir($testDir) && is_writable($testDir)
            ];
            
            $this->logger->error('Filesystem health check failed', $details);
        }

        return [
            'status' => $status,
            'details' => $details
        ];
    }

    /**
     * 檢查記憶體健康狀態
     */
    public function checkMemoryHealth(): array
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usagePercentage = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        
        // 判斷記憶體健康狀態
        if ($usagePercentage > 90) {
            $status = 'unhealthy';
        } elseif ($usagePercentage > 75) {
            $status = 'degraded';
        } else {
            $status = 'healthy';
        }

        return [
            'status' => $status,
            'details' => [
                'current_usage' => $memoryUsage,
                'peak_usage' => $peakMemoryUsage,
                'limit' => $memoryLimit,
                'usage_percentage' => round($usagePercentage, 2),
                'available' => $memoryLimit - $memoryUsage
            ]
        ];
    }

    /**
     * 檢查磁碟健康狀態
     */
    public function checkDiskHealth(): array
    {
        $rootPath = '/';
        $totalBytes = disk_total_space($rootPath);
        $freeBytes = disk_free_space($rootPath);
        $usedBytes = $totalBytes - $freeBytes;
        
        $usagePercentage = $totalBytes > 0 ? ($usedBytes / $totalBytes) * 100 : 0;
        
        // 判斷磁碟健康狀態
        if ($usagePercentage > 95) {
            $status = 'unhealthy';
        } elseif ($usagePercentage > 85) {
            $status = 'degraded';
        } else {
            $status = 'healthy';
        }

        return [
            'status' => $status,
            'details' => [
                'total' => $totalBytes ?: 0,
                'free' => $freeBytes ?: 0,
                'used' => $usedBytes,
                'usage_percentage' => round($usagePercentage, 2)
            ]
        ];
    }

    /**
     * 檢查應用程式健康狀態
     */
    public function checkApplicationHealth(): array
    {
        try {
            // 檢查重要的設定
            $requiredExtensions = ['pdo', 'json', 'mbstring', 'openssl'];
            $missingExtensions = [];
            
            foreach ($requiredExtensions as $extension) {
                if (!extension_loaded($extension)) {
                    $missingExtensions[] = $extension;
                }
            }
            
            // 檢查重要檔案是否存在
            $requiredFiles = [
                __DIR__ . '/../../../../config/container.php',
                __DIR__ . '/../../../../config/routes.php'
            ];
            
            $missingFiles = [];
            foreach ($requiredFiles as $file) {
                if (!file_exists($file)) {
                    $missingFiles[] = $file;
                }
            }
            
            $hasErrors = !empty($missingExtensions) || !empty($missingFiles);
            $status = $hasErrors ? 'unhealthy' : 'healthy';
            
            $details = [
                'php_version' => PHP_VERSION,
                'required_extensions' => $requiredExtensions,
                'missing_extensions' => $missingExtensions,
                'missing_files' => $missingFiles,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ];
            
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $details = [
                'error' => $e->getMessage()
            ];
            
            $this->logger->error('Application health check failed', $details);
        }

        return [
            'status' => $status,
            'details' => $details
        ];
    }

    /**
     * 檢查特定組件的健康狀態
     */
    public function checkComponentHealth(string $component): array
    {
        return match($component) {
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'filesystem' => $this->checkFilesystemHealth(),
            'memory' => $this->checkMemoryHealth(),
            'disk' => $this->checkDiskHealth(),
            'application' => $this->checkApplicationHealth(),
            default => [
                'status' => 'unknown',
                'details' => ['error' => 'Unknown component: ' . $component]
            ]
        };
    }

    /**
     * 判斷整體健康狀態
     */
    private function determineOverallStatus(array $healthChecks): string
    {
        $statuses = array_column($healthChecks, 'status');
        
        if (in_array('unhealthy', $statuses, true)) {
            return 'unhealthy';
        }
        
        if (in_array('degraded', $statuses, true)) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * 產生健康檢查摘要
     */
    private function generateHealthSummary(array $healthChecks): array
    {
        $summary = [
            'total_checks' => count($healthChecks),
            'healthy' => 0,
            'degraded' => 0,
            'unhealthy' => 0
        ];
        
        foreach ($healthChecks as $check) {
            $status = $check['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }
        
        return $summary;
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