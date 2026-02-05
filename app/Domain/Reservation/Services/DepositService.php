<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Services;

use App\Domain\Reservation\Exceptions\DepositException;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing reservation deposits.
 *
 * Handles all deposit operations:
 * - Collecting (marking as paid)
 * - Refunding
 * - Transferring to orders
 * - Forfeiting (no-show penalty)
 *
 * Usage:
 *   $service = app(DepositService::class);
 *   $service->markAsPaid($reservation, 'card', 'txn_123');
 *   $service->transferToOrder($reservation, $order);
 */
final class DepositService
{
    /**
     * Valid deposit statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_FORFEITED = 'forfeited';

    /**
     * All valid statuses.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_REFUNDED,
        self::STATUS_TRANSFERRED,
        self::STATUS_FORFEITED,
    ];

    /**
     * Check if reservation requires a deposit.
     */
    public function requiresDeposit(Reservation $reservation): bool
    {
        return $reservation->deposit > 0;
    }

    /**
     * Check if deposit is paid.
     */
    public function isPaid(Reservation $reservation): bool
    {
        return $reservation->deposit_status === self::STATUS_PAID;
    }

    /**
     * Check if deposit can be collected.
     */
    public function canCollect(Reservation $reservation): bool
    {
        return $this->requiresDeposit($reservation)
            && $reservation->deposit_status === self::STATUS_PENDING;
    }

    /**
     * Check if deposit can be refunded.
     */
    public function canRefund(Reservation $reservation): bool
    {
        return $reservation->deposit_status === self::STATUS_PAID;
    }

    /**
     * Check if deposit can be transferred to order.
     */
    public function canTransfer(Reservation $reservation): bool
    {
        return $reservation->deposit_status === self::STATUS_PAID
            && $reservation->deposit > 0;
    }

    /**
     * Check if deposit can be forfeited.
     */
    public function canForfeit(Reservation $reservation): bool
    {
        return $reservation->deposit_status === self::STATUS_PAID;
    }

    /**
     * Mark deposit as paid.
     *
     * @param Reservation $reservation
     * @param string|null $paymentMethod Payment method (card, cash, online, etc.)
     * @param string|null $transactionId External transaction ID
     * @param int|null $userId User who collected the deposit
     *
     * @throws DepositException
     */
    public function markAsPaid(
        Reservation $reservation,
        ?string $paymentMethod = null,
        ?string $transactionId = null,
        ?int $userId = null,
    ): Reservation {
        if (!$this->canCollect($reservation)) {
            if ($reservation->deposit_status === self::STATUS_PAID) {
                throw DepositException::alreadyPaid($reservation);
            }
            throw DepositException::invalidOperation(
                $reservation,
                'collect',
                "Deposit cannot be collected in status: {$reservation->deposit_status}"
            );
        }

        $reservation->update([
            'deposit_status' => self::STATUS_PAID,
            'deposit_paid_at' => now(),
            'deposit_paid_by' => $userId,
            'deposit_payment_method' => $paymentMethod,
            'deposit_transaction_id' => $transactionId,
        ]);

        return $reservation->fresh();
    }

    /**
     * Refund the deposit.
     *
     * @param Reservation $reservation
     * @param string|null $reason Refund reason
     * @param int|null $userId User who processed the refund
     *
     * @throws DepositException
     */
    public function refund(
        Reservation $reservation,
        ?string $reason = null,
        ?int $userId = null,
    ): Reservation {
        if (!$this->canRefund($reservation)) {
            if ($reservation->deposit_status === self::STATUS_TRANSFERRED) {
                throw DepositException::alreadyTransferred(
                    $reservation,
                    $reservation->deposit_transferred_to_order_id
                );
            }
            if ($reservation->deposit_status === self::STATUS_REFUNDED) {
                throw DepositException::alreadyRefunded($reservation);
            }
            throw DepositException::invalidOperation(
                $reservation,
                'refund',
                "Cannot refund deposit in status: {$reservation->deposit_status}"
            );
        }

        $reservation->update([
            'deposit_status' => self::STATUS_REFUNDED,
            'deposit_refunded_at' => now(),
            'deposit_refunded_by' => $userId,
            'deposit_refund_reason' => $reason,
        ]);

        return $reservation->fresh();
    }

    /**
     * Transfer deposit to an order as prepayment.
     *
     * @param Reservation $reservation
     * @param Order $order Target order
     * @param int|null $userId User who processed the transfer
     *
     * @throws DepositException
     */
    public function transferToOrder(
        Reservation $reservation,
        Order $order,
        ?int $userId = null,
    ): DepositTransferResult {
        if (!$this->canTransfer($reservation)) {
            if ($reservation->deposit_status === self::STATUS_TRANSFERRED) {
                throw DepositException::alreadyTransferred(
                    $reservation,
                    $reservation->deposit_transferred_to_order_id
                );
            }
            throw DepositException::invalidOperation(
                $reservation,
                'transfer',
                "Cannot transfer deposit in status: {$reservation->deposit_status}"
            );
        }

        // Validate order belongs to reservation
        if ($order->reservation_id !== $reservation->id) {
            throw DepositException::orderMismatch($reservation, $order);
        }

        return DB::transaction(function () use ($reservation, $order, $userId) {
            $amount = (float) $reservation->deposit;

            // Update reservation
            $reservation->update([
                'deposit_status' => self::STATUS_TRANSFERRED,
                'deposit_transferred_to_order_id' => $order->id,
                'deposit_transferred_at' => now(),
                'deposit_transferred_by' => $userId,
            ]);

            // Update order with prepayment
            $order->update([
                'prepaid_amount' => ($order->prepaid_amount ?? 0) + $amount,
                'prepaid_source' => 'reservation_deposit',
                'prepaid_reservation_id' => $reservation->id,
            ]);

            return new DepositTransferResult(
                reservation: $reservation->fresh(),
                order: $order->fresh(),
                amount: $amount,
            );
        });
    }

    /**
     * Forfeit the deposit (no-show penalty).
     *
     * @param Reservation $reservation
     * @param string|null $reason Forfeiture reason
     * @param int|null $userId User who processed the forfeiture
     *
     * @throws DepositException
     */
    public function forfeit(
        Reservation $reservation,
        ?string $reason = null,
        ?int $userId = null,
    ): Reservation {
        if (!$this->canForfeit($reservation)) {
            throw DepositException::invalidOperation(
                $reservation,
                'forfeit',
                "Cannot forfeit deposit in status: {$reservation->deposit_status}"
            );
        }

        $reservation->update([
            'deposit_status' => self::STATUS_FORFEITED,
            'deposit_forfeited_at' => now(),
            'deposit_forfeited_by' => $userId,
            'deposit_forfeit_reason' => $reason ?? 'No-show',
        ]);

        return $reservation->fresh();
    }

    /**
     * Calculate required deposit amount based on reservation.
     *
     * Override this method to implement custom deposit calculation logic.
     */
    public function calculateAmount(Reservation $reservation): float
    {
        // Default: use restaurant's deposit settings
        $restaurant = $reservation->restaurant;

        if (!$restaurant || !$restaurant->deposit_required) {
            return 0.0;
        }

        // Fixed amount per guest or percentage of average check
        if ($restaurant->deposit_type === 'per_guest') {
            return (float) ($restaurant->deposit_amount * $reservation->guests_count);
        }

        if ($restaurant->deposit_type === 'fixed') {
            return (float) $restaurant->deposit_amount;
        }

        // Default fallback
        return (float) ($reservation->deposit ?? 0);
    }

    /**
     * Get deposit status label.
     */
    public function getStatusLabel(Reservation $reservation): string
    {
        return match ($reservation->deposit_status) {
            self::STATUS_PENDING => 'Ожидает оплаты',
            self::STATUS_PAID => 'Оплачен',
            self::STATUS_REFUNDED => 'Возвращён',
            self::STATUS_TRANSFERRED => 'Перенесён в заказ',
            self::STATUS_FORFEITED => 'Конфискован',
            default => 'Неизвестно',
        };
    }

    /**
     * Get deposit summary for display.
     */
    public function getSummary(Reservation $reservation): array
    {
        return [
            'amount' => (float) $reservation->deposit,
            'status' => $reservation->deposit_status,
            'status_label' => $this->getStatusLabel($reservation),
            'is_paid' => $this->isPaid($reservation),
            'can_refund' => $this->canRefund($reservation),
            'can_transfer' => $this->canTransfer($reservation),
            'paid_at' => $reservation->deposit_paid_at,
            'transferred_to_order_id' => $reservation->deposit_transferred_to_order_id,
        ];
    }
}
