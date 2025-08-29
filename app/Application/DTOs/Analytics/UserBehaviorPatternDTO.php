<?php

declare(strict_types=1);

namespace App\Application\DTOs\Analytics;

/**
 * 使用者行為模式分析結果 DTO
 */
final readonly class UserBehaviorPatternDTO
{
    public function __construct(
        public string $userId,
        public \DateTime $analysisStartTime,
        public \DateTime $analysisEndTime,
        public int $totalActivities,
        public float $averageActivitiesPerDay,
        public array $mostActiveHours,
        public array $mostActiveWeekdays,
        public array $topCategories,
        public array $activityFrequencyTrend,
        public float $behaviorConsistencyScore,
        public array $uniqueIpAddresses,
        public array $suspiciousPatterns
    ) {}

    /**
     * 轉換為陣列格式
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'analysis_period' => [
                'start_time' => $this->analysisStartTime->format('Y-m-d H:i:s'),
                'end_time' => $this->analysisEndTime->format('Y-m-d H:i:s'),
                'days' => $this->analysisStartTime->diff($this->analysisEndTime)->days
            ],
            'activity_summary' => [
                'total_activities' => $this->totalActivities,
                'average_per_day' => round($this->averageActivitiesPerDay, 2)
            ],
            'time_patterns' => [
                'most_active_hours' => $this->mostActiveHours,
                'most_active_weekdays' => $this->mostActiveWeekdays
            ],
            'usage_patterns' => [
                'top_categories' => $this->topCategories,
                'frequency_trend' => $this->activityFrequencyTrend
            ],
            'behavior_analysis' => [
                'consistency_score' => round($this->behaviorConsistencyScore, 3),
                'unique_ip_count' => count($this->uniqueIpAddresses),
                'unique_ips' => $this->uniqueIpAddresses
            ],
            'security_indicators' => [
                'suspicious_patterns_count' => count($this->suspiciousPatterns),
                'suspicious_patterns' => $this->suspiciousPatterns
            ]
        ];
    }

    /**
     * 取得行為模式摘要
     */
    public function getBehaviorSummary(): string
    {
        if ($this->totalActivities === 0) {
            return '無活動資料';
        }

        $consistencyLevel = match (true) {
            $this->behaviorConsistencyScore >= 0.8 => '高度一致',
            $this->behaviorConsistencyScore >= 0.6 => '中度一致',
            $this->behaviorConsistencyScore >= 0.3 => '低度一致',
            default => '不規律'
        };

        $suspiciousCount = count($this->suspiciousPatterns);
        $riskIndicator = $suspiciousCount > 0 ? "發現 {$suspiciousCount} 項可疑模式" : '無明顯異常';

        return "行為一致性：{$consistencyLevel}，{$riskIndicator}";
    }
}