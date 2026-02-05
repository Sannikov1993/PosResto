<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

/**
 * Event dispatched when a reservation is confirmed.
 *
 * Listeners can:
 * - Send confirmation notification to guest
 * - Update dashboard counters
 * - Schedule reminder notifications
 */
final class ReservationConfirmed extends ReservationEvent
{
    public function getEventName(): string
    {
        return 'reservation.confirmed';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Бронирование #%d подтверждено',
            $this->reservation->id
        );
    }
}
