<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Reservation;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for all reservation notifications.
 *
 * Provides common functionality:
 * - Channel selection based on notifiable preferences
 * - Common reservation data formatting
 * - Consistent styling across email and Telegram
 */
abstract class ReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public array $metadata = [],
    ) {
        $this->queue = 'notifications';
    }

    /**
     * Get notification type identifier.
     */
    abstract public function getType(): string;

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        // If notifiable has preferences, use them
        if (method_exists($notifiable, 'getNotificationChannels')) {
            $channels = $notifiable->getNotificationChannels('reservation');
            if (!empty($channels)) {
                return $this->mapChannels($channels);
            }
        }

        // Default: try all available channels
        return $this->getDefaultChannels($notifiable);
    }

    /**
     * Map preference channel names to Laravel notification channels.
     */
    protected function mapChannels(array $channels): array
    {
        $map = [
            'mail' => 'mail',
            'email' => 'mail',
            'telegram' => TelegramChannel::class,
            'sms' => 'sms', // Would need SmsChannel implementation
            'database' => 'database',
        ];

        return array_filter(array_map(
            fn($channel) => $map[$channel] ?? null,
            $channels
        ));
    }

    /**
     * Get default channels based on what's available.
     */
    protected function getDefaultChannels($notifiable): array
    {
        $channels = [];

        // Check for email
        $email = $this->getNotifiableEmail($notifiable);
        if ($email) {
            $channels[] = 'mail';
        }

        // Check for Telegram
        $telegramChatId = $this->getNotifiableTelegramChatId($notifiable);
        if ($telegramChatId) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    /**
     * Get email address from notifiable.
     */
    protected function getNotifiableEmail($notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            return $notifiable->routeNotificationForMail();
        }

        return $notifiable->email ?? null;
    }

    /**
     * Get Telegram chat ID from notifiable.
     */
    protected function getNotifiableTelegramChatId($notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForTelegram')) {
            return $notifiable->routeNotificationForTelegram();
        }

        return $notifiable->telegram_chat_id ?? null;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->getType(),
            'reservation_id' => $this->reservation->id,
            'guest_name' => $this->reservation->guest_name,
            'date' => $this->reservation->date?->format('Y-m-d'),
            'time_range' => $this->reservation->time_range,
        ];
    }

    // ===== COMMON HELPERS =====

    /**
     * Get restaurant name.
     */
    protected function getRestaurantName(): string
    {
        return $this->reservation->restaurant?->name ?? 'Ресторан';
    }

    /**
     * Get formatted date.
     */
    protected function getFormattedDate(): string
    {
        return $this->reservation->date?->format('d.m.Y') ?? '';
    }

    /**
     * Get time range string.
     */
    protected function getTimeRange(): string
    {
        return $this->reservation->time_range ?? '';
    }

    /**
     * Get table name.
     */
    protected function getTableName(): string
    {
        return $this->reservation->table?->name ?? '';
    }

    /**
     * Check if reservation has deposit.
     */
    protected function hasDeposit(): bool
    {
        return $this->reservation->deposit > 0;
    }

    /**
     * Get formatted deposit amount.
     */
    protected function getDepositAmount(): string
    {
        return number_format($this->reservation->deposit, 0, '.', ' ') . ' ₽';
    }

    /**
     * Build common email fields section.
     */
    protected function addReservationDetailsToMail(MailMessage $message): MailMessage
    {
        $message->line("**Дата:** {$this->getFormattedDate()}");
        $message->line("**Время:** {$this->getTimeRange()}");
        $message->line("**Гостей:** {$this->reservation->guests_count}");

        if ($tableName = $this->getTableName()) {
            $message->line("**Стол:** {$tableName}");
        }

        return $message;
    }

    /**
     * Build common Telegram fields.
     */
    protected function addReservationDetailsToTelegram(TelegramMessage $message): TelegramMessage
    {
        $message->field('Дата', $this->getFormattedDate(), '📅');
        $message->field('Время', $this->getTimeRange(), '🕐');
        $message->field('Гостей', (string) $this->reservation->guests_count, '👥');

        if ($tableName = $this->getTableName()) {
            $message->field('Стол', $tableName, '🪑');
        }

        return $message;
    }
}
