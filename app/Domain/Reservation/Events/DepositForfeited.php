<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;

/**
 * Event dispatched when a deposit is forfeited (no-show penalty).
 *
 * Listeners can:
 * - Update accounting as revenue
 * - Log for reports
 * - Update customer penalty history
 */
final class DepositForfeited extends ReservationEvent
{
    public float $amount;
    public ?string $reason;

    /**
     * @param Reservation $reservation
     * @param float $amount Forfeited amount
     * @param string|null $reason Forfeiture reason
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
        return 'deposit.forfeited';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Депозит %.2f ₽ конфискован для брони #%d (неявка)',
            $this->amount,
            $this->reservation->id
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'amount' => $this->amount,
            'reason' => $this->reason,
            'customer_id' => $this->reservation->customer_id,
        ]);
    }
}
