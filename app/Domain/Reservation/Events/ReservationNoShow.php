<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;

/**
 * Event dispatched when guests don't show up.
 *
 * Listeners can:
 * - Update customer no-show count
 * - Process deposit forfeiture
 * - Free table for walk-ins
 * - Send notification to customer
 */
final class ReservationNoShow extends ReservationEvent
{
    public bool $depositForfeited;

    /**
     * @param Reservation $reservation
     * @param bool $depositForfeited Whether deposit was forfeited
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        bool $depositForfeited = false,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->depositForfeited = $depositForfeited;
    }

    public function getEventName(): string
    {
        return 'reservation.no_show';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Неявка по брони #%d',
            $this->reservation->id
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'deposit_forfeited' => $this->depositForfeited,
            'customer_id' => $this->reservation->customer_id,
        ]);
    }
}
