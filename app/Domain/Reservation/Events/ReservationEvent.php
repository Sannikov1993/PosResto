<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all reservation domain events.
 *
 * Provides common functionality:
 * - Reservation model access
 * - User tracking
 * - Metadata storage
 * - Serialization for queued listeners
 */
abstract class ReservationEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Reservation $reservation;
    public ?int $userId;
    public array $metadata;

    /**
     * Create a new event instance.
     *
     * @param Reservation $reservation The reservation involved
     * @param int|null $userId User who triggered the event
     * @param array $metadata Additional event data
     */
    public function __construct(
        Reservation $reservation,
        ?int $userId = null,
        array $metadata = [],
    ) {
        $this->reservation = $reservation;
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
            'reservation_id' => $this->reservation->id,
            'restaurant_id' => $this->reservation->restaurant_id,
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
