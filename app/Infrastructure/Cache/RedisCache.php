<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

use AlleyNote\Shared\Cache\CacheServiceInterface;
use Exception;
use Redis;
use Throwable;

/**
 * Redis 快取服務實作
 * 
 * 使用 Redis 作為快取後端的實作
 */
class RedisCache implements CacheServiceInterface
{
    private Redis $redis;
    private string $prefix;

    /**
     * @param string $host Redis 主機
     * @param int $port Redis 埠號
     * @param string $prefix 快取鍵前綴
     * @param int $database 資料庫編號
     * @param string|null $password Redis 密碼
     * 
     * @throws Exception 當連接失敗時
     */
    public function __construct(
        string $host = 'redis',
        int $port = 6379,
        string $prefix = 'alleynote:',
        int $database = 0,
        ?string $password = null
    ) {
        $this->redis = new Redis();
        $this->prefix = $prefix;

        try {
            if (!$this->redis->connect($host, $port, 2.5)) {
                throw new Exception("無法連接到 Redis 服務器 {$host}:{$port}");
            }

            if ($password !== null) {
                if (!$this->redis->auth($password)) {
                    throw new Exception('Redis 認證失敗');
                }
            }

            if (!$this->redis->select($database)) {
                throw new Exception("無法選擇 Redis 資料庫 {$database}");
            }

            // 設定序列化模式
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        } catch (Throwable $e) {
            throw new Exception("Redis 初始化失敗: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 產生完整的快取鍵
     */
    private function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key): mixed
    {
        try {
            $value = $this->redis->get($this->getKey($key));
            return $value === false ? null : $value;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $redisKey = $this->getKey($key);
            
            if ($ttl === null) {
                return $this->redis->set($redisKey, $value);
            }
            
            return $this->redis->setex($redisKey, $ttl, $value);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->redis->del($this->getKey($key)) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->redis->exists($this->getKey($key)) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            return $this->redis->flushDB();
        } catch (Throwable) {
            return false;
        }
    }

    public function getMultiple(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        try {
            $redisKeys = array_map([$this, 'getKey'], $keys);
            $values = $this->redis->mget($redisKeys);
            
            $result = [];
            foreach ($keys as $index => $originalKey) {
                $value = $values[$index] ?? null;
                $result[$originalKey] = $value === false ? null : $value;
            }
            
            return $result;
        } catch (Throwable) {
            return array_fill_keys($keys, null);
        }
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if (empty($values)) {
            return true;
        }

        try {
            if ($ttl === null) {
                $redisValues = [];
                foreach ($values as $key => $value) {
                    $redisValues[$this->getKey($key)] = $value;
                }
                return $this->redis->mset($redisValues);
            }

            // 當有 TTL 時，需要逐一設定
            $success = true;
            foreach ($values as $key => $value) {
                if (!$this->set($key, $value, $ttl)) {
                    $success = false;
                }
            }
            return $success;
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        try {
            $redisKeys = array_map([$this, 'getKey'], $keys);
            return $this->redis->del(...$redisKeys) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->incrBy($this->getKey($key), $value);
        } catch (Throwable) {
            return false;
        }
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->decrBy($this->getKey($key), $value);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 取得 Redis 連接資訊
     * 
     * @return array<string, mixed>
     */
    public function getConnectionInfo(): array
    {
        try {
            return [
                'connected' => $this->redis->isConnected(),
                'info' => $this->redis->info(),
                'prefix' => $this->prefix,
            ];
        } catch (Throwable) {
            return [
                'connected' => false,
                'info' => null,
                'prefix' => $this->prefix,
            ];
        }
    }

    /**
     * 清理類別時關閉連接
     */
    public function __destruct()
    {
        try {
            if ($this->redis->isConnected()) {
                $this->redis->close();
            }
        } catch (Throwable) {
            // 忽略析構函數中的錯誤
        }
    }
}