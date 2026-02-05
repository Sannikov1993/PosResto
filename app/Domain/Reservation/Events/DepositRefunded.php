<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;

/**
 * Event dispatched when a deposit is refunded.
 *
 * Listeners can:
 * - Process refund transaction
 * - Send refund confirmation
 * - Update accounting
 */
final class DepositRefunded extends ReservationEvent
{
    public float $amount;
    public ?string $reason;

    /**
     * @param Reservation $reservation
     * @param float $amount Refunded amount
     * @param string|null $reason Refund reason
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        float $amount,
        ?string $reason = null,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->amount = $amount;
        $this->reason = $reason;
    }

    public function getEventName(): string
    {
        return 'deposit.refunded';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Депозит %.2f ₽ возвращён для брони #%d',
            $this->amount,
            $this->reservation->id
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'amount' => $this->amount,
            'reason' => $this->reason,
        ]);
    }
}
