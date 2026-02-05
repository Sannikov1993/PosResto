<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Events\DepositRefunded;
use App\Domain\Reservation\Events\ReservationCancelled;
use App\Domain\Reservation\Exceptions\DepositException;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Cancel a reservation.
 *
 * Marks reservation as cancelled and handles deposit refund if needed.
 * Frees tables if no other active usage.
 *
 * @throws InvalidStateTransitionException If reservation cannot be cancelled
 * @throws DepositException If deposit refund fails
 */
final class CancelReservation
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the cancel reservation action.
     *
     * @param Reservation $reservation Reservation to cancel
     * @param string|null $reason Cancellation reason
     * @param bool $refundDeposit Whether to refund the deposit
     * @param int|null $userId User performing the action
     *
     * @throws InvalidStateTransitionException
     * @throws DepositException
     */
    public function execute(
        Reservation $reservation,
        ?string $reason = null,
        bool $refundDeposit = true,
        ?int $userId = null,
    ): ActionResult {
        // 1. Validate state transition
        $this->stateMachine->assertCanCancel($reservation);

        // 2. Execute in transaction
        return DB::transaction(function () use ($reservation, $reason, $refundDeposit, $userId) {
            $tableIds = $this->getAllTableIds($reservation);
            $depositRefunded = false;

            // 3. Handle deposit refund
            if ($refundDeposit && $this->hasRefundableDeposit($reservation)) {
                $this->processDepositRefund($reservation, $userId);
                $depositRefunded = true;
            }

            // 4. Update reservation status
            $reservation->update([
                'status' => ReservationStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
            ]);

            // 5. Free tables if possible
            $this->freeTablesIfPossible($tableIds);

            $result = ActionResult::success(
                reservation: $reservation->fresh(),
                message: 'Бронирование отменено',
                metadata: [
                    'table_ids' => $tableIds,
                    'deposit_refunded' => $depositRefunded,
                    'reason' => $reason,
                ]
            );

            // Dispatch events
            ReservationCancelled::dispatch(
                $result->reservation,
                $reason,
                $depositRefunded,
                $userId
            );

            if ($depositRefunded) {
                DepositRefunded::dispatch(
                    $result->reservation,
                    (float) $reservation->deposit,
                    $reason,
                    $userId
                );
            }

            return $result;
        });
    }

    /**
     * Get all table IDs including linked tables.
     */
    private function getAllTableIds(Reservation $reservation): array
    {
        $tableIds = [$reservation->table_id];

        if (!empty($reservation->linked_table_ids)) {
            $linkedIds = is_array($reservation->linked_table_ids)
                ? $reservation->linked_table_ids
                : json_decode($reservation->linked_table_ids, true);

            if (is_array($linkedIds)) {
                $tableIds = array_merge($tableIds, $linkedIds);
            }
        }

        return array_unique(array_filter($tableIds));
    }

    /**
     * Check if reservation has refundable deposit.
     */
    private function hasRefundableDeposit(Reservation $reservation): bool
    {
        return $reservation->deposit > 0
            && $reservation->deposit_status === 'paid'
            && $reservation->deposit_status !== 'refunded';
    }

    /**
     * Process deposit refund.
     *
     * @throws DepositException
     */
    private function processDepositRefund(Reservation $reservation, ?int $userId): void
    {
        // Check if deposit was already transferred to order
        if ($reservation->deposit_status === 'transferred') {
            throw DepositException::alreadyTransferred(
                $reservation,
                $reservation->deposit_transferred_to_order_id
            );
        }

        // Mark deposit as refunded
        $reservation->update([
            'deposit_status' => 'refunded',
            'deposit_refunded_at' => now(),
            'deposit_refunded_by' => $userId,
        ]);
    }

    /**
     * Free tables if no other active usage.
     */
    private function freeTablesIfPossible(array $tableIds): void
    {
        foreach ($tableIds as $tableId) {
            $table = Table::find($tableId);
            if (!$table) {
                continue;
            }

            // Check for other active reservations
            $hasActiveReservations = Reservation::where('table_id', $tableId)
                ->whereIn('status', [
                    ReservationStatus::CONFIRMED->value,
                    ReservationStatus::SEATED->value,
                ])
                ->exists();

            if (!$hasActiveReservations && $table->status !== 'occupied') {
                $table->update(['status' => 'free']);
            }
        }
    }
}
