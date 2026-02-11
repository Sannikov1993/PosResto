<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use App\Services\WebPushService;
use App\Services\NotificationService;
use App\Models\Customer;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * API контроллер уведомлений
 */
class NotificationController extends Controller
{
    // ===== WEB PUSH =====

    /**
     * Получить публичный VAPID ключ
     */
    public function getVapidKey(WebPushService $webPush): JsonResponse
    {
        return response()->json([
            'success' => true,
            'public_key' => $webPush->getPublicKey(),
        ]);
    }

    /**
     * Подписаться на Web Push уведомления
     */
    public function subscribePush(Request $request, WebPushService $webPush): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $subscription = $webPush->saveSubscription(
            $validated,
            $validated['customer_id'] ?? null,
            $validated['phone'] ?? null
        );

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось сохранить подписку',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Подписка сохранена',
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Отписаться от Web Push
     */
    public function unsubscribePush(Request $request, WebPushService $webPush): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url',
        ]);

        $deleted = $webPush->deleteSubscription($validated['endpoint']);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Подписка удалена' : 'Подписка не найдена',
        ]);
    }

    // ===== TELEGRAM =====

    /**
     * Webhook для Telegram бота
     */
    public function telegramWebhook(Request $request, TelegramService $telegram): JsonResponse
    {
        // Validate webhook secret (обязательный — без секрета endpoint закрыт)
        $expectedSecret = config('services.telegram.webhook_secret');
        if (!$expectedSecret) {
            Log::error('Telegram webhook: secret not configured, rejecting request');
            return response()->json(['ok' => false], 403);
        }

        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (!$secretHeader || !hash_equals($expectedSecret, $secretHeader)) {
            Log::warning('Telegram webhook: invalid secret token');
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        $result = $telegram->handleWebhook($update);

        if ($result && $result['action'] === 'subscribe') {
            // Обработка подписки
            $this->handleTelegramSubscription($result, $telegram);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Обработать подписку из Telegram
     */
    private function handleTelegramSubscription(array $data, TelegramService $telegram): void
    {
        $chatId = $data['chat_id'];
        $payload = $data['payload'] ?? null;

        // Если в payload есть ID клиента — связываем с конкретным клиентом
        // Поиск по phone удалён: без restaurant_id он пробивал tenant isolation
        if ($payload) {
            if (str_starts_with($payload, 'customer_')) {
                $customerId = (int) str_replace('customer_', '', $payload);
                if ($customerId > 0) {
                    Customer::where('id', $customerId)->update([
                        'telegram_chat_id' => $chatId,
                        'telegram_username' => $data['user']['username'] ?? null,
                    ]);
                }
            }
        }

        // Приветственное сообщение
        $name = $data['user']['first_name'] ?? 'друг';
        $telegram->sendMessage($chatId,
            "👋 Привет, {$name}!\n\n" .
            "Теперь вы будете получать уведомления о статусе ваших заказов в <b>Гастробар Клюква</b>.\n\n" .
            "📦 Создание заказа\n" .
            "👨‍🍳 Приготовление\n" .
            "🚗 Курьер в пути\n" .
            "✅ Доставлено\n\n" .
            "Приятного аппетита! 🍽️"
        );
    }

    /**
     * Установить webhook для Telegram бота
     */
    public function setTelegramWebhook(Request $request, TelegramService $telegram): JsonResponse
    {
        $url = $request->input('url') ?? config('services.telegram.webhook_url');

        if (!$url) {
            $url = url('/api/telegram/webhook');
        }

        $success = $telegram->setWebhook($url);

        return response()->json([
            'success' => $success,
            'message' => $success ? "Webhook установлен: {$url}" : 'Ошибка установки webhook',
        ]);
    }

    /**
     * Получить информацию о Telegram боте
     */
    public function getTelegramBot(TelegramService $telegram): JsonResponse
    {
        $info = $telegram->getMe();

        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Бот не настроен или недоступен',
            ]);
        }

        return response()->json([
            'success' => true,
            'bot' => $info,
            'subscribe_link' => "https://t.me/{$info['username']}",
        ]);
    }

    /**
     * Получить ссылку для подписки клиента на Telegram
     */
    public function getTelegramSubscribeLink(Request $request, TelegramService $telegram): JsonResponse
    {
        $phone = $request->input('phone');
        $customerId = $request->input('customer_id');

        $botInfo = $telegram->getMe();
        if (!$botInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram бот не настроен',
            ]);
        }

        $botUsername = $botInfo['username'];

        // Формируем deep link
        $payload = '';
        if ($customerId) {
            $payload = "customer_{$customerId}";
        } elseif ($phone) {
            $payload = "phone_" . preg_replace('/\D/', '', $phone);
        }

        $link = "https://t.me/{$botUsername}";
        if ($payload) {
            $link .= "?start={$payload}";
        }

        return response()->json([
            'success' => true,
            'link' => $link,
            'bot_username' => $botUsername,
        ]);
    }

    // ===== ТЕСТИРОВАНИЕ =====

    /**
     * Отправить тестовое уведомление
     */
    public function sendTestNotification(Request $request, NotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'phone' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
        ]);

        $results = $notifications->sendTestNotification(
            $validated['customer_id'] ?? null,
            $validated['phone'] ?? null,
            $validated['telegram_chat_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}
