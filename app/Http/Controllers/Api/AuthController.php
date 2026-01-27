<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        // Быстрый поиск по pin_lookup (plaintext для скорости)
        $user = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('pin_lookup', $validated['pin'])
            ->first();

        // Fallback на старый метод с bcrypt (для совместимости)
        if (!$user) {
            $users = User::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->whereNotNull('pin_code')
                ->whereNull('pin_lookup')
                ->get();

            foreach ($users as $u) {
                if (Hash::check($validated['pin'], $u->pin_code)) {
                    // Мигрируем на быстрый поиск
                    $u->pin_lookup = $validated['pin'];
                    $u->save();
                    $user = $u;
                    break;
                }
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ], 401);
        }

        // Генерируем простой токен (для MVP)
        $token = bin2hex(random_bytes(32));

        // Сохраняем токен в api_token (отдельное поле, не remember_token!)
        $user->api_token = $token;
        $user->last_login_at = now();
        $user->save();

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
     * Вход по email/телефону и паролю
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string', // email или телефон
            'email' => 'nullable|string', // для обратной совместимости
            'password' => 'required|string',
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

        $token = bin2hex(random_bytes(32));

        $user->api_token = $token;
        $user->last_login_at = now();
        $user->save();

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

        $user = User::where('api_token', $token)
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
            User::where('api_token', $token)->update(['api_token' => null]);
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
        $user = User::where('api_token', $token)->first();

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

        $user->update(['pin_code' => Hash::make($validated['new_pin'])]);

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
}
