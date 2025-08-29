<?php

declare(strict_types=1);

namespace App\Application\Services\Monitoring;

use App\Application\DTOs\Monitoring\AlertDTO;
use App\Domain\Common\ValueObjects\AlertSeverity;
use App\Shared\Contracts\CacheServiceInterface;
use DateTime;
use DateTimeInterface;
use Exception;

/**
 * 告警管理服務.
 *
 * 負責告警的傳送、管理、抑制和升級
 */
final class AlertManagerService
{
    /**
     * 活動告警快取鍵前綴.
     */
    private const ACTIVE_ALERTS_CACHE_KEY = 'monitoring.active_alerts';

    /**
     * 抑制規則快取鍵前綴.
     */
    private const SILENCE_RULES_CACHE_KEY = 'monitoring.silence_rules';

    /**
     * 告警歷史快取鍵前綴.
     */
    private const ALERT_HISTORY_CACHE_KEY = 'monitoring.alert_history';

    public function __construct(
        private CacheServiceInterface $cache,
    ) {}

    /**
     * 處理新告警.
     */
    public function handleAlert(AlertDTO $alert): array
    {
        $result = [
            'success' => true,
            'alert_id' => $alert->id,
            'actions_taken' => [],
            'notifications_sent' => [],
            'errors' => [],
        ];

        try {
            // 1. 檢查是否被抑制
            if ($this->isAlertSilenced($alert)) {
                $result['actions_taken'][] = 'alert_silenced';
                $this->logAlert($alert, 'silenced');

                return $result;
            }

            // 2. 檢查是否為重複告警
            $existingAlert = $this->findExistingAlert($alert);
            if ($existingAlert !== null) {
                $result['actions_taken'][] = 'alert_updated';
                $this->updateExistingAlert($existingAlert, $alert);
            } else {
                $result['actions_taken'][] = 'alert_created';
                $this->createNewAlert($alert);
            }

            // 3. 發送通知
            $notifications = $this->sendNotifications($alert);
            $result['notifications_sent'] = $notifications;

            // 4. 記錄告警歷史
            $this->logAlert($alert, 'triggered');

            // 5. 檢查是否需要升級
            if ($this->shouldEscalateAlert($alert)) {
                $escalated = $this->escalateAlert($alert);
                if ($escalated) {
                    $result['actions_taken'][] = 'alert_escalated';
                }
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
            error_log('Failed to handle alert: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * 確認告警.
     */
    public function acknowledgeAlert(string $alertId, string $acknowledgedBy): bool
    {
        try {
            $alert = $this->getActiveAlert($alertId);
            if ($alert === null) {
                return false;
            }

            $acknowledgedAlert = $alert->acknowledge($acknowledgedBy);
            $this->updateActiveAlert($acknowledgedAlert);
            $this->logAlert($acknowledgedAlert, 'acknowledged');

            return true;
        } catch (Exception $e) {
            error_log("Failed to acknowledge alert {$alertId}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * 解決告警.
     */
    public function resolveAlert(string $alertId): bool
    {
        try {
            $alert = $this->getActiveAlert($alertId);
            if ($alert === null) {
                return false;
            }

            $resolvedAlert = $alert->resolve();
            $this->removeActiveAlert($alertId);
            $this->logAlert($resolvedAlert, 'resolved');

            return true;
        } catch (Exception $e) {
            error_log("Failed to resolve alert {$alertId}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * 靜音告警.
     */
    public function silenceAlert(string $alertId, DateTimeInterface $until): bool
    {
        try {
            $alert = $this->getActiveAlert($alertId);
            if ($alert === null) {
                return false;
            }

            $silencedAlert = $alert->silence($until);
            $this->updateActiveAlert($silencedAlert);
            $this->logAlert($silencedAlert, 'silenced');

            return true;
        } catch (Exception $e) {
            error_log("Failed to silence alert {$alertId}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * 取得所有活動告警.
     */
    public function getActiveAlerts(?AlertSeverity $severity = null): array
    {
        $cacheKey = self::ACTIVE_ALERTS_CACHE_KEY;
        $alerts = $this->cache->get($cacheKey) ?? [];

        if ($severity !== null) {
            $alerts = array_filter(
                $alerts,
                fn($alertData) => AlertSeverity::from($alertData['severity']) === $severity,
            );
        }

        return array_map(fn($alertData) => AlertDTO::fromArray($alertData), $alerts);
    }

    /**
     * 取得告警統計.
     */
    public function getAlertStatistics(): array
    {
        $activeAlerts = $this->getActiveAlerts();

        $stats = [
            'total_active' => count($activeAlerts),
            'by_severity' => [
                'critical' => 0,
                'warning' => 0,
                'info' => 0,
                'debug' => 0,
            ],
            'by_status' => [
                'firing' => 0,
                'acknowledged' => 0,
                'silenced' => 0,
            ],
            'oldest_alert' => null,
            'newest_alert' => null,
        ];

        if (empty($activeAlerts)) {
            return $stats;
        }

        foreach ($activeAlerts as $alert) {
            // 按嚴重程度統計
            $stats['by_severity'][$alert->severity->value]++;

            // 按狀態統計
            $stats['by_status'][$alert->status]++;
        }

        // 尋找最舊和最新的告警
        usort(
            $activeAlerts,
            fn($a, $b) => $a->alertedAt->getTimestamp() <=> $b->alertedAt->getTimestamp(),
        );

        $stats['oldest_alert'] = $activeAlerts[0]->getSummary();
        $stats['newest_alert'] = end($activeAlerts)->getSummary();

        return $stats;
    }

    /**
     * 檢查告警是否被抑制.
     */
    private function isAlertSilenced(AlertDTO $alert): bool
    {
        // 檢查全域抑制規則
        $silenceRules = $this->cache->get(self::SILENCE_RULES_CACHE_KEY) ?? [];

        foreach ($silenceRules as $rule) {
            if ($this->matchesSilenceRule($alert, $rule)) {
                return true;
            }
        }

        // 檢查個別告警的靜音狀態
        return $alert->isSilenced();
    }

    /**
     * 檢查告警是否符合抑制規則.
     */
    private function matchesSilenceRule(AlertDTO $alert, array $rule): bool
    {
        // 檢查規則是否過期
        if (isset($rule['expires_at'])) {
            $expiresAt = new DateTime($rule['expires_at']);
            if ($expiresAt < new DateTime()) {
                return false;
            }
        }

        // 檢查嚴重程度
        if (isset($rule['severity']) && $rule['severity'] !== $alert->severity->value) {
            return false;
        }

        // 檢查指標
        if (isset($rule['metric']) && $rule['metric'] !== $alert->metric) {
            return false;
        }

        // 檢查標籤匹配
        if (isset($rule['labels'])) {
            foreach ($rule['labels'] as $key => $value) {
                if (!isset($alert->labels[$key]) || $alert->labels[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 尋找現有的相似告警.
     */
    private function findExistingAlert(AlertDTO $alert): ?AlertDTO
    {
        $activeAlerts = $this->getActiveAlerts();

        foreach ($activeAlerts as $existingAlert) {
            if (
                $existingAlert->ruleId === $alert->ruleId
                && $existingAlert->metric === $alert->metric
                && !$existingAlert->isResolved()
            ) {
                return $existingAlert;
            }
        }

        return null;
    }

    /**
     * 更新現有告警.
     */
    private function updateExistingAlert(AlertDTO $existingAlert, AlertDTO $newAlert): void
    {
        $updatedAlert = $existingAlert->withChanges([
            'currentValue' => $newAlert->currentValue,
            'message' => $newAlert->message,
            'updatedAt' => new DateTime()->format('Y-m-d H:i:s'),
        ]);

        $this->updateActiveAlert($updatedAlert);
    }

    /**
     * 建立新告警.
     */
    private function createNewAlert(AlertDTO $alert): void
    {
        $newAlert = $alert->withChanges([
            'id' => $this->generateAlertId(),
            'createdAt' => new DateTime()->format('Y-m-d H:i:s'),
        ]);

        $this->addActiveAlert($newAlert);
    }

    /**
     * 發送通知.
     */
    private function sendNotifications(AlertDTO $alert): array
    {
        $notifications = [];

        try {
            // 根據嚴重程度決定通知管道
            $channels = $this->getNotificationChannels($alert->severity);

            foreach ($channels as $channel) {
                $result = $this->sendNotificationToChannel($alert, $channel);
                $notifications[] = [
                    'channel' => $channel,
                    'success' => $result['success'],
                    'message' => $result['message'] ?? null,
                ];
            }
        } catch (Exception $e) {
            error_log('Failed to send notifications: ' . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * 取得通知管道.
     */
    private function getNotificationChannels(AlertSeverity $severity): array
    {
        return match ($severity) {
            AlertSeverity::CRITICAL => ['email', 'slack', 'sms'],
            AlertSeverity::WARNING => ['email', 'slack'],
            AlertSeverity::INFO => ['email'],
            AlertSeverity::DEBUG => []
        };
    }

    /**
     * 發送通知到特定管道.
     */
    private function sendNotificationToChannel(AlertDTO $alert, string $channel): array
    {
        // 實際實作時這裡會整合真實的通知服務
        // 目前返回模擬結果

        $message = $this->formatNotificationMessage($alert, $channel);

        switch ($channel) {
            case 'email':
                return $this->sendEmailNotification($alert, $message);
            case 'slack':
                return $this->sendSlackNotification($alert, $message);
            case 'sms':
                return $this->sendSmsNotification($alert, $message);
            default:
                return ['success' => false, 'message' => "Unknown channel: {$channel}"];
        }
    }

    /**
     * 格式化通知訊息.
     */
    private function formatNotificationMessage(AlertDTO $alert, string $channel): string
    {
        $template = match ($channel) {
            'email' => "🚨 告警通知\n\n標題：{title}\n描述：{description}\n嚴重程度：{severity}\n當前值：{current_value}\n閾值：{threshold}\n時間：{time}",
            'slack' => "🚨 *{severity}告警* - {title}\n> {description}\n> 當前值：{current_value} | 閾值：{threshold}\n> 時間：{time}",
            'sms' => '告警：{title} - {severity}，當前值：{current_value}，閾值：{threshold}',
            default => '{title}: {description}'
        };

        return strtr($template, [
            '{title}' => $alert->title,
            '{description}' => $alert->description,
            '{severity}' => $alert->severity->getDisplayName(),
            '{current_value}' => $alert->currentValue,
            '{threshold}' => $alert->threshold,
            '{time}' => $alert->alertedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 發送 Email 通知（模擬）.
     */
    private function sendEmailNotification(AlertDTO $alert, string $message): array
    {
        // 實際實作時這裡會使用真實的郵件服務
        return ['success' => true, 'message' => 'Email sent successfully'];
    }

    /**
     * 發送 Slack 通知（模擬）.
     */
    private function sendSlackNotification(AlertDTO $alert, string $message): array
    {
        // 實際實作時這裡會使用 Slack API
        return ['success' => true, 'message' => 'Slack message sent successfully'];
    }

    /**
     * 發送 SMS 通知（模擬）.
     */
    private function sendSmsNotification(AlertDTO $alert, string $message): array
    {
        // 實際實作時這裡會使用 SMS 服務
        return ['success' => true, 'message' => 'SMS sent successfully'];
    }

    /**
     * 檢查是否需要升級告警.
     */
    private function shouldEscalateAlert(AlertDTO $alert): bool
    {
        // 只有危險告警且超過 30 分鐘未確認才升級
        if ($alert->severity !== AlertSeverity::CRITICAL) {
            return false;
        }

        if ($alert->isAcknowledged()) {
            return false;
        }

        $thirtyMinutesAgo = new DateTime()->modify('-30 minutes');

        return $alert->alertedAt < $thirtyMinutesAgo;
    }

    /**
     * 升級告警.
     */
    private function escalateAlert(AlertDTO $alert): bool
    {
        try {
            // 發送升級通知
            $escalationMessage = "🔥 告警升級通知\n\n"
                . "告警 {$alert->title} 已超過 30 分鐘未處理，現在升級為高優先級告警。\n"
                . '請立即處理。';

            // 發送給更高層級的通知管道
            $this->sendNotificationToChannel($alert, 'sms');

            // 記錄升級事件
            $this->logAlert($alert, 'escalated');

            return true;
        } catch (Exception $e) {
            error_log("Failed to escalate alert {$alert->id}: " . $e->getMessage());

            return false;
        }
    }

    /**
     * 記錄告警事件.
     */
    private function logAlert(AlertDTO $alert, string $action): void
    {
        $logEntry = [
            'alert_id' => $alert->id,
            'rule_id' => $alert->ruleId,
            'action' => $action,
            'severity' => $alert->severity->value,
            'metric' => $alert->metric,
            'current_value' => $alert->currentValue,
            'threshold' => $alert->threshold,
            'timestamp' => new DateTime()->format('Y-m-d H:i:s'),
        ];

        $historyKey = self::ALERT_HISTORY_CACHE_KEY . '.' . date('Y-m-d');
        $history = $this->cache->get($historyKey) ?? [];
        $history[] = $logEntry;

        // 只保留最新的 1000 條記錄
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }

        $this->cache->set($historyKey, $history, 86400); // 24 小時
    }

    /**
     * 取得特定活動告警.
     */
    private function getActiveAlert(string $alertId): ?AlertDTO
    {
        $activeAlerts = $this->getActiveAlerts();

        foreach ($activeAlerts as $alert) {
            if ($alert->id === $alertId) {
                return $alert;
            }
        }

        return null;
    }

    /**
     * 更新活動告警.
     */
    private function updateActiveAlert(AlertDTO $alert): void
    {
        $cacheKey = self::ACTIVE_ALERTS_CACHE_KEY;
        $alerts = $this->cache->get($cacheKey) ?? [];

        foreach ($alerts as &$alertData) {
            if ($alertData['id'] === $alert->id) {
                $alertData = $alert->toArray();
                break;
            }
        }

        $this->cache->set($cacheKey, $alerts, 3600); // 1 小時
    }

    /**
     * 添加活動告警.
     */
    private function addActiveAlert(AlertDTO $alert): void
    {
        $cacheKey = self::ACTIVE_ALERTS_CACHE_KEY;
        $alerts = $this->cache->get($cacheKey) ?? [];
        $alerts[] = $alert->toArray();

        $this->cache->set($cacheKey, $alerts, 3600); // 1 小時
    }

    /**
     * 移除活動告警.
     */
    private function removeActiveAlert(string $alertId): void
    {
        $cacheKey = self::ACTIVE_ALERTS_CACHE_KEY;
        $alerts = $this->cache->get($cacheKey) ?? [];

        $alerts = array_filter($alerts, fn($alertData) => $alertData['id'] !== $alertId);

        $this->cache->set($cacheKey, array_values($alerts), 3600); // 1 小時
    }

    /**
     * 生成告警 ID.
     */
    private function generateAlertId(): string
    {
        return 'alert_' . uniqid() . '_' . time();
    }

    /**
     * 取得告警歷史.
     */
    public function getAlertHistory(?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $historyKey = self::ALERT_HISTORY_CACHE_KEY . '.' . $date;

        return $this->cache->get($historyKey) ?? [];
    }

    /**
     * 清理過期的告警資料.
     */
    public function cleanupExpiredAlerts(): int
    {
        $cleaned = 0;

        // 清理過期的歷史資料（超過 30 天）
        $thirtyDaysAgo = new DateTime()->modify('-30 days');

        for ($i = 0; $i < 35; $i++) {
            $checkDate = (clone $thirtyDaysAgo)->modify("+{$i} days")->format('Y-m-d');
            $historyKey = self::ALERT_HISTORY_CACHE_KEY . '.' . $checkDate;

            if ($this->cache->delete($historyKey)) {
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
