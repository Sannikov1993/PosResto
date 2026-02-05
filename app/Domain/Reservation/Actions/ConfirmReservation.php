<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Events\ReservationConfirmed;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\ReservationConflictException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Action: Confirm a pending reservation.
 *
 * Transitions reservation from PENDING to CONFIRMED status.
 * Validates that there are no time conflicts with other confirmed reservations.
 *
 * @throws InvalidStateTransitionException If reservation is not pending
 * @throws ReservationConflictException If there are time conflicts
 */
final class ConfirmReservation
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the confirm reservation action.
     *
     * @param Reservation $reservation Reservation to confirm
     * @param int|null $userId User performing the action
     * @param bool $skipConflictCheck Skip conflict validation (use with caution)
     *
     * @throws InvalidStateTransitionException
     * @throws ReservationConflictException
     */
    public function execute(
        Reservation $reservation,
        ?int $userId = null,
        bool $skipConflictCheck = false,
    ): ActionResult {
        // 1. Validate state transition
        $this->stateMachine->assertCanConfirm($reservation);

        // 2. Execute in transaction with lock
        return DB::transaction(function () use ($reservation, $userId, $skipConflictCheck) {
            // 3. Check for conflicts (unless skipped)
            if (!$skipConflictCheck) {
                $this->validateNoConflicts($reservation);
            }

            // 4. Update reservation status
            $reservation->update([
                'status' => ReservationStatus::CONFIRMED->value,
                'confirmed_at' => now(),
                'confirmed_by' => $userId,
            ]);

            $result = ActionResult::success(
                reservation: $reservation->fresh(),
                message: 'Бронирование подтверждено',
            );

            ReservationConfirmed::dispatch($result->reservation, $userId);

            return $result;
        });
    }

    /**
     * Validate that there are no conflicting reservations.
     *
     * @throws ReservationConflictException
     */
    private function validateNoConflicts(Reservation $reservation): void
    {
        $tableIds = $this->getAllTableIds($reservation);

        // Find conflicting reservations
        $conflicts = Reservation::query()
            ->where('restaurant_id', $reservation->restaurant_id)
            ->where('id', '!=', $reservation->id)
            ->where('date', $reservation->date)
            ->whereIn('status', [
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::SEATED->value,
            ])
            ->where(function ($query) use ($tableIds) {
                $query->whereIn('table_id', $tableIds)
                    ->orWhere(function ($q) use ($tableIds) {
                        foreach ($tableIds as $tableId) {
                            $q->orWhereJsonContains('linked_table_ids', $tableId);
                        }
                    });
            })
            ->where(function ($query) use ($reservation) {
                // Time overlap check
                $query->where(function ($q) use ($reservation) {
                    $q->where('time_from', '<', $reservation->time_to)
                        ->where('time_to', '>', $reservation->time_from);
                });
            })
            ->get();

        if ($conflicts->isNotEmpty()) {
            $conflict = $conflicts->first();
            throw ReservationConflictException::timeConflict(
                $reservation->date,
                $reservation->time_from,
                $reservation->time_to,
                $conflict
            );
        }
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
}
