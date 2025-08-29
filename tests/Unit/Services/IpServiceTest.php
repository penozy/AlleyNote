<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\IpRepositoryInterface;
use App\Domains\Security\DTOs\CreateIpRuleDTO;
use App\Domains\Security\Models\IpList;
use App\Domains\Security\Services\IpService;
use App\Domains\Security\Services\SuspiciousActivityDetector;
use App\Shared\Contracts\ValidatorInterface;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class IpServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private IpRepositoryInterface $repository;

    private ValidatorInterface $validator;

    private SuspiciousActivityDetector $suspiciousActivityDetector;

    private ActivityLoggingServiceInterface $activityLogger;

    private IpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(IpRepositoryInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->suspiciousActivityDetector = Mockery::mock(SuspiciousActivityDetector::class);
        $this->activityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);

        // 設定 Validator Mock 的通用預期
        $this->validator->shouldReceive('addRule')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        $this->validator->shouldReceive('addMessage')
            ->zeroOrMoreTimes()
            ->andReturnSelf();

        // 設定 ActivityLogger Mock 的通用預期
        $this->activityLogger->shouldReceive('log')
            ->zeroOrMoreTimes();
        $this->activityLogger->shouldReceive('logSuccess')
            ->zeroOrMoreTimes();
        $this->activityLogger->shouldReceive('logFailure')
            ->zeroOrMoreTimes();
        $this->activityLogger->shouldReceive('logSecurityEvent')
            ->zeroOrMoreTimes();

        $this->service = new IpService(
            $this->repository,
            $this->activityLogger,
            $this->validator,
        );
    }

    public function testCanCreateIpRule(): void
    {
        $data = [
            'ip_address' => '192.168.1.1',
            'action' => 'allow',
            'reason' => '測試白名單',
            'created_by' => 1,
        ];

        $expectedIpList = new IpList([
            'id' => 1,
            'uuid' => 'test-uuid',
            'ip_address' => '192.168.1.1',
            'type' => 1,
            'description' => '測試白名單',
        ]);

        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->with(Mockery::any(), Mockery::any())
            ->andReturn($data);

        $dto = new CreateIpRuleDTO($this->validator, $data);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::any())
            ->andReturn($expectedIpList);

        $result = $this->service->createIpRule($dto);

        $this->assertSame($expectedIpList, $result);
    }

    public function testCannotCreateInvalidIpRule(): void
    {
        $data = [
            'ip_address' => 'invalid-ip',
            'action' => 'allow',
            'created_by' => 1,
        ];

        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->with(Mockery::any(), Mockery::any())
            ->andThrow(new ValidationException(
                new ValidationResult(false, ['ip_address' => ['無效的 IP 位址格式']], [], []),
            ));

        $this->expectException(ValidationException::class);

        $dto = new CreateIpRuleDTO($this->validator, $data);
        $this->service->createIpRule($dto);
    }

    public function testCannotCreateWithInvalidType(): void
    {
        $data = [
            'ip_address' => '192.168.1.1',
            'action' => 'invalid_action',
            'created_by' => 1,
        ];

        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->with(Mockery::any(), Mockery::any())
            ->andThrow(new ValidationException(
                new ValidationResult(false, ['action' => ['無效的動作類型']], [], []),
            ));

        $this->expectException(ValidationException::class);

        $dto = new CreateIpRuleDTO($this->validator, $data);
        $this->service->createIpRule($dto);
    }

    public function testCanCheckIpAccess(): void
    {
        $ip = '192.168.1.1';

        // 情境 1：IP 在白名單中
        $this->repository->shouldReceive('isWhitelisted')
            ->once()
            ->with($ip)
            ->andReturn(true);

        $this->repository->shouldReceive('isBlacklisted')
            ->never();

        $this->assertTrue($this->service->isIpAllowed($ip));

        // 情境 2：IP 在黑名單中
        $this->repository->shouldReceive('isWhitelisted')
            ->once()
            ->with($ip)
            ->andReturn(false);

        $this->repository->shouldReceive('isBlacklisted')
            ->once()
            ->with($ip)
            ->andReturn(true);

        $this->assertFalse($this->service->isIpAllowed($ip));

        // 情境 3：IP 不在任何名單中
        $this->repository->shouldReceive('isWhitelisted')
            ->once()
            ->with($ip)
            ->andReturn(false);

        $this->repository->shouldReceive('isBlacklisted')
            ->once()
            ->with($ip)
            ->andReturn(false);

        $this->assertTrue($this->service->isIpAllowed($ip));
    }

    public function testCanValidateCidrRange(): void
    {
        $validRanges = [
            '192.168.1.0/24',
            '10.0.0.0/8',
            '172.16.0.0/12',
        ];

        foreach ($validRanges as $range) {
            $data = [
                'ip_address' => $range,
                'action' => 'block',
                'created_by' => 1,
            ];

            $mockIpList = new IpList([
                'ip_address' => $range,
                'type' => 0,
            ]);

            $this->validator->shouldReceive('validateOrFail')
                ->once()
                ->with(Mockery::any(), Mockery::any())
                ->andReturn($data);

            $dto = new CreateIpRuleDTO($this->validator, $data);

            $this->repository->shouldReceive('create')
                ->once()
                ->with(Mockery::any())
                ->andReturn($mockIpList);

            $result = $this->service->createIpRule($dto);
            $this->assertEquals($range, $result->getIpAddress());
        }
    }

    public function testCanGetRulesByType(): void
    {
        $type = 1; // 白名單
        $mockRules = [
            new IpList([
                'ip_address' => '192.168.1.1',
                'type' => 1,
            ]),
            new IpList([
                'ip_address' => '192.168.1.2',
                'type' => 1,
            ]),
        ];

        $this->repository->shouldReceive('getByType')
            ->once()
            ->with($type)
            ->andReturn($mockRules);

        $result = $this->service->getRulesByType($type);

        $this->assertCount(2, $result);
        $this->assertEquals('192.168.1.1', $result[0]->getIpAddress());
        $this->assertEquals('192.168.1.2', $result[1]->getIpAddress());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
