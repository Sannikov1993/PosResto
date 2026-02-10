<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * Enum of all possible payment statuses.
 *
 * Provides type-safe payment status handling and human-readable labels.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PARTIAL = 'partial';
    case REFUNDED = 'refunded';

    /**
     * Get human-readable label in Russian.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает оплаты',
            self::PAID => 'Оплачен',
            self::PARTIAL => 'Частично оплачен',
            self::REFUNDED => 'Возврат',
        };
    }

    /**
     * Get CSS color class for payment status badge.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-500',
            self::PAID => 'bg-green-500',
            self::PARTIAL => 'bg-orange-500',
            self::REFUNDED => 'bg-red-500',
        };
    }

    /**
     * Check if payment is completed (fully paid).
     */
    public function isCompleted(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if payment can be accepted (not yet fully paid/refunded).
     */
    public function canPay(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PARTIAL,
        ], true);
    }
}
