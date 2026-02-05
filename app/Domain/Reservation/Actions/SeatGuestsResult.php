<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Collection;

/**
 * Result of SeatGuests action.
 *
 * Contains reservation, optional order, and affected tables.
 */
final class SeatGuestsResult extends ActionResult
{
    /**
     * @param Reservation $reservation Updated reservation
     * @param Order|null $order Created order (if requested)
     * @param Collection<Table> $tables Affected tables
     * @param bool $depositTransferred Whether deposit was transferred to order
     */
    public function __construct(
        Reservation $reservation,
        public readonly ?Order $order = null,
        public readonly ?Collection $tables = null,
        public readonly bool $depositTransferred = false,
        ?string $message = null,
    ) {
        parent::__construct(
            reservation: $reservation,
            success: true,
            message: $message ?? 'Гости посажены за стол',
            metadata: [
                'order_id' => $order?->id,
                'table_ids' => $tables?->pluck('id')->toArray() ?? [],
                'deposit_transferred' => $depositTransferred,
            ]
        );
    }

    /**
     * Check if order was created.
     */
    public function hasOrder(): bool
    {
        return $this->order !== null;
    }

    /**
     * Get table IDs.
     */
    public function getTableIds(): array
    {
        return $this->tables?->pluck('id')->toArray() ?? [];
    }
}
