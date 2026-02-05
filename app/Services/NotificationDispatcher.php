<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Central dispatcher for all notifications.
 *
 * Handles:
 * - Determining recipients and their preferred channels
 * - Creating NotificationLog entries
 * - Dispatching jobs to appropriate queues
 * - Guest notifications (via restaurant's white-label bot)
 * - Staff notifications (via platform staff bot)
 *
 * Usage:
 *   $dispatcher = app(NotificationDispatcher::class);
 *
 *   // Notify guest about reservation
 *   $dispatcher->notifyGuest($reservation, 'reservation_confirmed', $message);
 *
 *   // Notify staff about new reservation
 *   $dispatcher->notifyStaff($restaurant, 'new_reservation', $message);
 *
 *   // Send to specific channels
 *   $dispatcher->send($recipient, 'reminder', $message, ['telegram', 'email']);
 */
class NotificationDispatcher
{
    /**
     * Notify guest about their reservation.
     *
     * Uses customer's preferred channels if customer exists,
     * otherwise falls back to reservation contact info.
     */
    public function notifyGuest(
        Reservation $reservation,
        string $notificationType,
        string $message,
        ?string $subject = null,
        ?string $htmlEmail = null,
        array $options = [],
    ): array {
        $logs = [];
        $restaurant = $reservation->restaurant;
        $customer = $reservation->customer;

        // Determine channels to use
        $channels = $this->getGuestChannels($reservation, $customer);

        foreach ($channels as $channel) {
            $log = $this->createLog(
                restaurant: $restaurant,
                notifiable: $customer,
                notificationType: $notificationType,
                channel: $channel,
                subject: $subject,
                related: $reservation,
                recipientData: [
                    'phone' => $reservation->guest_phone,
                    'email' => $reservation->guest_email,
                    'name' => $reservation->guest_name,
                ],
            );

            $this->dispatchToChannel($log, $channel, [
                'message' => $message,
                'subject' => $subject,
                'html_email' => $htmlEmail,
                'restaurant_id' => $restaurant->id,
                'chat_id' => $customer?->telegram_chat_id,
                'email' => $reservation->guest_email ?? $customer?->email,
                'use_staff_bot' => false,
                'options' => $options,
            ]);

            $logs[] = $log;
        }

        Log::info('NotificationDispatcher: Guest notified', [
            'reservation_id' => $reservation->id,
            'notification_type' => $notificationType,
            'channels' => $channels,
            'log_ids' => array_map(fn($l) => $l->id, $logs),
        ]);

        return $logs;
    }

    /**
     * Notify staff about an event.
     *
     * Sends to all staff members with appropriate role who have the channel enabled.
     */
    public function notifyStaff(
        Restaurant $restaurant,
        string $notificationType,
        string $message,
        ?string $subject = null,
        ?Model $related = null,
        array $roles = [],
        array $options = [],
    ): array {
        $logs = [];

        // Get staff with telegram enabled for this notification type
        $query = User::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id');

        if (!empty($roles)) {
            $query->whereIn('role', $roles);
        }

        $staffMembers = $query->get();

        foreach ($staffMembers as $user) {
            // Check user's notification preferences
            if (!$this->userWantsNotification($user, $notificationType, 'telegram')) {
                continue;
            }

            $log = $this->createLog(
                restaurant: $restaurant,
                notifiable: $user,
                notificationType: $notificationType,
                channel: NotificationLog::CHANNEL_TELEGRAM,
                subject: $subject,
                related: $related,
            );

            $this->dispatchToChannel($log, NotificationLog::CHANNEL_TELEGRAM, [
                'message' => $message,
                'chat_id' => $user->telegram_chat_id,
                'use_staff_bot' => true,
                'options' => $options,
            ]);

            $logs[] = $log;
        }

        Log::info('NotificationDispatcher: Staff notified', [
            'restaurant_id' => $restaurant->id,
            'notification_type' => $notificationType,
            'staff_count' => count($logs),
        ]);

        return $logs;
    }

    /**
     * Send notification to a specific recipient.
     */
    public function send(
        Customer|User $recipient,
        string $notificationType,
        string $message,
        array $channels = [],
        ?string $subject = null,
        ?string $htmlEmail = null,
        ?Model $related = null,
        array $options = [],
    ): array {
        $logs = [];
        $restaurant = Restaurant::find($recipient->restaurant_id);

        // Auto-detect channels if not specified
        if (empty($channels)) {
            $channels = $this->getRecipientChannels($recipient, $notificationType);
        }

        $isStaff = $recipient instanceof User;

        foreach ($channels as $channel) {
            $log = $this->createLog(
                restaurant: $restaurant,
                notifiable: $recipient,
                notificationType: $notificationType,
                channel: $channel,
                subject: $subject,
                related: $related,
            );

            $this->dispatchToChannel($log, $channel, [
                'message' => $message,
                'subject' => $subject,
                'html_email' => $htmlEmail,
                'restaurant_id' => $restaurant?->id,
                'chat_id' => $recipient->telegram_chat_id,
                'email' => $recipient->email,
                'use_staff_bot' => $isStaff,
                'options' => $options,
            ]);

            $logs[] = $log;
        }

        return $logs;
    }

    /**
     * Send a reminder notification to guest.
     */
    public function sendGuestReminder(
        Reservation $reservation,
        string $message,
        ?string $subject = null,
        ?string $htmlEmail = null,
    ): array {
        return $this->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_REMINDER,
            message: $message,
            subject: $subject,
            htmlEmail: $htmlEmail,
        );
    }

    /**
     * Create a notification log entry.
     */
    protected function createLog(
        ?Restaurant $restaurant,
        ?Model $notifiable,
        string $notificationType,
        string $channel,
        ?string $subject = null,
        ?Model $related = null,
        array $recipientData = [],
    ): NotificationLog {
        return NotificationLog::create([
            'restaurant_id' => $restaurant?->id,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'notifiable_id' => $notifiable?->id,
            'notification_type' => $notificationType,
            'channel' => $channel,
            'subject' => $subject,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id,
            'status' => NotificationLog::STATUS_PENDING,
            'recipient_phone' => $recipientData['phone'] ?? null,
            'recipient_email' => $recipientData['email'] ?? null,
            'recipient_name' => $recipientData['name'] ?? null,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Dispatch notification to appropriate channel job.
     */
    protected function dispatchToChannel(NotificationLog $log, string $channel, array $data): void
    {
        $job = match ($channel) {
            NotificationLog::CHANNEL_TELEGRAM => $this->createTelegramJob($log, $data),
            NotificationLog::CHANNEL_EMAIL => $this->createEmailJob($log, $data),
            default => null,
        };

        if ($job) {
            dispatch($job)->onQueue('notifications');

            $log->update([
                'job_queue' => 'notifications',
            ]);
        }
    }

    /**
     * Create Telegram notification job.
     */
    protected function createTelegramJob(NotificationLog $log, array $data): ?SendTelegramNotificationJob
    {
        $chatId = $data['chat_id'] ?? null;

        if (!$chatId) {
            $log->markFailed('No Telegram chat_id', false);
            return null;
        }

        return new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: $chatId,
            message: $data['message'],
            restaurantId: $data['restaurant_id'] ?? null,
            useStaffBot: $data['use_staff_bot'] ?? false,
            options: $data['options'] ?? [],
        );
    }

    /**
     * Create Email notification job.
     */
    protected function createEmailJob(NotificationLog $log, array $data): ?SendEmailNotificationJob
    {
        $email = $data['email'] ?? null;

        if (!$email) {
            $log->markFailed('No email address', false);
            return null;
        }

        $htmlContent = $data['html_email'] ?? $this->wrapInEmailTemplate($data['message'], $data['subject'] ?? '');

        return new SendEmailNotificationJob(
            notificationLogId: $log->id,
            email: $email,
            subject: $data['subject'] ?? 'Уведомление',
            htmlContent: $htmlContent,
        );
    }

    /**
     * Get channels available for guest notifications.
     */
    protected function getGuestChannels(Reservation $reservation, ?Customer $customer): array
    {
        $channels = [];

        // Telegram (if customer has it linked)
        if ($customer && $customer->hasTelegram()) {
            if ($customer->wantsNotification('reservation', 'telegram')) {
                $channels[] = NotificationLog::CHANNEL_TELEGRAM;
            }
        }

        // Email (always available if present)
        $email = $reservation->guest_email ?? $customer?->email;
        if ($email) {
            if (!$customer || $customer->wantsNotification('reservation', 'email')) {
                $channels[] = NotificationLog::CHANNEL_EMAIL;
            }
        }

        return $channels;
    }

    /**
     * Get channels for a recipient based on their preferences.
     */
    protected function getRecipientChannels(Customer|User $recipient, string $notificationType): array
    {
        $channels = [];
        $prefType = $this->mapNotificationTypeToPreference($notificationType);

        if ($recipient instanceof Customer) {
            if ($recipient->hasTelegram() && $recipient->wantsNotification($prefType, 'telegram')) {
                $channels[] = NotificationLog::CHANNEL_TELEGRAM;
            }
            if ($recipient->email && $recipient->wantsNotification($prefType, 'email')) {
                $channels[] = NotificationLog::CHANNEL_EMAIL;
            }
        } else {
            // User (staff)
            if ($recipient->telegram_chat_id && $this->userWantsNotification($recipient, $prefType, 'telegram')) {
                $channels[] = NotificationLog::CHANNEL_TELEGRAM;
            }
        }

        return $channels;
    }

    /**
     * Check if user wants this type of notification on this channel.
     */
    protected function userWantsNotification(User $user, string $type, string $channel): bool
    {
        $settings = $user->notification_settings ?? [];
        $prefType = $this->mapNotificationTypeToUserPreference($type);

        return $settings[$prefType][$channel] ?? true; // Default to enabled
    }

    /**
     * Map notification type to customer preference key.
     */
    protected function mapNotificationTypeToPreference(string $type): string
    {
        return match (true) {
            str_contains($type, 'reservation') => 'reservation',
            str_contains($type, 'reminder') => 'reminder',
            str_contains($type, 'deposit') => 'reservation',
            default => 'system',
        };
    }

    /**
     * Map notification type to user preference key.
     */
    protected function mapNotificationTypeToUserPreference(string $type): string
    {
        return match (true) {
            str_contains($type, 'reservation') || str_contains($type, 'deposit') => 'system',
            str_contains($type, 'shift') => 'shift_reminder',
            str_contains($type, 'schedule') => 'schedule_change',
            str_contains($type, 'salary') => 'salary_paid',
            str_contains($type, 'bonus') => 'bonus_received',
            str_contains($type, 'penalty') => 'penalty_received',
            default => 'system',
        };
    }

    /**
     * Wrap plain text message in simple email template.
     */
    protected function wrapInEmailTemplate(string $message, string $title): string
    {
        $escapedMessage = nl2br(e($message));
        $escapedTitle = e($title);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; border-radius: 8px; padding: 24px;">
        <h2 style="margin: 0 0 16px; color: #1a1a1a;">{$escapedTitle}</h2>
        <div style="color: #4a4a4a;">
            {$escapedMessage}
        </div>
    </div>
    <p style="font-size: 12px; color: #888; margin-top: 24px; text-align: center;">
        Это автоматическое уведомление. Пожалуйста, не отвечайте на это письмо.
    </p>
</body>
</html>
HTML;
    }
}
