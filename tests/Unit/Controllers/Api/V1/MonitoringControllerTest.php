<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Api\V1;

use App\Application\DTOs\Monitoring\AlertDTO;
use App\Application\DTOs\Monitoring\AlertRuleDTO;
use App\Application\Services\Monitoring\AlertManagerService;
use App\Application\Services\Monitoring\AlertRuleEngine;
use App\Application\Services\Monitoring\HealthCheckService;
use App\Application\Services\Monitoring\MetricsCollectorService;
use App\Controllers\Api\V1\MonitoringController;
use App\Domain\Common\ValueObjects\AlertSeverity;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * 監控控制器單元測試.
 */
final class MonitoringControllerTest extends TestCase
{
    private MonitoringController $controller;

    /** @var MetricsCollectorService&MockObject */
    private MetricsCollectorService $metricsCollector;

    /** @var HealthCheckService&MockObject */
    private HealthCheckService $healthCheck;

    /** @var AlertManagerService&MockObject */
    private AlertManagerService $alertManager;

    /** @var AlertRuleEngine&MockObject */
    private AlertRuleEngine $alertRuleEngine;

    protected function setUp(): void
    {
        $this->metricsCollector = $this->createMock(MetricsCollectorService::class);
        $this->healthCheck = $this->createMock(HealthCheckService::class);
        $this->alertManager = $this->createMock(AlertManagerService::class);
        $this->alertRuleEngine = $this->createMock(AlertRuleEngine::class);

        $this->controller = new MonitoringController(
            metricsCollector: $this->metricsCollector,
            healthCheck: $this->healthCheck,
            alertManager: $this->alertManager,
            alertRuleEngine: $this->alertRuleEngine,
        );
    }

    public function testHealthReturnsHealthyStatus(): void
    {
        // Arrange
        $healthData = [
            'status' => 'healthy',
            'timestamp' => time(),
            'response_time' => 150.25,
            'checks' => [
                'database' => ['status' => 'healthy'],
                'cache' => ['status' => 'healthy'],
            ],
        ];

        $this->healthCheck
            ->expects($this->once())
            ->method('performFullHealthCheck')
            ->willReturn($healthData);

        // Act
        $result = $this->controller->health();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals($healthData, $decodedResult['data']);
    }

    public function testHealthReturnsUnhealthyStatus(): void
    {
        // Arrange
        $healthData = [
            'status' => 'unhealthy',
            'timestamp' => time(),
            'response_time' => 250.75,
            'checks' => [
                'database' => ['status' => 'unhealthy'],
                'cache' => ['status' => 'healthy'],
            ],
        ];

        $this->healthCheck
            ->expects($this->once())
            ->method('performFullHealthCheck')
            ->willReturn($healthData);

        // Act
        $result = $this->controller->health();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertEquals($healthData, $decodedResult['data']);
    }

    public function testMetricsReturnsAllMetrics(): void
    {
        // Arrange
        $metricsData = [
            'system' => ['cpu' => 45.2, 'memory' => 78.5],
            'application' => ['requests_per_second' => 125],
            'database' => ['connections' => 15],
        ];

        $this->metricsCollector
            ->expects($this->once())
            ->method('collectAllMetrics')
            ->willReturn($metricsData);

        // Act
        $result = $this->controller->metrics();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals($metricsData, $decodedResult['data']);
        $this->assertEquals('系統指標資料', $decodedResult['message']);
    }

    public function testMetricsReturnsSystemMetrics(): void
    {
        // Arrange
        $_GET['category'] = 'system';

        $systemMetrics = ['cpu' => 45.2, 'memory' => 78.5];

        $this->metricsCollector
            ->expects($this->once())
            ->method('collectSystemMetrics')
            ->willReturn($systemMetrics);

        // Act
        $result = $this->controller->metrics();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals($systemMetrics, $decodedResult['data']);

        // Clean up
        unset($_GET['category']);
    }

    public function testAlertsReturnsActiveAlerts(): void
    {
        // Arrange
        $mockAlert = $this->createMock(AlertDTO::class);
        $mockAlert->method('toArray')->willReturn([
            'id' => 'alert-123',
            'title' => 'High CPU Usage',
            'severity' => 'warning',
            'status' => 'active',
        ]);

        $alerts = [$mockAlert];

        $this->alertManager
            ->expects($this->once())
            ->method('getActiveAlerts')
            ->with(null)
            ->willReturn($alerts);

        // Act
        $result = $this->controller->alerts();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals(1, $decodedResult['data']['total']);
        $this->assertCount(1, $decodedResult['data']['alerts']);
    }

    public function testAlertsFiltersBySeverity(): void
    {
        // Arrange
        $_GET['severity'] = 'critical';

        $mockAlert = $this->createMock(AlertDTO::class);
        $mockAlert->method('toArray')->willReturn([
            'id' => 'alert-456',
            'title' => 'Database Down',
            'severity' => 'critical',
            'status' => 'active',
        ]);

        $alerts = [$mockAlert];

        $this->alertManager
            ->expects($this->once())
            ->method('getActiveAlerts')
            ->with(AlertSeverity::CRITICAL)
            ->willReturn($alerts);

        // Act
        $result = $this->controller->alerts();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals('critical', $decodedResult['data']['filtered_by_severity']);

        // Clean up
        unset($_GET['severity']);
    }

    public function testAcknowledgeAlertSuccess(): void
    {
        // Arrange
        $alertId = 'alert-123';

        // Mock PHP input
        $input = json_encode(['acknowledged_by' => 'admin']);
        $this->mockPhpInput($input);

        $this->alertManager
            ->expects($this->once())
            ->method('acknowledgeAlert')
            ->with($alertId, 'admin')
            ->willReturn(true);

        // Act
        $result = $this->controller->acknowledgeAlert($alertId);

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals('告警確認成功', $decodedResult['message']);
        $this->assertEquals($alertId, $decodedResult['data']['alert_id']);
        $this->assertEquals('admin', $decodedResult['data']['acknowledged_by']);
    }

    public function testAcknowledgeAlertFailure(): void
    {
        // Arrange
        $alertId = 'non-existent-alert';

        $input = json_encode(['acknowledged_by' => 'admin']);
        $this->mockPhpInput($input);

        $this->alertManager
            ->expects($this->once())
            ->method('acknowledgeAlert')
            ->with($alertId, 'admin')
            ->willReturn(false);

        // Act
        $result = $this->controller->acknowledgeAlert($alertId);

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertFalse($decodedResult['success']);
        $this->assertEquals('告警確認失敗，可能告警不存在', $decodedResult['message']);
    }

    public function testResolveAlertSuccess(): void
    {
        // Arrange
        $alertId = 'alert-123';

        $this->alertManager
            ->expects($this->once())
            ->method('resolveAlert')
            ->with($alertId)
            ->willReturn(true);

        // Act
        $result = $this->controller->resolveAlert($alertId);

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals('告警解決成功', $decodedResult['message']);
        $this->assertEquals($alertId, $decodedResult['data']['alert_id']);
    }

    public function testSilenceAlertSuccess(): void
    {
        // Arrange
        $alertId = 'alert-123';
        $until = '2024-12-31 23:59:59';

        $input = json_encode(['until' => $until]);
        $this->mockPhpInput($input);

        $this->alertManager
            ->expects($this->once())
            ->method('silenceAlert')
            ->with($alertId, $this->isInstanceOf(DateTime::class))
            ->willReturn(true);

        // Act
        $result = $this->controller->silenceAlert($alertId);

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals('告警靜音成功', $decodedResult['message']);
        $this->assertEquals($alertId, $decodedResult['data']['alert_id']);
        $this->assertEquals($until, $decodedResult['data']['silenced_until']);
    }

    public function testAlertStatistics(): void
    {
        // Arrange
        $statistics = [
            'total_alerts' => 25,
            'active_alerts' => 5,
            'resolved_alerts' => 20,
            'by_severity' => [
                'critical' => 2,
                'warning' => 3,
                'info' => 0,
                'debug' => 0,
            ],
        ];

        $this->alertManager
            ->expects($this->once())
            ->method('getAlertStatistics')
            ->willReturn($statistics);

        // Act
        $result = $this->controller->alertStatistics();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals($statistics, $decodedResult['data']);
        $this->assertEquals('告警統計資料', $decodedResult['message']);
    }

    public function testAlertRulesReturnsAllRules(): void
    {
        // Arrange
        $mockRule = $this->createMock(AlertRuleDTO::class);
        $mockRule->method('toArray')->willReturn([
            'id' => 'rule-1',
            'name' => 'High CPU Usage',
            'metric' => 'system.cpu.usage',
            'operator' => '>',
            'threshold' => 80,
            'severity' => 'warning',
            'enabled' => true,
        ]);

        $rules = [$mockRule];

        $this->alertRuleEngine
            ->expects($this->once())
            ->method('getAllRules')
            ->willReturn($rules);

        // Act
        $result = $this->controller->alertRules();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertEquals(1, $decodedResult['data']['total']);
        $this->assertCount(1, $decodedResult['data']['rules']);
    }

    public function testDashboardReturnsCompleteData(): void
    {
        // Arrange
        $healthData = ['status' => 'healthy'];
        $metricsData = ['system' => ['cpu' => 45.2]];
        $alertsData = [];
        $statisticsData = ['total_alerts' => 0];
        $allRules = [];
        $enabledRules = [];

        $this->healthCheck
            ->expects($this->once())
            ->method('performFullHealthCheck')
            ->willReturn($healthData);

        $this->metricsCollector
            ->expects($this->once())
            ->method('collectAllMetrics')
            ->willReturn($metricsData);

        $this->alertManager
            ->expects($this->once())
            ->method('getActiveAlerts')
            ->willReturn($alertsData);

        $this->alertManager
            ->expects($this->once())
            ->method('getAlertStatistics')
            ->willReturn($statisticsData);

        $this->alertRuleEngine
            ->expects($this->once())
            ->method('getAllRules')
            ->willReturn($allRules);

        $this->alertRuleEngine
            ->expects($this->once())
            ->method('getEnabledRules')
            ->willReturn($enabledRules);

        $this->alertRuleEngine
            ->expects($this->exactly(4))
            ->method('getRulesBySeverity')
            ->willReturn([]);

        // Act
        $result = $this->controller->dashboard();

        // Assert
        $this->assertIsString($result);

        $decodedResult = json_decode($result, true);
        $this->assertTrue($decodedResult['success']);
        $this->assertArrayHasKey('timestamp', $decodedResult['data']);
        $this->assertEquals($healthData, $decodedResult['data']['health']);
        $this->assertEquals($metricsData, $decodedResult['data']['metrics']);
    }

    /**
     * 模擬 PHP input 流
     */
    private function mockPhpInput(string $input): void
    {
        // 在真實環境中，可以使用 stream wrapper 或其他方法來模擬 php://input
        // 這裡僅作為範例，實際測試可能需要更複雜的設定
        $GLOBALS['_TEST_INPUT'] = $input;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_TEST_INPUT']);
        if (isset($_GET['category'])) {
            unset($_GET['category']);
        }
        if (isset($_GET['severity'])) {
            unset($_GET['severity']);
        }
    }
}
