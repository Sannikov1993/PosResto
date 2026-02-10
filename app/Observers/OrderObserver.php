<?php

namespace App\Observers;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
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
        $this->invalidateDashboardCache($order);

        // Уведомляем только о заказах на доставку
        if ($order->type !== OrderType::DELIVERY->value) {
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
        if ($order->wasChanged('status') || $order->wasChanged('total') || $order->wasChanged('payment_status')) {
            $this->invalidateDashboardCache($order);
        }

        // Проверяем, изменился ли статус
        if (!$order->wasChanged('status')) {
            return;
        }

        // Только для заказов на доставку
        if ($order->type !== OrderType::DELIVERY->value) {
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

    /**
     * Invalidate dashboard/analytics cache keys when orders change.
     */
    private function invalidateDashboardCache(Order $order): void
    {
        $rid = $order->restaurant_id;
        $today = now()->toDateString();

        // Dashboard caches
        Cache::forget("dashboard:sales:{$rid}:week:{$today}");
        Cache::forget("dashboard:sales:{$rid}:month:{$today}");
        Cache::forget("dashboard:hourly:{$rid}:{$today}");

        // Analytics period stats (forget pattern — clear today's stats)
        $from = now()->startOfWeek()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        Cache::forget("analytics:period_stats:{$rid}:{$from}:{$to}");
        Cache::forget("analytics:period_stats:{$rid}:{$today}:{$today}");
    }
}
