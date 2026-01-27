<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalReceipt extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'operation',
        'external_id',
        'atol_uuid',
        'status',
        'error_message',
        'total',
        'items',
        'payments',
        'fiscal_document_number',
        'fiscal_document_attribute',
        'fn_number',
        'shift_number',
        'receipt_datetime',
        'ofd_sum',
        'callback_response',
        'customer_email',
        'customer_phone',
    ];

    protected $casts = [
        'items' => 'array',
        'payments' => 'array',
        'callback_response' => 'array',
        'total' => 'decimal:2',
        'ofd_sum' => 'decimal:2',
    ];

    /**
     * Статусы чека
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DONE = 'done';
    const STATUS_FAIL = 'fail';

    /**
     * Типы операций
     */
    const OPERATION_SELL = 'sell';
    const OPERATION_SELL_REFUND = 'sell_refund';

    /**
     * Ресторан
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Заказ
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Чек успешно фискализирован?
     */
    public function isFiscalized(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    /**
     * Чек в ожидании?
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Чек с ошибкой?
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }

    /**
     * Пометить как обрабатывается
     */
    public function markAsProcessing(string $uuid): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'atol_uuid' => $uuid,
        ]);
    }

    /**
     * Пометить как успешно фискализированный
     */
    public function markAsDone(array $fiscalData): void
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'fiscal_document_number' => $fiscalData['fiscal_document_number'] ?? null,
            'fiscal_document_attribute' => $fiscalData['fiscal_document_attribute'] ?? null,
            'fn_number' => $fiscalData['fn_number'] ?? null,
            'shift_number' => $fiscalData['shift_number'] ?? null,
            'receipt_datetime' => $fiscalData['receipt_datetime'] ?? null,
            'ofd_sum' => $fiscalData['ofd_sum'] ?? null,
            'callback_response' => $fiscalData,
        ]);
    }

    /**
     * Пометить как неуспешный
     */
    public function markAsFailed(string $errorMessage, ?array $response = null): void
    {
        $this->update([
            'status' => self::STATUS_FAIL,
            'error_message' => $errorMessage,
            'callback_response' => $response,
        ]);
    }
}
