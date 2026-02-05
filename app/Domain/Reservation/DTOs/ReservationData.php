<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/**
 * Base class for reservation DTOs.
 *
 * Provides common functionality:
 * - Array conversion
 * - Request hydration
 * - Validation helpers
 */
abstract class ReservationData implements Arrayable
{
    /**
     * Create DTO from HTTP request.
     */
    abstract public static function fromRequest(Request $request): static;

    /**
     * Create DTO from array.
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Get validation rules.
     *
     * @return array<string, mixed>
     */
    abstract public static function rules(): array;

    /**
     * Get validation messages.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }

    /**
     * Get attribute names for validation.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [];
    }

    /**
     * Convert to array, excluding null values.
     */
    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            fn($value) => $value !== null
        );
    }

    /**
     * Convert to array, including null values.
     */
    public function toArrayWithNulls(): array
    {
        return get_object_vars($this);
    }
}
