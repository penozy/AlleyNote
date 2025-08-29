<?php

declare(strict_types=1);

namespace AlleyNote\Shared\Cache;

/**
 * 快取服務介面
 * 
 * 定義了系統統一的快取操作介面，支援常見的快取操作：
 * - get: 取得快取值
 * - set: 設定快取值（含 TTL 支援）
 * - delete: 刪除快取項目
 * - exists: 檢查快取是否存在
 * - clear: 清空所有快取
 * 
 * @author AI Assistant
 * @version 1.0
 */
interface CacheServiceInterface
{
    /**
     * 取得快取值
     * 
     * @param string $key 快取鍵
     * @return mixed 快取值，不存在時返回 null
     */
    public function get(string $key): mixed;

    /**
     * 設定快取值
     * 
     * @param string $key 快取鍵
     * @param mixed $value 快取值
     * @param int|null $ttl 存活時間（秒），null 表示永不過期
     * @return bool 設定成功返回 true
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 刪除快取項目
     * 
     * @param string $key 快取鍵
     * @return bool 刪除成功返回 true
     */
    public function delete(string $key): bool;

    /**
     * 檢查快取是否存在
     * 
     * @param string $key 快取鍵
     * @return bool 存在返回 true
     */
    public function exists(string $key): bool;

    /**
     * 清空所有快取
     * 
     * @return bool 清空成功返回 true
     */
    public function clear(): bool;

    /**
     * 批次取得多個快取值
     * 
     * @param array<string> $keys 快取鍵陣列
     * @return array<string, mixed> 鍵值對陣列，不存在的鍵將被忽略
     */
    public function getMultiple(array $keys): array;

    /**
     * 批次設定多個快取值
     * 
     * @param array<string, mixed> $values 鍵值對陣列
     * @param int|null $ttl 存活時間（秒），null 表示永不過期
     * @return bool 設定成功返回 true
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * 批次刪除多個快取項目
     * 
     * @param array<string> $keys 快取鍵陣列
     * @return bool 刪除成功返回 true
     */
    public function deleteMultiple(array $keys): bool;
}