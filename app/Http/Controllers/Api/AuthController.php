<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Вход по PIN-коду
     */
    public function loginByPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
            'restaurant_id' => 'nullable|integer',
        ]);

        $restaurantId = $validated['restaurant_id'] ?? 1;

        // Ищем пользователя по PIN
        $user = User::where('restaurant_id', $restaurantId)
            ->where('pin_code', $validated['pin'])
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ], 401);
        }

        // Генерируем простой токен (для MVP)
        $token = bin2hex(random_bytes(32));
        
        // Сохраняем токен в remember_token
        $user->update([
            'remember_token' => $token,
            'last_login_at' => now(),
        ]);

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
                'token' => $token,
            ],
        ]);
    }

    /**
     * Вход по email и паролю
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный email или пароль',
            ], 401);
        }

        $token = bin2hex(random_bytes(32));
        
        $user->update([
            'remember_token' => $token,
            'last_login_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Добро пожаловать!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'restaurant_id' => $user->restaurant_id,
                ],
                'token' => $token,
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

        $user = User::where('remember_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный токен',
            ], 401);
        }

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
            User::where('remember_token', $token)->update(['remember_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Вы вышли из системы',
        ]);
    }

    /**
     * Получить список пользователей для выбора (только имена и аватары)
     */
    public function users(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->select('id', 'name', 'role', 'avatar')
            ->orderBy('name')
            ->get();

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

        $token = $request->bearerToken() ?? $request->header('X-Auth-Token');
        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Не авторизован',
            ], 401);
        }

        if ($user->pin_code !== $validated['current_pin']) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный текущий PIN',
            ], 400);
        }

        $user->update(['pin_code' => $validated['new_pin']]);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код изменён',
        ]);
    }
}
