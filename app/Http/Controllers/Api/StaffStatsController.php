<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffStatsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Статистика персонала
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Кто работает сейчас
        $workingNow = TimeEntry::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->count();

        // Смены сегодня
        $shiftsToday = Shift::where('restaurant_id', $restaurantId)
            ->whereDate('date', $today)
            ->whereNotIn('status', ['cancelled'])
            ->count();

        // Часы за месяц
        $monthlyHours = TimeEntry::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->sum('worked_minutes') / 60;

        // Чаевые за месяц
        $monthlyTips = Tip::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->sum('amount');

        // Топ по чаевым
        $topByTips = Tip::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('user')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'working_now' => $workingNow,
                'shifts_today' => $shiftsToday,
                'monthly_hours' => round($monthlyHours, 1),
                'monthly_tips' => $monthlyTips,
                'top_by_tips' => $topByTips,
            ],
        ]);
    }

    /**
     * Отчёт по сотруднику
     */
    public function userReport(Request $request, User $user): JsonResponse
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Время
        $timeEntries = TimeEntry::where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->where('status', 'completed')
            ->get();

        // Чаевые
        $tips = Tip::where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        // Заказы
        $orders = Order::where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'period' => ['from' => $from, 'to' => $to],
                'time' => [
                    'days_worked' => $timeEntries->count(),
                    'total_hours' => round($timeEntries->sum('worked_minutes') / 60, 1),
                    'entries' => $timeEntries,
                ],
                'tips' => [
                    'total' => $tips->sum('amount'),
                    'count' => $tips->count(),
                    'by_type' => [
                        'cash' => $tips->where('type', 'cash')->sum('amount'),
                        'card' => $tips->where('type', 'card')->sum('amount'),
                    ],
                ],
                'orders' => [
                    'count' => $orders->count(),
                    'revenue' => $orders->sum('total'),
                    'avg_check' => $orders->count() > 0 ? round($orders->avg('total'), 2) : 0,
                ],
            ],
        ]);
    }
}
