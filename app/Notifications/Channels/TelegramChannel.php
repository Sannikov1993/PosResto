<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Restaurant;
use App\Services\TelegramService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Laravel Notification Channel for Telegram.
 *
 * Supports white-label bots: if notifiable has a restaurant with configured bot,
 * uses that bot; otherwise falls back to platform bot.
 *
 * Usage in Notification class:
 *   public function via($notifiable) {
 *       return ['mail', TelegramChannel::class];
 *   }
 *
 *   public function toTelegram($notifiable): TelegramMessage {
 *       return TelegramMessage::create()
 *           ->line('Hello!')
 *           ->line('Your reservation is confirmed.');
 *   }
 */
class TelegramChannel
{
    protected const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    public function __construct(
        protected TelegramService $telegram,
    ) {}

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array|null Returns ['success' => bool, 'message_id' => ?int] or null
     */
    public function send($notifiable, Notification $notification): ?array
    {
        $chatId = $this->getChatId($notifiable);

        if (!$chatId) {
            return null;
        }

        // Get the telegram message from notification
        $message = $notification->toTelegram($notifiable);

        if ($message instanceof TelegramMessage) {
            $text = $message->render();
            $options = $message->getOptions();
        } elseif (is_string($message)) {
            $text = $message;
            $options = [];
        } else {
            return null;
        }

        // Try to get restaurant's white-label bot
        $restaurant = $this->getRestaurant($notifiable);

        if ($restaurant && $restaurant->hasTelegramBot()) {
            // Use restaurant's bot
            $success = $this->sendViaRestaurantBot($restaurant, $chatId, $text, $options);
        } else {
            // Fallback to platform bot
            if (!$this->telegram->isConfigured()) {
                return null;
            }
            $success = $this->telegram->sendMessage($chatId, $text, $options);
        }

        return [
            'success' => $success,
            'chat_id' => $chatId,
            'white_label' => $restaurant && $restaurant->hasTelegramBot(),
        ];
    }

    /**
     * Send message via restaurant's white-label bot.
     */
    protected function sendViaRestaurantBot(
        Restaurant $restaurant,
        string $chatId,
        string $text,
        array $options = [],
    ): bool {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ], $options);

            $response = Http::timeout(10)->post(
                self::TELEGRAM_API_BASE . $restaurant->telegram_bot_token . '/sendMessage',
                $params
            );

            if ($response->successful() && $response->json('ok')) {
                Log::debug('TelegramChannel: Message sent via restaurant bot', [
                    'restaurant_id' => $restaurant->id,
                    'chat_id' => $chatId,
                    'message_id' => $response->json('result.message_id'),
                ]);
                return true;
            }

            Log::warning('TelegramChannel: Failed to send via restaurant bot', [
                'restaurant_id' => $restaurant->id,
                'chat_id' => $chatId,
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('TelegramChannel: Error sending via restaurant bot', [
                'restaurant_id' => $restaurant->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the chat ID for the notifiable.
     */
    protected function getChatId($notifiable): ?string
    {
        // First try the standard Laravel routing method
        if (method_exists($notifiable, 'routeNotificationForTelegram')) {
            return $notifiable->routeNotificationForTelegram();
        }

        // Fallback to telegram_chat_id property
        if (isset($notifiable->telegram_chat_id)) {
            return $notifiable->telegram_chat_id;
        }

        return null;
    }

    /**
     * Get the restaurant for the notifiable (for white-label bot).
     */
    protected function getRestaurant($notifiable): ?Restaurant
    {
        // GuestRecipient has restaurant property
        if (isset($notifiable->restaurant) && $notifiable->restaurant instanceof Restaurant) {
            return $notifiable->restaurant;
        }

        // Customer model has restaurant_id
        if (isset($notifiable->restaurant_id)) {
            return Restaurant::find($notifiable->restaurant_id);
        }

        // Try to get via relation
        if (method_exists($notifiable, 'restaurant')) {
            return $notifiable->restaurant;
        }

        return null;
    }
}
