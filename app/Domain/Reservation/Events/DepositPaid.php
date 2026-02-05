<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;

/**
 * Event dispatched when a deposit is paid.
 *
 * Listeners can:
 * - Send payment confirmation
 * - Update accounting records
 * - Generate receipt
 */
final class DepositPaid extends ReservationEvent
{
    public float $amount;
    public ?string $paymentMethod;
    public ?string $transactionId;

    /**
     * @param Reservation $reservation
     * @param float $amount Paid amount
     * @param string|null $paymentMethod Payment method used
     * @param string|null $transactionId External transaction ID
     * @param int|null $userId
     * @param array $metadata
     */
    public function __construct(
        Reservation $reservation,
        float $amount,
        ?string $paymentMethod = null,
        ?string $transactionId = null,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($reservation, $userId, $metadata);
        $this->amount = $amount;
        $this->paymentMethod = $paymentMethod;
        $this->transactionId = $transactionId;
    }

    public function getEventName(): string
    {
        return 'deposit.paid';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Депозит %.2f ₽ оплачен для брони #%d',
            $this->amount,
            $this->reservation->id
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'transaction_id' => $this->transactionId,
        ]);
    }
}
