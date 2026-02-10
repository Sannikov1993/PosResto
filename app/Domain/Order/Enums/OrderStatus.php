<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * Enum of all possible order statuses.
 *
 * Provides type-safe status handling and human-readable labels.
 */
enum OrderStatus: string
{
    case NEW = 'new';
    case CONFIRMED = 'confirmed';
    case COOKING = 'cooking';
    case READY = 'ready';
    case SERVED = 'served';
    case DELIVERING = 'delivering';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label in Russian.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Новый',
            self::CONFIRMED => 'Подтверждён',
            self::COOKING => 'Готовится',
            self::READY => 'Готов',
            self::SERVED => 'Подан',
            self::DELIVERING => 'Доставляется',
            self::COMPLETED => 'Завершён',
            self::CANCELLED => 'Отменён',
        };
    }

    /**
     * Get short label for UI badges.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::NEW => 'Новый',
            self::CONFIRMED => 'Подтв.',
            self::COOKING => 'Готовится',
            self::READY => 'Готов',
            self::SERVED => 'Подан',
            self::DELIVERING => 'Доставка',
            self::COMPLETED => 'Завершён',
            self::CANCELLED => 'Отменён',
        };
    }

    /**
     * Get CSS color class for status badge.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::NEW => 'bg-blue-500',
            self::CONFIRMED => 'bg-purple-500',
            self::COOKING => 'bg-yellow-500',
            self::READY => 'bg-green-500',
            self::SERVED => 'bg-teal-500',
            self::DELIVERING => 'bg-cyan-500',
            self::COMPLETED => 'bg-gray-500',
            self::CANCELLED => 'bg-red-500',
        };
    }

    /**
     * Get HEX color for status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NEW => '#3B82F6',
            self::CONFIRMED => '#8B5CF6',
            self::COOKING => '#F59E0B',
            self::READY => '#10B981',
            self::SERVED => '#14B8A6',
            self::DELIVERING => '#06B6D4',
            self::COMPLETED => '#6B7280',
            self::CANCELLED => '#EF4444',
        };
    }

    /**
     * Get icon for status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::NEW => 'clock',
            self::CONFIRMED => 'check',
            self::COOKING => 'fire',
            self::READY => 'bell',
            self::SERVED => 'utensils',
            self::DELIVERING => 'truck',
            self::COMPLETED => 'check-circle',
            self::CANCELLED => 'x-circle',
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
        ], true);
    }

    /**
     * Check if this is an active status (order in progress).
     */
    public function isActive(): bool
    {
        return !$this->isTerminal();
    }

    /**
     * Check if this is a kitchen status (visible on kitchen display).
     */
    public function isKitchen(): bool
    {
        return in_array($this, [
            self::NEW,
            self::CONFIRMED,
            self::COOKING,
            self::READY,
        ], true);
    }

    /**
     * Check if order can be modified (items added/removed).
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::NEW,
            self::CONFIRMED,
            self::COOKING,
        ], true);
    }

    /**
     * Get all statuses.
     *
     * @return OrderStatus[]
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get all active statuses.
     *
     * @return OrderStatus[]
     */
    public static function active(): array
    {
        return array_filter(self::cases(), fn($s) => $s->isActive());
    }

    /**
     * Get all terminal statuses.
     *
     * @return OrderStatus[]
     */
    public static function terminal(): array
    {
        return array_filter(self::cases(), fn($s) => $s->isTerminal());
    }

    /**
     * Get all kitchen statuses.
     *
     * @return OrderStatus[]
     */
    public static function kitchen(): array
    {
        return array_filter(self::cases(), fn($s) => $s->isKitchen());
    }
}
