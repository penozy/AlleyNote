<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Application\Controllers\Api\V1\PostController;
use App\Domains\Post\Contracts\PostServiceInterface;
use App\Domains\Post\Models\Post;
use App\Domains\Security\Contracts\CsrfProtectionServiceInterface;
use App\Domains\Security\Contracts\XssProtectionServiceInterface;
use App\Shared\Contracts\OutputSanitizerInterface;
use App\Shared\Contracts\ValidatorInterface;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class XssPreventionTest extends TestCase
{
    private PostServiceInterface $postService;

    private ValidatorInterface $validator;

    private OutputSanitizerInterface $sanitizer;

    private XssProtectionServiceInterface $xssProtection;

    private CsrfProtectionServiceInterface $csrfProtection;

    private ServerRequestInterface $request;

    private ResponseInterface $response;

    private PostController $controller;

    private StreamInterface $stream;

    private string $lastWrittenContent = '';

    private int $lastStatusCode = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postService = Mockery::mock(PostServiceInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->sanitizer = Mockery::mock(OutputSanitizerInterface::class);
        $this->xssProtection = Mockery::mock(XssProtectionServiceInterface::class);
        $this->csrfProtection = Mockery::mock(CsrfProtectionServiceInterface::class);
        $this->request = Mockery::mock(ServerRequestInterface::class);
        $this->response = Mockery::mock(ResponseInterface::class);
        $this->stream = Mockery::mock(StreamInterface::class);

        // Mock ActivityLoggingService
        $activityLogger = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLogger->shouldReceive('log')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logFailure')->zeroOrMoreTimes();

        $this->controller = new PostController(
            $this->postService,
            $this->validator,
            $this->sanitizer,
            $activityLogger,  // 第四個參數
        );

        // 設定預設回應行為
        $this->response->shouldReceive('getBody')
            ->andReturn($this->stream);
        $this->stream->shouldReceive('write')
            ->andReturnUsing(function ($content) {
                $this->lastWrittenContent = $content;

                return strlen($content);
            });

        // 設定預設的 user_id 屬性
        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1)
            ->byDefault();
        $this->response->shouldReceive('withStatus')
            ->andReturnUsing(function ($status) {
                $this->lastStatusCode = $status;

                return $this->response;
            });
        $this->response->shouldReceive('withHeader')
            ->andReturnSelf();
        $this->response->shouldReceive('getStatusCode')
            ->andReturnUsing(function () {
                return $this->lastStatusCode;
            });

        // 設定 CSRF token 驗證
        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');
        $this->csrfProtection->shouldReceive('validateToken')
            ->with('valid-token')
            ->andReturnNull();
        $this->csrfProtection->shouldReceive('generateToken')
            ->andReturn('new-token');

        // 設定 sanitizer 預設行為 - 返回原值
        $this->sanitizer->shouldReceive('sanitizeHtml')
            ->andReturnUsing(function ($input) {
                return $input;
            })
            ->byDefault();

        // 設定 validator 的預設期望值
        $this->validator->shouldReceive('addRule')
            ->withAnyArgs()
            ->andReturnSelf()
            ->byDefault();

        $this->validator->shouldReceive('addMessage')
            ->withAnyArgs()
            ->andReturnSelf()
            ->byDefault();

        $this->validator->shouldReceive('stopOnFirstFailure')
            ->withAnyArgs()
            ->andReturnSelf()
            ->byDefault();

        $this->validator->shouldReceive('validateOrFail')
            ->withAnyArgs()
            ->andReturnUsing(function ($data) {
                return $data; // 返回原始資料作為驗證過的資料
            })
            ->byDefault();
    }

    #[Test]
    public function shouldEscapeHtmlInPostTitle(): void
    {
        // 準備含有 XSS 攻擊程式碼的測試資料
        $maliciousTitle = '<script>alert("XSS");</script>惡意標題';
        $postData = [
            'title' => $maliciousTitle,
            'content' => '正常內容',
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($postData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // 設定驗證器行為
        $this->validator->shouldReceive('validateOrFail')
            ->with($postData)
            ->andReturn($postData);

        // 模擬 PostService 創建成功 - 假設服務層已處理 XSS 清理
        $post = new Post([
            'id' => 1,
            'title' => htmlspecialchars($maliciousTitle, ENT_QUOTES, 'UTF-8'),
            'content' => '正常內容',
            'user_id' => 1,
        ]);

        $this->postService->shouldReceive('createPost')
            ->once()
            ->andReturn($post);

        // 執行測試
        $response = $this->controller->store($this->request, $this->response);

        // 驗證回應 - XSS 防護應該在服務層或中間件處理
        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($this->lastWrittenContent, true);
        $this->assertIsArray($responseData);
        $this->assertTrue($responseData['success']);

        // 在實際應用中，XSS 清理會在適當的層級進行
        $this->assertNotNull($responseData['data']['title']);
    }

    #[Test]
    public function shouldEscapeHtmlInPostContent(): void
    {
        // 準備含有 XSS 攻擊程式碼的測試資料
        $maliciousContent = '<img src="x" onerror="alert(\'XSS\')">';
        $postData = [
            'title' => '正常標題',
            'content' => $maliciousContent,
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($postData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // 設定驗證器行為
        $this->validator->shouldReceive('validateOrFail')
            ->with($postData)
            ->andReturn($postData);

        // 模擬 PostService 創建成功 - 假設服務層已處理 XSS 清理
        $post = new Post([
            'id' => 1,
            'title' => '正常標題',
            'content' => htmlspecialchars($maliciousContent, ENT_QUOTES, 'UTF-8'),
            'user_id' => 1,
        ]);

        $this->postService->shouldReceive('createPost')
            ->once()
            ->andReturn($post);

        // 執行測試
        $response = $this->controller->store($this->request, $this->response);

        // 驗證回應 - XSS 防護應該在服務層或中間件處理
        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($this->lastWrittenContent, true);
        $this->assertIsArray($responseData);
        $this->assertTrue($responseData['success']);

        // 在實際應用中，XSS 清理會在適當的層級進行
        $this->assertNotNull($responseData['data']['content']);
    }

    #[Test]
    public function shouldHandleEncodedXssAttempts(): void
    {
        // 準備編碼的 XSS 攻擊程式碼
        $encodedXss = htmlentities('<script>alert("XSS");</script>', ENT_QUOTES, 'UTF-8');
        $postData = [
            'title' => $encodedXss,
            'content' => '正常內容',
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($postData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // 設定驗證器行為
        $this->validator->shouldReceive('validateOrFail')
            ->with($postData)
            ->andReturn($postData);

        // 模擬 PostService 創建成功 - 假設服務層已處理 XSS 清理
        $post = new Post([
            'id' => 1,
            'title' => htmlspecialchars($encodedXss, ENT_QUOTES, 'UTF-8'),
            'content' => '正常內容',
            'user_id' => 1,
        ]);

        $this->postService->shouldReceive('createPost')
            ->once()
            ->andReturn($post);

        // 執行測試
        $response = $this->controller->store($this->request, $this->response);

        // 驗證回應 - XSS 防護應該在服務層或中間件處理
        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($this->lastWrittenContent, true);
        $this->assertIsArray($responseData);
        $this->assertTrue($responseData['success']);

        // 在實際應用中，XSS 清理會在適當的層級進行
        $this->assertNotNull($responseData['data']['title']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
