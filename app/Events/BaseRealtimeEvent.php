<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Базовый класс для всех real-time событий
 *
 * Все события наследуют этот класс и определяют свои каналы
 */
abstract class BaseRealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public string $eventType,
        public array $data = [],
        public ?int $userId = null
    ) {}

    /**
     * Каналы, на которые отправляется событие
     */
    abstract public function broadcastOn(): array;

    /**
     * Имя события (используется на клиенте для подписки)
     */
    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    /**
     * Данные, отправляемые с событием
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->eventType,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
            'user_id' => $this->userId,
        ];
    }
}
