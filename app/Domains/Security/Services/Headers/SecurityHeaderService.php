<?php

declare(strict_types=1);

namespace App\Domains\Security\Services\Headers;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\SecurityHeaderServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class SecurityHeaderService implements SecurityHeaderServiceInterface
{
    private array $config;

    private ?string $currentNonce = null;

    public function __construct(
        private ActivityLoggingServiceInterface $activityLogger,
        private LoggerInterface $logger,
        array $config = [],
    ) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function setSecurityHeaders(): void
    {
        // Content Security Policy
        if ($this->config['csp']['enabled']) {
            header('Content-Security-Policy: ' . $this->buildCSP());
        }

        // Strict Transport Security (僅在 HTTPS 下啟用)
        if ($this->config['hsts']['enabled'] && $this->isHTTPS()) {
            $hstsValue = sprintf(
                'max-age=%d%s%s',
                $this->config['hsts']['max_age'],
                $this->config['hsts']['include_subdomains'] ? '; includeSubDomains' : '',
                $this->config['hsts']['preload'] ? '; preload' : '',
            );
            header('Strict-Transport-Security: ' . $hstsValue);
        }

        // X-Frame-Options
        if ($this->config['frame_options']['enabled']) {
            header('X-Frame-Options: ' . $this->config['frame_options']['value']);
        }

        // X-Content-Type-Options
        if ($this->config['content_type_options']['enabled']) {
            header('X-Content-Type-Options: nosniff');
        }

        // X-XSS-Protection (雖然現代瀏覽器已棄用，但為了向後相容)
        if ($this->config['xss_protection']['enabled']) {
            header('X-XSS-Protection: 1; mode=block');
        }

        // Referrer Policy
        if ($this->config['referrer_policy']['enabled']) {
            header('Referrer-Policy: ' . $this->config['referrer_policy']['value']);
        }

        // Permissions Policy
        if ($this->config['permissions_policy']['enabled']) {
            header('Permissions-Policy: ' . $this->buildPermissionsPolicy());
        }

        // Cross-Origin Embedder Policy
        if ($this->config['coep']['enabled']) {
            header('Cross-Origin-Embedder-Policy: ' . $this->config['coep']['value']);
        }

        // Cross-Origin Opener Policy
        if ($this->config['coop']['enabled']) {
            header('Cross-Origin-Opener-Policy: ' . $this->config['coop']['value']);
        }

        // Cross-Origin Resource Policy
        if ($this->config['corp']['enabled']) {
            header('Cross-Origin-Resource-Policy: ' . $this->config['corp']['value']);
        }

        // Cache Control for sensitive pages
        if ($this->config['cache_control']['enabled']) {
            header('Cache-Control: ' . $this->config['cache_control']['value']);
        }
    }

    /**
     * 產生 CSP nonce 值
     */
    public function generateNonce(): string
    {
        if ($this->currentNonce === null) {
            $this->currentNonce = base64_encode(random_bytes(16));
        }

        return $this->currentNonce;
    }

    /**
     * 取得當前的 nonce 值
     */
    public function getCurrentNonce(): ?string
    {
        return $this->currentNonce;
    }

    /**
     * 建立 CSP 違規報告端點.
     */
    public function handleCSPReport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);

            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (
            strpos($contentType, 'application/csp-report') === false
            && strpos($contentType, 'application/json') === false
        ) {
            http_response_code(400);

            return;
        }

        $input = file_get_contents('php://input');
        $report = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);

            return;
        }

        // 記錄 CSP 違規
        $this->logCSPViolation($report);

        http_response_code(204);
    }

    /**
     * 記錄 CSP 違規.
     */
    private function logCSPViolation(array $report): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'report' => $report,
        ];

        // 記錄 CSP 違規活動
        try {
            $dto = CreateActivityLogDTO::securityEvent(
                actionType: ActivityType::CSP_VIOLATION,
                description: 'Content Security Policy 違規檢測',
                ipAddress: $logData['ip'],
                userAgent: $logData['user_agent'],
                metadata: [
                    'csp_report' => $report,
                    'violated_directive' => $report['csp-report']['violated-directive'] ?? 'unknown',
                    'blocked_uri' => $report['csp-report']['blocked-uri'] ?? 'unknown',
                    'document_uri' => $report['csp-report']['document-uri'] ?? 'unknown',
                    'effective_directive' => $report['csp-report']['effective-directive'] ?? 'unknown',
                    'original_policy' => $report['csp-report']['original-policy'] ?? 'unknown',
                ],
            );

            $this->activityLogger->log($dto);

            $this->logger->warning('CSP violation detected', [
                'ip_address' => $logData['ip'],
                'violated_directive' => $report['csp-report']['violated-directive'] ?? 'unknown',
                'blocked_uri' => $report['csp-report']['blocked-uri'] ?? 'unknown',
                'user_agent' => $logData['user_agent'],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to log CSP violation activity', [
                'error' => $e->getMessage(),
                'report' => $report,
            ]);
        }

        // 記錄到日誌檔案
        error_log('CSP Violation: ' . (json_encode($logData) ?? ''));

        // 如果設定了監控服務，也可以發送到那裡
        if (isset($this->config['csp']['monitoring_endpoint'])) {
            $this->sendToMonitoring($logData);
        }
    }

    /**
     * 發送到監控服務.
     */
    private function sendToMonitoring(array $data): void
    {
        // 這裡可以整合 Sentry、DataDog 等監控服務
        // 目前僅作為範例實作
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => (json_encode($data) ?? ''),
                    'timeout' => 5,
                ],
            ]);

            file_get_contents($this->config['csp']['monitoring_endpoint'], false, $context);
        } catch (Exception $e) {
            error_log('Failed to send CSP violation to monitoring: ' . $e->getMessage());
        }
    }

    public function removeServerSignature(): void
    {
        // 移除可能洩漏伺服器資訊的標頭
        header_remove('Server');
        header_remove('X-Powered-By');

        // 設定通用的伺服器標識（可選）
        if ($this->config['server_signature']['enabled']) {
            header('Server: ' . $this->config['server_signature']['value']);
        }
    }

    private function buildCSP(): string
    {
        $directives = [];
        $nonce = $this->generateNonce();

        foreach ($this->config['csp']['directives'] as $directive => $sources) {
            if (!empty($sources)) {
                // 對於 script-src 和 style-src，添加 nonce 支援
                if (($directive === 'script-src' || $directive === 'style-src') && $nonce) {
                    // 移除 unsafe-inline 並添加 nonce
                    $sources = array_diff($sources, ["'unsafe-inline'"]);
                    $sources[] = "'nonce-{$nonce}'";
                }

                $directives[] = $directive . ' ' . implode(' ', $sources);
            } else {
                $directives[] = $directive;
            }
        }

        // 添加 CSP 違規報告
        if (isset($this->config['csp']['report_uri'])) {
            $directives[] = 'report-uri ' . $this->config['csp']['report_uri'];
        }

        return implode('; ', $directives);
    }

    private function buildPermissionsPolicy(): string
    {
        $policies = [];

        foreach ($this->config['permissions_policy']['directives'] as $feature => $allowlist) {
            if (is_array($allowlist)) {
                $policies[] = $feature . '=(' . implode(' ', $allowlist) . ')';
            } else {
                $policies[] = $feature . '=' . $allowlist;
            }
        }

        return implode(', ', $policies);
    }

    private function isHTTPS(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    private function getDefaultConfig(): array
    {
        return [
            'csp' => [
                'enabled' => true,
                'report_uri' => '/api/csp-report', // CSP 違規報告端點
                'monitoring_endpoint' => null, // 可設定外部監控服務端點
                'directives' => [
                    'default-src' => ["'self'"],
                    'script-src' => ["'self'"], // 移除 unsafe-inline，使用 nonce 策略
                    'style-src' => ["'self'"], // 移除 unsafe-inline，使用 nonce 策略
                    'img-src' => ["'self'", 'data:', 'https:'],
                    'font-src' => ["'self'"],
                    'connect-src' => ["'self'"],
                    'media-src' => ["'self'"],
                    'object-src' => ["'none'"],
                    'child-src' => ["'self'"],
                    'frame-ancestors' => ["'none'"],
                    'form-action' => ["'self'"],
                    'base-uri' => ["'self'"],
                    'upgrade-insecure-requests' => [],
                ],
            ],
            'hsts' => [
                'enabled' => true,
                'max_age' => 31536000, // 1 year
                'include_subdomains' => true,
                'preload' => false,
            ],
            'frame_options' => [
                'enabled' => true,
                'value' => 'DENY',
            ],
            'content_type_options' => [
                'enabled' => true,
            ],
            'xss_protection' => [
                'enabled' => true,
            ],
            'referrer_policy' => [
                'enabled' => true,
                'value' => 'strict-origin-when-cross-origin',
            ],
            'permissions_policy' => [
                'enabled' => true,
                'directives' => [
                    'geolocation' => '()',
                    'microphone' => '()',
                    'camera' => '()',
                    'magnetometer' => '()',
                    'gyroscope' => '()',
                    'fullscreen' => '(self)',
                    'payment' => '()',
                ],
            ],
            'coep' => [
                'enabled' => false,
                'value' => 'require-corp',
            ],
            'coop' => [
                'enabled' => true,
                'value' => 'same-origin',
            ],
            'corp' => [
                'enabled' => true,
                'value' => 'same-origin',
            ],
            'cache_control' => [
                'enabled' => true,
                'value' => 'no-cache, no-store, must-revalidate',
            ],
            'server_signature' => [
                'enabled' => false,
                'value' => 'AlleyNote/1.0',
            ],
        ];
    }
}
