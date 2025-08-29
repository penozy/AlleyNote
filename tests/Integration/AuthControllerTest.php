<?php

declare(strict_types=1);

namespace Tests\Integration;

use AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface;
use AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface;
use App\Application\Controllers\Api\V1\AuthController;
use App\Domains\Auth\DTOs\RegisterUserDTO;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Shared\Contracts\ValidatorInterface;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

#[Group('integration')]
class AuthControllerTest extends TestCase
{
    private AuthService|MockInterface $authService;

    private AuthenticationServiceInterface|MockInterface $authenticationService;

    private JwtTokenServiceInterface|MockInterface $jwtTokenService;

    private ValidatorInterface|MockInterface $validator;

    private ActivityLoggingServiceInterface|MockInterface $activityLoggingService;

    private ServerRequestInterface|MockInterface $request;

    private ResponseInterface|MockInterface $response;

    private int $statusCode = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = Mockery::mock(AuthService::class);
        $this->authenticationService = Mockery::mock(AuthenticationServiceInterface::class);
        $this->jwtTokenService = Mockery::mock(JwtTokenServiceInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->activityLoggingService = Mockery::mock(ActivityLoggingServiceInterface::class);

        // 設置 Request Mock
        $this->request = Mockery::mock(ServerRequestInterface::class);

        // 設置 Response Mock - 使用動態狀態碼追蹤
        $this->response = Mockery::mock(ResponseInterface::class);
        $this->statusCode = 200;

        $this->response->shouldReceive('withStatus')->andReturnUsing(function ($code) {
            $this->statusCode = $code;

            return $this->response;
        });

        // 設定預設的 user_id 屬性
        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1)
            ->byDefault();

        // 設定 validator 預設行為
        $this->validator->shouldReceive('validateOrFail')
            ->andReturnUsing(function ($data, $rules) {
                return $data; // 返回原始數據作為驗證通過的數據
            })
            ->byDefault();

        $this->validator->shouldReceive('addRule')
            ->andReturnNull()
            ->byDefault();

        $this->validator->shouldReceive('addMessage')
            ->andReturnNull()
            ->byDefault();

        $this->validator->shouldReceive('stopOnFirstFailure')
            ->andReturn($this->validator)
            ->byDefault();

        $this->response->shouldReceive('getStatusCode')->andReturnUsing(function () {
            return $this->statusCode;
        });

        $this->response->shouldReceive('withHeader')->andReturnSelf();

        $stream = Mockery::mock(StreamInterface::class);
        $writtenContent = '';
        $stream->shouldReceive('write')->andReturnUsing(function ($content) use (&$writtenContent) {
            $writtenContent .= $content;

            return strlen($content);
        });
        $stream->shouldReceive('__toString')->andReturnUsing(function () use (&$writtenContent) {
            return $writtenContent;
        });
        $this->response->shouldReceive('getBody')->andReturn($stream);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    #[Test]
    public function registerUserSuccessfully(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'user_ip' => '192.168.1.1',
        ];

        // 設定 Mock 期望和請求數據
        $this->request->shouldReceive('getParsedBody')->andReturn($userData);

        $this->authService->shouldReceive('register')
            ->once()
            ->with(Mockery::type(RegisterUserDTO::class))
            ->andReturn([
                'id' => 1,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'status' => 1,
            ]);

        // 建立控制器並執行
        $authenticationService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface::class);
        $jwtTokenService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface::class);
        $activityLoggingService = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLoggingService->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('log')->zeroOrMoreTimes();

        $controller = new AuthController(
            $this->authService,
            $authenticationService,
            $jwtTokenService,
            $this->validator,
            $activityLoggingService
        );
        $response = $controller->register($this->request, $this->response);

        // 驗證回應
        $this->assertEquals(201, $response->getStatusCode()); // 成功註冊狀態碼
        $responseBody = (string) $response->getBody();
        $responseData = json_decode($responseBody, true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('註冊成功', $responseData['message']);
    }

    #[Test]
    public function returnValidationErrorsForInvalidRegistrationData(): void
    {
        $invalidData = [
            'username' => '', // 空白用戶名
            'email' => 'invalid-email', // 無效email
            'password' => '123', // 密碼太短
            'confirm_password' => '123',
            'user_ip' => '192.168.1.1',
        ];

        // 設定 Mock 期望和請求數據
        $this->request->shouldReceive('getParsedBody')->andReturn($invalidData);

        // 驗證器應該拋出驗證異常
        $this->validator->shouldReceive('validateOrFail')
            ->andThrow(new ValidationException(
                ValidationResult::failure(['username' => ['使用者名稱不能為空']]),
            ));

        // AuthService 不應該被調用，因為驗證會先失敗
        $this->authService->shouldNotReceive('register');

        // 建立控制器並執行
        $authenticationService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface::class);
        $jwtTokenService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface::class);
        $activityLoggingService = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLoggingService->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('log')->zeroOrMoreTimes();

        $controller = new AuthController(
            $this->authService,
            $authenticationService,
            $jwtTokenService,
            $this->validator,
            $activityLoggingService
        );
        $response = $controller->register($this->request, $this->response);

        // 驗證回應
        $this->assertEquals(400, $response->getStatusCode()); // 驗證失敗應該返回400
    }

    #[Test]
    public function loginUserSuccessfully(): void
    {
        $credentials = [
            'username' => 'testuser',
            'password' => 'password123',
        ];

        // 設定 Mock 期望和請求數據
        $this->request->shouldReceive('getParsedBody')->andReturn($credentials);

        $this->authService->shouldReceive('login')
            ->once()
            ->with($credentials)
            ->andReturn([
                'success' => true,
                'token' => 'fake-jwt-token',
                'user' => [
                    'id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ]);

        // 建立控制器並執行
        $authenticationService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface::class);
        $jwtTokenService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface::class);
        $activityLoggingService = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLoggingService->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('log')->zeroOrMoreTimes();

        $controller = new AuthController(
            $this->authService,
            $authenticationService,
            $jwtTokenService,
            $this->validator,
            $activityLoggingService
        );
        $response = $controller->login($this->request, $this->response);

        // 驗證回應
        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        $responseData = json_decode($responseBody, true);
        $this->assertTrue($responseData['success']);
    }

    #[Test]
    public function returnErrorForInvalidLogin(): void
    {
        $invalidCredentials = [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ];

        // 設定 Mock 期望和請求數據
        $this->request->shouldReceive('getParsedBody')->andReturn($invalidCredentials);

        $this->authService->shouldReceive('login')
            ->once()
            ->with($invalidCredentials)
            ->andThrow(new InvalidArgumentException('無效的憑證'));

        // 建立控制器並執行
        $authenticationService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface::class);
        $jwtTokenService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface::class);
        $activityLoggingService = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLoggingService->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('log')->zeroOrMoreTimes();

        $controller = new AuthController(
            $this->authService,
            $authenticationService,
            $jwtTokenService,
            $this->validator,
            $activityLoggingService
        );
        $response = $controller->login($this->request, $this->response);

        // 驗證回應 - 當 AuthService 拋出 InvalidArgumentException 時，控制器返回 400
        $this->assertTrue($response->getStatusCode() >= 400); // 接受4xx或5xx錯誤狀態碼
    }

    #[Test]
    public function logoutUserSuccessfully(): void
    {
        // logout 方法不需要調用 AuthService，直接返回成功響應

        // 建立控制器並執行
        $authenticationService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\AuthenticationServiceInterface::class);
        $jwtTokenService = Mockery::mock(\AlleyNote\Domains\Auth\Contracts\JwtTokenServiceInterface::class);
        $activityLoggingService = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLoggingService->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLoggingService->shouldReceive('log')->zeroOrMoreTimes();

        $controller = new AuthController(
            $this->authService,
            $authenticationService,
            $jwtTokenService,
            $this->validator,
            $activityLoggingService
        );
        $response = $controller->logout($this->request, $this->response);

        // 驗證回應
        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = (string) $response->getBody();
        $responseData = json_decode($responseBody, true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('登出成功', $responseData['message']);
    }
}
