<?php

declare(strict_types=1);

namespace App\Domain\Order\Events;

use App\Models\Order;

/**
 * Event dispatched when an order is cancelled.
 *
 * Listeners can:
 * - Free table
 * - Process refund
 * - Send cancellation notification
 * - Update analytics
 */
final class OrderCancelled extends OrderEvent
{
    public ?string $reason;

    public function __construct(
        Order $order,
        ?string $reason = null,
        ?int $userId = null,
        array $metadata = [],
    ) {
        parent::__construct($order, $userId, $metadata);
        $this->reason = $reason;
    }

    public function getEventName(): string
    {
        return 'order.cancelled';
    }

    public function getDescription(): string
    {
        $desc = sprintf('Заказ #%s отменён', $this->order->order_number);

        if ($this->reason) {
            $desc .= ": {$this->reason}";
        }

        return $desc;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'reason' => $this->reason,
        ]);
    }
}
