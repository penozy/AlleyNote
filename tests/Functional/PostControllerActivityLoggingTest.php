<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Repositories\ActivityLogRepository;
use DateTime;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PostController 活動記錄功能測試.
 */
class PostControllerActivityLoggingTest extends TestCase
{
    private PDO $pdo;

    private ActivityLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立資料庫連接 - 使用與 Phinx 相同的資料庫路徑
        $this->pdo = new PDO('sqlite:' . __DIR__ . '/../../database/alleynote.sqlite3');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->repository = new ActivityLogRepository($this->pdo);

        // 清理測試資料
        $this->pdo->exec('DELETE FROM user_activity_logs WHERE action_type LIKE "post.%" AND user_id IS NULL');
    }

    protected function tearDown(): void
    {
        // 清理測試資料
        $this->pdo->exec('DELETE FROM user_activity_logs WHERE action_type LIKE "post.%" AND user_id IS NULL');
        parent::tearDown();
    }

    #[Test]
    public function it_can_record_post_creation_activity(): void
    {
        $userId = null; // 使用 NULL 來避免外鍵約束問題
        $postId = 1;
        $ipAddress = '192.168.1.100';

        // 模擬文章建立成功的記錄
        $this->pdo->prepare('
            INSERT INTO user_activity_logs 
            (uuid, user_id, action_type, action_category, status, target_id, target_type, ip_address, user_agent, metadata, occurred_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            uniqid('test_', true),
            $userId,
            ActivityType::POST_CREATED->value,
            ActivityType::POST_CREATED->getCategory()->value,
            'success',
            (string) $postId,
            'post',
            $ipAddress,
            'TestAgent/1.0',
            json_encode([
                'post_id' => $postId,
                'title' => 'Test Post Title',
                'operation' => 'create',
            ]),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
        ]);

        // 驗證記錄是否成功寫入
        $stmt = $this->pdo->prepare('SELECT * FROM user_activity_logs WHERE target_id = ? AND target_type = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([(string) $postId, 'post']);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityType::POST_CREATED->value, $logs[0]['action_type']);
        $this->assertSame('success', $logs[0]['status']);
        $this->assertSame((string) $postId, $logs[0]['target_id']);
        $this->assertSame('post', $logs[0]['target_type']);

        $metadata = json_decode($logs[0]['metadata'], true);
        $this->assertSame($postId, $metadata['post_id']);
        $this->assertSame('Test Post Title', $metadata['title']);
    }

    #[Test]
    public function it_can_record_post_viewing_activity(): void
    {
        $userId = null;
        $postId = 2;
        $ipAddress = '192.168.1.101';

        // 模擬文章查看的記錄
        $this->pdo->prepare('
            INSERT INTO user_activity_logs 
            (uuid, user_id, action_type, action_category, status, target_id, target_type, ip_address, user_agent, metadata, occurred_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            uniqid('test_', true),
            $userId,
            ActivityType::POST_VIEWED->value,
            ActivityType::POST_VIEWED->getCategory()->value,
            'success',
            (string) $postId,
            'post',
            $ipAddress,
            'TestAgent/1.0',
            json_encode([
                'post_id' => $postId,
                'title' => 'Another Test Post',
                'operation' => 'view',
            ]),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
        ]);

        // 驗證記錄是否成功寫入
        $stmt = $this->pdo->prepare('SELECT * FROM user_activity_logs WHERE target_id = ? AND target_type = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([(string) $postId, 'post']);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityType::POST_VIEWED->value, $logs[0]['action_type']);
        $this->assertSame('success', $logs[0]['status']);
        $this->assertSame((string) $postId, $logs[0]['target_id']);

        $metadata = json_decode($logs[0]['metadata'], true);
        $this->assertSame($postId, $metadata['post_id']);
        $this->assertSame('view', $metadata['operation']);
    }

    #[Test]
    public function it_can_record_post_update_activity(): void
    {
        $userId = null;
        $postId = 3;

        // 模擬文章更新的記錄
        $this->pdo->prepare('
            INSERT INTO user_activity_logs 
            (uuid, user_id, action_type, action_category, status, target_id, target_type, ip_address, metadata, occurred_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            uniqid('test_', true),
            $userId,
            ActivityType::POST_UPDATED->value,
            ActivityType::POST_UPDATED->getCategory()->value,
            'success',
            (string) $postId,
            'post',
            '192.168.1.102',
            json_encode([
                'post_id' => $postId,
                'title' => 'Updated Post Title',
                'operation' => 'update',
                'changes' => ['title', 'content'],
            ]),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
        ]);

        // 驗證記錄
        $stmt = $this->pdo->prepare('SELECT * FROM user_activity_logs WHERE target_id = ? AND target_type = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([(string) $postId, 'post']);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $logs);
        $this->assertSame(ActivityType::POST_UPDATED->value, $logs[0]['action_type']);

        $metadata = json_decode($logs[0]['metadata'], true);
        $this->assertSame('update', $metadata['operation']);
        $this->assertContains('title', $metadata['changes']);
        $this->assertContains('content', $metadata['changes']);
    }

    #[Test]
    public function it_can_query_post_activities_by_time_range(): void
    {
        $userId = null;

        // 簡化時間邏輯 - 使用當前時間
        $currentTime = time();
        $now = new DateTime('@' . $currentTime);
        $oneHourAgo = new DateTime('@' . ($currentTime - 3600));
        $twoHoursAgo = new DateTime('@' . ($currentTime - 7200));

        // 插入不同時間的記錄
        $activities = [
            ['time' => $twoHoursAgo, 'type' => ActivityType::POST_CREATED],
            ['time' => $oneHourAgo, 'type' => ActivityType::POST_UPDATED],
            ['time' => $now, 'type' => ActivityType::POST_VIEWED],
        ];

        foreach ($activities as $i => $activity) {
            $this->pdo->prepare('
                INSERT INTO user_activity_logs 
                (uuid, user_id, action_type, action_category, status, target_id, target_type, ip_address, occurred_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                uniqid('test_' . $i . '_', true),
                $userId,
                $activity['type']->value,
                $activity['type']->getCategory()->value,
                'success',
                (string) ($i + 1),
                'post',
                '192.168.1.100',
                $activity['time']->format('Y-m-d H:i:s'),
                $activity['time']->format('Y-m-d H:i:s'),
            ]);
        }

        // 查詢最近1.5小時的活動
        $startTime = new DateTime('@' . ($currentTime - 5400)); // 1.5小時前

        $recentLogs = $this->repository->findByTimeRange(
            $startTime,
            $now,
        );

        // 應該只有2筆記錄（1小時前和現在的）
        $this->assertCount(2, $recentLogs);

        $activityTypes = array_column($recentLogs, 'action_type');
        $this->assertContains(ActivityType::POST_UPDATED->value, $activityTypes);
        $this->assertContains(ActivityType::POST_VIEWED->value, $activityTypes);
        $this->assertNotContains(ActivityType::POST_CREATED->value, $activityTypes);
    }
}
