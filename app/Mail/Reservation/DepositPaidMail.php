<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositPaidMail extends Mailable implements ShouldQueue
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
        $amount = number_format($this->reservation->deposit, 0, ',', ' ');

        return new Envelope(
            subject: "Депозит {$amount} ₽ оплачен - {$restaurantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation.deposit-paid',
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
