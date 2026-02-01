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

        // Заказы за сегодня
        $todayOrders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->get();

        // Статистика по статусам
        $stats = [
            'new' => $todayOrders->where('status', 'new')->count(),
            'cooking' => $todayOrders->where('status', 'cooking')->count(),
            'ready' => $todayOrders->where('status', 'ready')->count(),
            'completed' => $todayOrders->where('status', 'completed')->count(),
            'cancelled' => $todayOrders->where('status', 'cancelled')->count(),
            'total_orders' => $todayOrders->count(),
            'revenue_today' => $todayOrders->where('status', 'completed')->sum('total'),
            'avg_check' => $todayOrders->where('status', 'completed')->avg('total') ?? 0,
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

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $completedOrders = $orders->where('status', 'completed');

        // Считаем также оплаченные заказы (paid)
        $paidOrders = $orders->where('payment_status', 'paid');
        $todayRevenue = $paidOrders->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_orders' => $orders->count(),
                'completed_orders' => $completedOrders->count(),
                'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
                'revenue' => $todayRevenue,
                // Для совместимости с frontend
                'todayRevenue' => $todayRevenue,
                'ordersToday' => $paidOrders->count(),
                'avgCheck' => $paidOrders->count() > 0 ? round($todayRevenue / $paidOrders->count(), 2) : 0,
                'avg_check' => $paidOrders->avg('total') ?? 0,
                'by_type' => [
                    'dine_in' => $completedOrders->where('type', 'dine_in')->count(),
                    'delivery' => $completedOrders->where('type', 'delivery')->count(),
                    'pickup' => $completedOrders->where('type', 'pickup')->count(),
                ],
                'by_payment' => [
                    'cash' => $paidOrders->where('payment_method', 'cash')->sum('total'),
                    'card' => $paidOrders->where('payment_method', 'card')->sum('total'),
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
        $sales = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = TimeHelper::today($restaurantId)->subDays($i);
            
            $dayOrders = Order::where('restaurant_id', $restaurantId)
                ->whereDate('created_at', $date)
                ->where('status', 'completed')
                ->get();

            $sales[] = [
                'date' => $date->format('d.m'),
                'day' => $date->isoFormat('dd'),
                'orders' => $dayOrders->count(),
                'revenue' => (float) $dayOrders->sum('total'),
            ];
        }

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

        $hourlyData = [];
        $tz = TimeHelper::getTimezone($restaurantId);
        for ($hour = 0; $hour < 24; $hour++) {
            $startHour = Carbon::parse($date, $tz)->setHour($hour)->setMinute(0)->setSecond(0);
            $endHour = Carbon::parse($date, $tz)->setHour($hour)->setMinute(59)->setSecond(59);

            $orders = Order::where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$startHour, $endHour])
                ->where('status', 'completed')
                ->get();

            $hourlyData[] = [
                'hour' => sprintf('%02d:00', $hour),
                'orders' => $orders->count(),
                'revenue' => (float) $orders->sum('total'),
            ];
        }

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
     */
    public function getRestaurant(int $id): JsonResponse
    {
        $restaurant = Restaurant::find($id);

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
     */
    public function updateRestaurant(Request $request, int $id): JsonResponse
    {
        $restaurant = Restaurant::find($id);

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
}
