<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Обновляем итоги для открытых смен
        foreach ($shifts as $shift) {
            if ($shift->isOpen()) {
                $shift->updateTotals();
                $shift->refresh();
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

        // Real-time событие
        CashEvent::dispatch($restaurantId, 'shift_opened', [
            'shift_id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'opening_amount' => $openingAmount,
            'cashier_id' => $cashierId,
        ]);

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

        // Real-time событие
        CashEvent::dispatch($shift->restaurant_id, 'shift_closed', [
            'shift_id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'closing_amount' => $closingAmount,
            'total_revenue' => $shift->total_revenue,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена закрыта',
            'data' => $shift->fresh(),
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

        $operation = CashOperation::recordDeposit(
            $restaurantId,
            $validated['amount'],
            $validated['staff_id'] ?? null,
            $validated['description'] ?? null
        );

        // Real-time событие
        CashEvent::dispatch($restaurantId, 'cash_operation_created', [
            'operation_id' => $operation->id,
            'type' => 'deposit',
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Внесение',
        ]);

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

        $operation = CashOperation::recordWithdrawal(
            $restaurantId,
            $validated['amount'],
            $validated['category'],
            $validated['staff_id'] ?? null,
            $validated['description'] ?? null
        );

        // Real-time событие
        CashEvent::dispatch($restaurantId, 'cash_operation_created', [
            'operation_id' => $operation->id,
            'type' => 'withdrawal',
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Изъятие',
        ]);

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

        $operation = CashOperation::recordOrderRefund(
            $restaurantId,
            $validated['order_id'] ?? null,
            $validated['amount'],
            $validated['refund_method'],
            null,
            $validated['order_number'] ?? null,
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Возврат оформлен',
            'data' => $operation,
        ], 201);
    }

    /**
     * ===========================================
     * ФИНАНСОВАЯ АНАЛИТИКА
     * ===========================================
     */

    /**
     * Сводка за день
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $date = $request->input('date', Carbon::today()->toDateString());

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $date)
            ->selectRaw("
                COUNT(*) as total_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                SUM(CASE WHEN payment_method = 'cash' AND payment_status = 'paid' THEN total ELSE 0 END) as cash_revenue,
                SUM(CASE WHEN payment_method = 'card' AND payment_status = 'paid' THEN total ELSE 0 END) as card_revenue,
                SUM(CASE WHEN payment_method = 'online' AND payment_status = 'paid' THEN total ELSE 0 END) as online_revenue,
                AVG(CASE WHEN payment_status = 'paid' THEN total ELSE NULL END) as average_check
            ")
            ->first();

        $operations = CashOperation::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $date)
            ->selectRaw("
                SUM(CASE WHEN type IN ('income', 'deposit') THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type IN ('expense', 'withdrawal') THEN amount ELSE 0 END) as total_expense,
                SUM(CASE WHEN type = 'expense' AND category = 'refund' THEN amount ELSE 0 END) as refunds
            ")
            ->first();

        $shifts = CashShift::where('restaurant_id', $restaurantId)
            ->whereDate('opened_at', $date)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'orders' => [
                    'total' => (int) $orders->total_orders,
                    'paid' => (int) $orders->paid_orders,
                    'revenue' => (float) $orders->revenue,
                    'cash' => (float) $orders->cash_revenue,
                    'card' => (float) $orders->card_revenue,
                    'online' => (float) $orders->online_revenue,
                    'average_check' => round((float) $orders->average_check, 2),
                ],
                'operations' => [
                    'income' => (float) $operations->total_income,
                    'expense' => (float) $operations->total_expense,
                    'refunds' => (float) $operations->refunds,
                    'net' => (float) $operations->total_income - (float) $operations->total_expense,
                ],
                'shifts' => $shifts,
            ],
        ]);
    }

    /**
     * Сводка за период
     */
    public function periodSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        // Выручка по дням
        $dailyRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->selectRaw("DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Общие итоги
        $totals = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->selectRaw("
                SUM(total) as revenue,
                COUNT(*) as orders,
                AVG(total) as average_check,
                SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) as cash,
                SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END) as card,
                SUM(CASE WHEN payment_method = 'online' THEN total ELSE 0 END) as online
            ")
            ->first();

        // Возвраты
        $refunds = CashOperation::where('restaurant_id', $restaurantId)
            ->where('type', 'expense')
            ->where('category', 'refund')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
                'totals' => [
                    'revenue' => (float) $totals->revenue,
                    'orders' => (int) $totals->orders,
                    'average_check' => round((float) $totals->average_check, 2),
                    'cash' => (float) $totals->cash,
                    'card' => (float) $totals->card,
                    'online' => (float) $totals->online,
                    'refunds' => (float) $refunds,
                    'net_revenue' => (float) $totals->revenue - (float) $refunds,
                ],
                'daily' => $dailyRevenue,
            ],
        ]);
    }

    /**
     * Топ блюд по выручке
     */
    public function topDishes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $dateFrom = $request->input('date_from', Carbon::today()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $limit = $request->input('limit', 10);

        $topDishes = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->selectRaw('
                dishes.id,
                dishes.name,
                SUM(order_items.quantity) as quantity,
                SUM(order_items.total) as revenue
            ')
            ->groupBy('dishes.id', 'dishes.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topDishes,
        ]);
    }

    /**
     * Сводка по способам оплаты
     */
    public function paymentMethodsSummary(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $dateFrom = $request->input('date_from', Carbon::today()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());

        $summary = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->selectRaw('
                payment_method,
                COUNT(*) as count,
                SUM(total) as amount
            ')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $total = $summary->sum('amount');

        $result = collect(['cash', 'card', 'online'])->map(function ($method) use ($summary, $total) {
            $data = $summary->get($method);
            return [
                'method' => $method,
                'label' => match($method) {
                    'cash' => 'Наличные',
                    'card' => 'Карта',
                    'online' => 'Онлайн',
                },
                'count' => $data ? (int) $data->count : 0,
                'amount' => $data ? (float) $data->amount : 0,
                'percentage' => $total > 0 ? round(($data ? $data->amount : 0) / $total * 100, 1) : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'methods' => $result,
                'total' => $total,
            ],
        ]);
    }

    /**
     * ===========================================
     * BACKOFFICE: Финансовые транзакции
     * ===========================================
     */

    /**
     * Список финансовых транзакций для бэк-офиса
     */
    public function transactions(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $operations = CashOperation::where('restaurant_id', $restaurantId)
            ->with('shift')
            ->orderByDesc('created_at')
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $operations,
        ]);
    }

    /**
     * Создать финансовую транзакцию
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense,deposit,withdrawal',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string|max:255',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $shift = CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        $operation = CashOperation::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? '',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $operation,
        ]);
    }

    /**
     * Обновить транзакцию
     */
    public function updateTransaction(Request $request, CashOperation $transaction): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $transaction->update($validated);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * Удалить транзакцию
     */
    public function destroyTransaction(CashOperation $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Транзакция удалена',
        ]);
    }

    /**
     * Категории расходов/доходов
     */
    public function categories(Request $request): JsonResponse
    {
        // Заглушка - категории пока не реализованы как отдельная модель
        $categories = [
            ['id' => 1, 'name' => 'Продажи', 'type' => 'income', 'color' => '#10B981'],
            ['id' => 2, 'name' => 'Возвраты', 'type' => 'expense', 'color' => '#EF4444'],
            ['id' => 3, 'name' => 'Закупки', 'type' => 'expense', 'color' => '#F59E0B'],
            ['id' => 4, 'name' => 'Зарплаты', 'type' => 'expense', 'color' => '#8B5CF6'],
            ['id' => 5, 'name' => 'Аренда', 'type' => 'expense', 'color' => '#6366F1'],
            ['id' => 6, 'name' => 'Прочее', 'type' => 'expense', 'color' => '#6B7280'],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Создать категорию
     */
    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }

    /**
     * Обновить категорию
     */
    public function updateCategory(Request $request, int $category): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }

    /**
     * Удалить категорию
     */
    public function destroyCategory(int $category): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }

    /**
     * Статистика финансов для бэк-офиса
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = Carbon::today();

        $todayRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total');

        $monthRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->sum('total');

        $shift = CashShift::getCurrentShift($restaurantId);

        return response()->json([
            'success' => true,
            'data' => [
                'today_revenue' => $todayRevenue,
                'month_revenue' => $monthRevenue,
                'current_shift' => $shift ? [
                    'id' => $shift->id,
                    'cash_balance' => $shift->cash_balance,
                    'total_revenue' => $shift->total_revenue,
                ] : null,
            ],
        ]);
    }

    /**
     * Финансовый отчёт
     */
    public function report(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $dateFrom = $request->input('date_from', Carbon::today()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());

        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->get();

        $revenue = $orders->sum('total');
        $ordersCount = $orders->count();
        $avgCheck = $ordersCount > 0 ? $revenue / $ordersCount : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'revenue' => $revenue,
                'orders_count' => $ordersCount,
                'avg_check' => round($avgCheck, 2),
                'by_day' => $orders->groupBy(fn($o) => $o->created_at->format('Y-m-d'))
                    ->map(fn($g) => ['date' => $g->first()->created_at->format('Y-m-d'), 'revenue' => $g->sum('total'), 'orders' => $g->count()])
                    ->values(),
            ],
        ]);
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
