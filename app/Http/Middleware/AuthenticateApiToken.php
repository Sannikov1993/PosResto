<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     * Аутентификация по api_token из заголовка Authorization: Bearer {token}
     * или из query parameter ?token= (для SSE, который не поддерживает заголовки)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Сначала проверяем заголовок, потом query parameter (для SSE)
        $token = $request->bearerToken() ?: $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Требуется авторизация',
            ], 401);
        }

        // 1) Проверяем по api_token (кабинет сотрудника, legacy)
        $user = User::where('api_token', $token)
            ->where('is_active', true)
            ->first();

        // 2) Fallback: Sanctum PersonalAccessToken (POS, backoffice)
        if (!$user) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                if ($user && !$user->is_active) {
                    $user = null;
                }
                // Обновляем last_used_at
                if ($user) {
                    $accessToken->forceFill(['last_used_at' => now()])->save();
                }
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный токен',
            ], 401);
        }

        // Устанавливаем пользователя для Auth::user()
        Auth::setUser($user);

        // Устанавливаем контекст тенанта для BelongsToTenant scope
        if ($user->tenant_id) {
            try {
                $tenantService = app(\App\Services\TenantService::class);
                if (!$tenantService->hasTenant()) {
                    $tenant = \App\Models\Tenant::find($user->tenant_id);
                    if ($tenant) {
                        $tenantService->setCurrentTenant($tenant);
                    }
                }
            } catch (\Throwable $e) {
                // TenantService может быть не настроен — пропускаем
            }
        }

        return $next($request);
    }
}
