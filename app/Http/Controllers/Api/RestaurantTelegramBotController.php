<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\RestaurantTelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller for managing restaurant's white-label Telegram bot.
 *
 * Allows restaurant admins to:
 * - Configure their own Telegram bot
 * - Setup/remove webhook
 * - View bot status
 */
class RestaurantTelegramBotController extends Controller
{
    public function __construct(
        protected RestaurantTelegramBotService $botService,
    ) {}

    /**
     * Get current bot status.
     *
     * GET /api/restaurant/telegram-bot
     */
    public function status(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $botInfo = $restaurant->getTelegramBotInfo();
        $webhookInfo = null;

        if ($restaurant->hasTelegramBotConfigured()) {
            $webhookResult = $this->botService->getWebhookInfo($restaurant);
            if ($webhookResult['success']) {
                $webhookInfo = [
                    'url' => $webhookResult['info']['url'] ?? null,
                    'has_custom_certificate' => $webhookResult['info']['has_custom_certificate'] ?? false,
                    'pending_update_count' => $webhookResult['info']['pending_update_count'] ?? 0,
                    'last_error_date' => $webhookResult['info']['last_error_date'] ?? null,
                    'last_error_message' => $webhookResult['info']['last_error_message'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $restaurant->hasTelegramBotConfigured(),
                'active' => $restaurant->hasTelegramBot(),
                'bot' => $botInfo,
                'webhook' => $webhookInfo,
            ],
        ]);
    }

    /**
     * Configure a new bot.
     *
     * POST /api/restaurant/telegram-bot
     * { "bot_token": "123456789:ABC..." }
     */
    public function configure(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'bot_token' => 'required|string|min:40|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->botService->configureBot(
            restaurant: $restaurant,
            botToken: $request->input('bot_token'),
        );

        if (!$result['success']) {
            $status = match ($result['error'] ?? 'error') {
                'invalid_token_format', 'invalid_token' => 422,
                'bot_already_used' => 409,
                default => 400,
            };

            return response()->json($result, $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bot' => $result['bot'],
                'message' => $result['message'],
            ],
        ]);
    }

    /**
     * Setup webhook for bot.
     *
     * POST /api/restaurant/telegram-bot/webhook
     */
    public function setupWebhook(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $result = $this->botService->setupWebhook($restaurant);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'webhook_url' => $result['webhook_url'],
                'message' => $result['message'],
            ],
        ]);
    }

    /**
     * Remove webhook (deactivate bot but keep configuration).
     *
     * DELETE /api/restaurant/telegram-bot/webhook
     */
    public function removeWebhook(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $result = $this->botService->removeWebhook($restaurant);

        return response()->json($result);
    }

    /**
     * Completely remove bot configuration.
     *
     * DELETE /api/restaurant/telegram-bot
     */
    public function remove(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $result = $this->botService->removeBot($restaurant);

        return response()->json($result);
    }

    /**
     * Send test message.
     *
     * POST /api/restaurant/telegram-bot/test
     * { "chat_id": "123456", "message": "Test" }
     */
    public function sendTest(Request $request): JsonResponse
    {
        $restaurant = $this->getRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|string',
            'message' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = $request->input('message', "🧪 Тестовое сообщение от {$restaurant->name}");

        $result = $this->botService->sendTestMessage(
            restaurant: $restaurant,
            chatId: $request->input('chat_id'),
            message: $message,
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Тестовое сообщение отправлено.',
        ]);
    }

    /**
     * Get restaurant from request context.
     */
    protected function getRestaurant(Request $request): ?Restaurant
    {
        // From authenticated user
        $user = $request->user();
        if ($user && $user->restaurant_id) {
            return Restaurant::find($user->restaurant_id);
        }

        // Or from request parameter
        if ($request->has('restaurant_id')) {
            return Restaurant::find($request->input('restaurant_id'));
        }

        return null;
    }
}
