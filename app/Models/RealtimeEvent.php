<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'restaurant_id',
        'channel',
        'event',
        'data',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    // Каналы событий
    const CHANNEL_ORDERS = 'orders';
    const CHANNEL_KITCHEN = 'kitchen';
    const CHANNEL_DELIVERY = 'delivery';
    const CHANNEL_RESERVATIONS = 'reservations';
    const CHANNEL_TABLES = 'tables';
    const CHANNEL_GLOBAL = 'global';

    // Типы событий
    const EVENT_NEW_ORDER = 'new_order';
    const EVENT_ORDER_UPDATED = 'order_updated';
    const EVENT_ORDER_STATUS = 'order_status';
    const EVENT_ORDER_PAID = 'order_paid';
    const EVENT_ORDER_CANCELLED = 'order_cancelled';
    
    const EVENT_KITCHEN_NEW = 'kitchen_new';
    const EVENT_KITCHEN_READY = 'kitchen_ready';
    
    const EVENT_DELIVERY_NEW = 'delivery_new';
    const EVENT_DELIVERY_STATUS = 'delivery_status';
    const EVENT_DELIVERY_ASSIGNED = 'delivery_assigned';
    
    const EVENT_RESERVATION_NEW = 'reservation_new';
    const EVENT_RESERVATION_CONFIRMED = 'reservation_confirmed';
    const EVENT_RESERVATION_CANCELLED = 'reservation_cancelled';
    
    const EVENT_TABLE_STATUS = 'table_status';

    /**
     * Отправить событие
     */
    public static function dispatch(string $channel, string $event, array $data = [], ?int $userId = null): self
    {
        return self::create([
            'restaurant_id' => $data['restaurant_id'] ?? 1,
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * Получить события после указанного ID
     */
    public static function getAfter(int $lastId, array $channels = [], int $restaurantId = 1): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('id', '>', $lastId)
            ->where('restaurant_id', $restaurantId)
            ->orderBy('id');

        if (!empty($channels)) {
            $query->whereIn('channel', $channels);
        }

        return $query->get();
    }

    /**
     * Очистить старые события (старше 1 часа)
     */
    public static function cleanup(): int
    {
        return self::where('created_at', '<', now()->subHour())->delete();
    }

    // ==========================================
    // Хелперы для отправки конкретных событий
    // ==========================================

    public static function orderCreated(array $order): self
    {
        return self::dispatch(self::CHANNEL_ORDERS, self::EVENT_NEW_ORDER, [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'type' => $order['type'],
            'total' => $order['total'],
            'table_id' => $order['table_id'] ?? null,
            'message' => "Новый заказ #{$order['order_number']}",
            'sound' => 'new_order',
        ]);
    }

    public static function orderStatusChanged(array $order, string $oldStatus, string $newStatus): self
    {
        $channel = self::CHANNEL_ORDERS;
        $event = self::EVENT_ORDER_STATUS;
        
        // Отправляем также на кухню если нужно
        if ($newStatus === 'cooking') {
            self::dispatch(self::CHANNEL_KITCHEN, self::EVENT_KITCHEN_NEW, [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'message' => "Заказ #{$order['order_number']} на кухню",
                'sound' => 'kitchen_new',
            ]);
        }
        
        if ($newStatus === 'ready') {
            self::dispatch(self::CHANNEL_KITCHEN, self::EVENT_KITCHEN_READY, [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'message' => "Заказ #{$order['order_number']} готов!",
                'sound' => 'order_ready',
            ]);
        }

        return self::dispatch($channel, $event, [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'message' => "Заказ #{$order['order_number']}: {$newStatus}",
        ]);
    }

    public static function orderPaid(array $order, string $method): self
    {
        return self::dispatch(self::CHANNEL_ORDERS, self::EVENT_ORDER_PAID, [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'total' => $order['total'],
            'method' => $method,
            'message' => "Заказ #{$order['order_number']} оплачен",
            'sound' => 'payment',
        ]);
    }

    public static function deliveryNew(array $order): self
    {
        return self::dispatch(self::CHANNEL_DELIVERY, self::EVENT_DELIVERY_NEW, [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'address' => $order['delivery_address'] ?? '',
            'message' => "Новая доставка #{$order['order_number']}",
            'sound' => 'delivery_new',
        ]);
    }

    public static function deliveryStatusChanged(array $order, string $status): self
    {
        return self::dispatch(self::CHANNEL_DELIVERY, self::EVENT_DELIVERY_STATUS, [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'delivery_status' => $status,
            'message' => "Доставка #{$order['order_number']}: {$status}",
        ]);
    }

    public static function reservationCreated(array $reservation): self
    {
        return self::dispatch(self::CHANNEL_RESERVATIONS, self::EVENT_RESERVATION_NEW, [
            'reservation_id' => $reservation['id'],
            'guest_name' => $reservation['guest_name'],
            'date' => $reservation['date'],
            'time' => $reservation['time_from'],
            'guests_count' => $reservation['guests_count'],
            'message' => "Новая бронь: {$reservation['guest_name']}",
            'sound' => 'reservation',
        ]);
    }

    public static function tableStatusChanged(int $tableId, string $status): self
    {
        return self::dispatch(self::CHANNEL_TABLES, self::EVENT_TABLE_STATUS, [
            'table_id' => $tableId,
            'status' => $status,
        ]);
    }
}
