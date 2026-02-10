<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderCancelled;
use App\Domain\Order\StateMachine\OrderStateMachine;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

/**
 * Action: Cancel an order.
 *
 * Transitions order to CANCELLED status.
 * Side-effect: frees table.
 *
 * @throws \App\Domain\Order\Exceptions\InvalidOrderStateTransitionException
 */
final class CancelOrder
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(
        Order $order,
        ?string $reason = null,
        ?int $userId = null,
    ): OrderActionResult {
        $this->stateMachine->assertCanCancel($order);

        return DB::transaction(function () use ($order, $reason, $userId) {
            $order->update([
                'status' => OrderStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $userId,
            ]);

            // Side-effect: free table
            $table = $order->table_id ? $order->table()->first() : null;
            if ($table instanceof Table) {
                $table->free();
            }

            $order->logStatus(OrderStatus::CANCELLED->value, $reason);

            $result = OrderActionResult::success(
                order: $order->fresh(),
                message: 'Заказ отменён',
                metadata: [
                    'reason' => $reason,
                ],
            );

            OrderCancelled::dispatch($result->order, $reason, $userId);

            return $result;
        });
    }
}
