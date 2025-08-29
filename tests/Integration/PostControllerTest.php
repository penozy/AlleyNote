<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Controllers\Api\V1\PostController;
use App\Domains\Post\Contracts\PostServiceInterface;
use App\Domains\Post\DTOs\CreatePostDTO;
use App\Domains\Post\DTOs\UpdatePostDTO;
use App\Domains\Post\Exceptions\PostNotFoundException;
use App\Domains\Post\Models\Post;
use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\CsrfProtectionServiceInterface;
use App\Domains\Security\Contracts\XssProtectionServiceInterface;
use App\Shared\Contracts\OutputSanitizerInterface;
use App\Shared\Contracts\ValidatorInterface;
use App\Shared\Exceptions\StateTransitionException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    /** @var PostServiceInterface&MockInterface */
    private PostServiceInterface $postService;

    /** @var XssProtectionServiceInterface&MockInterface */
    private XssProtectionServiceInterface $xssProtection;

    /** @var CsrfProtectionServiceInterface&MockInterface */
    private CsrfProtectionServiceInterface $csrfProtection;

    /** @var ValidatorInterface&MockInterface */
    private ValidatorInterface $validator;

    /** @var OutputSanitizerInterface&MockInterface */
    private OutputSanitizerInterface $sanitizer;

    private mixed $request;

    private mixed $response;

    private mixed $stream;

    private mixed $responseStatus;

    private mixed $currentResponseData;

    private function createController(): PostController
    {
        // Mock ActivityLoggingService
        $activityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);
        $activityLogger->shouldReceive('log')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logFailure')->zeroOrMoreTimes();

        return new PostController(
            $this->postService,
            $this->validator,
            $this->sanitizer,
            $activityLogger,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->postService = Mockery::mock(PostServiceInterface::class);
        $this->xssProtection = Mockery::mock(XssProtectionServiceInterface::class);
        $this->csrfProtection = Mockery::mock(CsrfProtectionServiceInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->sanitizer = Mockery::mock(OutputSanitizerInterface::class);

        // 設定預設行為
        $this->xssProtection->shouldReceive('cleanArray')
            ->byDefault()
            ->andReturnUsing(function ($data, $fields) {
                return $data;
            });
        $this->csrfProtection->shouldReceive('validateToken')
            ->byDefault()
            ->andReturn(true);
        $this->csrfProtection->shouldReceive('generateToken')
            ->byDefault()
            ->andReturn('new-token');

        // 設定 sanitizer 預設行為 - 返回原值
        $this->sanitizer->shouldReceive('sanitizeHtml')
            ->andReturnUsing(function ($input) {
                return $input;
            })
            ->byDefault();

        // 設定 validator 預設行為
        $this->validator->shouldReceive('validateOrFail')
            ->andReturnUsing(function ($data, $rules) {
                return $data;
            })
            ->byDefault();
        $this->validator->shouldReceive('addRule')
            ->andReturnNull()
            ->byDefault();
        $this->validator->shouldReceive('addMessage')
            ->andReturnNull()
            ->byDefault();

        // 先建立 stream
        $this->stream = $this->createStreamMock();
        // 再建立 response
        $this->response = $this->createResponseMock();
        // 最後建立 request
        $this->request = $this->createRequestMock();
    }

    #[Test]
    public function indexShouldReturnPaginatedPosts(): void
    {
        // 準備測試資料
        $filters = ['status' => 'published'];
        $expectedData = [];
        $expectedPagination = [
            'total' => 0,
            'page' => 1,
            'per_page' => 10,
            'total_pages' => 0,
        ];

        // 設定請求參數
        $this->request->shouldReceive('getQueryParams')
            ->once()
            ->andReturn(['page' => 1, 'per_page' => 10, 'status' => 'published']);

        // 設定服務層期望行為
        $serviceResult = [
            'items' => $expectedData,
            'total' => 0,
            'page' => 1,
            'per_page' => 10,
        ];
        $this->postService->shouldReceive('listPosts')
            ->once()
            ->with(1, 10, Mockery::subset(['status' => 'published']))
            ->andReturn($serviceResult);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->index($this->request, $this->response);

        // 驗證結果
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->currentResponseData['success']);
        $this->assertEquals($expectedData, $this->currentResponseData['data']);
        $this->assertEquals($expectedPagination, $this->currentResponseData['pagination']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function showShouldReturnPostDetails(): void
    {
        // 準備測試資料
        $postId = 1;
        $postData = [
            'id' => $postId,
            'title' => '測試文章',
            'content' => '測試內容',
            'is_pinned' => false,
        ];
        $post = new Post($postData);

        // 設定請求參數和屬性
        $this->request->shouldReceive('getAttribute')
            ->with('ip_address')
            ->andReturn('127.0.0.1');
        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // 設定服務層期望行為
        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andReturn($post);
        $this->postService->shouldReceive('recordView')
            ->once()
            ->with($postId, '203.0.113.1')
            ->andReturn(true);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->show($this->request, $this->response, ['id' => $postId]);

        // 驗證結果
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->currentResponseData['success']);
        $this->assertEquals('成功取得貼文', $this->currentResponseData['message']);
        $this->assertEquals($post->toSafeArray($this->sanitizer), $this->currentResponseData['data']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function storeShouldCreateNewPost(): void
    {
        // 準備測試資料
        $postData = [
            'title' => '新文章',
            'content' => '文章內容',
            'is_pinned' => false,
        ];
        $createdPost = new Post($postData + ['id' => 1]);

        // 設定請求資料
        $requestBody = json_encode($postData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定服務層期望行為
        $this->postService->shouldReceive('createPost')
            ->once()
            ->with(Mockery::type(CreatePostDTO::class))
            ->andReturn($createdPost);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->store($this->request, $this->response);

        // 驗證結果
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($this->currentResponseData['success']);
        $this->assertEquals('貼文建立成功', $this->currentResponseData['message']);
        $this->assertEquals($createdPost->toSafeArray($this->sanitizer), $this->currentResponseData['data']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function storeShouldReturn400WhenValidationFails(): void
    {
        // 準備測試資料
        $invalidData = ['title' => ''];

        // 設定請求資料
        $requestBody = json_encode($invalidData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定驗證器拋出異常（DTO 建立時就會失敗）
        $this->validator->shouldReceive('validateOrFail')
            ->andThrow(new ValidationException(
                ValidationResult::failure(['title' => ['標題不能為空']]),
            ));

        // PostService 不應該被調用，因為 DTO 建立會先失敗
        $this->postService->shouldNotReceive('createPost');

        // 執行測試
        $controller = $this->createController();
        $response = $controller->store($this->request, $this->response);

        // 驗證結果
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($this->currentResponseData['success']);
        $this->assertEquals('標題不能為空', $this->currentResponseData['message']);
        $this->assertEquals(400, $this->currentResponseData['error_code']);
        $this->assertEquals(['title' => ['標題不能為空']], $this->currentResponseData['errors']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function updateShouldModifyExistingPost(): void
    {
        // 準備測試資料
        $postId = 1;
        $updateData = [
            'title' => '更新的標題',
            'content' => '更新的內容',
            'is_pinned' => false,
        ];
        $updatedPost = new Post($updateData + ['id' => $postId]);

        // 設定請求資料
        $requestBody = json_encode($updateData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定服務層期望行為
        $this->postService->shouldReceive('updatePost')
            ->once()
            ->with($postId, Mockery::type(UpdatePostDTO::class))
            ->andReturn($updatedPost);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->update($this->request, $this->response, ['id' => $postId]);

        // 驗證結果
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->currentResponseData['success']);
        $this->assertEquals('貼文更新成功', $this->currentResponseData['message']);
        $this->assertEquals($updatedPost->toSafeArray($this->sanitizer), $this->currentResponseData['data']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function updateShouldReturn404WhenPostNotFound(): void
    {
        // 準備測試資料
        $postId = 999;
        $updateData = ['title' => '更新的標題'];

        // 設定請求資料
        $requestBody = json_encode($updateData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定服務層期望行為
        $this->postService->shouldReceive('updatePost')
            ->once()
            ->with($postId, Mockery::type(UpdatePostDTO::class))
            ->andThrow(new PostNotFoundException($postId));

        // 預期的回應設定
        $this->response->shouldReceive('getStatusCode')
            ->andReturn(404);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->update($this->request, $this->response, ['id' => $postId]);

        // 驗證結果
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($this->currentResponseData['success']);
        $this->assertEquals("找不到 ID 為 {$postId} 的貼文", $this->currentResponseData['message']);
        $this->assertEquals(404, $this->currentResponseData['error_code']);
        $this->assertArrayHasKey('timestamp', $this->currentResponseData);
    }

    #[Test]
    public function destroyShouldDeletePost(): void
    {
        // 準備測試資料
        $postId = 1;

        // Mock CSRF token
        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        // Mock user_id attribute
        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // Mock server params for IP address
        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        // Mock Post 物件
        $mockPost = Mockery::mock(Post::class);
        $mockPost->shouldReceive('getTitle')->andReturn('Test Post Title');
        $mockPost->shouldReceive('getStatus')->andReturn('published');

        // 設定服務層期望行為
        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andReturn($mockPost);

        $this->postService->shouldReceive('deletePost')
            ->once()
            ->with($postId)
            ->andReturn(true);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->delete($this->request, $this->response, ['id' => '1']);

        // 驗證結果
        $this->assertEquals(204, $response->getStatusCode());
    }

    #[Test]
    public function updatePinStatusShouldUpdatePinStatus(): void
    {
        // 準備測試資料
        $postId = 1;
        $pinData = ['pinned' => true];
        $post = new Post(['id' => $postId, 'title' => '測試文章', 'content' => '內容']);

        // 設定請求資料
        $requestBody = json_encode($pinData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定服務層期望行為
        $this->postService->shouldReceive('setPinned')
            ->once()
            ->with($postId, true)
            ->andReturn(true);
        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andReturn($post);

        // 執行測試
        $controller = $this->createController();
        $response = $controller->togglePin($this->request, $this->response, ['id' => '1']);

        // 驗證結果
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->currentResponseData['success']);
        $this->assertEquals('貼文已設為置頂', $this->currentResponseData['message']);
    }

    #[Test]
    public function updatePinStatusShouldReturn422WhenInvalidStateTransition(): void
    {
        // 準備測試資料
        $postId = 1;
        $pinData = ['pinned' => true];

        // 設定請求資料
        $requestBody = json_encode($pinData);
        $requestStream = Mockery::mock(StreamInterface::class);
        $requestStream->shouldReceive('getContents')->andReturn($requestBody);
        $this->request->shouldReceive('getBody')->andReturn($requestStream);

        // 設定服務層期望行為
        $this->postService->shouldReceive('setPinned')
            ->once()
            ->with($postId, true)
            ->andThrow(new StateTransitionException('無效的狀態轉換'));

        // 執行測試
        $controller = $this->createController();
        $response = $controller->togglePin($this->request, $this->response, ['id' => '1']);

        // 驗證結果
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($this->currentResponseData['success']);
        $this->assertEquals('無效的狀態轉換', $this->currentResponseData['message']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    private function createRequestMock()
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token')
            ->byDefault();

        $request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '203.0.113.1'])
            ->byDefault();

        $request->shouldReceive('getBody')
            ->andReturn($this->stream)
            ->byDefault();

        $request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1)
            ->byDefault();

        return $request;
    }

    private function createStreamMock()
    {
        $stream = Mockery::mock(StreamInterface::class);
        $this->currentResponseData = null;
        $stream->shouldReceive('write')
            ->andReturnUsing(function ($content) use ($stream) {
                $this->currentResponseData = json_decode($content, true);

                return $stream;
            });
        $stream->shouldReceive('getContents')
            ->andReturnUsing(function () {
                return json_encode($this->currentResponseData);
            });

        return $stream;
    }

    /**
     * @return ResponseInterface&MockInterface
     */
    protected function createResponseMock(): ResponseInterface
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')
            ->andReturnSelf();
        $response->shouldReceive('withStatus')
            ->andReturnUsing(function ($status) use ($response) {
                $this->responseStatus = $status;

                return $response;
            });
        $response->shouldReceive('getStatusCode')
            ->andReturnUsing(function () {
                return $this->responseStatus ?? 200;
            });
        $response->shouldReceive('getBody')
            ->andReturn($this->stream);

        return $response;
    }
}
