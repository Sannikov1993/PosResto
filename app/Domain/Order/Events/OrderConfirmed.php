<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

/**
 * Event dispatched when an order is confirmed.
 *
 * Listeners can:
 * - Send confirmation notification
 * - Update kitchen display
 * - Log status change
 */
final class OrderConfirmed extends OrderEvent
{
    public function getEventName(): string
    {
        return 'order.confirmed';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s подтверждён',
            $this->order->order_number
        );
    }
}
