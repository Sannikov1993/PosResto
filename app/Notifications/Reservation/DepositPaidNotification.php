<?php

declare(strict_types=1);

namespace App\Notifications\Reservation;

use App\Notifications\Channels\TelegramMessage;
use App\Notifications\ReservationNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent when deposit is paid for a reservation.
 */
class DepositPaidNotification extends ReservationNotification
{
    public function getType(): string
    {
        return 'deposit_paid';
    }

    /**
     * Get payment method display name.
     */
    protected function getPaymentMethodDisplay(): string
    {
        return match ($this->reservation->deposit_payment_method) {
            'cash' => 'наличными',
            'card' => 'картой',
            'online' => 'онлайн',
            default => $this->reservation->deposit_payment_method ?? 'не указан',
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject("💰 Депозит оплачен — Бронирование #{$this->reservation->id}")
            ->greeting("Здравствуйте, {$this->reservation->guest_name}!")
            ->line("Депозит по вашему бронированию успешно оплачен.")
            ->line('')
            ->line("**Сумма:** {$this->getDepositAmount()}")
            ->line("**Способ оплаты:** {$this->getPaymentMethodDisplay()}")
            ->line('');

        $message->line('**Детали бронирования:**');
        $this->addReservationDetailsToMail($message);

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
            ->success("Депозит оплачен!")
            ->line('')
            ->line("Здравствуйте, {$this->reservation->guest_name}!")
            ->line("Депозит по бронированию #{$this->reservation->id} оплачен.")
            ->line('');

        $message->field('Сумма', $this->getDepositAmount(), '💰');
        $message->field('Способ', $this->getPaymentMethodDisplay(), '💳');

        $message->line('');
        $message->separator();
        $message->line('');

        $this->addReservationDetailsToTelegram($message);

        $message->line('');
        $message->line('Ждём вас! 🍽️');

        return $message;
    }
}
