<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Services;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

/**
 * 批次操作服務
 * 提供高效能的批次資料庫操作.
 */
class BatchOperationService
{
    public function __construct(
        private ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * 批次插入記錄.
     */
    public function batchInsert(string $table, array $columns, array $data, int $batchSize = 1000): array
    {
        if (empty($data)) {
            return ['inserted' => 0, 'execution_time' => 0];
        }

        $startTime = microtime(true);
        $totalInserted = 0;

        try {
            $this->pdo->beginTransaction();

            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);

            $batches = array_chunk($data, $batchSize);

            foreach ($batches as $batch) {
                foreach ($batch as $row) {
                    $stmt->execute($row);
                    $totalInserted++;
                }
            }

            $this->pdo->commit();

            $executionTime = microtime(true) - $startTime;

            return [
                'inserted' => $totalInserted,
                'execution_time' => round($executionTime, 4),
                'average_time_per_record' => round($executionTime / $totalInserted * 1000, 4), // ms
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            return [
                'inserted' => 0,
                'execution_time' => microtime(true) - $startTime,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批次更新記錄.
     */
    public function batchUpdate(string $table, array $updates, string $whereColumn, int $batchSize = 1000): array
    {
        if (empty($updates)) {
            return ['updated' => 0, 'execution_time' => 0];
        }

        $startTime = microtime(true);
        $totalUpdated = 0;

        try {
            $this->pdo->beginTransaction();

            $batches = array_chunk($updates, $batchSize, true);

            foreach ($batches as $batch) {
                foreach ($batch as $whereValue => $updateData) {
                    $setClause = [];
                    $params = [];

                    foreach ($updateData as $column => $value) {
                        $setClause[] = "{$column} = ?";
                        $params[] = $value;
                    }

                    $params[] = $whereValue;

                    $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$whereColumn} = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);

                    $totalUpdated += $stmt->rowCount();
                }
            }

            $this->pdo->commit();

            $executionTime = microtime(true) - $startTime;

            return [
                'updated' => $totalUpdated,
                'execution_time' => round($executionTime, 4),
                'average_time_per_record' => $totalUpdated > 0 ? round($executionTime / $totalUpdated * 1000, 4) : 0,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            return [
                'updated' => 0,
                'execution_time' => microtime(true) - $startTime,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批次刪除記錄.
     */
    public function batchDelete(string $table, array $ids, string $idColumn = 'id', int $batchSize = 1000): array
    {
        if (empty($ids)) {
            return ['deleted' => 0, 'execution_time' => 0];
        }

        $startTime = microtime(true);
        $totalDeleted = 0;

        try {
            $this->pdo->beginTransaction();

            $batches = array_chunk($ids, $batchSize);

            foreach ($batches as $batch) {
                $placeholders = str_repeat('?,', count($batch) - 1) . '?';
                $sql = "DELETE FROM {$table} WHERE {$idColumn} IN ({$placeholders})";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($batch);

                $totalDeleted += $stmt->rowCount();
            }

            $this->pdo->commit();

            $executionTime = microtime(true) - $startTime;

            return [
                'deleted' => $totalDeleted,
                'execution_time' => round($executionTime, 4),
                'average_time_per_record' => $totalDeleted > 0 ? round($executionTime / $totalDeleted * 1000, 4) : 0,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            return [
                'deleted' => 0,
                'execution_time' => microtime(true) - $startTime,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批次 Upsert 操作（插入或更新）.
     */
    public function batchUpsert(string $table, array $columns, array $data, array $conflictColumns, int $batchSize = 1000): array
    {
        if (empty($data)) {
            return ['affected' => 0, 'execution_time' => 0];
        }

        $startTime = microtime(true);
        $totalAffected = 0;

        try {
            $this->pdo->beginTransaction();

            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $updateClause = [];

            foreach ($columns as $column) {
                if (!in_array($column, $conflictColumns)) {
                    $updateClause[] = "{$column} = excluded.{$column}";
                }
            }

            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
            if (!empty($updateClause)) {
                $sql .= ' ON CONFLICT(' . implode(',', $conflictColumns) . ') DO UPDATE SET ' . implode(', ', $updateClause);
            } else {
                $sql .= ' ON CONFLICT(' . implode(',', $conflictColumns) . ') DO NOTHING';
            }

            $stmt = $this->pdo->prepare($sql);

            $batches = array_chunk($data, $batchSize);

            foreach ($batches as $batch) {
                foreach ($batch as $row) {
                    $stmt->execute($row);
                    $totalAffected += $stmt->rowCount();
                }
            }

            $this->pdo->commit();

            $executionTime = microtime(true) - $startTime;

            return [
                'affected' => $totalAffected,
                'execution_time' => round($executionTime, 4),
                'average_time_per_record' => $totalAffected > 0 ? round($executionTime / $totalAffected * 1000, 4) : 0,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            return [
                'affected' => 0,
                'execution_time' => microtime(true) - $startTime,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 獲取批次操作統計資訊.
     */
    public function getBatchStatistics(): array
    {
        return [
            'recommended_batch_sizes' => [
                'insert' => 1000,
                'update' => 500,
                'delete' => 1000,
                'upsert' => 800,
            ],
            'performance_tips' => [
                '使用事務包裝批次操作',
                '適當調整批次大小避免記憶體不足',
                '對於大量資料考慮使用 PRAGMA synchronous = NORMAL',
                '批次操作前停用外鍵約束檢查可提升效能',
            ],
        ];
    }
}
