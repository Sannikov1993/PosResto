<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification when guest doesn't show up.
 */
class GuestNoShowNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_guest_no_show';
    }

    protected function isDepositForfeited(): bool
    {
        return $this->metadata['deposit_forfeited'] ?? false;
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->warning("Гость не пришёл (No-Show)")
            ->line('')
            ->line("Бронирование #{$this->reservation->id}");

        $this->addStaffDetails($message);

        if ($this->hasDeposit()) {
            $message->line('');
            if ($this->isDepositForfeited()) {
                $message->line("💰 Депозит {$this->getDepositAmount()} удержан");
            } elseif ($this->reservation->deposit_paid) {
                $message->line("💰 Депозит {$this->getDepositAmount()} (оплачен)");
            }
        }

        return $message;
    }
}
