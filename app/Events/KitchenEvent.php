<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События кухни
 *
 * Типы событий:
 * - kitchen_new: новый заказ на кухню
 * - kitchen_ready: блюдо/заказ готов
 * - item_cancelled: позиция отменена
 */
class KitchenEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.kitchen"),
        ];
    }
}
