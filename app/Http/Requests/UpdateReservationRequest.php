<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reservation\DTOs\UpdateReservationData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating a reservation.
 *
 * All fields are optional - only provided fields will be updated.
 */
class UpdateReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reservations.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['sometimes', 'integer', 'exists:tables,id'],
            'guest_name' => ['sometimes', 'string', 'max:100'],
            'guest_phone' => ['sometimes', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:100'],
            'date' => ['sometimes', 'date'],
            'time_from' => ['sometimes', 'date_format:H:i'],
            'time_to' => ['sometimes', 'date_format:H:i'],
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'special_requests' => ['nullable', 'string', 'max:500'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guests_count.min' => 'Минимальное количество гостей: 1.',
            'guests_count.max' => 'Максимальное количество гостей: 50.',
            'table_id.exists' => 'Выбранный стол не существует.',
        ];
    }

    /**
     * Convert validated request to DTO.
     */
    public function toDTO(): UpdateReservationData
    {
        $validated = $this->validated();

        return UpdateReservationData::fromArray([
            'table_id' => $validated['table_id'] ?? null,
            'date' => $validated['date'] ?? null,
            'time_from' => $validated['time_from'] ?? null,
            'time_to' => $validated['time_to'] ?? null,
            'guests_count' => $validated['guests_count'] ?? null,
            'customer_name' => $validated['guest_name'] ?? null,
            'customer_phone' => $validated['guest_phone'] ?? null,
            'customer_email' => $validated['guest_email'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'deposit' => $validated['deposit'] ?? null,
            'updated_by' => auth()->id(),
        ]);
    }
}
