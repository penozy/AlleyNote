<?php

declare(strict_types=1);

/**
 * 資料庫效能分析工具
 * 
 * 分析當前資料庫的查詢效能，識別慢查詢，並提供優化建議
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class DatabasePerformanceAnalyzer
{
    private PDO $pdo;
    private array $analysisResults = [];
    
    public function __construct()
    {
        $this->pdo = DatabaseConnection::getInstance();
    }
    
    /**
     * 執行完整的資料庫效能分析
     */
    public function runFullAnalysis(): array
    {
        echo "🔍 開始資料庫效能分析...\n\n";
        
        $this->analysisResults = [
            'slow_queries' => $this->analyzeSlowQueries(),
            'missing_indexes' => $this->findMissingIndexes(),
            'table_statistics' => $this->getTableStatistics(),
            'query_plans' => $this->analyzeQueryPlans(),
            'optimization_suggestions' => $this->generateOptimizationSuggestions(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->generateReport();
        
        return $this->analysisResults;
    }
    
    /**
     * 分析慢查詢
     */
    private function analyzeSlowQueries(): array
    {
        echo "📊 分析慢查詢模式...\n";
        
        $slowQueries = [];
        
        // 模擬常見查詢並測量執行時間
        $testQueries = [
            [
                'name' => '取得最新公告',
                'sql' => 'SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 10',
                'expected_time' => 0.01 // 10ms
            ],
            [
                'name' => '依使用者查詢公告',
                'sql' => 'SELECT * FROM posts WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
                'params' => [1],
                'expected_time' => 0.005
            ],
            [
                'name' => '查詢附件資訊',
                'sql' => 'SELECT a.*, p.title FROM attachments a JOIN posts p ON a.post_id = p.id WHERE p.deleted_at IS NULL',
                'expected_time' => 0.02
            ],
            [
                'name' => '使用者權限查詢',
                'sql' => 'SELECT DISTINCT p.* FROM permissions p 
                         JOIN group_permission gp ON p.id = gp.permission_id 
                         JOIN user_group ug ON gp.group_id = ug.group_id 
                         WHERE ug.user_id = ?',
                'params' => [1],
                'expected_time' => 0.015
            ]
        ];
        
        foreach ($testQueries as $query) {
            $startTime = microtime(true);
            
            try {
                $stmt = $this->pdo->prepare($query['sql']);
                if (isset($query['params'])) {
                    $stmt->execute($query['params']);
                } else {
                    $stmt->execute();
                }
                $result = $stmt->fetchAll();
                
                $executionTime = microtime(true) - $startTime;
                
                $slowQueries[] = [
                    'name' => $query['name'],
                    'sql' => $query['sql'],
                    'execution_time' => round($executionTime * 1000, 2), // 轉換為毫秒
                    'expected_time' => $query['expected_time'] * 1000,
                    'is_slow' => $executionTime > $query['expected_time'],
                    'result_count' => count($result),
                    'improvement_needed' => $executionTime > $query['expected_time'] ? 
                        round(($executionTime - $query['expected_time']) / $query['expected_time'] * 100, 2) : 0
                ];
                
            } catch (Exception $e) {
                $slowQueries[] = [
                    'name' => $query['name'],
                    'sql' => $query['sql'],
                    'error' => $e->getMessage(),
                    'execution_time' => 0,
                    'is_slow' => true
                ];
            }
        }
        
        return $slowQueries;
    }
    
    /**
     * 尋找缺失的索引
     */
    private function findMissingIndexes(): array
    {
        echo "🔎 分析索引覆蓋情況...\n";
        
        $missingIndexes = [];
        
        // 檢查規格書建議的索引
        $recommendedIndexes = [
            'users' => ['username', 'email', 'uuid'],
            'posts' => ['uuid', 'seq_number', 'user_id', 'publish_date', 'created_at', 'deleted_at'],
            'attachments' => ['post_id'],
            'ip_lists' => ['ip_address', 'type'],
            'user_unit' => ['user_id', 'unit_id'],
            'user_group' => ['user_id', 'group_id'],
            'group_permission' => ['group_id', 'permission_id']
        ];
        
        foreach ($recommendedIndexes as $table => $columns) {
            // 檢查表是否存在
            $tableExists = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            if (!$tableExists->fetch()) {
                continue;
            }
            
            // 獲取現有索引
            $existingIndexes = $this->pdo->query("PRAGMA index_list($table)")->fetchAll(PDO::FETCH_ASSOC);
            $existingColumns = [];
            
            foreach ($existingIndexes as $index) {
                $indexInfo = $this->pdo->query("PRAGMA index_info('{$index['name']}')")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($indexInfo as $column) {
                    $existingColumns[] = $column['name'];
                }
            }
            
            foreach ($columns as $column) {
                if (!in_array($column, $existingColumns)) {
                    $missingIndexes[] = [
                        'table' => $table,
                        'column' => $column,
                        'suggested_name' => "idx_{$table}_{$column}",
                        'create_sql' => "CREATE INDEX IF NOT EXISTS idx_{$table}_{$column} ON {$table}({$column});",
                        'priority' => $this->calculateIndexPriority($table, $column)
                    ];
                }
            }
        }
        
        // 按優先級排序
        usort($missingIndexes, fn($a, $b) => $b['priority'] <=> $a['priority']);
        
        return $missingIndexes;
    }
    
    /**
     * 獲取表統計資訊
     */
    private function getTableStatistics(): array
    {
        echo "📈 收集表統計資訊...\n";
        
        $statistics = [];
        
        // 獲取所有表
        $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                           ->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            try {
                // 行數
                $rowCount = $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                
                // 表大小估算（SQLite 沒有直接的大小查詢，使用頁數估算）
                $pageInfo = $this->pdo->query("PRAGMA page_count")->fetchColumn();
                $pageSize = $this->pdo->query("PRAGMA page_size")->fetchColumn();
                
                // 索引資訊
                $indexes = $this->pdo->query("PRAGMA index_list($table)")->fetchAll(PDO::FETCH_ASSOC);
                
                $statistics[] = [
                    'table' => $table,
                    'row_count' => (int)$rowCount,
                    'estimated_size_kb' => round(($pageInfo * $pageSize) / 1024 / count($tables), 2),
                    'index_count' => count($indexes),
                    'growth_rate' => $this->calculateGrowthRate($table),
                ];
            } catch (Exception $e) {
                $statistics[] = [
                    'table' => $table,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $statistics;
    }
    
    /**
     * 分析查詢計劃
     */
    private function analyzeQueryPlans(): array
    {
        echo "🔍 分析查詢執行計劃...\n";
        
        $queryPlans = [];
        
        $importantQueries = [
            '最新公告查詢' => 'SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 10',
            '使用者公告查詢' => 'SELECT * FROM posts WHERE user_id = 1 AND deleted_at IS NULL',
            '附件聯合查詢' => 'SELECT a.*, p.title FROM attachments a JOIN posts p ON a.post_id = p.id',
            'IP 黑白名單查詢' => 'SELECT * FROM ip_lists WHERE type = 1 ORDER BY created_at DESC'
        ];
        
        foreach ($importantQueries as $name => $sql) {
            try {
                $plan = $this->pdo->query("EXPLAIN QUERY PLAN $sql")->fetchAll(PDO::FETCH_ASSOC);
                
                $usesIndex = false;
                $scanType = 'UNKNOWN';
                
                foreach ($plan as $step) {
                    if (isset($step['detail'])) {
                        if (strpos($step['detail'], 'USING INDEX') !== false) {
                            $usesIndex = true;
                            $scanType = 'INDEX';
                        } elseif (strpos($step['detail'], 'SCAN') !== false) {
                            $scanType = 'FULL_SCAN';
                        }
                    }
                }
                
                $queryPlans[] = [
                    'name' => $name,
                    'sql' => $sql,
                    'uses_index' => $usesIndex,
                    'scan_type' => $scanType,
                    'steps' => $plan,
                    'optimization_needed' => !$usesIndex && $scanType === 'FULL_SCAN'
                ];
                
            } catch (Exception $e) {
                $queryPlans[] = [
                    'name' => $name,
                    'sql' => $sql,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $queryPlans;
    }
    
    /**
     * 生成優化建議
     */
    private function generateOptimizationSuggestions(): array
    {
        $suggestions = [];
        
        // 基於慢查詢的建議
        if (isset($this->analysisResults['slow_queries'])) {
            foreach ($this->analysisResults['slow_queries'] as $query) {
                if ($query['is_slow'] ?? false) {
                    $suggestions[] = [
                        'type' => 'slow_query',
                        'priority' => 'high',
                        'description' => "'{$query['name']}' 查詢執行時間過長 ({$query['execution_time']}ms)",
                        'suggestion' => '考慮新增相關索引或優化查詢結構',
                        'estimated_improvement' => '50-80%'
                    ];
                }
            }
        }
        
        // 基於缺失索引的建議
        if (isset($this->analysisResults['missing_indexes'])) {
            $highPriorityIndexes = array_filter(
                $this->analysisResults['missing_indexes'], 
                fn($index) => $index['priority'] >= 8
            );
            
            foreach ($highPriorityIndexes as $index) {
                $suggestions[] = [
                    'type' => 'missing_index',
                    'priority' => 'high',
                    'description' => "表 '{$index['table']}' 缺少 '{$index['column']}' 欄位的索引",
                    'suggestion' => $index['create_sql'],
                    'estimated_improvement' => '70-90%'
                ];
            }
        }
        
        // 一般性建議
        $suggestions[] = [
            'type' => 'general',
            'priority' => 'medium',
            'description' => '實作查詢結果分頁',
            'suggestion' => '使用 LIMIT 和 OFFSET 限制單次查詢結果數量',
            'estimated_improvement' => '30-50%'
        ];
        
        $suggestions[] = [
            'type' => 'general',
            'priority' => 'medium',
            'description' => '實作批次操作',
            'suggestion' => '將多個單一操作合併為批次處理，減少資料庫往返',
            'estimated_improvement' => '40-60%'
        ];
        
        return $suggestions;
    }
    
    /**
     * 計算索引優先級
     */
    private function calculateIndexPriority(string $table, string $column): int
    {
        $priority = 5; // 基礎優先級
        
        // 高頻使用的表
        if (in_array($table, ['posts', 'users', 'attachments'])) {
            $priority += 3;
        }
        
        // 常用於 WHERE 條件的欄位
        if (in_array($column, ['id', 'uuid', 'user_id', 'created_at', 'deleted_at'])) {
            $priority += 2;
        }
        
        // 用於 ORDER BY 的欄位
        if (in_array($column, ['created_at', 'updated_at', 'publish_date'])) {
            $priority += 1;
        }
        
        return min($priority, 10); // 最高10分
    }
    
    /**
     * 計算表增長率（簡化版）
     */
    private function calculateGrowthRate(string $table): string
    {
        // 這裡簡化處理，實際應該比較歷史資料
        $growthRates = [
            'posts' => 'medium',
            'users' => 'low',
            'attachments' => 'high',
            'activity_logs' => 'high'
        ];
        
        return $growthRates[$table] ?? 'unknown';
    }
    
    /**
     * 生成分析報告
     */
    private function generateReport(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 資料庫效能分析報告\n";
        echo str_repeat("=", 80) . "\n\n";
        
        // 慢查詢報告
        if (!empty($this->analysisResults['slow_queries'])) {
            echo "🐌 慢查詢分析:\n";
            foreach ($this->analysisResults['slow_queries'] as $query) {
                $status = ($query['is_slow'] ?? false) ? '❌ 需要優化' : '✅ 效能良好';
                echo "  • {$query['name']}: {$query['execution_time']}ms {$status}\n";
            }
            echo "\n";
        }
        
        // 缺失索引報告
        if (!empty($this->analysisResults['missing_indexes'])) {
            echo "🔍 缺失索引 (前5個高優先級):\n";
            $topIndexes = array_slice($this->analysisResults['missing_indexes'], 0, 5);
            foreach ($topIndexes as $index) {
                echo "  • {$index['table']}.{$index['column']} (優先級: {$index['priority']}/10)\n";
                echo "    SQL: {$index['create_sql']}\n";
            }
            echo "\n";
        }
        
        // 表統計
        if (!empty($this->analysisResults['table_statistics'])) {
            echo "📈 表統計資訊:\n";
            foreach ($this->analysisResults['table_statistics'] as $stat) {
                if (isset($stat['error'])) {
                    echo "  • {$stat['table']}: 錯誤 - {$stat['error']}\n";
                } else {
                    echo "  • {$stat['table']}: {$stat['row_count']} 筆記錄, {$stat['index_count']} 個索引\n";
                }
            }
            echo "\n";
        }
        
        // 優化建議
        if (!empty($this->analysisResults['optimization_suggestions'])) {
            echo "💡 優化建議:\n";
            foreach ($this->analysisResults['optimization_suggestions'] as $suggestion) {
                $priority = match($suggestion['priority']) {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪'
                };
                echo "  {$priority} {$suggestion['description']}\n";
                echo "     建議: {$suggestion['suggestion']}\n";
                echo "     預期改善: {$suggestion['estimated_improvement']}\n\n";
            }
        }
        
        echo "✅ 分析完成！報告已保存到 storage/logs/database_performance_analysis.json\n\n";
        
        // 保存詳細報告到檔案
        $reportPath = __DIR__ . '/../storage/logs/database_performance_analysis.json';
        file_put_contents($reportPath, json_encode($this->analysisResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// 執行分析
if (basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    try {
        $analyzer = new DatabasePerformanceAnalyzer();
        $results = $analyzer->runFullAnalysis();
        
        echo "🎯 下一步建議:\n";
        echo "1. 執行高優先級索引建立 SQL\n";
        echo "2. 實作查詢結果快取\n";
        echo "3. 優化慢查詢結構\n";
        echo "4. 實作批次操作\n";
        echo "5. 新增查詢分頁功能\n\n";
        
    } catch (Exception $e) {
        echo "❌ 分析失敗: " . $e->getMessage() . "\n";
        exit(1);
    }
}