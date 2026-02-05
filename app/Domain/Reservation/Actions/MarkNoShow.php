<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Events\DepositForfeited;
use App\Domain\Reservation\Events\ReservationNoShow;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Mark a reservation as no-show.
 *
 * Used when guests don't arrive for their confirmed reservation.
 * Handles deposit forfeiture policy and frees tables.
 *
 * @throws InvalidStateTransitionException If reservation is not confirmed
 */
final class MarkNoShow
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the mark no-show action.
     *
     * @param Reservation $reservation Reservation to mark as no-show
     * @param bool $forfeitDeposit Whether to forfeit the deposit (no refund)
     * @param int|null $userId User performing the action
     * @param string|null $notes Additional notes about the no-show
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(
        Reservation $reservation,
        bool $forfeitDeposit = true,
        ?int $userId = null,
        ?string $notes = null,
    ): ActionResult {
        // 1. Validate state transition
        $this->stateMachine->assertCanMarkNoShow($reservation);

        // 2. Execute in transaction
        return DB::transaction(function () use ($reservation, $forfeitDeposit, $userId, $notes) {
            $tableIds = $this->getAllTableIds($reservation);
            $depositForfeited = false;

            // 3. Handle deposit forfeiture
            if ($forfeitDeposit && $this->hasPaidDeposit($reservation)) {
                $this->forfeitDeposit($reservation, $userId);
                $depositForfeited = true;
            }

            // 4. Update reservation status
            $updateData = [
                'status' => ReservationStatus::NO_SHOW->value,
                'no_show_at' => now(),
                'no_show_by' => $userId,
            ];

            if ($notes) {
                $updateData['notes'] = $this->appendNotes($reservation->notes, $notes);
            }

            $reservation->update($updateData);

            // 5. Free tables if possible
            $this->freeTablesIfPossible($tableIds);

            $result = ActionResult::success(
                reservation: $reservation->fresh(),
                message: 'Бронирование отмечено как неявка',
                metadata: [
                    'table_ids' => $tableIds,
                    'deposit_forfeited' => $depositForfeited,
                ]
            );

            // Dispatch events
            ReservationNoShow::dispatch(
                $result->reservation,
                $depositForfeited,
                $userId
            );

            if ($depositForfeited) {
                DepositForfeited::dispatch(
                    $result->reservation,
                    (float) $reservation->deposit,
                    $notes,
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
     * Check if reservation has a paid deposit.
     */
    private function hasPaidDeposit(Reservation $reservation): bool
    {
        return $reservation->deposit > 0
            && $reservation->deposit_status === 'paid';
    }

    /**
     * Forfeit the deposit (mark as non-refundable).
     */
    private function forfeitDeposit(Reservation $reservation, ?int $userId): void
    {
        $reservation->update([
            'deposit_status' => 'forfeited',
            'deposit_forfeited_at' => now(),
            'deposit_forfeited_by' => $userId,
        ]);
    }

    /**
     * Append notes to existing notes.
     */
    private function appendNotes(?string $existing, string $new): string
    {
        if (empty($existing)) {
            return "[No-show] {$new}";
        }

        return $existing . "\n[No-show] " . $new;
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

            // Check for other active reservations on this table
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
