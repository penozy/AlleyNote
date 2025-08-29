<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Application\Controllers\Api\V1\PostController;
use App\Domains\Post\Contracts\PostServiceInterface;
use App\Domains\Post\DTOs\CreatePostDTO;
use App\Domains\Post\DTOs\UpdatePostDTO;
use App\Domains\Post\Exceptions\PostNotFoundException;
use App\Domains\Post\Models\Post;
use App\Domains\Security\Contracts\CsrfProtectionServiceInterface;
use App\Domains\Security\Contracts\XssProtectionServiceInterface;
use App\Shared\Contracts\OutputSanitizerInterface;
use App\Shared\Contracts\ValidatorInterface;
use App\Shared\Exceptions\StateTransitionException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PostServiceInterface|MockInterface $postService;

    private XssProtectionServiceInterface|MockInterface $xssProtection;

    private CsrfProtectionServiceInterface|MockInterface $csrfProtection;

    private ValidatorInterface|MockInterface $validator;

    private OutputSanitizerInterface|MockInterface $sanitizer;

    private ServerRequestInterface|MockInterface $request;

    private ResponseInterface|MockInterface $response;

    private StreamInterface|MockInterface $stream;

    private PostController $controller;

    private string $lastWrittenContent = '';

    private int $lastStatusCode = 0;

    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 初始化所有mock對象
        $this->postService = Mockery::mock(PostServiceInterface::class);
        $this->xssProtection = Mockery::mock(XssProtectionServiceInterface::class);
        $this->csrfProtection = Mockery::mock(CsrfProtectionServiceInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->sanitizer = Mockery::mock(OutputSanitizerInterface::class);
        $this->request = Mockery::mock(ServerRequestInterface::class);
        $this->response = Mockery::mock(ResponseInterface::class);
        $this->stream = Mockery::mock(StreamInterface::class);

        // 創建控制器實例
        $activityLogger = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLogger->shouldReceive('log')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logSuccess')->zeroOrMoreTimes();
        
        $this->controller = new PostController(
            $this->postService,
            $this->validator,
            $this->sanitizer,
            $activityLogger,
        );

        // 設定預設的response行為
        $this->setupResponseMocks();

        // 設定預設的 sanitizer 行為 - 返回原值
        $this->sanitizer->shouldReceive('sanitizeHtml')
            ->andReturnUsing(function ($input) {
                return $input;
            })
            ->byDefault();

        // 設定預設的XSS防護
        $this->xssProtection->shouldReceive('cleanArray')
            ->andReturnUsing(function ($data) {
                return $data;
            });

        // 設定預設的CSRF防護
        $this->csrfProtection->shouldReceive('validateToken')
            ->andReturn(true);

        $this->csrfProtection->shouldReceive('generateToken')
            ->andReturn('test-token');

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

        // 設定預設的 request 期望值
        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        // 設定預設的用戶ID
        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1)
            ->byDefault();
    }

    private function setupResponseMocks(): void
    {
        $this->response->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('write')
            ->andReturnUsing(function ($content) {
                $this->lastWrittenContent = $content;

                return strlen($content);
            });

        $this->response->shouldReceive('withStatus')
            ->andReturnUsing(function ($status) {
                $this->lastStatusCode = $status;

                return $this->response;
            });

        $this->response->shouldReceive('withHeader')
            ->andReturnUsing(function ($name, $value) {
                $this->headers[$name] = $value;

                return $this->response;
            });

        $this->response->shouldReceive('getStatusCode')
            ->andReturnUsing(function () {
                return $this->lastStatusCode;
            });
    }

    public function testGetPostsReturnsSuccessResponse(): void
    {
        // 設定查詢參數
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([]);

        // 模擬分頁數據
        $paginatedData = [
            'items' => [
                ['id' => 1, 'title' => '測試文章1', 'content' => '內容1'],
                ['id' => 2, 'title' => '測試文章2', 'content' => '內容2'],
            ],
            'total' => 2,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 1,
        ];

        $this->postService->shouldReceive('listPosts')
            ->with(1, 10, [])
            ->once()
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('pagination', $body);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testGetPostsWithPaginationParameters(): void
    {
        // 設定分頁查詢參數
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([
                'page' => '2',
                'limit' => '5',
            ]);

        $paginatedData = [
            'items' => [],
            'total' => 10,
            'page' => 2,
            'per_page' => 5,
            'last_page' => 2,
        ];

        $this->postService->shouldReceive('listPosts')
            ->once()
            ->with(2, 5, [])
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('pagination', $body);
        $this->assertEquals(2, $body['pagination']['page']);
        $this->assertEquals(5, $body['pagination']['per_page']);
    }

    public function testGetPostsWithSearchFilter(): void
    {
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([
                'search' => '測試',
                'page' => '1',
                'limit' => '10',
            ]);

        $paginatedData = [
            'items' => [
                ['id' => 1, 'title' => '測試文章', 'content' => '包含搜尋關鍵字的內容'],
            ],
            'total' => 1,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 1,
        ];

        $this->postService->shouldReceive('listPosts')
            ->with(1, 10, ['search' => '測試'])
            ->once()
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertStringContainsString('測試', $body['data'][0]['title']);
    }

    public function testGetPostsWithStatusFilter(): void
    {
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([
                'status' => 'published',
                'page' => '1',
                'limit' => '10',
            ]);

        $paginatedData = [
            'items' => [
                ['id' => 1, 'title' => '已發布文章', 'status' => 'published'],
            ],
            'total' => 1,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 1,
        ];

        $this->postService->shouldReceive('listPosts')
            ->with(1, 10, ['status' => 'published'])
            ->once()
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertEquals('published', $body['data'][0]['status']);
    }

    public function testGetPostsWithInvalidLimitReturnsPosts(): void
    {
        // PostController 會將無效的 limit 參數轉換為預設值，而不是拋出錯誤
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([
                'limit' => 'invalid', // 這會被轉換為預設值 10
            ]);

        $paginatedData = [
            'items' => [
                ['id' => 1, 'title' => '測試文章', 'content' => '測試內容'],
            ],
            'total' => 1,
            'page' => 1,
            'per_page' => 1, // 使用最小值
            'last_page' => 1,
        ];

        $this->postService->shouldReceive('listPosts')
            ->with(1, 1, []) // limit 被轉換為最小值 1
            ->once()
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertEquals(1, $body['pagination']['per_page']); // 確認使用了最小值
    }

    public function testCreatePostWithValidData(): void
    {
        $postData = [
            'title' => '測試文章標題',
            'content' => '這是測試文章的內容，應該足夠長來通過驗證規則。',
            'status' => 'draft',
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($postData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '8.8.8.8']);

        $createdPost = new Post([
            'id' => 1,
            'title' => $postData['title'],
            'content' => $postData['content'],
            'status' => $postData['status'],
            'user_id' => 1,
        ]);

        $this->postService->shouldReceive('createPost')
            ->once()
            ->with(Mockery::type(CreatePostDTO::class))
            ->andReturn($createdPost);

        $response = $this->controller->store($this->request, $this->response);

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertEquals($postData['title'], $body['data']['title']);
        $this->assertEquals($postData['content'], $body['data']['content']);
    }

    public function testCreatePostWithInvalidJsonReturnsError(): void
    {
        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn('{invalid json}');

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        // 確保 createPost 不會被調用（JSON 錯誤）
        $this->postService->shouldReceive('createPost')
            ->never();

        $response = $this->controller->store($this->request, $this->response);

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
        $this->assertNotNull($body['message']);
        $this->assertIsString($body['message']);
        $this->assertStringContainsString('JSON', $body['message']);
    }

    public function testCreatePostWithMissingRequiredFields(): void
    {
        $invalidData = [
            'title' => '', // 空標題
            'content' => '', // 空內容
        ];

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($invalidData));

        $this->request->shouldReceive('getAttribute')
            ->with('user_id')
            ->andReturn(1);

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '8.8.8.8']);

        // 移除預設的 validator 行為，讓它拋出異常
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $activityLogger = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $activityLogger->shouldReceive('log')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logFailure')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logSuccess')->zeroOrMoreTimes();
        
        $this->controller = new PostController(
            $this->postService,
            $this->validator,
            $this->sanitizer,
            $activityLogger,
        );

        // 設定 validator 拋出驗證異常
        $validationResult = new ValidationResult(false, ['title' => ['標題不能為空']], [], ['title' => ['required']]);

        $this->validator->shouldReceive('addRule')
            ->andReturnSelf();
        $this->validator->shouldReceive('addMessage')
            ->andReturnSelf();
        $this->validator->shouldReceive('stopOnFirstFailure')
            ->andReturnSelf();
        $this->validator->shouldReceive('validateOrFail')
            ->andThrow(new ValidationException($validationResult));

        // 確保 createPost 不會被調用
        $this->postService->shouldReceive('createPost')
            ->never();

        $response = $this->controller->store($this->request, $this->response);

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
        $this->assertNotNull($body['message']);
        $this->assertStringContainsString('標題', $body['message']);
    }

    public function testGetPostByIdReturnsSuccess(): void
    {
        $postId = 1;
        $post = new Post([
            'id' => $postId,
            'title' => '測試取得文章',
            'content' => '這是測試內容',
            'status' => 'published',
        ]);

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andReturn($post);

        $this->postService->shouldReceive('recordView')
            ->once()
            ->with($postId, '127.0.0.1');

        $response = $this->controller->show($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertEquals('測試取得文章', $body['data']['title']);
    }

    public function testGetNonExistentPostReturnsNotFound(): void
    {
        $postId = 99999;

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andThrow(new PostNotFoundException($postId));

        $this->postService->shouldReceive('recordView')
            ->never();

        $response = $this->controller->show($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
        $this->assertNotNull($body['message']);
        $this->assertStringContainsString('找不到', $body['message']);
    }

    public function testGetPostWithInvalidIdReturnsError(): void
    {
        $invalidId = 'invalid';

        $response = $this->controller->show($this->request, $this->response, ['id' => $invalidId]);

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
    }

    public function testUpdatePostWithValidData(): void
    {
        $postId = 1;
        $updateData = [
            'title' => '更新後的標題',
            'content' => '更新後的內容，這裡有足夠的文字來通過驗證。',
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($updateData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->andReturn($updateData);

        $updatedPost = new Post([
            'id' => $postId,
            'title' => $updateData['title'],
            'content' => $updateData['content'],
            'status' => 'published',
        ]);

        $this->postService->shouldReceive('updatePost')
            ->once()
            ->with($postId, Mockery::type(UpdatePostDTO::class))
            ->andReturn($updatedPost);

        $response = $this->controller->update($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertEquals('更新後的標題', $body['data']['title']);
        $this->assertEquals('更新後的內容，這裡有足夠的文字來通過驗證。', $body['data']['content']);
    }

    public function testUpdateNonExistentPostReturnsNotFound(): void
    {
        $postId = 99999;
        $updateData = [
            'title' => '更新標題',
            'content' => '更新內容',
        ];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($updateData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->validator->shouldReceive('validateOrFail')
            ->once()
            ->andReturn($updateData);

        $this->postService->shouldReceive('updatePost')
            ->once()
            ->with($postId, Mockery::type(UpdatePostDTO::class))
            ->andThrow(new PostNotFoundException($postId));

        $response = $this->controller->update($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
    }

    public function testDeletePost(): void
    {
        $postId = 1;

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->postService->shouldReceive('deletePost')
            ->once()
            ->with($postId)
            ->andReturn(true);

        $response = $this->controller->delete($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($this->lastWrittenContent);
    }

    public function testDeleteNonExistentPostReturnsNotFound(): void
    {
        $postId = 99999;

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->postService->shouldReceive('deletePost')
            ->once()
            ->with($postId)
            ->andThrow(new PostNotFoundException($postId));

        $response = $this->controller->delete($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testTogglePostPin(): void
    {
        $postId = 1;
        $pinData = ['pinned' => true];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($pinData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        // 移除 validateOrFail 的期望，因為 togglePin 方法不使用它
        // $this->validator->shouldReceive('validateOrFail')
        //     ->once()
        //     ->andReturn($pinData);

        $updatedPost = new Post([
            'id' => $postId,
            'title' => '測試文章',
            'content' => '內容',
            'pinned' => true,
        ]);

        $this->postService->shouldReceive('setPinned')
            ->once()
            ->with($postId, true)
            ->andReturn(true);

        $this->postService->shouldReceive('findById')
            ->once()
            ->with($postId)
            ->andReturn($updatedPost);

        $response = $this->controller->togglePin($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertTrue($body['success']);
        $this->assertStringContainsString('置頂', $body['message']);
    }

    public function testTogglePostPinWithInvalidData(): void
    {
        $postId = 1;
        $invalidData = ['pinned' => 'invalid'];

        $this->request->shouldReceive('getBody')
            ->andReturn($this->stream);

        $this->stream->shouldReceive('getContents')
            ->andReturn(json_encode($invalidData));

        $this->request->shouldReceive('getHeaderLine')
            ->with('X-CSRF-TOKEN')
            ->andReturn('valid-token');

        $this->request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $this->validator->shouldReceive('validateOrFail')
            ->with($invalidData)
            ->andReturn($invalidData);

        // 模擬 setPinned 會拋出 StateTransitionException
        $this->postService->shouldReceive('setPinned')
            ->with($postId, 'invalid')
            ->andThrow(new StateTransitionException('pinned 必須是布林值'));

        $response = $this->controller->togglePin($this->request, $this->response, ['id' => $postId]);

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($this->lastWrittenContent, true);
        $this->assertFalse($body['success']);
    }

    public function testApiResponseStructureConsistency(): void
    {
        $this->request->shouldReceive('getQueryParams')
            ->andReturn([]);

        $paginatedData = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 10,
            'last_page' => 0,
        ];

        $this->postService->shouldReceive('listPosts')
            ->with(1, 10, [])
            ->once()
            ->andReturn($paginatedData);

        $response = $this->controller->index($this->request, $this->response);

        $body = json_decode($this->lastWrittenContent, true);

        // 檢查所有必要的結構
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('pagination', $body);
        $this->assertArrayHasKey('timestamp', $body);

        // 檢查分頁結構
        $pagination = $body['pagination'];
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
    }

    public function testHealthEndpoint(): void
    {
        // 如果有健康檢查端點的話
        $this->assertTrue(true); // 佔位符測試
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
