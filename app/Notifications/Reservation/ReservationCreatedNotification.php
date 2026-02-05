<?php

declare(strict_types=1);

namespace App\Notifications\Reservation;

use App\Notifications\Channels\TelegramMessage;
use App\Notifications\ReservationNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent when a new reservation is created.
 */
class ReservationCreatedNotification extends ReservationNotification
{
    public function getType(): string
    {
        return 'reservation_created';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject("Бронирование #{$this->reservation->id} создано — {$this->getRestaurantName()}")
            ->greeting("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('Ваше бронирование успешно создано.')
            ->line('');

        $this->addReservationDetailsToMail($message);

        if ($this->hasDeposit()) {
            $message->line('');
            $message->line("**Депозит:** {$this->getDepositAmount()}");

            if (!$this->reservation->deposit_paid) {
                $message->line('*Депозит ожидает оплаты*');
            }
        }

        if ($this->reservation->notes) {
            $message->line('');
            $message->line("**Примечания:** {$this->reservation->notes}");
        }

        $message->line('');
        $message->line('Мы свяжемся с вами для подтверждения бронирования.');
        $message->salutation("С уважением,\n{$this->getRestaurantName()}");

        return $message;
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->greeting("📋 Бронирование #{$this->reservation->id} создано")
            ->line('')
            ->line("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('Ваше бронирование успешно создано.')
            ->line('');

        $this->addReservationDetailsToTelegram($message);

        if ($this->hasDeposit()) {
            $message->line('');
            $depositStatus = $this->reservation->deposit_paid ? '✅ оплачен' : '⏳ ожидает оплаты';
            $message->field('Депозит', "{$this->getDepositAmount()} ({$depositStatus})", '💰');
        }

        if ($this->reservation->notes) {
            $message->line('');
            $message->italic("📝 {$this->reservation->notes}");
        }

        $message->line('');
        $message->italic('Ожидайте подтверждения бронирования');

        return $message;
    }
}
