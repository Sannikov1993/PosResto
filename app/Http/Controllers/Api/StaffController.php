<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\Tip;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // СОТРУДНИКИ
    // ==========================================

    /**
     * Список сотрудников
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true)->whereNull('fired_at');
            } elseif ($request->input('status') === 'fired') {
                $query->whereNotNull('fired_at');
            }
        } elseif ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);

        if ($request->has('page')) {
            $paginated = $query->orderBy('name')->paginate($perPage);
            $users = $paginated->getCollection();
            $enrichedUsers = $this->enrichUsers($users);

            return response()->json([
                'success' => true,
                'data' => $enrichedUsers,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // Обратная совместимость: без page возвращаем с лимитом
        $users = $query->orderBy('name')->limit($perPage)->get();
        $enrichedUsers = $this->enrichUsers($users);

        return response()->json([
            'success' => true,
            'data' => $enrichedUsers,
        ]);
    }

    /**
     * Обогащение коллекции сотрудников статистикой (батч-запросы вместо N+1)
     */
    private function enrichUsers($users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->toArray();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Батч: активные смены (кто сейчас работает)
        $activeEntries = TimeEntry::whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('user_id');

        // Батч: заказы за месяц (count + sum)
        $orderStats = Order::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as orders_sum'))
            ->get()
            ->keyBy('user_id');

        // Батч: часы работы за месяц
        $hoursStats = TimeEntry::whereIn('user_id', $userIds)
            ->whereBetween('clock_in', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(worked_minutes) as total_minutes'))
            ->get()
            ->keyBy('user_id');

        // Батч: чаевые за месяц
        $tipsStats = Tip::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(amount) as total_tips'))
            ->get()
            ->keyBy('user_id');

        // Батч: ожидающие приглашения
        $pendingInvitations = \App\Models\StaffInvitation::whereIn('user_id', $userIds)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->pluck('user_id')
            ->flip()
            ->toArray();

        return $users->map(function ($user) use ($activeEntries, $orderStats, $hoursStats, $tipsStats, $pendingInvitations) {
            $activeEntry = $activeEntries->get($user->id);
            $orders = $orderStats->get($user->id);
            $hours = $hoursStats->get($user->id);
            $tips = $tipsStats->get($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'login' => $user->login,
                'phone' => $user->phone,
                'role' => $user->role,
                'position' => $user->position,
                'is_active' => $user->is_active,
                'has_pin' => !empty($user->pin_code),
                'has_password' => !empty($user->password),
                'pending_invitation' => isset($pendingInvitations[$user->id]),
                'hire_date' => $user->hire_date,
                'hired_at' => $user->hire_date,
                'birth_date' => $user->birth_date,
                'address' => $user->address,
                'emergency_contact' => $user->emergency_contact,
                'salary' => $user->salary,
                'salary_type' => $user->salary_type ?? 'fixed',
                'hourly_rate' => $user->hourly_rate,
                'sales_percent' => $user->percent_rate,
                'bank_card' => $user->bank_card,
                'fired_at' => $user->fired_at,
                'fire_reason' => $user->fire_reason,
                'is_working' => $activeEntry !== null,
                'current_shift_start' => $activeEntry?->clock_in?->format('H:i'),
                'month_orders_count' => $orders?->orders_count ?? 0,
                'month_orders_sum' => round($orders?->orders_sum ?? 0, 2),
                'month_hours_worked' => round(($hours?->total_minutes ?? 0) / 60, 1),
                'month_tips' => round($tips?->total_tips ?? 0, 2),
            ];
        })->toArray();
    }

    /**
     * Создать сотрудника
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|exists:roles,key',
            'login' => 'nullable|string|max:100', // Custom login identifier
            'password' => 'nullable|string|min:6',
            'pin_code' => 'nullable|string|min:4|max:6',
            'pin' => 'nullable|string|min:4|max:4',
            'send_invitation' => 'boolean', // Create invitation after saving
            'is_active' => 'boolean',
            'hire_date' => 'nullable|date',
            'hired_at' => 'nullable|date', // alias from frontend
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:fixed,hourly,percent,mixed',
            'hourly_rate' => 'nullable|numeric|min:0',
            'sales_percent' => 'nullable|numeric|min:0|max:100',
            'bank_card' => 'nullable|string|max:19',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Map frontend field names to backend
        $hireDate = $validated['hired_at'] ?? $validated['hire_date'] ?? null;
        $pinCode = $validated['pin'] ?? $validated['pin_code'] ?? null;
        $percentRate = $validated['sales_percent'] ?? null;
        $login = $validated['login'] ?? $validated['email'] ?? null;
        $hasPassword = !empty($validated['password']);
        $sendInvitation = $validated['send_invitation'] ?? false;

        $restaurantId = $this->getRestaurantId($request);

        // Найти role_id по ключу роли (приоритет у роли ресторана)
        $roleRecord = Role::where('key', $validated['role'])
            ->where('restaurant_id', $restaurantId)
            ->first();
        // Если нет роли ресторана, ищем системную
        if (!$roleRecord) {
            $roleRecord = Role::where('key', $validated['role'])
                ->whereNull('restaurant_id')
                ->first();
        }
        $roleId = $roleRecord?->id;

        // Проверка уникальности PIN
        if ($pinCode) {
            $pinExists = User::where('restaurant_id', $restaurantId)
                ->where('pin_lookup', User::hashPinForLookup($pinCode))
                ->exists();

            if ($pinExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот PIN-код уже используется другим сотрудником. Выберите другой.',
                ], 422);
            }
        }

        // Проверка уникальности логина
        if ($login) {
            $loginExists = User::where('login', $login)->exists();
            if ($loginExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот логин уже используется. Выберите другой.',
                ], 422);
            }
        }

        try {
            // Собираем только non-null поля, чтобы SQLite использовал defaults для NOT NULL колонок
            $userData = [
                'restaurant_id' => $restaurantId,
                'tenant_id' => $request->user()?->tenant_id,
                'name' => $validated['name'],
                'role' => $validated['role'],
                'role_id' => $roleId,
                // Если пароль не указан — генерируем случайный (cast 'hashed' в модели хеширует автоматически)
                'password' => $hasPassword ? $validated['password'] : \Str::random(32),
                'is_active' => $validated['is_active'] ?? true,
                'is_courier' => $validated['role'] === 'courier',
            ];

            // Опциональные поля — добавляем только если значение не null
            if (!empty($validated['email'])) {
                $userData['email'] = $validated['email'];
            }
            if ($login) {
                $userData['login'] = $login;
            }
            if (!empty($validated['phone'])) {
                $userData['phone'] = $validated['phone'];
            }
            if ($pinCode) {
                $userData['pin_code'] = \Hash::make($pinCode);
                $userData['pin_lookup'] = User::hashPinForLookup($pinCode);
            }
            if ($validated['role'] === 'courier') {
                $userData['courier_status'] = 'available';
            }
            if ($hireDate) {
                $userData['hire_date'] = $hireDate;
            }
            if (!empty($validated['birth_date'])) {
                $userData['birth_date'] = $validated['birth_date'];
            }
            if (!empty($validated['address'])) {
                $userData['address'] = $validated['address'];
            }
            if (!empty($validated['emergency_contact'])) {
                $userData['emergency_contact'] = $validated['emergency_contact'];
            }
            if (isset($validated['salary'])) {
                $userData['salary'] = $validated['salary'];
            }
            if (!empty($validated['salary_type'])) {
                $userData['salary_type'] = $validated['salary_type'];
            }
            if (isset($validated['hourly_rate'])) {
                $userData['hourly_rate'] = $validated['hourly_rate'];
            }
            if ($percentRate !== null) {
                $userData['percent_rate'] = $percentRate;
            }
            if (!empty($validated['bank_card'])) {
                $userData['bank_card'] = $validated['bank_card'];
            }
            if (!empty($validated['notes'])) {
                $userData['notes'] = $validated['notes'];
            }

            $user = User::create($userData);
        } catch (\Throwable $e) {
            \Log::error('Staff creation failed', [
                'error' => $e->getMessage(),
                'restaurant_id' => $restaurantId,
                'tenant_id' => $request->user()?->tenant_id,
                'role' => $validated['role'],
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Ошибка создания сотрудника: ' . $e->getMessage() : 'Ошибка создания сотрудника',
            ], 500);
        }

        // Create invitation if requested
        $invitation = null;
        if ($sendInvitation) {
            try {
                $invitation = \App\Models\StaffInvitation::create([
                    'restaurant_id' => $restaurantId,
                    'tenant_id' => $request->user()?->tenant_id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                    'role_id' => $user->role_id,
                    'token' => \Str::random(64),
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                    'created_by' => $request->user()?->id,
                ]);
            } catch (\Throwable $e) {
                \Log::error('Staff invitation creation failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $sendInvitation ? 'Сотрудник создан, приглашение сформировано' : 'Сотрудник создан',
            'data' => $user,
            'invitation' => $invitation,
        ], 201);
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
        $ordersCount = Order::where('user_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->count();

        $ordersRevenue = Order::where('user_id', $user->id)
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
            'role' => 'sometimes|exists:roles,key',
            'login' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6',
            'position' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'send_invitation' => 'boolean',
            'hire_date' => 'nullable|date',
            'hired_at' => 'nullable|date', // alias for hire_date from frontend
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:fixed,hourly,percent,mixed',
            'hourly_rate' => 'nullable|numeric|min:0',
            'sales_percent' => 'nullable|numeric|min:0|max:100',
            'bank_card' => 'nullable|string|max:19',
            'notes' => 'nullable|string|max:1000',
            'pin' => 'nullable|string|min:4|max:4',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Обновляем PIN отдельно если передан
        if (!empty($validated['pin'])) {
            // Проверка уникальности PIN
            $pinExists = User::where('restaurant_id', $restaurantId)
                ->where('pin_lookup', User::hashPinForLookup($validated['pin']))
                ->where('id', '!=', $user->id)
                ->exists();

            if ($pinExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот PIN-код уже используется другим сотрудником.',
                ], 422);
            }

            $user->setPin($validated['pin']);
            unset($validated['pin']);
        }

        // Обновляем логин если передан
        if (isset($validated['login']) && $validated['login'] !== $user->login) {
            // Проверка уникальности логина
            $loginExists = User::where('login', $validated['login'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($loginExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот логин уже используется.',
                ], 422);
            }
        }

        // Обновляем пароль если передан (cast 'hashed' в модели хеширует автоматически)
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        // Map frontend field names to backend
        if (isset($validated['hired_at'])) {
            $validated['hire_date'] = $validated['hired_at'];
            unset($validated['hired_at']);
        }

        if (isset($validated['sales_percent'])) {
            $validated['percent_rate'] = $validated['sales_percent'];
            unset($validated['sales_percent']);
        }

        // Обновляем is_courier и role_id при изменении роли
        if (isset($validated['role'])) {
            $validated['is_courier'] = $validated['role'] === 'courier';
            if ($validated['role'] === 'courier' && !$user->courier_status) {
                $validated['courier_status'] = 'available';
            }

            // Найти role_id по ключу роли
            $roleRecord = Role::where('key', $validated['role'])
                ->where(function ($q) use ($restaurantId) {
                    $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurantId);
                })
                ->first();
            if ($roleRecord) {
                $validated['role_id'] = $roleRecord->id;
            }
        }

        // Remove send_invitation from validated data (handled separately)
        $sendInvitation = $validated['send_invitation'] ?? false;
        unset($validated['send_invitation']);

        $user->update($validated);

        // Create invitation if requested
        $invitation = null;
        if ($sendInvitation) {
            // Delete any existing pending invitations
            \App\Models\StaffInvitation::where('user_id', $user->id)
                ->whereNull('accepted_at')
                ->delete();

            $invitation = \App\Models\StaffInvitation::create([
                'restaurant_id' => $restaurantId,
                'tenant_id' => $request->user()?->tenant_id,
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'token' => \Str::random(64),
                'expires_at' => now()->addDays(7),
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $sendInvitation ? 'Данные обновлены, приглашение сформировано' : 'Данные обновлены',
            'data' => $user->fresh(),
            'invitation' => $invitation,
        ]);
    }

    /**
     * Удалить сотрудника
     */
    public function destroy(User $user): JsonResponse
    {
        // Проверяем, нет ли активной смены
        $activeEntry = TimeEntry::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить сотрудника с активной сменой',
            ], 422);
        }

        // Мягкое удаление - деактивируем
        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник деактивирован',
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
            ->where('restaurant_id', $this->getRestaurantId($request));

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
            'restaurant_id' => $this->getRestaurantId($request),
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
        $restaurantId = $this->getRestaurantId($request);

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

        $restaurantId = $this->getRestaurantId($request);
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
            ->where('restaurant_id', $this->getRestaurantId($request));

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
        $restaurantId = $this->getRestaurantId($request);

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
            ->where('restaurant_id', $this->getRestaurantId($request));

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
            'restaurant_id' => $this->getRestaurantId($request),
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

    // ==========================================
    // РОЛИ И ПРАВА
    // ==========================================

    /**
     * Список ролей
     */
    public function roles(): JsonResponse
    {
        $roles = collect(User::getStaffRoles())->map(function ($label, $key) {
            $permissions = User::getRolePermissions()[$key] ?? [];
            return [
                'key' => $key,
                'label' => $label,
                'permissions' => $permissions,
                'users_count' => User::where('role', $key)->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Права доступа для роли
     */
    public function rolePermissions(string $role): JsonResponse
    {
        $permissions = User::getRolePermissions()[$role] ?? [];
        $allPermissions = $this->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'role_label' => User::getRoles()[$role] ?? $role,
                'permissions' => $permissions,
                'all_permissions' => $allPermissions,
            ],
        ]);
    }

    /**
     * Все доступные права
     */
    private function getAllPermissions(): array
    {
        return [
            'staff' => [
                'label' => 'Персонал',
                'permissions' => [
                    'staff.view' => 'Просмотр',
                    'staff.create' => 'Создание',
                    'staff.edit' => 'Редактирование',
                    'staff.delete' => 'Удаление',
                ],
            ],
            'menu' => [
                'label' => 'Меню',
                'permissions' => [
                    'menu.view' => 'Просмотр',
                    'menu.create' => 'Создание',
                    'menu.edit' => 'Редактирование',
                    'menu.delete' => 'Удаление',
                ],
            ],
            'orders' => [
                'label' => 'Заказы',
                'permissions' => [
                    'orders.view' => 'Просмотр',
                    'orders.create' => 'Создание',
                    'orders.edit' => 'Редактирование',
                    'orders.cancel' => 'Отмена',
                ],
            ],
            'reports' => [
                'label' => 'Отчёты',
                'permissions' => [
                    'reports.view' => 'Просмотр',
                    'reports.export' => 'Экспорт',
                ],
            ],
            'settings' => [
                'label' => 'Настройки',
                'permissions' => [
                    'settings.view' => 'Просмотр',
                    'settings.edit' => 'Редактирование',
                ],
            ],
            'loyalty' => [
                'label' => 'Лояльность',
                'permissions' => [
                    'loyalty.view' => 'Просмотр',
                    'loyalty.edit' => 'Редактирование',
                ],
            ],
            'finance' => [
                'label' => 'Финансы',
                'permissions' => [
                    'finance.view' => 'Просмотр',
                    'finance.edit' => 'Редактирование',
                ],
            ],
            'reservations' => [
                'label' => 'Бронирования',
                'permissions' => [
                    'reservations.view' => 'Просмотр',
                    'reservations.create' => 'Создание',
                    'reservations.edit' => 'Редактирование',
                ],
            ],
        ];
    }

    // ==========================================
    // PIN-КОД
    // ==========================================

    /**
     * Генерация нового PIN-кода
     */
    public function generatePin(): JsonResponse
    {
        $pin = User::generatePin();

        // Проверяем уникальность
        while (User::where('pin_code', \Hash::make($pin))->exists()) {
            $pin = User::generatePin();
        }

        return response()->json([
            'success' => true,
            'data' => ['pin' => $pin],
        ]);
    }

    /**
     * Сменить PIN-код сотрудника
     */
    public function changePin(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6|regex:/^\d+$/',
        ]);

        $user->setPin($validated['pin']);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код изменён',
        ]);
    }

    /**
     * Проверить PIN-код сотрудника (для подтверждения действий)
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
            'role' => 'nullable|string', // Минимальная роль для подтверждения
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $requiredRole = $validated['role'] ?? null;

        // Роли по уровню доступа (от низшего к высшему)
        $roleHierarchy = ['waiter' => 1, 'cashier' => 2, 'cook' => 2, 'courier' => 1, 'hostess' => 1, 'manager' => 3, 'admin' => 4, 'owner' => 5, 'super_admin' => 5];

        // Ищем сотрудника по PIN
        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->get();

        foreach ($users as $user) {
            if ($user->verifyPin($validated['pin'])) {
                // Проверяем роль
                if ($requiredRole) {
                    $userLevel = $roleHierarchy[$user->role] ?? 0;
                    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;

                    if ($userLevel < $requiredLevel) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Недостаточно прав для этого действия',
                        ], 403);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'PIN подтверждён',
                    'staff_id' => $user->id,
                    'staff_name' => $user->name,
                    'staff_role' => $user->role,
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Неверный PIN-код',
        ], 401);
    }

    /**
     * Сменить пароль сотрудника
     */
    public function changePassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'nullable|string|min:6',
        ]);

        // Cast 'hashed' в модели хеширует автоматически — НЕ использовать Hash::make()
        $user->update([
            'password' => $validated['password'] ?? \Str::random(12),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Пароль изменён',
        ]);
    }

    /**
     * Отправить ссылку для сброса пароля сотруднику
     */
    public function sendPasswordReset(Request $request, User $user): JsonResponse
    {
        if (!$user->email) {
            return response()->json([
                'success' => false,
                'message' => 'У сотрудника не указан email',
            ], 422);
        }

        // Delete any existing password reset invitations
        \App\Models\StaffInvitation::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->delete();

        // Create password reset invitation
        $invitation = \App\Models\StaffInvitation::create([
            'restaurant_id' => $user->restaurant_id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'type' => 'password_reset',
            'token' => \Str::random(64),
            'expires_at' => now()->addHours(24),
            'created_by' => $request->user()?->id,
        ]);

        // TODO: Send email notification with password reset link

        return response()->json([
            'success' => true,
            'message' => 'Ссылка для сброса пароля отправлена на ' . $user->email,
            'data' => [
                'reset_url' => url('/staff/reset-password/' . $invitation->token),
            ],
        ]);
    }

    /**
     * Переключить статус сотрудника
     */
    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Сотрудник активирован' : 'Сотрудник деактивирован',
            'data' => $user,
        ]);
    }

    /**
     * ===========================================
     * BACKOFFICE: Расписание и приглашения
     * ===========================================
     */

    /**
     * Расписание (alias для weekSchedule)
     */
    public function schedule(Request $request): JsonResponse
    {
        return $this->weekSchedule($request);
    }

    /**
     * Создать запись в расписании
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        return $this->createShift($request);
    }

    /**
     * Обновить запись в расписании
     */
    public function updateSchedule(Request $request, $schedule): JsonResponse
    {
        $shift = \App\Models\Shift::findOrFail($schedule);
        return $this->updateShift($request, $shift);
    }

    /**
     * Удалить запись из расписания
     */
    public function deleteSchedule($schedule): JsonResponse
    {
        $shift = \App\Models\Shift::findOrFail($schedule);
        return $this->deleteShift($shift);
    }

    /**
     * Отправить приглашение сотруднику
     */
    public function sendInvite(User $user): JsonResponse
    {
        // Создаём приглашение
        $invitation = \App\Models\StaffInvitation::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => \Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // TODO: Отправить email

        return response()->json([
            'success' => true,
            'message' => 'Приглашение отправлено',
            'data' => $invitation,
        ]);
    }

    /**
     * Список приглашений
     */
    public function invitations(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $invitations = \App\Models\StaffInvitation::where('restaurant_id', $restaurantId)
            ->with(['creator', 'acceptedByUser'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invitations,
        ]);
    }

    /**
     * Повторно отправить приглашение
     */
    public function resendInvitation($invitation): JsonResponse
    {
        $inv = \App\Models\StaffInvitation::findOrFail($invitation);
        $inv->update([
            'token' => \Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // TODO: Отправить email

        return response()->json([
            'success' => true,
            'message' => 'Приглашение отправлено повторно',
        ]);
    }

    /**
     * Отменить приглашение
     */
    public function cancelInvitation($invitation): JsonResponse
    {
        $inv = \App\Models\StaffInvitation::findOrFail($invitation);
        $inv->delete();

        return response()->json([
            'success' => true,
            'message' => 'Приглашение отменено',
        ]);
    }
}
