<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing white-label Telegram bots for restaurants.
 *
 * Each restaurant can configure their own Telegram bot for guest notifications.
 * This service handles:
 * - Bot verification (getMe API)
 * - Webhook setup/removal
 * - Bot status management
 *
 * Usage:
 *   $service = app(RestaurantTelegramBotService::class);
 *
 *   // Configure a new bot
 *   $result = $service->configureBot($restaurant, $botToken);
 *
 *   // Setup webhook for bot
 *   $result = $service->setupWebhook($restaurant);
 *
 *   // Remove webhook
 *   $result = $service->removeWebhook($restaurant);
 */
class RestaurantTelegramBotService
{
    protected const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    /**
     * Configure and verify a Telegram bot for a restaurant.
     *
     * @param Restaurant $restaurant
     * @param string $botToken Bot API token from @BotFather
     * @return array{success: bool, error?: string, bot?: array}
     */
    public function configureBot(Restaurant $restaurant, string $botToken): array
    {
        // Validate token format
        if (!$this->isValidTokenFormat($botToken)) {
            return [
                'success' => false,
                'error' => 'invalid_token_format',
                'message' => 'Неверный формат токена. Токен должен быть в формате 123456789:ABC-DEF...',
            ];
        }

        // Verify bot with Telegram API
        $botInfo = $this->getMe($botToken);

        if (!$botInfo) {
            return [
                'success' => false,
                'error' => 'invalid_token',
                'message' => 'Не удалось подключиться к боту. Проверьте токен.',
            ];
        }

        // Check if this bot is already used by another restaurant
        $existingRestaurant = Restaurant::where('telegram_bot_id', (string) $botInfo['id'])
            ->where('id', '!=', $restaurant->id)
            ->first();

        if ($existingRestaurant) {
            return [
                'success' => false,
                'error' => 'bot_already_used',
                'message' => 'Этот бот уже используется другим рестораном.',
            ];
        }

        // Generate webhook secret
        $webhookSecret = Str::random(64);

        // Save bot configuration
        $restaurant->update([
            'telegram_bot_token' => $botToken,
            'telegram_bot_username' => $botInfo['username'],
            'telegram_bot_id' => (string) $botInfo['id'],
            'telegram_webhook_secret' => $webhookSecret,
            'telegram_bot_active' => false, // Not active until webhook is set
            'telegram_bot_verified_at' => now(),
        ]);

        Log::info('RestaurantTelegramBotService: Bot configured', [
            'restaurant_id' => $restaurant->id,
            'bot_username' => $botInfo['username'],
            'bot_id' => $botInfo['id'],
        ]);

        return [
            'success' => true,
            'bot' => [
                'id' => $botInfo['id'],
                'username' => $botInfo['username'],
                'first_name' => $botInfo['first_name'] ?? null,
            ],
            'message' => 'Бот успешно подключен. Теперь установите webhook.',
        ];
    }

    /**
     * Setup webhook for restaurant's Telegram bot.
     *
     * @param Restaurant $restaurant
     * @param string|null $baseUrl Override base URL (for testing)
     * @return array{success: bool, error?: string}
     */
    public function setupWebhook(Restaurant $restaurant, ?string $baseUrl = null): array
    {
        if (!$restaurant->hasTelegramBotConfigured()) {
            return [
                'success' => false,
                'error' => 'no_bot_configured',
                'message' => 'Сначала настройте бота.',
            ];
        }

        $baseUrl = $baseUrl ?? config('app.url');

        // Check for HTTPS (required by Telegram)
        if (!str_starts_with($baseUrl, 'https://')) {
            // In local/development - activate without webhook
            if (app()->environment('local', 'development')) {
                $restaurant->update([
                    'telegram_bot_active' => true,
                ]);

                Log::info('RestaurantTelegramBotService: Bot activated without webhook (local env)', [
                    'restaurant_id' => $restaurant->id,
                ]);

                return [
                    'success' => true,
                    'warning' => 'local_mode',
                    'message' => 'Бот активирован в режиме разработки (без webhook). На продакшене с HTTPS webhook установится автоматически.',
                ];
            }

            return [
                'success' => false,
                'error' => 'https_required',
                'message' => 'Telegram требует HTTPS для webhook. Настройте SSL-сертификат или используйте ngrok для тестирования.',
            ];
        }

        // Webhook URL includes bot_id for routing
        $webhookUrl = rtrim($baseUrl, '/') . "/api/telegram/guest-bot/webhook/{$restaurant->telegram_bot_id}";

        // Set webhook with Telegram API
        $result = $this->setWebhook(
            token: $restaurant->telegram_bot_token,
            url: $webhookUrl,
            secret: $restaurant->telegram_webhook_secret,
        );

        if (!$result['success']) {
            return $result;
        }

        // Activate bot
        $restaurant->update([
            'telegram_bot_active' => true,
        ]);

        Log::info('RestaurantTelegramBotService: Webhook set', [
            'restaurant_id' => $restaurant->id,
            'webhook_url' => $webhookUrl,
        ]);

        return [
            'success' => true,
            'webhook_url' => $webhookUrl,
            'message' => 'Webhook установлен. Бот активен.',
        ];
    }

    /**
     * Remove webhook and deactivate bot.
     */
    public function removeWebhook(Restaurant $restaurant): array
    {
        if (!$restaurant->hasTelegramBotConfigured()) {
            return [
                'success' => false,
                'error' => 'no_bot_configured',
                'message' => 'Бот не настроен.',
            ];
        }

        // Delete webhook with Telegram API
        try {
            $response = Http::post(
                self::TELEGRAM_API_BASE . $restaurant->telegram_bot_token . '/deleteWebhook'
            );

            if (!$response->successful() || !$response->json('ok')) {
                Log::warning('RestaurantTelegramBotService: Failed to delete webhook', [
                    'restaurant_id' => $restaurant->id,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('RestaurantTelegramBotService: Error deleting webhook', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Deactivate bot
        $restaurant->update([
            'telegram_bot_active' => false,
        ]);

        Log::info('RestaurantTelegramBotService: Webhook removed', [
            'restaurant_id' => $restaurant->id,
        ]);

        return [
            'success' => true,
            'message' => 'Webhook удалён. Бот деактивирован.',
        ];
    }

    /**
     * Completely remove bot configuration.
     */
    public function removeBot(Restaurant $restaurant): array
    {
        if (!$restaurant->hasTelegramBotConfigured()) {
            return [
                'success' => false,
                'error' => 'no_bot_configured',
                'message' => 'Бот не настроен.',
            ];
        }

        // First remove webhook
        $this->removeWebhook($restaurant);

        // Clear bot configuration
        $restaurant->update([
            'telegram_bot_token' => null,
            'telegram_bot_username' => null,
            'telegram_bot_id' => null,
            'telegram_webhook_secret' => null,
            'telegram_bot_active' => false,
            'telegram_bot_verified_at' => null,
        ]);

        Log::info('RestaurantTelegramBotService: Bot removed', [
            'restaurant_id' => $restaurant->id,
        ]);

        return [
            'success' => true,
            'message' => 'Бот отключен.',
        ];
    }

    /**
     * Get webhook info for restaurant's bot.
     */
    public function getWebhookInfo(Restaurant $restaurant): array
    {
        if (!$restaurant->hasTelegramBotConfigured()) {
            return [
                'success' => false,
                'error' => 'no_bot_configured',
            ];
        }

        try {
            $response = Http::post(
                self::TELEGRAM_API_BASE . $restaurant->telegram_bot_token . '/getWebhookInfo'
            );

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success' => true,
                    'info' => $response->json('result'),
                ];
            }

            return [
                'success' => false,
                'error' => 'api_error',
                'message' => $response->json('description') ?? 'Unknown error',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'connection_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a test message through restaurant's bot.
     */
    public function sendTestMessage(Restaurant $restaurant, string $chatId, string $message): array
    {
        if (!$restaurant->hasTelegramBot()) {
            return [
                'success' => false,
                'error' => 'bot_not_active',
                'message' => 'Бот не активен.',
            ];
        }

        try {
            $response = Http::post(
                self::TELEGRAM_API_BASE . $restaurant->telegram_bot_token . '/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success' => true,
                    'message_id' => $response->json('result.message_id'),
                ];
            }

            return [
                'success' => false,
                'error' => 'send_failed',
                'message' => $response->json('description') ?? 'Failed to send message',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'connection_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    // ===== TELEGRAM API METHODS =====

    /**
     * Get bot information via getMe API.
     */
    protected function getMe(string $token): ?array
    {
        try {
            $response = Http::timeout(10)->post(
                self::TELEGRAM_API_BASE . $token . '/getMe'
            );

            if ($response->successful() && $response->json('ok')) {
                return $response->json('result');
            }

            Log::warning('RestaurantTelegramBotService: getMe failed', [
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('RestaurantTelegramBotService: getMe error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set webhook for bot.
     */
    protected function setWebhook(string $token, string $url, string $secret): array
    {
        try {
            $response = Http::timeout(30)->post(
                self::TELEGRAM_API_BASE . $token . '/setWebhook',
                [
                    'url' => $url,
                    'secret_token' => $secret,
                    'allowed_updates' => ['message', 'callback_query'],
                ]
            );

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success' => true,
                ];
            }

            return [
                'success' => false,
                'error' => 'webhook_failed',
                'message' => $response->json('description') ?? 'Failed to set webhook',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'connection_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate token format.
     */
    protected function isValidTokenFormat(string $token): bool
    {
        // Token format: {bot_id}:{random_string}
        // Example: 123456789:ABC-DEFGhijklMNOpqrstuvwxyz
        return (bool) preg_match('/^\d+:[A-Za-z0-9_-]{35,}$/', $token);
    }
}
