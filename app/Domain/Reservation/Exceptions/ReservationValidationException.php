<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

/**
 * Exception for reservation validation errors.
 *
 * Wraps business logic validation failures that aren't covered
 * by standard Laravel validation (e.g., capacity checks, time constraints).
 */
final class ReservationValidationException extends ReservationException
{
    protected string $errorCode = 'validation_error';
    protected int $httpStatus = 422;

    /**
     * Field that failed validation (if applicable).
     */
    public readonly ?string $field;

    /**
     * Validation rule that failed.
     */
    public readonly ?string $rule;

    private function __construct(
        string $message,
        ?string $field = null,
        ?string $rule = null
    ) {
        parent::__construct($message);

        $this->field = $field;
        $this->rule = $rule;

        $this->context = array_filter([
            'field' => $field,
            'rule' => $rule,
        ], fn($v) => $v !== null);
    }

    /**
     * Guest count exceeds table capacity.
     */
    public static function capacityExceeded(int $guests, int $capacity, array $tableNumbers = []): self
    {
        $tablesStr = !empty($tableNumbers)
            ? ' (стол(ы): ' . implode(', ', $tableNumbers) . ')'
            : '';

        $message = sprintf(
            'Количество гостей (%d) превышает вместимость стола (%d мест)%s.',
            $guests,
            $capacity,
            $tablesStr
        );

        $instance = new self($message, 'guests_count', 'max_capacity');
        $instance->context['guests_count'] = $guests;
        $instance->context['table_capacity'] = $capacity;
        $instance->context['table_numbers'] = $tableNumbers;

        return $instance;
    }

    /**
     * Reservation time is in the past.
     */
    public static function timeInPast(string $date, string $time): self
    {
        $message = sprintf(
            'Время бронирования %s %s уже прошло.',
            $date,
            $time
        );

        $instance = new self($message, 'time_from', 'future_time');
        $instance->context['date'] = $date;
        $instance->context['time'] = $time;

        return $instance;
    }

    /**
     * Duration is too short.
     */
    public static function durationTooShort(int $minutes, int $minimum): self
    {
        $message = sprintf(
            'Минимальная продолжительность бронирования — %d минут. Указано: %d минут.',
            $minimum,
            $minutes
        );

        $instance = new self($message, 'time_to', 'min_duration');
        $instance->context['duration_minutes'] = $minutes;
        $instance->context['minimum_minutes'] = $minimum;

        return $instance;
    }

    /**
     * Duration is too long.
     */
    public static function durationTooLong(int $minutes, int $maximum): self
    {
        $message = sprintf(
            'Максимальная продолжительность бронирования — %d минут (%d часов). Указано: %d минут.',
            $maximum,
            intdiv($maximum, 60),
            $minutes
        );

        $instance = new self($message, 'time_to', 'max_duration');
        $instance->context['duration_minutes'] = $minutes;
        $instance->context['maximum_minutes'] = $maximum;

        return $instance;
    }

    /**
     * Phone number is incomplete.
     */
    public static function incompletePhone(string $phone, int $expectedDigits = 11): self
    {
        $actualDigits = strlen(preg_replace('/\D/', '', $phone));

        $message = sprintf(
            'Номер телефона неполный. Введено %d цифр из %d.',
            $actualDigits,
            $expectedDigits
        );

        $instance = new self($message, 'guest_phone', 'complete_phone');
        $instance->context['actual_digits'] = $actualDigits;
        $instance->context['expected_digits'] = $expectedDigits;

        return $instance;
    }

    /**
     * Table does not exist.
     */
    public static function tableNotFound(int $tableId): self
    {
        $message = sprintf('Стол #%d не найден.', $tableId);

        $instance = new self($message, 'table_id', 'exists');
        $instance->context['table_id'] = $tableId;

        return $instance;
    }

    /**
     * Table is not active/available for booking.
     */
    public static function tableNotAvailable(int $tableId, ?string $tableName = null): self
    {
        $tableStr = $tableName ? "«{$tableName}»" : "#{$tableId}";
        $message = sprintf('Стол %s недоступен для бронирования.', $tableStr);

        $instance = new self($message, 'table_id', 'available');
        $instance->context['table_id'] = $tableId;
        $instance->context['table_name'] = $tableName;

        return $instance;
    }

    /**
     * Required field is missing.
     */
    public static function required(string $field, string $fieldLabel): self
    {
        $message = sprintf('Поле «%s» обязательно для заполнения.', $fieldLabel);

        return new self($message, $field, 'required');
    }

    /**
     * Date is too far in the future.
     */
    public static function dateTooFarAhead(string $date, int $maxDays): self
    {
        $message = sprintf(
            'Бронирование возможно максимум на %d дней вперёд. Выбрана дата: %s.',
            $maxDays,
            $date
        );

        $instance = new self($message, 'date', 'max_advance_days');
        $instance->context['date'] = $date;
        $instance->context['max_days'] = $maxDays;

        return $instance;
    }

    /**
     * Restaurant is closed at requested time.
     */
    public static function restaurantClosed(string $date, string $time): self
    {
        $message = sprintf(
            'Ресторан не работает в указанное время: %s %s.',
            $date,
            $time
        );

        $instance = new self($message, 'time_from', 'working_hours');
        $instance->context['date'] = $date;
        $instance->context['time'] = $time;

        return $instance;
    }

    /**
     * Generic validation error.
     */
    public static function withMessage(string $message, ?string $field = null): self
    {
        return new self($message, $field);
    }
}
