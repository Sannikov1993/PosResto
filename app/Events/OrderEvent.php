<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События заказов
 *
 * Типы событий:
 * - new_order: новый заказ создан
 * - order_status: статус заказа изменён
 * - order_paid: заказ оплачен
 * - order_cancelled: заказ отменён
 * - order_updated: заказ обновлён
 */
class OrderEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.orders"),
        ];
    }
}
