<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Controllers\Api\V1\PostController;
use App\Domains\Post\Contracts\PostServiceInterface;
use App\Domains\Post\Models\Post;
use App\Domains\Security\Contracts\CsrfProtectionServiceInterface;
use App\Domains\Security\Contracts\XssProtectionServiceInterface;
use App\Shared\Exceptions\NotFoundException;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    private PostServiceInterface $postService;

    private XssProtectionServiceInterface $xssProtection;

    private CsrfProtectionServiceInterface $csrfProtection;

    private ServerRequestInterface $request;

    private ResponseInterface $response;

    private StreamInterface $stream;

    private int $statusCode = 200;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock services
        $this->postService = Mockery::mock(PostServiceInterface::class);
        $this->xssProtection = Mockery::mock(XssProtectionServiceInterface::class);
        $this->csrfProtection = Mockery::mock(CsrfProtectionServiceInterface::class);

        // Set default behaviors
        $this->xssProtection->shouldReceive('cleanArray')
            ->byDefault()
            ->andReturnUsing(function ($data, $fields) {
                return $data;
                // 設定預設的 user_id 屬性
                $this->request->shouldReceive('getAttribute')
                    ->with('user_id')
                    ->andReturn(1)
                    ->byDefault();
            });

        $this->csrfProtection->shouldReceive('validateToken')
            ->byDefault()
            ->andReturn(true);

        $this->csrfProtection->shouldReceive('generateToken')
            ->byDefault()
            ->andReturn('test-csrf-token');

        // Mock Request
        $this->request = Mockery::mock(ServerRequestInterface::class);

        // Mock Response with status tracking
        $this->response = Mockery::mock(ResponseInterface::class);
        $this->response->shouldReceive('withStatus')->andReturnUsing(function ($code) {
            $this->statusCode = $code;

            return $this->response;
        });
        $this->response->shouldReceive('getStatusCode')->andReturnUsing(function () {
            return $this->statusCode;
        });
        $this->response->shouldReceive('withHeader')->andReturnSelf();

        // Mock Stream
        $this->stream = Mockery::mock(StreamInterface::class);
        $this->stream->shouldReceive('write')->andReturn(0);
        $this->stream->shouldReceive('__toString')->andReturn('{"success": true}');
        $this->response->shouldReceive('getBody')->andReturn($this->stream);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function indexShouldReturnPaginatedPosts(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Setup expectations
        $expectedData = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'perPage' => 10,
            'lastPage' => 1,
        ];

        $this->request->shouldReceive('getQueryParams')->andReturn([
            'page' => '1',
            'per_page' => '10',
        ]);

        $this->postService->shouldReceive('getPaginated')
            ->once()
            ->with(1, 10, [])
            ->andReturn($expectedData);

        // Execute
        $activityLogger = Mockery::mock(\App\Domains\Security\Contracts\ActivityLoggingServiceInterface::class);
        $validator = Mockery::mock(\App\Shared\Contracts\ValidatorInterface::class);
        $sanitizer = Mockery::mock(\App\Shared\Contracts\OutputSanitizerInterface::class);
        
        $controller = new PostController($this->postService, $validator, $sanitizer, $activityLogger);
        $response = $controller->index($this->request, $this->response);

        // Verify
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function showShouldReturnPostDetails(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $post = new Post([
            'id' => 1,
            'uuid' => 'test-uuid',
            'title' => 'Test Post',
            'content' => 'Test Content',
        ]);

        $this->postService->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($post);

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->show($this->request, $this->response, ['id' => '1']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function storeShouldCreateNewPost(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $postData = [
            'title' => 'New Post',
            'content' => 'New Content',
            'status' => 'draft',
        ];

        $this->request->shouldReceive('getParsedBody')->andReturn($postData);

        $createdPost = new Post([
            'id' => 1,
            'uuid' => 'new-uuid',
            'title' => 'New Post',
            'content' => 'New Content',
        ]);

        $this->postService->shouldReceive('create')
            ->once()
            ->with($postData)
            ->andReturn($createdPost);

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->store($this->request, $this->response);

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function storeShouldReturn400WhenValidationFails(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $invalidData = [
            'title' => '', // Empty title
            'content' => '',  // Empty content
        ];

        $this->request->shouldReceive('getParsedBody')->andReturn($invalidData);

        $this->postService->shouldReceive('create')
            ->once()
            ->with($invalidData)
            ->andThrow(new InvalidArgumentException('Validation failed'));

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->store($this->request, $this->response);

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function updateShouldModifyExistingPost(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $updateData = [
            'title' => 'Updated Post',
            'content' => 'Updated Content',
        ];

        $this->request->shouldReceive('getParsedBody')->andReturn($updateData);

        $this->postService->shouldReceive('update')
            ->once()
            ->with(1, $updateData)
            ->andReturn(true);

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->update($this->request, $this->response, ['id' => '1']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function updateShouldReturn404WhenPostNotFound(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $updateData = [
            'title' => 'Updated Post',
            'content' => 'Updated Content',
        ];

        $this->request->shouldReceive('getParsedBody')->andReturn($updateData);

        $this->postService->shouldReceive('update')
            ->once()
            ->with(999, $updateData)
            ->andThrow(new NotFoundException('Post not found'));

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->update($this->request, $this->response, ['id' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function destroyShouldDeletePost(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $this->postService->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->destroy($this->request, $this->response, ['id' => '1']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function destroyShouldReturn404WhenPostNotFound(): void
    {
        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        // Mock user_id attribute

        $this->request->shouldReceive('getAttribute')

            ->with('user_id')

            ->andReturn(1);

        $this->postService->shouldReceive('delete')
            ->once()
            ->with(999)
            ->andThrow(new NotFoundException('Post not found'));

        $controller = new PostController($this->postService, $this->xssProtection, $this->csrfProtection);
        $response = $controller->destroy($this->request, $this->response, ['id' => '999']);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
