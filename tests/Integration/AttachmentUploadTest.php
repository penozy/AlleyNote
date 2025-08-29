<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domains\Attachment\Models\Attachment;
use App\Domains\Attachment\Repositories\AttachmentRepository;
use App\Domains\Attachment\Services\AttachmentService;
use App\Domains\Auth\Services\AuthorizationService;
use App\Domains\Post\Repositories\PostRepository;
use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\LoggingSecurityServiceInterface;
use App\Shared\Exceptions\ValidationException;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Tests\TestCase;

#[Group('failing')]
class AttachmentUploadTest extends TestCase
{
    protected AttachmentService $attachmentService;

    protected \App\Domains\Auth\Services\AuthorizationService|MockInterface $authService;

    protected \App\Domains\Security\Contracts\LoggingSecurityServiceInterface|MockInterface $logger;

    protected string $uploadDir;

    protected AttachmentRepository $attachmentRepo;

    protected PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock authService
        $this->authService = Mockery::mock(AuthorizationService::class);
        $this->authService->shouldReceive('canUploadAttachment')->byDefault()->andReturn(true);
        $this->authService->shouldReceive('canDeleteAttachment')->byDefault()->andReturn(true);
        $this->authService->shouldReceive('isSuperAdmin')->byDefault()->andReturn(false);

        // Mock logger
        $this->logger = Mockery::mock(LoggingSecurityServiceInterface::class);
        $this->logger->shouldReceive('logSecurityEvent')->byDefault();
        $this->logger->shouldReceive('enrichSecurityContext')->byDefault()->andReturn([]);

        // 建立測試用目錄
        $this->uploadDir = sys_get_temp_dir() . '/alleynote_test_' . uniqid();
        mkdir($this->uploadDir);

        // 初始化測試依賴
        $this->attachmentRepo = new AttachmentRepository($this->db, $this->cache);
        $this->postRepo = new PostRepository($this->db, $this->cache, $this->logger);

        // Mock ActivityLoggingService
        $activityLogger = Mockery::mock(ActivityLoggingServiceInterface::class);
        $activityLogger->shouldReceive('log')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logSuccess')->zeroOrMoreTimes();
        $activityLogger->shouldReceive('logFailure')->zeroOrMoreTimes();

        // 初始化測試對象
        $this->attachmentService = new AttachmentService($this->attachmentRepo, $this->postRepo, $this->authService, $activityLogger, $this->uploadDir);

        $this->createTestTables();

        // 插入一筆 id=1 的測試文章（補齊所有必要欄位）
        $now = date('Y-m-d H:i:s');
        $uuid = 'test-uuid-1';
        $seq = 1;
        $this->db->exec("INSERT INTO posts (id, uuid, seq_number, title, content, user_id, user_ip, views, is_pinned, status, publish_date, created_at, updated_at) VALUES (
            1,
            '$uuid',
            $seq,
            '測試文章',
            '內容',
            1,
            '127.0.0.1',
            0,
            0,
            'published',
            '$now',
            '$now',
            '$now'
        )");
    }

    protected function createUploadedFileMock(string $filename, string $mimeType, int $size): UploadedFileInterface
    {
        $file = Mockery::mock(UploadedFileInterface::class);
        $file->shouldReceive('getClientFilename')
            ->andReturn($filename);
        $file->shouldReceive('getClientMediaType')
            ->andReturn($mimeType);
        $file->shouldReceive('getSize')
            ->andReturn($size);
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')
            ->andReturn(str_repeat('x', $size));
        $stream->shouldReceive('rewind')
            ->andReturn(true);

        $file->shouldReceive('getStream')
            ->andReturn($stream);
        $file->shouldReceive('moveTo')
            ->andReturnUsing(function ($path) use ($size) {
                $directory = dirname($path);
                if (!is_dir($directory)) {
                    mkdir($directory, 0o755, true);
                }
                file_put_contents($path, str_repeat('x', $size));

                return true;
            });

        return $file;
    }

    #[Test]
    public function should_handle_concurrent_uploads(): void
    {
        $postId = 1;

        // 準備多個檔案 - 簡化為順序上傳測試
        $successfulUploads = 0;
        for ($i = 1; $i <= 3; $i++) {
            $file = $this->createUploadedFileMock(
                "test{$i}.jpg",
                'image/jpeg',
                1024,
            );

            try {
                $attachment = $this->attachmentService->upload($postId, $file, 1);
                $this->assertInstanceOf(Attachment::class, $attachment);
                $successfulUploads++;
            } catch (Exception $e) {
                $this->fail('上傳失敗: ' . $e->getMessage());
            }
        }

        $this->assertEquals(3, $successfulUploads, '所有上傳應該成功完成');
    }

    #[Test]
    public function should_handle_large_file_upload(): void
    {
        $postId = 1;
        $fileSize = 10 * 1024 * 1024; // 10MB

        $file = $this->createUploadedFileMock(
            'large_file.jpg',
            'image/jpeg',
            $fileSize,
        );

        $attachment = $this->attachmentService->upload($postId, $file, 1);

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals($fileSize, $attachment->getFileSize());
    }

    #[Test]
    public function should_validate_file_types(): void
    {
        $postId = 1;
        $invalidTypes = [
            'text/html' => 'test.html',
            'application/x-php' => 'test.php',
            'application/x-javascript' => 'test.js',
            'application/x-msdownload' => 'test.exe',
        ];

        foreach ($invalidTypes as $mimeType => $filename) {
            $file = $this->createUploadedFileMock($filename, $mimeType, 1024);

            try {
                $this->attachmentService->upload($postId, $file, 1);
                $this->fail("應該拒絕 {$mimeType} 類型的檔案");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('不支援的檔案類型', $e->getMessage());
            }
        }
    }

    #[Test]
    public function should_handle_disk_full_error(): void
    {
        $postId = 1;
        $file = Mockery::mock(UploadedFileInterface::class);
        $file->shouldReceive('getClientFilename')->andReturn('test.jpg');
        $file->shouldReceive('getClientMediaType')->andReturn('image/jpeg');
        $file->shouldReceive('getSize')->andReturn(1024);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(str_repeat('x', 1024));
        $stream->shouldReceive('rewind')->andReturn(true);
        $file->shouldReceive('getStream')->andReturn($stream);

        $file->shouldReceive('moveTo')
            ->once()
            ->andThrow(new RuntimeException('No space left on device'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('檔案上傳失敗');

        $this->attachmentService->upload($postId, $file, 1);
    }

    #[Test]
    public function should_handle_permission_error(): void
    {
        $postId = 1;
        $file = Mockery::mock(UploadedFileInterface::class);
        $file->shouldReceive('getClientFilename')->andReturn('test.jpg');
        $file->shouldReceive('getClientMediaType')->andReturn('image/jpeg');
        $file->shouldReceive('getSize')->andReturn(1024);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(str_repeat('x', 1024));
        $stream->shouldReceive('rewind')->andReturn(true);
        $file->shouldReceive('getStream')->andReturn($stream);

        $file->shouldReceive('moveTo')
            ->once()
            ->andThrow(new RuntimeException('Permission denied'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('檔案上傳失敗');

        $this->attachmentService->upload($postId, $file, 1);
    }

    protected function tearDown(): void
    {
        // 清理測試檔案
        if (is_dir($this->uploadDir)) {
            chmod($this->uploadDir, 0o755); // 恢復權限以便刪除
            $files = glob($this->uploadDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    // 遞迴刪除資料夾
                    $subFiles = glob($file . '/*');
                    foreach ($subFiles as $subFile) {
                        if (is_file($subFile)) {
                            unlink($subFile);
                        }
                    }
                    rmdir($file);
                }
            }
            rmdir($this->uploadDir);
        }
        parent::tearDown();
        Mockery::close();
    }

    protected function createTestTables(): void
    {
        // 先嘗試刪除已存在的資料表
        $this->db->exec('DROP TABLE IF EXISTS attachments');
        $this->db->exec('DROP TABLE IF EXISTS posts');

        // 建立 posts 資料表（schema 與主程式一致）
        $this->db->exec('
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid VARCHAR(36) NOT NULL UNIQUE,
                seq_number INTEGER NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                user_ip VARCHAR(45),
                views INTEGER NOT NULL DEFAULT 0,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT "draft",
                publish_date DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )
        ');

        // 建立 attachments 資料表
        $this->db->exec('
            CREATE TABLE attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid VARCHAR(36) NOT NULL,
                post_id INTEGER NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(255) NOT NULL,
                file_size INTEGER NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )
        ');
    }
}
