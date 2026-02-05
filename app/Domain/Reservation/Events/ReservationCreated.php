<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

/**
 * Event dispatched when a new reservation is created.
 *
 * Listeners can:
 * - Send confirmation email/SMS
 * - Update table availability cache
 * - Log for analytics
 */
final class ReservationCreated extends ReservationEvent
{
    public function getEventName(): string
    {
        return 'reservation.created';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Создано бронирование #%d на %s %s для %d гостей',
            $this->reservation->id,
            $this->reservation->date,
            $this->reservation->time_from,
            $this->reservation->guests_count
        );
    }
}
