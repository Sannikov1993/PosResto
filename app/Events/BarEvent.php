<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События бара
 *
 * Типы событий:
 * - bar_order_created: заказ бара создан
 * - bar_order_updated: заказ бара обновлён
 * - bar_order_completed: заказ бара выполнен
 */
class BarEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.bar"),
        ];
    }
}
