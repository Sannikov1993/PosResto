<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

/**
 * Event dispatched when an order is ready.
 *
 * Listeners can:
 * - Notify waiter/customer
 * - Update kitchen display
 * - Trigger delivery assignment
 */
final class OrderReady extends OrderEvent
{
    public function getEventName(): string
    {
        return 'order.ready';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s готов',
            $this->order->order_number
        );
    }
}
