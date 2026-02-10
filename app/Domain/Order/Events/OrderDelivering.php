<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

use App\Models\Order;

/**
 * Event dispatched when an order starts delivering.
 *
 * Listeners can:
 * - Notify customer about courier
 * - Start delivery tracking
 * - Update delivery dashboard
 */
final class OrderDelivering extends OrderEvent
{
    public ?int $courierId;

    public function __construct(
        Order $order,
        ?int $courierId = null,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($order, $userId, $metadata);
        $this->courierId = $courierId;
    }

    public function getEventName(): string
    {
        return 'order.delivering';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s передан курьеру',
            $this->order->order_number
        );
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'courier_id' => $this->courierId,
        ]);
    }
}
