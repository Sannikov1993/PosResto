<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * События кассы
 *
 * Типы событий:
 * - cash_operation_created: кассовая операция создана (внесение/снятие)
 * - shift_opened: смена открыта
 * - shift_closed: смена закрыта
 */
class CashEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.cash"),
        ];
    }
}
