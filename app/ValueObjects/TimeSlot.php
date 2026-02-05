<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Value Object representing a time slot (interval) with proper midnight-crossing support.
 *
 * Handles reservations that span across midnight (e.g., 22:00 - 02:00) correctly.
 */
class TimeSlot implements JsonSerializable
{
    private Carbon $startsAt;
    private Carbon $endsAt;
    private string $timezone;

    /**
     * Minimum reservation duration in minutes.
     */
    public const MIN_DURATION_MINUTES = 30;

    /**
     * Maximum reservation duration in minutes (12 hours).
     */
    public const MAX_DURATION_MINUTES = 720;

    private function __construct(Carbon $startsAt, Carbon $endsAt, string $timezone = 'UTC')
    {
        $this->startsAt = $startsAt->copy();
        $this->endsAt = $endsAt->copy();
        $this->timezone = $timezone;
    }

    /**
     * Create TimeSlot from a date and separate time strings.
     * Automatically detects midnight crossing when time_to < time_from.
     *
     * @param string|DateTimeInterface $date Base date (YYYY-MM-DD or DateTime)
     * @param string $timeFrom Start time (HH:MM or HH:MM:SS)
     * @param string $timeTo End time (HH:MM or HH:MM:SS)
     * @param string $timezone Timezone for interpretation
     * @return static
     * @throws InvalidArgumentException
     */
    public static function fromDateAndTimes(
        string|DateTimeInterface $date,
        string $timeFrom,
        string $timeTo,
        string $timezone = 'UTC'
    ): static {
        // Parse the base date
        if ($date instanceof DateTimeInterface) {
            $baseDate = Carbon::instance($date)->setTimezone($timezone)->startOfDay();
        } else {
            $baseDate = Carbon::parse($date, $timezone)->startOfDay();
        }

        // Normalize time strings to H:i:s
        $timeFrom = self::normalizeTimeString($timeFrom);
        $timeTo = self::normalizeTimeString($timeTo);

        // Parse times
        [$fromHour, $fromMin, $fromSec] = array_map('intval', explode(':', $timeFrom));
        [$toHour, $toMin, $toSec] = array_map('intval', explode(':', $timeTo));

        // Create start datetime
        $startsAt = $baseDate->copy()->setTime($fromHour, $fromMin, $fromSec);

        // Create end datetime - add a day if it crosses midnight
        $endsAt = $baseDate->copy()->setTime($toHour, $toMin, $toSec);

        // Detect midnight crossing: if end time is less than or equal to start time
        $fromMinutes = $fromHour * 60 + $fromMin;
        $toMinutes = $toHour * 60 + $toMin;

        if ($toMinutes <= $fromMinutes) {
            // Crosses midnight - end time is on the next day
            $endsAt->addDay();
        }

        return new static($startsAt, $endsAt, $timezone);
    }

    /**
     * Create TimeSlot from two datetime objects.
     *
     * @param DateTimeInterface|string $startsAt Start datetime
     * @param DateTimeInterface|string $endsAt End datetime
     * @param string|null $timezone Optional timezone (defaults to starts_at timezone)
     * @return static
     */
    public static function fromDatetimes(
        DateTimeInterface|string $startsAt,
        DateTimeInterface|string $endsAt,
        ?string $timezone = null
    ): static {
        $start = $startsAt instanceof DateTimeInterface
            ? Carbon::instance($startsAt)
            : Carbon::parse($startsAt);

        $end = $endsAt instanceof DateTimeInterface
            ? Carbon::instance($endsAt)
            : Carbon::parse($endsAt);

        $tz = $timezone ?? $start->timezone->getName();

        if ($end->lte($start)) {
            throw new InvalidArgumentException('End time must be after start time');
        }

        return new static($start, $end, $tz);
    }

    /**
     * Create TimeSlot from start datetime and duration.
     *
     * @param DateTimeInterface|string $startsAt Start datetime
     * @param int $durationMinutes Duration in minutes
     * @param string|null $timezone Optional timezone
     * @return static
     */
    public static function fromStartAndDuration(
        DateTimeInterface|string $startsAt,
        int $durationMinutes,
        ?string $timezone = null
    ): static {
        $start = $startsAt instanceof DateTimeInterface
            ? Carbon::instance($startsAt)
            : Carbon::parse($startsAt);

        $tz = $timezone ?? $start->timezone->getName();
        $end = $start->copy()->addMinutes($durationMinutes);

        return new static($start, $end, $tz);
    }

    /**
     * Check if this time slot overlaps with another.
     *
     * Two intervals overlap if: start1 < end2 AND start2 < end1
     *
     * @param TimeSlot $other The other time slot
     * @return bool
     */
    public function overlaps(TimeSlot $other): bool
    {
        // Convert both to UTC for comparison
        $thisStart = $this->startsAt->copy()->utc();
        $thisEnd = $this->endsAt->copy()->utc();
        $otherStart = $other->startsAt->copy()->utc();
        $otherEnd = $other->endsAt->copy()->utc();

        return $thisStart->lt($otherEnd) && $otherStart->lt($thisEnd);
    }

    /**
     * Check if this time slot contains a specific moment in time.
     *
     * @param DateTimeInterface|string $datetime The datetime to check
     * @return bool
     */
    public function contains(DateTimeInterface|string $datetime): bool
    {
        $dt = $datetime instanceof DateTimeInterface
            ? Carbon::instance($datetime)->utc()
            : Carbon::parse($datetime)->utc();

        $start = $this->startsAt->copy()->utc();
        $end = $this->endsAt->copy()->utc();

        return $dt->gte($start) && $dt->lt($end);
    }

    /**
     * Check if this time slot crosses midnight.
     *
     * @return bool
     */
    public function crossesMidnight(): bool
    {
        // Get dates in the original timezone
        $startDate = $this->startsAt->copy()->setTimezone($this->timezone)->startOfDay();
        $endDate = $this->endsAt->copy()->setTimezone($this->timezone)->startOfDay();

        return !$startDate->equalTo($endDate);
    }

    /**
     * Get duration in minutes.
     *
     * @return int
     */
    public function durationMinutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }

    /**
     * Get duration as human-readable string.
     *
     * @return string
     */
    public function durationForHumans(): string
    {
        $minutes = $this->durationMinutes();
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins}м";
        }
        if ($mins === 0) {
            return "{$hours}ч";
        }
        return "{$hours}ч {$mins}м";
    }

    /**
     * Convert to UTC timezone.
     *
     * @return static
     */
    public function toUtc(): static
    {
        return new static(
            $this->startsAt->copy()->utc(),
            $this->endsAt->copy()->utc(),
            'UTC'
        );
    }

    /**
     * Convert to a specific timezone.
     *
     * @param string $timezone Target timezone
     * @return static
     */
    public function toTimezone(string $timezone): static
    {
        return new static(
            $this->startsAt->copy()->setTimezone($timezone),
            $this->endsAt->copy()->setTimezone($timezone),
            $timezone
        );
    }

    /**
     * Get the start datetime.
     *
     * @return Carbon
     */
    public function startsAt(): Carbon
    {
        return $this->startsAt->copy();
    }

    /**
     * Get the end datetime.
     *
     * @return Carbon
     */
    public function endsAt(): Carbon
    {
        return $this->endsAt->copy();
    }

    /**
     * Get the timezone.
     *
     * @return string
     */
    public function timezone(): string
    {
        return $this->timezone;
    }

    // ========== Legacy Support Methods ==========

    /**
     * Get the date portion (YYYY-MM-DD) of the start time.
     *
     * @return string
     */
    public function getDate(): string
    {
        return $this->startsAt->copy()->setTimezone($this->timezone)->format('Y-m-d');
    }

    /**
     * Get the start time (HH:MM) portion.
     *
     * @return string
     */
    public function getTimeFrom(): string
    {
        return $this->startsAt->copy()->setTimezone($this->timezone)->format('H:i');
    }

    /**
     * Get the end time (HH:MM) portion.
     *
     * @return string
     */
    public function getTimeTo(): string
    {
        return $this->endsAt->copy()->setTimezone($this->timezone)->format('H:i');
    }

    /**
     * Get the end date (YYYY-MM-DD) - useful when crossing midnight.
     *
     * @return string
     */
    public function getEndDate(): string
    {
        return $this->endsAt->copy()->setTimezone($this->timezone)->format('Y-m-d');
    }

    /**
     * Get formatted time range string (e.g., "19:00 - 23:00" or "22:00 - 02:00 (+1)").
     *
     * @return string
     */
    public function getTimeRange(): string
    {
        $from = $this->getTimeFrom();
        $to = $this->getTimeTo();

        if ($this->crossesMidnight()) {
            return "{$from} - {$to} (+1)";
        }

        return "{$from} - {$to}";
    }

    // ========== Validation Methods ==========

    /**
     * Check if the duration is within valid bounds.
     *
     * @param int|null $minMinutes Minimum duration (default: MIN_DURATION_MINUTES)
     * @param int|null $maxMinutes Maximum duration (default: MAX_DURATION_MINUTES)
     * @return bool
     */
    public function isValidDuration(?int $minMinutes = null, ?int $maxMinutes = null): bool
    {
        $duration = $this->durationMinutes();
        $min = $minMinutes ?? self::MIN_DURATION_MINUTES;
        $max = $maxMinutes ?? self::MAX_DURATION_MINUTES;

        return $duration >= $min && $duration <= $max;
    }

    /**
     * Check if the time slot is in the past.
     *
     * @return bool
     */
    public function isPast(): bool
    {
        return $this->endsAt->copy()->utc()->isPast();
    }

    /**
     * Check if the time slot starts in the past.
     *
     * @return bool
     */
    public function startsInPast(): bool
    {
        return $this->startsAt->copy()->utc()->isPast();
    }

    // ========== Comparison Methods ==========

    /**
     * Check if this time slot is equal to another.
     *
     * @param TimeSlot $other
     * @return bool
     */
    public function equals(TimeSlot $other): bool
    {
        return $this->startsAt->equalTo($other->startsAt)
            && $this->endsAt->equalTo($other->endsAt);
    }

    /**
     * Check if this time slot starts before another.
     *
     * @param TimeSlot $other
     * @return bool
     */
    public function startsBefore(TimeSlot $other): bool
    {
        return $this->startsAt->lt($other->startsAt);
    }

    // ========== Utility Methods ==========

    /**
     * Normalize a time string to HH:MM:SS format.
     *
     * @param string $time
     * @return string
     */
    private static function normalizeTimeString(string $time): string
    {
        $parts = explode(':', $time);
        $hour = str_pad($parts[0] ?? '0', 2, '0', STR_PAD_LEFT);
        $min = str_pad($parts[1] ?? '0', 2, '0', STR_PAD_LEFT);
        $sec = str_pad($parts[2] ?? '0', 2, '0', STR_PAD_LEFT);

        return "{$hour}:{$min}:{$sec}";
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'timezone' => $this->timezone,
            'duration_minutes' => $this->durationMinutes(),
            'crosses_midnight' => $this->crossesMidnight(),
            // Legacy format
            'date' => $this->getDate(),
            'time_from' => $this->getTimeFrom(),
            'time_to' => $this->getTimeTo(),
        ];
    }

    /**
     * JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getDate() . ' ' . $this->getTimeRange();
    }
}
