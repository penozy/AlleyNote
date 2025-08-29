<?php

declare(strict_types=1);

namespace App\Application\Services\Analytics;

use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Application\DTOs\Analytics\UserBehaviorPatternDTO;
use App\Application\DTOs\Analytics\AnomalyDetectionResultDTO;
use App\Application\DTOs\Analytics\UserRiskScoreDTO;
use App\Domain\Common\ValueObjects\AlertSeverity;
use Psr\Log\LoggerInterface;

/**
 * 使用者行為分析服務
 * 
 * 提供智能化的使用者行為模式分析、異常檢測和風險評估
 */
final readonly class UserBehaviorAnalysisService
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * 分析使用者活動模式
     */
    public function analyzeUserActivityPattern(
        string $userId, 
        int $daysBack = 30
    ): UserBehaviorPatternDTO {
        $startTime = new \DateTime("-{$daysBack} days");
        $endTime = new \DateTime();
        
        $this->logger->info('開始分析使用者活動模式', [
            'user_id' => $userId,
            'days_back' => $daysBack,
            'analysis_period' => $startTime->format('Y-m-d') . ' to ' . $endTime->format('Y-m-d')
        ]);

        // 取得使用者活動記錄
        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            $startTime,
            $endTime,
            10000,
            0
        );

        if (empty($activities)) {
            return new UserBehaviorPatternDTO(
                userId: $userId,
                analysisStartTime: $startTime,
                analysisEndTime: $endTime,
                totalActivities: 0,
                averageActivitiesPerDay: 0.0,
                mostActiveHours: [],
                mostActiveWeekdays: [],
                topCategories: [],
                activityFrequencyTrend: [],
                behaviorConsistencyScore: 0.0,
                uniqueIpAddresses: [],
                suspiciousPatterns: []
            );
        }

        // 分析時間分佈模式
        $hourlyDistribution = $this->analyzeHourlyDistribution($activities);
        $weekdayDistribution = $this->analyzeWeekdayDistribution($activities);
        
        // 分析功能使用偏好
        $categoryDistribution = $this->analyzeCategoryDistribution($activities);
        
        // 分析活動頻率趨勢
        $frequencyTrend = $this->analyzeActivityFrequencyTrend($activities, $daysBack);
        
        // 計算行為一致性分數
        $consistencyScore = $this->calculateBehaviorConsistencyScore($activities);
        
        // 分析 IP 地址分佈
        $ipAnalysis = $this->analyzeIpAddresses($activities);
        
        // 檢測可疑模式
        $suspiciousPatterns = $this->detectSuspiciousPatterns($activities);

        return new UserBehaviorPatternDTO(
            userId: $userId,
            analysisStartTime: $startTime,
            analysisEndTime: $endTime,
            totalActivities: count($activities),
            averageActivitiesPerDay: count($activities) / $daysBack,
            mostActiveHours: $this->getTopItems($hourlyDistribution, 3),
            mostActiveWeekdays: $this->getTopItems($weekdayDistribution, 3),
            topCategories: $this->getTopItems($categoryDistribution, 5),
            activityFrequencyTrend: $frequencyTrend,
            behaviorConsistencyScore: $consistencyScore,
            uniqueIpAddresses: array_keys($ipAnalysis),
            suspiciousPatterns: $suspiciousPatterns
        );
    }

    /**
     * 檢測使用者異常行為
     */
    public function detectAnomalousActivities(
        string $userId, 
        int $analysisWindow = 7
    ): AnomalyDetectionResultDTO {
        $this->logger->info('開始檢測使用者異常行為', [
            'user_id' => $userId,
            'analysis_window' => $analysisWindow
        ]);

        // 取得基線行為資料和分析窗口資料
        $baselineActivities = $this->getBaselineActivities($userId, 30);
        $recentActivities = $this->getRecentActivities($userId, $analysisWindow);

        if (empty($baselineActivities) || empty($recentActivities)) {
            return new AnomalyDetectionResultDTO(
                userId: $userId,
                analysisTimestamp: new \DateTime(),
                anomaliesDetected: [],
                anomalyScore: 0.0,
                riskLevel: AlertSeverity::INFO,
                recommendations: ['資料不足，無法進行異常檢測分析']
            );
        }

        $anomalies = [];
        $totalAnomalyScore = 0.0;

        // 執行各種異常檢測
        $detectionMethods = [
            'detectFrequencyAnomaly',
            'detectTimePatternAnomaly', 
            'detectUsagePatternAnomaly',
            'detectIpAddressAnomaly',
            'detectFailureRateAnomaly'
        ];

        foreach ($detectionMethods as $method) {
            $anomaly = $this->$method($baselineActivities, $recentActivities);
            if ($anomaly) {
                $anomalies[] = $anomaly;
                $totalAnomalyScore += $anomaly['severity_score'];
            }
        }

        // 計算整體異常分數和風險等級
        $normalizedScore = min($totalAnomalyScore / 100, 1.0);
        $riskLevel = $this->calculateRiskLevel($normalizedScore);
        $recommendations = $this->generateAnomalyRecommendations($anomalies, $riskLevel);

        return new AnomalyDetectionResultDTO(
            userId: $userId,
            analysisTimestamp: new \DateTime(),
            anomaliesDetected: $anomalies,
            anomalyScore: $normalizedScore,
            riskLevel: $riskLevel,
            recommendations: $recommendations
        );
    }

    /**
     * 計算使用者風險評分
     */
    public function calculateUserRiskScore(string $userId): UserRiskScoreDTO
    {
        $this->logger->info('開始計算使用者風險評分', ['user_id' => $userId]);

        $riskFactors = [];
        $totalScore = 0.0;

        // 風險因子評估配置
        $riskAssessments = [
            ['method' => 'calculateAnomalyRisk', 'weight' => 0.3, 'key' => 'anomaly_behavior', 'desc' => '異常行為檢測分數'],
            ['method' => 'calculateSecurityEventRisk', 'weight' => 0.25, 'key' => 'security_events', 'desc' => '安全事件歷史記錄'],
            ['method' => 'calculateIpDiversityRisk', 'weight' => 0.15, 'key' => 'ip_diversity', 'desc' => 'IP 位址變化頻率'],
            ['method' => 'calculateActivityFrequencyRisk', 'weight' => 0.15, 'key' => 'activity_frequency', 'desc' => '活動頻率變化'],
            ['method' => 'calculateFailureRateRisk', 'weight' => 0.15, 'key' => 'failure_rate', 'desc' => '操作失敗率']
        ];

        foreach ($riskAssessments as $assessment) {
            $score = $this->{$assessment['method']}($userId);
            $contribution = $score * $assessment['weight'];
            
            $riskFactors[$assessment['key']] = [
                'score' => $score,
                'weight' => $assessment['weight'],
                'contribution' => $contribution,
                'description' => $assessment['desc']
            ];
            
            $totalScore += $contribution;
        }

        // 確保分數在 0-1 範圍內
        $totalScore = max(0.0, min(1.0, $totalScore));
        $riskLevel = $this->calculateRiskLevel($totalScore);
        $recommendations = $this->generateRiskMitigationRecommendations($riskFactors, $riskLevel);

        return new UserRiskScoreDTO(
            userId: $userId,
            calculationTimestamp: new \DateTime(),
            totalRiskScore: $totalScore,
            riskLevel: $riskLevel,
            riskFactors: $riskFactors,
            recommendations: $recommendations
        );
    }

    /**
     * 分析活動趨勢
     */
    public function analyzeActivityTrends(string $userId, int $daysBack = 90): array
    {
        $this->logger->info('開始分析活動趨勢', [
            'user_id' => $userId,
            'days_back' => $daysBack
        ]);

        $startTime = new \DateTime("-{$daysBack} days");
        $endTime = new \DateTime();

        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            $startTime,
            $endTime,
            10000,
            0
        );

        if (empty($activities)) {
            return [
                'trend_analysis' => 'insufficient_data',
                'daily_trends' => [],
                'weekly_patterns' => [],
                'growth_rate' => 0.0,
                'seasonal_patterns' => []
            ];
        }

        return [
            'user_id' => $userId,
            'analysis_period' => [
                'start' => $startTime->format('Y-m-d'),
                'end' => $endTime->format('Y-m-d'),
                'days' => $daysBack
            ],
            'daily_trends' => $this->calculateDailyTrends($activities, $daysBack),
            'weekly_patterns' => $this->calculateWeeklyPatterns($activities),
            'growth_rate' => $this->calculateGrowthRate($this->calculateDailyTrends($activities, $daysBack)),
            'seasonal_patterns' => $this->calculateSeasonalPatterns($activities),
            'trend_summary' => $this->generateTrendSummary($this->calculateDailyTrends($activities, $daysBack), $this->calculateGrowthRate($this->calculateDailyTrends($activities, $daysBack)))
        ];
    }

    // === 私有方法實作 ===

    /**
     * 基礎分析方法
     */
    private function analyzeHourlyDistribution(array $activities): array
    {
        $distribution = array_fill(0, 24, 0);
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $hour = (int) (new \DateTime($timestamp))->format('H');
                $distribution[$hour]++;
            }
        }
        
        return $distribution;
    }

    private function analyzeWeekdayDistribution(array $activities): array
    {
        $distribution = array_fill(1, 7, 0);
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $weekday = (int) (new \DateTime($timestamp))->format('N');
                $distribution[$weekday]++;
            }
        }
        
        return $distribution;
    }

    private function analyzeCategoryDistribution(array $activities): array
    {
        $distribution = [];
        
        foreach ($activities as $activity) {
            $category = $activity['category'] ?? 'unknown';
            $distribution[$category] = ($distribution[$category] ?? 0) + 1;
        }
        
        return $distribution;
    }

    private function analyzeActivityFrequencyTrend(array $activities, int $daysBack): array
    {
        $dailyCounts = [];
        $today = new \DateTime();
        
        // 初始化每日計數
        for ($i = 0; $i < $daysBack; $i++) {
            $date = clone $today;
            $date->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            $dailyCounts[$dateStr] = 0;
        }
        
        // 統計每日活動數量
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $dateStr = (new \DateTime($timestamp))->format('Y-m-d');
                if (isset($dailyCounts[$dateStr])) {
                    $dailyCounts[$dateStr]++;
                }
            }
        }
        
        ksort($dailyCounts);
        return $dailyCounts;
    }

    private function calculateBehaviorConsistencyScore(array $activities): float
    {
        if (count($activities) < 7) {
            return 0.0;
        }

        $dailyActivityTrend = $this->analyzeActivityFrequencyTrend($activities, 30);
        $dailyCounts = array_values($dailyActivityTrend);
        
        $mean = array_sum($dailyCounts) / count($dailyCounts);
        if ($mean == 0) {
            return 0.0;
        }
        
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $dailyCounts)) / count($dailyCounts);
        
        $standardDeviation = sqrt($variance);
        $coefficientOfVariation = $standardDeviation / $mean;
        
        return max(0.0, min(1.0, 1.0 - $coefficientOfVariation));
    }

    private function analyzeIpAddresses(array $activities): array
    {
        $ipCounts = [];
        
        foreach ($activities as $activity) {
            $ip = $activity['ip_address'] ?? 'unknown';
            $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
        }
        
        arsort($ipCounts);
        return $ipCounts;
    }

    private function detectSuspiciousPatterns(array $activities): array
    {
        $patterns = [];
        
        // 檢測短時間內大量活動
        $recentActivities = array_filter($activities, function($activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if (!$timestamp) return false;
            
            $activityTime = new \DateTime($timestamp);
            $oneHourAgo = (new \DateTime())->modify('-1 hour');
            return $activityTime > $oneHourAgo;
        });
        
        if (count($recentActivities) > 100) {
            $patterns[] = [
                'type' => 'high_frequency_burst',
                'severity' => AlertSeverity::WARNING,
                'description' => '近1小時內活動異常頻繁',
                'count' => count($recentActivities)
            ];
        }
        
        // 檢測失敗率過高
        $failedActivities = array_filter($activities, function($activity) {
            return !($activity['is_success'] ?? true) || !($activity['success'] ?? true);
        });
        
        if (count($activities) > 0) {
            $failureRate = count($failedActivities) / count($activities);
            if ($failureRate > 0.3) {
                $patterns[] = [
                    'type' => 'high_failure_rate',
                    'severity' => AlertSeverity::CRITICAL,
                    'description' => '操作失敗率過高',
                    'failure_rate' => round($failureRate * 100, 2)
                ];
            }
        }
        
        return $patterns;
    }

    private function getTopItems(array $distribution, int $n): array
    {
        arsort($distribution);
        return array_slice($distribution, 0, $n, true);
    }

    /**
     * 異常檢測方法
     */
    private function getBaselineActivities(string $userId, int $days): array
    {
        $startTime = new \DateTime("-{$days} days");
        $endTime = new \DateTime("-7 days");
        
        return $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            $startTime,
            $endTime,
            5000,
            0
        );
    }
    
    private function getRecentActivities(string $userId, int $days): array
    {
        $startTime = new \DateTime("-{$days} days");
        $endTime = new \DateTime();
        
        return $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            $startTime,
            $endTime,
            5000,
            0
        );
    }
    
    private function detectFrequencyAnomaly(array $baseline, array $recent): ?array
    {
        $baselineCount = count($baseline);
        $recentCount = count($recent);
        
        if ($baselineCount === 0) {
            return null;
        }
        
        $frequencyChange = ($recentCount - $baselineCount) / $baselineCount;
        
        if (abs($frequencyChange) > 2.0) {
            return [
                'type' => 'frequency_anomaly',
                'severity_score' => min(abs($frequencyChange) * 15, 50),
                'description' => $frequencyChange > 0 ? '活動頻率異常增加' : '活動頻率異常減少',
                'baseline_count' => $baselineCount,
                'recent_count' => $recentCount,
                'change_ratio' => round($frequencyChange, 2)
            ];
        }
        
        return null;
    }
    
    private function detectTimePatternAnomaly(array $baseline, array $recent): ?array
    {
        $baselineHours = $this->extractHourDistribution($baseline);
        $recentHours = $this->extractHourDistribution($recent);
        
        $similarity = $this->calculateDistributionSimilarity($baselineHours, $recentHours);
        
        if ($similarity < 0.5) {
            return [
                'type' => 'time_pattern_anomaly',
                'severity_score' => (1 - $similarity) * 40,
                'description' => '活動時間模式發生明顯變化',
                'similarity_score' => round($similarity, 3),
                'baseline_pattern' => $baselineHours,
                'recent_pattern' => $recentHours
            ];
        }
        
        return null;
    }
    
    private function detectUsagePatternAnomaly(array $baseline, array $recent): ?array
    {
        $baselineCategories = $this->extractCategoryDistribution($baseline);
        $recentCategories = $this->extractCategoryDistribution($recent);
        
        $similarity = $this->calculateDistributionSimilarity($baselineCategories, $recentCategories);
        
        if ($similarity < 0.6) {
            return [
                'type' => 'usage_pattern_anomaly',
                'severity_score' => (1 - $similarity) * 30,
                'description' => '功能使用模式發生明顯變化',
                'similarity_score' => round($similarity, 3),
                'baseline_usage' => $baselineCategories,
                'recent_usage' => $recentCategories
            ];
        }
        
        return null;
    }
    
    private function detectIpAddressAnomaly(array $baseline, array $recent): ?array
    {
        $baselineIps = $this->extractUniqueIps($baseline);
        $recentIps = $this->extractUniqueIps($recent);
        
        $newIps = array_diff($recentIps, $baselineIps);
        $newIpCount = count($newIps);
        
        if ($newIpCount > 3) {
            return [
                'type' => 'ip_address_anomaly',
                'severity_score' => min($newIpCount * 10, 40),
                'description' => '發現異常多的新IP位址',
                'new_ip_count' => $newIpCount,
                'new_ips' => array_slice($newIps, 0, 5),
                'baseline_ip_count' => count($baselineIps)
            ];
        }
        
        return null;
    }
    
    private function detectFailureRateAnomaly(array $baseline, array $recent): ?array
    {
        $baselineFailureRate = $this->calculateFailureRate($baseline);
        $recentFailureRate = $this->calculateFailureRate($recent);
        
        $failureRateIncrease = $recentFailureRate - $baselineFailureRate;
        
        if ($failureRateIncrease > 0.2) {
            return [
                'type' => 'failure_rate_anomaly',
                'severity_score' => $failureRateIncrease * 100,
                'description' => '操作失敗率異常增加',
                'baseline_failure_rate' => round($baselineFailureRate, 3),
                'recent_failure_rate' => round($recentFailureRate, 3),
                'rate_increase' => round($failureRateIncrease, 3)
            ];
        }
        
        return null;
    }

    /**
     * 風險評估方法
     */
    private function calculateAnomalyRisk(string $userId): float
    {
        $anomalyResult = $this->detectAnomalousActivities($userId);
        return $anomalyResult->anomalyScore;
    }
    
    private function calculateSecurityEventRisk(string $userId): float
    {
        $securityEvents = $this->activityLogRepository->findFailedActivities(100, 0, (int) $userId);
        
        if (empty($securityEvents)) {
            return 0.0;
        }
        
        $recentSecurityEvents = array_filter($securityEvents, function($event) {
            $eventTime = new \DateTime($event['occurred_at'] ?? $event['created_at'] ?? '');
            $thirtyDaysAgo = new \DateTime('-30 days');
            return $eventTime > $thirtyDaysAgo;
        });
        
        $recentCount = count($recentSecurityEvents);
        return min($recentCount * 0.1, 1.0);
    }
    
    private function calculateIpDiversityRisk(string $userId): float
    {
        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            new \DateTime('-7 days'),
            new \DateTime(),
            1000,
            0
        );
        
        $uniqueIps = $this->extractUniqueIps($activities);
        $ipCount = count($uniqueIps);
        
        return match (true) {
            $ipCount >= 10 => 1.0,
            $ipCount >= 5 => 0.7,
            $ipCount >= 3 => 0.4,
            $ipCount >= 2 => 0.2,
            default => 0.0
        };
    }
    
    private function calculateActivityFrequencyRisk(string $userId): float
    {
        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            new \DateTime('-24 hours'),
            new \DateTime(),
            1000,
            0
        );
        
        $activityCount = count($activities);
        
        return match (true) {
            $activityCount >= 1000 => 1.0,
            $activityCount >= 500 => 0.8,
            $activityCount >= 200 => 0.5,
            $activityCount >= 100 => 0.3,
            default => 0.0
        };
    }
    
    private function calculateFailureRateRisk(string $userId): float
    {
        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            new \DateTime('-7 days'),
            new \DateTime(),
            1000,
            0
        );
        
        if (empty($activities)) {
            return 0.0;
        }
        
        $failureRate = $this->calculateFailureRate($activities);
        return min($failureRate * 2, 1.0);
    }

    /**
     * 趨勢分析方法
     */
    private function calculateDailyTrends(array $activities, int $days): array
    {
        $dailyCounts = [];
        $today = new \DateTime();
        
        for ($i = 0; $i < $days; $i++) {
            $date = clone $today;
            $date->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            $dailyCounts[$dateStr] = 0;
        }
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $dateStr = (new \DateTime($timestamp))->format('Y-m-d');
                if (isset($dailyCounts[$dateStr])) {
                    $dailyCounts[$dateStr]++;
                }
            }
        }
        
        ksort($dailyCounts);
        return $dailyCounts;
    }
    
    private function calculateWeeklyPatterns(array $activities): array
    {
        $weekdayPatterns = array_fill(1, 7, 0);
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $weekday = (int) (new \DateTime($timestamp))->format('N');
                $weekdayPatterns[$weekday]++;
            }
        }
        
        return $weekdayPatterns;
    }
    
    private function calculateGrowthRate(array $dailyTrends): float
    {
        $values = array_values($dailyTrends);
        $count = count($values);
        
        if ($count < 2) {
            return 0.0;
        }
        
        $firstHalf = array_slice($values, 0, intval($count / 2));
        $secondHalf = array_slice($values, intval($count / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        if ($firstAvg == 0) {
            return 0.0;
        }
        
        return ($secondAvg - $firstAvg) / $firstAvg;
    }
    
    private function calculateSeasonalPatterns(array $activities): array
    {
        $monthlyPattern = [];
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $month = (new \DateTime($timestamp))->format('n');
                $monthlyPattern[$month] = ($monthlyPattern[$month] ?? 0) + 1;
            }
        }
        
        ksort($monthlyPattern);
        return $monthlyPattern;
    }
    
    private function generateTrendSummary(array $dailyTrends, float $growthRate): string
    {
        if (abs($growthRate) < 0.1) {
            return 'stable';
        } elseif ($growthRate > 0.1) {
            return 'increasing';
        } else {
            return 'decreasing';
        }
    }

    /**
     * 輔助方法
     */
    private function calculateRiskLevel(float $score): AlertSeverity
    {
        return match (true) {
            $score >= 0.8 => AlertSeverity::CRITICAL,
            $score >= 0.6 => AlertSeverity::WARNING,
            $score >= 0.3 => AlertSeverity::INFO,
            default => AlertSeverity::DEBUG
        };
    }
    
    private function generateAnomalyRecommendations(array $anomalies, AlertSeverity $riskLevel): array
    {
        $recommendations = [];
        
        if ($riskLevel === AlertSeverity::CRITICAL) {
            $recommendations[] = '立即審查使用者帳戶和最近活動';
            $recommendations[] = '考慮暫時限制帳戶權限';
        }
        
        if ($riskLevel === AlertSeverity::WARNING) {
            $recommendations[] = '加強監控此使用者的後續活動';
            $recommendations[] = '驗證使用者身份';
        }
        
        return $recommendations;
    }
    
    private function generateRiskMitigationRecommendations(array $riskFactors, AlertSeverity $riskLevel): array
    {
        $recommendations = [];
        
        if ($riskLevel === AlertSeverity::CRITICAL) {
            $recommendations[] = '立即檢查使用者帳戶狀態';
            $recommendations[] = '考慮暫時限制帳戶權限';
            $recommendations[] = '啟動安全事件調查流程';
        } elseif ($riskLevel === AlertSeverity::WARNING) {
            $recommendations[] = '加強監控此使用者的後續活動';
            $recommendations[] = '驗證使用者近期登入的合理性';
        }
        
        foreach ($riskFactors as $factor => $data) {
            if ($data['contribution'] > 0.2) {
                switch ($factor) {
                    case 'anomaly_behavior':
                        $recommendations[] = '詳細檢查異常行為模式';
                        break;
                    case 'ip_diversity':
                        $recommendations[] = '驗證多個IP位址的來源';
                        break;
                    case 'failure_rate':
                        $recommendations[] = '檢查操作失敗的原因';
                        break;
                }
            }
        }
        
        return array_unique($recommendations);
    }
    
    private function extractHourDistribution(array $activities): array
    {
        $hours = array_fill(0, 24, 0);
        
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $hour = (int) (new \DateTime($timestamp))->format('H');
                $hours[$hour]++;
            }
        }
        
        return $hours;
    }
    
    private function extractCategoryDistribution(array $activities): array
    {
        $categories = [];
        
        foreach ($activities as $activity) {
            $category = $activity['category'] ?? 'unknown';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        
        return $categories;
    }
    
    private function extractUniqueIps(array $activities): array
    {
        $ips = [];
        
        foreach ($activities as $activity) {
            $ip = $activity['ip_address'] ?? '';
            if ($ip && !in_array($ip, $ips)) {
                $ips[] = $ip;
            }
        }
        
        return $ips;
    }
    
    private function calculateDistributionSimilarity(array $dist1, array $dist2): float
    {
        $keys = array_unique(array_merge(array_keys($dist1), array_keys($dist2)));
        
        $vector1 = [];
        $vector2 = [];
        
        foreach ($keys as $key) {
            $vector1[] = $dist1[$key] ?? 0;
            $vector2[] = $dist2[$key] ?? 0;
        }
        
        return $this->cosineSimilarity($vector1, $vector2);
    }
    
    private function cosineSimilarity(array $vector1, array $vector2): float
    {
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }
    
    private function calculateFailureRate(array $activities): float
    {
        if (empty($activities)) {
            return 0.0;
        }
        
        $failedCount = 0;
        foreach ($activities as $activity) {
            if (!($activity['is_success'] ?? true) || !($activity['success'] ?? true)) {
                $failedCount++;
            }
        }
        
        return $failedCount / count($activities);
    }
}