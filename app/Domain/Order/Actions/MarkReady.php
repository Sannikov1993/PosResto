<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderReady;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Action: Mark order as ready.
 *
 * Transitions order from COOKING to READY status.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class MarkReady
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanMarkReady($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->update([
                'status' => OrderStatus::READY->value,
                'cooking_finished_at' => now(),
                'ready_at' => now(),
            ]);

            $order->logStatus(OrderStatus::READY->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ готов',
            );

            OrderReady::dispatch($result->order, $userId);

            return $result;
        });
    }
}
