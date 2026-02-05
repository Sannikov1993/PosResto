<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Reservation $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function envelope(): Envelope
    {
        $restaurantName = $this->reservation->table?->restaurant?->name ?? 'Ресторан';

        return new Envelope(
            subject: "Напоминание о бронировании - {$restaurantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation.reminder',
            with: [
                'reservation' => $this->reservation,
                'restaurant' => $this->reservation->table?->restaurant,
                'guestName' => $this->getGuestName(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function getGuestName(): string
    {
        return $this->reservation->guest_name
            ?? $this->reservation->customer?->name
            ?? 'Гость';
    }
}
