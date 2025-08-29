<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\RedisCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisCacheTest extends TestCase
{
    private RedisCache $cache;

    protected function setUp(): void
    {
        // 檢查 Redis 擴展是否可用
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis 擴展不可用');
        }

        try {
            $this->cache = new RedisCache();
        } catch (\Exception $e) {
            $this->markTestSkipped('無法連接到 Redis 服務: ' . $e->getMessage());
        }

        // 清空測試資料庫
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
        }
    }

    #[Test]
    public function setAndGetValue(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $result = $this->cache->set($key, $value);
        $this->assertTrue($result);

        $retrievedValue = $this->cache->get($key);
        $this->assertEquals($value, $retrievedValue);
    }

    #[Test]
    public function setAndGetComplexValue(): void
    {
        $key = 'test_complex';
        $value = [
            'name' => 'Test User',
            'age' => 30,
            'active' => true,
            'tags' => ['php', 'redis', 'cache'],
        ];

        $result = $this->cache->set($key, $value);
        $this->assertTrue($result);

        $retrievedValue = $this->cache->get($key);
        $this->assertEquals($value, $retrievedValue);
    }

    #[Test]
    public function setWithTtl(): void
    {
        $key = 'test_ttl';
        $value = 'expires_soon';
        $ttl = 1; // 1 秒

        $result = $this->cache->set($key, $value, $ttl);
        $this->assertTrue($result);

        // 立即取得應該還存在
        $retrievedValue = $this->cache->get($key);
        $this->assertEquals($value, $retrievedValue);

        // 等待過期
        sleep(2);

        // 現在應該是 null
        $expiredValue = $this->cache->get($key);
        $this->assertNull($expiredValue);
    }

    #[Test]
    public function hasMethod(): void
    {
        $key = 'test_exists';
        $value = 'exists';

        $this->assertFalse($this->cache->has($key));

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
    }

    #[Test]
    public function deleteMethod(): void
    {
        $key = 'test_delete';
        $value = 'to_be_deleted';

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));

        $result = $this->cache->delete($key);
        $this->assertTrue($result);
        $this->assertFalse($this->cache->has($key));
    }

    #[Test]
    public function getMultiple(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // 設定多個值
        foreach ($data as $key => $value) {
            $this->cache->set($key, $value);
        }

        // 批次取得
        $keys = array_keys($data);
        $result = $this->cache->getMultiple($keys);

        $this->assertEquals($data, $result);
    }

    #[Test]
    public function setMultiple(): void
    {
        $data = [
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'value3',
        ];

        $result = $this->cache->setMultiple($data);
        $this->assertTrue($result);

        // 驗證所有值都已設定
        foreach ($data as $key => $expectedValue) {
            $actualValue = $this->cache->get($key);
            $this->assertEquals($expectedValue, $actualValue);
        }
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $keys = ['del1', 'del2', 'del3'];
        $value = 'to_delete';

        // 設定多個值
        foreach ($keys as $key) {
            $this->cache->set($key, $value);
        }

        // 批次刪除
        $result = $this->cache->deleteMultiple($keys);
        $this->assertTrue($result);

        // 驗證都已刪除
        foreach ($keys as $key) {
            $this->assertFalse($this->cache->has($key));
        }
    }

    #[Test]
    public function incrementCounter(): void
    {
        $key = 'counter';

        // 初始遞增
        $result = $this->cache->increment($key);
        $this->assertEquals(1, $result);

        // 再次遞增
        $result = $this->cache->increment($key);
        $this->assertEquals(2, $result);

        // 遞增指定值
        $result = $this->cache->increment($key, 5);
        $this->assertEquals(7, $result);
    }

    #[Test]
    public function decrementCounter(): void
    {
        $key = 'counter_dec';

        // 設定初始值
        $this->cache->set($key, 10);

        // 遞減
        $result = $this->cache->decrement($key);
        $this->assertEquals(9, $result);

        // 遞減指定值
        $result = $this->cache->decrement($key, 3);
        $this->assertEquals(6, $result);
    }

    #[Test]
    public function connectionInfo(): void
    {
        $info = $this->cache->getConnectionInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('connected', $info);
        $this->assertArrayHasKey('prefix', $info);
        $this->assertTrue($info['connected']);
    }

    #[Test]
    public function getNonExistentKey(): void
    {
        $value = $this->cache->get('non_existent_key');
        $this->assertNull($value);
    }

    #[Test]
    public function deleteNonExistentKey(): void
    {
        $result = $this->cache->delete('non_existent_key');
        $this->assertFalse($result);
    }
}