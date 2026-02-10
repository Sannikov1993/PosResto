<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

/**
 * Event dispatched when an order is completed.
 *
 * Listeners can:
 * - Free table
 * - Update customer stats
 * - Send completion notification
 * - Update analytics
 */
final class OrderCompleted extends OrderEvent
{
    public function getEventName(): string
    {
        return 'order.completed';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s завершён',
            $this->order->order_number
        );
    }
}
