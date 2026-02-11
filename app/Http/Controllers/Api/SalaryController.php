<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryPeriod;
use App\Models\SalaryCalculation;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\StaffNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryController extends Controller
{
    protected StaffNotificationService $notificationService;

    public function __construct(StaffNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get salary periods list
     */
    public function periods(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id ?? auth()->user()->restaurant_id;

        $periods = SalaryPeriod::forRestaurant($restaurantId)
            ->withCount('calculations')
            ->with('creator:id,name')
            ->orderByDesc('start_date')
            ->paginate($request->per_page ?? 12);

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    /**
     * Create new salary period
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $user = auth()->user();
        $restaurantId = $user->restaurant_id;

        // Check if period already exists
        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
        $existing = SalaryPeriod::forRestaurant($restaurantId)
            ->where('start_date', $startDate)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Период уже существует',
                'data' => $existing,
            ], 422);
        }

        $period = SalaryPeriod::createForMonth(
            $restaurantId,
            $validated['year'],
            $validated['month'],
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Период создан',
            'data' => $period,
        ]);
    }

    /**
     * Get period details with calculations
     */
    public function periodDetails(SalaryPeriod $period): JsonResponse
    {
        $period->load([
            'calculations.user:id,name,role,avatar,salary_type,salary,hourly_rate,percent_rate',
            'creator:id,name',
            'approver:id,name',
        ]);

        // Get work stats summary
        $workStats = [];
        foreach ($period->calculations as $calc) {
            $workStats[$calc->user_id] = WorkSession::getStatsForPeriod(
                $calc->user_id,
                $period->start_date,
                $period->end_date
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'work_stats' => $workStats,
                'statuses' => SalaryPeriod::getStatuses(),
            ],
        ]);
    }

    /**
     * Calculate salaries for period
     */
    public function calculate(SalaryPeriod $period): JsonResponse
    {
        if ($period->status === SalaryPeriod::STATUS_PAID || $period->status === SalaryPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ], 422);
        }

        $period->calculateAll();

        $period->load([
            'calculations.user:id,name,role,avatar',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Зарплаты рассчитаны',
            'data' => $period,
        ]);
    }

    /**
     * Recalculate single employee
     */
    public function recalculateUser(SalaryPeriod $period, User $user): JsonResponse
    {
        if ($period->status === SalaryPeriod::STATUS_PAID || $period->status === SalaryPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ], 422);
        }

        $calculation = $period->calculateForUser($user);
        $calculation->load('user:id,name,role,avatar');

        // Update period total
        $period->total_amount = $period->calculations()->sum('net_amount');
        $period->save();

        return response()->json([
            'success' => true,
            'message' => 'Зарплата сотрудника пересчитана',
            'data' => $calculation,
        ]);
    }

    /**
     * Approve period
     */
    public function approve(SalaryPeriod $period): JsonResponse
    {
        if ($period->status !== SalaryPeriod::STATUS_CALCULATED) {
            return response()->json([
                'success' => false,
                'message' => 'Период должен быть рассчитан перед утверждением',
            ], 422);
        }

        $period->approve(auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Период утверждён',
            'data' => $period,
        ]);
    }

    /**
     * Add bonus to employee
     */
    public function addBonus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:salary_periods,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $period = SalaryPeriod::findOrFail($validated['period_id']);

        if ($period->status === SalaryPeriod::STATUS_PAID || $period->status === SalaryPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно добавить премию в закрытый период',
            ], 422);
        }

        $payment = SalaryPayment::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $validated['user_id'],
            'salary_period_id' => $period->id,
            'created_by' => $user->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        // Recalculate if period is calculated
        if ($period->status === SalaryPeriod::STATUS_CALCULATED) {
            $targetUser = User::forRestaurant($user->restaurant_id)->findOrFail($validated['user_id']);
            $period->calculateForUser($targetUser);
        }

        // Notify user
        $targetUser = $targetUser ?? User::forRestaurant($user->restaurant_id)->findOrFail($validated['user_id']);
        $this->notificationService->notifyBonusReceived(
            $targetUser,
            $validated['amount'],
            $validated['description'] ?? 'Премия'
        );

        return response()->json([
            'success' => true,
            'message' => 'Премия добавлена',
            'data' => $payment,
        ]);
    }

    /**
     * Add penalty to employee
     */
    public function addPenalty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:salary_periods,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $user = auth()->user();
        $period = SalaryPeriod::findOrFail($validated['period_id']);

        if ($period->status === SalaryPeriod::STATUS_PAID || $period->status === SalaryPeriod::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно добавить штраф в закрытый период',
            ], 422);
        }

        $payment = SalaryPayment::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $validated['user_id'],
            'salary_period_id' => $period->id,
            'created_by' => $user->id,
            'type' => SalaryPayment::TYPE_PENALTY,
            'amount' => -abs($validated['amount']),
            'description' => $validated['description'],
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        // Recalculate if period is calculated
        if ($period->status === SalaryPeriod::STATUS_CALCULATED) {
            $targetUser = User::forRestaurant($user->restaurant_id)->findOrFail($validated['user_id']);
            $period->calculateForUser($targetUser);
        }

        // Notify user
        $targetUser = $targetUser ?? User::forRestaurant($user->restaurant_id)->findOrFail($validated['user_id']);
        $this->notificationService->notifyPenaltyReceived(
            $targetUser,
            abs($validated['amount']),
            $validated['description']
        );

        return response()->json([
            'success' => true,
            'message' => 'Штраф добавлен',
            'data' => $payment,
        ]);
    }

    /**
     * Pay advance to employee
     */
    public function payAdvance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_id' => 'required|exists:salary_periods,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();
        $period = SalaryPeriod::findOrFail($validated['period_id']);

        $payment = SalaryPayment::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $validated['user_id'],
            'salary_period_id' => $period->id,
            'created_by' => $user->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => $validated['amount'],
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'description' => 'Аванс за ' . $period->period_label,
        ]);

        // Update calculation if exists
        $calculation = SalaryCalculation::where('salary_period_id', $period->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($calculation) {
            $calculation->recalculatePaidAmount();
        }

        // Notify user
        $targetUser = User::forRestaurant($user->restaurant_id)->findOrFail($validated['user_id']);
        $this->notificationService->notifySalaryPaid(
            $targetUser,
            $validated['amount'],
            'advance'
        );

        return response()->json([
            'success' => true,
            'message' => 'Аванс выплачен',
            'data' => $payment,
        ]);
    }

    /**
     * Pay salary to employee
     */
    public function paySalary(Request $request, SalaryCalculation $calculation): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
        ]);

        if ($calculation->balance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Зарплата уже полностью выплачена',
            ], 422);
        }

        $amount = min($validated['amount'], $calculation->balance);

        $payment = $calculation->addPayment(
            $amount,
            'salary',
            'Выплата зарплаты',
            auth()->id()
        );

        // Notify user
        $this->notificationService->notifySalaryPaid(
            $calculation->user,
            $amount,
            'salary'
        );

        return response()->json([
            'success' => true,
            'message' => 'Зарплата выплачена',
            'data' => [
                'payment' => $payment,
                'calculation' => $calculation->fresh(),
            ],
        ]);
    }

    /**
     * Pay all pending salaries for period
     */
    public function payAll(SalaryPeriod $period, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:50',
        ]);

        if ($period->status !== SalaryPeriod::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Период должен быть утверждён для выплаты',
            ], 422);
        }

        $calculations = $period->calculations()
            ->where('balance', '>', 0)
            ->with('user')
            ->get();

        $notifications = [];

        $result = DB::transaction(function () use ($calculations, $period, &$notifications) {
            $paidCount = 0;
            $totalPaid = 0;

            foreach ($calculations as $calculation) {
                if ($calculation->balance > 0) {
                    $amount = $calculation->balance;
                    $payment = $calculation->addPayment(
                        $amount,
                        'salary',
                        'Выплата зарплаты за ' . $period->period_label,
                        auth()->id()
                    );

                    $notifications[] = ['user' => $calculation->user, 'amount' => $amount];

                    $totalPaid += $payment->amount;
                    $paidCount++;
                }
            }

            $period->markAsPaid();

            return [
                'paid_count' => $paidCount,
                'total_paid' => $totalPaid,
            ];
        });

        // Уведомления после коммита — их сбой не откатит платежи
        foreach ($notifications as $notification) {
            $this->notificationService->notifySalaryPaid(
                $notification['user'],
                $notification['amount'],
                'salary'
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Выплачено сотрудникам: {$result['paid_count']}",
            'data' => $result,
        ]);
    }

    /**
     * Get payment history for user
     */
    public function userPayments(Request $request, User $user): JsonResponse
    {
        $payments = SalaryPayment::forUser($user->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Get payment history for period
     */
    public function periodPayments(SalaryPeriod $period): JsonResponse
    {
        $payments = SalaryPayment::where('salary_period_id', $period->id)
            ->with(['user:id,name,avatar', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Cancel payment
     */
    public function cancelPayment(SalaryPayment $payment): JsonResponse
    {
        if ($payment->status === SalaryPayment::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Платёж уже отменён',
            ], 422);
        }

        $payment->cancel();

        // Recalculate if salary calculation exists
        if ($payment->salary_calculation_id) {
            $calculation = SalaryCalculation::find($payment->salary_calculation_id);
            if ($calculation) {
                $calculation->recalculatePaidAmount();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Платёж отменён',
        ]);
    }

    /**
     * Get salary summary for current user (staff app)
     */
    public function mySalary(): JsonResponse
    {
        $user = auth()->user();

        // Get current and last period calculations
        $calculations = SalaryCalculation::where('user_id', $user->id)
            ->with('period:id,name,start_date,end_date,status')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        // Get recent payments
        $payments = SalaryPayment::forUser($user->id)
            ->where('status', '!=', SalaryPayment::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Get current month work stats
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $workStats = WorkSession::getStatsForPeriod($user->id, $monthStart, $monthEnd);

        return response()->json([
            'success' => true,
            'data' => [
                'calculations' => $calculations,
                'payments' => $payments,
                'current_month_stats' => $workStats,
                'salary_type' => $user->salary_type,
                'salary_rate' => $user->salary,
                'hourly_rate' => $user->hourly_rate,
                'percent_rate' => $user->percent_rate,
            ],
        ]);
    }

    /**
     * Get calculation breakdown
     */
    public function calculationBreakdown(SalaryCalculation $calculation): JsonResponse
    {
        $calculation->load([
            'user:id,name,role,avatar',
            'period:id,name,start_date,end_date',
        ]);

        $workStats = WorkSession::getStatsForPeriod(
            $calculation->user_id,
            $calculation->period->start_date,
            $calculation->period->end_date
        );

        return response()->json([
            'success' => true,
            'data' => [
                'calculation' => $calculation,
                'breakdown' => $calculation->getBreakdown(),
                'work_stats' => $workStats,
            ],
        ]);
    }

    /**
     * Update calculation notes
     */
    public function updateCalculationNotes(Request $request, SalaryCalculation $calculation): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $calculation->update(['notes' => $validated['notes']]);

        return response()->json([
            'success' => true,
            'message' => 'Заметки сохранены',
        ]);
    }

    /**
     * Export period to Excel
     */
    public function exportPeriod(SalaryPeriod $period): JsonResponse
    {
        $period->load([
            'calculations.user:id,name,role',
        ]);

        $data = [];
        foreach ($period->calculations as $calc) {
            $data[] = [
                'Сотрудник' => $calc->user->name,
                'Должность' => $calc->user->role_label,
                'Тип оплаты' => $calc->salary_type_label,
                'Отработано часов' => $calc->hours_worked,
                'Отработано дней' => $calc->days_worked,
                'Базовый оклад' => $calc->base_amount,
                'За часы' => $calc->hourly_amount,
                'Сверхурочные' => $calc->overtime_amount,
                'Процент от продаж' => $calc->percent_amount,
                'Премии' => $calc->bonus_amount,
                'Штрафы' => $calc->penalty_amount,
                'Итого начислено' => $calc->gross_amount,
                'К выплате' => $calc->net_amount,
                'Выплачено' => $calc->paid_amount,
                'Остаток' => $calc->balance,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period->only(['id', 'name', 'period_label', 'total_amount']),
                'rows' => $data,
            ],
        ]);
    }
}
