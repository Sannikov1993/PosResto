<?php

namespace App\Traits;

use App\Models\RealtimeEvent;

/**
 * Trait для отправки real-time событий из контроллеров
 * 
 * Использование:
 * use App\Traits\BroadcastsEvents;
 * 
 * class OrderController {
 *     use BroadcastsEvents;
 *     
 *     public function store() {
 *         $order = Order::create(...);
 *         $this->broadcastOrderCreated($order);
 *     }
 * }
 */
trait BroadcastsEvents
{
    /**
     * Новый заказ создан
     */
    protected function broadcastOrderCreated($order): void
    {
        RealtimeEvent::orderCreated($order->toArray());
        
        // Если это доставка - отправляем также в канал доставки
        if ($order->type === 'delivery') {
            RealtimeEvent::deliveryNew($order->toArray());
        }
    }

    /**
     * Статус заказа изменён
     */
    protected function broadcastOrderStatusChanged($order, string $oldStatus, string $newStatus): void
    {
        RealtimeEvent::orderStatusChanged($order->toArray(), $oldStatus, $newStatus);
    }

    /**
     * Заказ оплачен
     */
    protected function broadcastOrderPaid($order, string $method): void
    {
        RealtimeEvent::orderPaid($order->toArray(), $method);
    }

    /**
     * Статус доставки изменён
     */
    protected function broadcastDeliveryStatusChanged($order, string $status): void
    {
        RealtimeEvent::deliveryStatusChanged($order->toArray(), $status);
    }

    /**
     * Бронирование создано
     */
    protected function broadcastReservationCreated($reservation): void
    {
        RealtimeEvent::reservationCreated($reservation->toArray());
    }

    /**
     * Статус стола изменён
     */
    protected function broadcastTableStatusChanged(int $tableId, string $status): void
    {
        RealtimeEvent::tableStatusChanged($tableId, $status);
    }

    /**
     * Отправить произвольное событие
     */
    protected function broadcast(string $channel, string $event, array $data = []): void
    {
        RealtimeEvent::dispatch($channel, $event, $data);
    }
}
