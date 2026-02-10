<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all order domain events.
 *
 * Provides common functionality:
 * - Order model access
 * - User tracking
 * - Metadata storage
 * - Serialization for queued listeners
 */
abstract class OrderEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Order $order;
    public ?int $userId;
    public array $metadata;

    public function __construct(
        Order $order,
        ?int $userId = null,
        array $metadata = [],
    ) {
        $this->order = $order;
        $this->userId = $userId;
        $this->metadata = $metadata;
    }

    /**
     * Get the event name for logging/debugging.
     */
    abstract public function getEventName(): string;

    /**
     * Get human-readable description.
     */
    abstract public function getDescription(): string;

    /**
     * Convert to array for logging.
     */
    public function toArray(): array
    {
        return [
            'event' => $this->getEventName(),
            'order_id' => $this->order->id,
            'restaurant_id' => $this->order->restaurant_id,
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
