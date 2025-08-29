<?php

namespace Tests\Unit\Services;

use App\Domains\Auth\Contracts\PasswordSecurityServiceInterface;
use App\Domains\Auth\DTOs\RegisterUserDTO;
use App\Domains\Auth\Repositories\UserRepository;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Shared\Contracts\ValidatorInterface;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private UserRepository|MockInterface $userRepository;

    private PasswordSecurityServiceInterface|MockInterface $passwordService;

    private ActivityLoggingServiceInterface|MockInterface $activityLogger;

    private ValidatorInterface|MockInterface $validator;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->passwordService = Mockery::mock(PasswordSecurityServiceInterface::class);
        $this->activityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);

        $this->service = new AuthService(
            $this->userRepository,
            $this->passwordService,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    #[Test]
    public function it_should_register_new_user_successfully(): void
    {
        // 準備測試資料
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'user_ip' => '192.168.1.1',
        ];

        // 設定驗證器 mock
        $this->validator->shouldReceive('addRule')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        $this->validator->shouldReceive('addMessage')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->with(Mockery::any(), Mockery::any())
            ->andReturn($userData);

        // 建立 DTO
        $dto = new RegisterUserDTO($this->validator, $userData);

        // 設定密碼服務 mock
        $this->passwordService->shouldReceive('validatePassword')
            ->once()
            ->with('password123');

        $this->passwordService->shouldReceive('hashPassword')
            ->once()
            ->with('password123')
            ->andReturn('hashed_password');

        // 設定用戶倉庫 mock
        $expectedData = $dto->toArray();
        $expectedData['password'] = 'hashed_password';

        $this->userRepository->shouldReceive('create')
            ->once()
            ->with($expectedData)
            ->andReturn([
                'id' => '1',
                'uuid' => 'test-uuid',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'status' => 1,
            ]);

        // 設定活動記錄 mock
        $this->activityLogger->shouldReceive('log')
            ->once()
            ->with(Mockery::type(CreateActivityLogDTO::class))
            ->andReturnUsing(function (CreateActivityLogDTO $activityDto) {
                $this->assertEquals(ActivityType::USER_REGISTERED, $activityDto->getActionType());
                $this->assertEquals('test@example.com', $activityDto->getDescription());
                $this->assertEquals('1', $activityDto->getUserId());
                $this->assertEquals('192.168.1.1', $activityDto->getIpAddress());
                $metadata = $activityDto->getMetadata();
                $this->assertArrayHasKey('username', $metadata);
                $this->assertArrayHasKey('email', $metadata);

                return true;
            });

        // 執行註冊
        $result = $this->service->register($dto, null);

        // 驗證結果
        $this->assertEquals('testuser', $result['user']['username']);
        $this->assertEquals('test@example.com', $result['user']['email']);
        $this->assertEquals(1, $result['user']['status']);
    }

    #[Test]
    public function it_should_validate_registration_data(): void
    {
        // 準備無效的測試資料
        $invalidData = [
            'username' => '', // 空的使用者名稱
            'email' => 'invalid-email', // 無效的電子郵件
            'password' => '123', // 太短的密碼
            'confirm_password' => '456', // 不匹配的確認密碼
            'user_ip' => '192.168.1.1',
        ];

        // 設定驗證器 mock 拋出驗證異常
        $this->validator->shouldReceive('addRule')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        $this->validator->shouldReceive('addMessage')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->with(Mockery::any(), Mockery::any())
            ->andThrow(new ValidationException(
                new ValidationResult(false, ['username' => ['使用者名稱不能為空']], [], []),
            ));

        // 執行測試並預期會拋出例外
        $this->expectException(ValidationException::class);
        new RegisterUserDTO($this->validator, $invalidData);
    }

    #[Test]
    public function it_should_login_user_successfully(): void
    {
        // 準備測試資料
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // 模擬資料庫中的使用者資料（已雜湊的密碼）
        $hashedPassword = password_hash('password123', PASSWORD_ARGON2ID);
        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn([
                'id' => '1',
                'uuid' => 'test-uuid',
                'email' => 'test@example.com',
                'password' => $hashedPassword,
                'status' => 1,
            ]);

        $this->userRepository->shouldReceive('updateLastLogin')
            ->once()
            ->with('1')
            ->andReturn(true);

        // 設定活動記錄 mock - 登入成功
        $this->activityLogger->shouldReceive('log')
            ->once()
            ->with(Mockery::type(CreateActivityLogDTO::class))
            ->andReturnUsing(function (CreateActivityLogDTO $activityDto) {
                $this->assertEquals(ActivityType::LOGIN_SUCCESS, $activityDto->getActionType());
                $this->assertEquals('1', $activityDto->getUserId());
                $metadata = $activityDto->getMetadata();
                $this->assertArrayHasKey('email', $metadata);
                $this->assertEquals('test@example.com', $metadata['email']);

                return true;
            });

        // 執行測試
        $result = $this->service->login($credentials, null);

        // 驗證結果
        $this->assertTrue($result['success']);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    #[Test]
    public function it_should_fail_login_with_invalid_credentials(): void
    {
        // 準備測試資料
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        // 模擬資料庫中的使用者資料
        $hashedPassword = password_hash('password123', PASSWORD_ARGON2ID);
        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn([
                'id' => '1',
                'uuid' => 'test-uuid',
                'email' => 'test@example.com',
                'password' => $hashedPassword,
                'status' => 1,
            ]);

        // 設定活動記錄 mock - 登入失敗
        $this->activityLogger->shouldReceive('log')
            ->once()
            ->with(Mockery::type(CreateActivityLogDTO::class))
            ->andReturnUsing(function (CreateActivityLogDTO $activityDto) {
                $this->assertEquals(ActivityType::LOGIN_FAILED, $activityDto->getActionType());
                $metadata = $activityDto->getMetadata();
                $this->assertArrayHasKey('email', $metadata);
                $this->assertArrayHasKey('reason', $metadata);
                $this->assertEquals('test@example.com', $metadata['email']);
                $this->assertEquals('invalid_credentials', $metadata['reason']);

                return true;
            });

        // 執行測試
        $result = $this->service->login($credentials, null);

        // 驗證結果
        $this->assertFalse($result['success']);
        $this->assertEquals('無效的認證資訊', $result['message']);
    }

    #[Test]
    public function it_should_not_login_inactive_user(): void
    {
        // 準備測試資料
        $credentials = [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ];

        // 模擬停用的使用者資料
        $hashedPassword = password_hash('password123', PASSWORD_ARGON2ID);
        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('inactive@example.com')
            ->andReturn([
                'id' => '1',
                'uuid' => 'test-uuid',
                'email' => 'inactive@example.com',
                'password' => $hashedPassword,
                'status' => 0, // 停用狀態
            ]);

        // 設定活動記錄 mock - 停用使用者嘗試登入
        $this->activityLogger->shouldReceive('log')
            ->once()
            ->with(Mockery::type(CreateActivityLogDTO::class))
            ->andReturnUsing(function (CreateActivityLogDTO $activityDto) {
                $this->assertEquals(ActivityType::LOGIN_FAILED, $activityDto->getActionType());
                $metadata = $activityDto->getMetadata();
                $this->assertArrayHasKey('email', $metadata);
                $this->assertArrayHasKey('reason', $metadata);
                $this->assertEquals('inactive@example.com', $metadata['email']);
                $this->assertEquals('account_disabled', $metadata['reason']);

                return true;
            });

        // 執行測試
        $result = $this->service->login($credentials, null);

        // 驗證結果
        $this->assertFalse($result['success']);
        $this->assertEquals('帳號已被停用', $result['message']);
    }
}
