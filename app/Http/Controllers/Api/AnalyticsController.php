<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Domain\Order\Enums\OrderStatus;

class AnalyticsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // ДАШБОРД АНАЛИТИКИ
    // ==========================================

    public function dashboard(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId);
        $yesterday = TimeHelper::yesterday($restaurantId);
        $weekAgo = TimeHelper::now($restaurantId)->subWeek();
        $monthStart = TimeHelper::startOfMonth($restaurantId);

        // Сегодня vs вчера
        $todayStats = $this->getPeriodStats($restaurantId, $today->format('Y-m-d'), $today->format('Y-m-d'));
        $yesterdayStats = $this->getPeriodStats($restaurantId, $yesterday->format('Y-m-d'), $yesterday->format('Y-m-d'));

        // Эта неделя
        $weekStats = $this->getPeriodStats($restaurantId, $weekAgo->format('Y-m-d'), $today->format('Y-m-d'));

        // Этот месяц
        $monthStats = $this->getPeriodStats($restaurantId, $monthStart->format('Y-m-d'), $today->format('Y-m-d'));

        // Топ-5 блюд за неделю
        $topDishes = $this->getTopDishes($restaurantId, $weekAgo->format('Y-m-d'), $today->format('Y-m-d'), 5);

        // Тренд по дням (последние 7 дней)
        $dailyTrend = Order::where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('created_at', '>=', $weekAgo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayStats,
                'yesterday' => $yesterdayStats,
                'week' => $weekStats,
                'month' => $monthStats,
                'top_dishes' => $topDishes,
                'daily_trend' => $dailyTrend,
                'today_vs_yesterday' => [
                    'revenue_diff' => round($todayStats['revenue'] - $yesterdayStats['revenue'], 2),
                    'orders_diff' => $todayStats['orders_count'] - $yesterdayStats['orders_count'],
                ],
            ],
        ]);
    }

    // ==========================================
    // СРАВНЕНИЕ ПЕРИОДОВ
    // ==========================================

    public function periodComparison(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period1From = $request->input('period1_from', TimeHelper::startOfWeek($restaurantId)->format('Y-m-d'));
        $period1To = $request->input('period1_to', TimeHelper::now($restaurantId)->endOfWeek()->format('Y-m-d'));
        $period2From = $request->input('period2_from', TimeHelper::now($restaurantId)->subWeek()->startOfWeek()->format('Y-m-d'));
        $period2To = $request->input('period2_to', TimeHelper::now($restaurantId)->subWeek()->endOfWeek()->format('Y-m-d'));

        $period1 = $this->getPeriodStats($restaurantId, $period1From, $period1To);
        $period2 = $this->getPeriodStats($restaurantId, $period2From, $period2To);

        // Расчёт изменений
        $changes = [];
        foreach (['revenue', 'orders_count', 'avg_check', 'items_sold', 'unique_customers'] as $metric) {
            $val1 = $period1[$metric] ?? 0;
            $val2 = $period2[$metric] ?? 0;
            $diff = $val1 - $val2;
            $percent = $val2 > 0 ? round(($diff / $val2) * 100, 1) : ($val1 > 0 ? 100 : 0);

            $changes[$metric] = [
                'period1' => $val1,
                'period2' => $val2,
                'diff' => round($diff, 2),
                'percent' => $percent,
                'trend' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'same'),
            ];
        }

        // Топ блюда по периодам
        $topDishes1 = $this->getTopDishes($restaurantId, $period1From, $period1To, 10);
        $topDishes2 = $this->getTopDishes($restaurantId, $period2From, $period2To, 10);

        return response()->json([
            'success' => true,
            'data' => [
                'period1' => [
                    'from' => $period1From,
                    'to' => $period1To,
                    'stats' => $period1,
                    'top_dishes' => $topDishes1,
                ],
                'period2' => [
                    'from' => $period2From,
                    'to' => $period2To,
                    'stats' => $period2,
                    'top_dishes' => $topDishes2,
                ],
                'changes' => $changes,
            ],
        ]);
    }

    // ==========================================
    // ОТЧЁТ ПО ОФИЦИАНТАМ
    // ==========================================

    public function waiterReport(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $from = $request->input('from', TimeHelper::startOfMonth($restaurantId)->format('Y-m-d'));
        $to = $request->input('to', TimeHelper::now($restaurantId)->format('Y-m-d'));

        // One JOIN+GROUP BY query instead of N+1 per waiter
        $waiterStats = Order::where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', OrderStatus::COMPLETED->value)
            ->whereBetween('orders.created_at', [$from, $to . ' 23:59:59'])
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('users.role', 'waiter')
            ->selectRaw("
                orders.user_id,
                users.name,
                COUNT(*) as orders_count,
                SUM(orders.total) as revenue,
                AVG(orders.total) as avg_check
            ")
            ->groupBy('orders.user_id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        // Batch items sold
        $orderIdsByWaiter = Order::where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('user_id')
            ->selectRaw("user_id, GROUP_CONCAT(id) as order_ids")
            ->groupBy('user_id')
            ->pluck('order_ids', 'user_id');

        $allOrderIds = $waiterStats->pluck('user_id')->flatMap(function ($userId) use ($orderIdsByWaiter) {
            $ids = $orderIdsByWaiter->get($userId, '');
            return $ids ? explode(',', $ids) : [];
        })->toArray();

        $itemsSoldByOrder = !empty($allOrderIds)
            ? OrderItem::whereIn('order_id', $allOrderIds)
                ->selectRaw("order_id, SUM(quantity) as qty")
                ->groupBy('order_id')
                ->pluck('qty', 'order_id')
            : collect();

        $waiters = $waiterStats->map(function ($ws) use ($orderIdsByWaiter, $itemsSoldByOrder) {
            $orderIds = $orderIdsByWaiter->get($ws->user_id, '');
            $ids = $orderIds ? explode(',', $orderIds) : [];
            $itemsSold = collect($ids)->sum(fn($id) => $itemsSoldByOrder->get($id, 0));

            return [
                'id' => $ws->user_id,
                'name' => $ws->name,
                'orders_count' => $ws->orders_count,
                'revenue' => round($ws->revenue, 2),
                'avg_check' => round($ws->avg_check, 2),
                'tips' => 0,
                'items_sold' => $itemsSold,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'waiters' => $waiters,
                'period' => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    // ==========================================
    // ПОЧАСОВАЯ АНАЛИТИКА
    // ==========================================

    public function hourlyAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $date = $request->input('date', TimeHelper::today($restaurantId)->format('Y-m-d'));
        $period = $request->input('period', 'day'); // day, week, month

        $query = Order::where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value);

        if ($period === 'day') {
            $query->whereDate('created_at', $date);
        } elseif ($period === 'week') {
            $query->whereBetween('created_at', [
                Carbon::parse($date)->startOfWeek(),
                Carbon::parse($date)->endOfWeek(),
            ]);
        } else {
            $query->whereBetween('created_at', [
                Carbon::parse($date)->startOfMonth(),
                Carbon::parse($date)->endOfMonth(),
            ]);
        }

        $hourlyData = $query->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // Заполняем все часы
        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $data = $hourlyData->get($h);
            $hours[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'orders' => $data->orders ?? 0,
                'revenue' => $data->revenue ?? 0,
            ];
        }

        // Пиковые часы
        $peakHours = collect($hours)->sortByDesc('orders')->take(3)->pluck('hour')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'hours' => $hours,
                'peak_hours' => $peakHours,
                'total_orders' => collect($hours)->sum('orders'),
                'total_revenue' => collect($hours)->sum('revenue'),
            ],
        ]);
    }

    // ==========================================
    // АНАЛИЗ КАТЕГОРИЙ
    // ==========================================

    public function categoryAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $from = $request->input('from', TimeHelper::startOfMonth($restaurantId)->format('Y-m-d'));
        $to = $request->input('to', TimeHelper::now($restaurantId)->format('Y-m-d'));

        // One JOIN+GROUP BY query instead of N+1 per category
        $salesByCategory = OrderItem::join('dishes', 'dishes.id', '=', 'order_items.dish_id')
            ->whereHas('order', function ($q) use ($restaurantId, $from, $to) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', OrderStatus::COMPLETED->value)
                  ->whereBetween('created_at', [$from, $to . ' 23:59:59']);
            })
            ->selectRaw("dishes.category_id, SUM(order_items.quantity) as qty, SUM(order_items.total) as revenue")
            ->groupBy('dishes.category_id')
            ->get()
            ->keyBy('category_id');

        $categories = Category::where('restaurant_id', $restaurantId)
            ->withCount('dishes')
            ->get()
            ->map(function ($category) use ($salesByCategory) {
                $sales = $salesByCategory->get($category->id);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'dishes_count' => $category->dishes_count,
                    'quantity' => $sales->qty ?? 0,
                    'revenue' => $sales->revenue ?? 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $totalRevenue = $categories->sum('revenue');

        // Добавляем процент
        $categories = $categories->map(function ($cat) use ($totalRevenue) {
            $cat['percent'] = $totalRevenue > 0 ? round(($cat['revenue'] / $totalRevenue) * 100, 1) : 0;
            return $cat;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'total_revenue' => $totalRevenue,
            ],
        ]);
    }

    // ==========================================
    // ПРИВАТНЫЕ МЕТОДЫ
    // ==========================================

    private function getPeriodStats($restaurantId, $from, $to): array
    {
        $cacheKey = "analytics:period_stats:{$restaurantId}:{$from}:{$to}";

        return Cache::remember($cacheKey, 300, function () use ($restaurantId, $from, $to) {
            // SQL aggregation instead of ->get() + PHP processing
            $stats = Order::where('restaurant_id', $restaurantId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->selectRaw("
                    SUM(total) as revenue,
                    COUNT(*) as orders_count,
                    AVG(total) as avg_check,
                    COUNT(DISTINCT customer_id) as unique_customers
                ")
                ->first();

            $itemsSold = OrderItem::whereHas('order', function ($q) use ($restaurantId, $from, $to) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', OrderStatus::COMPLETED->value)
                  ->whereBetween('created_at', [$from, $to . ' 23:59:59']);
            })->sum('quantity');

            return [
                'revenue' => round($stats->revenue ?? 0, 2),
                'orders_count' => $stats->orders_count ?? 0,
                'avg_check' => round($stats->avg_check ?? 0, 2),
                'items_sold' => $itemsSold,
                'unique_customers' => $stats->unique_customers ?? 0,
            ];
        });
    }

    private function getTopDishes($restaurantId, $from, $to, $limit = 10): array
    {
        return OrderItem::select('dish_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total) as revenue'))
            ->whereHas('order', function ($q) use ($restaurantId, $from, $to) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', OrderStatus::COMPLETED->value)
                  ->whereBetween('created_at', [$from, $to . ' 23:59:59']);
            })
            ->groupBy('dish_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->with('dish:id,name')
            ->get()
            ->map(fn($item) => [
                'dish_id' => $item->dish_id,
                'name' => $item->dish?->name ?? 'Удалено',
                'quantity' => $item->qty,
                'revenue' => $item->revenue,
            ])
            ->toArray();
    }
}
