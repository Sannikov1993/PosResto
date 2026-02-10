<?php

declare(strict_types=1);

namespace App\Domain\Order\DTOs;

/**
 * Base abstract DTO for order data.
 *
 * Provides common functionality for all order DTOs.
 */
abstract class OrderData
{
    /**
     * Create DTO from request.
     */
    abstract public static function fromRequest(\Illuminate\Http\Request $request): static;

    /**
     * Create DTO from array.
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            fn($v) => $v !== null
        );
    }
}
