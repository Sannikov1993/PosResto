<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Models\Reservation;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base class for staff notifications about reservations.
 *
 * Staff notifications typically go to:
 * - Restaurant Telegram group chat
 * - Individual staff members (managers, hostess)
 *
 * They include more operational details than guest notifications.
 */
abstract class StaffReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public array $metadata = [],
    ) {
        $this->queue = 'notifications';
    }

    abstract public function getType(): string;

    /**
     * Get the notification's delivery channels.
     * Staff notifications primarily use Telegram.
     */
    public function via($notifiable): array
    {
        // Staff notifications go to Telegram by default
        return [TelegramChannel::class];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->getType(),
            'reservation_id' => $this->reservation->id,
            'guest_name' => $this->reservation->guest_name,
            'guest_phone' => $this->reservation->guest_phone,
        ];
    }

    // ===== COMMON HELPERS =====

    protected function getFormattedDate(): string
    {
        return $this->reservation->date?->format('d.m.Y') ?? '';
    }

    protected function getTimeRange(): string
    {
        return $this->reservation->time_range ?? '';
    }

    protected function getTableName(): string
    {
        return $this->reservation->table?->name ?? 'Не указан';
    }

    protected function hasDeposit(): bool
    {
        return $this->reservation->deposit > 0;
    }

    protected function getDepositAmount(): string
    {
        return number_format($this->reservation->deposit, 0, '.', ' ') . ' ₽';
    }

    protected function getDepositStatus(): string
    {
        if (!$this->hasDeposit()) {
            return 'без депозита';
        }

        return $this->reservation->deposit_paid ? '✅ оплачен' : '❌ не оплачен';
    }

    protected function getUserName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        return \App\Models\User::find($userId)?->name;
    }

    /**
     * Add common reservation fields to Telegram message.
     */
    protected function addStaffDetails(TelegramMessage $message): TelegramMessage
    {
        $message->emoji('👤', $this->reservation->guest_name);
        $message->emoji('📞', $this->reservation->guest_phone);
        $message->field('Дата', $this->getFormattedDate(), '📅');
        $message->field('Время', $this->getTimeRange(), '🕐');
        $message->field('Гостей', (string) $this->reservation->guests_count, '👥');
        $message->field('Стол', $this->getTableName(), '🪑');

        return $message;
    }
}
