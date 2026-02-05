<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;

/**
 * Event dispatched when a reservation is cancelled.
 *
 * Listeners can:
 * - Send cancellation confirmation
 * - Free table slots
 * - Process refund if applicable
 * - Update analytics
 */
final class ReservationCancelled extends ReservationEvent
{
    public ?string $reason;
    public bool $depositRefunded;

    /**
     * @param Reservation $reservation
     * @param string|null $reason Cancellation reason
     * @param bool $depositRefunded Whether deposit was refunded
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        ?string $reason = null,
        bool $depositRefunded = false,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->reason = $reason;
        $this->depositRefunded = $depositRefunded;
    }

    public function getEventName(): string
    {
        return 'reservation.cancelled';
    }

    public function getDescription(): string
    {
        $desc = sprintf('Бронирование #%d отменено', $this->reservation->id);

        if ($this->reason) {
            $desc .= ": {$this->reason}";
        }

        return $desc;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'reason' => $this->reason,
            'deposit_refunded' => $this->depositRefunded,
        ]);
    }
}
