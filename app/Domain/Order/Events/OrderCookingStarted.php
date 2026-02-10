<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

/**
 * Event dispatched when an order starts cooking.
 *
 * Listeners can:
 * - Update kitchen display
 * - Start cooking timer
 * - Notify customer
 */
final class OrderCookingStarted extends OrderEvent
{
    public function getEventName(): string
    {
        return 'order.cooking_started';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Заказ #%s начал готовиться',
            $this->order->order_number
        );
    }
}
