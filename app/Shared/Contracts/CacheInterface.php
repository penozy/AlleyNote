<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * 快取服務介面.
 *
 * 提供統一的快取操作介面，支援多種後端實作
 */
interface CacheInterface
{
    /**
     * 取得快取值
     *
     * @param string $key 快取鍵
     * @return mixed 快取值，不存在時回傳 null
     */
    public function get(string $key): mixed;

    /**
     * 設定快取值
     *
     * @param string $key 快取鍵
     * @param mixed $value 快取值
     * @param int|null $ttl 存活時間（秒），null 表示永不過期
     * @return bool 設定是否成功
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 刪除快取.
     *
     * @param string $key 快取鍵
     * @return bool 刪除是否成功
     */
    public function delete(string $key): bool;

    /**
     * 檢查快取是否存在.
     *
     * @param string $key 快取鍵
     * @return bool 是否存在
     */
    public function has(string $key): bool;

    /**
     * 清空所有快取.
     *
     * @return bool 清空是否成功
     */
    public function clear(): bool;

    /**
     * 批次取得快取值
     *
     * @param array<string> $keys 快取鍵陣列
     * @return array<string, mixed> 快取值陣列，key => value
     */
    public function getMultiple(array $keys): array;

    /**
     * 批次設定快取值
     *
     * @param array<string, mixed> $values 快取值陣列，key => value
     * @param int|null $ttl 存活時間（秒），null 表示永不過期
     * @return bool 設定是否成功
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * 批次刪除快取.
     *
     * @param array<string> $keys 快取鍵陣列
     * @return bool 刪除是否成功
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * 遞增計數器.
     *
     * @param string $key 快取鍵
     * @param int $value 遞增值，預設為 1
     * @return int|false 新的值，失敗時回傳 false
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * 遞減計數器.
     *
     * @param string $key 快取鍵
     * @param int $value 遞減值，預設為 1
     * @return int|false 新的值，失敗時回傳 false
     */
    public function decrement(string $key, int $value = 1): int|false;
}
