<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Event dispatched when guests are seated.
 *
 * Listeners can:
 * - Notify kitchen about new guests
 * - Update table status on floor map
 * - Start service timer
 */
final class ReservationSeated extends ReservationEvent
{
    public ?Order $order;
    public ?Collection $tables;
    public bool $depositTransferred;

    /**
     * @param Reservation $reservation
     * @param Order|null $order Created order (if any)
     * @param Collection|null $tables Occupied tables
     * @param bool $depositTransferred Whether deposit was transferred
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        ?Order $order = null,
        ?Collection $tables = null,
        bool $depositTransferred = false,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->order = $order;
        $this->tables = $tables;
        $this->depositTransferred = $depositTransferred;
    }

    public function getEventName(): string
    {
        return 'reservation.seated';
    }

    public function getDescription(): string
    {
        $tableNumbers = $this->tables?->pluck('number')->join(', ') ?? 'N/A';

        return sprintf(
            'Гости посажены по брони #%d (столы: %s)',
            $this->reservation->id,
            $tableNumbers
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'order_id' => $this->order?->id,
            'table_ids' => $this->tables?->pluck('id')->toArray() ?? [],
            'deposit_transferred' => $this->depositTransferred,
        ]);
    }
}
