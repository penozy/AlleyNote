<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityStatus;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Repositories\ActivityLogRepository;
use App\Domains\Security\Services\ActivityLoggingService;
use App\Shared\Contracts\ValidatorInterface;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ActivityLoggingPerformanceTest extends TestCase
{
    private ActivityLoggingServiceInterface $activityLoggingService;

    private ActivityLogRepositoryInterface $repository;

    private PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立 user_activity_logs 表
        $this->createActivityLogsTable();

        // 建立真實的 ActivityLogRepository 和 ActivityLoggingService
        $this->repository = new ActivityLogRepository($this->db);

        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('validate')->andReturn([]);

        // ActivityLoggingService 建構子需要 Repository 和 Logger
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->zeroOrMoreTimes();
        $logger->shouldReceive('error')->zeroOrMoreTimes();

        $this->activityLoggingService = new ActivityLoggingService(
            $this->repository,
            $logger,
        );

        // 取得資料庫連線
        $this->database = $this->db;
    }

    protected function tearDown(): void
    {
        // 清理測試資料
        $this->db->exec('DELETE FROM user_activity_logs WHERE description LIKE "Performance Test%"');
        parent::tearDown();
    }

    /**
     * 建立測試用的 user_activity_logs 表.
     */
    private function createActivityLogsTable(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS user_activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                user_id INTEGER,
                session_id TEXT,
                action_type TEXT NOT NULL,
                action_category TEXT NOT NULL,
                target_type TEXT,
                target_id TEXT,
                status TEXT NOT NULL DEFAULT "success",
                description TEXT,
                metadata TEXT,
                ip_address TEXT,
                user_agent TEXT,
                request_method TEXT,
                request_path TEXT,
                created_at TEXT NOT NULL,
                occurred_at TEXT NOT NULL
            )
        ');

        // 建立索引
        $this->db->exec('
            CREATE INDEX IF NOT EXISTS idx_user_activity_logs_uuid ON user_activity_logs(uuid);
            CREATE INDEX IF NOT EXISTS idx_user_activity_logs_user_id ON user_activity_logs(user_id);
            CREATE INDEX IF NOT EXISTS idx_user_activity_logs_action_type ON user_activity_logs(action_type);
            CREATE INDEX IF NOT EXISTS idx_user_activity_logs_created_at ON user_activity_logs(created_at)
        ');
    }

    #[Test]
    public function singleRecordPerformanceTest(): void
    {
        $iterations = 50;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $dto = new CreateActivityLogDTO(
                actionType: ActivityType::LOGIN_SUCCESS,
                userId: 1,
                description: "Performance Test Single Record #{$i}",
                metadata: [
                    'test_iteration' => $i,
                    'performance_test' => 'single_record',
                ],
                ipAddress: '192.168.1.100',
            );

            $this->activityLoggingService->log($dto);

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // 轉換為毫秒
            $times[] = $executionTime;
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        echo "\n📊 單筆記錄效能測試結果:\n";
        echo '   平均執行時間: ' . number_format($averageTime, 2) . "ms\n";
        echo '   最大執行時間: ' . number_format($maxTime, 2) . "ms\n";
        echo "   執行次數: {$iterations}\n";

        // 驗證效能需求：單筆記錄 < 50ms
        $this->assertLessThan(
            50,
            $averageTime,
            "平均執行時間 {$averageTime}ms 超過 50ms 需求",
        );
        $this->assertLessThan(
            100,
            $maxTime,
            "最大執行時間 {$maxTime}ms 超過合理範圍",
        );
    }

    #[Test]
    public function batchRecordPerformanceTest(): void
    {
        $batchSize = 100;
        $batchCount = 5;

        $batchTimes = [];

        for ($batch = 0; $batch < $batchCount; $batch++) {
            $records = [];

            // 準備批次資料
            for ($i = 0; $i < $batchSize; $i++) {
                $records[] = new CreateActivityLogDTO(
                    actionType: ActivityType::LOGIN_SUCCESS,
                    userId: ($i % 10) + 1,
                    description: "Performance Test Batch #{$batch} Record #{$i}",
                    metadata: [
                        'batch_number' => $batch,
                        'record_number' => $i,
                        'performance_test' => 'batch_record',
                    ],
                    ipAddress: '192.168.1.' . ($i % 255 + 1),
                );
            }

            $startTime = microtime(true);
            $this->activityLoggingService->logBatch($records);
            $endTime = microtime(true);

            $executionTime = ($endTime - $startTime) * 1000;
            $batchTimes[] = $executionTime;
        }

        $averageBatchTime = array_sum($batchTimes) / count($batchTimes);
        $averagePerRecord = $averageBatchTime / $batchSize;
        $maxBatchTime = max($batchTimes);

        echo "\n📊 批次記錄效能測試結果:\n";
        echo "   批次大小: {$batchSize} 筆\n";
        echo '   平均批次時間: ' . number_format($averageBatchTime, 2) . "ms\n";
        echo '   平均每筆時間: ' . number_format($averagePerRecord, 2) . "ms\n";
        echo '   最大批次時間: ' . number_format($maxBatchTime, 2) . "ms\n";
        echo "   執行批次數: {$batchCount}\n";

        // 驗證批次效能：平均每筆應該比單筆更快
        $this->assertLessThan(
            10,
            $averagePerRecord,
            "批次平均每筆時間 {$averagePerRecord}ms 應該 < 10ms",
        );
        $this->assertLessThan(
            2000,
            $maxBatchTime,
            "最大批次時間 {$maxBatchTime}ms 超過合理範圍",
        );
    }

    #[Test]
    public function queryPerformanceTest(): void
    {
        // 先建立測試資料
        $this->createTestDataForQueryPerformance();

        $queryTests = [
            'findByUser' => fn() => $this->queryByUser(),
            'findByTimeRange' => fn() => $this->queryByTimeRange(),
            'findSecurityEvents' => fn() => $this->querySecurityEvents(),
            'getActivityStatistics' => fn() => $this->queryStatistics(),
        ];

        $results = [];

        foreach ($queryTests as $testName => $queryFunction) {
            $times = [];
            $iterations = 10;

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                $queryFunction();
                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000;
            }

            $averageTime = array_sum($times) / count($times);
            $maxTime = max($times);

            $results[$testName] = [
                'average' => $averageTime,
                'max' => $maxTime,
                'iterations' => $iterations,
            ];
        }

        echo "\n📊 查詢效能測試結果:\n";
        foreach ($results as $testName => $result) {
            echo "   {$testName}:\n";
            echo '     平均: ' . number_format($result['average'], 2) . "ms\n";
            echo '     最大: ' . number_format($result['max'], 2) . "ms\n";

            // 驗證查詢效能：< 500ms
            $this->assertLessThan(
                500,
                $result['average'],
                "{$testName} 平均查詢時間 {$result['average']}ms 超過 500ms 需求",
            );
        }
    }

    #[Test]
    public function concurrentRecordPerformanceTest(): void
    {
        $processCount = 10;
        $recordsPerProcess = 10;
        $totalRecords = $processCount * $recordsPerProcess;

        echo "\n📊 併發記錄效能測試 (模擬):\n";
        echo "   併發程序數: {$processCount}\n";
        echo "   每程序記錄數: {$recordsPerProcess}\n";
        echo "   總記錄數: {$totalRecords}\n";

        $startTime = microtime(true);

        // 模擬併發操作（實際上是循序執行，但測量總體效能）
        for ($process = 0; $process < $processCount; $process++) {
            for ($record = 0; $record < $recordsPerProcess; $record++) {
                $dto = CreateActivityLogDTO::success(
                    actionType: ActivityType::LOGIN_SUCCESS,
                    description: "Performance Test Concurrent Process #{$process} Record #{$record}",
                    userId: $process + 1,
                    metadata: [
                        'process_id' => $process,
                        'record_id' => $record,
                        'performance_test' => 'concurrent',
                        'ip_address' => "10.0.{$process}." . ($record + 1),
                    ],
                );

                $this->activityLoggingService->log($dto);
            }
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averagePerRecord = $totalTime / $totalRecords;

        echo '   總執行時間: ' . number_format($totalTime, 2) . "ms\n";
        echo '   平均每筆時間: ' . number_format($averagePerRecord, 2) . "ms\n";
        echo '   每秒處理能力: ' . number_format(1000 / $averagePerRecord, 0) . " 筆/秒\n";

        // 驗證併發效能
        $this->assertLessThan(
            50,
            $averagePerRecord,
            "併發平均每筆時間 {$averagePerRecord}ms 超過 50ms 需求",
        );
        $this->assertGreaterThan(
            20,
            1000 / $averagePerRecord,
            '每秒處理能力應該 > 20 筆/秒',
        );
    }

    #[Test]
    public function largeDatasetPerformanceTest(): void
    {
        $recordCount = 1000; // 因為測試環境限制，使用 1000 筆而不是 100 萬筆
        $batchSize = 100;
        $batches = ceil($recordCount / $batchSize);

        // 定義測試用的活動類型
        $activityTypes = [ActivityType::LOGIN_SUCCESS, ActivityType::LOGOUT, ActivityType::POST_CREATED];

        echo "\n📊 大量資料效能測試:\n";
        echo "   目標記錄數: {$recordCount}\n";
        echo "   批次大小: {$batchSize}\n";
        echo "   批次數量: {$batches}\n";

        $overallStartTime = microtime(true);
        $batchTimes = [];

        for ($batch = 0; $batch < $batches; $batch++) {
            $currentBatchSize = min($batchSize, $recordCount - ($batch * $batchSize));
            $records = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $recordIndex = $batch * $batchSize + $i;
                $records[] = CreateActivityLogDTO::success(
                    actionType: $activityTypes[array_rand($activityTypes)],
                    description: "Performance Test Large Dataset Record #{$recordIndex}",
                    userId: ($recordIndex % 100) + 1,
                    metadata: [
                        'record_index' => $recordIndex,
                        'batch_index' => $batch,
                        'performance_test' => 'large_dataset',
                        'ip_address' => '203.0.' . floor($recordIndex / 256) . '.' . ($recordIndex % 256),
                    ],
                );
            }

            $batchStartTime = microtime(true);
            $this->activityLoggingService->logBatch($records);
            $batchEndTime = microtime(true);

            $batchTime = ($batchEndTime - $batchStartTime) * 1000;
            $batchTimes[] = $batchTime;

            if ($batch % 5 == 0) { // 每5個批次報告一次進度
                echo '   批次 ' . ($batch + 1) . "/{$batches} 完成，耗時: "
                    . number_format($batchTime, 2) . "ms\n";
            }
        }

        $overallEndTime = microtime(true);
        $totalTime = ($overallEndTime - $overallStartTime) * 1000;
        $averagePerRecord = $totalTime / $recordCount;
        $averageBatchTime = array_sum($batchTimes) / count($batchTimes);

        echo "\n   總執行時間: " . number_format($totalTime / 1000, 2) . "s\n";
        echo '   平均每筆時間: ' . number_format($averagePerRecord, 2) . "ms\n";
        echo '   平均批次時間: ' . number_format($averageBatchTime, 2) . "ms\n";
        echo '   每秒處理能力: ' . number_format($recordCount / ($totalTime / 1000), 0) . " 筆/秒\n";

        // 驗證大量資料效能
        $this->assertLessThan(
            5,
            $averagePerRecord,
            "大量資料平均每筆時間 {$averagePerRecord}ms 應該 < 5ms",
        );
        $this->assertGreaterThan(
            100,
            $recordCount / ($totalTime / 1000),
            '每秒處理能力應該 > 100 筆/秒',
        );
    }

    private function createTestDataForQueryPerformance(): void
    {
        echo "   建立查詢測試資料...\n";

        $testData = [];
        $userIds = [1, 2, 3, 4, 5];
        $activityTypes = [ActivityType::LOGIN_SUCCESS, ActivityType::LOGOUT, ActivityType::POST_CREATED];

        for ($i = 0; $i < 100; $i++) {
            $testData[] = CreateActivityLogDTO::success(
                actionType: $activityTypes[array_rand($activityTypes)],
                description: "Performance Test Query Data #{$i}",
                userId: $userIds[array_rand($userIds)],
                metadata: [
                    'query_test_index' => $i,
                    'performance_test' => 'query',
                    'ip_address' => '192.168.2.' . ($i % 255 + 1),
                ],
            );
        }

        $this->activityLoggingService->logBatch($testData);
    }

    private function queryByUser(): array
    {
        $stmt = $this->database->prepare('
            SELECT * FROM user_activity_logs 
            WHERE user_id = ? AND description LIKE "Performance Test Query Data%"
            ORDER BY created_at DESC 
            LIMIT 20
        ');
        $stmt->execute(['1']);

        return $stmt->fetchAll();
    }

    private function queryByTimeRange(): array
    {
        $endTime = date('Y-m-d H:i:s');
        $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $stmt = $this->database->prepare('
            SELECT * FROM user_activity_logs 
            WHERE created_at BETWEEN ? AND ? 
            AND description LIKE "Performance Test Query Data%"
            ORDER BY created_at DESC
        ');
        $stmt->execute([$startTime, $endTime]);

        return $stmt->fetchAll();
    }

    private function querySecurityEvents(): array
    {
        $stmt = $this->database->prepare('
            SELECT * FROM user_activity_logs 
            WHERE status = ? AND description LIKE "Performance Test Query Data%"
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $stmt->execute([ActivityStatus::SUCCESS->value]);

        return $stmt->fetchAll();
    }

    private function queryStatistics(): array
    {
        $stmt = $this->database->prepare('
            SELECT 
                action_type,
                COUNT(*) as count,
                AVG(CASE WHEN metadata IS NOT NULL THEN 1 ELSE 0 END) as avg_metadata
            FROM user_activity_logs 
            WHERE description LIKE "Performance Test Query Data%"
            GROUP BY action_type
        ');
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
