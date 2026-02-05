<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification for new reservation created.
 */
class NewReservationNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_reservation_created';
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->greeting("📋 Новое бронирование #{$this->reservation->id}")
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

        if ($this->reservation->special_requests) {
            $message->line('');
            $message->italic("⭐ {$this->reservation->special_requests}");
        }

        $message->line('');
        $message->italic('Статус: ожидает подтверждения');

        return $message;
    }
}
