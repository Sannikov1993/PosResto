<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification when reservation is cancelled.
 */
class ReservationCancelledStaffNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_reservation_cancelled';
    }

    protected function isDepositRefunded(): bool
    {
        return $this->metadata['deposit_refunded'] ?? false;
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->error("Бронирование #{$this->reservation->id} отменено")
            ->line('');

        $this->addStaffDetails($message);

        if ($this->reservation->cancellation_reason) {
            $message->line('');
            $message->italic("📝 Причина: {$this->reservation->cancellation_reason}");
        }

        if ($this->hasDeposit()) {
            $message->line('');
            if ($this->isDepositRefunded()) {
                $message->success("Депозит {$this->getDepositAmount()} возвращён");
            } else {
                $message->info("Депозит {$this->getDepositAmount()} не возвращён");
            }
        }

        $cancelledBy = $this->getUserName($this->reservation->cancelled_by);
        if ($cancelledBy) {
            $message->line('');
            $message->italic("Отменил: {$cancelledBy}");
        }

        return $message;
    }
}
