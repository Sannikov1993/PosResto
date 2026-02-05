<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\ReservationValidationException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Unseat guests (return reservation to confirmed status).
 *
 * Used when guests leave temporarily or seating was done by mistake.
 * Only frees tables if no other active orders exist.
 *
 * @throws InvalidStateTransitionException If reservation is not seated
 * @throws ReservationValidationException If there are unpaid orders
 */
final class UnseatGuests
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the unseat guests action.
     *
     * @param Reservation $reservation Reservation to unseat
     * @param bool $force Force unseat even if there are active orders
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
        $this->stateMachine->assertCanUnseat($reservation);

        // 2. Execute in transaction
        return DB::transaction(function () use ($reservation, $force, $userId) {
            $tableIds = $this->getAllTableIds($reservation);

            // 3. Check for active orders (unless forced)
            if (!$force) {
                $this->validateNoActiveOrders($reservation, $tableIds);
            }

            // 4. Update reservation status
            $reservation->update([
                'status' => ReservationStatus::CONFIRMED->value,
                'unseated_at' => now(),
                'unseated_by' => $userId,
            ]);

            // 5. Update table statuses (only if no other active reservations/orders)
            $this->updateTableStatuses($tableIds);

            return ActionResult::success(
                reservation: $reservation->fresh(),
                message: 'Гости сняты со стола',
                metadata: ['table_ids' => $tableIds]
            );
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
     * Validate that there are no active orders preventing unseat.
     *
     * @throws ReservationValidationException
     */
    private function validateNoActiveOrders(Reservation $reservation, array $tableIds): void
    {
        // Check for unpaid orders linked to this reservation
        $hasUnpaidOrders = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['open', 'pending'])
            ->where(function ($q) {
                $q->whereNull('paid_at')
                    ->orWhereColumn('total', '>', 'paid_amount');
            })
            ->exists();

        if ($hasUnpaidOrders) {
            throw ReservationValidationException::withMessage(
                'Невозможно снять гостей: есть неоплаченные заказы.',
                'orders'
            );
        }
    }

    /**
     * Update table statuses - set to free if no other active usage.
     */
    private function updateTableStatuses(array $tableIds): void
    {
        foreach ($tableIds as $tableId) {
            $table = Table::find($tableId);
            if (!$table) {
                continue;
            }

            // Check if table has other seated reservations
            $hasOtherSeatedReservations = Reservation::where('table_id', $tableId)
                ->where('status', ReservationStatus::SEATED->value)
                ->exists();

            // Check if table has active orders
            $hasActiveOrders = Order::where('table_id', $tableId)
                ->whereIn('status', ['open', 'pending'])
                ->exists();

            // Only free the table if no other usage
            if (!$hasOtherSeatedReservations && !$hasActiveOrders) {
                $table->update(['status' => 'free']);
            }
        }
    }
}
