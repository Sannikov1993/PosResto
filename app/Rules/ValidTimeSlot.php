<?php

namespace App\Rules;

use App\ValueObjects\TimeSlot;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a time slot (time_from/time_to with date) is valid.
 *
 * Checks:
 * - time_to is after time_from (handles midnight crossing)
 * - Duration is within acceptable bounds
 * - Start time is not in the past (for today's date)
 */
class ValidTimeSlot implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Minimum duration in minutes.
     */
    protected int $minMinutes;

    /**
     * Maximum duration in minutes.
     */
    protected int $maxMinutes;

    /**
     * Field name for the date.
     */
    protected string $dateField;

    /**
     * Field name for the start time.
     */
    protected string $timeFromField;

    /**
     * Timezone to use for validation.
     */
    protected string $timezone;

    /**
     * Whether to allow start time in the past.
     */
    protected bool $allowPast;

    /**
     * Create a new rule instance.
     *
     * @param int $minMinutes Minimum duration in minutes
     * @param int $maxMinutes Maximum duration in minutes
     * @param string $dateField Field name for the date
     * @param string $timeFromField Field name for the start time
     * @param string $timezone Timezone for validation
     * @param bool $allowPast Whether to allow start time in the past
     */
    public function __construct(
        int $minMinutes = TimeSlot::MIN_DURATION_MINUTES,
        int $maxMinutes = TimeSlot::MAX_DURATION_MINUTES,
        string $dateField = 'date',
        string $timeFromField = 'time_from',
        string $timezone = 'UTC',
        bool $allowPast = false
    ) {
        $this->minMinutes = $minMinutes;
        $this->maxMinutes = $maxMinutes;
        $this->dateField = $dateField;
        $this->timeFromField = $timeFromField;
        $this->timezone = $timezone;
        $this->allowPast = $allowPast;
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

        // Basic presence checks
        if (!$date || !$timeFrom || !$timeTo) {
            $fail('Дата и время должны быть указаны.');
            return;
        }

        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeFrom)) {
            $fail('Неверный формат времени начала.');
            return;
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeTo)) {
            $fail('Неверный формат времени окончания.');
            return;
        }

        try {
            $timeSlot = TimeSlot::fromDateAndTimes($date, $timeFrom, $timeTo, $this->timezone);
        } catch (\Exception $e) {
            $fail('Невозможно создать временной интервал: ' . $e->getMessage());
            return;
        }

        // Check duration
        $duration = $timeSlot->durationMinutes();

        if ($duration < $this->minMinutes) {
            $minHours = $this->minMinutes >= 60
                ? floor($this->minMinutes / 60) . ' ч'
                : $this->minMinutes . ' мин';
            $fail("Минимальная длительность бронирования — {$minHours}.");
            return;
        }

        if ($duration > $this->maxMinutes) {
            $maxHours = floor($this->maxMinutes / 60);
            $fail("Максимальная длительность бронирования — {$maxHours} часов.");
            return;
        }

        // Check if start time is in the past (only for new reservations)
        if (!$this->allowPast && $timeSlot->startsInPast()) {
            $fail('Время начала бронирования уже прошло.');
            return;
        }
    }

    /**
     * Create a rule that allows past times (for editing existing reservations).
     *
     * @return static
     */
    public function allowPastTimes(): static
    {
        $this->allowPast = true;
        return $this;
    }

    /**
     * Set the timezone for validation.
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
