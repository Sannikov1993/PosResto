<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Order;
use App\Models\Reservation;

/**
 * Event dispatched when a deposit is transferred to an order.
 *
 * Listeners can:
 * - Update order payment status
 * - Log for accounting
 * - Notify POS about prepayment
 */
final class DepositTransferred extends ReservationEvent
{
    public Order $order;
    public float $amount;

    /**
     * @param Reservation $reservation
     * @param Order $order Target order
     * @param float $amount Transferred amount
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        Order $order,
        float $amount,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->order = $order;
        $this->amount = $amount;
    }

    public function getEventName(): string
    {
        return 'deposit.transferred';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Депозит %.2f ₽ перенесён из брони #%d в заказ #%d',
            $this->amount,
            $this->reservation->id,
            $this->order->id
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'order_id' => $this->order->id,
            'amount' => $this->amount,
        ]);
    }
}
