<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\StaffInvitation;
use App\Models\SalaryPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffManagementController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список сотрудников
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['shifts' => function($q) {
                $q->where('date', '>=', now()->subDays(30));
            }])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Фильтр по роли
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
        } else {
            // По умолчанию только активные
            $query->where('is_active', true);
        }

        // Поиск
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $staff = $query->orderBy('name')->get();

        // Добавляем статистику
        $staff->each(function($user) {
            $user->orders_count = $user->orders()->whereDate('created_at', '>=', now()->subDays(30))->count();
            $user->orders_sum = $user->orders()->whereDate('created_at', '>=', now()->subDays(30))->sum('total');
            $user->hours_worked = $user->shifts->sum('duration') ?? 0;
        });

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    /**
     * Создать сотрудника (админом)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|exists:roles,key',
            'role_id' => 'nullable|integer|exists:roles,id',
            'pin_code' => 'nullable|string|size:4',
            'password' => 'nullable|string|min:6',
            'salary_type' => 'required|in:fixed,hourly,mixed,percent',
            'salary' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'percent_rate' => 'nullable|numeric|min:0|max:100',
            'hire_date' => 'nullable|date',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:100',
            'emergency_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // ✅ Проверка уникальности PIN для официантов
        if ($validated['role'] === 'waiter' && isset($validated['pin_code'])) {
            $pinExists = User::where('restaurant_id', $restaurantId)
                ->where('pin_lookup', $validated['pin_code'])
                ->exists();

            if ($pinExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот PIN-код уже используется другим официантом. Выберите другой.',
                ], 422);
            }
        }

        // Автоматическое заполнение role_id из Role по key
        $roleId = $validated['role_id'] ?? null;
        if (!$roleId && !empty($validated['role'])) {
            $roleRecord = Role::where('key', $validated['role'])
                ->where('restaurant_id', $restaurantId)
                ->first();
            $roleId = $roleRecord?->id;
        }

        $userData = [
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'role_id' => $roleId,
            'password' => $validated['password'] ?? '123456',
            'salary_type' => $validated['salary_type'],
            'salary' => $validated['salary'] ?? 0,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'percent_rate' => $validated['percent_rate'] ?? null,
            'hire_date' => $validated['hire_date'] ?? now(),
            'birth_date' => $validated['birth_date'] ?? null,
            'address' => $validated['address'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'emergency_phone' => $validated['emergency_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ];

        // Добавляем PIN-код и pin_lookup если указан
        if (isset($validated['pin_code'])) {
            $userData['pin_code'] = Hash::make($validated['pin_code']);
            $userData['pin_lookup'] = $validated['pin_code'];
        }

        $user = User::create($userData);

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник создан',
            'data' => $user,
        ], 201);
    }

    /**
     * Получить сотрудника
     */
    public function show(User $user): JsonResponse
    {
        $user->load('shifts', 'orders');

        // Статистика за 30 дней
        $user->stats = [
            'orders_count' => $user->orders()->whereDate('created_at', '>=', now()->subDays(30))->count(),
            'orders_sum' => $user->orders()->whereDate('created_at', '>=', now()->subDays(30))->sum('total'),
            'hours_worked' => $user->shifts()->where('date', '>=', now()->subDays(30))->sum('duration') ?? 0,
            'avg_check' => $user->orders()->whereDate('created_at', '>=', now()->subDays(30))->avg('total') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Обновить сотрудника
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'role' => 'sometimes|string|exists:roles,key',
            'role_id' => 'sometimes|nullable|integer|exists:roles,id',
            'salary_type' => 'sometimes|in:fixed,hourly,mixed,percent',
            'salary' => 'sometimes|nullable|numeric|min:0',
            'hourly_rate' => 'sometimes|nullable|numeric|min:0',
            'percent_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'birth_date' => 'sometimes|nullable|date',
            'address' => 'sometimes|nullable|string|max:255',
            'emergency_contact' => 'sometimes|nullable|string|max:100',
            'emergency_phone' => 'sometimes|nullable|string|max:20',
            'notes' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        // Автоматическое обновление role_id при смене role
        if (isset($validated['role']) && !isset($validated['role_id'])) {
            $roleRecord = Role::where('key', $validated['role'])
                ->where('restaurant_id', $user->restaurant_id)
                ->first();
            if ($roleRecord) {
                $validated['role_id'] = $roleRecord->id;
            }
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Данные обновлены',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Изменить PIN-код
     */
    public function updatePin(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'pin_code' => 'required|string|size:4',
        ]);

        // ✅ Проверка уникальности PIN для официантов
        if ($user->role === 'waiter') {
            $pinExists = User::where('restaurant_id', $user->restaurant_id)
                ->where('pin_lookup', $validated['pin_code'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($pinExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот PIN-код уже используется другим официантом. Выберите другой.',
                ], 422);
            }
        }

        $user->setPin($validated['pin_code']);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код обновлён',
        ]);
    }

    /**
     * Удалить PIN-код
     */
    public function deletePin(User $user): JsonResponse
    {
        $user->clearPin();

        return response()->json([
            'success' => true,
            'message' => 'PIN-код удалён',
        ]);
    }

    /**
     * Изменить пароль
     */
    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        // User model has 'hashed' cast — auto-hashes password on assignment
        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Пароль обновлён',
        ]);
    }

    /**
     * Уволить сотрудника
     */
    public function fire(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        // Увольняем сотрудника
        $user->update([
            'is_active' => false,
            'fired_at' => now(),
            'fire_reason' => $validated['reason'] ?? null,
        ]);

        // ✅ КРИТИЧНО: Удаляем все device_sessions
        $deviceSessionService = app(\App\Services\DeviceSessionService::class);
        $deviceSessionService->revokeAllUserSessions($user->id);

        // Отзываем все Sanctum-токены
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник уволен. Все сессии завершены.',
        ]);
    }

    /**
     * Восстановить сотрудника
     */
    public function restore(User $user): JsonResponse
    {
        $user->update([
            'is_active' => true,
            'fired_at' => null,
            'fire_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник восстановлен',
        ]);
    }

    /**
     * Отправить приглашение существующему сотруднику
     */
    public function sendUserInvite(User $user): JsonResponse
    {
        // Проверяем, что у сотрудника нет пароля
        if ($user->has_password) {
            return response()->json([
                'success' => false,
                'message' => 'У сотрудника уже есть пароль',
            ], 400);
        }

        // Отменяем предыдущие приглашения
        StaffInvitation::where('accepted_by', $user->id)
            ->orWhere(function($q) use ($user) {
                $q->where('restaurant_id', $user->restaurant_id)
                  ->where('email', $user->email)
                  ->where('status', 'pending');
            })
            ->update(['status' => 'cancelled']);

        // Создаём новое приглашение
        $invitation = StaffInvitation::create([
            'restaurant_id' => $user->restaurant_id,
            'created_by' => auth()->id(),
            'token' => StaffInvitation::generateToken(),
            'email' => $user->email,
            'phone' => $user->phone,
            'name' => $user->name,
            'role' => $user->role,
            'role_id' => $user->role_id,
            'salary_type' => $user->salary_type ?? 'fixed',
            'salary_amount' => $user->salary ?? 0,
            'hourly_rate' => $user->hourly_rate,
            'percent_rate' => $user->percent_rate,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
            'notes' => 'Приглашение для существующего сотрудника #' . $user->id,
        ]);

        // Связываем приглашение с пользователем
        $user->update(['invitation_id' => $invitation->id]);

        return response()->json([
            'success' => true,
            'message' => 'Приглашение создано',
            'data' => $invitation,
        ]);
    }

    // ==================== ПРИГЛАШЕНИЯ ====================

    /**
     * Список приглашений
     */
    public function invitations(Request $request): JsonResponse
    {
        $invitations = StaffInvitation::with('creator', 'acceptedByUser')
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    /**
     * Создать приглашение
     */
    public function createInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|exists:roles,key',
            'role_id' => 'nullable|integer|exists:roles,id',
            'salary_type' => 'required|in:fixed,hourly,mixed,percent',
            'salary_amount' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'percent_rate' => 'nullable|numeric|min:0|max:100',
            'expires_days' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string|max:500',
        ]);

        // Получаем ID создателя из разных источников
        $createdBy = $request->input('user_id') ?? $request->input('created_by') ?? auth()->id() ?? 1;

        $invitation = StaffInvitation::createInvitation([
            'restaurant_id' => $this->getRestaurantId($request),
            'created_by' => $createdBy,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'role_id' => $validated['role_id'] ?? null,
            'salary_type' => $validated['salary_type'],
            'salary_amount' => $validated['salary_amount'] ?? 0,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'percent_rate' => $validated['percent_rate'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'expires_at' => Carbon::now()->addDays($validated['expires_days'] ?? 7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Приглашение создано',
            'data' => $invitation,
            'invite_url' => $invitation->invite_url,
        ], 201);
    }

    /**
     * Получить приглашение по токену (для страницы регистрации)
     */
    public function getInvitation(string $token): JsonResponse
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Приглашение не найдено',
            ], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => $invitation->is_expired ? 'Срок приглашения истёк' : 'Приглашение недействительно',
            ], 410);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $invitation->name,
                'email' => $invitation->email,
                'phone' => $invitation->phone,
                'role' => $invitation->role,
                'role_label' => $invitation->role_label,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Принять приглашение (активация аккаунта сотрудника)
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Приглашение недействительно или истекло',
            ], 410);
        }

        // Валидация с учётом того, что пользователь может предоставить свои данные
        $rules = [
            'password' => 'required|string|min:6',
        ];

        // Если в приглашении нет имени - требуем от пользователя
        if (!$invitation->name) {
            $rules['name'] = 'required|string|max:100';
        } else {
            $rules['name'] = 'nullable|string|max:100';
        }

        // Если в приглашении нет контактов - требуем хотя бы один
        if (!$invitation->email && !$invitation->phone) {
            $rules['email'] = 'required_without:phone|nullable|email|unique:users,email';
            $rules['phone'] = 'required_without:email|nullable|string|max:20|unique:users,phone';
        } else {
            $rules['email'] = 'nullable|email|unique:users,email';
            $rules['phone'] = 'nullable|string|max:20|unique:users,phone';
        }

        $validated = $request->validate($rules);

        // Определяем итоговые данные (приоритет у приглашения, затем пользователь)
        $name = $invitation->name ?? $validated['name'] ?? 'Сотрудник';
        $email = $invitation->email ?? ($validated['email'] ?? null);
        $phone = $invitation->phone ?? ($validated['phone'] ?? null);

        // Проверяем, что есть хотя бы один способ входа
        if (!$email && !$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо указать email или телефон для входа',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Ищем существующего пользователя по invitation_id
            $user = User::where('invitation_id', $invitation->id)->first();

            // User model has 'hashed' cast — auto-hashes password on assignment
            if ($user) {
                // Активируем существующего пользователя - устанавливаем пароль и обновляем данные
                $updateData = [
                    'password' => $validated['password'],
                ];
                // Обновляем данные, если они были пустыми
                if (!$user->name && $name) $updateData['name'] = $name;
                if (!$user->email && $email) $updateData['email'] = $email;
                if (!$user->phone && $phone) $updateData['phone'] = $phone;

                $user->update($updateData);
            } else {
                // Создаём нового пользователя
                $user = User::create([
                    'restaurant_id' => $invitation->restaurant_id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $invitation->role,
                    'role_id' => $invitation->role_id,
                    'password' => $validated['password'],
                    'salary_type' => $invitation->salary_type,
                    'salary' => $invitation->salary_amount,
                    'hourly_rate' => $invitation->hourly_rate,
                    'percent_rate' => $invitation->percent_rate,
                    'hire_date' => now(),
                    'invitation_id' => $invitation->id,
                    'is_active' => true,
                ]);
            }

            // Обновляем приглашение данными, если они были предоставлены пользователем
            if (!$invitation->name && $name) {
                $invitation->name = $name;
            }
            if (!$invitation->email && $email) {
                $invitation->email = $email;
            }
            if (!$invitation->phone && $phone) {
                $invitation->phone = $phone;
            }

            // Отмечаем приглашение как принятое
            $invitation->accept($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Аккаунт активирован! Теперь вы можете войти в систему.',
                'data' => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка активации: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отменить приглашение
     */
    public function cancelInvitation(StaffInvitation $invitation): JsonResponse
    {
        $invitation->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Приглашение отменено',
        ]);
    }

    /**
     * Повторно отправить приглашение (генерирует новый токен)
     */
    public function resendInvitation(StaffInvitation $invitation): JsonResponse
    {
        $invitation->update([
            'token' => StaffInvitation::generateToken(),
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Приглашение обновлено',
            'data' => $invitation->fresh(),
            'invite_url' => $invitation->invite_url,
        ]);
    }

    // ==================== РОЛИ И ПРАВА ====================

    /**
     * Список ролей
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')
            ->where(function($q) use ($request) {
                $q->whereNull('restaurant_id')
                  ->orWhere('restaurant_id', $this->getRestaurantId($request));
            })
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Список всех разрешений
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Permission::getGroups(),
            'flat' => Permission::getAllPermissions(),
        ]);
    }

    /**
     * Обновить права роли
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Права роли обновлены',
            'data' => $role->fresh('permissions'),
        ]);
    }

    // ==================== СПРАВОЧНИКИ ====================

    /**
     * Получить типы зарплаты
     */
    public function salaryTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StaffInvitation::getSalaryTypes(),
        ]);
    }

    /**
     * Получить доступные роли для создания сотрудников
     */
    public function availableRoles(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => User::getStaffRoles(),
        ]);
    }

    // ==================== ЗАРПЛАТНЫЕ НАЧИСЛЕНИЯ ====================

    /**
     * Список начислений
     */
    public function salaryPayments(Request $request): JsonResponse
    {
        $query = SalaryPayment::with(['user', 'creator'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтрация по месяцу и году
        if ($request->has('month') && $request->has('year')) {
            $month = (int) $request->input('month');
            $year = (int) $request->input('year');
            $query->whereMonth('created_at', $month)
                  ->whereYear('created_at', $year);
        }

        $payments = $query->orderByDesc('created_at')->limit(100)->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Создать начисление
     */
    public function createSalaryPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:salary,advance,bonus,penalty,overtime',
            'amount' => 'required|numeric',
            'hours_worked' => 'nullable|numeric|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:pending,paid',
        ]);

        $status = $validated['status'] ?? 'pending';

        $payment = SalaryPayment::create([
            'restaurant_id' => $this->getRestaurantId($request),
            'user_id' => $validated['user_id'],
            'created_by' => $request->input('user_id_creator') ?? auth()->id(),
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'hours_worked' => $validated['hours_worked'] ?? null,
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        $payment->load('user', 'creator');

        return response()->json([
            'success' => true,
            'message' => 'Начисление создано',
            'data' => $payment,
        ], 201);
    }

    /**
     * Обновить начисление (например, отметить как выплачено)
     */
    public function updateSalaryPayment(Request $request, SalaryPayment $payment): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,paid,cancelled',
            'payment_method' => 'nullable|string|max:30',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'paid') {
            $payment->markAsPaid($validated['payment_method'] ?? null);
        } elseif (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $payment->cancel();
        } else {
            $payment->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Начисление обновлено',
            'data' => $payment->fresh(['user', 'creator']),
        ]);
    }

    /**
     * Удалить начисление
     */
    public function deleteSalaryPayment(SalaryPayment $payment): JsonResponse
    {
        if ($payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить выплаченное начисление',
            ], 400);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Начисление удалено',
        ]);
    }
}
