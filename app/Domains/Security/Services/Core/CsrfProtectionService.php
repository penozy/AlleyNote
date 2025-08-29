<?php

declare(strict_types=1);

namespace App\Domains\Security\Services\Core;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityStatus;
use App\Domains\Security\Enums\ActivityType;
use App\Shared\Exceptions\CsrfTokenException;
use Exception;

class CsrfProtectionService
{
    private const TOKEN_LENGTH = 32;

    private const TOKEN_EXPIRY = 3600; // 1 hour

    private const TOKEN_POOL_SIZE = 5; // 權杖池大小

    private const TOKEN_POOL_KEY = 'csrf_token_pool';

    public function __construct(
        private ActivityLoggingServiceInterface $activityLogger,
    ) {}

    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        // 初始化權杖池（如果不存在）
        if (!isset($_SESSION[self::TOKEN_POOL_KEY])) {
            $_SESSION[self::TOKEN_POOL_KEY] = [];
        }

        // 加入新權杖到池中
        $_SESSION[self::TOKEN_POOL_KEY][$token] = time();

        // 清理過期的權杖
        $this->cleanExpiredTokens();

        // 限制池大小
        $this->limitPoolSize();

        // 設定當前權杖（向後相容）
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        // 記錄代幣生成活動
        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED, // 使用通用安全事件
                status: ActivityStatus::SUCCESS,
                targetType: 'csrf_token',
                targetId: session_id(),
                description: 'CSRF 代幣已生成',
                metadata: [
                    'session_id' => session_id(),
                    'token_pool_size' => count($_SESSION[self::TOKEN_POOL_KEY] ?? []),
                    'max_pool_size' => self::TOKEN_POOL_SIZE,
                ],
            ),
        );

        return $token;
    }

    public function validateToken(?string $token): void
    {
        if (empty($token)) {
            $this->logCsrfValidationFailure('', 'missing_token');

            throw new CsrfTokenException('缺少 CSRF token');
        }

        try {
            // 檢查權杖池模式
            if (isset($_SESSION[self::TOKEN_POOL_KEY]) && is_array($_SESSION[self::TOKEN_POOL_KEY])) {
                $this->validateTokenFromPool($token);
            } else {
                // 降級到單一權杖模式
                $this->validateSingleToken($token);
            }

            // 記錄成功驗證
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
                    status: ActivityStatus::SUCCESS,
                    targetType: 'csrf_token',
                    targetId: session_id(),
                    description: 'CSRF 代幣驗證成功',
                    metadata: [
                        'session_id' => session_id(),
                        'validation_mode' => isset($_SESSION[self::TOKEN_POOL_KEY]) ? 'pool' : 'single',
                    ],
                ),
            );
        } catch (CsrfTokenException $e) {
            $this->logCsrfValidationFailure($token, $e->getMessage());

            throw $e;
        }
    }

    /**
     * 從權杖池中驗證權杖.
     */
    private function validateTokenFromPool(string $token): void
    {
        $tokenPool = $_SESSION[self::TOKEN_POOL_KEY];

        // 使用恆定時間比較防止時序攻擊
        $found = false;
        $tokenTime = null;

        foreach ($tokenPool as $poolToken => $timestamp) {
            if (hash_equals($poolToken, $token)) {
                $found = true;
                $tokenTime = $timestamp;
                break;
            }
        }

        if (!$found) {
            throw new CsrfTokenException('CSRF token 驗證失敗');
        }

        // 檢查權杖是否過期
        if (time() - $tokenTime > self::TOKEN_EXPIRY) {
            throw new CsrfTokenException('CSRF token 已過期');
        }

        // 使用後移除權杖（單次使用）
        unset($_SESSION[self::TOKEN_POOL_KEY][$token]);

        // 產生新權杖以維持池的大小
        $this->generateToken();
    }

    /**
     * 單一權杖驗證（向後相容）.
     */
    private function validateSingleToken(string $token): void
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            throw new CsrfTokenException('無效的 CSRF token');
        }

        // 使用恆定時間比較防止時序攻擊
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new CsrfTokenException('CSRF token 驗證失敗');
        }

        if (time() - $_SESSION['csrf_token_time'] > self::TOKEN_EXPIRY) {
            throw new CsrfTokenException('CSRF token 已過期');
        }

        // 更新 token 以防止重放攻擊
        $this->generateToken();
    }

    /**
     * 清理過期的權杖.
     */
    private function cleanExpiredTokens(): void
    {
        if (!isset($_SESSION[self::TOKEN_POOL_KEY])) {
            return;
        }

        $currentTime = time();
        $tokenPool = $_SESSION[self::TOKEN_POOL_KEY];
        $expiredCount = 0;

        foreach ($tokenPool as $token => $timestamp) {
            if ($currentTime - $timestamp > self::TOKEN_EXPIRY) {
                unset($_SESSION[self::TOKEN_POOL_KEY][$token]);
                $expiredCount++;
            }
        }

        // 記錄清理活動（只在有過期代幣時）
        if ($expiredCount > 0) {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
                    status: ActivityStatus::SUCCESS,
                    targetType: 'csrf_token_cleanup',
                    targetId: session_id(),
                    description: '已清理過期的 CSRF 代幣',
                    metadata: [
                        'session_id' => session_id(),
                        'expired_tokens_count' => $expiredCount,
                    ],
                ),
            );
        }
    }

    /**
     * 限制權杖池大小.
     */
    private function limitPoolSize(): void
    {
        if (!isset($_SESSION[self::TOKEN_POOL_KEY])) {
            return;
        }

        $tokenPool = $_SESSION[self::TOKEN_POOL_KEY];
        $removedCount = 0;

        // 如果池大小超過限制，移除最舊的權杖
        while (count($tokenPool) > self::TOKEN_POOL_SIZE) {
            $oldestToken = array_key_first($tokenPool);
            unset($_SESSION[self::TOKEN_POOL_KEY][$oldestToken]);
            $tokenPool = $_SESSION[self::TOKEN_POOL_KEY];
            $removedCount++;
        }

        // 記錄池大小限制活動
        if ($removedCount > 0) {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
                    status: ActivityStatus::SUCCESS,
                    targetType: 'csrf_pool_limit',
                    targetId: session_id(),
                    description: 'CSRF 代幣池大小已限制',
                    metadata: [
                        'session_id' => session_id(),
                        'removed_tokens_count' => $removedCount,
                        'current_pool_size' => count($tokenPool),
                        'max_pool_size' => self::TOKEN_POOL_SIZE,
                    ],
                ),
            );
        }
    }

    /**
     * 檢查權杖是否有效（不會使用掉權杖）.
     */
    public function isTokenValid(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            // 檢查權杖池模式
            if (isset($_SESSION[self::TOKEN_POOL_KEY]) && is_array($_SESSION[self::TOKEN_POOL_KEY])) {
                $tokenPool = $_SESSION[self::TOKEN_POOL_KEY];

                foreach ($tokenPool as $poolToken => $timestamp) {
                    if (hash_equals($poolToken, $token)) {
                        return (time() - $timestamp) <= self::TOKEN_EXPIRY;
                    }
                }
            } else {
                // 降級到單一權杖模式
                if (isset($_SESSION['csrf_token']) && isset($_SESSION['csrf_token_time'])) {
                    return hash_equals($_SESSION['csrf_token'], $token)
                        && (time() - $_SESSION['csrf_token_time']) <= self::TOKEN_EXPIRY;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 預填權杖池.
     */
    public function initializeTokenPool(): void
    {
        $_SESSION[self::TOKEN_POOL_KEY] = [];

        // 產生初始權杖填滿池
        for ($i = 0; $i < self::TOKEN_POOL_SIZE; $i++) {
            $this->generateToken();
        }

        // 記錄代幣池初始化
        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
                status: ActivityStatus::SUCCESS,
                targetType: 'csrf_token_pool',
                targetId: session_id(),
                description: 'CSRF 代幣池已初始化',
                metadata: [
                    'session_id' => session_id(),
                    'initial_pool_size' => self::TOKEN_POOL_SIZE,
                ],
            ),
        );
    }

    /**
     * 取得權杖池狀態（用於除錯）.
     */
    public function getTokenPoolStatus(): array
    {
        if (!isset($_SESSION[self::TOKEN_POOL_KEY])) {
            return [
                'enabled' => false,
                'size' => 0,
                'tokens' => [],
            ];
        }

        $pool = $_SESSION[self::TOKEN_POOL_KEY];
        $currentTime = time();

        $tokens = [];
        foreach ($pool as $token => $timestamp) {
            $tokens[] = [
                'token' => substr($token, 0, 8) . '...', // 只顯示前8位
                'age' => $currentTime - $timestamp,
                'expires_in' => self::TOKEN_EXPIRY - ($currentTime - $timestamp),
                'expired' => ($currentTime - $timestamp) > self::TOKEN_EXPIRY,
            ];
        }

        return [
            'enabled' => true,
            'size' => count($pool),
            'max_size' => self::TOKEN_POOL_SIZE,
            'tokens' => $tokens,
        ];
    }

    /**
     * 記錄 CSRF 驗證失敗事件.
     */
    private function logCsrfValidationFailure(string $token, string $reason): void
    {
        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: ActivityType::CSRF_ATTACK_BLOCKED,
                status: ActivityStatus::BLOCKED,
                targetType: 'csrf_token',
                targetId: session_id(),
                description: "CSRF 代幣驗證失敗: {$reason}",
                metadata: [
                    'session_id' => session_id(),
                    'token_hash' => !empty($token) ? hash('sha256', $token) : '',
                    'failure_reason' => $reason,
                    'timestamp' => date('c'),
                ],
            ),
        );
    }
}
