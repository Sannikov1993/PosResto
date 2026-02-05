<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use App\ValueObjects\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for detecting and resolving reservation conflicts.
 *
 * Handles all conflict detection logic including midnight-crossing reservations.
 */
class ReservationConflictService
{
    /**
     * Active reservation statuses that can cause conflicts.
     */
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'seated'];

    /**
     * Check if there's a conflict for given tables and time slot.
     *
     * @param array|int $tableIds Table ID(s) to check
     * @param TimeSlot $timeSlot The time slot to check
     * @param int|null $excludeId Reservation ID to exclude (for updates)
     * @return bool
     */
    public function hasConflict(array|int $tableIds, TimeSlot $timeSlot, ?int $excludeId = null): bool
    {
        $tableIds = is_array($tableIds) ? $tableIds : [$tableIds];
        $utcSlot = $timeSlot->toUtc();

        return $this->buildConflictQuery($tableIds, $utcSlot, $excludeId)->exists();
    }

    /**
     * Check for conflict with row-level locking (for race condition prevention).
     *
     * Should be used within a transaction.
     *
     * @param array|int $tableIds Table ID(s) to check
     * @param TimeSlot $timeSlot The time slot to check
     * @param int|null $excludeId Reservation ID to exclude
     * @return bool
     */
    public function hasConflictWithLock(array|int $tableIds, TimeSlot $timeSlot, ?int $excludeId = null): bool
    {
        $tableIds = is_array($tableIds) ? $tableIds : [$tableIds];
        $utcSlot = $timeSlot->toUtc();

        return $this->buildConflictQuery($tableIds, $utcSlot, $excludeId)
            ->lockForUpdate()
            ->exists();
    }

    /**
     * Find all conflicting reservations for given tables and time slot.
     *
     * @param array|int $tableIds Table ID(s) to check
     * @param TimeSlot $timeSlot The time slot to check
     * @param int|null $excludeId Reservation ID to exclude
     * @return Collection
     */
    public function findConflicts(array|int $tableIds, TimeSlot $timeSlot, ?int $excludeId = null): Collection
    {
        $tableIds = is_array($tableIds) ? $tableIds : [$tableIds];
        $utcSlot = $timeSlot->toUtc();

        return $this->buildConflictQuery($tableIds, $utcSlot, $excludeId)->get();
    }

    /**
     * Get available time slots for a table on a given date.
     *
     * @param int $tableId Table ID
     * @param string $date Date (YYYY-MM-DD)
     * @param int $durationMinutes Desired duration in minutes
     * @param array $workHours Work hours ['start' => 'HH:MM', 'end' => 'HH:MM']
     * @param string $timezone Timezone for the restaurant
     * @param int $slotStep Step between slots in minutes (default: 30)
     * @return array Array of available TimeSlot objects
     */
    public function getAvailableSlots(
        int $tableId,
        string $date,
        int $durationMinutes,
        array $workHours,
        string $timezone = 'UTC',
        int $slotStep = 30
    ): array {
        $availableSlots = [];

        // Parse work hours
        $workStart = Carbon::parse($date . ' ' . $workHours['start'], $timezone);
        $workEnd = Carbon::parse($date . ' ' . $workHours['end'], $timezone);

        // Handle overnight working hours
        if ($workEnd->lte($workStart)) {
            $workEnd->addDay();
        }

        // Get existing reservations for this table on this date (and next day for midnight crossing)
        $reservations = $this->getReservationsForDateRange(
            $tableId,
            $workStart->copy()->subDay(),
            $workEnd->copy()->addDay()
        );

        // Generate potential slots
        $currentSlot = $workStart->copy();
        while ($currentSlot->copy()->addMinutes($durationMinutes)->lte($workEnd)) {
            $slotStart = $currentSlot->copy();
            $slotEnd = $currentSlot->copy()->addMinutes($durationMinutes);

            $potentialSlot = TimeSlot::fromDatetimes($slotStart, $slotEnd, $timezone);

            // Check if this slot conflicts with any existing reservation
            $hasConflict = false;
            foreach ($reservations as $reservation) {
                $existingSlot = $this->getTimeSlotFromReservation($reservation);
                if ($existingSlot && $potentialSlot->overlaps($existingSlot)) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict && !$potentialSlot->startsInPast()) {
                $availableSlots[] = $potentialSlot;
            }

            $currentSlot->addMinutes($slotStep);
        }

        return $availableSlots;
    }

    /**
     * Create a TimeSlot from a Reservation model.
     *
     * Prefers starts_at/ends_at if available, falls back to legacy fields.
     *
     * @param Reservation $reservation
     * @return TimeSlot|null
     */
    public function getTimeSlotFromReservation(Reservation $reservation): ?TimeSlot
    {
        // Prefer new datetime fields if available
        if ($reservation->starts_at && $reservation->ends_at) {
            return TimeSlot::fromDatetimes(
                $reservation->starts_at,
                $reservation->ends_at,
                $reservation->timezone ?? 'UTC'
            );
        }

        // Fall back to legacy fields
        if ($reservation->date && $reservation->time_from && $reservation->time_to) {
            $timezone = $reservation->timezone ?? 'UTC';

            // Handle date being either Carbon or string
            $dateStr = $reservation->date instanceof Carbon
                ? $reservation->date->format('Y-m-d')
                : substr($reservation->date, 0, 10);

            // Normalize time strings (remove seconds if present)
            $timeFrom = substr($reservation->time_from, 0, 5);
            $timeTo = substr($reservation->time_to, 0, 5);

            return TimeSlot::fromDateAndTimes($dateStr, $timeFrom, $timeTo, $timezone);
        }

        return null;
    }

    /**
     * Build the base conflict detection query.
     *
     * Supports both new datetime fields (starts_at/ends_at) and legacy fields (date/time_from/time_to).
     *
     * @param array $tableIds
     * @param TimeSlot $utcSlot Time slot in UTC
     * @param int|null $excludeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildConflictQuery(array $tableIds, TimeSlot $utcSlot, ?int $excludeId = null)
    {
        $startsAt = $utcSlot->startsAt();
        $endsAt = $utcSlot->endsAt();

        $query = Reservation::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function ($q) use ($tableIds) {
                foreach ($tableIds as $tableId) {
                    $tableId = (int) $tableId;
                    $q->orWhere('table_id', $tableId)
                      ->orWhereJsonContains('linked_table_ids', $tableId)
                      ->orWhereJsonContains('linked_table_ids', (string) $tableId);
                }
            });

        // Check for overlap using new datetime fields OR legacy fields
        $query->where(function ($q) use ($startsAt, $endsAt, $utcSlot) {
            // New datetime-based overlap detection (if starts_at/ends_at exist)
            $q->where(function ($q2) use ($startsAt, $endsAt) {
                $q2->whereNotNull('starts_at')
                   ->whereNotNull('ends_at')
                   ->where('starts_at', '<', $endsAt)
                   ->where('ends_at', '>', $startsAt);
            });

            // OR legacy field-based detection (fallback)
            $q->orWhere(function ($q2) use ($utcSlot) {
                $q2->whereNull('starts_at')
                   ->where(function ($q3) use ($utcSlot) {
                       $this->addLegacyOverlapConditions($q3, $utcSlot);
                   });
            });
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    /**
     * Add legacy (date/time_from/time_to) overlap conditions.
     *
     * This is a fallback for records that haven't been migrated yet.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param TimeSlot $utcSlot
     */
    private function addLegacyOverlapConditions($query, TimeSlot $utcSlot): void
    {
        // For legacy records, we need to check the date field
        // and handle same-day time comparison
        $date = $utcSlot->getDate();
        $endDate = $utcSlot->getEndDate();
        $timeFrom = $utcSlot->getTimeFrom() . ':00';
        $timeTo = $utcSlot->getTimeTo() . ':00';

        if ($date === $endDate) {
            // Non-midnight-crossing slot
            $query->where('date', $date)
                  ->where(function ($q) use ($timeFrom, $timeTo) {
                      // Standard overlap: start1 < end2 AND start2 < end1
                      $q->where('time_from', '<', $timeTo)
                        ->where('time_to', '>', $timeFrom);
                  });
        } else {
            // Midnight-crossing slot - need to check both dates
            $query->where(function ($q) use ($date, $endDate, $timeFrom, $timeTo) {
                // Reservations on the start date
                $q->where(function ($q2) use ($date, $timeFrom) {
                    $q2->where('date', $date)
                       ->where('time_to', '>', $timeFrom);
                });
                // Reservations on the end date
                $q->orWhere(function ($q2) use ($endDate, $timeTo) {
                    $q2->where('date', $endDate)
                       ->where('time_from', '<', $timeTo);
                });
            });
        }
    }

    /**
     * Get reservations for a table within a date range.
     *
     * @param int $tableId
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection
     */
    private function getReservationsForDateRange(int $tableId, Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function ($q) use ($tableId) {
                $q->where('table_id', $tableId)
                  ->orWhereJsonContains('linked_table_ids', $tableId)
                  ->orWhereJsonContains('linked_table_ids', (string) $tableId);
            })
            ->where(function ($q) use ($from, $to) {
                // New datetime fields
                $q->where(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('starts_at')
                       ->whereBetween('starts_at', [$from, $to]);
                });
                // Legacy date field
                $q->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('starts_at')
                       ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
                });
            })
            ->get();
    }

    /**
     * Validate that a time slot doesn't create a conflict (for use in form requests).
     *
     * @param array|int $tableIds
     * @param TimeSlot $timeSlot
     * @param int|null $excludeId
     * @param int|null $restaurantId Optional restaurant ID for validation
     * @return array ['valid' => bool, 'message' => string|null, 'conflicts' => Collection]
     */
    public function validateNoConflict(
        array|int $tableIds,
        TimeSlot $timeSlot,
        ?int $excludeId = null,
        ?int $restaurantId = null
    ): array {
        $tableIds = is_array($tableIds) ? $tableIds : [$tableIds];
        $conflicts = $this->findConflicts($tableIds, $timeSlot, $excludeId);

        if ($conflicts->isEmpty()) {
            return [
                'valid' => true,
                'message' => null,
                'conflicts' => collect(),
            ];
        }

        // Build descriptive error message
        $conflictTimes = $conflicts->map(function ($res) {
            $slot = $this->getTimeSlotFromReservation($res);
            $guestName = $res->guest_name ?: 'Гость';
            return $slot
                ? "{$guestName}: {$slot->getTimeRange()}"
                : "{$guestName}";
        })->join(', ');

        return [
            'valid' => false,
            'message' => "Время конфликтует с существующими бронированиями: {$conflictTimes}",
            'conflicts' => $conflicts,
        ];
    }
}
