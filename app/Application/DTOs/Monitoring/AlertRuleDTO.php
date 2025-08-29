<?php

declare(strict_types=1);

namespace App\Application\DTOs\Monitoring;

use App\Application\DTOs\Common\BaseDTO;
use App\Domain\Common\ValueObjects\AlertSeverity;
use DateTime;
use DateTimeInterface;
use Exception;

/**
 * 告警規則 DTO.
 *
 * 用於告警規則的數據傳輸和驗證
 */
final class AlertRuleDTO extends BaseDTO
{
    /**
     * 規則 ID.
     */
    public readonly ?string $id;

    /**
     * 規則名稱.
     */
    public readonly string $name;

    /**
     * 規則描述.
     */
    public readonly string $description;

    /**
     * 指標名稱.
     */
    public readonly string $metric;

    /**
     * 指標路徑（用於從指標數據中提取值）.
     */
    public readonly string $metricPath;

    /**
     * 比較操作符.
     */
    public readonly string $operator;

    /**
     * 閾值
     */
    public readonly float $threshold;

    /**
     * 告警嚴重程度.
     */
    public readonly AlertSeverity $severity;

    /**
     * 評估窗口時間（秒）.
     */
    public readonly int $evaluationWindow;

    /**
     * 觸發條件數量.
     */
    public readonly int $triggerCount;

    /**
     * 是否啟用.
     */
    public readonly bool $enabled;

    /**
     * 標籤.
     */
    public readonly array $tags;

    /**
     * 通知管道.
     */
    public readonly array $notificationChannels;

    /**
     * 建立時間.
     */
    public readonly ?DateTimeInterface $createdAt;

    /**
     * 更新時間.
     */
    public readonly ?DateTimeInterface $updatedAt;

    /**
     * 建構子.
     */
    public function __construct(
        string $name,
        string $description,
        string $metric,
        string $metricPath,
        string $operator,
        float $threshold,
        AlertSeverity $severity,
        int $evaluationWindow = 300,
        int $triggerCount = 1,
        bool $enabled = true,
        array $tags = [],
        array $notificationChannels = [],
        ?string $id = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->metric = $metric;
        $this->metricPath = $metricPath;
        $this->operator = $operator;
        $this->threshold = $threshold;
        $this->severity = $severity;
        $this->evaluationWindow = $evaluationWindow;
        $this->triggerCount = $triggerCount;
        $this->enabled = $enabled;
        $this->tags = $tags;
        $this->notificationChannels = $notificationChannels;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;

        $this->validate();
    }

    /**
     * 轉換為陣列.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'metric' => $this->metric,
            'metricPath' => $this->metricPath,
            'operator' => $this->operator,
            'threshold' => $this->threshold,
            'severity' => $this->severity->value,
            'evaluationWindow' => $this->evaluationWindow,
            'triggerCount' => $this->triggerCount,
            'enabled' => $this->enabled,
            'tags' => $this->tags,
            'notificationChannels' => $this->notificationChannels,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 獲取驗證錯誤.
     *
     * @return array<string, array<string>>
     */
    protected function getValidationErrors(): array
    {
        $errors = [];

        // 驗證規則名稱
        if (empty($this->name)) {
            $errors['name'][] = '規則名稱不能為空';
        } elseif (strlen($this->name) < 3) {
            $errors['name'][] = '規則名稱至少需要 3 個字符';
        } elseif (strlen($this->name) > 100) {
            $errors['name'][] = '規則名稱不能超過 100 個字符';
        }

        // 驗證規則描述
        if (empty($this->description)) {
            $errors['description'][] = '規則描述不能為空';
        } elseif (strlen($this->description) > 500) {
            $errors['description'][] = '規則描述不能超過 500 個字符';
        }

        // 驗證指標名稱
        if (empty($this->metric)) {
            $errors['metric'][] = '指標名稱不能為空';
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_\.]*$/', $this->metric)) {
            $errors['metric'][] = '指標名稱格式無效，必須以字母開頭，只能包含字母、數字、底線和點';
        }

        // 驗證比較操作符
        $validOperators = ['gt', 'gte', 'lt', 'lte', 'eq', 'ne'];
        if (empty($this->operator)) {
            $errors['operator'][] = '比較操作符不能為空';
        } elseif (!in_array($this->operator, $validOperators, true)) {
            $errors['operator'][] = '無效的比較操作符，只能是：' . implode(', ', $validOperators);
        }

        // 驗證評估窗口時間
        if ($this->evaluationWindow < 0) {
            $errors['evaluationWindow'][] = '評估窗口必須是非負整數';
        }

        // 驗證觸發條件數量
        if ($this->triggerCount < 1) {
            $errors['triggerCount'][] = '觸發條件數量必須是正整數';
        }

        // 驗證標籤
        if (!is_array($this->tags)) {
            $errors['tags'][] = '標籤必須是陣列';
        } else {
            foreach ($this->tags as $index => $tag) {
                if (!is_string($tag)) {
                    $errors['tags'][] = "標籤索引 {$index} 的值必須是字串";
                }
            }
        }

        // 驗證通知管道
        if (!is_array($this->notificationChannels)) {
            $errors['notificationChannels'][] = '通知管道必須是陣列';
        } else {
            foreach ($this->notificationChannels as $index => $channel) {
                if (!is_string($channel)) {
                    $errors['notificationChannels'][] = "通知管道索引 {$index} 的值必須是字串";
                }
            }
        }

        return $errors;
    }

    /**
     * 從陣列建立 DTO.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            metric: $data['metric'] ?? '',
            metricPath: $data['metricPath'] ?? $data['metric'] ?? '',
            operator: $data['operator'] ?? '',
            threshold: (float) ($data['threshold'] ?? 0.0),
            severity: is_string($data['severity'] ?? null)
                ? AlertSeverity::from($data['severity'])
                : ($data['severity'] ?? AlertSeverity::WARNING),
            evaluationWindow: (int) ($data['evaluationWindow'] ?? 300),
            triggerCount: (int) ($data['triggerCount'] ?? 1),
            enabled: (bool) ($data['enabled'] ?? true),
            tags: $data['tags'] ?? [],
            notificationChannels: $data['notificationChannels'] ?? [],
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTime($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTime($data['updatedAt']) : null,
        );
    }

    /**
     * 檢查指標值是否觸發告警.
     */
    public function evaluateMetric(float $value): bool
    {
        return match ($this->operator) {
            'gt' => $value > $this->threshold,
            'gte' => $value >= $this->threshold,
            'lt' => $value < $this->threshold,
            'lte' => $value <= $this->threshold,
            'eq' => abs($value - $this->threshold) < PHP_FLOAT_EPSILON,
            'ne' => abs($value - $this->threshold) >= PHP_FLOAT_EPSILON,
            default => false
        };
    }

    /**
     * 獲取操作符的顯示名稱.
     */
    public function getOperatorDisplayName(): string
    {
        return match ($this->operator) {
            'gt' => '大於',
            'gte' => '大於等於',
            'lt' => '小於',
            'lte' => '小於等於',
            'eq' => '等於',
            'ne' => '不等於',
            default => $this->operator
        };
    }

    /**
     * 獲取完整的規則描述.
     */
    public function getFullDescription(): string
    {
        return sprintf(
            '%s：當 %s %s %s 時觸發 %s 告警',
            $this->name,
            $this->metric,
            $this->getOperatorDisplayName(),
            $this->threshold,
            $this->severity->getDisplayName(),
        );
    }

    /**
     * 複製並修改規則.
     */
    public function withChanges(array $changes): self
    {
        $data = $this->toArray();
        foreach ($changes as $key => $value) {
            $data[$key] = $value;
        }

        return self::fromArray($data);
    }

    /**
     * 檢查規則是否有效.
     */
    public function isValid(): bool
    {
        try {
            $this->validate();

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
