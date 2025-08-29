<?php

declare(strict_types=1);

namespace App\Application\DTOs\Common;

use App\Shared\Exceptions\ValidationException;
use App\Shared\Validation\ValidationResult;
use BadMethodCallException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * 基礎 DTO 抽象類別.
 *
 * 提供所有 DTO 的通用功能
 */
abstract class BaseDTO
{
    /**
     * 驗證 DTO 資料.
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $errors = $this->getValidationErrors();

        if (!empty($errors)) {
            $validationResult = ValidationResult::failure($errors);

            throw new ValidationException($validationResult, 'DTO 驗證失敗');
        }
    }

    /**
     * 獲取驗證錯誤（子類別可以覆寫此方法）.
     *
     * @return array<string, array<string>>
     */
    protected function getValidationErrors(): array
    {
        // 預設沒有驗證錯誤，子類別可以覆寫此方法來實作自定義驗證
        return [];
    }

    /**
     * 轉換為陣列（子類別必須實作）.
     */
    abstract public function toArray(): array;

    /**
     * 從陣列建立 DTO（子類別必須實作）.
     */
    public static function fromArray(array $data): static
    {
        throw new BadMethodCallException('子類別必須實作 fromArray 方法');
    }

    /**
     * 轉換為 JSON 字串.
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($json === false) {
            throw new RuntimeException('無法將 DTO 轉換為 JSON');
        }

        return $json;
    }

    /**
     * 從 JSON 字串建立 DTO.
     */
    public static function fromJson(string $json): static
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('無效的 JSON 格式: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON 必須表示一個物件');
        }

        return static::fromArray($data);
    }

    /**
     * 檢查兩個 DTO 是否相等.
     */
    public function equals(?BaseDTO $other): bool
    {
        if ($other === null || get_class($this) !== get_class($other)) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    /**
     * 複製 DTO.
     */
    public function clone(): static
    {
        return static::fromArray($this->toArray());
    }

    /**
     * 獲取 DTO 的哈希值
     */
    public function getHash(): string
    {
        return hash('sha256', $this->toJson());
    }

    /**
     * 檢查 DTO 是否為空（所有欄位都是預設值）.
     */
    public function isEmpty(): bool
    {
        $data = $this->toArray();

        return empty(array_filter($data, fn($value) => $value !== null && $value !== '' && $value !== []));
    }

    /**
     * 獲取已設定的欄位.
     */
    public function getSetFields(): array
    {
        $data = $this->toArray();

        return array_keys(array_filter($data, fn($value) => $value !== null));
    }

    /**
     * 檢查特定欄位是否已設定.
     */
    public function hasField(string $field): bool
    {
        return in_array($field, $this->getSetFields(), true);
    }

    /**
     * 魔術方法：轉換為字串時返回 JSON.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * 魔術方法：序列化.
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * 魔術方法：反序列化.
     */
    public function __unserialize(array $data): void
    {
        $instance = static::fromArray($data);
        foreach (get_object_vars($instance) as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }
}
