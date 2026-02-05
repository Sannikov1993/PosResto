<?php

declare(strict_types=1);

namespace App\Notifications\Reservation;

use App\Notifications\Channels\TelegramMessage;
use App\Notifications\ReservationNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent when a reservation is cancelled.
 */
class ReservationCancelledNotification extends ReservationNotification
{
    public function getType(): string
    {
        return 'reservation_cancelled';
    }

    /**
     * Check if deposit was refunded.
     */
    protected function isDepositRefunded(): bool
    {
        return $this->metadata['deposit_refunded'] ?? false;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject("❌ Бронирование #{$this->reservation->id} отменено — {$this->getRestaurantName()}")
            ->greeting("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('К сожалению, ваше бронирование было отменено.')
            ->line('');

        $this->addReservationDetailsToMail($message);

        if ($this->reservation->cancellation_reason) {
            $message->line('');
            $message->line("**Причина отмены:** {$this->reservation->cancellation_reason}");
        }

        if ($this->hasDeposit()) {
            $message->line('');
            if ($this->isDepositRefunded()) {
                $message->line("✅ Депозит {$this->getDepositAmount()} будет возвращён.");
            } else {
                $message->line("ℹ️ Депозит {$this->getDepositAmount()} не подлежит возврату.");
            }
        }

        $message->line('');
        $message->line('Будем рады видеть вас снова!');
        $message->salutation("С уважением,\n{$this->getRestaurantName()}");

        return $message;
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->error("Бронирование #{$this->reservation->id} отменено")
            ->line('')
            ->line("Здравствуйте, {$this->reservation->guest_name}!")
            ->line('К сожалению, ваше бронирование было отменено.')
            ->line('');

        $this->addReservationDetailsToTelegram($message);

        if ($this->reservation->cancellation_reason) {
            $message->line('');
            $message->italic("Причина: {$this->reservation->cancellation_reason}");
        }

        if ($this->hasDeposit()) {
            $message->line('');
            if ($this->isDepositRefunded()) {
                $message->success("Депозит {$this->getDepositAmount()} будет возвращён");
            } else {
                $message->info("Депозит {$this->getDepositAmount()} не возвращается");
            }
        }

        $message->line('');
        $message->line('Будем рады видеть вас снова! 🙏');

        return $message;
    }
}
