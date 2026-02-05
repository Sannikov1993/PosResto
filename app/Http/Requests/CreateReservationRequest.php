<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Reservation\DTOs\CreateReservationData;
use App\Helpers\TimeHelper;
use App\Rules\ValidTimeSlot;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating a reservation.
 *
 * Validates input and provides typed DTO.
 *
 * Usage in controller:
 *   public function store(CreateReservationRequest $request)
 *   {
 *       $data = $request->toDTO();
 *       $reservation = $this->reservationService->create($data);
 *   }
 */
class CreateReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->getRestaurantId();
        $tz = TimeHelper::getTimezone($restaurantId);

        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'table_ids' => ['nullable', 'array'],
            'table_ids.*' => ['integer', 'exists:tables,id'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:100'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => [
                'required',
                'date_format:H:i',
                new ValidTimeSlot(
                    minMinutes: 30,
                    maxMinutes: 720,
                    dateField: 'date',
                    timeFromField: 'time_from',
                    timezone: $tz
                ),
            ],
            'guests_count' => ['required', 'integer', 'min:1', 'max:50'],
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
            'date.after_or_equal' => 'Дата бронирования не может быть в прошлом.',
            'guests_count.min' => 'Минимальное количество гостей: 1.',
            'guests_count.max' => 'Максимальное количество гостей: 50.',
            'table_id.exists' => 'Выбранный стол не существует.',
            'time_from.date_format' => 'Неверный формат времени начала.',
            'time_to.date_format' => 'Неверный формат времени окончания.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'table_id' => 'стол',
            'date' => 'дата',
            'time_from' => 'время начала',
            'time_to' => 'время окончания',
            'guests_count' => 'количество гостей',
            'guest_name' => 'имя гостя',
            'guest_phone' => 'телефон',
            'guest_email' => 'email',
            'notes' => 'заметки',
            'deposit' => 'депозит',
        ];
    }

    /**
     * Convert validated request to DTO.
     */
    public function toDTO(): CreateReservationData
    {
        $validated = $this->validated();

        return CreateReservationData::fromArray([
            'restaurant_id' => $this->getRestaurantId(),
            'table_id' => $validated['table_id'],
            'date' => $validated['date'],
            'time_from' => $validated['time_from'],
            'time_to' => $validated['time_to'],
            'guests_count' => $validated['guests_count'],
            'customer_name' => $validated['guest_name'] ?? null,
            'customer_phone' => $validated['guest_phone'] ?? null,
            'customer_email' => $validated['guest_email'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'deposit' => $validated['deposit'] ?? null,
            'linked_table_ids' => $this->getLinkedTableIds($validated),
            'source' => 'pos',
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Get restaurant ID from request context.
     */
    private function getRestaurantId(): int
    {
        // Try from route parameter
        if ($this->route('restaurant')) {
            return (int) $this->route('restaurant');
        }

        // Try from request input
        if ($this->has('restaurant_id')) {
            return (int) $this->input('restaurant_id');
        }

        // Try from authenticated user's current restaurant
        if (auth()->check() && method_exists(auth()->user(), 'getCurrentRestaurantId')) {
            return auth()->user()->getCurrentRestaurantId();
        }

        // Try from session
        if (session()->has('restaurant_id')) {
            return (int) session('restaurant_id');
        }

        // Default fallback
        return 1;
    }

    /**
     * Get linked table IDs from validated data.
     */
    private function getLinkedTableIds(array $validated): ?array
    {
        if (empty($validated['table_ids'])) {
            return null;
        }

        // Exclude main table from linked tables
        $mainTableId = $validated['table_id'];
        $linkedIds = array_values(array_diff($validated['table_ids'], [$mainTableId]));

        return !empty($linkedIds) ? $linkedIds : null;
    }
}
