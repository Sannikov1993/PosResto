<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Action: Confirm a new order.
 *
 * Transitions order from NEW to CONFIRMED status.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class ConfirmOrder
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanConfirm($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->update([
                'status' => OrderStatus::CONFIRMED->value,
                'confirmed_at' => now(),
            ]);

            $order->logStatus(OrderStatus::CONFIRMED->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ подтверждён',
            );

            OrderConfirmed::dispatch($result->order, $userId);

            return $result;
        });
    }
}
