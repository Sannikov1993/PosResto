<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\User;
use App\Models\Customer;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Services\RFMAnalysisService;
use App\Services\ChurnAnalysisService;
use App\Services\ForecastService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;

class AnalyticsController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // ==========================================
    // ABC-АНАЛИЗ МЕНЮ
    // ==========================================

    public function abcAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 30); // дней
        $metric = $request->input('metric', 'revenue'); // revenue или quantity

        $dateFrom = TimeHelper::now($restaurantId)->subDays($period);

        // Получаем продажи по блюдам
        $salesData = OrderItem::select(
                'dish_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as orders_count')
            )
            ->whereHas('order', function ($q) use ($restaurantId, $dateFrom) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', OrderStatus::COMPLETED->value)
                  ->where('created_at', '>=', $dateFrom);
            })
            ->groupBy('dish_id')
            ->with('dish:id,name,category_id,price')
            ->get();

        if ($salesData->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['items' => [], 'summary' => null],
            ]);
        }

        // Считаем общие суммы
        $totalRevenue = $salesData->sum('total_revenue');
        $totalQty = $salesData->sum('total_qty');

        // Сортируем по выбранной метрике
        $sorted = $salesData->sortByDesc($metric === 'revenue' ? 'total_revenue' : 'total_qty')->values();

        // ABC классификация
        $cumulative = 0;
        $items = [];
        
        foreach ($sorted as $item) {
            $value = $metric === 'revenue' ? $item->total_revenue : $item->total_qty;
            $total = $metric === 'revenue' ? $totalRevenue : $totalQty;
            $percent = $total > 0 ? ($value / $total) * 100 : 0;
            $cumulative += $percent;

            // Определяем категорию ABC
            if ($cumulative <= 80) {
                $category = 'A';
            } elseif ($cumulative <= 95) {
                $category = 'B';
            } else {
                $category = 'C';
            }

            $items[] = [
                'dish_id' => $item->dish_id,
                'dish_name' => $item->dish?->name ?? 'Удалено',
                'category_name' => $item->dish?->category?->name ?? '',
                'price' => $item->dish?->price ?? 0,
                'quantity' => $item->total_qty,
                'revenue' => $item->total_revenue,
                'orders_count' => $item->orders_count,
                'percent' => round($percent, 2),
                'cumulative_percent' => round($cumulative, 2),
                'abc_category' => $category,
            ];
        }

        // Статистика по категориям
        $summary = [
            'A' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
            'B' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
            'C' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
        ];

        foreach ($items as $item) {
            $cat = $item['abc_category'];
            $summary[$cat]['count']++;
            $summary[$cat]['revenue'] += $item['revenue'];
            $summary[$cat]['quantity'] += $item['quantity'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => $summary,
                'total_revenue' => $totalRevenue,
                'total_quantity' => $totalQty,
                'period_days' => $period,
                'metric' => $metric,
            ],
        ]);
    }

    // ==========================================
    // ПРОГНОЗ ПРОДАЖ
    // ==========================================

    public function salesForecast(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $forecastDays = $request->input('days', 7);

        // Берём данные за последние 8 недель для анализа паттернов
        $weeksBack = 8;
        $dateFrom = TimeHelper::now($restaurantId)->subWeeks($weeksBack)->startOfDay();

        // Получаем дневные продажи
        $dailySales = Order::where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('created_at', '>=', $dateFrom)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date', 'day_of_week')
            ->orderBy('date')
            ->get();

        if ($dailySales->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['forecast' => [], 'historical' => []],
            ]);
        }

        // Средние по дням недели
        $avgByDayOfWeek = [];
        $countByDayOfWeek = [];
        
        foreach ($dailySales as $day) {
            $dow = $day->day_of_week;
            if (!isset($avgByDayOfWeek[$dow])) {
                $avgByDayOfWeek[$dow] = ['revenue' => 0, 'orders' => 0];
                $countByDayOfWeek[$dow] = 0;
            }
            $avgByDayOfWeek[$dow]['revenue'] += $day->revenue;
            $avgByDayOfWeek[$dow]['orders'] += $day->orders_count;
            $countByDayOfWeek[$dow]++;
        }

        foreach ($avgByDayOfWeek as $dow => $data) {
            $count = $countByDayOfWeek[$dow];
            $avgByDayOfWeek[$dow]['revenue'] = round($data['revenue'] / $count, 2);
            $avgByDayOfWeek[$dow]['orders'] = round($data['orders'] / $count, 1);
        }

        // Тренд (простая линейная регрессия)
        $n = $dailySales->count();
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        
        foreach ($dailySales->values() as $i => $day) {
            $x = $i;
            $y = $day->revenue;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = 0;
        if ($n > 1 && ($n * $sumX2 - $sumX * $sumX) != 0) {
            $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        }

        // Генерируем прогноз
        $forecast = [];
        $today = TimeHelper::today($restaurantId);
        
        for ($i = 0; $i < $forecastDays; $i++) {
            $date = $today->copy()->addDays($i);
            $dow = $date->dayOfWeek + 1; // Carbon: 0=Sunday, MySQL DAYOFWEEK: 1=Sunday
            
            $baseRevenue = $avgByDayOfWeek[$dow]['revenue'] ?? 0;
            $baseOrders = $avgByDayOfWeek[$dow]['orders'] ?? 0;
            
            // Применяем тренд
            $trendFactor = 1 + ($slope * $i / max(1, $baseRevenue)) * 0.1;
            $trendFactor = max(0.8, min(1.2, $trendFactor)); // Ограничиваем ±20%
            
            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $this->getDayName($date->dayOfWeek),
                'predicted_revenue' => round($baseRevenue * $trendFactor, 2),
                'predicted_orders' => round($baseOrders * $trendFactor),
                'confidence' => $countByDayOfWeek[$dow] ?? 0 >= 4 ? 'high' : 'low',
            ];
        }

        // Исторические данные для графика
        $historical = $dailySales->map(function ($day) {
            return [
                'date' => $day->date,
                'revenue' => $day->revenue,
                'orders' => $day->orders_count,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'forecast' => $forecast,
                'historical' => $historical,
                'avg_by_day' => $avgByDayOfWeek,
                'trend_slope' => round($slope, 2),
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
    // ЭКСПОРТ В EXCEL (CSV)
    // ==========================================

    public function exportSales(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $from = $request->input('from', TimeHelper::startOfMonth($restaurantId)->format('Y-m-d'));
        $to = $request->input('to', TimeHelper::now($restaurantId)->format('Y-m-d'));

        $orders = Order::with(['items.dish', 'table', 'waiter', 'customer'])
            ->where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->orderBy('created_at')
            ->get();

        $filename = "sales_{$from}_{$to}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            
            // BOM для корректного отображения в Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Заголовки
            fputcsv($file, [
                '№ Заказа',
                'Дата',
                'Время',
                'Тип',
                'Стол',
                'Официант',
                'Клиент',
                'Кол-во позиций',
                'Сумма',
                'Способ оплаты',
            ], ';');

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    Carbon::parse($order->created_at)->format('d.m.Y'),
                    Carbon::parse($order->created_at)->format('H:i'),
                    $order->type === OrderType::DINE_IN->value ? 'В зале' : ($order->type === OrderType::DELIVERY->value ? 'Доставка' : 'Самовывоз'),
                    $order->table?->number ?? '-',
                    $order->waiter?->name ?? '-',
                    $order->customer?->name ?? 'Гость',
                    $order->items->sum('quantity'),
                    number_format($order->total, 2, ',', ' '),
                    $order->payment_method === 'cash' ? 'Наличные' : 'Карта',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAbc(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 30);

        // Получаем данные ABC
        $response = $this->abcAnalysis($request);
        $data = json_decode($response->getContent(), true)['data'];

        $filename = "abc_analysis_{$period}d.csv";
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Блюдо',
                'Категория',
                'Цена',
                'Продано шт.',
                'Выручка',
                '% от общей',
                'Накопленный %',
                'ABC',
            ], ';');

            foreach ($data['items'] as $item) {
                fputcsv($file, [
                    $item['dish_name'],
                    $item['category_name'],
                    number_format($item['price'], 2, ',', ' '),
                    $item['quantity'],
                    number_format($item['revenue'], 2, ',', ' '),
                    $item['percent'] . '%',
                    $item['cumulative_percent'] . '%',
                    $item['abc_category'],
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

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

    private function getDayName($dayOfWeek): string
    {
        $days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
        return $days[$dayOfWeek] ?? '';
    }


    // ==========================================
    // RFM-АНАЛИЗ КЛИЕНТОВ
    // ==========================================

    public function rfmAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->analyze($restaurantId, $period);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function rfmSegments(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->getSegmentsSummary($restaurantId, $period);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function rfmSegmentDescriptions(): JsonResponse
    {
        $service = new RFMAnalysisService();
        return response()->json([
            "success" => true,
            "data" => $service->getSegmentDescriptions(),
        ]);
    }

    public function customerRfm(Request $request, int $customerId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->getCustomerRFM($customerId, $restaurantId, $period);

        if (!$data) {
            return response()->json([
                "success" => false,
                "message" => "Клиент не найден или недоступен",
            ], 404);
        }

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function exportRfm(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->analyze($restaurantId, $period);

        $filename = "rfm_analysis_{$period}d.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen("php://output", "w");
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                "Клиент",
                "Телефон",
                "Дней без визита",
                "Заказов",
                "Сумма",
                "R",
                "F",
                "M",
                "RFM",
                "Сегмент",
                "Рекомендация",
            ], ";");

            foreach ($data["customers"] as $customer) {
                fputcsv($file, [
                    $customer["name"],
                    $customer["phone"],
                    $customer["recency_days"],
                    $customer["frequency"],
                    number_format($customer["monetary"], 2, ",", " "),
                    $customer["r_score"],
                    $customer["f_score"],
                    $customer["m_score"],
                    $customer["rfm_score"],
                    $customer["segment"],
                    $customer["action"],
                ], ";");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // АНАЛИЗ ОТТОКА КЛИЕНТОВ
    // ==========================================

    public function churnAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $lookbackDays = $request->input("lookback", 180);

        $service = new ChurnAnalysisService();
        $data = $service->analyze($restaurantId, $lookbackDays);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function churnAlerts(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $service = new ChurnAnalysisService();
        $data = $service->getAlerts($restaurantId);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function churnTrend(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $months = $request->input("months", 6);

        $service = new ChurnAnalysisService();
        $data = $service->calculateChurnTrend($restaurantId, $months);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function exportChurn(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);

        $service = new ChurnAnalysisService();
        $data = $service->analyze($restaurantId);

        $filename = "churn_analysis.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen("php://output", "w");
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                "Клиент",
                "Телефон",
                "Дней без визита",
                "Всего заказов",
                "Сумма покупок",
                "Вероятность оттока",
                "Уровень риска",
                "CLV",
                "Рекомендация",
            ], ";");

            foreach ($data["at_risk"] as $customer) {
                fputcsv($file, [
                    $customer["name"],
                    $customer["phone"],
                    $customer["last_order_days"],
                    $customer["total_orders"],
                    number_format($customer["total_spent"], 2, ",", " "),
                    $customer["churn_probability"] . "%",
                    $customer["risk_level"],
                    number_format($customer["clv"], 2, ",", " "),
                    $customer["recommended_action"],
                ], ";");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // УЛУЧШЕННЫЙ ПРОГНОЗ
    // ==========================================

    public function enhancedForecast(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecast($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastByCategory(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastByCategory($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastIngredients(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastIngredients($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastStaff(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastStaff($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }
}