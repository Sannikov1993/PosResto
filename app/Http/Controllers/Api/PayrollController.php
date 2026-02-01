<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use App\Models\SalaryPeriod;
use App\Models\SalaryCalculation;
use App\Models\SalaryPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PayrollController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // ==================== ТАБЕЛЬ (WORK SESSIONS) ====================

    /**
     * Получить табель за период
     */
    public function timesheet(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $userId = $request->input('user_id');

        $query = WorkSession::with(['user:id,name,role'])
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('clock_in', [$startDate, Carbon::parse($endDate)->endOfDay()]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $sessions = $query->orderBy('clock_in', 'desc')->get();

        // Группируем по сотрудникам для статистики
        $byUser = $sessions->groupBy('user_id')->map(function ($userSessions) {
            $user = $userSessions->first()->user;
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'total_hours' => round($userSessions->sum('hours_worked'), 2),
                'days_worked' => $userSessions->pluck('date')->unique()->count(),
                'sessions_count' => $userSessions->count(),
                'sessions' => $userSessions,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessions,
                'by_user' => $byUser,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'totals' => [
                    'total_hours' => round($sessions->sum('hours_worked'), 2),
                    'total_sessions' => $sessions->count(),
                    'employees_count' => $byUser->count(),
                ],
            ],
        ]);
    }

    /**
     * Отметить приход
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $ip = $request->ip();

        // Проверяем, нет ли уже активной смены
        $activeSession = WorkSession::getActiveSession($validated['user_id'], $restaurantId);
        if ($activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'У сотрудника уже есть активная смена',
                'data' => $activeSession,
            ], 400);
        }

        $session = WorkSession::clockIn($validated['user_id'], $restaurantId, $ip);

        return response()->json([
            'success' => true,
            'message' => 'Смена начата',
            'data' => $session->load('user:id,name,role'),
        ]);
    }

    /**
     * Отметить уход
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $ip = $request->ip();

        $session = WorkSession::getActiveSession($validated['user_id'], $restaurantId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активной смены',
            ], 400);
        }

        $session->clockOut($ip);

        return response()->json([
            'success' => true,
            'message' => 'Смена завершена',
            'data' => $session->fresh()->load('user:id,name,role'),
        ]);
    }

    /**
     * Текущий статус сотрудника (на смене или нет)
     */
    public function clockStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $session = WorkSession::getActiveSession($validated['user_id'], $restaurantId);

        return response()->json([
            'success' => true,
            'data' => [
                'is_clocked_in' => $session !== null,
                'session' => $session,
            ],
        ]);
    }

    // ==================== МЕТОДЫ ДЛЯ АВТОРИЗОВАННОГО ПОЛЬЗОВАТЕЛЯ ====================

    /**
     * Статус смены текущего пользователя
     */
    public function myClockStatus(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        }

        $restaurantId = $user->restaurant_id ?? $this->getRestaurantId($request);
        $session = WorkSession::getActiveSession($user->id, $restaurantId);

        return response()->json([
            'is_clocked_in' => $session !== null,
            'session' => $session ? [
                'id' => $session->id,
                'clock_in' => $session->clock_in,
                'duration_formatted' => $session->duration_formatted,
            ] : null,
        ]);
    }

    /**
     * Начать смену текущего пользователя
     */
    public function myClockIn(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        }

        $restaurantId = $user->restaurant_id ?? $this->getRestaurantId($request);
        $ip = $request->ip();

        // Проверяем, нет ли уже активной смены
        $activeSession = WorkSession::getActiveSession($user->id, $restaurantId);
        if ($activeSession) {
            return response()->json([
                'success' => false,
                'message' => 'У вас уже есть активная смена',
            ], 400);
        }

        $session = WorkSession::clockIn($user->id, $restaurantId, $ip);

        return response()->json([
            'success' => true,
            'message' => 'Смена начата',
            'session' => [
                'id' => $session->id,
                'clock_in' => $session->clock_in,
            ],
        ]);
    }

    /**
     * Завершить смену текущего пользователя
     */
    public function myClockOut(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        }

        $restaurantId = $user->restaurant_id ?? $this->getRestaurantId($request);
        $ip = $request->ip();

        $session = WorkSession::getActiveSession($user->id, $restaurantId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активной смены',
            ], 400);
        }

        $session->clockOut($ip);

        return response()->json([
            'success' => true,
            'message' => 'Смена завершена',
            'session' => [
                'id' => $session->id,
                'clock_in' => $session->clock_in,
                'clock_out' => $session->fresh()->clock_out,
                'hours_worked' => $session->fresh()->hours_worked,
            ],
        ]);
    }

    /**
     * Создать/редактировать запись вручную
     */
    public function storeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'clock_in' => 'required|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'break_minutes' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $clockIn = Carbon::parse($validated['clock_in']);
        $clockOut = isset($validated['clock_out']) ? Carbon::parse($validated['clock_out']) : null;

        $hoursWorked = null;
        if ($clockOut) {
            $hoursWorked = $clockIn->diffInMinutes($clockOut) / 60;
            $hoursWorked -= ($validated['break_minutes'] ?? 0) / 60;
            $hoursWorked = max(0, round($hoursWorked, 2));
        }

        $session = WorkSession::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'hours_worked' => $hoursWorked,
            'break_minutes' => $validated['break_minutes'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'status' => $clockOut ? WorkSession::STATUS_COMPLETED : WorkSession::STATUS_ACTIVE,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись создана',
            'data' => $session->load('user:id,name,role'),
        ], 201);
    }

    /**
     * Корректировать запись
     */
    public function correctSession(Request $request, WorkSession $session): JsonResponse
    {
        $validated = $request->validate([
            'clock_in' => 'required|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'break_minutes' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $correctorId = $request->input('corrector_id') ?? auth()->id() ?? 1;

        $session->correct(
            Carbon::parse($validated['clock_in']),
            isset($validated['clock_out']) ? Carbon::parse($validated['clock_out']) : null,
            $correctorId,
            $validated['reason']
        );

        if (isset($validated['break_minutes'])) {
            $session->update(['break_minutes' => $validated['break_minutes']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Запись скорректирована',
            'data' => $session->fresh()->load('user:id,name,role', 'corrector:id,name'),
        ]);
    }

    /**
     * Удалить запись
     */
    public function deleteSession(WorkSession $session): JsonResponse
    {
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена',
        ]);
    }

    /**
     * Кто сейчас на смене
     */
    public function whoIsWorking(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $activeSessions = WorkSession::with('user:id,name,role,phone')
            ->where('restaurant_id', $restaurantId)
            ->whereNull('clock_out')
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activeSessions,
            'count' => $activeSessions->count(),
        ]);
    }

    // ==================== РАСЧЁТНЫЕ ПЕРИОДЫ ====================

    /**
     * Список периодов
     */
    public function periods(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $periods = SalaryPeriod::with('creator:id,name')
            ->where('restaurant_id', $restaurantId)
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    /**
     * Создать период
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $createdBy = $request->input('user_id') ?? auth()->id();

        // Проверяем, нет ли уже такого периода
        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
        $endDate = Carbon::create($validated['year'], $validated['month'], 1)->endOfMonth();

        $existing = SalaryPeriod::where('restaurant_id', $restaurantId)
            ->where('start_date', $startDate)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Период уже существует',
                'data' => $existing,
            ], 400);
        }

        $period = SalaryPeriod::createForMonth($restaurantId, $validated['year'], $validated['month'], $createdBy);

        return response()->json([
            'success' => true,
            'message' => 'Период создан',
            'data' => $period,
        ], 201);
    }

    /**
     * Получить период с расчётами
     */
    public function showPeriod(SalaryPeriod $period): JsonResponse
    {
        $period->load([
            'calculations.user:id,name,role,phone',
            'creator:id,name',
            'approver:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => $period,
        ]);
    }

    /**
     * Рассчитать зарплаты за период
     */
    public function calculatePeriod(SalaryPeriod $period): JsonResponse
    {
        if (!in_array($period->status, ['draft', 'calculated'])) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно пересчитать период в статусе "' . $period->status_label . '"',
            ], 400);
        }

        $period->calculateAll();

        return response()->json([
            'success' => true,
            'message' => 'Расчёт выполнен',
            'data' => $period->fresh()->load('calculations.user:id,name,role'),
        ]);
    }

    /**
     * Утвердить период
     */
    public function approvePeriod(Request $request, SalaryPeriod $period): JsonResponse
    {
        if ($period->status !== 'calculated') {
            return response()->json([
                'success' => false,
                'message' => 'Сначала выполните расчёт',
            ], 400);
        }

        $approverId = $request->input('user_id') ?? auth()->id() ?? 1;
        $period->approve($approverId);

        return response()->json([
            'success' => true,
            'message' => 'Период утверждён',
            'data' => $period->fresh(),
        ]);
    }

    // ==================== ВЫПЛАТЫ ====================

    /**
     * Создать выплату (аванс, зарплата, бонус, штраф)
     */
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'salary_period_id' => 'nullable|integer|exists:salary_periods,id',
            'type' => 'required|in:salary,advance,bonus,penalty,overtime',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:30',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $createdBy = $request->input('creator_id') ?? auth()->id();

        // Находим расчёт для периода
        $calculationId = null;
        if ($validated['salary_period_id']) {
            $calculation = SalaryCalculation::where('salary_period_id', $validated['salary_period_id'])
                ->where('user_id', $validated['user_id'])
                ->first();
            $calculationId = $calculation?->id;
        }

        // Для штрафов делаем сумму отрицательной
        $amount = $validated['type'] === 'penalty' ? -abs($validated['amount']) : abs($validated['amount']);

        $payment = SalaryPayment::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'salary_period_id' => $validated['salary_period_id'] ?? null,
            'salary_calculation_id' => $calculationId,
            'created_by' => $createdBy,
            'type' => $validated['type'],
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'description' => $validated['description'] ?? null,
        ]);

        // Пересчитываем выплаченную сумму в расчёте
        if ($calculationId) {
            $calculation->recalculatePaidAmount();
        }

        return response()->json([
            'success' => true,
            'message' => 'Выплата создана',
            'data' => $payment->load('user:id,name'),
        ], 201);
    }

    /**
     * История выплат
     */
    public function payments(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $query = SalaryPayment::with(['user:id,name,role', 'creator:id,name'])
            ->where('restaurant_id', $restaurantId);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('salary_period_id')) {
            $query->where('salary_period_id', $request->input('salary_period_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $payments = $query->orderByDesc('created_at')->limit(200)->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Массовая выплата зарплаты за период
     */
    public function payPeriod(Request $request, SalaryPeriod $period): JsonResponse
    {
        if (!in_array($period->status, ['approved', 'paid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Период должен быть утверждён',
            ], 400);
        }

        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:30',
        ]);

        $createdBy = $request->input('creator_id') ?? auth()->id();
        $paymentMethod = $validated['payment_method'] ?? 'cash';

        $calculations = $period->calculations()->where('balance', '>', 0)->get();
        $totalPaid = 0;

        foreach ($calculations as $calc) {
            if ($calc->balance > 0) {
                SalaryPayment::create([
                    'restaurant_id' => $period->restaurant_id,
                    'user_id' => $calc->user_id,
                    'salary_period_id' => $period->id,
                    'salary_calculation_id' => $calc->id,
                    'created_by' => $createdBy,
                    'type' => 'salary',
                    'amount' => $calc->balance,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $paymentMethod,
                    'description' => 'Зарплата за ' . $period->name,
                ]);

                $calc->recalculatePaidAmount();
                $totalPaid += $calc->balance;
            }
        }

        $period->markAsPaid();

        return response()->json([
            'success' => true,
            'message' => 'Зарплата выплачена',
            'data' => [
                'total_paid' => $totalPaid,
                'employees_count' => $calculations->count(),
            ],
        ]);
    }

    /**
     * Сводка по сотруднику
     */
    public function userSummary(Request $request, User $user): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $currentMonth = Carbon::now();

        // Текущий статус
        $activeSession = WorkSession::getActiveSession($user->id, $restaurantId);

        // Статистика за текущий месяц
        $monthStats = WorkSession::getStatsForPeriod(
            $user->id,
            $currentMonth->copy()->startOfMonth(),
            $currentMonth->copy()->endOfMonth()
        );

        // Последний расчёт зарплаты
        $lastCalculation = SalaryCalculation::with('period:id,name,status')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        // История выплат за последние 3 месяца
        $recentPayments = SalaryPayment::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth->copy()->subMonths(3))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'role', 'salary_type', 'salary', 'hourly_rate', 'percent_rate']),
                'is_working' => $activeSession !== null,
                'active_session' => $activeSession,
                'current_month' => $monthStats,
                'last_calculation' => $lastCalculation,
                'recent_payments' => $recentPayments,
            ],
        ]);
    }

    /**
     * Отмена выплаты
     */
    public function cancelPayment(SalaryPayment $payment): JsonResponse
    {
        if ($payment->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Выплата уже отменена',
            ], 400);
        }

        $payment->cancel();

        // Пересчитываем в расчёте
        if ($payment->salary_calculation_id) {
            $payment->calculation->recalculatePaidAmount();
        }

        return response()->json([
            'success' => true,
            'message' => 'Выплата отменена',
        ]);
    }

    /**
     * ===========================================
     * BACKOFFICE: Зарплаты
     * ===========================================
     */

    /**
     * Список начислений зарплат для бэк-офиса
     */
    public function index(Request $request): JsonResponse
    {
        return $this->timesheet($request);
    }

    /**
     * История выплат
     */
    public function history(Request $request): JsonResponse
    {
        return $this->payments($request);
    }

    /**
     * Ставки оплаты
     */
    public function rates(Request $request): JsonResponse
    {
        // Заглушка - пока используем фиксированные ставки
        $rates = [
            ['id' => 1, 'role' => 'waiter', 'role_label' => 'Официант', 'hourly_rate' => 200, 'tip_share' => 100],
            ['id' => 2, 'role' => 'cook', 'role_label' => 'Повар', 'hourly_rate' => 250, 'tip_share' => 0],
            ['id' => 3, 'role' => 'cashier', 'role_label' => 'Кассир', 'hourly_rate' => 220, 'tip_share' => 50],
            ['id' => 4, 'role' => 'admin', 'role_label' => 'Админ', 'hourly_rate' => 350, 'tip_share' => 0],
        ];

        return response()->json([
            'success' => true,
            'data' => $rates,
        ]);
    }

    /**
     * Создать ставку
     */
    public function storeRate(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ставки пока не реализованы как отдельная модель',
        ], 501);
    }

    /**
     * Обновить ставку
     */
    public function updateRate(Request $request, int $rate): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ставки пока не реализованы как отдельная модель',
        ], 501);
    }

    /**
     * Удалить ставку
     */
    public function destroyRate(int $rate): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ставки пока не реализованы как отдельная модель',
        ], 501);
    }

    /**
     * Рассчитать зарплату
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020',
        ]);

        // Создаём или находим период
        $period = SalaryPeriod::firstOrCreate([
            'restaurant_id' => $this->getRestaurantId($request),
            'month' => $validated['month'],
            'year' => $validated['year'],
        ], [
            'status' => 'draft',
        ]);

        // Рассчитываем
        $this->calculatePeriod($period);

        return response()->json([
            'success' => true,
            'message' => 'Зарплаты рассчитаны',
            'data' => $period->fresh(['calculations.user']),
        ]);
    }

    /**
     * Обновить начисление
     */
    public function update(Request $request, $payroll): JsonResponse
    {
        $calculation = \App\Models\SalaryCalculation::findOrFail($payroll);

        $validated = $request->validate([
            'bonus' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $calculation->update($validated);
        $calculation->recalculate();

        return response()->json([
            'success' => true,
            'data' => $calculation,
        ]);
    }

    /**
     * Выплатить зарплату
     */
    public function pay(Request $request, $payroll): JsonResponse
    {
        $calculation = \App\Models\SalaryCalculation::findOrFail($payroll);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,transfer',
        ]);

        $payment = SalaryPayment::create([
            'restaurant_id' => $calculation->restaurant_id,
            'user_id' => $calculation->user_id,
            'salary_calculation_id' => $calculation->id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['method'],
            'status' => 'completed',
            'paid_by' => auth()->id(),
            'paid_at' => now(),
        ]);

        $calculation->recalculatePaidAmount();

        return response()->json([
            'success' => true,
            'message' => 'Выплата проведена',
            'data' => $payment,
        ]);
    }
}
