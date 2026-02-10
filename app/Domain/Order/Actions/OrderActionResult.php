<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Models\Order;

/**
 * Base result class for order actions.
 *
 * Provides a consistent return type for all actions.
 */
class OrderActionResult
{
    public function __construct(
        public readonly Order $order,
        public readonly bool $success = true,
        public readonly ?string $message = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        Order $order,
        ?string $message = null,
        array $metadata = []
    ): static {
        return new static($order, true, $message, $metadata);
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'message' => $this->message,
            'order' => $this->order->toArray(),
            ...$this->metadata,
        ], fn($v) => $v !== null);
    }
}
