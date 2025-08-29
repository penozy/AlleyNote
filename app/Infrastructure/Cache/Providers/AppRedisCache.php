<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Providers;

use App\Shared\Contracts\CacheInterface;
use Exception;
use Redis;

/**
 * Redis 快取服務實作
 * 
 * 使用 Redis 作為快取後端，實現 App\Shared\Contracts\CacheInterface 介面
 */
final class AppRedisCache implements CacheInterface
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

            // 設定序列化選項
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        } catch (Exception $e) {
            throw new Exception('Redis 初始化失敗: ' . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $key): mixed
    {
        $fullKey = $this->prefix . $key;

        try {
            $value = $this->redis->get($fullKey);
            return $value === false ? null : $value;
        } catch (Exception) {
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = $this->prefix . $key;

        try {
            if ($ttl !== null) {
                return $this->redis->setex($fullKey, $ttl, $value);
            }

            return $this->redis->set($fullKey, $value);
        } catch (Exception) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        try {
            $result = $this->redis->del($fullKey);
            return $result > 0;
        } catch (Exception) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        try {
            return $this->redis->exists($fullKey) > 0;
        } catch (Exception) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            // 如果有前綴，只刪除有前綴的鍵
            if (!empty($this->prefix)) {
                $pattern = $this->prefix . '*';
                $keys = $this->redis->keys($pattern);

                if (!empty($keys)) {
                    return $this->redis->del($keys) > 0;
                }
                return true;
            }

            // 沒有前綴時清空整個資料庫
            return $this->redis->flushDB();
        } catch (Exception) {
            return false;
        }
    }

    public function getMultiple(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $fullKeys = [];
        $keyMap = [];

        foreach ($keys as $key) {
            $fullKey = $this->prefix . $key;
            $fullKeys[] = $fullKey;
            $keyMap[$fullKey] = $key;
        }

        try {
            $values = $this->redis->mget($fullKeys);
            $result = [];

            foreach ($fullKeys as $index => $fullKey) {
                $value = $values[$index] ?? false;
                if ($value !== false) {
                    $result[$keyMap[$fullKey]] = $value;
                }
            }

            return $result;
        } catch (Exception) {
            return [];
        }
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if (empty($values)) {
            return true;
        }

        try {
            $success = true;

            foreach ($values as $key => $value) {
                if (!$this->set($key, $value, $ttl)) {
                    $success = false;
                }
            }

            return $success;
        } catch (Exception) {
            return false;
        }
    }

    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $fullKeys = [];
        foreach ($keys as $key) {
            $fullKeys[] = $this->prefix . $key;
        }

        try {
            $deleted = $this->redis->del($fullKeys);
            return $deleted > 0;
        } catch (Exception) {
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $fullKey = $this->prefix . $key;

        try {
            if ($value === 1) {
                return $this->redis->incr($fullKey);
            }

            return $this->redis->incrBy($fullKey, $value);
        } catch (Exception) {
            return false;
        }
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $fullKey = $this->prefix . $key;

        try {
            if ($value === 1) {
                return $this->redis->decr($fullKey);
            }

            return $this->redis->decrBy($fullKey, $value);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * 關閉 Redis 連接
     */
    public function __destruct()
    {
        try {
            if ($this->redis instanceof Redis) {
                $this->redis->close();
            }
        } catch (Exception) {
            // 忽略關閉時的例外
        }
    }

    /**
     * 取得 Redis 連接實例（用於測試或高級操作）
     * 
     * @return Redis
     */
    public function getRedisInstance(): Redis
    {
        return $this->redis;
    }

    /**
     * 取得快取鍵前綴
     * 
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
