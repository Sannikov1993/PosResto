<?php

namespace App\Traits;

use App\Models\RealtimeEvent;
use App\Events\OrderEvent;
use App\Events\KitchenEvent;
use App\Events\DeliveryEvent;
use App\Events\ReservationEvent;
use App\Events\TableEvent;
use App\Events\GlobalEvent;
use App\Events\BarEvent;
use App\Events\CashEvent;

/**
 * Trait для отправки real-time событий из контроллеров
 *
 * Использует Laravel Reverb (WebSocket) для мгновенной доставки событий
 * и сохраняет в RealtimeEvent для audit log
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
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'type' => $order->type,
            'total' => $order->total,
            'table_id' => $order->table_id,
            'message' => "Новый заказ #{$order->order_number}",
            'sound' => 'new_order',
        ];

        // Broadcast через Reverb (мгновенно)
        OrderEvent::dispatch($restaurantId, 'new_order', $data);

        // Если это доставка - отправляем также в канал доставки
        if ($order->type === 'delivery') {
            DeliveryEvent::dispatch($restaurantId, 'delivery_new', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'address' => $order->delivery_address ?? '',
                'message' => "Новая доставка #{$order->order_number}",
                'sound' => 'delivery_new',
            ]);
        }

        // Для delivery/pickup заказов - отправляем сразу на кухню
        // (они создаются со статусом confirmed/cooking, минуя new → confirmed transition)
        if (in_array($order->type, ['delivery', 'pickup']) && in_array($order->status, ['confirmed', 'cooking'])) {
            KitchenEvent::dispatch($restaurantId, 'kitchen_new', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'table_id' => $order->table_id,
                'table_number' => $order->table?->number ?? $order->table?->name,
                'type' => $order->type,
                'message' => "Заказ #{$order->order_number} на кухню",
                'sound' => 'kitchen_new',
            ]);
        }

        // Сохраняем в БД для аудита (async)
        $this->logToDatabase('orders', 'new_order', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Статус заказа изменён
     */
    protected function broadcastOrderStatusChanged($order, string $oldStatus, string $newStatus): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'message' => "Заказ #{$order->order_number}: {$newStatus}",
        ];

        // Отправляем в канал заказов
        OrderEvent::dispatch($restaurantId, 'order_status', $data);

        // Отправляем на кухню ТОЛЬКО когда заказ впервые отправлен на кухню (new → confirmed)
        // Не срабатывает при: confirmed → cooking (взял в работу) или cooking → confirmed (вернул)
        if ($oldStatus === 'new' && $newStatus === 'confirmed') {
            KitchenEvent::dispatch($restaurantId, 'kitchen_new', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'table_id' => $order->table_id,
                'table_number' => $order->table?->number ?? $order->table?->name,
                'type' => $order->type,
                'message' => "Заказ #{$order->order_number} на кухню",
                'sound' => 'kitchen_new',
            ]);
        }

        // Для delivery/pickup заказов - синхронизируем delivery_status с order status
        if (in_array($order->type, ['delivery', 'pickup'])) {
            $deliveryStatusMap = [
                'cooking' => 'preparing',
                'ready' => 'ready',
                'completed' => 'delivered',
            ];

            if (isset($deliveryStatusMap[$newStatus])) {
                DeliveryEvent::dispatch($restaurantId, 'delivery_status', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'delivery_status' => $deliveryStatusMap[$newStatus],
                    'message' => "Заказ #{$order->order_number}: " . $deliveryStatusMap[$newStatus],
                ]);
            }
        }

        if ($newStatus === 'ready') {
            KitchenEvent::dispatch($restaurantId, 'kitchen_ready', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'message' => "Заказ #{$order->order_number} готов!",
                'sound' => 'order_ready',
            ]);
        }

        // Сохраняем в БД для аудита
        $this->logToDatabase('orders', 'order_status', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Заказ оплачен
     */
    protected function broadcastOrderPaid($order, string $method): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'method' => $method,
            'message' => "Заказ #{$order->order_number} оплачен",
            'sound' => 'payment',
        ];

        OrderEvent::dispatch($restaurantId, 'order_paid', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('orders', 'order_paid', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Заказ обновлён (items changed, etc)
     */
    protected function broadcastOrderUpdated($order): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'table_id' => $order->table_id,
            'message' => "Заказ #{$order->order_number} обновлён",
        ];

        OrderEvent::dispatch($restaurantId, 'order_updated', $data);
    }

    /**
     * Статус доставки изменён
     */
    protected function broadcastDeliveryStatusChanged($order, string $status): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'delivery_status' => $status,
            'message' => "Доставка #{$order->order_number}: {$status}",
        ];

        DeliveryEvent::dispatch($restaurantId, 'delivery_status', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('delivery', 'delivery_status', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Курьер назначен
     */
    protected function broadcastCourierAssigned($order, $courier): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'courier_id' => $courier->id,
            'courier_name' => $courier->name ?? $courier->user?->name,
            'message' => "Курьер назначен на заказ #{$order->order_number}",
        ];

        DeliveryEvent::dispatch($restaurantId, 'courier_assigned', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('delivery', 'courier_assigned', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Бронирование создано
     */
    protected function broadcastReservationCreated($reservation): void
    {
        $restaurantId = $reservation->restaurant_id;
        $data = [
            'reservation_id' => $reservation->id,
            'guest_name' => $reservation->guest_name,
            'date' => $reservation->date,
            'time' => $reservation->time_from,
            'guests_count' => $reservation->guests_count,
            'message' => "Новая бронь: {$reservation->guest_name}",
            'sound' => 'reservation',
        ];

        ReservationEvent::dispatch($restaurantId, 'reservation_new', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('reservations', 'reservation_new', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Статус стола изменён
     */
    protected function broadcastTableStatusChanged(int $tableId, string $status, ?int $restaurantId = null): void
    {
        $restaurantId = $restaurantId ?? auth()->user()?->restaurant_id;

        if (!$restaurantId) {
            return;
        }

        $data = [
            'table_id' => $tableId,
            'status' => $status,
        ];

        TableEvent::dispatch($restaurantId, 'table_status', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('tables', 'table_status', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Позиция отменена - уведомление на кухню
     */
    protected function broadcastItemCancelled($order, array $item, array $cancellation): void
    {
        $restaurantId = $order->restaurant_id ?? $order['restaurant_id'] ?? null;

        if (!$restaurantId) {
            return;
        }

        $reasonLabels = [
            'guest_refused' => 'Гость отказался',
            'guest_changed_mind' => 'Гость передумал',
            'wrong_order' => 'Ошибка официанта',
            'out_of_stock' => 'Закончился товар',
            'quality_issue' => 'Проблема с качеством',
            'long_wait' => 'Долгое ожидание',
            'duplicate' => 'Дубликат заказа',
            'other' => 'Другое',
        ];

        $orderNumber = is_array($order)
            ? ($order['order_number'] ?? $order['daily_number'] ?? "#{$order['id']}")
            : ($order->order_number ?? $order->daily_number ?? "#{$order->id}");

        $data = [
            'order_id' => is_array($order) ? $order['id'] : $order->id,
            'order_number' => $orderNumber,
            'table_number' => is_array($order) ? ($order['table']['number'] ?? null) : ($order->table?->number ?? null),
            'item_id' => $item['id'],
            'item_name' => $item['name'],
            'quantity' => $item['quantity'],
            'reason_type' => $cancellation['reason_type'],
            'reason_label' => $reasonLabels[$cancellation['reason_type']] ?? $cancellation['reason_type'],
            'reason_comment' => $cancellation['reason_comment'] ?? null,
            'cancelled_at' => $cancellation['cancelled_at'],
            'message' => "ОТMENA: {$item['name']} x{$item['quantity']}",
            'sound' => 'cancel',
            'urgent' => true,
        ];

        KitchenEvent::dispatch($restaurantId, 'item_cancelled', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('kitchen', 'item_cancelled', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Стоп-лист изменён
     */
    protected function broadcastStopListChanged(int $restaurantId): void
    {
        GlobalEvent::dispatch($restaurantId, 'stop_list_changed', [
            'message' => 'Стоп-лист обновлён',
        ]);

        // Сохраняем в БД для аудита
        $this->logToDatabase('global', 'stop_list_changed', ['restaurant_id' => $restaurantId]);
    }

    /**
     * Заказ бара создан
     */
    protected function broadcastBarOrderCreated($order): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'message' => "Новый заказ бара #{$order->order_number}",
        ];

        BarEvent::dispatch($restaurantId, 'bar_order_created', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('bar', 'bar_order_created', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Заказ бара обновлён
     */
    protected function broadcastBarOrderUpdated($order): void
    {
        $restaurantId = $order->restaurant_id;
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'status' => $order->status,
        ];

        BarEvent::dispatch($restaurantId, 'bar_order_updated', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('bar', 'bar_order_updated', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Смена открыта
     */
    protected function broadcastShiftOpened($shift): void
    {
        $restaurantId = $shift->restaurant_id;
        $data = [
            'shift_id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'opening_amount' => $shift->opening_amount,
            'cashier_id' => $shift->cashier_id,
            'message' => 'Смена открыта',
        ];

        CashEvent::dispatch($restaurantId, 'shift_opened', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('cash', 'shift_opened', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Смена закрыта
     */
    protected function broadcastShiftClosed($shift): void
    {
        $restaurantId = $shift->restaurant_id;
        $data = [
            'shift_id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'closing_amount' => $shift->closing_amount,
            'total_revenue' => $shift->total_revenue,
            'message' => 'Смена закрыта',
        ];

        CashEvent::dispatch($restaurantId, 'shift_closed', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('cash', 'shift_closed', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Кассовая операция создана
     */
    protected function broadcastCashOperationCreated($operation): void
    {
        $restaurantId = $operation->restaurant_id;
        $data = [
            'operation_id' => $operation->id,
            'type' => $operation->type,
            'category' => $operation->category,
            'amount' => $operation->amount,
            'description' => $operation->description,
        ];

        CashEvent::dispatch($restaurantId, 'cash_operation_created', $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase('cash', 'cash_operation_created', array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Отправить произвольное событие
     */
    protected function broadcast(string $channel, string $event, array $data = []): void
    {
        $restaurantId = $data['restaurant_id']
            ?? auth()->user()?->restaurant_id;

        if (!$restaurantId) {
            return;
        }

        // Определяем класс события по каналу
        $eventClass = match ($channel) {
            'orders' => OrderEvent::class,
            'kitchen' => KitchenEvent::class,
            'delivery' => DeliveryEvent::class,
            'reservations' => ReservationEvent::class,
            'tables' => TableEvent::class,
            'bar' => BarEvent::class,
            'cash' => CashEvent::class,
            'global' => GlobalEvent::class,
            default => OrderEvent::class,
        };

        // Убираем restaurant_id из data
        unset($data['restaurant_id']);

        $eventClass::dispatch($restaurantId, $event, $data);

        // Сохраняем в БД для аудита
        $this->logToDatabase($channel, $event, array_merge($data, ['restaurant_id' => $restaurantId]));
    }

    /**
     * Сохраняет событие в БД для аудита (async)
     */
    private function logToDatabase(string $channel, string $event, array $data): void
    {
        // Выполняем после отправки ответа клиенту для минимизации latency
        dispatch(function () use ($channel, $event, $data) {
            try {
                RealtimeEvent::dispatch($channel, $event, $data);
            } catch (\Throwable $e) {
                // Логируем ошибку, но не прерываем основной процесс
                \Log::warning("Failed to log realtime event to database: {$e->getMessage()}");
            }
        })->afterResponse();
    }
}
