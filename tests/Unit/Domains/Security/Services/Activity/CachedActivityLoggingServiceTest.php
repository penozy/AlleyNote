<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Security\Services\Activity;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Services\Activity\CachedActivityLoggingService;
use App\Shared\Contracts\CacheInterface;
use Exception;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CachedActivityLoggingServiceTest extends TestCase
{
    private CachedActivityLoggingService $cachedService;

    private ActivityLoggingServiceInterface $mockActivityService;

    private CacheInterface $mockCache;

    protected function setUp(): void
    {
        $this->mockActivityService = Mockery::mock(ActivityLoggingServiceInterface::class);
        $this->mockCache = Mockery::mock(CacheInterface::class);
        $this->cachedService = new CachedActivityLoggingService(
            $this->mockActivityService,
            $this->mockCache,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[Test]
    public function logActivityInvalidatesCache(): void
    {
        $dto = new CreateActivityLogDTO(
            actionType: ActivityType::LOGIN_SUCCESS,
            userId: 123,
            targetType: 'system',
            targetId: '1',
            ipAddress: '192.168.1.1',
        );

        // 預期 cache 會被清除
        $this->mockCache
            ->shouldReceive('delete')
            ->with('user_activity_stats_123')
            ->once()
            ->andReturn(true);

        // 預期會呼叫原始服務
        $this->mockActivityService
            ->shouldReceive('log')
            ->with($dto)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->log($dto);

        $this->assertTrue($result);
    }

    #[Test]
    public function logSuccessInvalidatesCache(): void
    {
        $userId = 123;
        $actionType = ActivityType::LOGIN_SUCCESS;

        // 預期 cache 會被清除
        $this->mockCache
            ->shouldReceive('delete')
            ->with('user_activity_stats_123')
            ->once()
            ->andReturn(true);

        $this->mockActivityService
            ->shouldReceive('logSuccess')
            ->with($actionType, $userId, null, null, null)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->logSuccess($actionType, $userId);
        $this->assertTrue($result);
    }

    #[Test]
    public function logFailureInvalidatesCache(): void
    {
        $userId = 123;
        $actionType = ActivityType::LOGIN_FAILED;
        $reason = 'Invalid credentials';

        $this->mockCache
            ->shouldReceive('delete')
            ->with('user_activity_stats_123')
            ->once()
            ->andReturn(true);

        $this->mockActivityService
            ->shouldReceive('logFailure')
            ->with($actionType, $userId, $reason, null)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->logFailure($actionType, $userId, $reason);
        $this->assertTrue($result);
    }

    #[Test]
    public function logSecurityEventCallsOriginalService(): void
    {
        $actionType = ActivityType::SUSPICIOUS_ACTIVITY_DETECTED;
        $description = 'Suspicious activity detected';
        $metadata = ['ip' => '192.168.1.1'];

        $this->mockActivityService
            ->shouldReceive('logSecurityEvent')
            ->with($actionType, $description, $metadata)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->logSecurityEvent($actionType, $description, $metadata);
        $this->assertTrue($result);
    }

    #[Test]
    public function logBatchInvalidatesMultipleUserCaches(): void
    {
        $dtos = [
            new CreateActivityLogDTO(
                actionType: ActivityType::LOGIN_SUCCESS,
                userId: 123,
                targetType: 'system',
                targetId: '1',
            ),
            new CreateActivityLogDTO(
                actionType: ActivityType::LOGOUT,
                userId: 456,
                targetType: 'system',
                targetId: '1',
            ),
        ];

        // 預期會清除多個使用者的 cache
        $this->mockCache
            ->shouldReceive('delete')
            ->with('user_activity_stats_123')
            ->once()
            ->andReturn(true);

        $this->mockCache
            ->shouldReceive('delete')
            ->with('user_activity_stats_456')
            ->once()
            ->andReturn(true);

        $this->mockActivityService
            ->shouldReceive('logBatch')
            ->with($dtos)
            ->once()
            ->andReturn(2);

        $result = $this->cachedService->logBatch($dtos);
        $this->assertEquals(2, $result);
    }

    #[Test]
    public function enableLoggingCallsOriginalService(): void
    {
        $actionType = ActivityType::LOGIN_SUCCESS;

        $this->mockActivityService
            ->shouldReceive('enableLogging')
            ->with($actionType)
            ->once();

        $this->cachedService->enableLogging($actionType);
    }

    #[Test]
    public function disableLoggingCallsOriginalService(): void
    {
        $actionType = ActivityType::LOGIN_SUCCESS;

        $this->mockActivityService
            ->shouldReceive('disableLogging')
            ->with($actionType)
            ->once();

        $this->cachedService->disableLogging($actionType);
    }

    #[Test]
    public function isLoggingEnabledUsesCache(): void
    {
        $actionType = ActivityType::LOGIN_SUCCESS;
        $cacheKey = 'logging_enabled_LOGIN_SUCCESS';

        // 第一次呼叫 - cache miss
        $this->mockCache
            ->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);

        $this->mockActivityService
            ->shouldReceive('isLoggingEnabled')
            ->with($actionType)
            ->once()
            ->andReturn(true);

        $this->mockCache
            ->shouldReceive('set')
            ->with($cacheKey, true, 300)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->isLoggingEnabled($actionType);
        $this->assertTrue($result);

        // 第二次呼叫 - cache hit
        $this->mockCache
            ->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(true);

        $result2 = $this->cachedService->isLoggingEnabled($actionType);
        $this->assertTrue($result2);
    }

    #[Test]
    public function setLogLevelInvalidatesConfigCache(): void
    {
        $level = 3;

        $this->mockCache
            ->shouldReceive('delete')
            ->with('log_level')
            ->once()
            ->andReturn(true);

        $this->mockActivityService
            ->shouldReceive('setLogLevel')
            ->with($level)
            ->once();

        $this->cachedService->setLogLevel($level);
    }

    #[Test]
    public function cleanupInvalidatesAllCaches(): void
    {
        // 預期會清除所有相關 cache
        $this->mockCache
            ->shouldReceive('deleteMultiple')
            ->with(['user_activity_stats_*', 'logging_enabled_*'])
            ->once()
            ->andReturn(true);

        $this->mockActivityService
            ->shouldReceive('cleanup')
            ->once()
            ->andReturn(150);

        $result = $this->cachedService->cleanup();
        $this->assertEquals(150, $result);
    }

    #[Test]
    public function cacheFailureDoesNotAffectFunctionality(): void
    {
        $dto = new CreateActivityLogDTO(
            actionType: ActivityType::USER_LOGIN,
            userId: 123,
            targetType: 'system',
            targetId: '1',
        );

        // cache 操作失敗
        $this->mockCache
            ->shouldReceive('delete')
            ->andThrow(new Exception('Cache error'));

        // 但原始服務仍然被呼叫
        $this->mockActivityService
            ->shouldReceive('log')
            ->with($dto)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->log($dto);
        $this->assertTrue($result);
    }

    #[Test]
    public function logWithNullUserIdDoesNotInvalidateUserCache(): void
    {
        $dto = new CreateActivityLogDTO(
            actionType: ActivityType::POST_CREATED,
            userId: null,
            targetType: 'system',
            targetId: '1',
        );

        // 不應該嘗試清除使用者 cache
        $this->mockCache
            ->shouldNotReceive('delete');

        $this->mockActivityService
            ->shouldReceive('log')
            ->with($dto)
            ->once()
            ->andReturn(true);

        $result = $this->cachedService->log($dto);
        $this->assertTrue($result);
    }
}
