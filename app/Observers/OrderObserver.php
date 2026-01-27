<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Observer для отслеживания изменений статуса заказа
 * и автоматической отправки уведомлений клиентам
 */
class OrderObserver
{
    private NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Уведомляем только о заказах на доставку
        if ($order->type !== 'delivery') {
            return;
        }

        try {
            $this->notifications->notifyOrderCreated($order);
        } catch (\Exception $e) {
            Log::error('OrderObserver: Failed to send created notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Проверяем, изменился ли статус
        if (!$order->wasChanged('status')) {
            return;
        }

        // Только для заказов на доставку
        if ($order->type !== 'delivery') {
            return;
        }

        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        Log::info('OrderObserver: Status changed', [
            'order_id' => $order->id,
            'from' => $oldStatus,
            'to' => $newStatus,
        ]);

        try {
            // Уведомляем клиента
            match ($newStatus) {
                'cooking' => $this->notifications->notifyOrderCooking($order),
                'ready' => $this->notifications->notifyOrderReady($order),
                'delivering' => $this->notifications->notifyOrderDelivering($order),
                'completed' => $this->notifications->notifyOrderCompleted($order),
                'cancelled' => $this->notifications->notifyOrderCancelled($order),
                default => null,
            };

            // Уведомляем админа
            $this->notifications->notifyAdmin($order, $newStatus);

        } catch (\Exception $e) {
            Log::error('OrderObserver: Failed to send status notification', [
                'order_id' => $order->id,
                'status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
