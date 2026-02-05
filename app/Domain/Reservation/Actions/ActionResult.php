<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Base result class for reservation actions.
 *
 * Provides a consistent return type for all actions.
 */
class ActionResult
{
    public function __construct(
        public readonly Reservation $reservation,
        public readonly bool $success = true,
        public readonly ?string $message = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        Reservation $reservation,
        ?string $message = null,
        array $metadata = []
    ): static {
        return new static($reservation, true, $message, $metadata);
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'message' => $this->message,
            'reservation' => $this->reservation->toArray(),
            ...$this->metadata,
        ], fn($v) => $v !== null);
    }
}
