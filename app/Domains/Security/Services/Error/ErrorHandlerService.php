<?php

declare(strict_types=1);

namespace App\Domains\Security\Services\Error;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\ErrorHandlerServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use ErrorException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Throwable;

class ErrorHandlerService implements ErrorHandlerServiceInterface
{
    private Logger $logger;

    private bool $isDevelopment;

    private array $sensitiveKeys;

    public function __construct(
        private ?ActivityLoggingServiceInterface $activityLogger,
        string $logPath = '',
        bool $isDevelopment = false,
        array $sensitiveKeys = [],
    ) {
        $this->isDevelopment = $isDevelopment;
        $this->sensitiveKeys = array_merge([
            'password',
            'passwd',
            'secret',
            'token',
            'key',
            'auth',
            'session',
            'cookie',
            'csrf',
            'api_key',
            'private',
            'salt',
            'hash',
            'signature',
            'authorization',
        ], $sensitiveKeys);

        $this->initializeLogger($logPath ?: __DIR__ . '/../../../storage/logs');
        $this->registerErrorHandlers();
    }

    public function handleException(Throwable $e, bool $isPublicError = false): array
    {
        // 記錄完整錯誤到日誌
        $this->logException($e);

        // 返回用戶友善的錯誤訊息
        if ($this->isDevelopment && !$isPublicError) {
            return [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'type' => get_class($e),
            ];
        }

        return [
            'error' => $this->getPublicErrorMessage($e),
            'code' => $this->getErrorCode($e),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function logSecurityEvent(string $event, array $context = []): void
    {
        $sanitizedContext = $this->sanitizeLogData($context);

        // 記錄到 Monolog
        $this->logger->warning('Security Event: ' . $event, array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'timestamp' => time(),
        ], $sanitizedContext));

        // 記錄到活動記錄系統
        if ($this->activityLogger) {
            try {
                $dto = CreateActivityLogDTO::securityEvent(
                    actionType: ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
                    description: "安全事件: {$event}",
                    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
                    metadata: [
                        'security_event_type' => $event,
                        'context' => $sanitizedContext,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    ],
                );

                $this->activityLogger->log($dto);
            } catch (Throwable $e) {
                $this->logger->error('Failed to log security event to activity logger', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function logAuthenticationAttempt(bool $success, string $username, array $context = []): void
    {
        $event = $success ? 'Authentication Success' : 'Authentication Failed';

        $this->logSecurityEvent($event, array_merge([
            'username' => $username,
            'success' => $success,
        ], $context));

        // 額外記錄認證嘗試到活動記錄系統
        if ($this->activityLogger) {
            try {
                $activityType = $success ? ActivityType::LOGIN_SUCCESS : ActivityType::LOGIN_FAILED;

                $dto = CreateActivityLogDTO::securityEvent(
                    actionType: $activityType,
                    description: $success ? "使用者 {$username} 登入成功" : "使用者 {$username} 登入失敗",
                    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
                    metadata: [
                        'username' => $username,
                        'authentication_success' => $success,
                        'context' => $this->sanitizeLogData($context),
                    ],
                );

                $this->activityLogger->log($dto);
            } catch (Throwable $e) {
                $this->logger->error('Failed to log authentication attempt to activity logger', [
                    'username' => $username,
                    'success' => $success,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function logSuspiciousActivity(string $activity, array $context = []): void
    {
        $this->logger->error('Suspicious Activity: ' . $activity, array_merge([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'timestamp' => time(),
        ], $this->sanitizeLogData($context)));
    }

    public function sanitizeLogData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } elseif (is_string($value)) {
                // 檢查是否可能是敏感資料
                if ($this->containsSensitiveData($value)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->truncateString($value, 1000);
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function initializeLogger(string $logPath): void
    {
        $this->logger = new Logger('security');

        // 確保日誌目錄存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0o750, true);
        }

        // 設定檔案權限
        if (is_dir($logPath)) {
            chmod($logPath, 0o750);
        }

        // 一般日誌 (INFO 級別以上)
        $infoHandler = new RotatingFileHandler(
            $logPath . '/app.log',
            0, // 保留所有檔案
            Logger::INFO,
        );

        // 錯誤日誌 (ERROR 級別以上)
        $errorHandler = new RotatingFileHandler(
            $logPath . '/error.log',
            0,
            Logger::ERROR,
        );

        // 安全日誌 (WARNING 級別以上)
        $securityHandler = new RotatingFileHandler(
            $logPath . '/security.log',
            0,
            Logger::WARNING,
        );

        // 設定格式化器
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true,
        );

        $infoHandler->setFormatter($formatter);
        $errorHandler->setFormatter($formatter);
        $securityHandler->setFormatter($formatter);

        // 添加處理器
        $this->logger->pushHandler($infoHandler);
        $this->logger->pushHandler($errorHandler);
        $this->logger->pushHandler($securityHandler);

        // 添加額外資訊處理器
        $this->logger->pushProcessor(new IntrospectionProcessor());
        $this->logger->pushProcessor(new WebProcessor());
    }

    private function registerErrorHandlers(): void
    {
        // 設定全域例外處理器
        set_exception_handler([$this, 'globalExceptionHandler']);

        // 設定錯誤處理器
        set_error_handler([$this, 'globalErrorHandler']);

        // 設定致命錯誤處理器
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    public function globalExceptionHandler(Throwable $e): void
    {
        $errorData = $this->handleException($e, true);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }

        echo json_encode($errorData) ?? '';
        exit;
    }

    public function globalErrorHandler(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $exception = new ErrorException($message, 0, $severity, $file, $line);
        $this->logException($exception);

        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            $this->globalExceptionHandler($exception);
        }

        return true;
    }

    public function shutdownHandler(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line'],
            );

            $this->globalExceptionHandler($exception);
        }
    }

    private function logException(Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'previous' => $e->getPrevious() ? get_class($e->getPrevious()) : null,
        ];

        $this->logger->error($e->getMessage(), $this->sanitizeLogData($context));
    }

    private function getPublicErrorMessage(Throwable $e): string
    {
        return match (get_class($e)) {
            'App\\Exceptions\\ValidationException' => $e->getMessage(),
            'App\\Exceptions\\NotFoundException' => '請求的資源不存在',
            'App\\Exceptions\\CsrfTokenException' => '安全驗證失敗，請重新載入頁面',
            'App\\Exceptions\\StateTransitionException' => '操作失敗，請稍後再試',
            default => '系統暫時無法處理您的請求，請稍後再試'
        };
    }

    private function getErrorCode(Throwable $e): string
    {
        return match (get_class($e)) {
            'App\\Exceptions\\ValidationException' => 'VALIDATION_ERROR',
            'App\\Exceptions\\NotFoundException' => 'NOT_FOUND',
            'App\\Exceptions\\CsrfTokenException' => 'CSRF_ERROR',
            'App\\Exceptions\\StateTransitionException' => 'STATE_ERROR',
            default => 'INTERNAL_ERROR'
        };
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($key, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    private function containsSensitiveData(string $value): bool
    {
        // 檢查是否像是密碼哈希、Token 等
        if (strlen($value) > 20 && ctype_alnum(str_replace(['/', '+', '='], '', $value))) {
            return true;
        }

        // 檢查是否像是信用卡號
        if (preg_match('/^\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}$/', $value)) {
            return true;
        }

        // 檢查是否像是電子郵件（部分遮罩）
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return false; // 電子郵件可以記錄，但可能需要部分遮罩
        }

        return false;
    }

    private function truncateString(string $string, int $maxLength = 1000): string
    {
        if (strlen($string) > $maxLength) {
            return substr($string, 0, $maxLength) . '... [truncated]';
        }

        return $string;
    }
}
