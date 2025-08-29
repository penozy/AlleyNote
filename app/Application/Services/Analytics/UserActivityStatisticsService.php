<?php

declare(strict_types=1);

namespace App\Application\Services\Analytics;

use App\Domains\Security\Contracts\ActivityLogRepositoryInterface;
use App\Application\DTOs\Analytics\ActivityStatisticsDTO;
use App\Application\DTOs\Analytics\UserActivitySummaryDTO;
use App\Domain\Common\ValueObjects\ActivityCategory;
use Psr\Log\LoggerInterface;

/**
 * 使用者活動統計服務
 * 
 * 提供各種使用者活動統計和分析功能
 */
final readonly class UserActivityStatisticsService
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * 取得使用者活動統計
     * 
     * @param string $userId 使用者 ID
     * @param int $daysBack 回溯天數
     */
    public function getUserActivityStatistics(string $userId, int $daysBack = 30): ActivityStatisticsDTO
    {
        $this->logger->info('開始計算使用者活動統計', [
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
            return new ActivityStatisticsDTO(
                userId: $userId,
                analysisStartTime: $startTime,
                analysisEndTime: $endTime,
                totalActivities: 0,
                successfulActivities: 0,
                failedActivities: 0,
                successRate: 0.0,
                averageActivitiesPerDay: 0.0,
                peakActivityDay: null,
                peakActivityHour: null,
                categoryDistribution: [],
                hourlyDistribution: array_fill(0, 24, 0),
                weekdayDistribution: array_fill(1, 7, 0),
                monthlyTrend: [],
                mostActiveSession: null,
                averageSessionDuration: 0.0
            );
        }

        // 基本統計
        $totalActivities = count($activities);
        $successfulActivities = $this->countSuccessfulActivities($activities);
        $failedActivities = $totalActivities - $successfulActivities;
        $successRate = $totalActivities > 0 ? $successfulActivities / $totalActivities : 0.0;

        // 每日平均活動
        $averagePerDay = $totalActivities / $daysBack;

        // 時間分佈分析
        $hourlyDistribution = $this->calculateHourlyDistribution($activities);
        $weekdayDistribution = $this->calculateWeekdayDistribution($activities);

        // 尖峰時段分析
        $peakActivityHour = $this->findPeakHour($hourlyDistribution);
        $peakActivityDay = $this->findPeakWeekday($weekdayDistribution);

        // 類別分佈
        $categoryDistribution = $this->calculateCategoryDistribution($activities);

        // 月度趨勢（如果資料跨月）
        $monthlyTrend = $this->calculateMonthlyTrend($activities, $daysBack);

        // 會話分析
        $sessionAnalysis = $this->analyzeUserSessions($activities);

        return new ActivityStatisticsDTO(
            userId: $userId,
            analysisStartTime: $startTime,
            analysisEndTime: $endTime,
            totalActivities: $totalActivities,
            successfulActivities: $successfulActivities,
            failedActivities: $failedActivities,
            successRate: $successRate,
            averageActivitiesPerDay: $averagePerDay,
            peakActivityDay: $peakActivityDay,
            peakActivityHour: $peakActivityHour,
            categoryDistribution: $categoryDistribution,
            hourlyDistribution: $hourlyDistribution,
            weekdayDistribution: $weekdayDistribution,
            monthlyTrend: $monthlyTrend,
            mostActiveSession: $sessionAnalysis['most_active_session'],
            averageSessionDuration: $sessionAnalysis['average_duration']
        );
    }

    /**
     * 取得使用者活動摘要
     */
    public function getUserActivitySummary(string $userId, int $daysBack = 7): UserActivitySummaryDTO
    {
        $this->logger->info('生成使用者活動摘要', [
            'user_id' => $userId,
            'days_back' => $daysBack
        ]);

        $statistics = $this->getUserActivityStatistics($userId, $daysBack);
        
        // 計算與前期比較
        $previousPeriodStats = $this->getUserActivityStatistics($userId, $daysBack * 2);
        $previousPeriodActivities = $previousPeriodStats->totalActivities - $statistics->totalActivities;
        
        $activityTrend = $this->calculateActivityTrend(
            $statistics->totalActivities, 
            $previousPeriodActivities
        );

        // 生成洞察
        $insights = $this->generateActivityInsights($statistics);

        // 風險指標
        $riskIndicators = $this->identifyRiskIndicators($statistics);

        return new UserActivitySummaryDTO(
            userId: $userId,
            summaryDate: new \DateTime(),
            analysisPeriod: $daysBack,
            totalActivities: $statistics->totalActivities,
            activityTrend: $activityTrend,
            successRate: $statistics->successRate,
            mostActiveTimeSlot: $this->getMostActiveTimeSlot($statistics),
            topCategories: $this->getTopCategories($statistics->categoryDistribution, 3),
            insights: $insights,
            riskIndicators: $riskIndicators,
            recommendations: $this->generateRecommendations($statistics, $riskIndicators)
        );
    }

    /**
     * 取得活動熱圖資料
     */
    public function getActivityHeatmapData(string $userId, int $daysBack = 30): array
    {
        $startTime = new \DateTime("-{$daysBack} days");
        $endTime = new \DateTime();

        $activities = $this->activityLogRepository->findByUserAndTimeRange(
            (int) $userId,
            $startTime,
            $endTime,
            10000,
            0
        );

        // 建立24x7熱圖矩陣 (小時 x 星期)
        $heatmapData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $heatmapData[$hour][$weekday] = 0;
            }
        }

        // 統計活動分佈
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $dateTime = new \DateTime($timestamp);
                $hour = (int) $dateTime->format('H');
                $weekday = (int) $dateTime->format('N');
                $heatmapData[$hour][$weekday]++;
            }
        }

        // 正規化資料 (0-1 範圍)
        $maxCount = 0;
        foreach ($heatmapData as $hourData) {
            $maxCount = max($maxCount, max($hourData));
        }

        if ($maxCount > 0) {
            for ($hour = 0; $hour < 24; $hour++) {
                for ($weekday = 1; $weekday <= 7; $weekday++) {
                    $heatmapData[$hour][$weekday] = $heatmapData[$hour][$weekday] / $maxCount;
                }
            }
        }

        return [
            'user_id' => $userId,
            'analysis_period' => [
                'start' => $startTime->format('Y-m-d'),
                'end' => $endTime->format('Y-m-d'),
                'days' => $daysBack
            ],
            'heatmap_data' => $heatmapData,
            'max_activity_count' => $maxCount,
            'total_activities' => count($activities),
            'data_format' => 'heatmap[hour][weekday] = normalized_activity_count'
        ];
    }

    /**
     * 計算成功活動數量
     */
    private function countSuccessfulActivities(array $activities): int
    {
        $count = 0;
        foreach ($activities as $activity) {
            if (($activity['is_success'] ?? true) || ($activity['success'] ?? true)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 計算小時分佈
     */
    private function calculateHourlyDistribution(array $activities): array
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

    /**
     * 計算星期分佈
     */
    private function calculateWeekdayDistribution(array $activities): array
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

    /**
     * 計算類別分佈
     */
    private function calculateCategoryDistribution(array $activities): array
    {
        $distribution = [];
        
        foreach ($activities as $activity) {
            $category = $activity['category'] ?? 'unknown';
            $distribution[$category] = ($distribution[$category] ?? 0) + 1;
        }
        
        arsort($distribution);
        return $distribution;
    }

    /**
     * 尋找尖峰小時
     */
    private function findPeakHour(array $hourlyDistribution): ?int
    {
        $maxCount = max($hourlyDistribution);
        return $maxCount > 0 ? array_search($maxCount, $hourlyDistribution) : null;
    }

    /**
     * 尋找尖峰星期
     */
    private function findPeakWeekday(array $weekdayDistribution): ?int
    {
        $maxCount = max($weekdayDistribution);
        return $maxCount > 0 ? array_search($maxCount, $weekdayDistribution) : null;
    }

    /**
     * 計算月度趨勢
     */
    private function calculateMonthlyTrend(array $activities, int $daysBack): array
    {
        if ($daysBack < 30) {
            return [];
        }

        $monthlyData = [];
        foreach ($activities as $activity) {
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            if ($timestamp) {
                $month = (new \DateTime($timestamp))->format('Y-m');
                $monthlyData[$month] = ($monthlyData[$month] ?? 0) + 1;
            }
        }

        ksort($monthlyData);
        return $monthlyData;
    }

    /**
     * 分析使用者會話
     */
    private function analyzeUserSessions(array $activities): array
    {
        $sessions = [];
        $sessionTimeout = 30 * 60; // 30分鐘

        foreach ($activities as $activity) {
            $ip = $activity['ip_address'] ?? 'unknown';
            $timestamp = $activity['occurred_at'] ?? $activity['created_at'] ?? '';
            
            if (!$timestamp) continue;
            
            $activityTime = (new \DateTime($timestamp))->getTimestamp();
            
            $foundSession = false;
            foreach ($sessions as &$session) {
                if ($session['ip'] === $ip && 
                    ($activityTime - $session['last_activity']) <= $sessionTimeout) {
                    $session['activities']++;
                    $session['last_activity'] = $activityTime;
                    $session['duration'] = $session['last_activity'] - $session['start_time'];
                    $foundSession = true;
                    break;
                }
            }
            
            if (!$foundSession) {
                $sessions[] = [
                    'ip' => $ip,
                    'start_time' => $activityTime,
                    'last_activity' => $activityTime,
                    'activities' => 1,
                    'duration' => 0
                ];
            }
        }

        // 尋找最活躍會話
        $mostActiveSession = null;
        $maxActivities = 0;
        $totalDuration = 0;

        foreach ($sessions as $session) {
            if ($session['activities'] > $maxActivities) {
                $maxActivities = $session['activities'];
                $mostActiveSession = $session;
            }
            $totalDuration += $session['duration'];
        }

        $averageDuration = count($sessions) > 0 ? $totalDuration / count($sessions) : 0;

        return [
            'most_active_session' => $mostActiveSession,
            'average_duration' => $averageDuration,
            'total_sessions' => count($sessions)
        ];
    }

    /**
     * 計算活動趨勢
     */
    private function calculateActivityTrend(int $currentPeriod, int $previousPeriod): array
    {
        if ($previousPeriod === 0) {
            return [
                'trend' => $currentPeriod > 0 ? 'increasing' : 'stable',
                'change_percentage' => 0,
                'change_absolute' => $currentPeriod
            ];
        }

        $changePercentage = (($currentPeriod - $previousPeriod) / $previousPeriod) * 100;
        $trend = match (true) {
            $changePercentage > 10 => 'increasing',
            $changePercentage < -10 => 'decreasing',
            default => 'stable'
        };

        return [
            'trend' => $trend,
            'change_percentage' => round($changePercentage, 2),
            'change_absolute' => $currentPeriod - $previousPeriod
        ];
    }

    /**
     * 生成活動洞察
     */
    private function generateActivityInsights(ActivityStatisticsDTO $statistics): array
    {
        $insights = [];

        // 成功率洞察
        if ($statistics->successRate < 0.9) {
            $successPercentage = round($statistics->successRate * 100, 1);
            $insights[] = "成功率較低 ({$successPercentage}%)，建議檢查操作問題";
        }

        // 活動模式洞察
        if ($statistics->peakActivityHour !== null) {
            $insights[] = "最活躍時段為 {$statistics->peakActivityHour}:00";
        }

        // 活動量洞察
        if ($statistics->averageActivitiesPerDay > 100) {
            $insights[] = "活動頻率較高，建議關注是否為正常使用";
        } elseif ($statistics->averageActivitiesPerDay < 1) {
            $insights[] = "活動頻率較低，使用者參與度可能不足";
        }

        return $insights;
    }

    /**
     * 識別風險指標
     */
    private function identifyRiskIndicators(ActivityStatisticsDTO $statistics): array
    {
        $indicators = [];

        if ($statistics->successRate < 0.7) {
            $indicators[] = [
                'type' => 'low_success_rate',
                'severity' => 'high',
                'description' => '操作成功率過低'
            ];
        }

        if ($statistics->averageActivitiesPerDay > 500) {
            $indicators[] = [
                'type' => 'high_activity_frequency',
                'severity' => 'medium',
                'description' => '活動頻率異常高'
            ];
        }

        return $indicators;
    }

    private function getMostActiveTimeSlot(ActivityStatisticsDTO $statistics): ?string
    {
        return $statistics->peakActivityHour ? "{$statistics->peakActivityHour}:00-" . ($statistics->peakActivityHour + 1) . ":00" : null;
    }

    private function getTopCategories(array $categoryDistribution, int $limit): array
    {
        return array_slice($categoryDistribution, 0, $limit, true);
    }

    private function generateRecommendations(ActivityStatisticsDTO $statistics, array $riskIndicators): array
    {
        $recommendations = [];
        
        if (!empty($riskIndicators)) {
            $recommendations[] = '建議加強監控使用者行為';
        }
        
        if ($statistics->successRate < 0.9) {
            $recommendations[] = '提供使用者操作指導或系統優化';
        }

        return $recommendations;
    }
}