<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

use AlleyNote\Shared\Cache\CacheServiceInterface;

/**
 * 記憶體快取服務實作
 * 
 * 使用陣列作為後端儲存的簡單快取實作，主要用於測試環境。
 * 注意：此實作不支援 TTL 和持久化。
 * 
 * @author AI Assistant
 * @version 1.0
 */
final class MemoryCache implements CacheServiceInterface
{
    /** @var array<string, mixed> 快取儲存 */
    private array $cache = [];

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = $value;
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($this->cache[$key])) {
                $result[$key] = $this->cache[$key];
            }
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->cache[$key] = $value;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $deleted = false;
        foreach ($keys as $key) {
            if (isset($this->cache[$key])) {
                unset($this->cache[$key]);
                $deleted = true;
            }
        }
        return $deleted;
    }

    /**
     * 取得所有快取項目數量
     * 
     * @return int 快取項目數量
     */
    public function count(): int
    {
        return count($this->cache);
    }

    /**
     * 取得所有快取鍵
     * 
     * @return array<string> 快取鍵陣列
     */
    public function keys(): array
    {
        return array_keys($this->cache);
    }
}
