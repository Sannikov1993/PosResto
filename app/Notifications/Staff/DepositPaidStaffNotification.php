<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification when deposit is paid.
 */
class DepositPaidStaffNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_deposit_paid';
    }

    protected function getPaymentMethodDisplay(): string
    {
        return match ($this->reservation->deposit_payment_method) {
            'cash' => 'Наличные',
            'card' => 'Карта',
            'online' => 'Онлайн',
            default => $this->reservation->deposit_payment_method ?? 'Не указан',
        };
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->success("Депозит оплачен")
            ->line('')
            ->line("Бронирование #{$this->reservation->id}");

        $message->emoji('👤', $this->reservation->guest_name);
        $message->field('Дата', $this->getFormattedDate(), '📅');
        $message->field('Время', $this->getTimeRange(), '🕐');
        $message->line('');
        $message->field('Сумма', $this->getDepositAmount(), '💵');
        $message->field('Способ', $this->getPaymentMethodDisplay(), '💳');

        $paidBy = $this->getUserName($this->reservation->deposit_paid_by);
        if ($paidBy) {
            $message->line('');
            $message->italic("Принял: {$paidBy}");
        }

        return $message;
    }
}
