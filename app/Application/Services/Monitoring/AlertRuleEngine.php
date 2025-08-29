<?php

declare(strict_types=1);

namespace App\Application\Services\Monitoring;

use App\Application\DTOs\Monitoring\AlertRuleDTO;
use App\Application\DTOs\Monitoring\AlertDTO;
use App\Domain\Common\ValueObjects\AlertSeverity;

/**
 * 告警規則引擎服務
 * 
 * 負責管理和評估告警規則，生成告警
 */
final class AlertRuleEngine
{
    /**
     * 預設告警規則
     */
    private array $defaultRules = [];

    /**
     * 自訂告警規則
     */
    private array $customRules = [];

    public function __construct()
    {
        $this->defaultRules = $this->loadDefaultRules();
    }

    /**
     * 評估指標數據並生成告警
     */
    public function evaluateMetrics(array $metrics): array
    {
        $alerts = [];
        $allRules = array_merge($this->defaultRules, $this->customRules);

        foreach ($allRules as $rule) {
            if (!$rule->enabled) {
                continue;
            }

            try {
                $value = $this->extractMetricValue($metrics, $rule->metricPath);
                
                if ($value !== null && $rule->evaluateMetric($value)) {
                    $alerts[] = $this->createAlert($rule, $value);
                }
            } catch (\Exception $e) {
                // 記錄錯誤但不中斷處理其他規則
                error_log("Error evaluating rule {$rule->name}: " . $e->getMessage());
            }
        }

        return $alerts;
    }

    /**
     * 添加自訂規則
     */
    public function addCustomRule(AlertRuleDTO $rule): void
    {
        $this->customRules[] = $rule;
    }

    /**
     * 移除自訂規則
     */
    public function removeCustomRule(string $ruleId): bool
    {
        foreach ($this->customRules as $index => $rule) {
            if ($rule->id === $ruleId) {
                unset($this->customRules[$index]);
                return true;
            }
        }
        return false;
    }

    /**
     * 取得所有規則
     */
    public function getAllRules(): array
    {
        return array_merge($this->defaultRules, $this->customRules);
    }

    /**
     * 取得已啟用的規則
     */
    public function getEnabledRules(): array
    {
        return array_filter($this->getAllRules(), fn($rule) => $rule->enabled);
    }

    /**
     * 根據嚴重程度取得規則
     */
    public function getRulesBySeverity(AlertSeverity $severity): array
    {
        return array_filter($this->getAllRules(), fn($rule) => $rule->severity === $severity);
    }

    /**
     * 從指標數據中提取特定值
     */
    private function extractMetricValue(array $metrics, string $path): ?float
    {
        $pathParts = explode('.', $path);
        $current = $metrics;

        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        return is_numeric($current) ? (float) $current : null;
    }

    /**
     * 建立告警
     */
    private function createAlert(AlertRuleDTO $rule, float $value): AlertDTO
    {
        $message = $this->generateAlertMessage($rule, $value);
        
        return new AlertDTO(
            ruleId: $rule->id ?? 'unknown',
            ruleName: $rule->name,
            title: $rule->name,
            description: $rule->description,
            message: $message,
            severity: $rule->severity,
            status: 'firing',
            metric: $rule->metric,
            currentValue: $value,
            threshold: $rule->threshold,
            operator: $rule->operator,
            labels: [
                'rule_id' => $rule->id ?? 'unknown',
                'metric_path' => $rule->metricPath,
                'tags' => implode(',', $rule->tags)
            ]
        );
    }

    /**
     * 生成告警訊息
     */
    private function generateAlertMessage(AlertRuleDTO $rule, float $value): string
    {
        $operatorName = match ($rule->operator) {
            'gt' => '大於',
            'gte' => '大於等於',
            'lt' => '小於',
            'lte' => '小於等於',
            'eq' => '等於',
            'ne' => '不等於',
            default => $rule->operator
        };

        return sprintf(
            '指標 %s 的當前值 %.2f %s 閾值 %.2f，觸發 %s 告警',
            $rule->metric,
            $value,
            $operatorName,
            $rule->threshold,
            $rule->severity->getDisplayName()
        );
    }

    /**
     * 載入預設規則
     */
    private function loadDefaultRules(): array
    {
        return [
            // 記憶體使用率告警
            AlertRuleDTO::fromArray([
                'id' => 'memory_usage_critical',
                'name' => '記憶體使用率危險告警',
                'description' => '記憶體使用率超過 90% 時觸發危險告警',
                'metric' => 'system.memory.usage.percent',
                'metricPath' => 'system.memory.usage_percentage',
                'operator' => 'gt',
                'threshold' => 90.0,
                'severity' => 'critical',
                'evaluationWindow' => 300,
                'triggerCount' => 2,
                'enabled' => true,
                'tags' => ['system', 'memory', 'critical'],
                'notificationChannels' => ['email', 'slack']
            ]),

            AlertRuleDTO::fromArray([
                'id' => 'memory_usage_warning',
                'name' => '記憶體使用率警告告警',
                'description' => '記憶體使用率超過 80% 時觸發警告告警',
                'metric' => 'system.memory.usage.percent',
                'metricPath' => 'system.memory.usage_percentage',
                'operator' => 'gt',
                'threshold' => 80.0,
                'severity' => 'warning',
                'evaluationWindow' => 300,
                'triggerCount' => 3,
                'enabled' => true,
                'tags' => ['system', 'memory', 'warning'],
                'notificationChannels' => ['email']
            ]),

            // 磁碟使用率告警
            AlertRuleDTO::fromArray([
                'id' => 'disk_usage_critical',
                'name' => '磁碟使用率危險告警',
                'description' => '磁碟使用率超過 95% 時觸發危險告警',
                'metric' => 'system.disk.usage.percent',
                'metricPath' => 'system.disk.usage_percentage',
                'operator' => 'gt',
                'threshold' => 95.0,
                'severity' => 'critical',
                'evaluationWindow' => 600,
                'triggerCount' => 1,
                'enabled' => true,
                'tags' => ['system', 'disk', 'critical'],
                'notificationChannels' => ['email', 'slack']
            ]),

            AlertRuleDTO::fromArray([
                'id' => 'disk_usage_warning',
                'name' => '磁碟使用率警告告警',
                'description' => '磁碟使用率超過 85% 時觸發警告告警',
                'metric' => 'system.disk.usage.percent',
                'metricPath' => 'system.disk.usage_percentage',
                'operator' => 'gt',
                'threshold' => 85.0,
                'severity' => 'warning',
                'evaluationWindow' => 600,
                'triggerCount' => 2,
                'enabled' => true,
                'tags' => ['system', 'disk', 'warning'],
                'notificationChannels' => ['email']
            ]),

            // 錯誤率告警
            AlertRuleDTO::fromArray([
                'id' => 'error_rate_critical',
                'name' => '錯誤率危險告警',
                'description' => '每小時錯誤率超過 5% 時觸發危險告警',
                'metric' => 'application.error.rate.hourly',
                'metricPath' => 'application.error_rate_hourly',
                'operator' => 'gt',
                'threshold' => 5.0,
                'severity' => 'critical',
                'evaluationWindow' => 300,
                'triggerCount' => 2,
                'enabled' => true,
                'tags' => ['application', 'error', 'critical'],
                'notificationChannels' => ['email', 'slack']
            ]),

            AlertRuleDTO::fromArray([
                'id' => 'error_rate_warning',
                'name' => '錯誤率警告告警',
                'description' => '每小時錯誤率超過 2% 時觸發警告告警',
                'metric' => 'application.error.rate.hourly',
                'metricPath' => 'application.error_rate_hourly',
                'operator' => 'gt',
                'threshold' => 2.0,
                'severity' => 'warning',
                'evaluationWindow' => 300,
                'triggerCount' => 3,
                'enabled' => true,
                'tags' => ['application', 'error', 'warning'],
                'notificationChannels' => ['email']
            ]),

            // 回應時間告警
            AlertRuleDTO::fromArray([
                'id' => 'response_time_critical',
                'name' => '回應時間危險告警',
                'description' => '平均回應時間超過 2000ms 時觸發危險告警',
                'metric' => 'application.response.time.avg',
                'metricPath' => 'application.response_time_avg',
                'operator' => 'gt',
                'threshold' => 2000.0,
                'severity' => 'critical',
                'evaluationWindow' => 300,
                'triggerCount' => 3,
                'enabled' => true,
                'tags' => ['application', 'performance', 'critical'],
                'notificationChannels' => ['email', 'slack']
            ])
        ];
    }
}