<?php

declare(strict_types=1);

namespace App\Notifications\Staff;

use App\Notifications\Channels\TelegramMessage;

/**
 * Staff notification when guest is seated.
 */
class GuestSeatedNotification extends StaffReservationNotification
{
    public function getType(): string
    {
        return 'staff_guest_seated';
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->greeting("🪑 Гость посажен")
            ->line('')
            ->line("Бронирование #{$this->reservation->id}");

        $message->emoji('👤', $this->reservation->guest_name);
        $message->field('Гостей', (string) $this->reservation->guests_count, '👥');
        $message->field('Стол', $this->getTableName(), '🪑');

        $seatedBy = $this->getUserName($this->reservation->seated_by);
        if ($seatedBy) {
            $message->line('');
            $message->italic("Посадил: {$seatedBy}");
        }

        return $message;
    }
}
