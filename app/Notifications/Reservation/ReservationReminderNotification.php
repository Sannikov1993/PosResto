<?php

declare(strict_types=1);

namespace App\Notifications\Reservation;

use App\Notifications\Channels\TelegramMessage;
use App\Notifications\ReservationNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reminder notification sent before the reservation.
 */
class ReservationReminderNotification extends ReservationNotification
{
    public function getType(): string
    {
        return 'reservation_reminder';
    }

    /**
     * Get hours until reservation.
     */
    protected function getHoursUntil(): int
    {
        return $this->metadata['hours_until'] ?? 2;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $hours = $this->getHoursUntil();
        $hoursText = $this->formatHours($hours);

        $message = (new MailMessage())
            ->subject("⏰ Напоминание: бронирование через {$hoursText} — {$this->getRestaurantName()}")
            ->greeting("Здравствуйте, {$this->reservation->guest_name}!")
            ->line("Напоминаем, что ваше бронирование через {$hoursText}.")
            ->line('');

        $this->addReservationDetailsToMail($message);

        if ($this->hasDeposit() && !$this->reservation->deposit_paid) {
            $message->line('');
            $message->line("⚠️ **Внимание:** Депозит {$this->getDepositAmount()} ещё не оплачен.");
        }

        $message->line('');
        $message->line('Ждём вас!');
        $message->salutation("С уважением,\n{$this->getRestaurantName()}");

        return $message;
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $hours = $this->getHoursUntil();
        $hoursText = $this->formatHours($hours);

        $message = TelegramMessage::create()
            ->greeting("⏰ Напоминание о бронировании")
            ->line('')
            ->line("Здравствуйте, {$this->reservation->guest_name}!")
            ->line("Ваше бронирование через {$hoursText}.")
            ->line('');

        $this->addReservationDetailsToTelegram($message);

        if ($this->hasDeposit() && !$this->reservation->deposit_paid) {
            $message->line('');
            $message->warning("Депозит {$this->getDepositAmount()} не оплачен");
        }

        $message->line('');
        $message->line('Ждём вас! 🍽️');

        return $message;
    }

    /**
     * Format hours with correct Russian declension.
     */
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
}
