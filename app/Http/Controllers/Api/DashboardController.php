<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\Reservation;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Основная статистика дашборда
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId);

        // Агрегация по статусам одним запросом вместо ->get() + PHP-фильтрации
        $statusStats = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->selectRaw("status, COUNT(*) as cnt, SUM(total) as total_sum")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $revenueStats = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->selectRaw("SUM(total) as revenue, AVG(total) as avg_check")
            ->first();

        $totalOrders = array_sum($statusStats);

        $stats = [
            'new' => $statusStats['new'] ?? 0,
            'cooking' => $statusStats['cooking'] ?? 0,
            'ready' => $statusStats['ready'] ?? 0,
            'completed' => $statusStats['completed'] ?? 0,
            'cancelled' => $statusStats['cancelled'] ?? 0,
            'total_orders' => $totalOrders,
            'revenue_today' => $revenueStats->revenue ?? 0,
            'avg_check' => $revenueStats->avg_check ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Детальная статистика
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 'today'); // today, week, month, year

        $startDate = match($period) {
            'today' => TimeHelper::today($restaurantId),
            'yesterday' => TimeHelper::yesterday($restaurantId),
            'week' => TimeHelper::startOfWeek($restaurantId),
            'month' => TimeHelper::startOfMonth($restaurantId),
            'year' => TimeHelper::startOfYear($restaurantId),
            default => TimeHelper::today($restaurantId),
        };

        $endDate = match($period) {
            'yesterday' => TimeHelper::yesterday($restaurantId)->endOfDay(),
            default => TimeHelper::now($restaurantId),
        };

        // Агрегация SQL вместо ->get() + PHP-фильтрация
        $baseQuery = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $statusCounts = (clone $baseQuery)
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $paidStats = (clone $baseQuery)
            ->where('payment_status', 'paid')
            ->selectRaw("COUNT(*) as cnt, SUM(total) as revenue, AVG(total) as avg_check")
            ->first();

        $completedByType = (clone $baseQuery)
            ->where('status', 'completed')
            ->selectRaw("type, COUNT(*) as cnt")
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->toArray();

        $paidByMethod = (clone $baseQuery)
            ->where('payment_status', 'paid')
            ->selectRaw("payment_method, SUM(total) as total_sum")
            ->groupBy('payment_method')
            ->pluck('total_sum', 'payment_method')
            ->toArray();

        $todayRevenue = $paidStats->revenue ?? 0;
        $paidCount = $paidStats->cnt ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_orders' => array_sum($statusCounts),
                'completed_orders' => $statusCounts['completed'] ?? 0,
                'cancelled_orders' => $statusCounts['cancelled'] ?? 0,
                'revenue' => $todayRevenue,
                // Для совместимости с frontend
                'todayRevenue' => $todayRevenue,
                'ordersToday' => $paidCount,
                'avgCheck' => $paidCount > 0 ? round($todayRevenue / $paidCount, 2) : 0,
                'avg_check' => $paidStats->avg_check ?? 0,
                'by_type' => [
                    'dine_in' => $completedByType['dine_in'] ?? 0,
                    'delivery' => $completedByType['delivery'] ?? 0,
                    'pickup' => $completedByType['pickup'] ?? 0,
                ],
                'by_payment' => [
                    'cash' => $paidByMethod['cash'] ?? 0,
                    'card' => $paidByMethod['card'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Данные о продажах для графика
     */
    public function sales(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 'week'); // week, month

        $days = $period === 'month' ? 30 : 7;
        $dateFrom = TimeHelper::today($restaurantId)->subDays($days - 1);
        $dateTo = TimeHelper::today($restaurantId);

        $cacheKey = "dashboard:sales:{$restaurantId}:{$period}:{$dateTo->toDateString()}";

        $sales = Cache::remember($cacheKey, 300, function () use ($restaurantId, $dateFrom, $dateTo, $days) {
            // Один GROUP BY запрос вместо N запросов по дням
            $dailyData = Order::where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->selectRaw("DATE(created_at) as date, COUNT(*) as orders_count, SUM(total) as revenue")
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $sales = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = TimeHelper::today($restaurantId)->subDays($i);
                $dateKey = $date->format('Y-m-d');
                $dayData = $dailyData->get($dateKey);

                $sales[] = [
                    'date' => $date->format('d.m'),
                    'day' => $date->isoFormat('dd'),
                    'orders' => $dayData->orders_count ?? 0,
                    'revenue' => (float) ($dayData->revenue ?? 0),
                ];
            }
            return $sales;
        });

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * Топ популярных блюд
     */
    public function popularDishes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);

        $startDate = match($period) {
            'today' => TimeHelper::today($restaurantId),
            'week' => TimeHelper::startOfWeek($restaurantId),
            'month' => TimeHelper::startOfMonth($restaurantId),
            'year' => TimeHelper::startOfYear($restaurantId),
            default => TimeHelper::startOfMonth($restaurantId),
        };

        $popularDishes = OrderItem::select(
                'dish_id',
                'name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as order_count')
            )
            ->whereHas('order', function ($q) use ($restaurantId, $startDate) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', 'completed')
                  ->where('created_at', '>=', $startDate);
            })
            ->groupBy('dish_id', 'name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $popularDishes,
        ]);
    }

    /**
     * Отчёт по продажам
     */
    public function salesReport(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $startDate = $request->input('start_date', TimeHelper::startOfMonth($restaurantId)->toDateString());
        $endDate = $request->input('end_date', TimeHelper::now($restaurantId)->toDateString());

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, TimeHelper::parse($endDate, $restaurantId)->endOfDay()])
            ->where('status', 'completed')
            ->get();

        // Группировка по дням
        $byDay = $orders->groupBy(function ($order) {
            return $order->created_at->format('Y-m-d');
        })->map(function ($dayOrders, $date) {
            return [
                'date' => $date,
                'orders' => $dayOrders->count(),
                'revenue' => $dayOrders->sum('total'),
                'avg_check' => $dayOrders->avg('total'),
            ];
        })->values();

        // Группировка по типам
        $byType = [
            'dine_in' => ['orders' => 0, 'revenue' => 0],
            'delivery' => ['orders' => 0, 'revenue' => 0],
            'pickup' => ['orders' => 0, 'revenue' => 0],
        ];
        foreach ($orders as $order) {
            if (isset($byType[$order->type])) {
                $byType[$order->type]['orders']++;
                $byType[$order->type]['revenue'] += $order->total;
            }
        }

        // Группировка по способам оплаты
        $byPayment = [
            'cash' => ['orders' => 0, 'revenue' => 0],
            'card' => ['orders' => 0, 'revenue' => 0],
        ];
        foreach ($orders as $order) {
            $method = $order->payment_method ?? 'cash';
            if (isset($byPayment[$method])) {
                $byPayment[$method]['orders']++;
                $byPayment[$method]['revenue'] += $order->total;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'summary' => [
                    'total_orders' => $orders->count(),
                    'total_revenue' => $orders->sum('total'),
                    'avg_check' => $orders->avg('total') ?? 0,
                ],
                'by_day' => $byDay,
                'by_type' => $byType,
                'by_payment' => $byPayment,
            ],
        ]);
    }

    /**
     * Отчёт по блюдам
     */
    public function dishesReport(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $startDate = $request->input('start_date', TimeHelper::startOfMonth($restaurantId)->toDateString());
        $endDate = $request->input('end_date', TimeHelper::now($restaurantId)->toDateString());

        $dishes = OrderItem::select(
                'dish_id',
                'name',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(price) as avg_price'),
                DB::raw('COUNT(DISTINCT order_id) as order_count')
            )
            ->whereHas('order', function ($q) use ($restaurantId, $startDate, $endDate) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', 'completed')
                  ->whereBetween('created_at', [$startDate, TimeHelper::parse($endDate, $restaurantId)->endOfDay()]);
            })
            ->groupBy('dish_id', 'name')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = $dishes->sum('revenue');

        $dishes = $dishes->map(function ($dish) use ($totalRevenue) {
            $dish->percent = $totalRevenue > 0 ? round(($dish->revenue / $totalRevenue) * 100, 1) : 0;
            return $dish;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_revenue' => $totalRevenue,
                'total_dishes' => $dishes->sum('quantity'),
                'dishes' => $dishes,
            ],
        ]);
    }

    /**
     * Отчёт по часам
     */
    public function hourlyReport(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $date = $request->input('date', TimeHelper::today($restaurantId)->toDateString());

        $cacheKey = "dashboard:hourly:{$restaurantId}:{$date}";

        $hourlyData = Cache::remember($cacheKey, 300, function () use ($restaurantId, $date) {
            $tz = TimeHelper::getTimezone($restaurantId);
            $dayStart = Carbon::parse($date, $tz)->startOfDay();
            $dayEnd = Carbon::parse($date, $tz)->endOfDay();

            // Один GROUP BY HOUR запрос вместо 24 отдельных
            // SQLite: strftime('%H'), MySQL: HOUR()
            $hourExpr = DB::getDriverName() === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER)"
                : "HOUR(created_at)";

            $hourlyStats = Order::where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'completed')
                ->selectRaw("{$hourExpr} as hour, COUNT(*) as orders_count, SUM(total) as revenue")
                ->groupBy('hour')
                ->get()
                ->keyBy('hour');

            $data = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $stats = $hourlyStats->get($hour);
                $data[] = [
                    'hour' => sprintf('%02d:00', $hour),
                    'orders' => $stats->orders_count ?? 0,
                    'revenue' => (float) ($stats->revenue ?? 0),
                ];
            }
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $hourlyData,
        ]);
    }

    /**
     * Краткая статистика для боковой панели календаря
     */
    public function briefStats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId);
        $yesterday = TimeHelper::yesterday($restaurantId);

        // Заказы за вчера (оплаченные)
        $yesterdayOrders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $yesterday)
            ->where('payment_status', 'paid')
            ->get();

        // Заказы за сегодня (оплаченные)
        $todayOrders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->get();

        // Все брони на сегодня
        $todayReservations = Reservation::where('restaurant_id', $restaurantId)
            ->whereDate('date', $today)
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->get();

        // Ожидающие брони (не посажены)
        $pendingReservations = $todayReservations->whereIn('status', ['pending', 'confirmed'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'yesterday' => [
                    'orders_count' => $yesterdayOrders->count(),
                    'total' => (float) $yesterdayOrders->sum('total'),
                ],
                'today' => [
                    'orders_count' => $todayOrders->count(),
                    'total' => (float) $todayOrders->sum('total'),
                    'reservations_count' => $todayReservations->count(),
                    'pending_reservations' => $pendingReservations,
                ],
            ],
        ]);
    }

    /**
     * Получить данные ресторана
     *
     * БЕЗОПАСНОСТЬ: Сначала проверяем права, потом ищем ресторан
     */
    public function getRestaurant(int $id): JsonResponse
    {
        // БЕЗОПАСНОСТЬ: ищем ресторан с проверкой доступа в одном запросе
        $restaurant = $this->findAccessibleRestaurant($id);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $restaurant,
        ]);
    }

    /**
     * Обновить данные ресторана
     *
     * БЕЗОПАСНОСТЬ: Сначала проверяем права, потом ищем ресторан
     */
    public function updateRestaurant(Request $request, int $id): JsonResponse
    {
        // БЕЗОПАСНОСТЬ: ищем ресторан с проверкой доступа в одном запросе
        $restaurant = $this->findAccessibleRestaurant($id);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'website' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'logo' => 'nullable|string|max:255',
            'currency' => 'sometimes|string|max:10',
            'timezone' => 'sometimes|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'service_charge' => 'nullable|numeric|min:0|max:100',
            'working_hours' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $restaurant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Настройки обновлены',
            'data' => $restaurant->fresh(),
        ]);
    }

    /**
     * Найти ресторан с проверкой доступа (безопасный паттерн)
     *
     * БЕЗОПАСНОСТЬ: Проверка доступа и поиск в одном запросе
     * - Superadmin: любой ресторан
     * - Tenant owner: рестораны своей сети
     * - Обычный пользователь: только свой ресторан
     */
    protected function findAccessibleRestaurant(int $id): ?Restaurant
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // Superadmin имеет доступ ко всем
        if ($user->is_superadmin ?? false) {
            return Restaurant::find($id);
        }

        // Tenant owner имеет доступ ко всем ресторанам своей сети
        if (($user->is_tenant_owner ?? false) && $user->tenant_id) {
            return Restaurant::where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
        }

        // Обычный пользователь — только свой ресторан
        if ($user->restaurant_id === $id) {
            return Restaurant::find($id);
        }

        return null;
    }
}
