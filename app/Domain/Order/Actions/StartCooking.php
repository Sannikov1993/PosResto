<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderCookingStarted;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Start cooking an order.
 *
 * Transitions order from NEW/CONFIRMED to COOKING status.
 * Side-effect: occupies the table.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class StartCooking
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanStartCooking($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->update([
                'status' => OrderStatus::COOKING->value,
                'cooking_started_at' => now(),
            ]);

            // Side-effect: occupy table
            $table = $order->table_id ? $order->table()->first() : null;
            if ($table instanceof Table) {
                $table->occupy();
            }

            $order->logStatus(OrderStatus::COOKING->value);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ готовится',
            );

            OrderCookingStarted::dispatch($result->order, $userId);

            return $result;
        });
    }
}
