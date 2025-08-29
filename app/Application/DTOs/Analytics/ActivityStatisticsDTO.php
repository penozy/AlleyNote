<?php

declare(strict_types=1);

namespace App\Application\DTOs\Analytics;

/**
 * 活動統計 DTO
 */
final readonly class ActivityStatisticsDTO
{
    public function __construct(
        public string $userId,
        public \DateTime $analysisStartTime,
        public \DateTime $analysisEndTime,
        public int $totalActivities,
        public int $successfulActivities,
        public int $failedActivities,
        public float $successRate,
        public float $averageActivitiesPerDay,
        public ?int $peakActivityDay,
        public ?int $peakActivityHour,
        public array $categoryDistribution,
        public array $hourlyDistribution,
        public array $weekdayDistribution,
        public array $monthlyTrend,
        public ?array $mostActiveSession,
        public float $averageSessionDuration
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
                'successful_activities' => $this->successfulActivities,
                'failed_activities' => $this->failedActivities,
                'success_rate' => round($this->successRate, 3),
                'average_per_day' => round($this->averageActivitiesPerDay, 2)
            ],
            'peak_activity' => [
                'peak_hour' => $this->peakActivityHour,
                'peak_weekday' => $this->peakActivityDay
            ],
            'distributions' => [
                'categories' => $this->categoryDistribution,
                'hourly' => $this->hourlyDistribution,
                'weekday' => $this->weekdayDistribution
            ],
            'trends' => [
                'monthly' => $this->monthlyTrend
            ],
            'session_analysis' => [
                'most_active_session' => $this->mostActiveSession,
                'average_session_duration' => round($this->averageSessionDuration, 2)
            ]
        ];
    }

    /**
     * 取得統計摘要
     */
    public function getSummary(): string
    {
        if ($this->totalActivities === 0) {
            return '無活動資料';
        }

        $successPercentage = round($this->successRate * 100, 1);
        $avgPerDay = round($this->averageActivitiesPerDay, 1);
        
        $peakHourText = $this->peakActivityHour !== null 
            ? "尖峰時段 {$this->peakActivityHour}:00" 
            : '無明顯尖峰時段';

        return "總計 {$this->totalActivities} 次活動，成功率 {$successPercentage}%，日均 {$avgPerDay} 次，{$peakHourText}";
    }
}