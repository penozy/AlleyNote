<?php

declare(strict_types=1);

/**
 * 監控系統 API 路由配置
 * 
 * 定義監控相關的 API 端點路由
 */

return [
    // 健康檢查
    [
        'method' => 'GET',
        'path' => '/api/v1/health',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'health',
        'description' => '系統健康狀態檢查'
    ],

    // 系統指標
    [
        'method' => 'GET',
        'path' => '/api/v1/metrics',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'metrics',
        'description' => '取得系統指標資料'
    ],

    // 告警管理
    [
        'method' => 'GET',
        'path' => '/api/v1/alerts',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'alerts',
        'description' => '取得活動告警列表'
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/alerts/{alert_id}/acknowledge',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'acknowledgeAlert',
        'description' => '確認告警'
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/alerts/{alert_id}/resolve',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'resolveAlert',
        'description' => '解決告警'
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/alerts/{alert_id}/silence',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'silenceAlert',
        'description' => '靜音告警'
    ],
    [
        'method' => 'GET',
        'path' => '/api/v1/alerts/statistics',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'alertStatistics',
        'description' => '取得告警統計資料'
    ],
    [
        'method' => 'GET',
        'path' => '/api/v1/alerts/history',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'alertHistory',
        'description' => '取得告警歷史記錄'
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/v1/alerts/cleanup',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'cleanupAlerts',
        'description' => '清理過期告警資料'
    ],

    // 告警規則
    [
        'method' => 'GET',
        'path' => '/api/v1/alert-rules',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'alertRules',
        'description' => '取得告警規則列表'
    ],

    // 監控儀表板
    [
        'method' => 'GET',
        'path' => '/api/v1/monitoring/dashboard',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'dashboard',
        'description' => '取得監控儀表板資料'
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/monitoring/evaluate',
        'controller' => 'App\Controllers\Api\V1\MonitoringController',
        'action' => 'evaluateMetrics',
        'description' => '手動觸發指標評估'
    ]
];