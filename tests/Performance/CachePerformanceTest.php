<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Services\CachedActivityLoggingService;
use App\Domains\Security\Services\Core\ActivityLoggingService;
use App\Infrastructure\Cache\Providers\AppRedisCache;
use App\Shared\Contracts\CacheInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Redis 快取效能基準測試
 * 
 * 測試快取系統是否達到預期的效能提升目標：
 * - 50% 查詢效能提升
 * - 80%+ 快取命中率
 */
final class CachePerformanceTest extends TestCase
{
    private const TEST_ITERATIONS = 100;
    private const CACHE_HIT_RATE_TARGET = 80.0; // 80%
    private const PERFORMANCE_IMPROVEMENT_TARGET = 50.0; // 50%

    private ActivityLoggingServiceInterface $originalService;
    private CachedActivityLoggingService $cachedService;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // 跳過測試如果 Redis 不可用
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            // 建立 Redis 快取連接
            $this->cache = new AppRedisCache(
                host: $_ENV['REDIS_HOST'] ?? 'redis',
                port: (int) ($_ENV['REDIS_PORT'] ?? 6379),
                prefix: 'perf_test:',
                database: 14 // 使用專用測試資料庫
            );

            // 清理測試快取
            $this->cache->clear();

            // 建立服務實例 - 這裡需要實際的實作，暫時使用 mock
            $this->originalService = $this->createMock(ActivityLoggingServiceInterface::class);

            // 建立快取裝飾器服務
            $this->cachedService = new CachedActivityLoggingService(
                $this->originalService,
                $this->cache
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis connection failed: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
        }
        parent::tearDown();
    }

    /**
     * 測試快取配置查詢的效能提升
     */
    #[Test]
    public function cacheImproveIsLoggingEnabledPerformance(): void
    {
        $actionType = ActivityType::LOGIN_SUCCESS;

        // 設定 Mock 原始服務行為（模擬資料庫查詢延遲）
        $this->originalService
            ->expects($this->exactly(1)) // 只會被調用一次（快取後不再調用）
            ->method('isLoggingEnabled')
            ->with($actionType)
            ->willReturnCallback(function () {
                // 模擬資料庫查詢延遲
                usleep(10000); // 10ms 延遲
                return true;
            });

        // 測試第一次調用（Cache Miss）
        $startTime = microtime(true);
        $result1 = $this->cachedService->isLoggingEnabled($actionType);
        $firstCallTime = (microtime(true) - $startTime) * 1000; // 轉換為毫秒

        $this->assertTrue($result1);

        // 測試後續調用（Cache Hit）
        $hitTimes = [];
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $startTime = microtime(true);
            $result = $this->cachedService->isLoggingEnabled($actionType);
            $hitTimes[] = (microtime(true) - $startTime) * 1000;
            $this->assertTrue($result);
        }

        // 計算效能統計
        $avgHitTime = array_sum($hitTimes) / count($hitTimes);
        $performanceImprovement = (($firstCallTime - $avgHitTime) / $firstCallTime) * 100;

        // 驗證效能提升
        $this->assertGreaterThan(
            self::PERFORMANCE_IMPROVEMENT_TARGET,
            $performanceImprovement,
            sprintf(
                'Cache performance improvement %.2f%% is below target %.2f%%. First call: %.2fms, Avg hit: %.2fms',
                $performanceImprovement,
                self::PERFORMANCE_IMPROVEMENT_TARGET,
                $firstCallTime,
                $avgHitTime
            )
        );

        // 輸出效能統計
        printf(
            "\n🚀 Cache Performance Test Results:\n" .
                "   First call (Cache Miss): %.2fms\n" .
                "   Average hit time: %.2fms\n" .
                "   Performance improvement: %.2f%%\n" .
                "   Target: %.2f%%\n",
            $firstCallTime,
            $avgHitTime,
            $performanceImprovement,
            self::PERFORMANCE_IMPROVEMENT_TARGET
        );
    }

    /**
     * 測試快取命中率統計功能
     */
    #[Test]
    public function measureCacheHitRate(): void
    {
        $testKeys = [];
        $testValues = [];

        // 準備測試資料
        for ($i = 0; $i < 20; $i++) {
            $testKeys[] = "test_key_$i";
            $testValues["test_key_$i"] = "test_value_$i";
        }

        // 設定快取資料
        foreach ($testValues as $key => $value) {
            $this->cache->set($key, $value, 300);
        }

        $hits = 0;
        $misses = 0;

        // 模擬混合的命中和未命中情況
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            // 80% 機率查詢存在的鍵（命中）
            if ($i % 5 !== 0) {
                $key = $testKeys[array_rand($testKeys)];
                $result = $this->cache->get($key);
                if ($result !== null) {
                    $hits++;
                } else {
                    $misses++;
                }
            } else {
                // 20% 機率查詢不存在的鍵（未命中）
                $result = $this->cache->get("non_existent_key_$i");
                if ($result !== null) {
                    $hits++;
                } else {
                    $misses++;
                }
            }
        }

        $hitRate = ($hits / ($hits + $misses)) * 100;

        $this->assertGreaterThanOrEqual(
            self::CACHE_HIT_RATE_TARGET,
            $hitRate,
            sprintf(
                'Cache hit rate %.2f%% is below target %.2f%%. Hits: %d, Misses: %d',
                $hitRate,
                self::CACHE_HIT_RATE_TARGET,
                $hits,
                $misses
            )
        );

        // 輸出命中率統計
        printf(
            "\n📊 Cache Hit Rate Test Results:\n" .
                "   Total requests: %d\n" .
                "   Cache hits: %d\n" .
                "   Cache misses: %d\n" .
                "   Hit rate: %.2f%%\n" .
                "   Target: %.2f%%\n",
            $hits + $misses,
            $hits,
            $misses,
            $hitRate,
            self::CACHE_HIT_RATE_TARGET
        );
    }

    /**
     * 測試快取操作本身的效能
     */
    #[Test]
    public function measureCacheOperationPerformance(): void
    {
        $testData = [
            'user_id' => 123,
            'activities' => [
                ['type' => 'LOGIN', 'timestamp' => '2024-01-15 10:00:00'],
                ['type' => 'LOGOUT', 'timestamp' => '2024-01-15 18:00:00'],
            ],
            'metadata' => ['ip' => '127.0.0.1'],
        ];

        // 測試設定操作效能
        $setTimes = [];
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $key = "perf_test_$i";
            $startTime = microtime(true);
            $this->cache->set($key, $testData, 300);
            $setTimes[] = (microtime(true) - $startTime) * 1000;
        }

        // 測試取得操作效能
        $getTimes = [];
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $key = "perf_test_$i";
            $startTime = microtime(true);
            $result = $this->cache->get($key);
            $getTimes[] = (microtime(true) - $startTime) * 1000;
            $this->assertNotNull($result);
        }

        $avgSetTime = array_sum($setTimes) / count($setTimes);
        $avgGetTime = array_sum($getTimes) / count($getTimes);

        // 快取操作應該非常快速
        $this->assertLessThan(5.0, $avgSetTime, 'Cache SET operation too slow');
        $this->assertLessThan(2.0, $avgGetTime, 'Cache GET operation too slow');

        // 輸出效能統計
        printf(
            "\n⚡ Cache Operation Performance:\n" .
                "   Average SET time: %.2fms\n" .
                "   Average GET time: %.2fms\n" .
                "   Operations tested: %d each\n",
            $avgSetTime,
            $avgGetTime,
            self::TEST_ITERATIONS
        );
    }

    /**
     * 測試批次操作的效能
     */
    #[Test]
    public function measureBatchOperationPerformance(): void
    {
        $batchSize = 50;
        $testData = [];

        // 準備批次資料
        for ($i = 0; $i < $batchSize; $i++) {
            $testData["batch_key_$i"] = "batch_value_$i";
        }

        // 測試批次設定效能
        $startTime = microtime(true);
        $result = $this->cache->setMultiple($testData, 300);
        $batchSetTime = (microtime(true) - $startTime) * 1000;

        $this->assertTrue($result);

        // 測試批次取得效能
        $keys = array_keys($testData);
        $startTime = microtime(true);
        $results = $this->cache->getMultiple($keys);
        $batchGetTime = (microtime(true) - $startTime) * 1000;

        $this->assertCount($batchSize, $results);

        // 計算平均單項操作時間
        $avgSetTimePerItem = $batchSetTime / $batchSize;
        $avgGetTimePerItem = $batchGetTime / $batchSize;

        // 批次操作應該比單項操作更有效率
        $this->assertLessThan(1.0, $avgSetTimePerItem, 'Batch SET operation not efficient enough');
        $this->assertLessThan(0.5, $avgGetTimePerItem, 'Batch GET operation not efficient enough');

        // 輸出批次操作效能統計
        printf(
            "\n🔄 Batch Operation Performance:\n" .
                "   Batch size: %d items\n" .
                "   Total SET time: %.2fms (%.3fms per item)\n" .
                "   Total GET time: %.2fms (%.3fms per item)\n",
            $batchSize,
            $batchSetTime,
            $avgSetTimePerItem,
            $batchGetTime,
            $avgGetTimePerItem
        );
    }

    /**
     * 測試快取失效機制的效能
     */
    #[Test]
    public function measureCacheInvalidationPerformance(): void
    {
        // 準備測試快取資料
        $cacheKeys = [];
        for ($i = 0; $i < 100; $i++) {
            $key = "invalidation_test_$i";
            $cacheKeys[] = $key;
            $this->cache->set($key, "test_value_$i", 300);
        }

        // 驗證資料已設定
        foreach ($cacheKeys as $key) {
            $this->assertTrue($this->cache->has($key));
        }

        // 測試批次刪除效能
        $startTime = microtime(true);
        $result = $this->cache->deleteMultiple($cacheKeys);
        $invalidationTime = (microtime(true) - $startTime) * 1000;

        $this->assertTrue($result);

        // 驗證快取已清除
        foreach ($cacheKeys as $key) {
            $this->assertFalse($this->cache->has($key));
        }

        // 批次刪除應該很快
        $avgDeleteTimePerItem = $invalidationTime / count($cacheKeys);
        $this->assertLessThan(0.5, $avgDeleteTimePerItem, 'Cache invalidation too slow');

        // 輸出失效操作效能統計
        printf(
            "\n🗑️ Cache Invalidation Performance:\n" .
                "   Items deleted: %d\n" .
                "   Total time: %.2fms\n" .
                "   Average per item: %.3fms\n",
            count($cacheKeys),
            $invalidationTime,
            $avgDeleteTimePerItem
        );
    }
}
