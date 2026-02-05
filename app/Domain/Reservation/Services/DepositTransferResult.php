<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Services;

use App\Models\Order;
use App\Models\Reservation;

/**
 * Result of a deposit transfer operation.
 */
final class DepositTransferResult
{
    public function __construct(
        public readonly Reservation $reservation,
        public readonly Order $order,
        public readonly float $amount,
    ) {}

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        return [
            'success' => true,
            'message' => sprintf('Депозит %.2f ₽ перенесён в заказ #%d', $this->amount, $this->order->id),
            'reservation_id' => $this->reservation->id,
            'order_id' => $this->order->id,
            'amount' => $this->amount,
        ];
    }
}
