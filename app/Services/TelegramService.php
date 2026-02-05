<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис для работы с Telegram Bot API
 */
class TelegramService
{
    private string $botToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token') ?? '';
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Проверить настроен ли бот
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Отправить сообщение в чат
     *
     * @param int|string $chatId ID чата или username
     * @param string $message Текст сообщения
     * @param array $options Дополнительные параметры
     * @return bool
     */
    public function sendMessage($chatId, string $message, array $options = []): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('TelegramService: Бот не настроен');
            return false;
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/sendMessage", array_merge([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ], $options));

            if (!$response->successful()) {
                Log::error('TelegramService: Ошибка отправки', [
                    'chat_id' => $chatId,
                    'error' => $response->json(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('TelegramService: Исключение', [
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
            return false;
        }
    }

    /**
     * Отправить сообщение с inline-кнопками
     */
    public function sendMessageWithButtons($chatId, string $message, array $buttons): bool
    {
        $keyboard = [
            'inline_keyboard' => $buttons,
        ];

        return $this->sendMessage($chatId, $message, [
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    /**
     * Уведомление о новом заказе
     */
    public function notifyOrderCreated($chatId, array $orderData): bool
    {
        $message = "📦 <b>Заказ #{$orderData['order_number']} принят!</b>\n\n";
        $message .= "Сумма: <b>{$orderData['total']} ₽</b>\n";

        if (!empty($orderData['delivery_time'])) {
            $message .= "Доставка: ~{$orderData['delivery_time']} мин\n";
        }

        $message .= "\nОжидайте, мы уже готовим ваш заказ!";

        $buttons = [];
        if (!empty($orderData['track_url'])) {
            $buttons[] = [
                ['text' => '📍 Отследить заказ', 'url' => $orderData['track_url']],
            ];
        }

        if ($buttons) {
            return $this->sendMessageWithButtons($chatId, $message, $buttons);
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о статусе "Готовится"
     */
    public function notifyOrderCooking($chatId, array $orderData): bool
    {
        $message = "👨‍🍳 <b>Заказ #{$orderData['order_number']}</b>\n\n";
        $message .= "Ваш заказ готовится на кухне!";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о статусе "Готов"
     */
    public function notifyOrderReady($chatId, array $orderData): bool
    {
        $message = "✅ <b>Заказ #{$orderData['order_number']} готов!</b>\n\n";
        $message .= "Ищем курьера для доставки...";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о назначении курьера
     */
    public function notifyOrderCourierAssigned($chatId, array $orderData): bool
    {
        $message = "🚗 <b>Курьер в пути!</b>\n\n";
        $message .= "Заказ #{$orderData['order_number']}\n";

        if (!empty($orderData['courier_name'])) {
            $message .= "Курьер: {$orderData['courier_name']}\n";
        }

        if (!empty($orderData['eta'])) {
            $message .= "Примерное время: {$orderData['eta']} мин\n";
        }

        $buttons = [];
        if (!empty($orderData['courier_phone'])) {
            $buttons[] = [
                ['text' => '📞 Позвонить курьеру', 'url' => "tel:{$orderData['courier_phone']}"],
            ];
        }

        if ($buttons) {
            return $this->sendMessageWithButtons($chatId, $message, $buttons);
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о доставке
     */
    public function notifyOrderDelivered($chatId, array $orderData): bool
    {
        $message = "🎉 <b>Заказ #{$orderData['order_number']} доставлен!</b>\n\n";
        $message .= "Спасибо за заказ!\n";
        $message .= "Приятного аппетита! 🍽️";

        $buttons = [
            [
                ['text' => '⭐ Оставить отзыв', 'callback_data' => "review_{$orderData['order_id']}"],
            ],
        ];

        return $this->sendMessageWithButtons($chatId, $message, $buttons);
    }

    /**
     * Уведомление об отмене заказа
     */
    public function notifyOrderCancelled($chatId, array $orderData): bool
    {
        $message = "❌ <b>Заказ #{$orderData['order_number']} отменён</b>\n\n";

        if (!empty($orderData['reason'])) {
            $message .= "Причина: {$orderData['reason']}\n";
        }

        $message .= "\nПриносим извинения за неудобства.";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Установить webhook для бота
     */
    public function setWebhook(string $url): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::post("{$this->baseUrl}/setWebhook", [
                'url' => $url,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

            return $response->successful() && ($response->json()['ok'] ?? false);

        } catch (\Exception $e) {
            Log::error('TelegramService: Ошибка установки webhook', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить информацию о боте
     */
    public function getMe(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::get("{$this->baseUrl}/getMe");

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                return $response->json()['result'];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Обработать входящий webhook
     */
    public function handleWebhook(array $update): ?array
    {
        // Обработка сообщения
        if (isset($update['message'])) {
            return $this->handleMessage($update['message']);
        }

        // Обработка callback-кнопки
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        return null;
    }

    /**
     * Обработать входящее сообщение
     */
    private function handleMessage(array $message): ?array
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        // Команда /start - подписка на уведомления
        if (str_starts_with($text, '/start')) {
            $params = explode(' ', $text);
            $payload = $params[1] ?? null;

            return [
                'action' => 'subscribe',
                'chat_id' => $chatId,
                'payload' => $payload, // может содержать customer_id или phone
                'user' => [
                    'id' => $message['from']['id'] ?? null,
                    'first_name' => $message['from']['first_name'] ?? null,
                    'last_name' => $message['from']['last_name'] ?? null,
                    'username' => $message['from']['username'] ?? null,
                ],
            ];
        }

        // Команда /status - статус заказа
        if (str_starts_with($text, '/status')) {
            return [
                'action' => 'status',
                'chat_id' => $chatId,
            ];
        }

        // Команда /help
        if ($text === '/help') {
            $this->sendMessage($chatId,
                "🍽️ <b>Гастробар Клюква</b>\n\n" .
                "Доступные команды:\n" .
                "/start - Подписаться на уведомления\n" .
                "/status - Статус текущего заказа\n" .
                "/help - Помощь\n\n" .
                "После подписки вы будете получать уведомления о статусе ваших заказов."
            );

            return ['action' => 'help', 'chat_id' => $chatId];
        }

        return null;
    }

    /**
     * Обработать нажатие inline-кнопки
     */
    private function handleCallbackQuery(array $query): ?array
    {
        $chatId = $query['message']['chat']['id'] ?? null;
        $data = $query['data'] ?? '';

        // Ответить на callback чтобы убрать "часики"
        if (!empty($query['id'])) {
            Http::post("{$this->baseUrl}/answerCallbackQuery", [
                'callback_query_id' => $query['id'],
            ]);
        }

        // Обработка отзыва
        if (str_starts_with($data, 'review_')) {
            $orderId = str_replace('review_', '', $data);
            return [
                'action' => 'review',
                'chat_id' => $chatId,
                'order_id' => $orderId,
            ];
        }

        return null;
    }

    // ===== RESERVATION NOTIFICATIONS =====

    /**
     * Уведомление о новом бронировании
     */
    public function notifyReservationCreated($chatId, array $data): bool
    {
        $message = "📋 <b>Новое бронирование #{$data['id']}</b>\n\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📞 {$data['guest_phone']}\n";
        $message .= "📅 {$data['date']}\n";
        $message .= "🕐 {$data['time_range']}\n";
        $message .= "👥 Гостей: {$data['guests_count']}\n";

        if (!empty($data['table_name'])) {
            $message .= "🪑 Стол: {$data['table_name']}\n";
        }

        if (!empty($data['deposit']) && $data['deposit'] > 0) {
            $message .= "💰 Депозит: {$data['deposit']} ₽\n";
        }

        if (!empty($data['notes'])) {
            $message .= "\n📝 {$data['notes']}\n";
        }

        $message .= "\n<i>Статус: ожидает подтверждения</i>";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о подтверждении бронирования
     */
    public function notifyReservationConfirmed($chatId, array $data): bool
    {
        $message = "✅ <b>Бронирование #{$data['id']} подтверждено</b>\n\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📅 {$data['date']}\n";
        $message .= "🕐 {$data['time_range']}\n";
        $message .= "👥 Гостей: {$data['guests_count']}\n";

        if (!empty($data['table_name'])) {
            $message .= "🪑 Стол: {$data['table_name']}\n";
        }

        if (!empty($data['confirmed_by'])) {
            $message .= "\n<i>Подтвердил: {$data['confirmed_by']}</i>";
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление об отмене бронирования
     */
    public function notifyReservationCancelled($chatId, array $data): bool
    {
        $message = "❌ <b>Бронирование #{$data['id']} отменено</b>\n\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📅 {$data['date']}\n";
        $message .= "🕐 {$data['time_range']}\n";

        if (!empty($data['cancellation_reason'])) {
            $message .= "\n📝 Причина: {$data['cancellation_reason']}\n";
        }

        if (!empty($data['deposit_refunded'])) {
            $message .= "\n💰 Депозит возвращён: {$data['deposit']} ₽";
        } elseif (!empty($data['deposit']) && $data['deposit'] > 0) {
            $message .= "\n💰 Депозит: {$data['deposit']} ₽ (не возвращён)";
        }

        if (!empty($data['cancelled_by'])) {
            $message .= "\n\n<i>Отменил: {$data['cancelled_by']}</i>";
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление об оплате депозита
     */
    public function notifyReservationDepositPaid($chatId, array $data): bool
    {
        $message = "💰 <b>Депозит оплачен</b>\n\n";
        $message .= "📋 Бронирование #{$data['id']}\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📅 {$data['date']}\n";
        $message .= "🕐 {$data['time_range']}\n";
        $message .= "💵 Сумма: {$data['deposit']} ₽\n";

        if (!empty($data['payment_method'])) {
            $methods = [
                'cash' => 'Наличные',
                'card' => 'Карта',
                'online' => 'Онлайн',
            ];
            $message .= "💳 Способ: " . ($methods[$data['payment_method']] ?? $data['payment_method']) . "\n";
        }

        if (!empty($data['paid_by'])) {
            $message .= "\n<i>Принял: {$data['paid_by']}</i>";
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о посадке гостя
     */
    public function notifyReservationSeated($chatId, array $data): bool
    {
        $message = "🪑 <b>Гость посажен</b>\n\n";
        $message .= "📋 Бронирование #{$data['id']}\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "👥 Гостей: {$data['guests_count']}\n";

        if (!empty($data['table_name'])) {
            $message .= "🪑 Стол: {$data['table_name']}\n";
        }

        if (!empty($data['seated_by'])) {
            $message .= "\n<i>Посадил: {$data['seated_by']}</i>";
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Уведомление о no-show
     */
    public function notifyReservationNoShow($chatId, array $data): bool
    {
        $message = "⚠️ <b>Гость не пришёл (No-Show)</b>\n\n";
        $message .= "📋 Бронирование #{$data['id']}\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📞 {$data['guest_phone']}\n";
        $message .= "📅 {$data['date']}\n";
        $message .= "🕐 {$data['time_range']}\n";

        if (!empty($data['deposit']) && $data['deposit'] > 0) {
            $message .= "\n💰 Депозит: {$data['deposit']} ₽";

            if (!empty($data['deposit_forfeited'])) {
                $message .= " (удержан)";
            }
        }

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Напоминание о бронировании (для персонала)
     */
    public function notifyReservationReminder($chatId, array $data): bool
    {
        $message = "⏰ <b>Напоминание о бронировании</b>\n\n";
        $message .= "📋 Бронирование #{$data['id']}\n";
        $message .= "👤 {$data['guest_name']}\n";
        $message .= "📞 {$data['guest_phone']}\n";
        $message .= "🕐 Через {$data['minutes_until']} минут\n";
        $message .= "👥 Гостей: {$data['guests_count']}\n";

        if (!empty($data['table_name'])) {
            $message .= "🪑 Стол: {$data['table_name']}\n";
        }

        if (!empty($data['deposit']) && $data['deposit'] > 0) {
            $depositStatus = $data['deposit_paid'] ? '✅ оплачен' : '❌ не оплачен';
            $message .= "\n💰 Депозит: {$data['deposit']} ₽ ({$depositStatus})";
        }

        if (!empty($data['notes'])) {
            $message .= "\n\n📝 {$data['notes']}";
        }

        return $this->sendMessage($chatId, $message);
    }
}
