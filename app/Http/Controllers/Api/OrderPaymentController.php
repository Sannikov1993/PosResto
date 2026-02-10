<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Models\RealtimeEvent;
use App\Models\CashOperation;
use App\Models\Reservation;
use App\Services\BonusService;
use App\Services\LegalEntityService;
use App\Services\PaymentService;
use App\Events\OrderEvent;
use App\Http\Requests\Order\PayOrderRequest;
use App\Http\Requests\Order\CancelOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;

class OrderPaymentController extends Controller
{
    use BroadcastsEvents;
    protected LegalEntityService $legalEntityService;

    public function __construct(LegalEntityService $legalEntityService)
    {
        $this->legalEntityService = $legalEntityService;
    }

    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Проверяем, не оплачен ли уже заказ
        if ($order->payment_status === PaymentStatus::PAID->value) {
            return response()->json(['success' => false, 'message' => 'Заказ уже оплачен'], 422);
        }

        // Проверяем открытую кассовую смену
        $restaurantId = $order->restaurant_id;
        $shift = \App\Models\CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Откройте кассовую смену перед оплатой'], 422);
        }

        // Проверяем, что смена открыта сегодня
        $shiftDate = $shift->opened_at->toDateString();
        $today = now()->toDateString();

        if ($shiftDate !== $today) {
            $shiftDateFormatted = $shift->opened_at->format('d.m.Y');
            return response()->json([
                'success' => false,
                'message' => "Смена от {$shiftDateFormatted}. Закройте её и откройте новую смену для сегодняшних операций.",
                'error_code' => 'SHIFT_OUTDATED'
            ], 422);
        }

        // Проверка лимита скидки при оплате
        $discountAmount = $validated['discount_amount'] ?? 0;
        if ($discountAmount > 0 && $order->subtotal > 0) {
            $discountPercent = (int) round(($discountAmount / $order->subtotal) * 100);
            $user = $request->user();
            if ($user && $discountPercent > 0 && !$user->canApplyDiscount($discountPercent)) {
                $role = $user->getEffectiveRole();
                $maxDiscount = $role ? $role->max_discount_percent : 0;
                return response()->json([
                    'success' => false,
                    'message' => "Вы не можете применить скидку {$discountPercent}%. Максимум для вашей роли: {$maxDiscount}%",
                ], 403);
            }
        }

        // Применяем скидку если передана
        $bonusUsed = $validated['bonus_used'] ?? 0;

        $freshOrder = DB::transaction(function () use ($order, $validated, $discountAmount, $bonusUsed, $shift, $restaurantId) {
            if ($discountAmount > 0 || $bonusUsed > 0) {
                $order->update([
                    'discount_amount' => $discountAmount,
                    'bonus_used' => $bonusUsed,
                    'total' => max(0, $order->subtotal - $discountAmount - $bonusUsed + ($order->delivery_fee ?? 0)),
                    'promo_code' => $validated['promo_code'] ?? null,
                ]);
            }

            // Обновляем заказ
            $order->update([
                'payment_status' => PaymentStatus::PAID->value,
                'payment_method' => $validated['method'],
                'paid_at' => now()
            ]);

            // Разбиваем заказ по юридическим лицам и записываем операции
            $splitData = $this->legalEntityService->splitOrderByLegalEntity($order);

            if (count($splitData) > 1) {
                // Несколько юрлиц - создаём раздельные операции
                $this->legalEntityService->createSplitPaymentOperations(
                    $order,
                    $splitData,
                    $validated['method'],
                    $shift,
                    auth()->id()
                );

                // Сохраняем информацию о разбиении в заказе
                $paymentSplit = $this->legalEntityService->formatPaymentSplit($splitData);
                $order->update(['payment_split' => $paymentSplit]);
            } else {
                // Одно юрлицо или нет юрлиц - обычная операция
                $singleSplit = reset($splitData);
                $legalEntityId = $singleSplit['legal_entity_id'] ?? null;
                $cashRegister = $legalEntityId
                    ? $this->legalEntityService->getDefaultCashRegister($legalEntityId)
                    : null;

                \App\Models\CashOperation::create([
                    'restaurant_id' => $order->restaurant_id,
                    'legal_entity_id' => $legalEntityId,
                    'cash_register_id' => $cashRegister?->id,
                    'cash_shift_id' => $shift->id,
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'type' => CashOperation::TYPE_INCOME,
                    'category' => CashOperation::CATEGORY_ORDER,
                    'amount' => $validated['amount'] ?? $order->total,
                    'payment_method' => $validated['method'],
                    'description' => "Оплата заказа #{$order->order_number}",
                ]);
            }

            // Обновляем итоги смены
            $shift->updateTotals();

            // Обновляем статистику клиента и работаем с бонусами через BonusService
            if ($order->customer_id && $order->customer) {
                $order->customer->updateStats();

                $bonusService = new BonusService($restaurantId);

                // Списываем бонусы если использовались
                if ($bonusUsed > 0) {
                    $bonusService->spendForOrder($order, (int) $bonusUsed);
                }

                // Начисляем бонусы за заказ
                $bonusService->earnForOrder($order);
            }

            return $order->fresh();
        });

        // Отправляем событие через WebSocket (после коммита транзакции)
        OrderEvent::dispatch($freshOrder->restaurant_id, 'order_paid', [
            'order_id' => $freshOrder->id,
            'order_number' => $freshOrder->order_number,
            'total' => $freshOrder->total,
            'method' => $validated['method'],
            'message' => "Заказ #{$freshOrder->order_number} оплачен",
            'sound' => 'payment',
        ]);

        // Автоматическое списание со склада (вне транзакции — не должно блокировать оплату)
        $this->deductInventoryForOrder($freshOrder, $restaurantId);

        $this->broadcastOrderPaid($freshOrder, $validated['method']);

        return response()->json([
            'success' => true,
            'message' => 'Оплата принята',
            'data' => $freshOrder,
            'payment_split' => $freshOrder->payment_split,
        ]);
    }

    public function cancelWithWriteOff(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Проверка лимита отмены по сумме заказа
        $user = $request->user();
        if ($user && !$user->canCancelOrder((float) $order->total)) {
            $role = $user->getEffectiveRole();
            $maxCancel = $role ? $role->max_cancel_amount : 0;
            return response()->json([
                'success' => false,
                'message' => "Вы не можете отменить заказ на сумму {$order->total} ₽. Максимум для вашей роли: {$maxCancel} ₽",
            ], 403);
        }

        \Log::info('cancelWithWriteOff', [
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'linked_table_ids' => $order->linked_table_ids,
        ]);

        $oldStatus = $order->status;
        $tableId = $order->table_id;
        $linkedTableIds = $order->linked_table_ids ?? [];
        $restaurantId = $order->restaurant_id;

        $freshOrder = DB::transaction(function () use ($order, $validated, $tableId, $linkedTableIds) {
            $reservationId = $order->reservation_id;

            $order->update([
                'status' => OrderStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
                'cancelled_by' => $validated['manager_id'],
                'is_write_off' => true,
            ]);

            // Обрабатываем бронирование - отменяем его тоже
            if ($reservationId) {
                $reservation = Reservation::forRestaurant($order->restaurant_id)->find($reservationId);
                if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                    $reservation->update(['status' => 'cancelled']);
                }
            }

            if ($tableId) {
                Table::where('id', $tableId)->update(['status' => 'free']);
            }

            // Освобождаем связанные столы
            if (!empty($linkedTableIds)) {
                foreach ($linkedTableIds as $linkedTableId) {
                    if ($linkedTableId != $tableId) {
                        Table::where('id', $linkedTableId)->update(['status' => 'free']);
                    }
                }
            }

            return $order->fresh()->load(['items.dish', 'table']);
        });

        // Broadcast события после коммита транзакции
        if ($tableId) {
            $this->broadcastTableStatusChanged($tableId, 'free', $restaurantId);
        }
        if (!empty($linkedTableIds)) {
            foreach ($linkedTableIds as $linkedTableId) {
                if ($linkedTableId != $tableId) {
                    $this->broadcastTableStatusChanged($linkedTableId, 'free', $restaurantId);
                }
            }
        }

        $this->broadcastOrderStatusChanged($freshOrder, $oldStatus, 'cancelled');
        return response()->json(['success' => true, 'message' => 'Заказ отменён со списанием', 'data' => $freshOrder]);
    }

    /**
     * Списать ингредиенты со склада при оплате заказа
     */
    protected function deductInventoryForOrder(Order $order, int $restaurantId): void
    {
        try {
            // Получаем склад по умолчанию
            $warehouseId = \App\Models\Warehouse::where('restaurant_id', $restaurantId)
                ->where('is_default', true)
                ->value('id')
                ?? \App\Models\Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

            if (!$warehouseId) {
                return; // Нет склада - пропускаем
            }

            // Списываем ингредиенты по каждой позиции
            foreach ($order->items as $item) {
                if ($item->dish_id) {
                    \App\Models\Recipe::deductIngredientsForDish(
                        $item->dish_id,
                        $warehouseId,
                        $item->quantity,
                        $order->id,
                        null // userId
                    );
                }
            }

            // Помечаем заказ как обработанный
            $order->update(['inventory_deducted' => true]);
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем оплату
            \Log::warning('Inventory deduction failed for order #' . $order->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Предпросмотр разбиения заказа по юрлицам
     * GET /api/v1/orders/{order}/payment-split-preview
     */
    public function paymentSplitPreview(Order $order): JsonResponse
    {
        $splitData = $this->legalEntityService->splitOrderByLegalEntity($order);

        // Если только одно юрлицо или нет юрлиц - разбиения нет
        if (count($splitData) <= 1) {
            return response()->json([
                'success' => true,
                'has_split' => false,
                'splits' => [],
            ]);
        }

        // Формируем preview данные
        $splits = [];
        foreach ($splitData as $legalEntityId => $data) {
            $splits[] = [
                'legal_entity_id' => $legalEntityId,
                'legal_entity_name' => $data['legal_entity_name'],
                'legal_entity_short_name' => $data['legal_entity_short_name'] ?? null,
                'amount' => $data['total'],
                'items_count' => count($data['items']),
                'items' => array_map(function ($item) {
                    return [
                        'name' => $item['dish_name'] ?? 'Позиция',
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $item['price'] ?? 0,
                    ];
                }, $data['items']),
            ];
        }

        return response()->json([
            'success' => true,
            'has_split' => true,
            'splits' => $splits,
            'total' => $order->total,
        ]);
    }
}
