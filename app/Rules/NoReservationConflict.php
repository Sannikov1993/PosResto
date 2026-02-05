<?php

namespace App\Rules;

use App\Services\ReservationConflictService;
use App\ValueObjects\TimeSlot;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a reservation time slot doesn't conflict with existing reservations.
 */
class NoReservationConflict implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Reservation ID to exclude (for updates).
     */
    protected ?int $excludeId;

    /**
     * Restaurant ID for scoping.
     */
    protected ?int $restaurantId;

    /**
     * Field name for the date.
     */
    protected string $dateField;

    /**
     * Field name for the start time.
     */
    protected string $timeFromField;

    /**
     * Field name for the table ID.
     */
    protected string $tableIdField;

    /**
     * Field name for additional table IDs (for multi-table reservations).
     */
    protected string $tableIdsField;

    /**
     * Timezone for the time slot.
     */
    protected string $timezone;

    /**
     * Create a new rule instance.
     *
     * @param int|null $excludeId Reservation ID to exclude (for updates)
     * @param int|null $restaurantId Restaurant ID
     * @param string $dateField Field name for the date
     * @param string $timeFromField Field name for the start time
     * @param string $tableIdField Field name for the main table ID
     * @param string $tableIdsField Field name for additional table IDs
     * @param string $timezone Timezone for the time slot
     */
    public function __construct(
        ?int $excludeId = null,
        ?int $restaurantId = null,
        string $dateField = 'date',
        string $timeFromField = 'time_from',
        string $tableIdField = 'table_id',
        string $tableIdsField = 'table_ids',
        string $timezone = 'UTC'
    ) {
        $this->excludeId = $excludeId;
        $this->restaurantId = $restaurantId;
        $this->dateField = $dateField;
        $this->timeFromField = $timeFromField;
        $this->tableIdField = $tableIdField;
        $this->tableIdsField = $tableIdsField;
        $this->timezone = $timezone;
    }

    /**
     * Set the data under validation.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value The time_to value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = $this->data[$this->dateField] ?? null;
        $timeFrom = $this->data[$this->timeFromField] ?? null;
        $timeTo = $value;

        // Get table IDs
        $tableId = $this->data[$this->tableIdField] ?? null;
        $tableIds = $this->data[$this->tableIdsField] ?? [];

        // Build complete list of table IDs
        $allTableIds = collect($tableIds);
        if ($tableId) {
            $allTableIds->prepend($tableId);
        }
        $allTableIds = $allTableIds->unique()->filter()->values()->all();

        // Skip if no tables specified
        if (empty($allTableIds)) {
            return;
        }

        // Skip if date/time not fully specified (other rules will catch this)
        if (!$date || !$timeFrom || !$timeTo) {
            return;
        }

        try {
            $timeSlot = TimeSlot::fromDateAndTimes($date, $timeFrom, $timeTo, $this->timezone);
        } catch (\Exception $e) {
            // Invalid time slot - let ValidTimeSlot rule handle this
            return;
        }

        // Check for conflicts
        $service = app(ReservationConflictService::class);
        $result = $service->validateNoConflict(
            $allTableIds,
            $timeSlot,
            $this->excludeId,
            $this->restaurantId
        );

        if (!$result['valid']) {
            $fail($result['message']);
        }
    }

    /**
     * Set the reservation ID to exclude.
     *
     * @param int|null $excludeId
     * @return static
     */
    public function exclude(?int $excludeId): static
    {
        $this->excludeId = $excludeId;
        return $this;
    }

    /**
     * Set the restaurant ID.
     *
     * @param int|null $restaurantId
     * @return static
     */
    public function forRestaurant(?int $restaurantId): static
    {
        $this->restaurantId = $restaurantId;
        return $this;
    }

    /**
     * Set the timezone.
     *
     * @param string $timezone
     * @return static
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }
}
