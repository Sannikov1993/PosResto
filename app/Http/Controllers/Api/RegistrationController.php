<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Проверка токена приглашения
     */
    public function validateToken(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен не указан',
            ], 400);
        }

        $invitation = StaffInvitation::where('token', $token)
            ->where('status', StaffInvitation::STATUS_PENDING)
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Приглашение не найдено или уже использовано',
            ], 404);
        }

        if ($invitation->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия приглашения истек',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $invitation->name,
                'role' => $invitation->role,
                'role_label' => $invitation->role_label,
                'email' => $invitation->email,
                'phone' => $invitation->phone,
            ],
        ]);
    }

    /**
     * Регистрация сотрудника
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'avatar' => 'nullable|string',
        ]);

        // Проверяем приглашение
        $invitation = StaffInvitation::where('token', $validated['token'])
            ->where('status', StaffInvitation::STATUS_PENDING)
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Приглашение не найдено',
            ], 404);
        }

        if ($invitation->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия приглашения истек',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Login = email (полный адрес)
            $login = $validated['email'];

            // Автоматически найти role_id если не указан в приглашении
            $roleId = $invitation->role_id;
            $roleKey = $invitation->role;

            \Log::info('Registration: Initial role_id from invitation', [
                'invitation_role_id' => $invitation->role_id,
                'invitation_role' => $invitation->role,
                'restaurant_id' => $invitation->restaurant_id,
            ]);

            if (!$roleId && $roleKey) {
                // Сначала ищем роль ресторана по точному ключу
                $role = \App\Models\Role::where('key', $roleKey)
                    ->where('restaurant_id', $invitation->restaurant_id)
                    ->first();

                // Если не нашли - ищем по паттерну (cashier, cashier_2, cashier_3...)
                if (!$role) {
                    $role = \App\Models\Role::where('restaurant_id', $invitation->restaurant_id)
                        ->where(function($q) use ($roleKey) {
                            $q->where('key', $roleKey)
                              ->orWhere('key', 'like', $roleKey . '_%');
                        })
                        ->first();
                }

                // Если не нашли - ищем системную роль
                if (!$role) {
                    $role = \App\Models\Role::where('key', $roleKey)
                        ->whereNull('restaurant_id')
                        ->first();
                }

                $roleId = $role?->id;
                // Также обновляем roleKey на актуальный ключ из найденной роли
                if ($role) {
                    $roleKey = $role->key;
                }

                \Log::info('Registration: Found role', [
                    'original_key' => $invitation->role,
                    'resolved_key' => $roleKey,
                    'found_role' => $role ? ['id' => $role->id, 'key' => $role->key, 'name' => $role->name] : null,
                    'resolved_role_id' => $roleId,
                ]);
            }

            // Создаем пользователя
            \Log::info('Registration: Creating user with data', [
                'role' => $roleKey,
                'role_id' => $roleId,
            ]);

            $user = User::create([
                'restaurant_id' => $invitation->restaurant_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $invitation->phone,
                'login' => $login,
                'password' => Hash::make($validated['password']),
                'role' => $roleKey,
                'role_id' => $roleId,
                'avatar' => $validated['avatar'] ?? null,
                'is_active' => true,
                'invitation_id' => $invitation->id,
                'salary_type' => $invitation->salary_type,
                'salary' => $invitation->salary_amount,
                'hourly_rate' => $invitation->hourly_rate,
                'percent_rate' => $invitation->percent_rate,
            ]);

            \Log::info('Registration: User created', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'user_role_id' => $user->role_id,
            ]);

            // Обновляем статус приглашения
            $invitation->update([
                'status' => StaffInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);

            // Автоматический логин
            $user->update(['last_login_at' => now()]);
            $newToken = $user->createToken('cabinet');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Регистрация успешна! Добро пожаловать в команду!',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'login' => $user->login,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'has_password' => true,
                        'has_pin' => false,
                    ],
                    'token' => $newToken->plainTextToken,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Registration error: ' . $e->getMessage(), [
                'token' => $validated['token'],
                'email' => $validated['email'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка регистрации. Попробуйте позже.',
            ], 500);
        }
    }
}
