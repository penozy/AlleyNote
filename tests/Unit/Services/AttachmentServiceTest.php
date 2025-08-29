<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domains\Attachment\Models\Attachment;
use App\Domains\Attachment\Repositories\AttachmentRepository;
use App\Domains\Attachment\Services\AttachmentService;
use App\Domains\Auth\Services\AuthorizationService;
use App\Domains\Post\Models\Post;
use App\Domains\Post\Repositories\PostRepository;
use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Infrastructure\Services\CacheService;
use App\Shared\Exceptions\ValidationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class AttachmentServiceTest extends TestCase
{
    protected AttachmentService $service;

    protected string $uploadDir;

    protected AttachmentRepository|MockInterface $attachmentRepo;

    protected PostRepository|MockInterface $postRepo;

    protected CacheService|MockInterface $attachmentCache;

    protected AuthorizationService|MockInterface $authService;

    protected ActivityLoggingServiceInterface|MockInterface $activityLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadDir = sys_get_temp_dir() . '/alleynote_test_' . uniqid();
        mkdir($this->uploadDir);
        mkdir($this->uploadDir . '/attachments', 0o755, true);

        $this->attachmentRepo = Mockery::mock(AttachmentRepository::class);
        $this->postRepo = Mockery::mock(PostRepository::class);
        $this->attachmentCache = Mockery::mock(CacheService::class);
        $this->authService = Mockery::mock(AuthorizationService::class);
        $this->activityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);

        $this->service = new AttachmentService(
            $this->attachmentRepo,
            $this->postRepo,
            $this->authService,
            $this->activityLogger,
            $this->uploadDir,
        );

        // 設置AttachmentRepository mock期望
        $this->attachmentRepo->shouldReceive('create')
            ->andReturn(new Attachment([
                'id' => 1,
                'uuid' => 'test-uuid',
                'post_id' => 1,
                'filename' => 'test.jpg',
                'original_name' => 'test.jpg',
                'file_size' => 1024,
                'mime_type' => 'image/jpeg',
                'storage_path' => '/uploads/test.jpg',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]))
            ->byDefault();

        $this->attachmentRepo->shouldReceive('findById')
            ->andReturn(null)
            ->byDefault();

        $this->attachmentRepo->shouldReceive('delete')
            ->andReturn(true)
            ->byDefault();

        // 設置 ActivityLogger mock 期望
        $this->activityLogger->shouldReceive('logSuccess')
            ->byDefault();

        $this->activityLogger->shouldReceive('logFailure')
            ->byDefault();
    }

    #[Test]
    public function shouldUploadFileSuccessfully(): void
    {
        // 準備測試資料
        $postId = 1;
        $testFilename = $this->uploadDir . '/attachments/test.jpg';

        $file = $this->createUploadedFileMock(
            'test.jpg',
            'image/jpeg',
            1024,
            UPLOAD_ERR_OK,
        );

        // 模擬權限檢查 - 非管理員但是文章擁有者
        $this->authService->shouldReceive('isSuperAdmin')
            ->once()
            ->with(1)
            ->andReturn(false);

        // 模擬文章存在且用戶是文章擁有者
        $post = Mockery::mock(Post::class);
        $post->shouldReceive('getId')->andReturn($postId);
        $post->shouldReceive('getUserId')->andReturn(1); // 文章擁有者是 userId = 1

        $this->postRepo->shouldReceive('find')
            ->once()
            ->with($postId)
            ->andReturn($post);

        // 模擬檔案上傳
        $file->shouldReceive('moveTo')
            ->andReturnUsing(function ($path) {
                // 實際建立檔案並設定權限
                file_put_contents($path, 'test content');
                chmod($path, 0o644);

                return null;
            });

        // 模擬附件建立
        $this->attachmentRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'post_id' => $postId,
                'original_name' => 'test.jpg',
                'filename' => Mockery::any(),
                'file_size' => Mockery::any(),
                'mime_type' => Mockery::any(),
                'storage_path' => Mockery::any(),
            ]))
            ->andReturn(Mockery::mock('App\Domains\Attachment\Models\Attachment'));

        // 執行測試
        $result = $this->service->upload($postId, $file, 1); // userId = 1

        // 驗證結果
        $this->assertNotNull($result);
    }

    #[Test]
    public function shouldRejectInvalidFileType(): void
    {
        // 準備測試資料
        $postId = 1;
        $file = $this->createUploadedFileMock(
            'test.exe',
            'application/x-msdownload',
            1024,
            UPLOAD_ERR_OK,
        );

        // 模擬權限檢查 - 非管理員但是文章擁有者
        $this->authService->shouldReceive('isSuperAdmin')
            ->once()
            ->with(1)
            ->andReturn(false);

        // 模擬文章存在且用戶是文章擁有者
        $post = Mockery::mock(Post::class);
        $post->shouldReceive('getId')->andReturn($postId);
        $post->shouldReceive('getUserId')->andReturn(1); // 文章擁有者是 userId = 1

        $this->postRepo->shouldReceive('find')
            ->once()
            ->with($postId)
            ->andReturn($post);

        // 預期會拋出例外
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('不支援的檔案類型');

        // 執行測試
        $this->service->upload($postId, $file, 1); // userId = 1
    }

    #[Test]
    public function shouldRejectOversizedFile(): void
    {
        // 準備測試資料
        $postId = 1;
        $file = $this->createUploadedFileMock(
            'test.jpg',
            'image/jpeg',
            11 * 1024 * 1024, // 11MB，超過 10MB 限制
            UPLOAD_ERR_OK,
        );

        // 模擬權限檢查 - 非管理員但是文章擁有者
        $this->authService->shouldReceive('isSuperAdmin')
            ->once()
            ->with(1)
            ->andReturn(false);

        // 模擬文章存在且用戶是文章擁有者
        $post = Mockery::mock(Post::class);
        $post->shouldReceive('getId')->andReturn($postId);
        $post->shouldReceive('getUserId')->andReturn(1); // 文章擁有者是 userId = 1

        $this->postRepo->shouldReceive('find')
            ->once()
            ->with($postId)
            ->andReturn($post);

        // 預期會拋出例外
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('檔案大小超過限制（10MB）');

        // 執行測試
        $this->service->upload($postId, $file, 1); // userId = 1
    }

    #[Test]
    public function shouldRejectUploadToNonExistentPost(): void
    {
        // 準備測試資料
        $postId = 999;
        $file = $this->createUploadedFileMock(
            'test.jpg',
            'image/jpeg',
            1024,
            UPLOAD_ERR_OK,
        );

        // 模擬權限檢查 - 先檢查是否為管理員
        $this->authService->shouldReceive('isSuperAdmin')
            ->once()
            ->with(1)
            ->andReturn(false);

        // 模擬文章不存在
        $this->postRepo->shouldReceive('find')
            ->once()
            ->with($postId)
            ->andReturn(null);

        // 預期會拋出例外
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('無權限上傳附件到此公告');

        // 執行測試
        $this->service->upload($postId, $file, 1); // userId = 1
    }

    private function createUploadedFileMock(
        string $filename,
        string $mimeType,
        int $size,
        int $error,
    ): UploadedFileInterface {
        $stream = Mockery::mock('Psr\Http\Message\StreamInterface');
        $stream->shouldReceive('getContents')->andReturn('test content');
        $stream->shouldReceive('rewind')->andReturnNull();

        $file = Mockery::mock(UploadedFileInterface::class);
        $file->shouldReceive('getClientFilename')->andReturn($filename);
        $file->shouldReceive('getClientMediaType')->andReturn($mimeType);
        $file->shouldReceive('getSize')->andReturn($size);
        $file->shouldReceive('getError')->andReturn($error);
        $file->shouldReceive('moveTo')->andReturnUsing(function ($path) use ($mimeType) {
            // 建立實際檔案以便 finfo 可以檢測 MIME 類型
            if ($mimeType === 'image/jpeg') {
                // 建立一個有效的最小 JPEG 檔案 (1x1 像素)
                $validJpegData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxAPwA/wA==');
                file_put_contents($path, $validJpegData);
            } else {
                // 對於其他類型，建立簡單的文字檔案
                file_put_contents($path, 'test content');
            }
            chmod($path, 0o644);

            return null;
        });
        $file->shouldReceive('getStream')->andReturn($stream);

        return $file;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();

        // 清理測試上傳目錄
        if (is_dir($this->uploadDir)) {
            $this->recursiveRemoveDirectory($this->uploadDir);
        }
    }

    /**
     * 遞歸刪除目錄.
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
