<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\Order;
use App\Models\Tip;
use App\Services\StaffEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffController extends Controller
{
    use Traits\ResolvesRestaurantId;

    private StaffEnrichmentService $enrichmentService;

    public function __construct(StaffEnrichmentService $enrichmentService)
    {
        $this->enrichmentService = $enrichmentService;
    }

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
            $enrichedUsers = $this->enrichmentService->enrichUsers($users);

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
        $enrichedUsers = $this->enrichmentService->enrichUsers($users);

        return response()->json([
            'success' => true,
            'data' => $enrichedUsers,
        ]);
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
            'login' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6',
            'pin_code' => 'nullable|string|min:4|max:6',
            'pin' => 'nullable|string|min:4|max:4',
            'send_invitation' => 'boolean',
            'is_active' => 'boolean',
            'hire_date' => 'nullable|date',
            'hired_at' => 'nullable|date',
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
            $userData = [
                'restaurant_id' => $restaurantId,
                'tenant_id' => $request->user()?->tenant_id,
                'name' => $validated['name'],
                'role' => $validated['role'],
                'role_id' => $roleId,
                'password' => $hasPassword ? $validated['password'] : \Str::random(32),
                'is_active' => $validated['is_active'] ?? true,
                'is_courier' => $validated['role'] === 'courier',
            ];

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
            'hired_at' => 'nullable|date',
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

        // Обновляем пароль если передан
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
        $activeEntry = TimeEntry::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить сотрудника с активной сменой',
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник деактивирован',
        ]);
    }

    /**
     * Переключить статус сотрудника
     */
    public function toggleActive(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Сотрудник активирован' : 'Сотрудник деактивирован',
            'data' => $user,
        ]);
    }
}
