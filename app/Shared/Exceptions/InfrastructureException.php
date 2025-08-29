<?php

declare(strict_types=1);

namespace AlleyNote\Shared\Exceptions;

use Exception;
use Throwable;

/**
 * 基礎設施層例外
 * 
 * 用於表示基礎設施層（如資料庫、快取、第三方服務等）發生的錯誤。
 * 
 * @author AI Assistant
 * @version 1.0
 */
class InfrastructureException extends Exception
{
    /**
     * 建構基礎設施例外
     * 
     * @param string $message 例外訊息
     * @param int $code 例外代碼
     * @param Throwable|null $previous 前一個例外
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}