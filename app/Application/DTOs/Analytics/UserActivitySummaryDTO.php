<?php

declare(strict_types=1);

namespace App\Application\DTOs\Analytics;

/**
 * 使用者活動摘要 DTO
 */
final readonly class UserActivitySummaryDTO
{
    public function __construct(
        public string $userId,
        public \DateTime $summaryDate,
        public int $analysisPeriod,
        public int $totalActivities,
        public array $activityTrend,
        public float $successRate,
        public ?string $mostActiveTimeSlot,
        public array $topCategories,
        public array $insights,
        public array $riskIndicators,
        public array $recommendations
    ) {}

    /**
     * 轉換為陣列格式
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'summary_date' => $this->summaryDate->format('Y-m-d H:i:s'),
            'analysis_period_days' => $this->analysisPeriod,
            'activity_overview' => [
                'total_activities' => $this->totalActivities,
                'activity_trend' => $this->activityTrend,
                'success_rate' => round($this->successRate, 3),
                'most_active_time_slot' => $this->mostActiveTimeSlot
            ],
            'usage_patterns' => [
                'top_categories' => $this->topCategories
            ],
            'analysis' => [
                'insights' => $this->insights,
                'risk_indicators' => $this->riskIndicators,
                'recommendations' => $this->recommendations
            ],
            'summary_text' => $this->generateSummaryText()
        ];
    }

    /**
     * 生成摘要文字
     */
    public function generateSummaryText(): string
    {
        $period = $this->analysisPeriod;
        $activities = $this->totalActivities;
        $successPercentage = round($this->successRate * 100, 1);
        
        $trendText = match($this->activityTrend['trend'] ?? 'stable') {
            'increasing' => '呈上升趨勢',
            'decreasing' => '呈下降趨勢',
            default => '保持穩定'
        };

        $riskText = count($this->riskIndicators) > 0 
            ? '，發現 ' . count($this->riskIndicators) . ' 項風險指標'
            : '，無明顯風險';

        return "過去 {$period} 天內共有 {$activities} 次活動，成功率 {$successPercentage}%，活動量{$trendText}{$riskText}。";
    }

    /**
     * 是否需要關注
     */
    public function requiresAttention(): bool
    {
        return count($this->riskIndicators) > 0 || 
               $this->successRate < 0.8 ||
               ($this->activityTrend['trend'] ?? '') === 'decreasing';
    }

    /**
     * 取得關注等級
     */
    public function getAttentionLevel(): string
    {
        if (count($this->riskIndicators) >= 3 || $this->successRate < 0.5) {
            return 'high';
        } elseif (count($this->riskIndicators) > 0 || $this->successRate < 0.8) {
            return 'medium';
        }
        
        return 'low';
    }
}