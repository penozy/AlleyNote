<?php

declare(strict_types=1);

namespace App\Application\DTOs\Analytics;

use App\Domain\Common\ValueObjects\AlertSeverity;

/**
 * 異常檢測結果 DTO
 */
final readonly class AnomalyDetectionResultDTO
{
    public function __construct(
        public string $userId,
        public \DateTime $analysisTimestamp,
        public array $anomaliesDetected,
        public float $anomalyScore,
        public AlertSeverity $riskLevel,
        public array $recommendations
    ) {}

    /**
     * 轉換為陣列格式
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'analysis_timestamp' => $this->analysisTimestamp->format('Y-m-d H:i:s'),
            'anomaly_detection' => [
                'total_anomalies' => count($this->anomaliesDetected),
                'anomaly_score' => round($this->anomalyScore, 3),
                'risk_level' => $this->riskLevel->value,
                'anomalies' => $this->anomaliesDetected
            ],
            'recommendations' => $this->recommendations,
            'analysis_summary' => $this->getAnalysisSummary()
        ];
    }

    /**
     * 取得分析摘要
     */
    public function getAnalysisSummary(): string
    {
        $anomalyCount = count($this->anomaliesDetected);
        $scorePercentage = round($this->anomalyScore * 100, 1);
        
        if ($anomalyCount === 0) {
            return '未檢測到異常行為';
        }

        $riskLevelText = match ($this->riskLevel) {
            AlertSeverity::CRITICAL => '極高風險',
            AlertSeverity::WARNING => '中度風險',
            AlertSeverity::INFO => '低度風險',
            AlertSeverity::DEBUG => '正常'
        };

        return "檢測到 {$anomalyCount} 項異常，異常分數 {$scorePercentage}%，風險等級：{$riskLevelText}";
    }

    /**
     * 是否需要立即關注
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->riskLevel === AlertSeverity::CRITICAL || 
               $this->anomalyScore >= 0.8 ||
               count($this->anomaliesDetected) >= 5;
    }

    /**
     * 取得高優先級異常
     */
    public function getHighPriorityAnomalies(): array
    {
        return array_filter($this->anomaliesDetected, function($anomaly) {
            return isset($anomaly['severity_score']) && $anomaly['severity_score'] >= 30;
        });
    }
}