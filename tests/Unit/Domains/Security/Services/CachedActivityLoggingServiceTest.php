<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Security\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Services\CachedActivityLoggingService;
use App\Shared\Contracts\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * 快取增強活動記錄服務測試
 */
final class CachedActivityLoggingServiceTest extends TestCase
{
    private MockObject&ActivityLoggingServiceInterface $mockDecoratedService;
    private MockObject&CacheInterface $mockCache;
    private CachedActivityLoggingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDecoratedService = $this->createMock(ActivityLoggingServiceInterface::class);
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->service = new CachedActivityLoggingService(
            $this->mockDecoratedService,
            $this->mockCache
        );
    }

    public function testLogSuccessInvalidatesCache(): void
    {
        // Arrange
        $dto = CreateActivityLogDTO::success(ActivityType::LOGIN_SUCCESS, 123);
        
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('log')
            ->with($dto)
            ->willReturn(true);
        
        $this->mockCache
            ->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);
        
        // Act
        $result = $this->service->log($dto);
        
        // Assert
        $this->assertTrue($result);
    }

    public function testLogFailureDoesNotInvalidateCacheWhenServiceFails(): void
    {
        // Arrange
        $dto = CreateActivityLogDTO::success(ActivityType::LOGIN_SUCCESS, 123);
        
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('log')
            ->with($dto)
            ->willReturn(false);
        
        $this->mockCache
            ->expects($this->never())
            ->method('delete');
        
        // Act
        $result = $this->service->log($dto);
        
        // Assert
        $this->assertFalse($result);
    }

    public function testLogSecurityEventInvalidatesSecurityCache(): void
    {
        // Arrange
        $dto = CreateActivityLogDTO::failure(ActivityType::LOGIN_FAILED, 123);
        
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('log')
            ->with($dto)
            ->willReturn(true);
        
        // 預期會刪除使用者快取、統計快取和安全事件快取
        $this->mockCache
            ->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);
        
        // Act
        $result = $this->service->log($dto);
        
        // Assert
        $this->assertTrue($result);
    }

    public function testIsLoggingEnabledUsesCache(): void
    {
        // Arrange
        $actionType = ActivityType::LOGIN_SUCCESS;
        $expectedCacheKey = 'activity_log:config:enabled:' . $actionType->value;
        
        // 第一次調用 - 快取不存在，需要從原始服務獲取
        $this->mockCache
            ->expects($this->exactly(2))
            ->method('get')
            ->with($expectedCacheKey)
            ->willReturnOnConsecutiveCalls(null, true);
        
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('isLoggingEnabled')
            ->with($actionType)
            ->willReturn(true);
        
        $this->mockCache
            ->expects($this->once())
            ->method('set')
            ->with($expectedCacheKey, true, 3600)
            ->willReturn(true);
        
        // Act & Assert
        $this->assertTrue($this->service->isLoggingEnabled($actionType));
        
        // 第二次調用 - 從快取獲取
        $this->assertTrue($this->service->isLoggingEnabled($actionType));
    }

    public function testEnableLoggingInvalidatesConfigCache(): void
    {
        // Arrange
        $actionType = ActivityType::LOGIN_SUCCESS;
        
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('enableLogging')
            ->with($actionType);
        
        $this->mockCache
            ->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);
        
        // Act
        $this->service->enableLogging($actionType);
        
        // Assert - 透過 mock 驗證已執行
        $this->addToAssertionCount(1);
    }

    public function testCleanupInvalidatesAllCaches(): void
    {
        // Arrange
        $this->mockDecoratedService
            ->expects($this->once())
            ->method('cleanup')
            ->willReturn(10);
        
        $this->mockCache
            ->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);
        
        // Act
        $result = $this->service->cleanup();
        
        // Assert
        $this->assertSame(10, $result);
    }

    public function testGetCacheStatsReturnsConfiguration(): void
    {
        // Act
        $stats = $this->service->getCacheStats();
        
        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cache_backend', $stats);
        $this->assertArrayHasKey('cache_ttl', $stats);
        $this->assertArrayHasKey('user_activity', $stats['cache_ttl']);
        $this->assertArrayHasKey('stats', $stats['cache_ttl']);
        $this->assertArrayHasKey('security_events', $stats['cache_ttl']);
        $this->assertArrayHasKey('config', $stats['cache_ttl']);
    }

    public function testClearUserCacheCallsInvalidateUserCache(): void
    {
        // Arrange
        $userId = 123;
        
        $this->mockCache
            ->expects($this->once())
            ->method('delete')
            ->with('activity_log:user:123:activities')
            ->willReturn(true);
        
        // Act
        $result = $this->service->clearUserCache($userId);
        
        // Assert
        $this->assertTrue($result);
    }
}