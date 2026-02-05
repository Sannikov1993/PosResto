<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События доставки
 *
 * Типы событий:
 * - delivery_new: новая доставка
 * - delivery_status: статус доставки изменён
 * - delivery_assigned: курьер назначен
 * - courier_assigned: курьер назначен (алиас)
 */
class DeliveryEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.delivery"),
        ];
    }
}
