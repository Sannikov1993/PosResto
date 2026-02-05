<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions\Concerns;

use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;

/**
 * Shared functionality for handling reservation tables.
 *
 * Provides common methods for:
 * - Getting all table IDs (main + linked)
 * - Freeing tables when no longer in use
 */
trait HandlesReservationTables
{
    /**
     * Get all table IDs including linked tables.
     */
    protected function getAllTableIds(Reservation $reservation): array
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
     * Free tables if no other active usage.
     *
     * Checks for:
     * - Other seated reservations on the table
     * - Active orders on the table
     *
     * Only frees if neither exists.
     */
    protected function freeTablesIfPossible(array $tableIds): void
    {
        foreach ($tableIds as $tableId) {
            $table = Table::find($tableId);
            if (!$table) {
                continue;
            }

            // Check for other seated reservations
            $hasSeatedReservations = Reservation::where('table_id', $tableId)
                ->where('status', ReservationStatus::SEATED->value)
                ->exists();

            // Check for active orders
            $hasActiveOrders = Order::where('table_id', $tableId)
                ->whereIn('status', ['open', 'pending'])
                ->exists();

            // Only free if no active usage
            if (!$hasSeatedReservations && !$hasActiveOrders) {
                $table->update(['status' => 'free']);
            }
        }
    }

    /**
     * Free tables, considering active reservations (not just seated).
     *
     * Used when cancelling - checks for confirmed reservations too.
     */
    protected function freeTablesIfNoActiveReservations(array $tableIds): void
    {
        foreach ($tableIds as $tableId) {
            $table = Table::find($tableId);
            if (!$table || $table->status === 'occupied') {
                continue;
            }

            $hasActiveReservations = Reservation::where('table_id', $tableId)
                ->whereIn('status', [
                    ReservationStatus::CONFIRMED->value,
                    ReservationStatus::SEATED->value,
                ])
                ->exists();

            if (!$hasActiveReservations) {
                $table->update(['status' => 'free']);
            }
        }
    }
}
