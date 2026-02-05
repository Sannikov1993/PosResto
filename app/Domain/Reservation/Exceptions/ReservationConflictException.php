<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use App\Models\Reservation;
use App\ValueObjects\TimeSlot;
use Illuminate\Support\Collection;

/**
 * Exception thrown when a reservation conflicts with existing bookings.
 *
 * Contains detailed information about conflicting reservations
 * for display to users and debugging.
 */
final class ReservationConflictException extends ReservationException
{
    protected string $errorCode = 'reservation_conflict';
    protected int $httpStatus = 409; // Conflict

    /**
     * Conflicting reservations.
     *
     * @var Collection<Reservation>
     */
    private Collection $conflicts;

    /**
     * Table IDs that have conflicts.
     */
    private array $tableIds;

    /**
     * Requested time slot.
     */
    private ?TimeSlot $requestedSlot;

    private function __construct(string $message)
    {
        parent::__construct($message);
        $this->conflicts = collect();
        $this->tableIds = [];
        $this->requestedSlot = null;
    }

    /**
     * Create exception for table conflict.
     */
    public static function forTables(
        array $tableIds,
        TimeSlot $requestedSlot,
        Collection $conflicts
    ): self {
        $tableNumbers = $conflicts
            ->pluck('table.number')
            ->unique()
            ->filter()
            ->implode(', ');

        $conflictTimes = $conflicts
            ->map(fn($r) => $r->time_from . '-' . $r->time_to)
            ->unique()
            ->implode(', ');

        $message = "Столы уже заняты в это время. ";
        if ($tableNumbers) {
            $message .= "Стол(ы): {$tableNumbers}. ";
        }
        if ($conflictTimes) {
            $message .= "Конфликтующие брони: {$conflictTimes}.";
        }

        $instance = new self($message);
        $instance->conflicts = $conflicts;
        $instance->tableIds = $tableIds;
        $instance->requestedSlot = $requestedSlot;
        $instance->context = [
            'table_ids' => $tableIds,
            'requested_time' => [
                'date' => $requestedSlot->getDate(),
                'from' => $requestedSlot->getTimeFrom(),
                'to' => $requestedSlot->getTimeTo(),
            ],
            'conflicts' => $conflicts->map(fn($r) => [
                'id' => $r->id,
                'time_from' => $r->time_from,
                'time_to' => $r->time_to,
                'guest_name' => $r->guest_name,
                'status' => $r->status,
            ])->values()->toArray(),
        ];

        return $instance;
    }

    /**
     * Create exception for single table conflict.
     */
    public static function forTable(
        int $tableId,
        TimeSlot $requestedSlot,
        Collection $conflicts
    ): self {
        return self::forTables([$tableId], $requestedSlot, $conflicts);
    }

    /**
     * Create simple conflict exception with message.
     */
    public static function withMessage(string $message): self
    {
        return new self($message);
    }

    /**
     * Create exception for overlapping time slots.
     */
    public static function overlappingSlot(
        TimeSlot $requested,
        TimeSlot $existing,
        ?Reservation $existingReservation = null
    ): self {
        $message = sprintf(
            'Запрошенное время %s-%s пересекается с существующей бронью %s-%s.',
            $requested->getTimeFrom(),
            $requested->getTimeTo(),
            $existing->getTimeFrom(),
            $existing->getTimeTo()
        );

        $instance = new self($message);
        $instance->requestedSlot = $requested;
        $instance->context = [
            'requested_time' => [
                'date' => $requested->getDate(),
                'from' => $requested->getTimeFrom(),
                'to' => $requested->getTimeTo(),
            ],
            'existing_time' => [
                'date' => $existing->getDate(),
                'from' => $existing->getTimeFrom(),
                'to' => $existing->getTimeTo(),
            ],
        ];

        if ($existingReservation) {
            $instance->conflicts = collect([$existingReservation]);
            $instance->context['existing_reservation_id'] = $existingReservation->id;
        }

        return $instance;
    }

    /**
     * Get conflicting reservations.
     *
     * @return Collection<Reservation>
     */
    public function getConflicts(): Collection
    {
        return $this->conflicts;
    }

    /**
     * Get table IDs with conflicts.
     */
    public function getTableIds(): array
    {
        return $this->tableIds;
    }

    /**
     * Get requested time slot.
     */
    public function getRequestedSlot(): ?TimeSlot
    {
        return $this->requestedSlot;
    }

    /**
     * Check if specific table has conflict.
     */
    public function hasConflictForTable(int $tableId): bool
    {
        return in_array($tableId, $this->tableIds, true);
    }
}
