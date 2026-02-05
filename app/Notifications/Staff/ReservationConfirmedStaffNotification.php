<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification when reservation is confirmed.
 */
class ReservationConfirmedStaffNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_reservation_confirmed';
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->success("Бронирование #{$this->reservation->id} подтверждено")
            ->line('');

        $this->addStaffDetails($message);

        $confirmedBy = $this->getUserName($this->reservation->confirmed_by);
        if ($confirmedBy) {
            $message->line('');
            $message->italic("Подтвердил: {$confirmedBy}");
        }

        return $message;
    }
}
