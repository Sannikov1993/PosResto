<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderDelivering;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Action: Start delivering an order.
 *
 * Transitions order from READY to DELIVERING status.
 * Assigns courier and records pickup time.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class StartDelivering
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $courierId = null,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanStartDelivering($order);

        return DB::transaction(function () use ($order, $courierId, $userId) {
            $order->update([
                'status' => OrderStatus::DELIVERING->value,
                'courier_id' => $courierId ?? $order->courier_id,
                'picked_up_at' => now(),
            ]);

            $order->logStatus(OrderStatus::DELIVERING->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ передан курьеру',
            );

            OrderDelivering::dispatch($result->order, $courierId, $userId);

            return $result;
        });
    }
}
