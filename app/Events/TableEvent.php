<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События столов
 *
 * Типы событий:
 * - table_status: статус стола изменён
 */
class TableEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.tables"),
        ];
    }
}
