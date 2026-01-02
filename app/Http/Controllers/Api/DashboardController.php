<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Основная статистика дашборда
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $today = Carbon::today();

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
        $restaurantId = $request->input('restaurant_id', 1);
        $period = $request->input('period', 'today'); // today, week, month, year

        $startDate = match($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::today(),
        };

        $endDate = match($period) {
            'yesterday' => Carbon::yesterday()->endOfDay(),
            default => Carbon::now(),
        };

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $completedOrders = $orders->where('status', 'completed');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_orders' => $orders->count(),
                'completed_orders' => $completedOrders->count(),
                'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
                'revenue' => $completedOrders->sum('total'),
                'avg_check' => $completedOrders->avg('total') ?? 0,
                'by_type' => [
                    'dine_in' => $completedOrders->where('type', 'dine_in')->count(),
                    'delivery' => $completedOrders->where('type', 'delivery')->count(),
                    'pickup' => $completedOrders->where('type', 'pickup')->count(),
                ],
                'by_payment' => [
                    'cash' => $completedOrders->where('payment_method', 'cash')->sum('total'),
                    'card' => $completedOrders->where('payment_method', 'card')->sum('total'),
                ],
            ],
        ]);
    }

    /**
     * Данные о продажах для графика
     */
    public function sales(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $period = $request->input('period', 'week'); // week, month

        $days = $period === 'month' ? 30 : 7;
        $sales = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            
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
        $restaurantId = $request->input('restaurant_id', 1);
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);

        $startDate = match($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
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
        $restaurantId = $request->input('restaurant_id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, Carbon::parse($endDate)->endOfDay()])
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
        $restaurantId = $request->input('restaurant_id', 1);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

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
                  ->whereBetween('created_at', [$startDate, Carbon::parse($endDate)->endOfDay()]);
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
        $restaurantId = $request->input('restaurant_id', 1);
        $date = $request->input('date', Carbon::today()->toDateString());

        $hourlyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $startHour = Carbon::parse($date)->setHour($hour)->setMinute(0)->setSecond(0);
            $endHour = Carbon::parse($date)->setHour($hour)->setMinute(59)->setSecond(59);

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
}
