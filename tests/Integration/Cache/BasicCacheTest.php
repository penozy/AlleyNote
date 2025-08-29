<?php

declare(strict_types=1);

namespace Tests\Integration\Cache;

use App\Infrastructure\Cache\Providers\AppRedisCache;
use App\Shared\Contracts\CacheInterface;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Redis 快取系統基本連接測試.
 */
final class BasicCacheTest extends TestCase
{
    private ?CacheInterface $cache = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 跳過測試如果 Redis 擴展不可用
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            $this->cache = new AppRedisCache(
                host: $_ENV['REDIS_HOST'] ?? 'redis',
                port: (int) ($_ENV['REDIS_PORT'] ?? 6379),
                prefix: 'test:basic:',
                database: 15, // 使用測試專用資料庫
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
        if ($this->cache !== null) {
            $this->cache->clear();
        }

        parent::tearDown();
    }

    public function testBasicCacheOperations(): void
    {
        if ($this->cache === null) {
            $this->markTestSkipped('Cache not available');
        }

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

    public function testCacheSerializationWithArrays(): void
    {
        if ($this->cache === null) {
            $this->markTestSkipped('Cache not available');
        }

        $testData = [
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

        $this->assertTrue($this->cache->set('complex_data', $testData, 300));
        $retrieved = $this->cache->get('complex_data');

        $this->assertSame($testData, $retrieved);
    }

    public function testCacheMultipleOperations(): void
    {
        if ($this->cache === null) {
            $this->markTestSkipped('Cache not available');
        }

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
}
