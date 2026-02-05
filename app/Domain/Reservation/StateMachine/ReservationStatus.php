<?php

declare(strict_types=1);

namespace App\Domain\Reservation\StateMachine;

/**
 * Enum of all possible reservation statuses.
 *
 * Provides type-safe status handling and human-readable labels.
 */
enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SEATED = 'seated';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    /**
     * Get human-readable label in Russian.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает подтверждения',
            self::CONFIRMED => 'Подтверждено',
            self::SEATED => 'Гости за столом',
            self::COMPLETED => 'Завершено',
            self::CANCELLED => 'Отменено',
            self::NO_SHOW => 'Неявка',
        };
    }

    /**
     * Get short label for UI badges.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает',
            self::CONFIRMED => 'Подтв.',
            self::SEATED => 'За столом',
            self::COMPLETED => 'Завершено',
            self::CANCELLED => 'Отменено',
            self::NO_SHOW => 'Неявка',
        };
    }

    /**
     * Get CSS color class for status badge.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-500',
            self::CONFIRMED => 'bg-green-500',
            self::SEATED => 'bg-blue-500',
            self::COMPLETED => 'bg-gray-500',
            self::CANCELLED => 'bg-red-500',
            self::NO_SHOW => 'bg-red-700',
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
            self::NO_SHOW,
        ], true);
    }

    /**
     * Check if this is an active status (reservation in progress).
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::SEATED,
        ], true);
    }

    /**
     * Check if guests are currently at the table.
     */
    public function isSeated(): bool
    {
        return $this === self::SEATED;
    }

    /**
     * Check if reservation can be modified.
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
        ], true);
    }

    /**
     * Get all statuses.
     *
     * @return ReservationStatus[]
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get all active statuses.
     *
     * @return ReservationStatus[]
     */
    public static function active(): array
    {
        return array_filter(self::cases(), fn($s) => $s->isActive());
    }

    /**
     * Get all terminal statuses.
     *
     * @return ReservationStatus[]
     */
    public static function terminal(): array
    {
        return array_filter(self::cases(), fn($s) => $s->isTerminal());
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Try to create from string, return null if invalid.
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
