<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\Order;
use App\Events\CashEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class FinanceController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * ===========================================
     * КАССОВЫЕ СМЕНЫ
     * ===========================================
     */

    /**
     * Список кассовых смен
     */
    public function shifts(Request $request): JsonResponse
    {
        $query = CashShift::with(['cashier', 'events'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтр по дате
        if ($request->has('date_from')) {
            $query->whereDate('opened_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('opened_at', '<=', $request->input('date_to'));
        }

        // Только сегодня
        if ($request->boolean('today')) {
            $query->whereDate('opened_at', Carbon::today());
        }

        $shifts = $query->orderByDesc('opened_at')
            ->limit($request->input('limit', 50))
            ->get();

        // Обновляем итоги и вычисляем current_cash только для открытых смен
        // Для закрытых смен current_cash == expected_amount (уже в БД)
        foreach ($shifts as $shift) {
            if ($shift->isOpen()) {
                $shift->updateTotals();
                $shift->refresh();
                $shift->append('current_cash');
            } else {
                // Для закрытых смен используем сохранённое expected_amount без доп. запросов
                $shift->setAttribute('current_cash', (float) $shift->expected_amount);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Остаток с последней закрытой смены (для открытия новой)
     */
    public function lastClosedShiftBalance(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $lastClosedShift = CashShift::where('restaurant_id', $restaurantId)
            ->where('status', CashShift::STATUS_CLOSED)
            ->orderByDesc('closed_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'closing_amount' => $lastClosedShift ? (float) $lastClosedShift->closing_amount : 0,
                'closed_at' => $lastClosedShift?->closed_at?->toIso8601String(),
                'shift_number' => $lastClosedShift?->shift_number,
            ],
        ]);
    }

    /**
     * Текущая открытая смена
     */
    public function currentShift(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $shift = CashShift::getCurrentShift($restaurantId);

        // Безопасная загрузка связей
        if ($shift) {
            try {
                // Обновляем итоги смены при запросе
                $shift->updateTotals();
                $shift->refresh();
                // Загружаем связи после refresh
                $shift->load(['cashier', 'operations', 'events']);
                // Explicit append current_cash (убран из auto-append для N+1 fix)
                $shift->append('current_cash');
            } catch (\Exception $e) {
                \Log::warning('Failed to load shift relations: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data' => $shift,
        ]);
    }

    /**
     * Открыть смену (одна смена на день)
     */
    public function openShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cashier_id' => 'nullable|integer',
            'opening_amount' => 'nullable|numeric|min:0',
            'opening_cash' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $cashierId = $validated['cashier_id'] ?? null;
        $openingAmount = $validated['opening_amount'] ?? $validated['opening_cash'] ?? 0;

        // Проверяем, нет ли уже открытой смены
        $existingShift = CashShift::getCurrentShift($restaurantId);
        if ($existingShift) {
            $existingShift->append('current_cash');
            return response()->json([
                'success' => false,
                'message' => 'Уже есть открытая смена. Сначала закройте текущую смену.',
                'data' => $existingShift,
            ], 400);
        }

        // Открываем новую смену
        $shift = CashShift::openShift(
            $restaurantId,
            $cashierId,
            $openingAmount
        );

        // Real-time событие (не блокируем ответ при ошибке broadcast)
        try {
            CashEvent::dispatch($restaurantId, 'shift_opened', [
                'shift_id' => $shift->id,
                'shift_number' => $shift->shift_number,
                'opening_amount' => $openingAmount,
                'cashier_id' => $cashierId,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CashEvent broadcast failed (shift_opened): ' . $e->getMessage());
        }

        // Explicit append current_cash для новой смены
        $shift->append('current_cash');

        return response()->json([
            'success' => true,
            'message' => 'Смена открыта',
            'data' => $shift,
        ], 201);
    }

    /**
     * Закрыть смену
     */
    public function closeShift(Request $request, CashShift $shift): JsonResponse
    {
        // Enterprise: явная проверка принадлежности к текущему ресторану
        $shift->requireCurrentRestaurant();

        $validated = $request->validate([
            'closing_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($shift->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Смена уже закрыта',
            ], 400);
        }

        $closingAmount = $validated['closing_amount'] ?? 0;
        $shift->closeShift($closingAmount);

        if (isset($validated['notes'])) {
            $shift->update(['notes' => $validated['notes']]);
        }

        // Real-time событие (не блокируем ответ при ошибке broadcast)
        try {
            CashEvent::dispatch($shift->restaurant_id, 'shift_closed', [
                'shift_id' => $shift->id,
                'shift_number' => $shift->shift_number,
                'closing_amount' => $closingAmount,
                'total_revenue' => $shift->total_revenue,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CashEvent broadcast failed (shift_closed): ' . $e->getMessage());
        }

        $freshShift = $shift->fresh();
        $freshShift->append('current_cash');

        return response()->json([
            'success' => true,
            'message' => 'Смена закрыта',
            'data' => $freshShift,
        ]);
    }

    /**
     * Детали смены
     */
    public function showShift(CashShift $shift): JsonResponse
    {
        // Enterprise: явная проверка принадлежности к текущему ресторану
        $shift->requireCurrentRestaurant();

        // Обновляем итоги если смена открыта
        if ($shift->isOpen()) {
            $shift->updateTotals();
            $shift->refresh();
        }

        // Explicit append current_cash (убран из auto-append для N+1 fix)
        $shift->append('current_cash');

        return response()->json([
            'success' => true,
            'data' => $shift->load(['cashier', 'events', 'operations']),
        ]);
    }

    /**
     * Заказы смены (оплаченные в рамках этой смены)
     */
    public function shiftOrders(CashShift $shift): JsonResponse
    {
        // Enterprise: явная проверка принадлежности к текущему ресторану
        $shift->requireCurrentRestaurant();

        // Получаем ID заказов из операций этой смены
        $orderIds = $shift->operations()
            ->where('type', CashOperation::TYPE_INCOME)
            ->where('category', CashOperation::CATEGORY_ORDER)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->unique();

        // Загружаем заказы с деталями (включая скидку уровня лояльности)
        $orders = Order::with(['items.dish', 'table', 'customer', 'loyaltyLevel'])
            ->whereIn('id', $orderIds)
            ->orderByDesc('paid_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Получить предоплаты за смену
     */
    public function shiftPrepayments(CashShift $shift): JsonResponse
    {
        // Enterprise: явная проверка принадлежности к текущему ресторану
        $shift->requireCurrentRestaurant();

        $prepayments = $shift->operations()
            ->where('type', CashOperation::TYPE_INCOME)
            ->where('category', CashOperation::CATEGORY_PREPAYMENT)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $prepayments,
        ]);
    }

    /**
     * X-отчёт (промежуточный отчёт без закрытия)
     */
    public function xReport(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $shift = CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        // Пересчитываем итоги
        $shift->updateTotals();

        $report = [
            'shift' => $shift->fresh(['cashier']),
            'expected_cash' => $shift->calculateExpectedAmount(),
            'operations_summary' => $this->getOperationsSummary($shift),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Z-отчёт (закрытие смены с отчётом)
     */
    public function zReport(Request $request, CashShift $shift): JsonResponse
    {
        // Enterprise: явная проверка принадлежности к текущему ресторану
        $shift->requireCurrentRestaurant();

        if ($shift->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Смена уже закрыта',
            ], 400);
        }

        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
        ]);

        // Закрываем смену
        $shift->closeShift($validated['closing_amount']);

        $report = [
            'shift' => $shift->fresh(['cashier']),
            'operations_summary' => $this->getOperationsSummary($shift),
            'operations' => $shift->operations()->with(['staff', 'order'])->get(),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Смена закрыта. Z-отчёт сформирован.',
            'data' => $report,
        ]);
    }

    /**
     * ===========================================
     * КАССОВЫЕ ОПЕРАЦИИ
     * ===========================================
     */

    /**
     * Список операций
     */
    public function operations(Request $request): JsonResponse
    {
        $query = CashOperation::with(['staff', 'order', 'cashShift'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Фильтр по смене
        if ($request->has('shift_id')) {
            $query->where('cash_shift_id', $request->input('shift_id'));
        }

        // Фильтр по типу
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Фильтр по категории
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        // Фильтр по дате
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Только сегодня
        if ($request->boolean('today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        $operations = $query->orderByDesc('created_at')
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $operations,
        ]);
    }

    /**
     * Внесение денег в кассу
     */
    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'staff_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string|max:255',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Проверяем наличие открытой смены
        $shift = CashShift::getCurrentShift($restaurantId);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        $operation = DB::transaction(function () use ($restaurantId, $validated) {
            return CashOperation::recordDeposit(
                $restaurantId,
                $validated['amount'],
                $validated['staff_id'] ?? null,
                $validated['description'] ?? null
            );
        });

        // Real-time событие (не блокируем ответ при ошибке broadcast)
        try {
            CashEvent::dispatch($restaurantId, 'cash_operation_created', [
                'operation_id' => $operation->id,
                'type' => 'deposit',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Внесение',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CashEvent broadcast failed (deposit): ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Деньги внесены в кассу',
            'data' => $operation->load('staff'),
        ], 201);
    }

    /**
     * Изъятие денег из кассы
     */
    public function withdrawal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:purchase,salary,tips,other',
            'staff_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string|max:255',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Проверяем наличие открытой смены
        $shift = CashShift::getCurrentShift($restaurantId);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        // Проверяем, достаточно ли денег
        $expectedCash = $shift->calculateExpectedAmount();
        if ($validated['amount'] > $expectedCash) {
            return response()->json([
                'success' => false,
                'message' => "Недостаточно наличных. В кассе: {$expectedCash} ₽",
            ], 400);
        }

        $operation = DB::transaction(function () use ($restaurantId, $validated) {
            return CashOperation::recordWithdrawal(
                $restaurantId,
                $validated['amount'],
                $validated['category'],
                $validated['staff_id'] ?? null,
                $validated['description'] ?? null
            );
        });

        // Real-time событие (не блокируем ответ при ошибке broadcast)
        try {
            CashEvent::dispatch($restaurantId, 'cash_operation_created', [
                'operation_id' => $operation->id,
                'type' => 'withdrawal',
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Изъятие',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CashEvent broadcast failed (withdrawal): ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Деньги изъяты из кассы',
            'data' => $operation->load('staff'),
        ], 201);
    }

    /**
     * Предоплата за заказ (доставка/самовывоз)
     */
    public function orderPrepayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card',
            'order_id' => 'nullable|integer',
            'order_number' => 'nullable|string|max:50',
            'customer_name' => 'nullable|string|max:255',
            'order_type' => 'nullable|in:delivery,pickup',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Проверяем наличие открытой смены
        $shift = CashShift::getCurrentShift($restaurantId);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        $operation = CashOperation::recordOrderPrepayment(
            $restaurantId,
            $validated['order_id'] ?? null,
            $validated['amount'],
            $validated['payment_method'],
            null,
            $validated['customer_name'] ?? null,
            $validated['order_type'] ?? 'delivery',
            $validated['order_number'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Предоплата проведена',
            'data' => $operation,
        ], 201);
    }

    /**
     * Возврат денег за отменённый заказ
     */
    public function refund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'refund_method' => 'required|in:cash,card',
            'order_id' => 'nullable|integer',
            'order_number' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:500',
        ]);

        // Проверка лимита возврата
        $user = $request->user();
        if ($user && !$user->canRefund((float) $validated['amount'])) {
            $role = $user->getEffectiveRole();
            $maxRefund = $role ? $role->max_refund_amount : 0;
            return response()->json([
                'success' => false,
                'message' => "Вы не можете оформить возврат на сумму {$validated['amount']} ₽. Максимум для вашей роли: {$maxRefund} ₽",
            ], 403);
        }

        $restaurantId = $this->getRestaurantId($request);

        // Проверяем наличие открытой смены
        $shift = CashShift::getCurrentShift($restaurantId);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        // Если возврат наличными - проверяем достаточно ли денег в кассе
        if ($validated['refund_method'] === 'cash') {
            $expectedCash = $shift->calculateExpectedAmount();
            if ($validated['amount'] > $expectedCash) {
                return response()->json([
                    'success' => false,
                    'message' => "Недостаточно наличных в кассе. Доступно: {$expectedCash} ₽",
                ], 400);
            }
        }

        $operation = DB::transaction(function () use ($restaurantId, $validated) {
            return CashOperation::recordOrderRefund(
                $restaurantId,
                $validated['order_id'] ?? null,
                $validated['amount'],
                $validated['refund_method'],
                null,
                $validated['order_number'] ?? null,
                $validated['reason'] ?? null
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Возврат оформлен',
            'data' => $operation,
        ], 201);
    }

    /**
     * ===========================================
     * ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
     * ===========================================
     */

    /**
     * Сводка операций смены
     */
    protected function getOperationsSummary(CashShift $shift): array
    {
        $operations = $shift->operations;

        return [
            'income' => [
                'count' => $operations->where('type', 'income')->count(),
                'amount' => $operations->where('type', 'income')->sum('amount'),
            ],
            'expense' => [
                'count' => $operations->where('type', 'expense')->count(),
                'amount' => $operations->where('type', 'expense')->sum('amount'),
            ],
            'deposits' => [
                'count' => $operations->where('type', 'deposit')->count(),
                'amount' => $operations->where('type', 'deposit')->sum('amount'),
            ],
            'withdrawals' => [
                'count' => $operations->where('type', 'withdrawal')->count(),
                'amount' => $operations->where('type', 'withdrawal')->sum('amount'),
            ],
            'by_payment_method' => [
                'cash' => $operations->where('payment_method', 'cash')->where('type', 'income')->sum('amount'),
                'card' => $operations->where('payment_method', 'card')->where('type', 'income')->sum('amount'),
                'online' => $operations->where('payment_method', 'online')->where('type', 'income')->sum('amount'),
            ],
        ];
    }
}
