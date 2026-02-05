<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События бронирований
 *
 * Типы событий:
 * - reservation_new: новое бронирование
 * - reservation_confirmed: бронирование подтверждено
 * - reservation_cancelled: бронирование отменено
 */
class ReservationEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.reservations"),
        ];
    }
}
