<?php

declare(strict_types=1);

namespace App\Application\DTOs\Monitoring;

use App\Domain\Common\ValueObjects\AlertSeverity;
use App\Application\DTOs\Common\BaseDTO;

/**
 * 告警 DTO
 * 
 * 用於告警的數據傳輸和驗證
 */
final class AlertDTO extends BaseDTO
{
    /**
     * 告警 ID
     */
    public readonly ?string $id;

    /**
     * 規則 ID
     */
    public readonly string $ruleId;

    /**
     * 規則名稱
     */
    public readonly string $ruleName;

    /**
     * 告警標題
     */
    public readonly string $title;

    /**
     * 告警描述
     */
    public readonly string $description;

    /**
     * 告警訊息
     */
    public readonly string $message;

    /**
     * 告警嚴重程度
     */
    public readonly AlertSeverity $severity;

    /**
     * 告警狀態
     */
    public readonly string $status;

    /**
     * 觸發指標名稱
     */
    public readonly string $metric;

    /**
     * 當前指標值
     */
    public readonly float $currentValue;

    /**
     * 閾值
     */
    public readonly float $threshold;

    /**
     * 比較操作符
     */
    public readonly string $operator;

    /**
     * 告警標籤
     */
    public readonly array $labels;

    /**
     * 告警註釋
     */
    public readonly array $annotations;

    /**
     * 告警觸發時間
     */
    public readonly \DateTimeInterface $alertedAt;

    /**
     * 告警解決時間
     */
    public readonly ?\DateTimeInterface $resolvedAt;

    /**
     * 告警確認時間
     */
    public readonly ?\DateTimeInterface $acknowledgedAt;

    /**
     * 告警確認者
     */
    public readonly ?string $acknowledgedBy;

    /**
     * 靜音到期時間
     */
    public readonly ?\DateTimeInterface $silencedUntil;

    /**
     * 通知狀態
     */
    public readonly array $notificationStatus;

    /**
     * 建立時間
     */
    public readonly ?\DateTimeInterface $createdAt;

    /**
     * 更新時間
     */
    public readonly ?\DateTimeInterface $updatedAt;

    /**
     * 建構子
     */
    public function __construct(
        string $ruleId,
        string $ruleName,
        string $title,
        string $description,
        string $message,
        AlertSeverity $severity,
        string $status,
        string $metric,
        float $currentValue,
        float $threshold,
        string $operator,
        array $labels = [],
        array $annotations = [],
        ?\DateTimeInterface $alertedAt = null,
        ?\DateTimeInterface $resolvedAt = null,
        ?\DateTimeInterface $acknowledgedAt = null,
        ?string $acknowledgedBy = null,
        ?\DateTimeInterface $silencedUntil = null,
        array $notificationStatus = [],
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->ruleId = $ruleId;
        $this->ruleName = $ruleName;
        $this->title = $title;
        $this->description = $description;
        $this->message = $message;
        $this->severity = $severity;
        $this->status = $status;
        $this->metric = $metric;
        $this->currentValue = $currentValue;
        $this->threshold = $threshold;
        $this->operator = $operator;
        $this->labels = $labels;
        $this->annotations = $annotations;
        $this->alertedAt = $alertedAt ?? new \DateTime();
        $this->resolvedAt = $resolvedAt;
        $this->acknowledgedAt = $acknowledgedAt;
        $this->acknowledgedBy = $acknowledgedBy;
        $this->silencedUntil = $silencedUntil;
        $this->notificationStatus = $notificationStatus;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;

        $this->validate();
    }

    /**
     * 轉換為陣列
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ruleId' => $this->ruleId,
            'ruleName' => $this->ruleName,
            'title' => $this->title,
            'description' => $this->description,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'status' => $this->status,
            'metric' => $this->metric,
            'currentValue' => $this->currentValue,
            'threshold' => $this->threshold,
            'operator' => $this->operator,
            'labels' => $this->labels,
            'annotations' => $this->annotations,
            'alertedAt' => $this->alertedAt->format('Y-m-d H:i:s'),
            'resolvedAt' => $this->resolvedAt?->format('Y-m-d H:i:s'),
            'acknowledgedAt' => $this->acknowledgedAt?->format('Y-m-d H:i:s'),
            'acknowledgedBy' => $this->acknowledgedBy,
            'silencedUntil' => $this->silencedUntil?->format('Y-m-d H:i:s'),
            'notificationStatus' => $this->notificationStatus,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * 獲取驗證錯誤
     * 
     * @return array<string, array<string>>
     */
    protected function getValidationErrors(): array
    {
        $errors = [];

        // 驗證規則 ID
        if (empty($this->ruleId)) {
            $errors['ruleId'][] = '規則 ID 不能為空';
        }

        // 驗證規則名稱
        if (empty($this->ruleName)) {
            $errors['ruleName'][] = '規則名稱不能為空';
        }

        // 驗證告警標題
        if (empty($this->title)) {
            $errors['title'][] = '告警標題不能為空';
        } elseif (strlen($this->title) > 255) {
            $errors['title'][] = '告警標題不能超過 255 個字符';
        }

        // 驗證告警描述
        if (empty($this->description)) {
            $errors['description'][] = '告警描述不能為空';
        } elseif (strlen($this->description) > 1000) {
            $errors['description'][] = '告警描述不能超過 1000 個字符';
        }

        // 驗證告警狀態
        $validStatuses = ['firing', 'resolved', 'acknowledged', 'silenced'];
        if (empty($this->status)) {
            $errors['status'][] = '告警狀態不能為空';
        } elseif (!in_array($this->status, $validStatuses, true)) {
            $errors['status'][] = '無效的告警狀態，只能是：' . implode(', ', $validStatuses);
        }

        // 驗證指標名稱
        if (empty($this->metric)) {
            $errors['metric'][] = '指標名稱不能為空';
        }

        // 驗證比較操作符
        $validOperators = ['gt', 'gte', 'lt', 'lte', 'eq', 'ne'];
        if (empty($this->operator)) {
            $errors['operator'][] = '比較操作符不能為空';
        } elseif (!in_array($this->operator, $validOperators, true)) {
            $errors['operator'][] = '無效的比較操作符，只能是：' . implode(', ', $validOperators);
        }

        // 驗證標籤
        if (!is_array($this->labels)) {
            $errors['labels'][] = '標籤必須是陣列';
        }

        // 驗證註釋
        if (!is_array($this->annotations)) {
            $errors['annotations'][] = '註釋必須是陣列';
        }

        // 驗證通知狀態
        if (!is_array($this->notificationStatus)) {
            $errors['notificationStatus'][] = '通知狀態必須是陣列';
        }

        return $errors;
    }

    /**
     * 從陣列建立 DTO
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ruleId: $data['ruleId'] ?? '',
            ruleName: $data['ruleName'] ?? '',
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            message: $data['message'] ?? '',
            severity: is_string($data['severity'] ?? null) 
                ? AlertSeverity::from($data['severity'])
                : ($data['severity'] ?? AlertSeverity::WARNING),
            status: $data['status'] ?? 'firing',
            metric: $data['metric'] ?? '',
            currentValue: (float) ($data['currentValue'] ?? 0.0),
            threshold: (float) ($data['threshold'] ?? 0.0),
            operator: $data['operator'] ?? '',
            labels: $data['labels'] ?? [],
            annotations: $data['annotations'] ?? [],
            alertedAt: isset($data['alertedAt']) ? new \DateTime($data['alertedAt']) : null,
            resolvedAt: isset($data['resolvedAt']) ? new \DateTime($data['resolvedAt']) : null,
            acknowledgedAt: isset($data['acknowledgedAt']) ? new \DateTime($data['acknowledgedAt']) : null,
            acknowledgedBy: $data['acknowledgedBy'] ?? null,
            silencedUntil: isset($data['silencedUntil']) ? new \DateTime($data['silencedUntil']) : null,
            notificationStatus: $data['notificationStatus'] ?? [],
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new \DateTime($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new \DateTime($data['updatedAt']) : null
        );
    }

    /**
     * 檢查告警是否處於活動狀態
     */
    public function isActive(): bool
    {
        return $this->status === 'firing';
    }

    /**
     * 檢查告警是否已解決
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * 檢查告警是否已確認
     */
    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged' || $this->acknowledgedAt !== null;
    }

    /**
     * 檢查告警是否被靜音
     */
    public function isSilenced(): bool
    {
        if ($this->silencedUntil === null) {
            return false;
        }
        
        return $this->silencedUntil > new \DateTime();
    }

    /**
     * 獲取告警持續時間（秒）
     */
    public function getDuration(): int
    {
        $endTime = $this->resolvedAt ?? new \DateTime();
        return $endTime->getTimestamp() - $this->alertedAt->getTimestamp();
    }

    /**
     * 獲取告警年齡（從觸發到現在的時間）
     */
    public function getAge(): int
    {
        return (new \DateTime())->getTimestamp() - $this->alertedAt->getTimestamp();
    }

    /**
     * 複製並修改告警
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
     * 建立已解決的告警副本
     */
    public function resolve(): self
    {
        return $this->withChanges([
            'status' => 'resolved',
            'resolvedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * 建立已確認的告警副本
     */
    public function acknowledge(string $acknowledgedBy): self
    {
        return $this->withChanges([
            'status' => 'acknowledged',
            'acknowledgedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'acknowledgedBy' => $acknowledgedBy,
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * 建立靜音的告警副本
     */
    public function silence(\DateTimeInterface $until): self
    {
        return $this->withChanges([
            'status' => 'silenced',
            'silencedUntil' => $until->format('Y-m-d H:i:s'),
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * 獲取告警摘要
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'severity' => $this->severity->value,
            'status' => $this->status,
            'metric' => $this->metric,
            'currentValue' => $this->currentValue,
            'threshold' => $this->threshold,
            'alertedAt' => $this->alertedAt->format('Y-m-d H:i:s'),
            'duration' => $this->getDuration(),
            'age' => $this->getAge()
        ];
    }
}