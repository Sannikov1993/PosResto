<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\AuditService;
use App\Services\StaffNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramStaffBotController extends Controller
{
    protected StaffNotificationService $notificationService;
    protected ?string $botToken;

    public function __construct(StaffNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->botToken = config('services.telegram.staff_bot_token');
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate webhook secret (обязательная проверка)
        $expectedSecret = config('services.telegram.staff_bot_webhook_secret');
        if (!$expectedSecret) {
            Log::error('Telegram staff bot webhook: secret not configured in services.telegram.staff_bot_webhook_secret');
            return response()->json(['ok' => false, 'error' => 'misconfigured'], 500);
        }

        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (!$secretHeader || !hash_equals($expectedSecret, $secretHeader)) {
            Log::warning('Telegram staff bot webhook: invalid secret token');
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        AuditService::logWebhook('telegram_staff_bot', [
            'update_id' => $update['update_id'] ?? null,
        ]);

        Log::info('Telegram staff bot webhook', ['update' => $update]);

        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }
        } catch (\Exception $e) {
            Log::error('Telegram staff bot error', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle incoming message
     */
    protected function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        if (empty($chatId)) {
            Log::warning('Telegram webhook: missing or empty chat_id');
            return;
        }
        $chatId = (string) $chatId;
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;

        // Handle /start command with token
        if (str_starts_with($text, '/start ')) {
            $token = trim(substr($text, 7));
            $this->handleStartCommand($chatId, $token, $username);
            return;
        }

        // Handle other commands
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->sendMessage($chatId,
                "Вы не подключены к системе MenuLab.\n\n" .
                "Для подключения перейдите в настройки профиля в приложении и получите ссылку для подключения Telegram."
            );
            return;
        }

        match($text) {
            '/start' => $this->sendWelcome($chatId, $user),
            '/status' => $this->sendShiftStatus($chatId, $user),
            '/help' => $this->sendHelp($chatId),
            '/stop' => $this->handleStop($chatId, $user),
            default => $this->sendHelp($chatId),
        };
    }

    /**
     * Handle /start command with connection token
     */
    protected function handleStartCommand(string $chatId, string $token, ?string $username): void
    {
        $user = $this->notificationService->processTelegramCallback($token, $chatId, $username);

        if (!$user) {
            $this->sendMessage($chatId,
                "Ссылка недействительна или устарела.\n\n" .
                "Получите новую ссылку в настройках профиля MenuLab."
            );
        }
        // Success message is sent by processTelegramCallback
    }

    /**
     * Send welcome message
     */
    protected function sendWelcome(string $chatId, User $user): void
    {
        $this->sendMessage($chatId,
            "Привет, {$user->name}!\n\n" .
            "Вы подключены к уведомлениям MenuLab.\n\n" .
            "Доступные команды:\n" .
            "/status - статус смены\n" .
            "/help - справка\n" .
            "/stop - отключить уведомления"
        );
    }

    /**
     * Send shift status
     */
    protected function sendShiftStatus(string $chatId, User $user): void
    {
        $session = WorkSession::getActiveSession($user->id, $user->restaurant_id);

        if ($session) {
            $duration = $session->duration_formatted ?? $this->formatDuration($session->clock_in);
            $this->sendMessage($chatId,
                "🟢 *Смена активна*\n\n" .
                "Начало: " . $session->clock_in->format('H:i') . "\n" .
                "Время на смене: {$duration}"
            );
        } else {
            $this->sendMessage($chatId,
                "⚪️ *Смена не начата*\n\n" .
                "Вы сейчас не на смене. Отметьтесь в приложении, когда начнёте работу."
            );
        }
    }

    /**
     * Send help message
     */
    protected function sendHelp(string $chatId): void
    {
        $this->sendMessage($chatId,
            "📱 *Бот уведомлений MenuLab*\n\n" .
            "Команды:\n" .
            "/status - текущий статус смены\n" .
            "/help - эта справка\n" .
            "/stop - отключить уведомления\n\n" .
            "Вы будете получать уведомления о:\n" .
            "• Напоминания о сменах\n" .
            "• Изменения в расписании\n" .
            "• Зарплата и премии\n" .
            "• Важные сообщения от руководства"
        );
    }

    /**
     * Handle stop command - disconnect Telegram
     */
    protected function handleStop(string $chatId, User $user): void
    {
        $user->disconnectTelegram();

        $this->sendMessage($chatId,
            "Уведомления отключены.\n\n" .
            "Чтобы снова подключиться, получите ссылку в настройках профиля MenuLab."
        );
    }

    /**
     * Handle callback query (button press)
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        if (empty($chatId)) {
            Log::warning('Telegram callback: missing or empty chat_id');
            return;
        }
        $chatId = (string) $chatId;
        $data = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? null;

        // Answer callback to remove loading state
        if ($callbackQueryId) {
            $this->answerCallbackQuery($callbackQueryId);
        }

        $user = $this->getUserByChatId($chatId);
        if (!$user) {
            return;
        }

        // Handle different callback data
        // Example: "confirm_shift_123" or "view_schedule"
        // Add more handlers as needed
    }

    /**
     * Get user by Telegram chat ID
     */
    protected function getUserByChatId(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->first();
    }

    /**
     * Send message to Telegram chat
     */
    protected function sendMessage(string $chatId, string $text, ?array $keyboard = null): bool
    {
        if (!$this->botToken) {
            return false;
        }

        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($keyboard) {
                $params['reply_markup'] = json_encode($keyboard);
            }

            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $params);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram send message error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Answer callback query
     */
    protected function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        if (!$this->botToken) {
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram answer callback error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format duration from clock_in time
     */
    protected function formatDuration($clockIn): string
    {
        $diffMinutes = now()->diffInMinutes($clockIn);
        $hours = floor($diffMinutes / 60);
        $minutes = $diffMinutes % 60;

        if ($hours > 0) {
            return "{$hours}ч {$minutes}м";
        }
        return "{$minutes}м";
    }

    /**
     * Set webhook URL (for initial setup)
     */
    public function setWebhook(Request $request): JsonResponse
    {
        if (!$this->botToken) {
            return response()->json(['success' => false, 'message' => 'Bot token not configured']);
        }

        $webhookUrl = $request->url ?? url('/api/telegram/staff-bot/webhook');

        try {
            $params = ['url' => $webhookUrl];
            $secret = config('services.telegram.staff_bot_webhook_secret');
            if ($secret) {
                $params['secret_token'] = $secret;
            }

            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/setWebhook", $params);

            $result = $response->json();

            return response()->json([
                'success' => $result['ok'] ?? false,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Ошибка установки webhook',
            ]);
        }
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): JsonResponse
    {
        if (!$this->botToken) {
            return response()->json(['success' => false, 'message' => 'Bot token not configured']);
        }

        try {
            $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getWebhookInfo");
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => config('app.debug') ? $e->getMessage() : 'Ошибка получения информации']);
        }
    }
}
