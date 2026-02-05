<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Listeners;

use App\Domain\Reservation\Events\ReservationEvent;
use Illuminate\Support\Facades\Log;

/**
 * Logs all reservation domain events.
 *
 * Example listener that demonstrates event handling.
 * Logs events for audit trail and debugging.
 */
class LogReservationActivity
{
    /**
     * Handle any reservation event.
     */
    public function handle(ReservationEvent $event): void
    {
        Log::channel('reservations')->info(
            $event->getDescription(),
            $event->toArray()
        );
    }
}
