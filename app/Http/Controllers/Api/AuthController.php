<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\PasswordResetMail;

class AuthController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Получить пользователя по Sanctum-токену из заголовка
     */
    private function getUserByToken(Request $request): ?User
    {
        $token = $request->bearerToken() ?? $request->header('X-Auth-Token');
        if (!$token) return null;

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$accessToken) return null;

        $user = $accessToken->tokenable;
        return ($user && $user->is_active) ? $user : null;
    }

    /**
     * Вход по PIN-коду (только для авторизованных устройств)
     */
    public function loginByPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
            'restaurant_id' => 'nullable|integer',
            'device_token' => 'nullable|string',
            'app_type' => 'nullable|string|in:pos,backoffice,waiter,courier,kitchen',
            'user_id' => 'nullable|integer',
        ]);

        $restaurantId = $validated['restaurant_id'] ?? $this->getRestaurantId($request);
        $userId = $validated['user_id'] ?? null;

        $user = null;

        if ($userId) {
            // Поиск конкретного пользователя по ID и проверка его PIN
            $candidate = User::where('id', $userId)
                ->where('is_active', true)
                ->first();

            if ($candidate) {
                // Проверяем PIN через pin_lookup
                if ($candidate->pin_lookup === $validated['pin']) {
                    $user = $candidate;
                }
                // Fallback на bcrypt
                elseif ($candidate->pin_code && Hash::check($validated['pin'], $candidate->pin_code)) {
                    $candidate->pin_lookup = $validated['pin'];
                    $candidate->save();
                    $user = $candidate;
                }
            }
        } else {
            // Поиск по PIN среди всех сотрудников ресторана (старое поведение)
            $user = User::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->where('pin_lookup', $validated['pin'])
                ->first();

            // Fallback на bcrypt
            if (!$user) {
                $users = User::where('restaurant_id', $restaurantId)
                    ->where('is_active', true)
                    ->whereNotNull('pin_code')
                    ->whereNull('pin_lookup')
                    ->get();

                foreach ($users as $u) {
                    if (Hash::check($validated['pin'], $u->pin_code)) {
                        $u->pin_lookup = $validated['pin'];
                        $u->save();
                        $user = $u;
                        break;
                    }
                }
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ], 401);
        }

        // ✅ БЕЗОПАСНОСТЬ: Вход по PIN
        $deviceToken = $validated['device_token'] ?? null;
        $appType = $request->input('app_type', null);

        // POS/backoffice — доверенные терминалы, device_token не требуется
        // Waiter/Courier — личные устройства, требуется device_token
        if (!in_array($appType, ['pos', 'backoffice'])) {
            if (!$deviceToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вход по PIN-коду доступен только на авторизованных устройствах. Войдите по логину и паролю.',
                    'reason' => 'device_not_authorized',
                    'require_full_login' => true,
                ], 403);
            }

            $deviceSessionService = app(\App\Services\DeviceSessionService::class);
            $tokenUser = $deviceSessionService->getUserByToken($deviceToken);

            if (!$tokenUser || $tokenUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Это устройство не авторизовано для данного пользователя. Войдите по логину и паролю.',
                    'reason' => 'device_user_mismatch',
                    'require_full_login' => true,
                ], 403);
            }
        }

        // ✅ ПРОВЕРКА: Доступ к интерфейсу (Enterprise-level security)
        if ($appType && !$user->isSuperAdmin() && !$user->isTenantOwner()) {
            $accessDenied = $this->checkInterfaceAccess($user, $appType);
            if ($accessDenied) {
                return $accessDenied;
            }
        }

        // ✅ ПРОВЕРКА: Активная смена для waiter/courier приложений (кроме главного админа)
        if (in_array($appType, ['waiter', 'courier']) && $user->id !== 1) {
            $hasActiveShift = \App\Models\WorkSession::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->exists();

            if (!$hasActiveShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы не на смене. Сначала отметьте начало работы.',
                    'reason' => 'no_active_shift',
                    'action_required' => 'clock_in',
                ], 403);
            }
        }

        $user->last_login_at = now();
        $user->save();

        $tokenName = $appType ?: 'pos';
        $newToken = $user->createToken($tokenName);

        $permissionData = $this->getUserPermissionData($user);

        return response()->json([
            'success' => true,
            'message' => 'Добро пожаловать, ' . $user->name . '!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'token' => $newToken->plainTextToken,
                'permissions' => $permissionData['permissions'],
                'limits' => $permissionData['limits'],
                'interface_access' => $permissionData['interface_access'],
            ],
        ]);
    }

    /**
     * Вход по email/телефону и паролю
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'nullable|string|required_without:email', // email или телефон
            'email' => 'nullable|string|required_without:login', // для обратной совместимости
            'password' => 'required|string',
            'app_type' => 'nullable|string|in:pos,backoffice,waiter,courier,kitchen',
        ]);

        $login = $validated['login'] ?? $validated['email'];

        // Ищем по email или телефону
        $user = User::where('is_active', true)
            ->where(function($query) use ($login) {
                $query->where('email', $login)
                      ->orWhere('phone', $login)
                      ->orWhere('phone', preg_replace('/[^0-9+]/', '', $login)); // Нормализованный телефон
            })
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный логин или пароль',
            ], 401);
        }

        // ✅ ПРОВЕРКА: Доступ к интерфейсу (если указан app_type)
        $appType = $request->input('app_type');
        if ($appType && !$user->isSuperAdmin() && !$user->isTenantOwner()) {
            $accessDenied = $this->checkInterfaceAccess($user, $appType);
            if ($accessDenied) {
                return $accessDenied;
            }
        }

        $user->last_login_at = now();
        $user->save();

        $newToken = $user->createToken($appType ?: 'web');
        $permissionData = $this->getUserPermissionData($user);

        return response()->json([
            'success' => true,
            'message' => 'Добро пожаловать!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'token' => $newToken->plainTextToken,
                'permissions' => $permissionData['permissions'],
                'limits' => $permissionData['limits'],
                'interface_access' => $permissionData['interface_access'],
            ],
        ]);
    }

    /**
     * Проверка токена
     */
    public function check(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->header('X-Auth-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен не предоставлен',
            ], 401);
        }

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный токен',
            ], 401);
        }

        $user = $accessToken->tokenable;

        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный токен',
            ], 401);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $permissionData = $this->getUserPermissionData($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'permissions' => $permissionData['permissions'],
                'limits' => $permissionData['limits'],
                'interface_access' => $permissionData['interface_access'],
            ],
        ]);
    }

    /**
     * Выход
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->header('X-Auth-Token');

        if ($token) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $accessToken->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Вы вышли из системы',
        ]);
    }

    /**
     * Получить список пользователей для выбора (только имена и аватары)
     * Фильтрует по доступу к интерфейсу если указан app_type
     */
    public function users(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $appType = $request->input('app_type'); // pos, backoffice, kitchen, delivery

        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with('roleRecord') // Загружаем связь с Role
            ->select('id', 'name', 'role', 'role_id', 'avatar', 'is_tenant_owner')
            ->orderBy('name')
            ->get();

        // Фильтруем по доступу к интерфейсу (Enterprise-level)
        if ($appType) {
            $interfaceMap = [
                'pos' => 'can_access_pos',
                'backoffice' => 'can_access_backoffice',
                'kitchen' => 'can_access_kitchen',
                'delivery' => 'can_access_delivery',
            ];

            $field = $interfaceMap[$appType] ?? null;

            if ($field) {
                $users = $users->filter(function ($user) use ($field) {
                    // Tenant owner имеет полный доступ
                    if ($user->is_tenant_owner) {
                        return true;
                    }

                    // Проверяем через Role
                    $role = $user->roleRecord;
                    if (!$role) {
                        // Fallback: ищем по строковому ключу role
                        $role = Role::where('key', $user->role)
                            ->where('restaurant_id', $user->restaurant_id ?? null)
                            ->first();
                    }

                    return $role && $role->$field;
                })->values();
            }
        }

        // Добавляем role_label для отображения
        $users = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'role_label' => User::getRoles()[$user->role] ?? $user->role,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Смена PIN-кода
     */
    public function changePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4|max:6',
        ]);

        $user = $this->getUserByToken($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        }

        if (!Hash::check($validated['current_pin'], $user->pin_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный текущий PIN',
            ], 400);
        }

        // ✅ Проверка уникальности PIN для официантов
        if ($user->role === 'waiter') {
            $pinExists = User::where('restaurant_id', $user->restaurant_id)
                ->where('pin_lookup', $validated['new_pin'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($pinExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот PIN-код уже используется другим официантом. Выберите другой.',
                ], 422);
            }
        }

        $user->update([
            'pin_code' => Hash::make($validated['new_pin']),
            'pin_lookup' => $validated['new_pin'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код изменён',
        ]);
    }

    /**
     * Запрос на восстановление пароля
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact' => 'required|string', // email или телефон
        ]);

        $contact = $validated['contact'];

        // Ищем пользователя по email или телефону
        $user = User::where('is_active', true)
            ->where(function($query) use ($contact) {
                $query->where('email', $contact)
                      ->orWhere('phone', $contact)
                      ->orWhere('phone', preg_replace('/[^0-9+]/', '', $contact));
            })
            ->first();

        // Всегда возвращаем успех (для безопасности - не раскрываем существование аккаунта)
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Если аккаунт существует, мы отправили ссылку для сброса пароля',
            ]);
        }

        // Генерируем токен сброса
        $token = Str::random(64);

        // Удаляем старые токены для этого пользователя
        DB::table('password_reset_tokens')
            ->where('email', $user->email ?? $user->phone)
            ->delete();

        // Сохраняем новый токен
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email ?? $user->phone,
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Формируем URL для сброса пароля
        $resetUrl = url('/reset-password?token=' . $token . '&email=' . urlencode($user->email ?? $user->phone));

        // Отправляем email если есть адрес
        $emailSent = false;
        if ($user->email) {
            try {
                Mail::to($user->email)->send(new PasswordResetMail($resetUrl, $user->name));
                $emailSent = true;
            } catch (\Exception $e) {
                // Логируем ошибку, но не показываем пользователю
                \Log::error('Failed to send password reset email: ' . $e->getMessage());
            }
        }

        $response = [
            'success' => true,
            'message' => 'Если аккаунт существует, мы отправили ссылку для сброса пароля',
        ];

        // В режиме разработки показываем дополнительную информацию
        if (config('app.debug')) {
            $response['debug_reset_url'] = $resetUrl;
            $response['debug_email_sent'] = $emailSent;
            if (!$emailSent && $user->email) {
                $response['debug_note'] = 'Email не отправлен - проверьте настройки SMTP в .env';
            }
        }

        return response()->json($response);
    }

    /**
     * Проверка токена сброса пароля
     */
    public function checkResetToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ], 400);
        }

        // Проверяем токен
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ], 400);
        }

        // Проверяем срок действия (1 час)
        if (Carbon::parse($record->created_at)->addHour()->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия ссылки истёк. Запросите новую.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Токен действителен',
        ]);
    }

    /**
     * Сброс пароля
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ], 400);
        }

        // Проверяем токен
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ], 400);
        }

        // Проверяем срок действия (1 час)
        if (Carbon::parse($record->created_at)->addHour()->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия ссылки истёк. Запросите новую.',
            ], 400);
        }

        // Ищем пользователя
        $user = User::where('email', $validated['email'])
            ->orWhere('phone', $validated['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден',
            ], 404);
        }

        // Обновляем пароль
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Удаляем использованный токен
        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Пароль успешно изменён! Теперь вы можете войти в систему.',
        ]);
    }

    /**
     * Вход с запоминанием устройства
     */
    public function loginWithDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'device_fingerprint' => 'required|string',
            'device_name' => 'nullable|string',
            'app_type' => 'required|string|in:pos,waiter,courier,backoffice',
            'remember_device' => 'boolean',
        ]);

        // Обычный логин
        $user = User::where('is_active', true)
            ->where(function($query) use ($validated) {
                $query->where('email', $validated['login'])
                      ->orWhere('phone', $validated['login'])
                      ->orWhere('login', $validated['login']);
            })
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный логин или пароль',
            ], 401);
        }

        // ✅ ПРОВЕРКА: Доступ к интерфейсу (Enterprise-level security)
        $appType = $validated['app_type'];
        if (!$user->isSuperAdmin() && !$user->isTenantOwner()) {
            $accessDenied = $this->checkInterfaceAccess($user, $appType);
            if ($accessDenied) {
                return $accessDenied;
            }
        }

        // ✅ ПРОВЕРКА: Активная смена для waiter/courier приложений (кроме главного админа)
        if (in_array($appType, ['waiter', 'courier']) && $user->id !== 1) {
            $hasActiveShift = \App\Models\WorkSession::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->exists();

            if (!$hasActiveShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы не на смене. Попросите администратора начать вашу смену или используйте биометрическую систему учета рабочего времени.',
                    'reason' => 'no_active_shift',
                ], 403);
            }
        }

        $user->update(['last_login_at' => now()]);

        $newToken = $user->createToken($appType);
        $permissionData = $this->getUserPermissionData($user);

        $response = [
            'success' => true,
            'message' => 'Добро пожаловать!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'token' => $newToken->plainTextToken,
                'permissions' => $permissionData['permissions'],
                'limits' => $permissionData['limits'],
                'interface_access' => $permissionData['interface_access'],
            ],
        ];

        // Если запомнить устройство
        if ($validated['remember_device'] ?? false) {
            $deviceSessionService = app(\App\Services\DeviceSessionService::class);

            try {
                $deviceSession = $deviceSessionService->createSession(
                    $user,
                    $validated['device_fingerprint'],
                    $validated['app_type'],
                    $validated['device_name'] ?? null
                );

                $response['data']['device_token'] = $deviceSession->token;
            } catch (\Exception $e) {
                // Логируем ошибку, но не блокируем вход
                \Log::error('Failed to create device session: ' . $e->getMessage());
            }
        }

        return response()->json($response);
    }

    /**
     * Автовход по токену устройства
     */
    public function deviceLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => 'required|string',
        ]);

        $deviceSessionService = app(\App\Services\DeviceSessionService::class);
        $user = $deviceSessionService->getUserByToken($validated['device_token']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Сессия устройства истекла или недействительна',
                'reason' => 'invalid_device_token',
            ], 401);
        }

        // Получаем app_type из device_session
        $deviceSession = \App\Models\DeviceSession::where('token', $validated['device_token'])->first();
        $appType = $deviceSession ? $deviceSession->app_type : null;

        // ✅ ПРОВЕРКА: Доступ к интерфейсу (Enterprise-level security)
        if ($appType && !$user->isSuperAdmin() && !$user->isTenantOwner()) {
            $accessDenied = $this->checkInterfaceAccess($user, $appType);
            if ($accessDenied) {
                return $accessDenied;
            }
        }

        // ✅ ПРОВЕРКА: Активная смена для waiter/courier приложений (кроме главного админа)
        if (in_array($appType, ['waiter', 'courier']) && $user->id !== 1) {
            $hasActiveShift = \App\Models\WorkSession::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->exists();

            if (!$hasActiveShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы не на смене. Сначала отметьте начало работы.',
                    'reason' => 'no_active_shift',
                    'action_required' => 'clock_in',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'role' => $user->role,
                        'avatar' => $user->avatar,
                    ],
                ], 403);
            }
        }

        $user->update(['last_login_at' => now()]);

        $newToken = $user->createToken($appType ?: 'device');
        $permissionData = $this->getUserPermissionData($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'token' => $newToken->plainTextToken,
                'permissions' => $permissionData['permissions'],
                'limits' => $permissionData['limits'],
                'interface_access' => $permissionData['interface_access'],
            ],
        ]);
    }

    /**
     * Список пользователей на устройстве (для терминалов)
     */
    public function deviceUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_fingerprint' => 'required|string',
            'app_type' => 'required|string|in:pos,kitchen',
        ]);

        $deviceSessionService = app(\App\Services\DeviceSessionService::class);
        $users = $deviceSessionService->getDeviceUsers(
            $validated['device_fingerprint'],
            $validated['app_type']
        );

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Выход с удалением device session (опционально)
     */
    public function logoutDevice(Request $request): JsonResponse
    {
        $deviceToken = $request->input('device_token');
        $apiToken = $request->bearerToken() ?? $request->header('X-Auth-Token');

        // Удаляем Sanctum токен
        if ($apiToken) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($apiToken);
            if ($accessToken) {
                $accessToken->delete();
            }
        }

        // Удаляем device session если указан
        if ($deviceToken) {
            $deviceSessionService = app(\App\Services\DeviceSessionService::class);
            $deviceSessionService->revokeSession($deviceToken);
        }

        return response()->json([
            'success' => true,
            'message' => 'Вы вышли из системы',
        ]);
    }

    /**
     * Получить список активных device sessions текущего пользователя
     */
    public function getDeviceSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не авторизован',
            ], 401);
        }

        $sessions = \App\Models\DeviceSession::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'device_name' => $session->device_name,
                    'device_fingerprint' => substr($session->device_fingerprint, 0, 12) . '...',
                    'app_type' => $session->app_type,
                    'last_activity_at' => $session->last_activity_at,
                    'created_at' => $session->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Отозвать конкретную device session
     */
    public function revokeDeviceSession(Request $request, $sessionId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не авторизован',
            ], 401);
        }

        $session = \App\Models\DeviceSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Сессия не найдена',
            ], 404);
        }

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Сессия отозвана',
        ]);
    }

    /**
     * Отозвать все device sessions кроме текущей
     */
    public function revokeAllDeviceSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не авторизован',
            ], 401);
        }

        // Получаем device_token из запроса (если есть)
        $currentDeviceToken = $request->input('device_token') ?? $request->header('X-Device-Token');

        // Удаляем все сессии пользователя кроме текущей
        $query = \App\Models\DeviceSession::where('user_id', $user->id);

        if ($currentDeviceToken) {
            $query->where('token', '!=', $currentDeviceToken);
        }

        $deletedCount = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "Отозвано сессий: {$deletedCount}",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Проверка: нужна ли первоначальная настройка системы
     */
    public function setupStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'needs_setup' => User::count() === 0,
        ]);
    }

    /**
     * Первоначальная настройка: создание организации, ресторана, владельца
     */
    public function setup(Request $request): JsonResponse
    {
        if (User::count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Система уже настроена',
            ], 403);
        }

        $validated = $request->validate([
            'restaurant_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                // 1. Сидирование ролей и прав
                $this->seedRolesAndPermissions();

                // 2. Создание Tenant
                $tenant = Tenant::create([
                    'name' => $validated['restaurant_name'],
                    'slug' => Str::slug($validated['restaurant_name']) ?: 'restaurant',
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'plan' => 'trial',
                    'trial_ends_at' => now()->addDays(14),
                    'is_active' => true,
                ]);

                // 3. Создание Restaurant
                $restaurant = Restaurant::create([
                    'tenant_id' => $tenant->id,
                    'name' => $validated['restaurant_name'],
                    'slug' => Str::slug($validated['restaurant_name']) ?: 'restaurant',
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => true,
                    'is_main' => true,
                ]);

                // 4. Получаем роль owner
                $ownerRole = Role::where('key', 'owner')->whereNull('restaurant_id')->first();

                // 5. Создание User (owner)
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'restaurant_id' => $restaurant->id,
                    'name' => $validated['owner_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'role' => 'owner',
                    'role_id' => $ownerRole?->id,
                    'is_active' => true,
                    'is_tenant_owner' => true,
                    'last_login_at' => now(),
                ]);

                // 6. Создание Sanctum-токена
                $newToken = $user->createToken('web');

                return [
                    'user' => $user,
                    'token' => $newToken->plainTextToken,
                ];
            });

            $user = $result['user'];
            $permissionData = $this->getUserPermissionData($user);

            return response()->json([
                'success' => true,
                'message' => 'Система настроена! Добро пожаловать!',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'restaurant_id' => $user->restaurant_id,
                    ],
                    'token' => $result['token'],
                    'permissions' => $permissionData['permissions'],
                    'limits' => $permissionData['limits'],
                    'interface_access' => $permissionData['interface_access'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при настройке системы: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Сидирование ролей и прав (логика из RolesAndPermissionsSeeder)
     */
    private function seedRolesAndPermissions(): void
    {
        // Создаём все разрешения
        $groups = Permission::getGroups();

        foreach ($groups as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $name) {
                Permission::firstOrCreate(
                    ['key' => $key, 'restaurant_id' => null],
                    [
                        'name' => $name,
                        'group' => $groupKey,
                        'description' => null,
                        'is_system' => true,
                    ]
                );
            }
        }

        // Специальное разрешение "полный доступ"
        Permission::firstOrCreate(
            ['key' => '*', 'restaurant_id' => null],
            [
                'name' => 'Полный доступ',
                'group' => 'system',
                'description' => 'Доступ ко всем функциям системы',
                'is_system' => true,
            ]
        );

        // Создаём роли
        $defaultRoles = Role::getDefaultRoles();

        foreach ($defaultRoles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::firstOrCreate(
                ['key' => $roleData['key'], 'restaurant_id' => null],
                $roleData
            );

            $permissionIds = Permission::whereIn('key', $permissions)
                ->whereNull('restaurant_id')
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * Проверить доступ пользователя к интерфейсу
     * Возвращает JsonResponse с ошибкой или null если доступ разрешён
     */
    private function checkInterfaceAccess(User $user, string $appType): ?JsonResponse
    {
        // Маппинг app_type на поле Role
        $interfaceMap = [
            'pos' => 'can_access_pos',
            'backoffice' => 'can_access_backoffice',
            'kitchen' => 'can_access_kitchen',
            'delivery' => 'can_access_delivery',
            'waiter' => 'can_access_pos', // Waiter app использует POS доступ
            'courier' => 'can_access_delivery',
        ];

        $interfaceNames = [
            'pos' => 'POS-терминал',
            'backoffice' => 'Бэк-офис',
            'kitchen' => 'Кухонный дисплей',
            'delivery' => 'Приложение доставки',
            'waiter' => 'Приложение официанта',
            'courier' => 'Приложение курьера',
        ];

        $field = $interfaceMap[$appType] ?? null;

        if (!$field) {
            // Неизвестный app_type - пропускаем проверку
            return null;
        }

        $role = $user->getEffectiveRole();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Роль не назначена. Обратитесь к администратору.',
                'reason' => 'no_role_assigned',
            ], 403);
        }

        if (!$role->$field) {
            $interfaceName = $interfaceNames[$appType] ?? $appType;

            return response()->json([
                'success' => false,
                'message' => "У вас нет доступа к интерфейсу: {$interfaceName}",
                'reason' => 'interface_access_denied',
                'denied_interface' => $appType,
                'user_role' => $role->name ?? $role->key,
            ], 403);
        }

        return null; // Доступ разрешён
    }

    /**
     * Собрать данные о правах доступа пользователя
     */
    private function getUserPermissionData(User $user): array
    {
        $role = $user->getEffectiveRole();

        if (!$role) {
            return [
                'permissions' => [],
                'limits' => [
                    'max_discount_percent' => 0,
                    'max_refund_amount' => 0,
                    'max_cancel_amount' => 0,
                ],
                'interface_access' => [
                    'can_access_pos' => false,
                    'can_access_backoffice' => false,
                    'can_access_kitchen' => false,
                    'can_access_delivery' => false,
                ],
            ];
        }

        // Собираем все строковые ключи permissions из связи
        $permissions = $role->permissions()->pluck('key')->toArray();

        return [
            'permissions' => $permissions,
            'limits' => [
                'max_discount_percent' => (int) ($role->max_discount_percent ?? 0),
                'max_refund_amount' => (float) ($role->max_refund_amount ?? 0),
                'max_cancel_amount' => (float) ($role->max_cancel_amount ?? 0),
            ],
            'interface_access' => [
                'can_access_pos' => (bool) ($role->can_access_pos ?? false),
                'can_access_backoffice' => (bool) ($role->can_access_backoffice ?? false),
                'can_access_kitchen' => (bool) ($role->can_access_kitchen ?? false),
                'can_access_delivery' => (bool) ($role->can_access_delivery ?? false),
            ],
        ];
    }
}
