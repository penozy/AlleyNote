<?php

declare(strict_types=1);

namespace App\Domains\Security\Enums;

/**
 * 使用者活動行為類型枚舉
 * 定義所有可追蹤的使用者行為類型.
 */
enum ActivityType: string
{
    // === 認證相關行為 ===
    case LOGIN_SUCCESS = 'auth.login.success';
    case LOGIN_FAILED = 'auth.login.failed';
    case LOGOUT = 'auth.logout';
    case PASSWORD_CHANGED = 'auth.password.changed';
    case PASSWORD_RESET_REQUESTED = 'auth.password.reset_requested';
    case PASSWORD_RESET_COMPLETED = 'auth.password.reset_completed';
    case TWO_FACTOR_ENABLED = 'auth.2fa.enabled';
    case TWO_FACTOR_DISABLED = 'auth.2fa.disabled';
    case SESSION_EXPIRED = 'auth.session.expired';
    case ACCOUNT_LOCKED = 'auth.account.locked';
    case ACCOUNT_UNLOCKED = 'auth.account.unlocked';

        // === 文章管理行為 ===
    case POST_CREATED = 'post.created';
    case POST_UPDATED = 'post.updated';
    case POST_DELETED = 'post.deleted';
    case POST_PUBLISHED = 'post.published';
    case POST_UNPUBLISHED = 'post.unpublished';
    case POST_VIEWED = 'post.viewed';
    case POST_PINNED = 'post.pinned';
    case POST_UNPINNED = 'post.unpinned';

        // === 附件管理行為 ===
    case ATTACHMENT_UPLOADED = 'attachment.uploaded';
    case ATTACHMENT_DOWNLOADED = 'attachment.downloaded';
    case ATTACHMENT_DELETED = 'attachment.deleted';
    case ATTACHMENT_VIRUS_DETECTED = 'attachment.virus_detected';
    case ATTACHMENT_SIZE_EXCEEDED = 'attachment.size_exceeded';
    case ATTACHMENT_PERMISSION_DENIED = 'attachment.permission_denied';

        // === 使用者管理行為 ===
    case USER_REGISTERED = 'user.registered';
    case USER_PROFILE_UPDATED = 'user.profile.updated';
    case USER_AVATAR_CHANGED = 'user.avatar.changed';
    case USER_EMAIL_VERIFIED = 'user.email.verified';
    case USER_BANNED = 'user.banned';
    case USER_UNBANNED = 'user.unbanned';

        // === 權限與角色管理 ===
    case ROLE_ASSIGNED = 'role.assigned';
    case ROLE_REMOVED = 'role.removed';
    case PERMISSION_GRANTED = 'permission.granted';
    case PERMISSION_REVOKED = 'permission.revoked';
    case PERMISSION_DENIED = 'permission.denied';

        // === 系統安全行為 ===
    case SUSPICIOUS_ACTIVITY_DETECTED = 'security.suspicious_activity';
    case SECURITY_ACTIVITY_SCAN_COMPLETED = 'security.scan.completed';
    case BRUTE_FORCE_ATTEMPT = 'security.brute_force';
    case IP_BLOCKED = 'security.ip.blocked';
    case IP_UNBLOCKED = 'security.ip.unblocked';
    case CSRF_ATTACK_BLOCKED = 'security.csrf.blocked';
    case XSS_ATTACK_BLOCKED = 'security.xss.blocked';
    case SQL_INJECTION_BLOCKED = 'security.sql_injection.blocked';
    case CSP_VIOLATION = 'security.csp.violation';

        // === 管理員操作 ===
    case ADMIN_LOGIN = 'admin.login';
    case ADMIN_LOGOUT = 'admin.logout';
    case SYSTEM_SETTINGS_CHANGED = 'admin.settings.changed';
    case USER_IMPERSONATED = 'admin.user.impersonated';
    case CACHE_CLEARED = 'admin.cache.cleared';
    case BACKUP_CREATED = 'admin.backup.created';
    case BACKUP_RESTORED = 'admin.backup.restored';

        // === API 操作 ===
    case API_KEY_CREATED = 'api.key.created';
    case API_KEY_DELETED = 'api.key.deleted';
    case API_RATE_LIMIT_EXCEEDED = 'api.rate_limit.exceeded';
    case API_UNAUTHORIZED_ACCESS = 'api.unauthorized';

        // === 資料匯出入 ===
    case DATA_EXPORTED = 'data.exported';
    case DATA_IMPORTED = 'data.imported';
    case GDPR_DATA_REQUEST = 'gdpr.data.requested';
    case GDPR_DATA_DELETED = 'gdpr.data.deleted';

    /**
     * 取得行為類型的分類.
     */
    public function getCategory(): ActivityCategory
    {
        return match ($this) {
            self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGOUT,
            self::PASSWORD_CHANGED, self::PASSWORD_RESET_REQUESTED,
            self::PASSWORD_RESET_COMPLETED, self::TWO_FACTOR_ENABLED,
            self::TWO_FACTOR_DISABLED, self::SESSION_EXPIRED,
            self::ACCOUNT_LOCKED, self::ACCOUNT_UNLOCKED => ActivityCategory::AUTHENTICATION,

            self::POST_CREATED, self::POST_UPDATED, self::POST_DELETED,
            self::POST_PUBLISHED, self::POST_UNPUBLISHED, self::POST_VIEWED,
            self::POST_PINNED, self::POST_UNPINNED => ActivityCategory::CONTENT,

            self::ATTACHMENT_UPLOADED, self::ATTACHMENT_DOWNLOADED,
            self::ATTACHMENT_DELETED, self::ATTACHMENT_VIRUS_DETECTED,
            self::ATTACHMENT_SIZE_EXCEEDED, self::ATTACHMENT_PERMISSION_DENIED => ActivityCategory::FILE,

            self::USER_REGISTERED, self::USER_PROFILE_UPDATED,
            self::USER_AVATAR_CHANGED, self::USER_EMAIL_VERIFIED,
            self::USER_BANNED, self::USER_UNBANNED => ActivityCategory::USER_MANAGEMENT,

            self::ROLE_ASSIGNED, self::ROLE_REMOVED,
            self::PERMISSION_GRANTED, self::PERMISSION_REVOKED,
            self::PERMISSION_DENIED => ActivityCategory::AUTHORIZATION,

            self::SUSPICIOUS_ACTIVITY_DETECTED, self::SECURITY_ACTIVITY_SCAN_COMPLETED,
            self::BRUTE_FORCE_ATTEMPT, self::IP_BLOCKED, self::IP_UNBLOCKED,
            self::CSRF_ATTACK_BLOCKED, self::XSS_ATTACK_BLOCKED,
            self::SQL_INJECTION_BLOCKED, self::CSP_VIOLATION => ActivityCategory::SECURITY,

            self::ADMIN_LOGIN, self::ADMIN_LOGOUT, self::SYSTEM_SETTINGS_CHANGED,
            self::USER_IMPERSONATED, self::CACHE_CLEARED, self::BACKUP_CREATED,
            self::BACKUP_RESTORED => ActivityCategory::ADMINISTRATION,

            self::API_KEY_CREATED, self::API_KEY_DELETED,
            self::API_RATE_LIMIT_EXCEEDED, self::API_UNAUTHORIZED_ACCESS => ActivityCategory::API,

            self::DATA_EXPORTED, self::DATA_IMPORTED,
            self::GDPR_DATA_REQUEST, self::GDPR_DATA_DELETED => ActivityCategory::DATA_MANAGEMENT,
        };
    }

    /**
     * 取得行為類型的嚴重程度.
     */
    public function getSeverity(): ActivitySeverity
    {
        return match ($this) {
            // INFO 等級：一般性資訊操作
            self::LOGIN_SUCCESS, self::LOGOUT, self::POST_VIEWED,
            self::ATTACHMENT_DOWNLOADED, self::ADMIN_LOGIN, self::ADMIN_LOGOUT => ActivitySeverity::LOW,

            // LOW 等級：基本操作
            self::POST_CREATED, self::POST_UPDATED, self::USER_REGISTERED,
            self::PASSWORD_CHANGED, self::POST_PUBLISHED, self::POST_UNPUBLISHED,
            self::POST_PINNED, self::POST_UNPINNED, self::ATTACHMENT_UPLOADED,
            self::USER_PROFILE_UPDATED, self::USER_AVATAR_CHANGED,
            self::USER_EMAIL_VERIFIED, self::PASSWORD_RESET_REQUESTED,
            self::PASSWORD_RESET_COMPLETED, self::DATA_EXPORTED, self::DATA_IMPORTED,
            self::API_KEY_CREATED, self::API_KEY_DELETED, self::CACHE_CLEARED,
            self::BACKUP_CREATED, self::BACKUP_RESTORED,
            self::SECURITY_ACTIVITY_SCAN_COMPLETED => ActivitySeverity::NORMAL,

            // MEDIUM 等級：中等重要操作
            self::POST_DELETED, self::USER_BANNED, self::USER_UNBANNED,
            self::ROLE_ASSIGNED, self::ROLE_REMOVED, self::PERMISSION_GRANTED,
            self::PERMISSION_REVOKED, self::ATTACHMENT_DELETED,
            self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED,
            self::IP_BLOCKED, self::IP_UNBLOCKED, self::SESSION_EXPIRED,
            self::ACCOUNT_LOCKED, self::ACCOUNT_UNLOCKED,
            self::SYSTEM_SETTINGS_CHANGED, self::USER_IMPERSONATED,
            self::ATTACHMENT_SIZE_EXCEEDED, self::GDPR_DATA_REQUEST,
            self::GDPR_DATA_DELETED => ActivitySeverity::MEDIUM,

            // HIGH 等級：高重要性操作
            self::LOGIN_FAILED, self::BRUTE_FORCE_ATTEMPT,
            self::SUSPICIOUS_ACTIVITY_DETECTED, self::PERMISSION_DENIED,
            self::API_UNAUTHORIZED_ACCESS, self::API_RATE_LIMIT_EXCEEDED,
            self::ATTACHMENT_PERMISSION_DENIED => ActivitySeverity::HIGH,

            // CRITICAL 等級：關鍵安全事件
            self::CSRF_ATTACK_BLOCKED, self::XSS_ATTACK_BLOCKED,
            self::SQL_INJECTION_BLOCKED, self::ATTACHMENT_VIRUS_DETECTED,
            self::CSP_VIOLATION => ActivitySeverity::CRITICAL,
        };
    }

    /**
     * 判斷是否為失敗的行為.
     */
    public function isFailureAction(): bool
    {
        return match ($this) {
            self::LOGIN_FAILED, self::PERMISSION_DENIED,
            self::API_UNAUTHORIZED_ACCESS, self::ATTACHMENT_SIZE_EXCEEDED,
            self::API_RATE_LIMIT_EXCEEDED, self::ATTACHMENT_PERMISSION_DENIED => true,
            default => false,
        };
    }

    /**
     * 判斷是否為安全相關的行為.
     */
    public function isSecurityRelated(): bool
    {
        return $this->getCategory() === ActivityCategory::SECURITY
            || $this->isFailureAction();
    }

    /**
     * 取得人類可讀的描述.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => '使用者登入成功',
            self::LOGIN_FAILED => '使用者登入失敗',
            self::LOGOUT => '使用者登出',
            self::POST_CREATED => '建立新文章',
            self::POST_UPDATED => '更新文章',
            self::POST_DELETED => '刪除文章',
            self::ATTACHMENT_UPLOADED => '上傳附件',
            self::ATTACHMENT_DOWNLOADED => '下載附件',
            self::ATTACHMENT_DELETED => '刪除附件',
            self::ATTACHMENT_VIRUS_DETECTED => '附件病毒檢測',
            self::ATTACHMENT_SIZE_EXCEEDED => '附件大小超限',
            self::ATTACHMENT_PERMISSION_DENIED => '附件權限被拒',
            self::SUSPICIOUS_ACTIVITY_DETECTED => '檢測到可疑活動',
            self::SECURITY_ACTIVITY_SCAN_COMPLETED => '安全掃描完成',
            self::CSP_VIOLATION => 'CSP違規檢測',
            // ... 可以繼續添加更多描述
            default => $this->value,
        };
    }
}
