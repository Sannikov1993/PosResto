<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * DTO for creating a new reservation.
 *
 * Encapsulates all data needed to create a reservation.
 * Provides validation rules and request hydration.
 *
 * Usage:
 *   $data = CreateReservationData::fromRequest($request);
 *   $reservation = $reservationService->create($data);
 */
final class CreateReservationData extends ReservationData
{
    public function __construct(
        public readonly int $restaurantId,
        public readonly int $tableId,
        public readonly string $date,
        public readonly string $timeFrom,
        public readonly string $timeTo,
        public readonly int $guestsCount,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly ?int $customerId = null,
        public readonly ?string $notes = null,
        public readonly ?string $source = null,
        public readonly ?float $deposit = null,
        public readonly ?array $linkedTableIds = null,
        public readonly ?array $preorderItems = null,
        public readonly ?string $status = 'pending',
        public readonly ?int $createdBy = null,
    ) {}

    /**
     * Create from HTTP request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static(
            restaurantId: (int) $request->input('restaurant_id'),
            tableId: (int) $request->input('table_id'),
            date: $request->input('date'),
            timeFrom: $request->input('time_from'),
            timeTo: $request->input('time_to'),
            guestsCount: (int) $request->input('guests_count'),
            customerName: $request->input('customer_name'),
            customerPhone: $request->input('customer_phone'),
            customerEmail: $request->input('customer_email'),
            customerId: $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            notes: $request->input('notes'),
            source: $request->input('source', 'pos'),
            deposit: $request->filled('deposit') ? (float) $request->input('deposit') : null,
            linkedTableIds: $request->input('linked_table_ids'),
            preorderItems: $request->input('preorder_items'),
            status: $request->input('status', 'pending'),
            createdBy: auth()->id(),
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            restaurantId: (int) $data['restaurant_id'],
            tableId: (int) $data['table_id'],
            date: $data['date'],
            timeFrom: $data['time_from'],
            timeTo: $data['time_to'],
            guestsCount: (int) $data['guests_count'],
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            notes: $data['notes'] ?? null,
            source: $data['source'] ?? 'pos',
            deposit: isset($data['deposit']) ? (float) $data['deposit'] : null,
            linkedTableIds: $data['linked_table_ids'] ?? null,
            preorderItems: $data['preorder_items'] ?? null,
            status: $data['status'] ?? 'pending',
            createdBy: $data['created_by'] ?? null,
        );
    }

    /**
     * Validation rules.
     */
    public static function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'in:pos,web,phone,app'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'linked_table_ids' => ['nullable', 'array'],
            'linked_table_ids.*' => ['integer', 'exists:tables,id'],
            'preorder_items' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:pending,confirmed'],
        ];
    }

    /**
     * Validation messages.
     */
    public static function messages(): array
    {
        return [
            'date.after_or_equal' => 'Дата бронирования не может быть в прошлом.',
            'guests_count.min' => 'Минимальное количество гостей: 1.',
            'guests_count.max' => 'Максимальное количество гостей: 50.',
            'table_id.exists' => 'Выбранный стол не существует.',
        ];
    }

    /**
     * Attribute names.
     */
    public static function attributes(): array
    {
        return [
            'restaurant_id' => 'ресторан',
            'table_id' => 'стол',
            'date' => 'дата',
            'time_from' => 'время начала',
            'time_to' => 'время окончания',
            'guests_count' => 'количество гостей',
            'customer_name' => 'имя гостя',
            'customer_phone' => 'телефон',
            'customer_email' => 'email',
            'notes' => 'заметки',
            'deposit' => 'депозит',
        ];
    }

    /**
     * Get reservation date as Carbon instance.
     */
    public function getDate(): Carbon
    {
        return Carbon::parse($this->date);
    }

    /**
     * Get start datetime.
     */
    public function getStartDateTime(): Carbon
    {
        return Carbon::parse("{$this->date} {$this->timeFrom}");
    }

    /**
     * Get end datetime.
     */
    public function getEndDateTime(): Carbon
    {
        $endDate = $this->date;

        // Handle overnight reservations
        if ($this->timeTo < $this->timeFrom) {
            $endDate = Carbon::parse($this->date)->addDay()->toDateString();
        }

        return Carbon::parse("{$endDate} {$this->timeTo}");
    }

    /**
     * Check if this is an overnight reservation.
     */
    public function isOvernight(): bool
    {
        return $this->timeTo < $this->timeFrom;
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutes(): int
    {
        return (int) $this->getStartDateTime()->diffInMinutes($this->getEndDateTime());
    }

    /**
     * Check if deposit is required.
     */
    public function hasDeposit(): bool
    {
        return $this->deposit !== null && $this->deposit > 0;
    }

    /**
     * Get all table IDs (main + linked).
     */
    public function getAllTableIds(): array
    {
        $ids = [$this->tableId];

        if (!empty($this->linkedTableIds)) {
            $ids = array_merge($ids, $this->linkedTableIds);
        }

        return array_unique($ids);
    }

    /**
     * Convert to model attributes array.
     */
    public function toModelAttributes(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'table_id' => $this->tableId,
            'date' => $this->date,
            'time_from' => $this->timeFrom,
            'time_to' => $this->timeTo,
            'guests_count' => $this->guestsCount,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'customer_id' => $this->customerId,
            'notes' => $this->notes,
            'source' => $this->source ?? 'pos',
            'deposit' => $this->deposit ?? 0,
            'deposit_status' => $this->hasDeposit() ? 'pending' : null,
            'linked_table_ids' => $this->linkedTableIds,
            'status' => $this->status ?? 'pending',
            'created_by' => $this->createdBy,
        ];
    }
}
