<?php

declare(strict_types=1);

namespace App\Domains\Security\Services\Core;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityStatus;
use App\Domains\Security\Enums\ActivityType;
use HTMLPurifier;
use HTMLPurifier_Config;

class XssProtectionService
{
    private HTMLPurifier $purifier;

    private HTMLPurifier $strictPurifier;

    public function __construct(
        private ActivityLoggingServiceInterface $activityLogger,
    ) {
        $this->initializePurifiers();
    }

    /**
     * 初始化 HTML Purifier 設定.
     */
    private function initializePurifiers(): void
    {
        // 基本設定 - 允許一些安全的 HTML 標籤
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', 'p,b,strong,i,em,u,br,ul,ol,li,a[href],blockquote,h3,h4,h5,h6');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('Cache.SerializerPath', $this->getCachePath()); // 使用方法取得路徑

        $this->purifier = new HTMLPurifier($config);

        // 嚴格設定 - 不允許任何 HTML 標籤
        $strictConfig = HTMLPurifier_Config::createDefault();
        $strictConfig->set('HTML.Allowed', '');
        $strictConfig->set('Cache.SerializerPath', $this->getCachePath()); // 使用方法取得路徑

        $this->strictPurifier = new HTMLPurifier($strictConfig);
    }

    /**
     * 清理輸入 - 基本防護，適用於簡單文字.
     */
    public function clean(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // 檢查是否有變化（可能的XSS攻擊）
        if ($input !== $cleaned) {
            $this->logXssAttempt($input, $cleaned, 'basic_clean');
        }

        return $cleaned;
    }

    /**
     * 清理 HTML 內容 - 允許安全的 HTML 標籤.
     */
    public function cleanHtml(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = $this->purifier->purify($input);

        // 檢查是否有變化（可能的XSS攻擊）
        if ($input !== $cleaned) {
            $this->logXssAttempt($input, $cleaned, 'html_clean');
        }

        return $cleaned;
    }

    /**
     * 嚴格清理 - 移除所有 HTML 標籤.
     */
    public function cleanStrict(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = $this->strictPurifier->purify($input);

        // 檢查是否有變化（可能的XSS攻擊）
        if ($input !== $cleaned) {
            $this->logXssAttempt($input, $cleaned, 'strict_clean');
        }

        return $cleaned;
    }

    /**
     * 清理陣列中的指定欄位.
     */
    public function cleanArray(array $input, array $keys): array
    {
        $hasChanges = false;
        $cleanedKeys = [];

        foreach ($keys as $key) {
            if (isset($input[$key])) {
                $original = $input[$key];
                $input[$key] = $this->clean($input[$key]);

                if ($original !== $input[$key]) {
                    $hasChanges = true;
                    $cleanedKeys[] = $key;
                }
            }
        }

        if ($hasChanges) {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::XSS_ATTACK_BLOCKED,
                    status: ActivityStatus::BLOCKED,
                    targetType: 'input_array',
                    targetId: implode(',', $cleanedKeys),
                    description: '陣列欄位 XSS 攻擊已阻擋',
                    metadata: [
                        'cleaned_fields' => $cleanedKeys,
                        'field_count' => count($cleanedKeys),
                        'cleaning_method' => 'array_clean',
                    ],
                ),
            );
        }

        return $input;
    }

    /**
     * 清理陣列中的 HTML 欄位.
     */
    public function cleanHtmlArray(array $input, array $keys): array
    {
        $hasChanges = false;
        $cleanedKeys = [];

        foreach ($keys as $key) {
            if (isset($input[$key])) {
                $original = $input[$key];
                $input[$key] = $this->cleanHtml($input[$key]);

                if ($original !== $input[$key]) {
                    $hasChanges = true;
                    $cleanedKeys[] = $key;
                }
            }
        }

        if ($hasChanges) {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::XSS_ATTACK_BLOCKED,
                    status: ActivityStatus::BLOCKED,
                    targetType: 'html_array',
                    targetId: implode(',', $cleanedKeys),
                    description: 'HTML 陣列欄位 XSS 攻擊已阻擋',
                    metadata: [
                        'cleaned_fields' => $cleanedKeys,
                        'field_count' => count($cleanedKeys),
                        'cleaning_method' => 'html_array_clean',
                    ],
                ),
            );
        }

        return $input;
    }

    /**
     * 為 JavaScript 輸出清理字串，回傳一個 JSON 編碼的字串.
     */
    public function cleanForJs(?string $input): string
    {
        if ($input === null) {
            return 'null';
        }

        // 使用 json_encode 是最安全、最標準的方式來將字串傳遞給 JavaScript
        // 它會處理所有引號、反斜線和控制字元
        // JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT 提供了額外的保護層，防止 XSS
        $cleaned = (json_encode($input, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?? '') ?: '';

        // 檢查是否包含可疑內容
        if ($this->containsSuspiciousJsContent($input)) {
            $this->logXssAttempt($input, $cleaned, 'js_clean');
        }

        return $cleaned;
    }

    /**
     * 為 URL 參數清理字串.
     */
    public function cleanForUrl(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = urlencode($input);

        // 檢查是否有變化
        if ($input !== urldecode($cleaned)) {
            $this->logXssAttempt($input, $cleaned, 'url_clean');
        }

        return $cleaned;
    }

    /**
     * 檢查字串是否包含可疑的 XSS 模式.
     *
     * @deprecated 1.0.0 此方法為弱檢測，容易被繞過。請優先使用 cleanHtml() 進行過濾和淨化。
     */
    public function detectXss(string $input): array
    {
        $suspiciousPatterns = [
            'javascript:' => 'JavaScript URL scheme',
            'vbscript:' => 'VBScript URL scheme',
            'data:' => 'Data URL scheme',
            '<script' => 'Script tag',
            '</script>' => 'Script tag',
            'onload=' => 'Event handler',
            'onerror=' => 'Event handler',
            'onclick=' => 'Event handler',
            'onmouseover=' => 'Event handler',
            'eval(' => 'JavaScript eval',
            'expression(' => 'CSS expression',
            'url(' => 'CSS URL function',
            '&#' => 'HTML entity encoding',
            '%3c' => 'URL encoded angle bracket',
            '%3e' => 'URL encoded angle bracket',
            'alert(' => 'JavaScript alert',
            'confirm(' => 'JavaScript confirm',
            'prompt(' => 'JavaScript prompt',
        ];

        $detected = [];
        $lowerInput = strtolower($input);

        foreach ($suspiciousPatterns as $pattern => $description) {
            if (str_contains($lowerInput, $pattern)) {
                $detected[] = [
                    'pattern' => $pattern,
                    'description' => $description,
                    'risk_level' => $this->getRiskLevel($pattern),
                ];
            }
        }

        // 記錄檢測到的XSS嘗試
        if (!empty($detected)) {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::XSS_ATTACK_BLOCKED,
                    status: ActivityStatus::BLOCKED,
                    targetType: 'xss_detection',
                    targetId: hash('sha256', $input),
                    description: 'XSS 攻擊模式檢測',
                    metadata: [
                        'detected_patterns' => array_column($detected, 'pattern'),
                        'pattern_count' => count($detected),
                        'highest_risk' => $this->getHighestRiskLevel($detected),
                        'input_length' => strlen($input),
                    ],
                ),
            );
        }

        return $detected;
    }

    /**
     * 取得風險等級.
     */
    private function getRiskLevel(string $pattern): string
    {
        $highRisk = ['<script', 'javascript:', 'eval(', 'expression('];
        $mediumRisk = ['onload=', 'onerror=', 'onclick=', 'alert('];

        if (in_array($pattern, $highRisk, true)) {
            return 'high';
        }

        if (in_array($pattern, $mediumRisk, true)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 取得最高風險等級.
     */
    private function getHighestRiskLevel(array $detected): string
    {
        $risks = array_column($detected, 'risk_level');

        if (in_array('high', $risks)) {
            return 'high';
        } elseif (in_array('medium', $risks)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 檢查 JS 內容是否包含可疑內容.
     */
    private function containsSuspiciousJsContent(string $input): bool
    {
        $suspiciousPatterns = ['<script', 'javascript:', 'eval(', 'alert(', 'document.', 'window.'];
        $lowerInput = strtolower($input);

        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($lowerInput, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 記錄 XSS 攻擊嘗試.
     */
    private function logXssAttempt(string $original, string $cleaned, string $method): void
    {
        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: ActivityType::XSS_ATTACK_BLOCKED,
                status: ActivityStatus::BLOCKED,
                targetType: 'input_cleaning',
                targetId: hash('sha256', $original),
                description: "XSS 攻擊已阻擋 - {$method}",
                metadata: [
                    'cleaning_method' => $method,
                    'original_length' => strlen($original),
                    'cleaned_length' => strlen($cleaned),
                    'bytes_removed' => strlen($original) - strlen($cleaned),
                    'original_hash' => hash('sha256', $original),
                    'timestamp' => date('c'),
                ],
            ),
        );
    }

    /**
     * 取得允許的 HTML 標籤清單.
     */
    public function getAllowedHtmlTags(): array
    {
        $definition = $this->purifier->config->getHTMLDefinition();

        return array_keys($definition->info);
    }

    /**
     * 取得並建立安全的快取路徑.
     */
    private function getCachePath(): string
    {
        // 允許透過環境變數設定，並提供一個合理的預設值
        $cachePath = $_ENV['HTMLPURIFIER_CACHE_PATH'] ?? __DIR__ . '/../../../storage/cache/htmlpurifier';

        if (!is_dir($cachePath)) {
            // @ 符號抑制錯誤，以處理多執行緒環境下的競爭條件
            // 權限設為 0750，只有擁有者和同群組使用者可以存取
            @mkdir($cachePath, 0o750, true);
        }

        return $cachePath;
    }
}
