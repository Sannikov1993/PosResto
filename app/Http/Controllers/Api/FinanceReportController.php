<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashOperation;
use App\Models\CashShift;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Финансовая аналитика и отчёты.
 *
 * Выделено из FinanceController для разделения ответственности:
 * - FinanceController — кассовые смены и операции (POS)
 * - FinanceReportController — аналитика и отчёты (read-only)
 * - FinanceTransactionController — транзакции бэк-офиса
 */
class FinanceReportController extends Controller
{
    use Traits\ResolvesRestaurantId;

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

        foreach ($shifts as $shift) {
            if ($shift->isOpen()) {
                $shift->append('current_cash');
            } else {
                $shift->setAttribute('current_cash', (float) $shift->expected_amount);
            }
        }

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

        $dailyRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->selectRaw("DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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

        $refunds = CashOperation::where('restaurant_id', $restaurantId)
            ->where('type', 'expense')
            ->where('category', 'refund')
            ->whereBetween('created_at', [$dateFrom, Carbon::parse($dateTo)->endOfDay()])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
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
}
