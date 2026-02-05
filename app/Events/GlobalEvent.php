<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * Глобальные события ресторана
 *
 * Типы событий:
 * - stop_list_changed: стоп-лист изменён
 * - settings_changed: настройки изменены
 */
class GlobalEvent extends BaseRealtimeEvent
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->restaurantId}.global"),
        ];
    }
}
