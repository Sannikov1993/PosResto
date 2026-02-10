<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Events\OrderCompleted;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Complete an order.
 *
 * Transitions order to COMPLETED status.
 * Side-effects: frees table, updates customer stats.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class CompleteOrder
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanComplete($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->update([
                'status' => OrderStatus::COMPLETED->value,
                'completed_at' => now(),
                'delivered_at' => $order->type === OrderType::DELIVERY->value ? now() : null,
            ]);

            // Side-effect: free table
            $table = $order->table_id ? $order->table()->first() : null;
            if ($table instanceof Table) {
                $table->free();
            }

            // Side-effect: update customer stats
            if ($order->customer) {
                $order->customer->updateStats();
            }

            $order->logStatus(OrderStatus::COMPLETED->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ завершён',
            );

            OrderCompleted::dispatch($result->order, $userId);

            return $result;
        });
    }
}
