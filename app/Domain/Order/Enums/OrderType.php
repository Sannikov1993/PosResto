<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * Enum of all possible order types.
 *
 * Provides type-safe order type handling and human-readable labels.
 */
enum OrderType: string
{
    case DINE_IN = 'dine_in';
    case DELIVERY = 'delivery';
    case PICKUP = 'pickup';
    case AGGREGATOR = 'aggregator';
    case PREORDER = 'preorder';

    /**
     * Get human-readable label in Russian.
     */
    public function label(): string
    {
        return match ($this) {
            self::DINE_IN => 'В зале',
            self::DELIVERY => 'Доставка',
            self::PICKUP => 'Самовывоз',
            self::AGGREGATOR => 'Агрегатор',
            self::PREORDER => 'Предзаказ',
        };
    }

    /**
     * Get icon for order type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DINE_IN => 'utensils',
            self::DELIVERY => 'truck',
            self::PICKUP => 'shopping-bag',
            self::AGGREGATOR => 'smartphone',
            self::PREORDER => 'calendar',
        };
    }

    /**
     * Check if this type requires a table assignment.
     */
    public function requiresTable(): bool
    {
        return $this === self::DINE_IN;
    }

    /**
     * Check if this type requires a delivery address.
     */
    public function requiresAddress(): bool
    {
        return $this === self::DELIVERY;
    }

    /**
     * Check if this type requires delivery (courier assignment).
     */
    public function requiresDelivery(): bool
    {
        return in_array($this, [
            self::DELIVERY,
            self::AGGREGATOR,
        ], true);
    }
}
