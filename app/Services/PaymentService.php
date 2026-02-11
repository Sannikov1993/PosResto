<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\RealtimeEvent;
use App\Models\Warehouse;
use App\Models\Recipe;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Events\OrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Единый сервис обработки платежей
 *
 * Отвечает за:
 * - Проверку кассовой смены
 * - Создание кассовых операций для ВСЕХ методов оплаты
 * - Обновление итогов смены
 * - Работу с бонусами
 * - Списание со склада
 */
class PaymentService
{
    protected ?BonusService $bonusService = null;
    protected ?LegalEntityService $legalEntityService = null;

    public function __construct(
        ?LegalEntityService $legalEntityService = null
    ) {
        $this->legalEntityService = $legalEntityService;
    }

    /**
     * Основной метод оплаты заказа
     *
     * @param Order $order Заказ для оплаты
     * @param array $paymentData Данные оплаты:
     *   - method: string (cash|card|online|mixed|bonus)
     *   - amount: ?float (если не указано - используется total заказа)
     *   - cash_amount: ?float (для mixed)
     *   - card_amount: ?float (для mixed)
     *   - bonus_used: ?float
     *   - discount_amount: ?float
     *   - promo_code: ?string
     *   - deposit_used: ?float
     *   - staff_id: ?int
     *   - items: ?array (информация о позициях)
     *   - guest_numbers: ?array
     * @return array ['success' => bool, 'message' => string, 'data' => ?array]
     */
    public function processPayment(Order $order, array $paymentData): array
    {
        // 1. Проверяем кассовую смену (до транзакции)
        $shiftCheck = $this->validateShift($order->restaurant_id);
        if (!$shiftCheck['success']) {
            return $shiftCheck;
        }
        $shift = $shiftCheck['shift'];

        // 2. Извлекаем данные оплаты
        $paymentMethod = $paymentData['method'] ?? 'cash';
        $bonusUsed = (float) ($paymentData['bonus_used'] ?? 0);
        $discountAmount = (float) ($paymentData['discount_amount'] ?? 0);
        $depositUsed = (float) ($paymentData['deposit_used'] ?? 0);
        $staffId = $paymentData['staff_id'] ?? auth()->id();
        $cashAmount = (float) ($paymentData['cash_amount'] ?? 0);
        $cardAmount = (float) ($paymentData['card_amount'] ?? 0);
        $paidItems = $paymentData['items'] ?? null;
        $guestNumbers = $paymentData['guest_numbers'] ?? null;

        // 3. Атомарная транзакция с pessimistic lock
        $result = DB::transaction(function () use (
            $order, $paymentMethod, $bonusUsed, $discountAmount, $depositUsed,
            $staffId, $cashAmount, $cardAmount, $paidItems, $guestNumbers, $shift, $paymentData
        ) {
            // lockForUpdate предотвращает двойную оплату
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->payment_status === PaymentStatus::PAID->value) {
                return [
                    'success' => false,
                    'message' => 'Заказ уже оплачен',
                    'error_code' => 'ALREADY_PAID',
                ];
            }

            // 4. Применяем скидки и бонусы к заказу
            if ($discountAmount > 0 || $bonusUsed > 0) {
                $order->update([
                    'discount_amount' => $discountAmount,
                    'bonus_used' => $bonusUsed,
                    'total' => max(0, $order->subtotal - $discountAmount - $bonusUsed + ($order->delivery_fee ?? 0)),
                    'promo_code' => $paymentData['promo_code'] ?? $order->promo_code,
                ]);
                $order->refresh();
            }

            // 5. Определяем сумму для оплаты (bonusUsed уже учтён в total)
            $paymentAmount = $paymentData['amount'] ?? ($order->total - $depositUsed);
            $paymentAmount = max(0, $paymentAmount);

            // 6. Определяем эффективный метод оплаты
            $effectiveMethod = $this->determineEffectiveMethod($paymentMethod, $depositUsed, $bonusUsed, $order->total);

            // 7. Обновляем заказ
            $order->update([
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'payment_method' => $effectiveMethod,
                'paid_at' => now(),
                'completed_at' => now(),
                'deposit_used' => $depositUsed,
                'bonus_used' => $bonusUsed,
            ]);

            // 8. Создаём кассовые операции
            $this->createCashOperations(
                order: $order,
                shift: $shift,
                paymentMethod: $paymentMethod,
                paymentAmount: $paymentAmount,
                cashAmount: $cashAmount,
                cardAmount: $cardAmount,
                staffId: $staffId,
                paidItems: $paidItems,
                guestNumbers: $guestNumbers
            );

            // 9. Обновляем итоги смены
            $shift->updateTotals();

            return [
                'success' => true,
                'order' => $order,
                'effectiveMethod' => $effectiveMethod,
                'shift' => $shift,
            ];
        });

        // Если транзакция вернула ошибку — возвращаем сразу
        if (!$result['success']) {
            return $result;
        }

        $order = $result['order'];
        $effectiveMethod = $result['effectiveMethod'];

        // Действия после коммита транзакции (не блокируют оплату)

        // WebSocket событие
        OrderEvent::dispatch($order->restaurant_id, 'order_paid', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'method' => $effectiveMethod,
            'message' => "Заказ #{$order->order_number} оплачен",
            'sound' => 'payment',
        ]);

        // Работа с бонусами клиента
        $this->handleCustomerBonuses($order, $bonusUsed);

        // Списание со склада
        $this->deductInventory($order);

        // Освобождаем столы
        $this->handleTableRelease($order);

        // Завершаем бронирование
        $this->handleReservationCompletion($order);

        // Realtime событие
        $freshOrder = $order->fresh();
        RealtimeEvent::orderPaid($freshOrder->toArray(), $effectiveMethod);

        return [
            'success' => true,
            'message' => 'Оплата принята',
            'data' => [
                'order' => $freshOrder,
                'shift' => $result['shift'],
            ],
        ];
    }

    /**
     * Проверка и получение открытой кассовой смены
     */
    public function validateShift(int $restaurantId): array
    {
        $shift = CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return [
                'success' => false,
                'message' => 'Откройте кассовую смену перед оплатой',
                'error_code' => 'NO_SHIFT',
            ];
        }

        // Проверяем, что смена открыта сегодня
        $shiftDate = $shift->opened_at->toDateString();
        $today = now()->toDateString();

        if ($shiftDate !== $today) {
            $shiftDateFormatted = $shift->opened_at->format('d.m.Y');
            return [
                'success' => false,
                'message' => "Смена от {$shiftDateFormatted}. Закройте её и откройте новую смену для сегодняшних операций.",
                'error_code' => 'SHIFT_OUTDATED',
            ];
        }

        return [
            'success' => true,
            'shift' => $shift,
        ];
    }

    /**
     * Определение эффективного метода оплаты
     */
    protected function determineEffectiveMethod(
        string $paymentMethod,
        float $depositUsed,
        float $bonusUsed,
        float $orderTotal
    ): string {
        // Полностью оплачено депозитом/бонусами
        if ($depositUsed + $bonusUsed >= $orderTotal) {
            return 'bonus';
        }

        // Частичная оплата депозитом + другой метод
        if ($depositUsed > 0 && $paymentMethod !== 'mixed') {
            return 'mixed';
        }

        return $paymentMethod;
    }

    /**
     * Создание кассовых операций
     */
    protected function createCashOperations(
        Order $order,
        CashShift $shift,
        string $paymentMethod,
        float $paymentAmount,
        float $cashAmount,
        float $cardAmount,
        ?int $staffId,
        ?array $paidItems,
        ?array $guestNumbers
    ): void {
        // Пропускаем если сумма оплаты 0 (полностью оплачено бонусами/депозитом)
        if ($paymentAmount <= 0) {
            return;
        }

        // Смешанная оплата - создаём отдельные операции
        if ($paymentMethod === 'mixed' && ($cashAmount > 0 || $cardAmount > 0)) {
            if ($cashAmount > 0) {
                $this->createSingleCashOperation(
                    $order, $shift, 'cash', $cashAmount, $staffId, $paidItems, $guestNumbers
                );
            }
            if ($cardAmount > 0) {
                $this->createSingleCashOperation(
                    $order, $shift, 'card', $cardAmount, $staffId, $paidItems, $guestNumbers
                );
            }
            return;
        }

        // Обычная оплата - одна операция
        $this->createSingleCashOperation(
            $order, $shift, $paymentMethod, $paymentAmount, $staffId, $paidItems, $guestNumbers
        );
    }

    /**
     * Создание одной кассовой операции
     */
    protected function createSingleCashOperation(
        Order $order,
        CashShift $shift,
        string $paymentMethod,
        float $amount,
        ?int $staffId,
        ?array $paidItems,
        ?array $guestNumbers
    ): CashOperation {
        // Формируем описание
        $description = "Оплата заказа #{$order->order_number}";
        if ($guestNumbers && count($guestNumbers) > 0) {
            $guestStr = count($guestNumbers) === 1
                ? "Гость " . $guestNumbers[0]
                : "Гости " . implode(', ', $guestNumbers);
            $description .= " ({$guestStr})";
        } elseif ($amount < $order->total) {
            $description .= " (часть)";
        }

        // Формируем notes
        $notes = null;
        if ($paidItems || $guestNumbers) {
            $notesData = [];
            if ($guestNumbers) {
                $notesData['guest_numbers'] = $guestNumbers;
            }
            if ($paidItems) {
                $notesData['items'] = $paidItems;
            }
            $notes = json_encode($notesData, JSON_UNESCAPED_UNICODE);
        }

        return CashOperation::create([
            'restaurant_id' => $order->restaurant_id,
            'cash_shift_id' => $shift->id,
            'order_id' => $order->id,
            'user_id' => $staffId,
            'type' => CashOperation::TYPE_INCOME,
            'category' => CashOperation::CATEGORY_ORDER,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'notes' => $notes,
        ]);
    }

    /**
     * Обработка бонусов клиента (списание и начисление)
     */
    protected function handleCustomerBonuses(Order $order, float $bonusUsed): void
    {
        if (!$order->customer_id) {
            return;
        }

        try {
            $order->load('customer');
            if (!$order->customer) {
                return;
            }

            // Обновляем статистику клиента
            $order->customer->updateStats();

            $bonusService = $this->getBonusService($order->restaurant_id);

            // Списываем бонусы если использовались
            if ($bonusUsed > 0) {
                $bonusService->spendForOrder($order, (int) $bonusUsed);
            }

            // Начисляем бонусы за заказ
            if ($bonusService->isEnabled()) {
                $bonusService->earnForOrder($order);
            }
        } catch (\Exception $e) {
            Log::warning("PaymentService: bonus handling failed for order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Списание ингредиентов со склада
     */
    protected function deductInventory(Order $order): void
    {
        try {
            if ($order->inventory_deducted) {
                return;
            }

            $warehouseId = Warehouse::where('restaurant_id', $order->restaurant_id)
                ->where('is_default', true)
                ->value('id')
                ?? Warehouse::where('restaurant_id', $order->restaurant_id)->first()?->id;

            if (!$warehouseId) {
                return;
            }

            foreach ($order->items as $item) {
                if ($item->dish_id) {
                    Recipe::deductIngredientsForDish(
                        $item->dish_id,
                        $warehouseId,
                        $item->quantity,
                        $order->id,
                        null
                    );
                }
            }

            $order->update(['inventory_deducted' => true]);
        } catch (\Exception $e) {
            Log::warning("PaymentService: inventory deduction failed for order #{$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Освобождение столов
     */
    protected function handleTableRelease(Order $order): void
    {
        if (!$order->table_id) {
            return;
        }

        $allTableIds = [$order->table_id];
        if (!empty($order->linked_table_ids)) {
            $allTableIds = array_merge($allTableIds, $order->linked_table_ids);
            $allTableIds = array_unique($allTableIds);
        }

        // Проверяем есть ли другие активные заказы
        $activeOrders = Order::whereIn('table_id', $allTableIds)
            ->where('id', '!=', $order->id)
            ->whereIn('status', [OrderStatus::NEW->value, OrderStatus::COOKING->value, OrderStatus::READY->value, OrderStatus::SERVED->value])
            ->where('payment_status', PaymentStatus::PENDING->value)
            ->where('total', '>', 0)
            ->count();

        if ($activeOrders === 0) {
            foreach ($allTableIds as $tableId) {
                Table::where('id', $tableId)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->update(['status' => 'free']);
                RealtimeEvent::tableStatusChanged($tableId, 'free', $order->restaurant_id);
            }
        }
    }

    /**
     * Завершение бронирования
     */
    protected function handleReservationCompletion(Order $order): void
    {
        if (!$order->reservation_id) {
            return;
        }

        $reservation = Reservation::forRestaurant($order->restaurant_id)
            ->find($order->reservation_id);

        if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
            $reservation->update(['status' => 'completed']);
        }
    }

    /**
     * Записать возврат
     */
    public function processRefund(Order $order, float $amount, string $paymentMethod, ?int $staffId = null): array
    {
        $shiftCheck = $this->validateShift($order->restaurant_id);
        if (!$shiftCheck['success']) {
            return $shiftCheck;
        }
        $shift = $shiftCheck['shift'];

        $operation = CashOperation::create([
            'restaurant_id' => $order->restaurant_id,
            'cash_shift_id' => $shift->id,
            'order_id' => $order->id,
            'user_id' => $staffId ?? auth()->id(),
            'type' => CashOperation::TYPE_EXPENSE,
            'category' => CashOperation::CATEGORY_REFUND,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'description' => "Возврат по заказу #{$order->order_number}",
        ]);

        $shift->updateTotals();

        return [
            'success' => true,
            'message' => 'Возврат оформлен',
            'data' => [
                'operation' => $operation,
                'shift' => $shift,
            ],
        ];
    }

    /**
     * Внесение денег в кассу
     */
    public function processDeposit(int $restaurantId, float $amount, string $description, ?int $staffId = null): array
    {
        $shiftCheck = $this->validateShift($restaurantId);
        if (!$shiftCheck['success']) {
            return $shiftCheck;
        }
        $shift = $shiftCheck['shift'];

        $operation = CashOperation::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift->id,
            'user_id' => $staffId ?? auth()->id(),
            'type' => CashOperation::TYPE_DEPOSIT,
            'category' => 'deposit',
            'amount' => $amount,
            'payment_method' => 'cash',
            'description' => $description ?: 'Внесение в кассу',
        ]);

        $shift->updateTotals();

        return [
            'success' => true,
            'message' => 'Внесение выполнено',
            'data' => [
                'operation' => $operation,
                'shift' => $shift,
            ],
        ];
    }

    /**
     * Изъятие денег из кассы
     */
    public function processWithdrawal(int $restaurantId, float $amount, string $description, ?int $staffId = null): array
    {
        $shiftCheck = $this->validateShift($restaurantId);
        if (!$shiftCheck['success']) {
            return $shiftCheck;
        }
        $shift = $shiftCheck['shift'];

        // Проверяем достаточно ли денег в кассе
        $currentCash = $shift->calculateExpectedAmount();
        if ($amount > $currentCash) {
            return [
                'success' => false,
                'message' => "Недостаточно денег в кассе. Доступно: {$currentCash} ₽",
                'error_code' => 'INSUFFICIENT_FUNDS',
            ];
        }

        $operation = CashOperation::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift->id,
            'user_id' => $staffId ?? auth()->id(),
            'type' => CashOperation::TYPE_WITHDRAWAL,
            'category' => 'withdrawal',
            'amount' => $amount,
            'payment_method' => 'cash',
            'description' => $description ?: 'Изъятие из кассы',
        ]);

        $shift->updateTotals();

        return [
            'success' => true,
            'message' => 'Изъятие выполнено',
            'data' => [
                'operation' => $operation,
                'shift' => $shift,
            ],
        ];
    }

    /**
     * Получить BonusService (lazy initialization)
     */
    protected function getBonusService(int $restaurantId): BonusService
    {
        if (!$this->bonusService || $this->bonusService->getRestaurantId() !== $restaurantId) {
            $this->bonusService = new BonusService($restaurantId);
        }
        return $this->bonusService;
    }
}
