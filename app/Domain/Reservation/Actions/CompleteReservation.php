<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Events\ReservationCompleted;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\ReservationValidationException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Complete a reservation.
 *
 * Marks reservation as completed after guests leave.
 * Frees tables and closes associated orders if needed.
 *
 * @throws InvalidStateTransitionException If reservation is not seated
 * @throws ReservationValidationException If there are unpaid orders
 */
final class CompleteReservation
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the complete reservation action.
     *
     * @param Reservation $reservation Reservation to complete
     * @param bool $force Force complete even if there are unpaid orders
     * @param int|null $userId User performing the action
     *
     * @throws InvalidStateTransitionException
     * @throws ReservationValidationException
     */
    public function execute(
        Reservation $reservation,
        bool $force = false,
        ?int $userId = null,
    ): ActionResult {
        // 1. Validate state transition
        $this->stateMachine->assertCanComplete($reservation);

        // 2. Execute in transaction
        return DB::transaction(function () use ($reservation, $force, $userId) {
            $tableIds = $this->getAllTableIds($reservation);

            // 3. Check for unpaid orders (unless forced)
            if (!$force) {
                $this->validateOrdersPaid($reservation);
            }

            // 4. Update reservation status
            $reservation->update([
                'status' => ReservationStatus::COMPLETED->value,
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            // 5. Close any open orders
            $this->closeOrders($reservation);

            // 6. Free tables (only if no other usage)
            $this->freeTablesIfPossible($tableIds);

            $result = ActionResult::success(
                reservation: $reservation->fresh(),
                message: 'Бронирование завершено',
                metadata: ['table_ids' => $tableIds]
            );

            ReservationCompleted::dispatch($result->reservation, $userId);

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
     * Validate that all orders are paid.
     *
     * @throws ReservationValidationException
     */
    private function validateOrdersPaid(Reservation $reservation): void
    {
        $unpaidOrder = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['open', 'pending'])
            ->where(function ($q) {
                $q->whereNull('paid_at')
                    ->orWhereColumn('total', '>', 'paid_amount');
            })
            ->first();

        if ($unpaidOrder) {
            $remaining = ($unpaidOrder->total ?? 0) - ($unpaidOrder->paid_amount ?? 0);
            throw ReservationValidationException::withMessage(
                sprintf(
                    'Невозможно завершить бронирование: заказ #%d не оплачен (осталось %.2f ₽).',
                    $unpaidOrder->id,
                    $remaining
                ),
                'orders'
            );
        }
    }

    /**
     * Close any open orders for this reservation.
     */
    private function closeOrders(Reservation $reservation): void
    {
        Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['open', 'pending'])
            ->update([
                'status' => OrderStatus::COMPLETED->value,
                'closed_at' => now(),
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

            // Check for other seated reservations
            $hasOtherSeated = Reservation::where('table_id', $tableId)
                ->where('status', ReservationStatus::SEATED->value)
                ->exists();

            // Check for other active orders
            $hasActiveOrders = Order::where('table_id', $tableId)
                ->whereIn('status', ['open', 'pending'])
                ->exists();

            if (!$hasOtherSeated && !$hasActiveOrders) {
                $table->update(['status' => 'free']);
            }
        }
    }
}
