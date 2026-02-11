<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

/**
 * Enum статусов доставки.
 *
 * Single source of truth для label/color маппинга.
 */
enum DeliveryStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Человекочитаемый лейбл на русском.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Новый',
            self::PREPARING => 'Готовится',
            self::READY => 'Готов',
            self::PICKED_UP => 'Забран',
            self::IN_TRANSIT => 'В пути',
            self::DELIVERED => 'Доставлен',
            self::CANCELLED => 'Отменён',
        };
    }

    /**
     * HEX-цвет статуса.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#3B82F6',
            self::PREPARING => '#F59E0B',
            self::READY => '#10B981',
            self::PICKED_UP => '#8B5CF6',
            self::IN_TRANSIT => '#8B5CF6',
            self::DELIVERED => '#6B7280',
            self::CANCELLED => '#EF4444',
        };
    }

    /**
     * Проверка на терминальный (конечный) статус.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELLED], true);
    }

    /**
     * Проверка на активный статус.
     */
    public function isActive(): bool
    {
        return !$this->isTerminal();
    }

    /**
     * Лейбл из строкового значения (для nullable полей).
     */
    public static function labelFor(?string $value): string
    {
        if ($value === null) {
            return 'Неизвестно';
        }

        $case = self::tryFrom($value);
        return $case?->label() ?? $value;
    }

    /**
     * Все активные (не-терминальные) статусы.
     *
     * @return self[]
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn(self $s) => $s->isActive()));
    }

    /**
     * Все терминальные статусы.
     *
     * @return self[]
     */
    public static function terminal(): array
    {
        return array_values(array_filter(self::cases(), fn(self $s) => $s->isTerminal()));
    }

    /**
     * Значения активных статусов как массив строк.
     *
     * @return string[]
     */
    public static function activeValues(): array
    {
        return array_map(fn(self $s) => $s->value, self::active());
    }

    /**
     * Цвет из строкового значения (для nullable полей).
     */
    public static function colorFor(?string $value): string
    {
        if ($value === null) {
            return '#6B7280';
        }

        $case = self::tryFrom($value);
        return $case?->color() ?? '#6B7280';
    }
}
