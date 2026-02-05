<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Reservation $reservation;
    public bool $depositRefunded;

    public function __construct(Reservation $reservation, bool $depositRefunded = false)
    {
        $this->reservation = $reservation;
        $this->depositRefunded = $depositRefunded;
    }

    public function envelope(): Envelope
    {
        $restaurantName = $this->reservation->table?->restaurant?->name ?? 'Ресторан';

        return new Envelope(
            subject: "Бронирование отменено - {$restaurantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation.cancelled',
            with: [
                'reservation' => $this->reservation,
                'restaurant' => $this->reservation->table?->restaurant,
                'guestName' => $this->getGuestName(),
                'depositRefunded' => $this->depositRefunded,
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
