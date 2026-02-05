<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Listeners;

use App\Domain\Reservation\Events\ReservationCompleted;
use App\Domain\Reservation\Events\ReservationNoShow;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Updates customer statistics based on reservation outcomes.
 *
 * Tracks:
 * - Total visits
 * - No-show count
 * - Last visit date
 */
class UpdateCustomerStats implements ShouldQueue
{
    public string $queue = 'default';

    /**
     * Handle reservation completed event.
     */
    public function handleCompleted(ReservationCompleted $event): void
    {
        $reservation = $event->reservation;

        if (!$reservation->customer_id) {
            return;
        }

        // Increment visit count and update last visit
        $reservation->customer?->increment('visits_count');
        $reservation->customer?->update([
            'last_visit_at' => now(),
        ]);
    }

    /**
     * Handle no-show event.
     */
    public function handleNoShow(ReservationNoShow $event): void
    {
        $reservation = $event->reservation;

        if (!$reservation->customer_id) {
            return;
        }

        // Increment no-show count
        $reservation->customer?->increment('no_show_count');
    }

    /**
     * Subscribe to events.
     */
    public function subscribe($events): array
    {
        return [
            ReservationCompleted::class => 'handleCompleted',
            ReservationNoShow::class => 'handleNoShow',
        ];
    }
}
