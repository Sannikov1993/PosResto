<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

/**
 * Event dispatched when an order is served to the guest.
 *
 * Listeners can:
 * - Update table status
 * - Log serving time
 */
final class OrderServed extends OrderEvent
{
    public function getEventName(): string
    {
        return 'order.served';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s подан',
            $this->order->order_number
        );
    }
}
