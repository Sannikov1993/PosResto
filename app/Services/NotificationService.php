<?php

namespace App\Services;

use App\Domain\Order\Enums\OrderType;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Единый сервис уведомлений (Telegram + Web Push)
 */
class NotificationService
{
    private TelegramService $telegram;
    private WebPushService $webPush;

    public function __construct(TelegramService $telegram, WebPushService $webPush)
    {
        $this->telegram = $telegram;
        $this->webPush = $webPush;
    }

    /**
     * Отправить уведомление о создании заказа
     */
    public function notifyOrderCreated(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);

        // Telegram
        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderCreated($telegramChatId, $orderData);
        }

        // Web Push
        $results['webpush'] = $this->webPush->notifyOrderCreated(
            $order->customer_id,
            $order->phone,
            $orderData
        );

        // Уведомляем админа
        $this->notifyAdmin($order, 'created');

        $this->logNotification('order_created', $order->id, $results);

        return $results;
    }

    /**
     * Отправить уведомление о статусе "Готовится"
     */
    public function notifyOrderCooking(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);

        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderCooking($telegramChatId, $orderData);
        }

        $results['webpush'] = $this->webPush->notifyOrderCooking(
            $order->customer_id,
            $order->phone,
            $orderData
        );

        $this->logNotification('order_cooking', $order->id, $results);

        return $results;
    }

    /**
     * Отправить уведомление о статусе "Готов"
     */
    public function notifyOrderReady(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);

        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderReady($telegramChatId, $orderData);
        }

        $this->logNotification('order_ready', $order->id, $results);

        return $results;
    }

    /**
     * Отправить уведомление о назначении курьера / начале доставки
     */
    public function notifyOrderDelivering(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);

        // Добавляем данные курьера
        if ($order->courier) {
            $orderData['courier_name'] = $order->courier->name;
            $orderData['courier_phone'] = $order->courier->phone ?? null;
        }

        // Расчёт ETA
        $orderData['eta'] = $order->estimated_delivery_minutes
            ?? $order->deliveryZone?->estimated_time
            ?? 30;

        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderCourierAssigned($telegramChatId, $orderData);
        }

        $results['webpush'] = $this->webPush->notifyOrderCourierAssigned(
            $order->customer_id,
            $order->phone,
            $orderData
        );

        $this->logNotification('order_delivering', $order->id, $results);

        return $results;
    }

    /**
     * Отправить уведомление о доставке
     */
    public function notifyOrderCompleted(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);

        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderDelivered($telegramChatId, $orderData);
        }

        $results['webpush'] = $this->webPush->notifyOrderDelivered(
            $order->customer_id,
            $order->phone,
            $orderData
        );

        $this->logNotification('order_completed', $order->id, $results);

        return $results;
    }

    /**
     * Отправить уведомление об отмене
     */
    public function notifyOrderCancelled(Order $order): array
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return ['telegram' => false, 'webpush' => 0];
        }

        $results = ['telegram' => false, 'webpush' => 0];
        $orderData = $this->prepareOrderData($order);
        $orderData['reason'] = $order->cancel_reason;

        if ($telegramChatId = $this->getTelegramChatId($order)) {
            $results['telegram'] = $this->telegram->notifyOrderCancelled($telegramChatId, $orderData);
        }

        $this->logNotification('order_cancelled', $order->id, $results);

        return $results;
    }

    /**
     * Уведомить администратора о событии заказа
     */
    public function notifyAdmin(Order $order, string $event): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        if (!$adminChatId) {
            return;
        }

        $message = match($event) {
            'created' => "🆕 <b>Новый заказ!</b>\n\n" .
                "📦 #{$order->order_number}\n" .
                "💰 {$order->total} ₽\n" .
                "📍 {$order->delivery_address}\n" .
                "📞 {$order->phone}",
            'cooking' => "👨‍🍳 #{$order->order_number} — готовится",
            'ready' => "✅ #{$order->order_number} — готов к отправке",
            'delivering' => "🚗 #{$order->order_number} — курьер в пути",
            'completed' => "🎉 #{$order->order_number} — доставлен",
            'cancelled' => "❌ #{$order->order_number} — отменён" .
                ($order->cancel_reason ? "\nПричина: {$order->cancel_reason}" : ''),
            default => null,
        };

        if ($message) {
            $this->telegram->sendMessage($adminChatId, $message);
        }
    }

    /**
     * Подготовить данные заказа для уведомлений
     */
    private function prepareOrderData(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'delivery_time' => $order->estimated_delivery_minutes ?? $order->deliveryZone?->estimated_time ?? 45,
            'track_url' => url("/track/{$order->order_number}"),
            'status' => $order->status,
            'address' => $order->delivery_address,
        ];
    }

    /**
     * Получить Telegram chat_id клиента
     */
    private function getTelegramChatId(Order $order): ?string
    {
        // Проверяем связанного клиента
        if ($order->customer && $order->customer->telegram_chat_id) {
            return $order->customer->telegram_chat_id;
        }

        // Ищем по телефону (только в рамках ресторана заказа)
        if ($order->phone && $order->restaurant_id) {
            $phone = preg_replace('/\D/', '', $order->phone);
            $customer = Customer::forRestaurant($order->restaurant_id)
                ->where('phone', 'like', "%{$phone}%")
                ->whereNotNull('telegram_chat_id')
                ->first();

            if ($customer) {
                return $customer->telegram_chat_id;
            }
        }

        return null;
    }

    /**
     * Логировать отправку уведомления
     */
    private function logNotification(string $type, int $orderId, array $results): void
    {
        Log::info("NotificationService: {$type}", [
            'order_id' => $orderId,
            'telegram' => $results['telegram'],
            'webpush' => $results['webpush'],
        ]);
    }

    /**
     * Отправить тестовое уведомление
     */
    public function sendTestNotification(?int $customerId = null, ?string $phone = null, ?string $telegramChatId = null): array
    {
        $results = ['telegram' => false, 'webpush' => 0];

        $testData = [
            'order_id' => 0,
            'order_number' => 'TEST-001',
            'total' => 1500,
            'delivery_time' => 30,
            'track_url' => url('/track'),
        ];

        if ($telegramChatId) {
            $results['telegram'] = $this->telegram->notifyOrderCreated($telegramChatId, $testData);
        }

        if ($customerId || $phone) {
            $results['webpush'] = $this->webPush->notifyOrderCreated($customerId, $phone, $testData);
        }

        return $results;
    }
}
