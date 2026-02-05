<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Проверка прав доступа пользователя.
     *
     * Использование в маршрутах:
     *   ->middleware('permission:orders.create')          — одно право
     *   ->middleware('permission:orders.create|orders.edit') — любое из (OR)
     *
     * Super admin и tenant owner проходят без проверки.
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        // Если пользователь не авторизован — 401
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Необходима авторизация',
            ], 401);
        }

        // Super admin, tenant owner и owner — bypass (полный доступ)
        if ($user->isSuperAdmin() || $user->isTenantOwner() || $user->role === 'owner') {
            return $next($request);
        }

        // Разбиваем pipe-separated permissions (OR логика)
        $permissionList = explode('|', $permissions);

        foreach ($permissionList as $permission) {
            if ($user->hasPermission(trim($permission))) {
                return $next($request);
            }
        }

        // Ни одно из прав не найдено
        return response()->json([
            'success' => false,
            'message' => 'Недостаточно прав для выполнения этого действия',
            'required_permissions' => $permissionList,
        ], 403);
    }
}
