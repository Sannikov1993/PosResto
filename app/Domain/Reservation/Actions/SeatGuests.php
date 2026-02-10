<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Events\DepositTransferred;
use App\Domain\Reservation\Events\ReservationSeated;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\TableOccupiedException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Action: Seat guests for a reservation.
 *
 * Transitions reservation to SEATED status and updates table statuses.
 * Optionally creates an order and transfers deposit.
 *
 * Usage:
 *   $action = new SeatGuests($stateMachine);
 *   $result = $action->execute($reservation, createOrder: true);
 *
 * @throws InvalidStateTransitionException If reservation cannot be seated
 * @throws TableOccupiedException If tables are already occupied
 */
final class SeatGuests
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
    ) {}

    /**
     * Execute the seat guests action.
     *
     * @param Reservation $reservation Reservation to seat
     * @param bool $createOrder Whether to create an order
     * @param int|null $userId User performing the action
     * @param bool $transferDeposit Whether to transfer deposit to order
     * @param int|null $guestsCount Override guests count for order
     *
     * @throws InvalidStateTransitionException
     * @throws TableOccupiedException
     */
    public function execute(
        Reservation $reservation,
        bool $createOrder = true,
        ?int $userId = null,
        bool $transferDeposit = true,
        ?int $guestsCount = null,
    ): SeatGuestsResult {
        // 1. Validate state transition
        $this->stateMachine->assertCanSeat($reservation);

        // 2. Execute in transaction
        return DB::transaction(function () use (
            $reservation,
            $createOrder,
            $userId,
            $transferDeposit,
            $guestsCount
        ) {
            // 3. Lock and validate tables
            $tables = $this->lockAndValidateTables($reservation);

            // 4. Update reservation status
            $reservation->update([
                'status' => ReservationStatus::SEATED->value,
                'seated_at' => now(),
                'seated_by' => $userId,
            ]);

            // 5. Update all tables to occupied
            Table::whereIn('id', $tables->pluck('id'))
                ->update(['status' => 'occupied']);

            // Refresh tables to get updated status
            $tables = $tables->fresh();

            // 6. Create order if requested
            $order = null;
            $depositTransferred = false;

            if ($createOrder) {
                $order = $this->createOrder(
                    $reservation,
                    $tables,
                    $guestsCount ?? $reservation->guests_count,
                    $userId
                );

                // 6b. Перенести позиции предзаказа в новый заказ
                $this->mergePreorderItems($reservation, $order);

                // 7. Transfer deposit if applicable
                if ($transferDeposit && $this->canTransferDeposit($reservation)) {
                    $this->transferDeposit($reservation, $order);
                    $depositTransferred = true;
                }
            }

            $result = new SeatGuestsResult(
                reservation: $reservation->fresh(),
                order: $order,
                tables: $tables,
                depositTransferred: $depositTransferred,
            );

            // Dispatch events
            ReservationSeated::dispatch(
                $result->reservation,
                $order,
                $tables,
                $depositTransferred,
                $userId
            );

            if ($depositTransferred) {
                DepositTransferred::dispatch(
                    $result->reservation,
                    $order,
                    (float) $reservation->deposit,
                    $userId
                );
            }

            return $result;
        });
    }

    /**
     * Lock tables and validate they are available.
     *
     * @throws TableOccupiedException
     */
    private function lockAndValidateTables(Reservation $reservation): Collection
    {
        $tableIds = $this->getAllTableIds($reservation);

        // Lock tables for update
        $tables = Table::whereIn('id', $tableIds)
            ->lockForUpdate()
            ->get();

        // Check for tables with ACTUAL active orders (not just status field)
        // Исключаем предзаказы (type=preorder) — они не блокируют посадку
        $tablesWithActiveOrders = $tables->filter(function (Table $table) {
            return Order::where('table_id', $table->id)
                ->whereIn('status', [OrderStatus::NEW->value, 'open', OrderStatus::COOKING->value, OrderStatus::READY->value, OrderStatus::SERVED->value])
                ->where('payment_status', PaymentStatus::PENDING->value)
                ->where('type', '!=', OrderType::PREORDER->value)
                ->exists();
        });

        if ($tablesWithActiveOrders->isNotEmpty()) {
            throw TableOccupiedException::tables($tablesWithActiveOrders);
        }

        // Auto-fix inconsistent status: if table.status='occupied' but no active orders
        $tables->each(function (Table $table) {
            if ($table->status === 'occupied') {
                $hasActiveOrder = Order::where('table_id', $table->id)
                    ->whereIn('status', [OrderStatus::NEW->value, 'open', OrderStatus::COOKING->value, OrderStatus::READY->value, OrderStatus::SERVED->value])
                    ->where('payment_status', PaymentStatus::PENDING->value)
                    ->where('type', '!=', OrderType::PREORDER->value)
                    ->exists();

                if (!$hasActiveOrder) {
                    $table->update(['status' => 'free']);
                }
            }
        });

        return $tables->fresh();
    }

    /**
     * Get all table IDs including linked tables.
     */
    private function getAllTableIds(Reservation $reservation): array
    {
        $tableIds = [$reservation->table_id];

        if (!empty($reservation->linked_table_ids)) {
            $linkedIds = is_array($reservation->linked_table_ids)
                ? $reservation->linked_table_ids
                : json_decode($reservation->linked_table_ids, true);

            if (is_array($linkedIds)) {
                $tableIds = array_merge($tableIds, $linkedIds);
            }
        }

        return array_unique(array_filter($tableIds));
    }

    /**
     * Create order for the reservation.
     */
    private function createOrder(
        Reservation $reservation,
        Collection $tables,
        int $guestsCount,
        ?int $userId
    ): Order {
        $mainTable = $tables->first();
        $linkedTableIds = $tables->count() > 1
            ? $tables->slice(1)->pluck('id')->toArray()
            : null;

        return Order::create([
            'restaurant_id' => $reservation->restaurant_id,
            'table_id' => $mainTable->id,
            'linked_table_ids' => $linkedTableIds,
            'reservation_id' => $reservation->id,
            'customer_id' => $reservation->customer_id,
            'order_number' => Order::generateOrderNumber($reservation->restaurant_id),
            'persons' => $guestsCount,
            'type' => OrderType::DINE_IN->value,
            'status' => 'open',
            'payment_status' => PaymentStatus::PENDING->value,
            'subtotal' => 0,
            'total' => 0,
            'user_id' => $userId,
        ]);
    }

    /**
     * Перенести позиции предзаказа в основной заказ.
     * Предзаказ помечается как завершённый после переноса.
     */
    private function mergePreorderItems(Reservation $reservation, Order $order): void
    {
        $preorder = Order::where('reservation_id', $reservation->id)
            ->where('type', OrderType::PREORDER->value)
            ->with('items.dish')
            ->first();

        if (!$preorder || $preorder->items->isEmpty()) {
            return;
        }

        // Переносим каждую позицию в новый заказ
        foreach ($preorder->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'dish_id' => $item->dish_id,
                'name' => $item->name ?: ($item->dish?->name ?? 'Блюдо'),
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
                'comment' => $item->comment,
                'modifiers' => $item->modifiers,
            ]);
        }

        // Пересчитываем итого нового заказа
        $order->recalculateTotal();

        // Помечаем предзаказ как завершённый
        $preorder->update([
            'status' => OrderStatus::COMPLETED->value,
            'type' => OrderType::PREORDER->value,
        ]);
    }

    /**
     * Check if deposit can be transferred.
     */
    private function canTransferDeposit(Reservation $reservation): bool
    {
        return $reservation->deposit > 0
            && $reservation->deposit_status === 'paid'
            && $reservation->isDepositPaid();
    }

    /**
     * Transfer deposit to order.
     */
    private function transferDeposit(Reservation $reservation, Order $order): void
    {
        // Mark deposit as transferred
        $reservation->update([
            'deposit_status' => 'transferred',
            'deposit_transferred_to_order_id' => $order->id,
            'deposit_transferred_at' => now(),
        ]);

        // Add prepayment to order
        $order->update([
            'prepaid_amount' => $reservation->deposit,
            'prepaid_source' => 'reservation_deposit',
            'prepaid_reservation_id' => $reservation->id,
        ]);
    }
}
