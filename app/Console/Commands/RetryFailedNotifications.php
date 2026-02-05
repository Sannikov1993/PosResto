<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry failed notifications with exponential backoff.
 *
 * Runs every 5 minutes via scheduler.
 * Respects max_attempts and next_retry_at for each notification.
 */
class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry
        {--limit=100 : Maximum notifications to process}
        {--dry-run : Show what would be retried without actually retrying}';

    protected $description = 'Retry failed notifications that are due for retry';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $notifications = NotificationLog::dueForRetry()
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('No notifications due for retry.');
            return self::SUCCESS;
        }

        $this->info("Found {$notifications->count()} notifications to retry.");

        if ($dryRun) {
            $this->table(
                ['ID', 'Type', 'Channel', 'Attempts', 'Last Error', 'Next Retry'],
                $notifications->map(fn($n) => [
                    $n->id,
                    $n->notification_type,
                    $n->channel,
                    "{$n->attempts}/{$n->max_attempts}",
                    \Illuminate\Support\Str::limit($n->error_message, 30),
                    $n->next_retry_at->diffForHumans(),
                ])
            );
            return self::SUCCESS;
        }

        $retried = 0;
        $skipped = 0;

        foreach ($notifications as $notification) {
            try {
                $job = $this->createRetryJob($notification);

                if ($job) {
                    // Reset status for retry
                    $notification->resetForRetry();

                    dispatch($job)->onQueue('notifications');

                    $retried++;

                    $this->line("  Retrying #{$notification->id} ({$notification->channel})");

                    Log::info('RetryFailedNotifications: Retrying notification', [
                        'notification_log_id' => $notification->id,
                        'attempt' => $notification->attempts + 1,
                        'channel' => $notification->channel,
                    ]);
                } else {
                    $skipped++;
                    $this->warn("  Skipping #{$notification->id} - cannot create job");
                }
            } catch (\Throwable $e) {
                $skipped++;
                $this->error("  Error retrying #{$notification->id}: {$e->getMessage()}");

                Log::error('RetryFailedNotifications: Error creating retry job', [
                    'notification_log_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Retried: {$retried}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Create appropriate job for retry.
     */
    protected function createRetryJob(NotificationLog $notification): ?object
    {
        $channelData = $notification->channel_data ?? [];

        return match ($notification->channel) {
            NotificationLog::CHANNEL_TELEGRAM => $this->createTelegramRetryJob($notification, $channelData),
            NotificationLog::CHANNEL_EMAIL => $this->createEmailRetryJob($notification, $channelData),
            default => null,
        };
    }

    /**
     * Create Telegram retry job from stored data.
     */
    protected function createTelegramRetryJob(NotificationLog $notification, array $channelData): ?SendTelegramNotificationJob
    {
        // Get chat_id from notifiable or channel_data
        $chatId = $channelData['chat_id'] ?? null;

        if (!$chatId && $notification->notifiable) {
            $chatId = $notification->notifiable->telegram_chat_id ?? null;
        }

        if (!$chatId) {
            return null;
        }

        // Get message from channel_data or generate generic
        $message = $channelData['message'] ?? $notification->subject ?? 'Уведомление';

        // Determine if staff bot
        $useStaffBot = $notification->notifiable_type === 'App\\Models\\User';

        return new SendTelegramNotificationJob(
            notificationLogId: $notification->id,
            chatId: $chatId,
            message: $message,
            restaurantId: $notification->restaurant_id,
            useStaffBot: $useStaffBot,
        );
    }

    /**
     * Create Email retry job from stored data.
     */
    protected function createEmailRetryJob(NotificationLog $notification, array $channelData): ?SendEmailNotificationJob
    {
        $email = $notification->recipient_email;

        if (!$email && $notification->notifiable) {
            $email = $notification->notifiable->email ?? null;
        }

        if (!$email) {
            return null;
        }

        $subject = $notification->subject ?? 'Уведомление';
        $htmlContent = $channelData['html_content'] ?? "<p>{$subject}</p>";

        return new SendEmailNotificationJob(
            notificationLogId: $notification->id,
            email: $email,
            subject: $subject,
            htmlContent: $htmlContent,
        );
    }
}
