<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Services\ChannelLinkingService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Guest Bot Webhook Controller (White-Label).
 *
 * Handles incoming updates from restaurant's Telegram bots:
 * - /start link_{token} - Channel linking flow
 * - /start - General bot start
 * - /unlink - Unlink Telegram
 * - /help - Help message
 * - Callback queries for confirmation buttons
 *
 * Multi-tenant: Each restaurant has its own bot, routed by bot_id in URL.
 */
class TelegramBotController extends Controller
{
    /**
     * Current restaurant context (set in webhook method).
     */
    protected ?Restaurant $restaurant = null;

    public function __construct(
        protected TelegramService $telegram,
        protected ChannelLinkingService $linkingService,
    ) {}

    /**
     * Handle incoming Telegram webhook for a restaurant's bot.
     *
     * Route: POST /api/telegram/guest-bot/webhook/{botId}
     */
    public function webhook(Request $request, ?string $botId = null): JsonResponse
    {
        // Find restaurant by bot_id
        if ($botId) {
            $this->restaurant = Restaurant::findByTelegramBotId($botId);

            if (!$this->restaurant) {
                Log::warning('TelegramBotController: Unknown bot_id', ['bot_id' => $botId]);
                return response()->json(['ok' => false, 'error' => 'unknown_bot'], 404);
            }

            // Verify webhook secret
            $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($secretHeader && $secretHeader !== $this->restaurant->telegram_webhook_secret) {
                Log::warning('TelegramBotController: Invalid webhook secret', [
                    'bot_id' => $botId,
                    'restaurant_id' => $this->restaurant->id,
                ]);
                return response()->json(['ok' => false, 'error' => 'invalid_secret'], 403);
            }
        }

        $update = $request->all();

        Log::debug('TelegramBotController: Received update', [
            'update_id' => $update['update_id'] ?? null,
            'restaurant_id' => $this->restaurant?->id,
            'bot_id' => $botId,
        ]);

        try {
            // Handle message
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            // Handle callback query (button clicks)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }

        } catch (\Throwable $e) {
            Log::error('TelegramBotController: Error handling update', [
                'error' => $e->getMessage(),
                'update_id' => $update['update_id'] ?? null,
                'restaurant_id' => $this->restaurant?->id,
            ]);
        }

        // Always return 200 to Telegram
        return response()->json(['ok' => true]);
    }

    /**
     * Handle incoming message.
     */
    protected function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'] ?? [];

        // /start command
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($chatId, $text, $from);
            return;
        }

        // /unlink command
        if ($text === '/unlink') {
            $this->handleUnlinkCommand($chatId);
            return;
        }

        // /help command
        if ($text === '/help') {
            $this->handleHelpCommand($chatId);
            return;
        }

        // /status command
        if ($text === '/status') {
            $this->handleStatusCommand($chatId);
            return;
        }

        // Unknown command or message - show help
        $restaurantName = $this->restaurant?->name ?? 'ресторана';
        $this->sendBotMessage($chatId,
            "Я бот для уведомлений о бронированиях {$restaurantName}.\n\n" .
            "Используйте /help для списка команд."
        );
    }

    /**
     * Handle /start command.
     */
    protected function handleStartCommand(string $chatId, string $text, array $from): void
    {
        // Parse payload: /start link_{signedToken}
        $parts = explode(' ', $text, 2);
        $payload = $parts[1] ?? '';

        // Link command
        if (str_starts_with($payload, 'link_')) {
            $signedToken = substr($payload, 5); // Remove 'link_' prefix
            $this->handleLinkRequest($chatId, $signedToken, $from);
            return;
        }

        // Regular /start - welcome message
        $firstName = $from['first_name'] ?? 'друг';
        $restaurantName = $this->restaurant?->name ?? 'нашего ресторана';

        $this->sendBotMessage($chatId,
            "👋 Привет, {$firstName}!\n\n" .
            "Я бот для уведомлений о бронированиях {$restaurantName}.\n\n" .
            "Чтобы получать уведомления, привяжите Telegram " .
            "при создании бронирования на сайте или в приложении.\n\n" .
            "Команды:\n" .
            "/status — проверить привязку\n" .
            "/unlink — отвязать Telegram\n" .
            "/help — помощь"
        );
    }

    /**
     * Handle link request from deep link.
     */
    protected function handleLinkRequest(string $chatId, string $signedToken, array $from): void
    {
        // Verify token first (without completing)
        $token = \App\Models\ChannelLinkToken::findBySignedToken($signedToken);

        if (!$token) {
            $this->sendBotMessage($chatId,
                "❌ Ссылка недействительна или истекла.\n\n" .
                "Запросите новую ссылку на сайте или в приложении."
            );
            return;
        }

        // Verify token belongs to this restaurant (if we have restaurant context)
        if ($this->restaurant && $token->restaurant_id !== $this->restaurant->id) {
            $this->sendBotMessage($chatId,
                "❌ Эта ссылка предназначена для другого ресторана.\n\n" .
                "Убедитесь, что вы используете правильную ссылку."
            );
            return;
        }

        // Get customer info for confirmation
        $customer = $token->customer;
        $maskedPhone = $this->maskPhone($customer->phone);
        $restaurantName = $token->restaurant?->name ?? 'ресторана';

        // Send confirmation with buttons
        $buttons = [
            [
                ['text' => '✅ Да, привязать', 'callback_data' => "link_confirm_{$signedToken}"],
            ],
            [
                ['text' => '❌ Отмена', 'callback_data' => 'link_cancel'],
            ],
        ];

        $this->sendBotMessageWithButtons($chatId,
            "🔗 <b>Привязка Telegram</b>\n\n" .
            "Привязать этот Telegram аккаунт к номеру <b>{$maskedPhone}</b>?\n\n" .
            "После привязки вы будете получать уведомления о бронированиях от <b>{$restaurantName}</b>.",
            $buttons
        );
    }

    /**
     * Handle callback query (button clicks).
     */
    protected function handleCallbackQuery(array $query): void
    {
        $chatId = $query['message']['chat']['id'] ?? null;
        $messageId = $query['message']['message_id'] ?? null;
        $data = $query['data'] ?? '';
        $from = $query['from'] ?? [];
        $callbackId = $query['id'];

        // Acknowledge callback
        $this->answerCallback($callbackId);

        if (!$chatId) {
            return;
        }

        // Link confirmation
        if (str_starts_with($data, 'link_confirm_')) {
            $signedToken = substr($data, 13); // Remove 'link_confirm_' prefix
            $this->completeLinking($chatId, $messageId, $signedToken, $from);
            return;
        }

        // Link cancellation
        if ($data === 'link_cancel') {
            $this->editBotMessage($chatId, $messageId,
                "❌ Привязка отменена.\n\n" .
                "Вы можете привязать Telegram позже через сайт или приложение."
            );
            return;
        }

        // Unlink confirmation
        if ($data === 'unlink_confirm') {
            $this->completeUnlinking($chatId, $messageId);
            return;
        }

        // Unlink cancellation
        if ($data === 'unlink_cancel') {
            $this->editBotMessage($chatId, $messageId,
                "Отвязка отменена. Вы продолжите получать уведомления."
            );
            return;
        }
    }

    /**
     * Complete the linking process.
     */
    protected function completeLinking(
        string $chatId,
        ?int $messageId,
        string $signedToken,
        array $from,
    ): void {
        $username = $from['username'] ?? null;

        $result = $this->linkingService->completeTelegramLink(
            signedToken: $signedToken,
            chatId: $chatId,
            username: $username,
        );

        if ($result['success']) {
            $restaurantName = $this->restaurant?->name ?? 'ресторана';
            $message = "✅ <b>Telegram успешно привязан!</b>\n\n" .
                "Теперь вы будете получать уведомления от <b>{$restaurantName}</b> о:\n" .
                "• Подтверждении бронирований\n" .
                "• Напоминаниях\n" .
                "• Изменениях статуса\n\n" .
                "Чтобы отвязать — используйте /unlink";
        } else {
            $message = "❌ {$result['message']}";
        }

        if ($messageId) {
            $this->editBotMessage($chatId, $messageId, $message);
        } else {
            $this->sendBotMessage($chatId, $message);
        }
    }

    /**
     * Handle /unlink command.
     */
    protected function handleUnlinkCommand(string $chatId): void
    {
        // Find customer by chat ID (scoped to restaurant if we have context)
        $query = Customer::where('telegram_chat_id', $chatId);
        if ($this->restaurant) {
            $query->where('restaurant_id', $this->restaurant->id);
        }
        $customer = $query->first();

        if (!$customer) {
            $this->sendBotMessage($chatId,
                "ℹ️ Этот Telegram не привязан ни к одному номеру."
            );
            return;
        }

        $maskedPhone = $this->maskPhone($customer->phone);
        $restaurantName = $this->restaurant?->name ?? 'ресторана';

        // Confirmation buttons
        $buttons = [
            [
                ['text' => '✅ Да, отвязать', 'callback_data' => 'unlink_confirm'],
            ],
            [
                ['text' => '❌ Отмена', 'callback_data' => 'unlink_cancel'],
            ],
        ];

        $this->sendBotMessageWithButtons($chatId,
            "🔓 <b>Отвязка Telegram</b>\n\n" .
            "Отвязать Telegram от номера <b>{$maskedPhone}</b>?\n\n" .
            "Вы перестанете получать уведомления о бронированиях от <b>{$restaurantName}</b>.",
            $buttons
        );
    }

    /**
     * Complete the unlinking process.
     */
    protected function completeUnlinking(string $chatId, ?int $messageId): void
    {
        // Find customer (scoped to restaurant if we have context)
        $query = Customer::where('telegram_chat_id', $chatId);
        if ($this->restaurant) {
            $query->where('restaurant_id', $this->restaurant->id);
        }
        $customer = $query->first();

        if (!$customer) {
            $message = "ℹ️ Telegram уже отвязан.";
        } else {
            $result = $this->linkingService->unlinkTelegram($customer);
            $restaurantName = $this->restaurant?->name ?? 'ресторана';
            $message = $result['success']
                ? "✅ Telegram отвязан.\n\nВы больше не будете получать уведомления от {$restaurantName}."
                : "❌ {$result['message']}";
        }

        if ($messageId) {
            $this->editBotMessage($chatId, $messageId, $message);
        } else {
            $this->sendBotMessage($chatId, $message);
        }
    }

    /**
     * Handle /status command.
     */
    protected function handleStatusCommand(string $chatId): void
    {
        // Find customer (scoped to restaurant if we have context)
        $query = Customer::where('telegram_chat_id', $chatId);
        if ($this->restaurant) {
            $query->where('restaurant_id', $this->restaurant->id);
        }
        $customer = $query->first();

        if (!$customer) {
            $this->sendBotMessage($chatId,
                "ℹ️ Этот Telegram не привязан ни к одному номеру.\n\n" .
                "Привяжите Telegram при создании бронирования на сайте."
            );
            return;
        }

        $maskedPhone = $this->maskPhone($customer->phone);
        $linkedAt = $customer->telegram_linked_at?->format('d.m.Y H:i') ?? 'неизвестно';
        $restaurantName = $this->restaurant?->name ?? $customer->restaurant?->name ?? 'ресторан';

        $this->sendBotMessage($chatId,
            "📱 <b>Статус привязки</b>\n\n" .
            "Ресторан: <b>{$restaurantName}</b>\n" .
            "Номер: <b>{$maskedPhone}</b>\n" .
            "Привязан: {$linkedAt}\n" .
            "Уведомления: " . ($customer->telegram_consent ? '✅ включены' : '❌ выключены') . "\n\n" .
            "Чтобы отвязать — /unlink"
        );
    }

    /**
     * Handle /help command.
     */
    protected function handleHelpCommand(string $chatId): void
    {
        $restaurantName = $this->restaurant?->name ?? 'ресторана';

        $this->sendBotMessage($chatId,
            "📚 <b>Справка</b>\n\n" .
            "Я бот для уведомлений о бронированиях {$restaurantName}.\n\n" .
            "<b>Как привязать Telegram:</b>\n" .
            "1. Создайте бронирование на сайте\n" .
            "2. Нажмите «Получать уведомления в Telegram»\n" .
            "3. Перейдите по ссылке и подтвердите\n\n" .
            "<b>Команды:</b>\n" .
            "/status — проверить привязку\n" .
            "/unlink — отвязать Telegram\n" .
            "/help — эта справка\n\n" .
            "<b>Какие уведомления приходят:</b>\n" .
            "• Подтверждение бронирования\n" .
            "• Напоминание за 2 часа\n" .
            "• Изменение или отмена брони"
        );
    }

    // ===== TELEGRAM API HELPERS (WHITE-LABEL) =====

    /**
     * Get bot token (restaurant's or fallback to platform).
     */
    protected function getBotToken(): string
    {
        if ($this->restaurant && $this->restaurant->hasTelegramBot()) {
            return $this->restaurant->telegram_bot_token;
        }

        return config('services.telegram.bot_token');
    }

    /**
     * Send message via restaurant's bot.
     */
    protected function sendBotMessage(string $chatId, string $text): void
    {
        try {
            Http::post(
                "https://api.telegram.org/bot{$this->getBotToken()}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('TelegramBotController: Failed to send message', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    /**
     * Send message with inline buttons via restaurant's bot.
     */
    protected function sendBotMessageWithButtons(string $chatId, string $text, array $buttons): void
    {
        try {
            Http::post(
                "https://api.telegram.org/bot{$this->getBotToken()}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => [
                        'inline_keyboard' => $buttons,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('TelegramBotController: Failed to send message with buttons', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    /**
     * Edit message text via restaurant's bot.
     */
    protected function editBotMessage(string $chatId, int $messageId, string $text): void
    {
        try {
            Http::post(
                "https://api.telegram.org/bot{$this->getBotToken()}/editMessageText",
                [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]
            );
        } catch (\Throwable $e) {
            // Fallback to new message
            $this->sendBotMessage($chatId, $text);
        }
    }

    /**
     * Answer callback query.
     */
    protected function answerCallback(string $callbackId, ?string $text = null): void
    {
        $params = ['callback_query_id' => $callbackId];

        if ($text) {
            $params['text'] = $text;
        }

        try {
            Http::post(
                "https://api.telegram.org/bot{$this->getBotToken()}/answerCallbackQuery",
                $params
            );
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    /**
     * Mask phone number for display.
     */
    protected function maskPhone(?string $phone): string
    {
        if (!$phone) {
            return '***';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 4) {
            return '***';
        }

        return '+7 ***-**-' . substr($digits, -2);
    }
}
