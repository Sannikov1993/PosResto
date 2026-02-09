<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Публичные endpoints (без авторизации)
        $publicEndpoints = [
            'api/register/validate-token',
            'api/register',
            'api/auth/setup-status',
            'api/auth/setup',
            'api/auth/login',
            'api/auth/login-pin',
            'api/auth/login-device',
            'api/auth/device-login',
            'api/auth/forgot-password',
            'api/auth/reset-password',
        ];

        $path = $request->path();
        foreach ($publicEndpoints as $endpoint) {
            if (str_starts_with($path, $endpoint)) {
                return $next($request);
            }
        }

        // Проверяем активность пользователя (SetRestaurant уже резолвил auth)
        $user = auth()->user();

        if ($user && !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Ваш доступ заблокирован. Обратитесь к администратору.',
                'reason' => 'user_deactivated',
            ], 403);
        }

        return $next($request);
    }
}
