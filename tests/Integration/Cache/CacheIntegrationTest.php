<?php

declare(strict_types=1);

namespace Tests\Integration\Cache;

use App\Infrastructure\Cache\RedisCache;
use App\Shared\Contracts\CacheInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Redis 快取整合測試.
 *
 * 測試 Redis 快取是否與活動記錄服務正確整合
 */
final class CacheIntegrationTest extends TestCase
{
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // 跳過測試如果 Redis 不可用
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            $this->cache = new RedisCache(
                host: $_ENV['REDIS_HOST'] ?? 'redis',
                port: (int) ($_ENV['REDIS_PORT'] ?? 6379),
                prefix: 'test:cache:integration:',
                database: 15, // 使用測試專用的資料庫
            );

            // 清理測試資料庫
            $this->cache->clear();
        } catch (Exception $e) {
            $this->markTestSkipped('Redis connection failed: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // 清理測試資料
        if (isset($this->cache)) {
            $this->cache->clear();
        }

        parent::tearDown();
    }

    public function testRedisCacheBasicOperations(): void
    {
        // Test set/get
        $this->assertTrue($this->cache->set('test_key', 'test_value', 60));
        $this->assertSame('test_value', $this->cache->get('test_key'));

        // Test has
        $this->assertTrue($this->cache->has('test_key'));
        $this->assertFalse($this->cache->has('nonexistent_key'));

        // Test delete
        $this->assertTrue($this->cache->delete('test_key'));
        $this->assertNull($this->cache->get('test_key'));
        $this->assertFalse($this->cache->has('test_key'));
    }

    public function testRedisCacheMultipleOperations(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // Test setMultiple
        $this->assertTrue($this->cache->setMultiple($data, 60));

        // Test getMultiple
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3', 'nonexistent']);
        $this->assertSame('value1', $result['key1']);
        $this->assertSame('value2', $result['key2']);
        $this->assertSame('value3', $result['key3']);
        $this->assertArrayNotHasKey('nonexistent', $result);

        // Test deleteMultiple
        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key3']));
        $this->assertNull($this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testRedisCacheCounterOperations(): void
    {
        $key = 'counter_test';

        // Test increment from zero
        $result = $this->cache->increment($key);
        $this->assertSame(1, $result);

        // Test increment with value
        $result = $this->cache->increment($key, 5);
        $this->assertSame(6, $result);

        // Test decrement
        $result = $this->cache->decrement($key, 2);
        $this->assertSame(4, $result);

        // Test decrement to negative
        $result = $this->cache->decrement($key, 10);
        $this->assertSame(-6, $result);
    }

    public function testRedisCacheSerializationWithComplexData(): void
    {
        $complexData = [
            'user_id' => 123,
            'activities' => [
                ['type' => 'LOGIN', 'timestamp' => '2024-01-15 10:00:00'],
                ['type' => 'LOGOUT', 'timestamp' => '2024-01-15 18:00:00'],
            ],
            'metadata' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Agent',
            ],
        ];

        $this->assertTrue($this->cache->set('complex_data', $complexData, 300));
        $retrieved = $this->cache->get('complex_data');

        $this->assertSame($complexData, $retrieved);
    }

    public function testRedisCacheActivityLogIntegration(): void
    {
        // 這個測試需要完整的 DI 容器設定
        // 由於整合測試的複雜性，我們先創建一個簡化版本

        $cacheKeyPrefix = 'activity_log:';
        $userId = 123;
        $statsKey = $cacheKeyPrefix . 'stats:summary';
        $userKey = $cacheKeyPrefix . "user:{$userId}:activities";

        // 模擬快取活動資料
        $statsData = [
            'total_activities' => 100,
            'today_activities' => 10,
            'unique_users' => 5,
        ];

        $userActivities = [
            ['type' => 'LOGIN_SUCCESS', 'timestamp' => '2024-01-15 09:00:00'],
            ['type' => 'LOGOUT', 'timestamp' => '2024-01-15 17:00:00'],
        ];

        // 設定快取
        $this->assertTrue($this->cache->set($statsKey, $statsData, 3600));
        $this->assertTrue($this->cache->set($userKey, $userActivities, 900));

        // 驗證快取內容
        $this->assertSame($statsData, $this->cache->get($statsKey));
        $this->assertSame($userActivities, $this->cache->get($userKey));

        // 模擬快取失效（新活動記錄後）
        $this->assertTrue($this->cache->delete($statsKey));
        $this->assertTrue($this->cache->delete($userKey));

        // 驗證已失效
        $this->assertNull($this->cache->get($statsKey));
        $this->assertNull($this->cache->get($userKey));
    }

    public function testRedisCacheExpirationAndCleanup(): void
    {
        // 測試 TTL 功能
        $this->assertTrue($this->cache->set('ttl_test', 'value', 1)); // 1秒過期
        $this->assertSame('value', $this->cache->get('ttl_test'));

        // 等待過期
        sleep(2);
        $this->assertNull($this->cache->get('ttl_test'));
    }

    public function testCacheKeyPrefixing(): void
    {
        // 測試鍵前綴是否正確應用
        $this->assertTrue($this->cache->set('prefixed_key', 'prefixed_value'));

        // 直接連接 Redis 檢查鍵是否包含前綴
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int) ($_ENV['REDIS_PORT'] ?? 6379));
        $redis->select(15); // 使用相同的測試資料庫

        $keys = $redis->keys('test:cache:integration:*');
        $this->assertNotEmpty($keys);

        $found = false;
        foreach ($keys as $key) {
            if (str_contains($key, 'prefixed_key')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Key with prefix not found in Redis');

        $redis->close();
    }
}
