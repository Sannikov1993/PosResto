<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use App\Models\StaffSchedule;
use App\Models\SalaryPeriod;
use App\Models\SalaryCalculation;
use App\Models\SalaryPayment;
use App\Models\Order;
use App\Models\Tip;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffCabinetController extends Controller
{
    /**
     * Get dashboard data for current user
     */
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        // Upcoming shifts (next 7 days)
        $upcomingShifts = StaffSchedule::forUser($user->id)
            ->published()
            ->where('date', '>=', $now->toDateString())
            ->where('date', '<=', $now->copy()->addDays(7)->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        // Today's shift
        $todayShift = StaffSchedule::forUser($user->id)
            ->published()
            ->where('date', $now->toDateString())
            ->first();

        // Current work session
        $activeSession = WorkSession::getActiveSession($user->id, $user->restaurant_id);

        // Month stats
        $monthStats = WorkSession::getStatsForPeriod($user->id, $monthStart, $monthEnd);

        // Current salary calculation
        $currentPeriod = SalaryPeriod::forRestaurant($user->restaurant_id)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();

        $salaryCalc = null;
        if ($currentPeriod) {
            $salaryCalc = SalaryCalculation::where('salary_period_id', $currentPeriod->id)
                ->where('user_id', $user->id)
                ->first();
        }

        // Unread notifications count
        $unreadNotifications = Notification::where('user_id', $user->id)
            ->unread()
            ->count();

        // For waiters: month sales
        $monthSales = null;
        $monthTips = null;
        if (in_array($user->role, ['waiter', 'bartender', 'cashier'])) {
            $monthSales = Order::where('waiter_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first();

            $monthTips = Tip::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'role_label' => $user->role_label,
                    'avatar' => $user->avatar,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'today_shift' => $todayShift,
                'upcoming_shifts' => $upcomingShifts,
                'active_session' => $activeSession,
                'month_stats' => [
                    'hours_worked' => $monthStats['total_hours'],
                    'days_worked' => $monthStats['days_worked'],
                    'avg_hours_per_day' => $monthStats['avg_hours_per_day'],
                ],
                'salary' => $salaryCalc ? [
                    'net_amount' => $salaryCalc->net_amount,
                    'paid_amount' => $salaryCalc->paid_amount,
                    'balance' => $salaryCalc->balance,
                    'status' => $salaryCalc->status,
                ] : null,
                'sales' => $monthSales ? [
                    'orders_count' => $monthSales->count,
                    'total' => $monthSales->total,
                ] : null,
                'tips' => $monthTips,
                'unread_notifications' => $unreadNotifications,
            ],
        ]);
    }

    /**
     * Get my schedule
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $user = auth()->user();
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfWeek();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy()->addWeeks(2);

        $shifts = StaffSchedule::forUser($user->id)
            ->published()
            ->forDateRange($startDate, $endDate)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Group by date
        $byDate = [];
        foreach ($shifts as $shift) {
            $dateKey = $shift->date->format('Y-m-d');
            if (!isset($byDate[$dateKey])) {
                $byDate[$dateKey] = [];
            }
            $byDate[$dateKey][] = $shift;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'shifts' => $shifts,
                'by_date' => $byDate,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Get my timesheet
     */
    public function myTimesheet(Request $request): JsonResponse
    {
        $user = auth()->user();
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        $sessions = WorkSession::forUser($user->id)
            ->forPeriod($startDate, $endDate)
            ->orderByDesc('clock_in')
            ->get();

        $stats = WorkSession::getStatsForPeriod($user->id, $startDate, $endDate);

        // Group by date
        $byDate = [];
        foreach ($sessions as $session) {
            $dateKey = $session->clock_in->format('Y-m-d');
            if (!isset($byDate[$dateKey])) {
                $byDate[$dateKey] = [
                    'sessions' => [],
                    'total_hours' => 0,
                ];
            }
            $byDate[$dateKey]['sessions'][] = $session;
            $byDate[$dateKey]['total_hours'] += $session->hours_worked ?? 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessions,
                'by_date' => $byDate,
                'stats' => $stats,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Clock in
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if already clocked in
        $active = WorkSession::getActiveSession($user->id, $user->restaurant_id);
        if ($active) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже на смене',
                'data' => $active,
            ], 422);
        }

        $session = WorkSession::clockIn($user->id, $user->restaurant_id, $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Смена начата',
            'data' => $session,
        ]);
    }

    /**
     * Clock out
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = auth()->user();

        $active = WorkSession::getActiveSession($user->id, $user->restaurant_id);
        if (!$active) {
            return response()->json([
                'success' => false,
                'message' => 'Вы не на смене',
            ], 422);
        }

        $active->clockOut($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Смена завершена',
            'data' => $active->fresh(),
        ]);
    }

    /**
     * Get my salary history
     */
    public function mySalary(): JsonResponse
    {
        $user = auth()->user();

        // Get all calculations for user
        $calculations = SalaryCalculation::where('user_id', $user->id)
            ->with('period:id,name,start_date,end_date,status')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        // Get recent payments
        $payments = SalaryPayment::forUser($user->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Current salary info
        $currentInfo = [
            'salary_type' => $user->salary_type,
            'salary_type_label' => match($user->salary_type) {
                'fixed' => 'Оклад',
                'hourly' => 'Почасовая',
                'mixed' => 'Смешанная',
                'percent' => 'Процент',
                default => $user->salary_type,
            },
            'base_salary' => $user->salary,
            'hourly_rate' => $user->hourly_rate,
            'percent_rate' => $user->percent_rate,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'calculations' => $calculations,
                'payments' => $payments,
                'current_info' => $currentInfo,
            ],
        ]);
    }

    /**
     * Get salary calculation details
     */
    public function salaryDetails(SalaryCalculation $calculation): JsonResponse
    {
        $user = auth()->user();

        if ($calculation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        }

        $calculation->load('period:id,name,start_date,end_date');

        return response()->json([
            'success' => true,
            'data' => [
                'calculation' => $calculation,
                'breakdown' => $calculation->getBreakdown(),
            ],
        ]);
    }

    /**
     * Get my stats (for waiters)
     */
    public function myStats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        // Orders stats
        $ordersQuery = Order::where('waiter_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed');

        $ordersStats = $ordersQuery->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as total,
            COALESCE(AVG(total), 0) as average
        ')->first();

        // Orders by day
        $ordersByDay = Order::where('waiter_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Tips
        $tips = Tip::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $tipsByDay = Tip::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Work hours
        $workStats = WorkSession::getStatsForPeriod($user->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'count' => $ordersStats->count ?? 0,
                    'total' => $ordersStats->total ?? 0,
                    'average' => round($ordersStats->average ?? 0, 2),
                ],
                'orders_by_day' => $ordersByDay,
                'tips' => $tips,
                'tips_by_day' => $tipsByDay,
                'work' => $workStats,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Get my profile
     */
    public function myProfile(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'avatar' => $user->avatar,
                'hire_date' => $user->hire_date?->format('Y-m-d'),
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'has_password' => $user->has_password,
                'has_pin' => $user->has_pin,
                'telegram_connected' => $user->hasTelegram(),
                'telegram_username' => $user->telegram_username,
                'notification_settings' => $user->notification_settings ?? User::getDefaultNotificationSettings(),
            ],
        ]);
    }

    /**
     * Update my profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Профиль обновлён',
        ]);
    }

    /**
     * Change PIN
     */
    public function changePin(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_pin' => 'nullable|string|size:4',
            'new_pin' => 'required|string|size:4',
        ]);

        // If user has PIN, verify current
        if ($user->has_pin && !empty($validated['current_pin'])) {
            if (!$user->verifyPin($validated['current_pin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный текущий PIN',
                ], 422);
            }
        }

        $user->setPin($validated['new_pin']);

        return response()->json([
            'success' => true,
            'message' => 'PIN изменён',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required_if:has_password,true|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // If user has password, verify current
        if ($user->has_password) {
            if (!\Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный текущий пароль',
                ], 422);
            }
        }

        $user->update([
            'password' => $validated['new_password'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Пароль изменён',
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $user->update([
            'notification_settings' => $validated['settings'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
        ]);
    }

    /**
     * Get my notifications
     */
    public function myNotifications(Request $request): JsonResponse
    {
        $user = auth()->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsRead(): JsonResponse
    {
        $user = auth()->user();

        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Все уведомления прочитаны',
        ]);
    }

    // ==================== PUSH NOTIFICATIONS ====================

    /**
     * Get VAPID public key for push subscription
     */
    public function getVapidPublicKey(): JsonResponse
    {
        $webPush = app(\App\Services\WebPushService::class);

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $webPush->getPublicKey(),
                'is_configured' => $webPush->isConfigured(),
            ],
        ]);
    }

    /**
     * Subscribe to push notifications
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'endpoint' => 'required|string|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $webPush = app(\App\Services\WebPushService::class);
        $subscription = $webPush->saveUserSubscription($user->id, $validated);

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось сохранить подписку',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Подписка на уведомления активирована',
            'data' => [
                'id' => $subscription->id,
                'device_info' => $subscription->device_info,
            ],
        ]);
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $webPush = app(\App\Services\WebPushService::class);
        $deleted = $webPush->deleteSubscription($validated['endpoint']);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Подписка отменена' : 'Подписка не найдена',
        ]);
    }

    /**
     * Get my push subscriptions (devices)
     */
    public function myPushSubscriptions(): JsonResponse
    {
        $user = auth()->user();

        $subscriptions = \App\Models\PushSubscription::forUser($user->id)
            ->active()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn($sub) => [
                'id' => $sub->id,
                'device_info' => $sub->device_info,
                'created_at' => $sub->created_at->format('d.m.Y H:i'),
                'last_used_at' => $sub->last_used_at?->format('d.m.Y H:i'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Send test push notification to current user
     */
    public function testPushNotification(): JsonResponse
    {
        $user = auth()->user();
        $webPush = app(\App\Services\WebPushService::class);

        $sent = $webPush->sendToUser($user->id, [
            'title' => 'Тестовое уведомление',
            'body' => 'Push-уведомления работают!',
            'icon' => '/images/logo/poslab_icon_192.png',
            'tag' => 'test-' . time(),
            'data' => [
                'type' => 'test',
                'url' => '/cabinet',
            ],
        ]);

        return response()->json([
            'success' => $sent > 0,
            'message' => $sent > 0
                ? "Отправлено уведомлений: {$sent}"
                : 'Нет активных подписок для отправки',
            'data' => [
                'sent_count' => $sent,
            ],
        ]);
    }

    // ==================== BIOMETRIC AUTHENTICATION ====================

    /**
     * Get biometric registration options
     */
    public function biometricRegisterOptions(): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        try {
            $options = $webAuthn->generateRegistrationOptions($user);

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Register biometric credential
     */
    public function biometricRegister(Request $request): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        $validated = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.attestationObject' => 'required|string',
            'name' => 'nullable|string|max:100',
            'deviceType' => 'nullable|string|max:50',
        ]);

        try {
            $credential = $webAuthn->verifyRegistration(
                $user,
                $validated,
                $validated['name'] ?? null
            );

            if ($validated['deviceType'] ?? null) {
                $credential->update(['device_type' => $validated['deviceType']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Биометрия зарегистрирована',
                'data' => $credential->info,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get biometric authentication options
     */
    public function biometricAuthOptions(): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        try {
            $options = $webAuthn->generateAuthenticationOptions($user);

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify biometric authentication
     */
    public function biometricVerify(Request $request): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        $validated = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.authenticatorData' => 'required|string',
            'response.signature' => 'required|string',
        ]);

        try {
            $credential = $webAuthn->verifyAuthentication($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Биометрия подтверждена',
                'data' => [
                    'credential_id' => $credential->id,
                    'device_type' => $credential->device_type,
                    'verified_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's biometric credentials list
     */
    public function biometricCredentials(): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        return response()->json([
            'success' => true,
            'data' => [
                'credentials' => $webAuthn->getUserCredentials($user->id),
                'has_biometric' => $webAuthn->userHasBiometric($user->id),
                'require_biometric_clock' => $user->require_biometric_clock ?? false,
            ],
        ]);
    }

    /**
     * Delete biometric credential
     */
    public function biometricDelete(int $credentialId): JsonResponse
    {
        $user = auth()->user();
        $webAuthn = app(\App\Services\WebAuthnService::class);

        $deleted = $webAuthn->deleteCredential($user->id, $credentialId);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Биометрия удалена' : 'Биометрия не найдена',
        ]);
    }

    /**
     * Toggle require biometric for clock in/out
     */
    public function biometricToggleRequirement(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'require' => 'required|boolean',
        ]);

        // Check if user has biometric before requiring it
        if ($validated['require']) {
            $webAuthn = app(\App\Services\WebAuthnService::class);
            if (!$webAuthn->userHasBiometric($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сначала зарегистрируйте биометрию',
                ], 400);
            }
        }

        $user->update(['require_biometric_clock' => $validated['require']]);

        return response()->json([
            'success' => true,
            'message' => $validated['require']
                ? 'Биометрия обязательна для отметок'
                : 'Биометрия не обязательна',
        ]);
    }

    // ==================== CLOCK IN/OUT WITH BIOMETRIC ====================

    /**
     * Clock in with optional biometric verification
     */
    public function clockInWithBiometric(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if biometric is required
        if ($user->require_biometric_clock) {
            $validated = $request->validate([
                'biometric' => 'required|array',
            ]);

            $webAuthn = app(\App\Services\WebAuthnService::class);

            try {
                $credential = $webAuthn->verifyAuthentication($user, $validated['biometric']);
                $verifiedBy = 'biometric:' . $credential->device_type;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Биометрия не подтверждена: ' . $e->getMessage(),
                ], 400);
            }
        } else {
            $verifiedBy = 'manual';
        }

        // Check if already clocked in
        $active = WorkSession::getActiveSession($user->id, $user->restaurant_id);
        if ($active) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже на смене',
                'data' => $active,
            ], 422);
        }

        // Create session with verification info
        $session = WorkSession::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $user->id,
            'clock_in' => now(),
            'clock_in_ip' => $request->ip(),
            'clock_in_verified_by' => $verifiedBy,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена начата',
            'data' => $session,
        ]);
    }

    /**
     * Clock out with optional biometric verification
     */
    public function clockOutWithBiometric(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if biometric is required
        if ($user->require_biometric_clock) {
            $validated = $request->validate([
                'biometric' => 'required|array',
            ]);

            $webAuthn = app(\App\Services\WebAuthnService::class);

            try {
                $credential = $webAuthn->verifyAuthentication($user, $validated['biometric']);
                $verifiedBy = 'biometric:' . $credential->device_type;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Биометрия не подтверждена: ' . $e->getMessage(),
                ], 400);
            }
        } else {
            $verifiedBy = 'manual';
        }

        $active = WorkSession::getActiveSession($user->id, $user->restaurant_id);
        if (!$active) {
            return response()->json([
                'success' => false,
                'message' => 'Вы не на смене',
            ], 422);
        }

        $active->update([
            'clock_out' => now(),
            'clock_out_ip' => $request->ip(),
            'clock_out_verified_by' => $verifiedBy,
            'status' => 'completed',
            'hours_worked' => round($active->clock_in->diffInMinutes(now()) / 60, 2),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена завершена',
            'data' => $active->fresh(),
        ]);
    }
}
