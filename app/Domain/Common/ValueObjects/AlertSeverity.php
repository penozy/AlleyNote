<?php

declare(strict_types=1);

namespace App\Domain\Common\ValueObjects;

/**
 * 告警嚴重程度枚舉.
 *
 * 定義告警的嚴重程度等級
 */
enum AlertSeverity: string
{
    case CRITICAL = 'critical';
    case WARNING = 'warning';
    case INFO = 'info';
    case DEBUG = 'debug';

    /**
     * 獲取嚴重程度的顯示名稱.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::CRITICAL => '危險',
            self::WARNING => '警告',
            self::INFO => '資訊',
            self::DEBUG => '調試'
        };
    }

    /**
     * 獲取嚴重程度的數值權重（用於排序）.
     */
    public function getWeight(): int
    {
        return match ($this) {
            self::CRITICAL => 4,
            self::WARNING => 3,
            self::INFO => 2,
            self::DEBUG => 1
        };
    }

    /**
     * 獲取嚴重程度的顏色代碼
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::CRITICAL => '#FF4444',  // 紅色
            self::WARNING => '#FFA500',   // 橙色
            self::INFO => '#2196F3',      // 藍色
            self::DEBUG => '#9E9E9E'      // 灰色
        };
    }

    /**
     * 檢查是否需要立即處理.
     */
    public function requiresImmediateAction(): bool
    {
        return $this === self::CRITICAL;
    }

    /**
     * 檢查是否應該發送通知.
     */
    public function shouldNotify(): bool
    {
        return $this === self::CRITICAL || $this === self::WARNING;
    }

    /**
     * 比較嚴重程度.
     */
    public function isMoreSevereThan(self $other): bool
    {
        return $this->getWeight() > $other->getWeight();
    }

    /**
     * 獲取所有嚴重程度選項.
     */
    public static function getAllOptions(): array
    {
        return [
            self::CRITICAL,
            self::WARNING,
            self::INFO,
            self::DEBUG,
        ];
    }

    /**
     * 從字串建立枚舉.
     */
    public static function fromString(string $value): ?self
    {
        return match (strtolower($value)) {
            'critical', 'crit', '危險' => self::CRITICAL,
            'warning', 'warn', '警告' => self::WARNING,
            'info', 'information', '資訊' => self::INFO,
            'debug', '調試' => self::DEBUG,
            default => null
        };
    }
}
