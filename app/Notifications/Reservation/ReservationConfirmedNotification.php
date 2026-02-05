<?php

declare(strict_types=1);

namespace App\Notifications\Reservation;

use App\Notifications\Channels\TelegramMessage;
use App\Notifications\ReservationNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent when a reservation is confirmed.
 */
class ReservationConfirmedNotification extends ReservationNotification
{
    public function getType(): string
    {
        return 'reservation_confirmed';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject("✅ Бронирование #{$this->reservation->id} подтверждено — {$this->getRestaurantName()}")
            ->greeting("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('Ваше бронирование подтверждено!')
            ->line('');

        $this->addReservationDetailsToMail($message);

        if ($this->hasDeposit() && !$this->reservation->deposit_paid) {
            $message->line('');
            $message->line("⚠️ **Внимание:** Депозит {$this->getDepositAmount()} ожидает оплаты.");
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
        $message = TelegramMessage::create()
            ->success("Бронирование #{$this->reservation->id} подтверждено!")
            ->line('')
            ->line("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('');

        $this->addReservationDetailsToTelegram($message);

        if ($this->hasDeposit() && !$this->reservation->deposit_paid) {
            $message->line('');
            $message->warning("Депозит {$this->getDepositAmount()} ожидает оплаты");
        }

        $message->line('');
        $message->line('Ждём вас! 🍽️');

        return $message;
    }
}
