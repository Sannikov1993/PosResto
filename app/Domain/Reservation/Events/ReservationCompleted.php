<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

/**
 * Event dispatched when a reservation is completed.
 *
 * Listeners can:
 * - Send feedback request to guest
 * - Update visit history
 * - Calculate loyalty points
 */
final class ReservationCompleted extends ReservationEvent
{
    public function getEventName(): string
    {
        return 'reservation.completed';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Бронирование #%d завершено',
            $this->reservation->id
        );
    }
}
