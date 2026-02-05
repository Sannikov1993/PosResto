<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * DTO for updating an existing reservation.
 *
 * All fields are optional - only provided fields will be updated.
 * Provides validation rules that account for the existing reservation.
 *
 * Usage:
 *   $data = UpdateReservationData::fromRequest($request);
 *   $reservation = $reservationService->update($reservation, $data);
 */
final class UpdateReservationData extends ReservationData
{
    public function __construct(
        public readonly ?int $tableId = null,
        public readonly ?string $date = null,
        public readonly ?string $timeFrom = null,
        public readonly ?string $timeTo = null,
        public readonly ?int $guestsCount = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly ?int $customerId = null,
        public readonly ?string $notes = null,
        public readonly ?float $deposit = null,
        public readonly ?array $linkedTableIds = null,
        public readonly ?array $preorderItems = null,
        public readonly ?int $updatedBy = null,
    ) {}

    /**
     * Create from HTTP request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static(
            tableId: $request->filled('table_id') ? (int) $request->input('table_id') : null,
            date: $request->input('date'),
            timeFrom: $request->input('time_from'),
            timeTo: $request->input('time_to'),
            guestsCount: $request->filled('guests_count') ? (int) $request->input('guests_count') : null,
            customerName: $request->input('customer_name'),
            customerPhone: $request->input('customer_phone'),
            customerEmail: $request->input('customer_email'),
            customerId: $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            notes: $request->input('notes'),
            deposit: $request->filled('deposit') ? (float) $request->input('deposit') : null,
            linkedTableIds: $request->input('linked_table_ids'),
            preorderItems: $request->input('preorder_items'),
            updatedBy: auth()->id(),
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            tableId: isset($data['table_id']) ? (int) $data['table_id'] : null,
            date: $data['date'] ?? null,
            timeFrom: $data['time_from'] ?? null,
            timeTo: $data['time_to'] ?? null,
            guestsCount: isset($data['guests_count']) ? (int) $data['guests_count'] : null,
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            notes: $data['notes'] ?? null,
            deposit: isset($data['deposit']) ? (float) $data['deposit'] : null,
            linkedTableIds: $data['linked_table_ids'] ?? null,
            preorderItems: $data['preorder_items'] ?? null,
            updatedBy: $data['updated_by'] ?? null,
        );
    }

    /**
     * Validation rules.
     */
    public static function rules(): array
    {
        return [
            'table_id' => ['sometimes', 'integer', 'exists:tables,id'],
            'date' => ['sometimes', 'date'],
            'time_from' => ['sometimes', 'date_format:H:i'],
            'time_to' => ['sometimes', 'date_format:H:i'],
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'deposit' => ['sometimes', 'numeric', 'min:0'],
            'linked_table_ids' => ['nullable', 'array'],
            'linked_table_ids.*' => ['integer', 'exists:tables,id'],
            'preorder_items' => ['nullable', 'array'],
        ];
    }

    /**
     * Validation messages.
     */
    public static function messages(): array
    {
        return [
            'guests_count.min' => 'Минимальное количество гостей: 1.',
            'guests_count.max' => 'Максимальное количество гостей: 50.',
            'table_id.exists' => 'Выбранный стол не существует.',
        ];
    }

    /**
     * Check if any data was provided.
     */
    public function hasChanges(): bool
    {
        return !empty($this->toArray());
    }

    /**
     * Check if time slot is being changed.
     */
    public function isChangingTimeSlot(): bool
    {
        return $this->date !== null
            || $this->timeFrom !== null
            || $this->timeTo !== null;
    }

    /**
     * Check if table is being changed.
     */
    public function isChangingTable(): bool
    {
        return $this->tableId !== null || $this->linkedTableIds !== null;
    }

    /**
     * Merge with existing reservation data.
     *
     * Returns complete time slot data using existing values for missing fields.
     */
    public function mergeWithReservation(Reservation $reservation): array
    {
        $date = $this->date ?? ($reservation->date?->format('Y-m-d'));

        return [
            'table_id' => $this->tableId ?? $reservation->table_id,
            'date' => $date,
            'time_from' => $this->timeFrom ?? $reservation->time_from,
            'time_to' => $this->timeTo ?? $reservation->time_to,
            'guests_count' => $this->guestsCount ?? $reservation->guests_count,
            'linked_table_ids' => $this->linkedTableIds ?? $reservation->linked_table_ids,
        ];
    }

    /**
     * Get only changed fields for model update.
     */
    public function toModelAttributes(): array
    {
        $attributes = [];

        if ($this->tableId !== null) {
            $attributes['table_id'] = $this->tableId;
        }
        if ($this->date !== null) {
            $attributes['date'] = $this->date;
        }
        if ($this->timeFrom !== null) {
            $attributes['time_from'] = $this->timeFrom;
        }
        if ($this->timeTo !== null) {
            $attributes['time_to'] = $this->timeTo;
        }
        if ($this->guestsCount !== null) {
            $attributes['guests_count'] = $this->guestsCount;
        }
        if ($this->customerName !== null) {
            $attributes['customer_name'] = $this->customerName;
        }
        if ($this->customerPhone !== null) {
            $attributes['customer_phone'] = $this->customerPhone;
        }
        if ($this->customerEmail !== null) {
            $attributes['customer_email'] = $this->customerEmail;
        }
        if ($this->customerId !== null) {
            $attributes['customer_id'] = $this->customerId;
        }
        if ($this->notes !== null) {
            $attributes['notes'] = $this->notes;
        }
        if ($this->deposit !== null) {
            $attributes['deposit'] = $this->deposit;
        }
        if ($this->linkedTableIds !== null) {
            $attributes['linked_table_ids'] = $this->linkedTableIds;
        }
        if ($this->updatedBy !== null) {
            $attributes['updated_by'] = $this->updatedBy;
        }

        return $attributes;
    }
}
