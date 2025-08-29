<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Infrastructure\Database\DatabaseConnection;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * 資料庫查詢效能測試
 */
class DatabaseQueryPerformanceTest extends TestCase
{
    private PDO $pdo;
    private array $performanceResults = [];

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnection::getInstance();
    }

    protected function tearDown(): void
    {
        // 輸出效能報告
        $this->outputPerformanceReport();
    }

    /**
     * 測試最新公告查詢效能
     */
    public function testRecentPostsQueryPerformance(): void
    {
        $sql = 'SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 10';

        $executionTime = $this->measureQueryTime($sql);
        $this->performanceResults['recent_posts'] = $executionTime;

        // 目標：查詢時間應該少於 10ms
        $this->assertLessThan(0.01, $executionTime, '最新公告查詢應該在 10ms 內完成');
    }

    /**
     * 測試使用者公告查詢效能
     */
    public function testUserPostsQueryPerformance(): void
    {
        // 先確保有測試資料
        $this->createTestUser();

        $sql = 'SELECT * FROM posts WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20';

        $executionTime = $this->measureQueryTime($sql, [1]);
        $this->performanceResults['user_posts'] = $executionTime;

        // 目標：查詢時間應該少於 5ms
        $this->assertLessThan(0.005, $executionTime, '使用者公告查詢應該在 5ms 內完成');
    }

    /**
     * 測試複雜聯合查詢效能
     */
    public function testComplexJoinQueryPerformance(): void
    {
        $sql = '
            SELECT p.*, u.username, u.email 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.deleted_at IS NULL 
            ORDER BY p.created_at DESC 
            LIMIT 10
        ';

        $executionTime = $this->measureQueryTime($sql);
        $this->performanceResults['complex_join'] = $executionTime;

        // 目標：複雜查詢應該在 20ms 內完成
        $this->assertLessThan(0.02, $executionTime, '複雜聯合查詢應該在 20ms 內完成');
    }

    /**
     * 測試權限查詢效能
     */
    public function testPermissionQueryPerformance(): void
    {
        $sql = '
            SELECT DISTINCT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ';

        $executionTime = $this->measureQueryTime($sql, [1]);
        $this->performanceResults['permission_query'] = $executionTime;

        // 目標：權限查詢應該在 15ms 內完成
        $this->assertLessThan(0.015, $executionTime, '權限查詢應該在 15ms 內完成');
    }

    /**
     * 測試分頁查詢效能
     */
    public function testPaginationQueryPerformance(): void
    {
        $sql = '
            SELECT COUNT(*) OVER() as total_count, p.*
            FROM posts p 
            WHERE p.deleted_at IS NULL 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?
        ';

        $executionTime = $this->measureQueryTime($sql, [20, 0]);
        $this->performanceResults['pagination'] = $executionTime;

        // 目標：分頁查詢應該在 10ms 內完成
        $this->assertLessThan(0.01, $executionTime, '分頁查詢應該在 10ms 內完成');
    }

    /**
     * 測試批次插入效能
     */
    public function testBatchInsertPerformance(): void
    {
        $startTime = microtime(true);

        $this->pdo->beginTransaction();

        $sql = 'INSERT INTO user_activity_logs (uuid, user_id, action_type, action_category, metadata, ip_address, user_agent, created_at, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);

        // 批次插入 100 筆記錄
        for ($i = 1; $i <= 100; $i++) {
            $stmt->execute([
                uniqid('perf-test-', true),
                1,
                'test_action',
                'testing',
                json_encode(['test' => $i]),
                '127.0.0.1',
                'test-agent',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
        }

        $this->pdo->commit();

        $executionTime = microtime(true) - $startTime;
        $this->performanceResults['batch_insert'] = $executionTime;

        // 目標：100 筆批次插入應該在 50ms 內完成
        $this->assertLessThan(0.05, $executionTime, '100 筆批次插入應該在 50ms 內完成');

        // 清理測試資料
        $this->pdo->exec("DELETE FROM user_activity_logs WHERE action_type = 'test_action'");
    }

    /**
     * 測量查詢執行時間
     */
    private function measureQueryTime(string $sql, array $params = []): float
    {
        $startTime = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        $endTime = microtime(true);

        return $endTime - $startTime;
    }

    /**
     * 建立測試使用者
     */
    private function createTestUser(): void
    {
        $sql = 'INSERT OR IGNORE INTO users (username, email, password_hash, created_at) 
                VALUES (?, ?, ?, ?)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'performance_test_user',
            'test@example.com',
            password_hash('test123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 輸出效能報告
     */
    private function outputPerformanceReport(): void
    {
        if (empty($this->performanceResults)) {
            return;
        }

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 T4.2 資料庫查詢效能測試報告\n";
        echo str_repeat("=", 60) . "\n";

        foreach ($this->performanceResults as $testName => $time) {
            $timeMs = round($time * 1000, 2);
            $status = $this->getPerformanceStatus($testName, $time);
            echo sprintf("  • %s: %.2fms %s\n", $testName, $timeMs, $status);
        }

        $totalTime = array_sum($this->performanceResults);
        $avgTime = $totalTime / count($this->performanceResults);

        echo sprintf("\n總執行時間: %.2fms\n", $totalTime * 1000);
        echo sprintf("平均執行時間: %.2fms\n", $avgTime * 1000);
        echo str_repeat("=", 60) . "\n\n";
    }

    /**
     * 獲取效能狀態
     */
    private function getPerformanceStatus(string $testName, float $time): string
    {
        $thresholds = [
            'recent_posts' => 0.01,
            'user_posts' => 0.005,
            'complex_join' => 0.02,
            'permission_query' => 0.015,
            'pagination' => 0.01,
            'batch_insert' => 0.05
        ];

        $threshold = $thresholds[$testName] ?? 0.01;

        if ($time <= $threshold * 0.7) {
            return '🟢 優秀';
        } elseif ($time <= $threshold) {
            return '🟡 良好';
        } else {
            return '🔴 需優化';
        }
    }
}
