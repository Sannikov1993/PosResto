<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification for upcoming reservation reminder.
 */
class UpcomingReservationNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_reservation_reminder';
    }

    protected function getMinutesUntil(): int
    {
        return $this->metadata['minutes_until'] ?? 120;
    }

    protected function formatTimeUntil(): string
    {
        $minutes = $this->getMinutesUntil();

        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            if ($mins === 0) {
                return $this->formatHours($hours);
            }

            return "{$this->formatHours($hours)} {$mins} мин";
        }

        return "{$minutes} мин";
    }

    protected function formatHours(int $hours): string
    {
        $lastDigit = $hours % 10;
        $lastTwoDigits = $hours % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return "{$hours} часов";
        }

        return match ($lastDigit) {
            1 => "{$hours} час",
            2, 3, 4 => "{$hours} часа",
            default => "{$hours} часов",
        };
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->greeting("⏰ Напоминание о бронировании")
            ->line('')
            ->line("Бронирование #{$this->reservation->id}")
            ->bold("Через {$this->formatTimeUntil()}")
            ->line('');

        $this->addStaffDetails($message);

        if ($this->hasDeposit()) {
            $message->line('');
            $message->field('Депозит', "{$this->getDepositAmount()} ({$this->getDepositStatus()})", '💰');
        }

        if ($this->reservation->notes) {
            $message->line('');
            $message->italic("📝 {$this->reservation->notes}");
        }

        return $message;
    }
}
