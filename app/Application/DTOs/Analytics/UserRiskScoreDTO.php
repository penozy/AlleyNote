<?php

declare(strict_types=1);

namespace App\Application\DTOs\Analytics;

use App\Domain\Common\ValueObjects\AlertSeverity;

/**
 * 使用者風險評分 DTO
 */
final readonly class UserRiskScoreDTO
{
    public function __construct(
        public string $userId,
        public \DateTime $calculationTimestamp,
        public float $totalRiskScore,
        public AlertSeverity $riskLevel,
        public array $riskFactors,
        public array $recommendations
    ) {}

    /**
     * 轉換為陣列格式
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'calculation_timestamp' => $this->calculationTimestamp->format('Y-m-d H:i:s'),
            'risk_assessment' => [
                'total_risk_score' => round($this->totalRiskScore, 3),
                'risk_percentage' => round($this->totalRiskScore * 100, 1),
                'risk_level' => $this->riskLevel->value,
                'risk_level_description' => $this->getRiskLevelDescription()
            ],
            'risk_factors' => $this->formatRiskFactors(),
            'recommendations' => $this->recommendations,
            'risk_summary' => $this->getRiskSummary()
        ];
    }

    /**
     * 格式化風險因子
     */
    private function formatRiskFactors(): array
    {
        $formatted = [];
        
        foreach ($this->riskFactors as $factor => $data) {
            $formatted[$factor] = [
                'score' => round($data['score'], 3),
                'weight' => $data['weight'],
                'contribution' => round($data['contribution'], 3),
                'percentage_contribution' => round(($data['contribution'] / $this->totalRiskScore) * 100, 1),
                'description' => $data['description']
            ];
        }
        
        return $formatted;
    }

    /**
     * 取得風險等級描述
     */
    public function getRiskLevelDescription(): string
    {
        return match ($this->riskLevel) {
            AlertSeverity::CRITICAL => '極高風險 - 需要立即採取行動',
            AlertSeverity::WARNING => '中度風險 - 建議加強監控',
            AlertSeverity::INFO => '低度風險 - 正常監控即可',
            AlertSeverity::DEBUG => '正常風險 - 無特殊處置需求'
        };
    }

    /**
     * 取得風險摘要
     */
    public function getRiskSummary(): string
    {
        $percentage = round($this->totalRiskScore * 100, 1);
        $levelText = $this->getRiskLevelDescription();
        
        $topRiskFactor = $this->getTopRiskFactor();
        $topFactorText = $topRiskFactor ? "主要風險來源：{$topRiskFactor['description']}" : '';
        
        return "整體風險分數 {$percentage}%，{$levelText}。{$topFactorText}";
    }

    /**
     * 取得主要風險因子
     */
    public function getTopRiskFactor(): ?array
    {
        if (empty($this->riskFactors)) {
            return null;
        }

        $maxContribution = 0;
        $topFactor = null;

        foreach ($this->riskFactors as $factor => $data) {
            if ($data['contribution'] > $maxContribution) {
                $maxContribution = $data['contribution'];
                $topFactor = array_merge($data, ['name' => $factor]);
            }
        }

        return $topFactor;
    }

    /**
     * 是否為高風險使用者
     */
    public function isHighRiskUser(): bool
    {
        return $this->totalRiskScore >= 0.7 || $this->riskLevel === AlertSeverity::CRITICAL;
    }

    /**
     * 是否需要特殊關注
     */
    public function requiresSpecialAttention(): bool
    {
        return $this->totalRiskScore >= 0.5 || 
               $this->riskLevel === AlertSeverity::WARNING ||
               $this->riskLevel === AlertSeverity::CRITICAL;
    }

    /**
     * 取得風險趨勢建議
     */
    public function getRiskTrendSuggestions(): array
    {
        $suggestions = [];

        if ($this->totalRiskScore >= 0.8) {
            $suggestions[] = '建議每日監控此使用者活動';
            $suggestions[] = '考慮實施額外身份驗證措施';
        } elseif ($this->totalRiskScore >= 0.6) {
            $suggestions[] = '建議每週檢視此使用者風險評分變化';
            $suggestions[] = '密切關注異常活動模式';
        } elseif ($this->totalRiskScore >= 0.3) {
            $suggestions[] = '定期檢視風險評分趨勢';
        }

        return array_merge($suggestions, $this->recommendations);
    }
}