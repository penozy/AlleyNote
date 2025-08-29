<?php

declare(strict_types=1);

namespace AlleyNote\Infrastructure\Cache;

class CacheKeys
{
    private const PREFIX = 'alleynote';

    private const SEPARATOR = ':';

    /**
     * 貼文相關快取鍵.
     */
    public static function post(int $id): string
    {
        return self::buildKey('post', $id);
    }

    public static function postByUuid(string $uuid): string
    {
        return self::buildKey('post', 'uuid', $uuid);
    }

    public static function postList(int $page, string $status = 'published'): string
    {
        return self::buildKey('posts', $status, 'page', $page);
    }

    public static function pinnedPosts(): string
    {
        return self::buildKey('posts', 'pinned');
    }

    public static function postsByCategory(string $category, int $page = 1): string
    {
        return self::buildKey('posts', 'category', $category, 'page', $page);
    }

    public static function userPosts(int $userId, int $page = 1): string
    {
        return self::buildKey('user', $userId, 'posts', 'page', $page);
    }

    public static function postTags(int $postId): string
    {
        return self::buildKey('post', $postId, 'tags');
    }

    public static function postViews(int $postId): string
    {
        return self::buildKey('post', $postId, 'views');
    }

    public static function postComments(int $postId, int $page = 1): string
    {
        return self::buildKey('post', $postId, 'comments', 'page', $page);
    }

    /**
     * 使用者相關快取鍵.
     */
    public static function user(int $userId): string
    {
        return self::buildKey('user', $userId);
    }

    public static function userByEmail(string $email): string
    {
        return self::buildKey('user', 'email', md5($email));
    }

    public static function userProfile(int $userId): string
    {
        return self::buildKey('user', $userId, 'profile');
    }

    public static function userPermissions(int $userId): string
    {
        return self::buildKey('user', $userId, 'permissions');
    }

    public static function userSessions(int $userId): string
    {
        return self::buildKey('user', $userId, 'sessions');
    }

    /**
     * 系統設定相關快取鍵.
     */
    public static function systemConfig(): string
    {
        return self::buildKey('system', 'config');
    }

    public static function siteSettings(): string
    {
        return self::buildKey('site', 'settings');
    }

    public static function menuItems(): string
    {
        return self::buildKey('menu', 'items');
    }

    /**
     * 標籤相關快取鍵.
     */
    public static function allTags(): string
    {
        return self::buildKey('tags', 'all');
    }

    public static function popularTags(int $limit = 10): string
    {
        return self::buildKey('tags', 'popular', $limit);
    }

    public static function tagPosts(int $tagId, int $page = 1): string
    {
        return self::buildKey('tag', $tagId, 'posts', 'page', $page);
    }

    /**
     * 統計相關快取鍵.
     */
    public static function siteStats(): string
    {
        return self::buildKey('stats', 'site');
    }

    public static function dailyStats(string $date): string
    {
        return self::buildKey('stats', 'daily', $date);
    }

    public static function monthlyStats(string $yearMonth): string
    {
        return self::buildKey('stats', 'monthly', $yearMonth);
    }

    /**
     * 搜尋相關快取鍵.
     */
    public static function searchResults(string $query, int $page = 1): string
    {
        return self::buildKey('search', md5($query), 'page', $page);
    }

    public static function popularSearches(): string
    {
        return self::buildKey('search', 'popular');
    }

    /**
     * 附件相關快取鍵.
     */
    public static function attachment(int $attachmentId): string
    {
        return self::buildKey('attachment', $attachmentId);
    }

    public static function postAttachments(int $postId): string
    {
        return self::buildKey('post', $postId, 'attachments');
    }

    /**
     * 速率限制相關快取鍵.
     */
    public static function rateLimitByIp(string $ip, string $action): string
    {
        return self::buildKey('rate_limit', 'ip', md5($ip), $action);
    }

    public static function rateLimitByUser(int $userId, string $action): string
    {
        return self::buildKey('rate_limit', 'user', $userId, $action);
    }

    /**
     * 鎖定相關快取鍵.
     */
    public static function postLock(int $postId): string
    {
        return self::buildKey('lock', 'post', $postId);
    }

    public static function userLock(int $userId): string
    {
        return self::buildKey('lock', 'user', $userId);
    }

    /**
     * 通知相關快取鍵.
     */
    public static function userNotifications(int $userId): string
    {
        return self::buildKey('user', $userId, 'notifications');
    }

    public static function unreadNotificationCount(int $userId): string
    {
        return self::buildKey('user', $userId, 'notifications', 'unread_count');
    }

    /**
     * 建立快取鍵的通用方法.
     */
    private static function buildKey(...$parts): string
    {
        // 過濾空值並轉換為字串
        $cleanParts = array_filter(
            array_map('strval', $parts),
            fn($part) => $part !== '',
        );

        return self::PREFIX . self::SEPARATOR . implode(self::SEPARATOR, $cleanParts);
    }

    /**
     * 取得快取鍵的前綴.
     */
    public static function getPrefix(): string
    {
        return self::PREFIX;
    }

    /**
     * 取得分隔符號
     */
    public static function getSeparator(): string
    {
        return self::SEPARATOR;
    }

    /**
     * 驗證快取鍵是否屬於此應用程式.
     */
    public static function isValidKey(string $key): bool
    {
        return str_starts_with($key, self::PREFIX . self::SEPARATOR);
    }

    /**
     * 解析快取鍵取得各部分.
     */
    public static function parseKey(string $key): array
    {
        if (!self::isValidKey($key)) {
            return [];
        }

        $withoutPrefix = substr($key, strlen(self::PREFIX . self::SEPARATOR));

        return explode(self::SEPARATOR, $withoutPrefix);
    }

    /**
     * 建立模式匹配的快取鍵（用於刪除相關快取）.
     */
    public static function pattern(...$parts): string
    {
        $pattern = self::buildKey(...$parts);

        return $pattern . '*';
    }

    /**
     * 特定模式的快取清理方法.
     */
    public static function userPattern(int $userId): string
    {
        return self::pattern('user', $userId);
    }

    public static function postPattern(int $postId): string
    {
        return self::pattern('post', $postId);
    }

    public static function postsListPattern(): string
    {
        return self::pattern('posts');
    }

    public static function statsPattern(): string
    {
        return self::pattern('stats');
    }
}
