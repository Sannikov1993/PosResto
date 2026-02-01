<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Mail\StaffNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Сервис уведомлений для сотрудников (Email, Telegram, Push)
 */
class StaffNotificationService
{
    protected ?string $telegramBotToken;
    protected ?string $telegramBotUsername;

    public function __construct()
    {
        $this->telegramBotToken = config('services.telegram.staff_bot_token');
        $this->telegramBotUsername = config('services.telegram.staff_bot_username');
    }

    /**
     * Send notification to user
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $channels = null
    ): Notification {
        // Determine channels to use
        $channels = $channels ?? $user->getNotificationChannels($type);

        // Create notification record
        $notification = Notification::create([
            'user_id' => $user->id,
            'restaurant_id' => $user->restaurant_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'channels' => $channels,
        ]);

        // Send through each channel
        $deliveryStatus = [];

        foreach ($channels as $channel) {
            try {
                $success = match($channel) {
                    'email' => $this->sendEmail($user, $notification),
                    'telegram' => $this->sendTelegram($user, $notification),
                    'push' => $this->sendPush($user, $notification),
                    'in_app' => true, // Always succeeds, just stored in DB
                    default => false,
                };

                $deliveryStatus[$channel] = [
                    'status' => $success ? 'sent' : 'failed',
                    'at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error("Staff notification failed for channel {$channel}", [
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);

                $deliveryStatus[$channel] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'at' => now()->toIso8601String(),
                ];
            }
        }

        // Update notification with delivery status
        $notification->update([
            'sent_at' => now(),
            'delivery_status' => $deliveryStatus,
        ]);

        return $notification;
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMany(
        array $users,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): array {
        $notifications = [];

        foreach ($users as $user) {
            if ($user instanceof User) {
                $notifications[] = $this->send($user, $type, $title, $message, $data);
            }
        }

        return $notifications;
    }

    /**
     * Send notification to all staff of a restaurant
     */
    public function sendToRestaurantStaff(
        int $restaurantId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $roles = null
    ): array {
        $query = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true);

        if ($roles) {
            $query->whereIn('role', $roles);
        }

        $users = $query->get();

        return $this->sendToMany($users->all(), $type, $title, $message, $data);
    }

    // ==================== CHANNEL METHODS ====================

    /**
     * Send email notification
     */
    protected function sendEmail(User $user, Notification $notification): bool
    {
        if (empty($user->email)) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new StaffNotificationMail($notification));
            return true;
        } catch (\Exception $e) {
            Log::error("Staff email notification failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send Telegram notification
     */
    protected function sendTelegram(User $user, Notification $notification): bool
    {
        if (empty($user->telegram_chat_id) || empty($this->telegramBotToken)) {
            return false;
        }

        try {
            $icon = Notification::getTypeIcon($notification->type);
            $text = "{$icon} *{$notification->title}*\n\n{$notification->message}";

            $response = Http::post("https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage", [
                'chat_id' => $user->telegram_chat_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);

            if (!$response->successful()) {
                throw new \Exception("Telegram API error: " . $response->body());
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Staff Telegram notification failed", [
                'user_id' => $user->id,
                'chat_id' => $user->telegram_chat_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send push notification via WebPush
     */
    protected function sendPush(User $user, Notification $notification): bool
    {
        try {
            $webPush = app(WebPushService::class);

            if (!$webPush->isConfigured()) {
                return false;
            }

            $payload = [
                'title' => $notification->title,
                'body' => $notification->message,
                'icon' => '/images/logo/menulab_icon_192.png',
                'badge' => '/images/logo/menulab_icon_72.png',
                'tag' => "{$notification->type}-{$notification->id}",
                'data' => [
                    'type' => $notification->type,
                    'notification_id' => $notification->id,
                    'url' => $this->getNotificationUrl($notification),
                    ...$notification->data ?? [],
                ],
            ];

            $sent = $webPush->sendToUser($user->id, $payload);

            return $sent > 0;
        } catch (\Exception $e) {
            Log::error('Staff push notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get URL for notification based on type
     */
    protected function getNotificationUrl(Notification $notification): string
    {
        return match($notification->type) {
            'shift_reminder', 'schedule_published', 'schedule_change' => '/cabinet#schedule',
            'salary_paid', 'bonus', 'penalty', 'bonus_received', 'penalty_received' => '/cabinet#salary',
            default => '/cabinet',
        };
    }

    // ==================== TELEGRAM BOT METHODS ====================

    /**
     * Get Telegram bot link for user to connect
     */
    public function getTelegramConnectLink(User $user): ?string
    {
        if (empty($this->telegramBotUsername)) {
            return null;
        }

        // Create a unique token for this user
        $token = base64_encode(json_encode([
            'user_id' => $user->id,
            'restaurant_id' => $user->restaurant_id,
            'expires' => now()->addHours(24)->timestamp,
        ]));

        return "https://t.me/{$this->telegramBotUsername}?start={$token}";
    }

    /**
     * Process Telegram bot callback (when user clicks /start with token)
     */
    public function processTelegramCallback(string $token, string $chatId, ?string $username = null): ?User
    {
        try {
            $data = json_decode(base64_decode($token), true);

            if (!$data || !isset($data['user_id']) || !isset($data['expires'])) {
                return null;
            }

            if ($data['expires'] < now()->timestamp) {
                return null;
            }

            // Проверяем наличие restaurant_id в токене для безопасности
            if (!isset($data['restaurant_id'])) {
                Log::warning("Telegram callback: missing restaurant_id in token", ['user_id' => $data['user_id']]);
                return null;
            }

            // Ищем пользователя с проверкой restaurant_id
            $user = User::where('id', $data['user_id'])
                ->where('restaurant_id', $data['restaurant_id'])
                ->first();

            if (!$user) {
                return null;
            }

            $user->connectTelegram($chatId, $username);

            $this->sendTelegramDirect($chatId,
                "Telegram успешно подключён!\n\n" .
                "Вы будете получать уведомления о:\n" .
                "• Сменах и расписании\n" .
                "• Зарплате и премиях\n" .
                "• Важных событиях\n\n" .
                "Команды:\n" .
                "/status - статус смены\n" .
                "/stop - отключить уведомления"
            );

            return $user;
        } catch (\Exception $e) {
            Log::error("Telegram callback processing failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send direct message to Telegram chat
     */
    public function sendTelegramDirect(string $chatId, string $message): bool
    {
        if (empty($this->telegramBotToken)) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Direct Telegram message failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ==================== PRESET NOTIFICATIONS ====================

    /**
     * Notify about shift reminder
     */
    public function notifyShiftReminder(User $user, string $shiftTime): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SHIFT_REMINDER,
            'Напоминание о смене',
            "Ваша смена начинается в {$shiftTime}. Не забудьте отметиться!"
        );
    }

    /**
     * Notify about schedule change
     */
    public function notifyScheduleChange(User $user, string $details): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SCHEDULE_CHANGE,
            'Изменение расписания',
            $details
        );
    }

    /**
     * Notify about schedule published
     */
    public function notifySchedulePublished(User $user, string $period): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SCHEDULE_PUBLISHED,
            'Расписание опубликовано',
            "Опубликовано расписание на {$period}. Проверьте ваши смены."
        );
    }

    /**
     * Notify about salary payment
     */
    public function notifySalaryPaid(User $user, float $amount, string $period): Notification
    {
        $formattedAmount = number_format($amount, 0, ',', ' ');

        return $this->send(
            $user,
            Notification::TYPE_SALARY_PAID,
            'Зарплата выплачена',
            "Вам выплачена зарплата за {$period} в размере {$formattedAmount} ₽",
            ['amount' => $amount, 'period' => $period]
        );
    }

    /**
     * Notify about bonus
     */
    public function notifyBonusReceived(User $user, float $amount, string $reason): Notification
    {
        $formattedAmount = number_format($amount, 0, ',', ' ');

        return $this->send(
            $user,
            Notification::TYPE_BONUS_RECEIVED,
            'Получена премия',
            "Вам начислена премия {$formattedAmount} ₽.\nПричина: {$reason}",
            ['amount' => $amount, 'reason' => $reason]
        );
    }

    /**
     * Notify about penalty
     */
    public function notifyPenaltyReceived(User $user, float $amount, string $reason): Notification
    {
        $formattedAmount = number_format($amount, 0, ',', ' ');

        return $this->send(
            $user,
            Notification::TYPE_PENALTY_RECEIVED,
            'Получен штраф',
            "Вам начислен штраф {$formattedAmount} ₽.\nПричина: {$reason}",
            ['amount' => $amount, 'reason' => $reason]
        );
    }

    /**
     * Notify about shift started
     */
    public function notifyShiftStarted(User $user): Notification
    {
        return $this->send(
            $user,
            Notification::TYPE_SHIFT_STARTED,
            'Смена начата',
            "Вы успешно отметились на смену в " . now()->format('H:i')
        );
    }

    /**
     * Notify about shift ended
     */
    public function notifyShiftEnded(User $user, float $hoursWorked): Notification
    {
        $hours = number_format($hoursWorked, 1);

        return $this->send(
            $user,
            Notification::TYPE_SHIFT_ENDED,
            'Смена завершена',
            "Смена завершена. Отработано: {$hours} ч."
        );
    }

    /**
     * Send custom notification
     */
    public function sendCustom(User $user, string $title, string $message, array $data = []): Notification
    {
        return $this->send($user, Notification::TYPE_CUSTOM, $title, $message, $data);
    }

    /**
     * Send system notification
     */
    public function sendSystem(User $user, string $title, string $message): Notification
    {
        return $this->send($user, Notification::TYPE_SYSTEM, $title, $message);
    }
}
