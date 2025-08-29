<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Application\Controllers\BaseController;
use App\Application\Services\Monitoring\AlertManagerService;
use App\Application\Services\Monitoring\AlertRuleEngine;
use App\Application\Services\Monitoring\HealthCheckService;
use App\Application\Services\Monitoring\MetricsCollectorService;
use App\Domain\Common\ValueObjects\AlertSeverity;
use DateTime;
use Exception;

/**
 * 監控系統 API 控制器.
 *
 * 提供系統監控相關的 API 端點
 */
final class MonitoringController extends BaseController
{
    public function __construct(
        private readonly MetricsCollectorService $metricsCollector,
        private readonly HealthCheckService $healthCheck,
        private readonly AlertManagerService $alertManager,
        private readonly AlertRuleEngine $alertRuleEngine,
    ) {}

    /**
     * 取得系統健康狀態
     * GET /api/v1/health.
     */
    public function health(): string
    {
        try {
            $healthStatus = $this->healthCheck->performFullHealthCheck();

            $httpStatus = $healthStatus['status'] === 'healthy' ? 200 : 503;

            return $this->jsonResponse($healthStatus, $httpStatus);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得系統指標
     * GET /api/v1/metrics.
     */
    public function metrics(): string
    {
        try {
            $category = $_GET['category'] ?? null;

            if ($category) {
                // 根據類別收集特定指標
                $metrics = match ($category) {
                    'system' => $this->metricsCollector->collectSystemMetrics(),
                    'application' => $this->metricsCollector->collectApplicationMetrics(),
                    'database' => $this->metricsCollector->collectDatabaseMetrics(),
                    'cache' => $this->metricsCollector->collectCacheMetrics(),
                    'business' => $this->metricsCollector->collectBusinessMetrics(),
                    default => $this->metricsCollector->collectAllMetrics()
                };
            } else {
                $metrics = $this->metricsCollector->collectAllMetrics();
            }

            return $this->successResponse($metrics, '系統指標資料');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得活動告警列表
     * GET /api/v1/alerts.
     */
    public function alerts(): string
    {
        try {
            $severity = $_GET['severity'] ?? null;
            $severityEnum = null;

            if ($severity) {
                try {
                    $severityEnum = AlertSeverity::from($severity);
                } catch (Exception $e) {
                    return $this->errorResponse('無效的嚴重程度參數', 400);
                }
            }

            $alerts = $this->alertManager->getActiveAlerts($severityEnum);
            $alertsArray = array_map(fn($alert) => $alert->toArray(), $alerts);

            $responseData = [
                'alerts' => $alertsArray,
                'total' => count($alertsArray),
                'filtered_by_severity' => $severity,
            ];

            return $this->successResponse($responseData, '活動告警列表');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 確認告警
     * POST /api/v1/alerts/{alert_id}/acknowledge.
     */
    public function acknowledgeAlert(string $alertId): string
    {
        try {
            if (empty($alertId)) {
                return $this->errorResponse('告警 ID 不能為空', 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $acknowledgedBy = $input['acknowledged_by'] ?? 'unknown';

            $success = $this->alertManager->acknowledgeAlert($alertId, $acknowledgedBy);

            if ($success) {
                return $this->successResponse([
                    'alert_id' => $alertId,
                    'acknowledged_by' => $acknowledgedBy,
                ], '告警確認成功');
            } else {
                return $this->errorResponse('告警確認失敗，可能告警不存在', 404);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 解決告警
     * POST /api/v1/alerts/{alert_id}/resolve.
     */
    public function resolveAlert(string $alertId): string
    {
        try {
            if (empty($alertId)) {
                return $this->errorResponse('告警 ID 不能為空', 400);
            }

            $success = $this->alertManager->resolveAlert($alertId);

            if ($success) {
                return $this->successResponse(['alert_id' => $alertId], '告警解決成功');
            } else {
                return $this->errorResponse('告警解決失敗，可能告警不存在', 404);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 靜音告警
     * POST /api/v1/alerts/{alert_id}/silence.
     */
    public function silenceAlert(string $alertId): string
    {
        try {
            if (empty($alertId)) {
                return $this->errorResponse('告警 ID 不能為空', 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $until = $input['until'] ?? null;

            if (empty($until)) {
                return $this->errorResponse('靜音截止時間不能為空', 400);
            }

            $untilDateTime = new DateTime($until);
            $success = $this->alertManager->silenceAlert($alertId, $untilDateTime);

            if ($success) {
                return $this->successResponse([
                    'alert_id' => $alertId,
                    'silenced_until' => $until,
                ], '告警靜音成功');
            } else {
                return $this->errorResponse('告警靜音失敗，可能告警不存在', 404);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得告警統計
     * GET /api/v1/alerts/statistics.
     */
    public function alertStatistics(): string
    {
        try {
            $statistics = $this->alertManager->getAlertStatistics();

            return $this->successResponse($statistics, '告警統計資料');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得告警規則
     * GET /api/v1/alert-rules.
     */
    public function alertRules(): string
    {
        try {
            $enabled = isset($_GET['enabled']) ? filter_var($_GET['enabled'], FILTER_VALIDATE_BOOLEAN) : null;

            if ($enabled !== null) {
                $rules = $enabled
                    ? $this->alertRuleEngine->getEnabledRules()
                    : array_filter(
                        $this->alertRuleEngine->getAllRules(),
                        fn($rule) => !$rule->enabled,
                    );
            } else {
                $rules = $this->alertRuleEngine->getAllRules();
            }

            $rulesArray = array_map(fn($rule) => $rule->toArray(), $rules);

            $responseData = [
                'rules' => $rulesArray,
                'total' => count($rulesArray),
                'enabled_filter' => $enabled,
            ];

            return $this->successResponse($responseData, '告警規則列表');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得監控儀表板資料
     * GET /api/v1/monitoring/dashboard.
     */
    public function dashboard(): string
    {
        try {
            // 收集儀表板所需的所有資料
            $dashboardData = [
                'timestamp' => new DateTime()->format('Y-m-d H:i:s'),
                'health' => $this->healthCheck->performFullHealthCheck(),
                'metrics' => $this->metricsCollector->collectAllMetrics(),
                'alerts' => [
                    'active' => array_map(
                        fn($alert) => $alert->getSummary(),
                        $this->alertManager->getActiveAlerts(),
                    ),
                    'statistics' => $this->alertManager->getAlertStatistics(),
                ],
                'alert_rules' => [
                    'total' => count($this->alertRuleEngine->getAllRules()),
                    'enabled' => count($this->alertRuleEngine->getEnabledRules()),
                    'by_severity' => [
                        'critical' => count($this->alertRuleEngine->getRulesBySeverity(AlertSeverity::CRITICAL)),
                        'warning' => count($this->alertRuleEngine->getRulesBySeverity(AlertSeverity::WARNING)),
                        'info' => count($this->alertRuleEngine->getRulesBySeverity(AlertSeverity::INFO)),
                        'debug' => count($this->alertRuleEngine->getRulesBySeverity(AlertSeverity::DEBUG)),
                    ],
                ],
            ];

            return $this->successResponse($dashboardData, '監控儀表板資料');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 手動觸發指標評估
     * POST /api/v1/monitoring/evaluate.
     */
    public function evaluateMetrics(): string
    {
        try {
            // 收集所有指標
            $metrics = $this->metricsCollector->collectAllMetrics();

            // 評估告警規則
            $alerts = $this->alertRuleEngine->evaluateMetrics($metrics);

            $processedAlerts = [];
            foreach ($alerts as $alert) {
                $result = $this->alertManager->handleAlert($alert);
                $processedAlerts[] = [
                    'alert' => $alert->getSummary(),
                    'processing_result' => $result,
                ];
            }

            $responseData = [
                'evaluation_time' => new DateTime()->format('Y-m-d H:i:s'),
                'metrics_collected' => !empty($metrics),
                'alerts_generated' => count($alerts),
                'alerts_processed' => count($processedAlerts),
                'alerts' => $processedAlerts,
            ];

            return $this->successResponse($responseData, '指標評估完成');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 取得告警歷史
     * GET /api/v1/alerts/history.
     */
    public function alertHistory(): string
    {
        try {
            $date = $_GET['date'] ?? null;

            $history = $this->alertManager->getAlertHistory($date);

            $responseData = [
                'date' => $date ?? date('Y-m-d'),
                'total_events' => count($history),
                'history' => $history,
            ];

            return $this->successResponse($responseData, '告警歷史資料');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 清理過期告警資料
     * DELETE /api/v1/alerts/cleanup.
     */
    public function cleanupAlerts(): string
    {
        try {
            $cleanedCount = $this->alertManager->cleanupExpiredAlerts();

            $responseData = [
                'cleaned_records' => $cleanedCount,
                'cleanup_time' => new DateTime()->format('Y-m-d H:i:s'),
            ];

            return $this->successResponse($responseData, "成功清理 {$cleanedCount} 條過期告警記錄");
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
