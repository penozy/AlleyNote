<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

use AlleyNote\Shared\Cache\CacheServiceInterface;
use AlleyNote\Infrastructure\Cache\CacheKeys;

/**
 * 快取管理器
 * 
 * 提供高階快取操作介面，包含記憶化、模式操作、統計等功能。
 * 基於 CacheServiceInterface 實作，支援不同的快取後端。
 * 
 * @author AI Assistant
 * @version 1.0
 */
final class CacheManager
{
    private CacheServiceInterface $cache;
    private int $defaultTtl;

    public function __construct(
        CacheServiceInterface $cache,
        int $defaultTtl = 3600
    ) {
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * 取得快取值
     */
    public function get(string $key, $default = null)
    {
        $value = $this->cache->get($key);
        return $value !== null ? $value : $default;
    }

    /**
     * 設定快取值
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * 檢查快取是否存在
     */
    public function has(string $key): bool
    {
        return $this->cache->exists($key);
    }

    /**
     * 刪除快取
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * 清空所有快取
     */
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * 記憶化取得（如果不存在則執行回調並快取結果）
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 永久記憶化取得（直到手動刪除）
     */
    public function rememberForever(string $key, callable $callback)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->cache->set($key, $value); // 無 TTL，永不過期

        return $value;
    }

    /**
     * 取得多個快取值
     */
    public function many(array $keys): array
    {
        return $this->cache->getMultiple($keys);
    }

    /**
     * 設定多個快取值
     */
    public function putMany(array $values, ?int $ttl = null): bool
    {
        $ttl ??= $this->defaultTtl;
        return $this->cache->setMultiple($values, $ttl);
    }

    /**
     * 根據模式刪除快取
     * 注意：這個功能需要快取後端支援，目前僅支援部分實作
     */
    public function deletePattern(string $pattern): int
    {
        // 這個功能依賴具體的快取實作
        // Redis 支援 KEYS 命令，但記憶體快取不支援
        if ($this->cache instanceof RedisCache && method_exists($this->cache, 'deletePattern')) {
            return $this->cache->deletePattern($pattern);
        }

        // 對於不支援的實作，返回 0 表示未刪除任何項目
        return 0;
    }

    /**
     * 增加數值快取
     * 注意：這個功能需要快取後端支援
     */
    public function increment(string $key, int $value = 1): int
    {
        if ($this->cache instanceof RedisCache && method_exists($this->cache, 'increment')) {
            $result = $this->cache->increment($key, $value);
            return $result !== false ? $result : 0;
        }

        // 回退實作：取得目前值，增加後設定回去
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);

        return $new;
    }

    /**
     * 減少數值快取
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * 取得快取統計資訊
     * 注意：此實作可能不準確，依賴於快取後端
     */
    public function getStats(): array
    {
        // 基本統計資訊，實際實作可能需要更多細節
        return [
            'cache_backend' => get_class($this->cache),
            'default_ttl' => $this->defaultTtl,
            'supported_operations' => $this->getSupportedOperations(),
        ];
    }

    /**
     * 取得支援的操作列表
     */
    private function getSupportedOperations(): array
    {
        $operations = ['get', 'set', 'delete', 'exists', 'clear'];
        
        if (method_exists($this->cache, 'getMultiple')) {
            $operations[] = 'getMultiple';
        }
        if (method_exists($this->cache, 'setMultiple')) {
            $operations[] = 'setMultiple';
        }
        if (method_exists($this->cache, 'deleteMultiple')) {
            $operations[] = 'deleteMultiple';
        }
        if (method_exists($this->cache, 'increment')) {
            $operations[] = 'increment';
        }
        if (method_exists($this->cache, 'decrement')) {
            $operations[] = 'decrement';
        }

        return $operations;
    }

    /**
     * 估算記憶體使用量（簡化實作）
     */
    private function getMemoryUsage(): string
    {
        $memory = memory_get_usage(true);
        
        if ($memory < 1024) {
            return $memory . ' B';
        } elseif ($memory < 1048576) {
            return round($memory / 1024, 2) . ' KB';
        } else {
            return round($memory / 1048576, 2) . ' MB';
        }
    }

    /**
     * 清理過期的快取（依賴快取後端實作）
     */
    public function cleanup(): int
    {
        // 這個功能通常由快取後端自動處理
        // Redis 會自動清理過期鍵，記憶體快取則不支援 TTL
        if ($this->cache instanceof RedisCache && method_exists($this->cache, 'cleanup')) {
            return $this->cache->cleanup();
        }

        return 0;
    }

    /**
     * 檢查快取鍵是否有效.
     */
    public function isValidKey(string $key): bool
    {
        return CacheKeys::isValidKey($key);
    }
}
