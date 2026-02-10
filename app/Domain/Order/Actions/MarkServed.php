<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderServed;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Action: Mark order as served.
 *
 * Transitions order from READY to SERVED status.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class MarkServed
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanMarkServed($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->update([
                'status' => OrderStatus::SERVED->value,
            ]);

            $order->logStatus(OrderStatus::SERVED->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ подан',
            );

            OrderServed::dispatch($result->order, $userId);

            return $result;
        });
    }
}
