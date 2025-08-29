<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Services;

use App\Shared\Contracts\CacheInterface;
use Redis;
use Throwable;

/**
 * 快取監控服務.
 *
 * 提供快取效能監控、統計分析和除錯資訊
 */
final class CacheMonitoringService
{
    private const string STATS_KEY_PREFIX = 'cache:monitoring:';

    private const string HITS_KEY = self::STATS_KEY_PREFIX . 'hits';

    private const string MISSES_KEY = self::STATS_KEY_PREFIX . 'misses';

    private const string OPERATIONS_KEY = self::STATS_KEY_PREFIX . 'operations';

    private const string ERRORS_KEY = self::STATS_KEY_PREFIX . 'errors';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * 記錄快取命中.
     */
    public function recordHit(string $key): void
    {
        try {
            $this->cache->increment(self::HITS_KEY);
            $this->recordOperation('hit', $key);
        } catch (Throwable) {
            // 監控失敗不應影響主要功能
        }
    }

    /**
     * 記錄快取未命中.
     */
    public function recordMiss(string $key): void
    {
        try {
            $this->cache->increment(self::MISSES_KEY);
            $this->recordOperation('miss', $key);
        } catch (Throwable) {
            // 監控失敗不應影響主要功能
        }
    }

    /**
     * 記錄快取操作錯誤.
     */
    public function recordError(string $operation, string $error): void
    {
        try {
            $this->cache->increment(self::ERRORS_KEY);
            $errorData = [
                'operation' => $operation,
                'error' => $error,
                'timestamp' => time(),
            ];

            // 記錄最近的錯誤（保留最新 10 個）
            $recentErrors = $this->cache->get('cache:monitoring:recent_errors') ?? [];
            array_unshift($recentErrors, $errorData);
            $recentErrors = array_slice($recentErrors, 0, 10);
            $this->cache->set('cache:monitoring:recent_errors', $recentErrors, 3600);
        } catch (Throwable) {
            // 監控失敗不應影響主要功能
        }
    }

    /**
     * 記錄快取操作.
     */
    private function recordOperation(string $type, string $key): void
    {
        try {
            $operations = $this->cache->get(self::OPERATIONS_KEY) ?? [];
            $currentHour = date('Y-m-d H:00');

            if (!isset($operations[$currentHour])) {
                $operations[$currentHour] = ['hits' => 0, 'misses' => 0];
            }

            $operations[$currentHour][$type === 'hit' ? 'hits' : 'misses']++;

            // 只保留最近 24 小時的資料
            $cutoff = date('Y-m-d H:00', strtotime('-24 hours'));
            foreach ($operations as $hour => $stats) {
                if ($hour < $cutoff) {
                    unset($operations[$hour]);
                }
            }

            $this->cache->set(self::OPERATIONS_KEY, $operations, 86400);
        } catch (Throwable) {
            // 操作記錄失敗不應影響主要功能
        }
    }

    /**
     * 取得快取統計資訊.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        try {
            $hits = $this->cache->get(self::HITS_KEY) ?? 0;
            $misses = $this->cache->get(self::MISSES_KEY) ?? 0;
            $errors = $this->cache->get(self::ERRORS_KEY) ?? 0;
            $operations = $this->cache->get(self::OPERATIONS_KEY) ?? [];
            $recentErrors = $this->cache->get('cache:monitoring:recent_errors') ?? [];

            $total = $hits + $misses;
            $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

            return [
                'hit_rate' => [
                    'percentage' => round($hitRate, 2),
                    'hits' => (int) $hits,
                    'misses' => (int) $misses,
                    'total' => $total,
                ],
                'errors' => [
                    'total' => (int) $errors,
                    'recent' => $recentErrors,
                ],
                'hourly_operations' => $operations,
                'summary' => [
                    'status' => $hitRate >= 80 ? 'excellent' : ($hitRate >= 60 ? 'good' : 'needs_attention'),
                    'health_score' => $this->calculateHealthScore($hitRate, (int) $errors, $total),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'error' => 'Failed to retrieve cache statistics: ' . $e->getMessage(),
                'hit_rate' => ['percentage' => 0, 'hits' => 0, 'misses' => 0, 'total' => 0],
                'errors' => ['total' => 0, 'recent' => []],
                'hourly_operations' => [],
                'summary' => ['status' => 'error', 'health_score' => 0],
            ];
        }
    }

    /**
     * 計算快取健康分數 (0-100).
     */
    private function calculateHealthScore(float $hitRate, int $errors, int $total): int
    {
        // 基礎分數根據命中率
        $score = min(100, $hitRate);

        // 錯誤率懲罰
        if ($total > 0) {
            $errorRate = ($errors / $total) * 100;
            $score -= min(30, $errorRate * 2); // 最多扣30分
        }

        // 活動量獎勵（有一定操作量的系統更健康）
        if ($total >= 100) {
            $score += min(5, $total / 1000); // 最多加5分
        }

        return max(0, min(100, (int) round($score)));
    }

    /**
     * 重置所有統計資料.
     */
    public function resetStats(): bool
    {
        try {
            $this->cache->delete(self::HITS_KEY);
            $this->cache->delete(self::MISSES_KEY);
            $this->cache->delete(self::ERRORS_KEY);
            $this->cache->delete(self::OPERATIONS_KEY);
            $this->cache->delete('cache:monitoring:recent_errors');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 取得快取鍵分析.
     *
     * @return array<string, mixed>
     */
    public function analyzeKeys(): array
    {
        try {
            // 這個功能需要 Redis 的 KEYS 命令，在生產環境中應該謹慎使用
            if (!method_exists($this->cache, 'getRedisInstance')) {
                return ['error' => 'Key analysis not supported for this cache backend'];
            }

            /** @var Redis $redis */
            $redis = $this->cache->getRedisInstance();
            $prefix = method_exists($this->cache, 'getPrefix') ? $this->cache->getPrefix() : '';

            $keys = $redis->keys($prefix . '*');

            if (empty($keys)) {
                return ['total_keys' => 0, 'patterns' => [], 'sizes' => []];
            }

            $patterns = [];
            $sizes = [];

            foreach ($keys as $key) {
                // 移除前綴以便分析
                $cleanKey = $prefix ? str_replace($prefix, '', $key) : $key;

                // 分析鍵模式
                $pattern = $this->extractKeyPattern($cleanKey);
                $patterns[$pattern] = ($patterns[$pattern] ?? 0) + 1;

                // 取得鍵的記憶體使用量（如果可用）
                try {
                    $memory = $redis->memory('USAGE', $key);
                    $sizes[$pattern] = ($sizes[$pattern] ?? 0) + $memory;
                } catch (Throwable) {
                    // 如果無法取得記憶體使用量，忽略此資訊
                }
            }

            // 按使用量排序
            arsort($patterns);
            arsort($sizes);

            return [
                'total_keys' => count($keys),
                'patterns' => $patterns,
                'memory_usage' => $sizes,
                'top_patterns' => array_slice($patterns, 0, 10, true),
            ];
        } catch (Throwable $e) {
            return ['error' => 'Key analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * 從快取鍵中提取模式.
     */
    private function extractKeyPattern(string $key): string
    {
        // 將數字替換為 {id}，UUID 替換為 {uuid} 等
        $pattern = preg_replace('/\d+/', '{id}', $key);
        $pattern = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '{uuid}', $pattern);
        $pattern = preg_replace('/[0-9a-f]{32}/', '{hash}', $pattern);

        return $pattern ?: 'unknown';
    }

    /**
     * 產生快取報告.
     *
     * @return array<string, mixed>
     */
    public function generateReport(): array
    {
        $stats = $this->getStats();
        $keyAnalysis = $this->analyzeKeys();

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'performance' => $stats,
            'key_analysis' => $keyAnalysis,
            'recommendations' => $this->generateRecommendations($stats, $keyAnalysis),
        ];
    }

    /**
     * 基於統計資料產生建議.
     *
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $keyAnalysis
     * @return array<string>
     */
    private function generateRecommendations(array $stats, array $keyAnalysis): array
    {
        $recommendations = [];

        // 命中率建議
        $hitRate = $stats['hit_rate']['percentage'] ?? 0;
        if ($hitRate < 60) {
            $recommendations[] = '快取命中率過低 (' . $hitRate . '%)，建議檢查快取策略和 TTL 設定';
        } elseif ($hitRate < 80) {
            $recommendations[] = '快取命中率可以改善 (' . $hitRate . '%)，考慮增加 TTL 或優化快取鍵設計';
        }

        // 錯誤率建議
        $errorCount = $stats['errors']['total'] ?? 0;
        $totalOps = $stats['hit_rate']['total'] ?? 0;
        if ($totalOps > 0 && ($errorCount / $totalOps) > 0.05) {
            $recommendations[] = '快取錯誤率過高，建議檢查 Redis 連接和配置';
        }

        // 鍵數量建議
        $totalKeys = $keyAnalysis['total_keys'] ?? 0;
        if ($totalKeys > 10000) {
            $recommendations[] = '快取鍵數量過多 (' . $totalKeys . ')，考慮實作過期策略或清理機制';
        }

        if (empty($recommendations)) {
            $recommendations[] = '快取系統運行良好，無需特別優化';
        }

        return $recommendations;
    }
}
