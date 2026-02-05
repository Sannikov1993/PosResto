<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job for sending Telegram notifications with full logging and retry support.
 *
 * Supports both:
 * - Restaurant's white-label bot (for guests)
 * - Platform staff bot (for employees)
 */
class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1; // We handle retries via NotificationLog

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    public function __construct(
        public int $notificationLogId,
        public string $chatId,
        public string $message,
        public ?int $restaurantId = null,
        public bool $useStaffBot = false,
        public array $options = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (!$log) {
            Log::warning('SendTelegramNotificationJob: NotificationLog not found', [
                'notification_log_id' => $this->notificationLogId,
            ]);
            return;
        }

        // Skip if already delivered
        if ($log->status === NotificationLog::STATUS_DELIVERED) {
            return;
        }

        try {
            $botToken = $this->getBotToken();

            if (!$botToken) {
                $log->markFailed('Bot token not configured');
                return;
            }

            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                array_merge([
                    'chat_id' => $this->chatId,
                    'text' => $this->message,
                    'parse_mode' => 'HTML',
                ], $this->options)
            );

            if ($response->successful() && $response->json('ok')) {
                $messageId = $response->json('result.message_id');

                $log->markDelivered([
                    'telegram_message_id' => $messageId,
                    'sent_at' => now()->toIso8601String(),
                ]);

                Log::info('SendTelegramNotificationJob: Message sent', [
                    'notification_log_id' => $this->notificationLogId,
                    'chat_id' => $this->chatId,
                    'message_id' => $messageId,
                ]);
            } else {
                $errorDescription = $response->json('description') ?? 'Unknown error';
                $errorCode = $response->json('error_code');

                // Don't retry for certain errors
                $permanentErrors = [
                    400, // Bad Request (invalid chat_id, etc.)
                    403, // Forbidden (bot blocked by user)
                    404, // Not Found
                ];

                $shouldRetry = !in_array($errorCode, $permanentErrors);

                $log->markFailed("Telegram API error: {$errorDescription}", $shouldRetry);

                Log::warning('SendTelegramNotificationJob: API error', [
                    'notification_log_id' => $this->notificationLogId,
                    'error_code' => $errorCode,
                    'description' => $errorDescription,
                    'will_retry' => $shouldRetry,
                ]);
            }
        } catch (\Throwable $e) {
            $log->markFailed("Exception: {$e->getMessage()}", true);

            Log::error('SendTelegramNotificationJob: Exception', [
                'notification_log_id' => $this->notificationLogId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the appropriate bot token.
     */
    protected function getBotToken(): ?string
    {
        // Staff bot
        if ($this->useStaffBot) {
            return config('services.telegram.staff_bot_token');
        }

        // Restaurant's white-label bot
        if ($this->restaurantId) {
            $restaurant = Restaurant::find($this->restaurantId);

            if ($restaurant && $restaurant->hasTelegramBot()) {
                return $restaurant->telegram_bot_token;
            }
        }

        // Fallback to platform guest bot
        return config('services.telegram.bot_token');
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notification',
            'telegram',
            "log:{$this->notificationLogId}",
        ];
    }
}
