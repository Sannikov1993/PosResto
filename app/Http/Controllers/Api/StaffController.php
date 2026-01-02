<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\Tip;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffController extends Controller
{
    // ==========================================
    // СОТРУДНИКИ
    // ==========================================

    /**
     * Список сотрудников
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            // Добавляем статистику
            $today = Carbon::today();
            $activeEntry = TimeEntry::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'position' => $user->position,
                'is_active' => $user->is_active,
                'hire_date' => $user->hire_date,
                'is_working' => $activeEntry !== null,
                'current_shift_start' => $activeEntry?->clock_in?->format('H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Показать сотрудника
     */
    public function show(User $user): JsonResponse
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Статистика за месяц
        $timeStats = TimeEntry::getMonthlyStats($user->id);
        $tipsStats = Tip::getStats($user->id, $monthStart, $monthEnd);

        // Заказы за месяц
        $ordersCount = Order::where('waiter_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->count();

        $ordersRevenue = Order::where('waiter_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'stats' => [
                    'time' => $timeStats,
                    'tips' => $tipsStats,
                    'orders' => [
                        'count' => $ordersCount,
                        'revenue' => $ordersRevenue,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Обновить сотрудника
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:admin,manager,waiter,cook,cashier',
            'position' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Данные обновлены',
            'data' => $user->fresh(),
        ]);
    }

    // ==========================================
    // СМЕНЫ
    // ==========================================

    /**
     * Список смен
     */
    public function shifts(Request $request): JsonResponse
    {
        $query = Shift::with(['user'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        // Фильтр по дате
        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Фильтр по неделе
        if ($request->has('week_of')) {
            $date = Carbon::parse($request->input('week_of'));
            $query->whereBetween('date', [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek(),
            ]);
        }

        // Фильтр по сотруднику
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $shifts = $query->orderBy('date')->orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Создать смену
     */
    public function createShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        // Проверяем пересечение с другими сменами
        $overlap = Shift::where('user_id', $validated['user_id'])
            ->whereDate('date', $validated['date'])
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']]);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Смена пересекается с другой сменой этого сотрудника',
            ], 422);
        }

        $shift = Shift::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            'user_id' => $validated['user_id'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->input('created_by'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена создана',
            'data' => $shift->load('user'),
        ], 201);
    }

    /**
     * Обновить смену
     */
    public function updateShift(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'status' => 'sometimes|in:scheduled,confirmed,cancelled',
            'notes' => 'nullable|string|max:500',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Смена обновлена',
            'data' => $shift->fresh('user'),
        ]);
    }

    /**
     * Удалить смену
     */
    public function deleteShift(Shift $shift): JsonResponse
    {
        if ($shift->status === 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить активную смену',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Смена удалена',
        ]);
    }

    /**
     * Расписание на неделю
     */
    public function weekSchedule(Request $request): JsonResponse
    {
        $weekOf = $request->input('week_of', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($weekOf);
        $restaurantId = $request->input('restaurant_id', 1);

        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        // Получаем всех активных сотрудников
        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereIn('role', ['waiter', 'cook', 'cashier', 'manager'])
            ->orderBy('name')
            ->get();

        // Получаем смены за неделю
        $shifts = Shift::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->groupBy('user_id');

        // Формируем расписание
        $schedule = $users->map(function ($user) use ($shifts, $weekStart) {
            $userShifts = $shifts->get($user->id, collect());
            $days = [];
            
            for ($i = 0; $i < 7; $i++) {
                $dayDate = $weekStart->copy()->addDays($i)->format('Y-m-d');
                $dayShift = $userShifts->firstWhere('date', $dayDate);
                $days[] = [
                    'date' => $dayDate,
                    'shift' => $dayShift,
                ];
            }

            return [
                'user' => $user,
                'days' => $days,
                'total_hours' => $userShifts->sum('duration_hours'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'schedule' => $schedule,
            ],
        ]);
    }

    // ==========================================
    // УЧЁТ ВРЕМЕНИ
    // ==========================================

    /**
     * Отметка прихода
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'method' => 'nullable|in:manual,pin,qr',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $method = $validated['method'] ?? 'manual';

        // Проверяем активную смену
        $activeEntry = TimeEntry::where('user_id', $validated['user_id'])
            ->where('status', 'active')
            ->first();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Уже есть активная смена. Сначала завершите её.',
                'data' => $activeEntry,
            ], 422);
        }

        // Ищем запланированную смену на сегодня
        $scheduledShift = Shift::where('user_id', $validated['user_id'])
            ->whereDate('date', Carbon::today())
            ->where('status', 'scheduled')
            ->first();

        $entry = TimeEntry::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'shift_id' => $scheduledShift?->id,
            'date' => Carbon::today(),
            'clock_in' => now(),
            'status' => 'active',
            'clock_in_method' => $method,
        ]);

        // Обновляем статус смены
        if ($scheduledShift) {
            $scheduledShift->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Отмечено начало работы',
            'data' => $entry->load('user'),
        ]);
    }

    /**
     * Отметка ухода
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'method' => 'nullable|in:manual,pin,qr',
        ]);

        $entry = TimeEntry::where('user_id', $validated['user_id'])
            ->where('status', 'active')
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активной смены',
            ], 422);
        }

        $entry->clockOut($validated['method'] ?? 'manual');

        return response()->json([
            'success' => true,
            'message' => 'Отмечено окончание работы',
            'data' => $entry->fresh('user'),
        ]);
    }

    /**
     * История учёта времени
     */
    public function timeEntries(Request $request): JsonResponse
    {
        $query = TimeEntry::with(['user', 'shift'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        $entries = $query->orderByDesc('date')->orderByDesc('clock_in')->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Кто сейчас работает
     */
    public function whoIsWorking(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        $activeEntries = TimeEntry::with('user')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(function ($entry) {
                return [
                    'user' => $entry->user,
                    'clock_in' => $entry->clock_in->format('H:i'),
                    'worked_hours' => $entry->worked_hours,
                    'entry_id' => $entry->id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $activeEntries,
        ]);
    }

    // ==========================================
    // ЧАЕВЫЕ
    // ==========================================

    /**
     * Список чаевых
     */
    public function tips(Request $request): JsonResponse
    {
        $query = Tip::with(['user', 'order'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        $tips = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $tips,
        ]);
    }

    /**
     * Добавить чаевые
     */
    public function addTip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:cash,card,shared',
            'order_id' => 'nullable|integer|exists:orders,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $tip = Tip::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            'user_id' => $validated['user_id'],
            'order_id' => $validated['order_id'] ?? null,
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'date' => Carbon::today(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Чаевые добавлены',
            'data' => $tip->load('user'),
        ], 201);
    }

    // ==========================================
    // СТАТИСТИКА
    // ==========================================

    /**
     * Статистика персонала
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
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
        $orders = Order::where('waiter_id', $user->id)
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
