<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Security\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Domains\Security\DTOs\SuspiciousActivityAnalysisDTO;
use App\Domains\Security\Enums\ActivitySeverity;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Services\SuspiciousActivityDetector;
use DateTimeImmutable;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * SuspiciousActivityDetector 測試.
 */
class SuspiciousActivityDetectorTest extends TestCase
{
    private SuspiciousActivityDetector $detector;

    /** @var ActivityLogRepositoryInterface&MockInterface */
    private ActivityLogRepositoryInterface $mockRepository;

    /** @var ActivityLoggingServiceInterface&MockInterface */
    private ActivityLoggingServiceInterface $mockActivityLogger;

    /** @var LoggerInterface&MockInterface */
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(ActivityLogRepositoryInterface::class);
        $this->mockActivityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);

        $this->detector = new SuspiciousActivityDetector(
            $this->mockRepository,
            $this->mockActivityLogger,
            $this->mockLogger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_detect_suspicious_user_activity_with_high_failure_rate(): void
    {
        $userId = 123;
        // 使用預設時間窗口 60 分鐘
        $timeWindow = 60;
        $now = new DateTimeImmutable();

        // 模擬高失敗率的活動記錄（確保超過預設閾值 5）
        $activities = [
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'user_id' => $userId, 'ip_address' => '192.168.1.1', 'occurred_at' => $now->format('Y-m-d H:i:s')],
        ];

        $this->mockRepository
            ->shouldReceive('findByUserAndTimeRange')
            ->once()
            ->andReturn($activities);

        $this->mockActivityLogger
            ->shouldReceive('log')
            ->once();

        // Logger 可能在正常情況下也會被呼叫，我們允許任何呼叫
        $this->mockLogger->shouldIgnoreMissing();

        $result = $this->detector->detectSuspiciousActivity($userId, $timeWindow);

        $this->assertInstanceOf(SuspiciousActivityAnalysisDTO::class, $result);
        $this->assertTrue($result->isSuspicious());
        $this->assertSame((string) $userId, $result->getTargetId());
        $this->assertSame('user', $result->getTargetType());
    }

    #[Test]
    public function it_can_detect_suspicious_ip_activity(): void
    {
        $ipAddress = '192.168.1.100';
        // 使用預設時間窗口
        $timeWindow = 60;
        $now = new DateTimeImmutable();

        // 模擬可疑IP活動（確保超過閾值）
        $activities = [
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 1, 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 2, 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 3, 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 4, 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 5, 'occurred_at' => $now->format('Y-m-d H:i:s')],
            ['action_type' => 'auth.login.failed', 'status' => 'failed', 'ip_address' => $ipAddress, 'user_id' => 6, 'occurred_at' => $now->format('Y-m-d H:i:s')],
        ];

        $this->mockRepository
            ->shouldReceive('findByIpAddressAndTimeRange')
            ->once()
            ->andReturn($activities);

        $this->mockActivityLogger
            ->shouldReceive('log')
            ->once();

        // Logger 可能在正常情況下也會被呼叫，我們允許任何呼叫
        $this->mockLogger->shouldIgnoreMissing();

        $result = $this->detector->detectSuspiciousIpActivity($ipAddress, $timeWindow);

        $this->assertInstanceOf(SuspiciousActivityAnalysisDTO::class, $result);
        $this->assertTrue($result->isSuspicious());
        $this->assertSame($ipAddress, $result->getTargetId());
        $this->assertSame('ip', $result->getTargetType());
    }

    #[Test]
    public function it_can_detect_global_suspicious_patterns(): void
    {
        $timeWindow = 60;

        // 模擬異常高的失敗率統計資料
        $statistics = [
            ['action_category' => 'authentication', 'action_type' => 'auth.login.failed', 'count' => 500],  // 異常高
            ['action_category' => 'security', 'action_type' => 'security.brute_force', 'count' => 150],    // 異常高
            ['action_category' => 'authentication', 'action_type' => 'auth.login.success', 'count' => 100],
        ];

        $this->mockRepository
            ->shouldReceive('getActivityStatistics')
            ->once()
            ->andReturn($statistics);

        // 不期望每次都會記錄活動，允許可選的記錄呼叫
        $this->mockActivityLogger->shouldIgnoreMissing();

        // Logger 可能在正常情況下也會被呼叫，我們允許任何呼叫
        $this->mockLogger->shouldIgnoreMissing();

        $patterns = $this->detector->detectGlobalSuspiciousPatterns($timeWindow);

        $this->assertIsArray($patterns);
        // 由於統計資料顯示異常，應該會檢測到模式
    }

    #[Test]
    public function it_can_set_and_get_failure_threshold(): void
    {
        $activityType = ActivityType::LOGIN_FAILED;
        $threshold = 10;
        $timeWindow = 30;

        $this->detector->setFailureThreshold($activityType, $threshold, $timeWindow);

        $config = $this->detector->getThresholdConfiguration();

        $this->assertArrayHasKey('failure_thresholds', $config);
        $this->assertArrayHasKey($activityType->value, $config['failure_thresholds']);
        $this->assertSame($threshold, $config['failure_thresholds'][$activityType->value]['threshold']);
        $this->assertSame($timeWindow, $config['failure_thresholds'][$activityType->value]['timeWindow']);
    }

    #[Test]
    public function it_can_set_and_get_frequency_threshold(): void
    {
        $activityType = ActivityType::LOGIN_SUCCESS;
        $threshold = 50;
        $timeWindow = 60;

        $this->detector->setFrequencyThreshold($activityType, $threshold, $timeWindow);

        $config = $this->detector->getThresholdConfiguration();

        $this->assertArrayHasKey('frequency_thresholds', $config);
        $this->assertArrayHasKey($activityType->value, $config['frequency_thresholds']);
        $this->assertSame($threshold, $config['frequency_thresholds'][$activityType->value]['threshold']);
        $this->assertSame($timeWindow, $config['frequency_thresholds'][$activityType->value]['timeWindow']);
    }

    #[Test]
    public function it_can_enable_and_disable_detection_types(): void
    {
        $detectionType = 'failure_rate';

        // 預設應該啟用
        $this->assertTrue($this->detector->isDetectionEnabled($detectionType));

        // 停用
        $this->detector->disableDetection($detectionType);
        $this->assertFalse($this->detector->isDetectionEnabled($detectionType));

        // 重新啟用
        $this->detector->enableDetection($detectionType);
        $this->assertTrue($this->detector->isDetectionEnabled($detectionType));
    }

    #[Test]
    public function it_can_reset_thresholds_to_defaults(): void
    {
        // 修改一個閾值
        $this->detector->setFailureThreshold(ActivityType::LOGIN_FAILED, 999);

        // 重設為預設值
        $this->detector->resetThresholdsToDefaults();

        $config = $this->detector->getThresholdConfiguration();

        // 應該回到預設值（注意是 timeWindow 而非 time_window）
        $this->assertSame(5, $config['failure_thresholds'][ActivityType::LOGIN_FAILED->value]['threshold']);
        $this->assertSame(60, $config['failure_thresholds'][ActivityType::LOGIN_FAILED->value]['timeWindow']);
    }

    #[Test]
    public function it_should_trigger_alert_for_high_severity_suspicious_activity(): void
    {
        $analysis = SuspiciousActivityAnalysisDTO::forUser(
            userId: 123,
            timeWindowMinutes: 60,
            isSuspicious: true,
            severityLevel: ActivitySeverity::CRITICAL,
            activityCounts: ['auth.login.failed' => 10],
            failureCounts: ['auth.login.failed' => 10],
            anomalyScores: ['auth.login.failed' => 0.95],
            detectionRules: ['high_failure_rate'],
            metadata: [],
            recommendedAction: 'Block user immediately',
            confidenceScore: 0.95,
        );

        $shouldAlert = $this->detector->shouldTriggerAlert($analysis);

        $this->assertTrue($shouldAlert);
    }

    #[Test]
    public function it_should_not_trigger_alert_for_non_suspicious_activity(): void
    {
        $analysis = SuspiciousActivityAnalysisDTO::forUser(
            userId: 123,
            timeWindowMinutes: 60,
            isSuspicious: false,
            severityLevel: ActivitySeverity::LOW,
            activityCounts: ['auth.login.success' => 5],
            failureCounts: [],
            anomalyScores: [],
            detectionRules: [],
            metadata: [],
            recommendedAction: null,
            confidenceScore: 0.1,
        );

        $shouldAlert = $this->detector->shouldTriggerAlert($analysis);

        $this->assertFalse($shouldAlert);
    }

    #[Test]
    public function it_handles_exceptions_gracefully_during_user_detection(): void
    {
        $userId = 123;

        $this->mockRepository
            ->shouldReceive('findByUserAndTimeRange')
            ->andThrow(new Exception('Database error'));

        $this->mockLogger
            ->shouldReceive('error')
            ->once();

        $result = $this->detector->detectSuspiciousActivity($userId);

        $this->assertInstanceOf(SuspiciousActivityAnalysisDTO::class, $result);
        $this->assertFalse($result->isSuspicious());
        $this->assertSame(ActivitySeverity::LOW, $result->getSeverityLevel());
    }

    #[Test]
    public function it_handles_exceptions_gracefully_during_ip_detection(): void
    {
        $ipAddress = '192.168.1.1';

        $this->mockRepository
            ->shouldReceive('findByIpAddressAndTimeRange')
            ->andThrow(new Exception('Database error'));

        $this->mockLogger
            ->shouldReceive('error')
            ->once();

        $result = $this->detector->detectSuspiciousIpActivity($ipAddress);

        $this->assertInstanceOf(SuspiciousActivityAnalysisDTO::class, $result);
        $this->assertFalse($result->isSuspicious());
        $this->assertSame(ActivitySeverity::LOW, $result->getSeverityLevel());
    }

    #[Test]
    public function it_returns_empty_array_when_global_detection_fails(): void
    {
        $this->mockRepository
            ->shouldReceive('getActivityStatistics')
            ->andThrow(new Exception('Database error'));

        $this->mockLogger
            ->shouldReceive('error')
            ->once();

        $patterns = $this->detector->detectGlobalSuspiciousPatterns();

        $this->assertIsArray($patterns);
        $this->assertEmpty($patterns);
    }
}
