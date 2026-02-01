<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для проверки доступа к интерфейсам (POS, Backoffice, Kitchen, Delivery)
 *
 * Использование в routes:
 *   ->middleware('interface:pos')           // требует can_access_pos
 *   ->middleware('interface:backoffice')    // требует can_access_backoffice
 *   ->middleware('interface:kitchen')       // требует can_access_kitchen
 *   ->middleware('interface:delivery')      // требует can_access_delivery
 *   ->middleware('interface:pos|backoffice') // требует любой из
 */
class CheckInterfaceAccess
{
    /**
     * Маппинг интерфейсов на поля Role
     */
    private const INTERFACE_MAP = [
        'pos' => 'can_access_pos',
        'backoffice' => 'can_access_backoffice',
        'kitchen' => 'can_access_kitchen',
        'delivery' => 'can_access_delivery',
    ];

    /**
     * Читаемые названия интерфейсов
     */
    private const INTERFACE_NAMES = [
        'pos' => 'POS-терминал',
        'backoffice' => 'Бэк-офис',
        'kitchen' => 'Кухонный дисплей',
        'delivery' => 'Приложение курьера',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $interfaces  Список интерфейсов через | (OR логика)
     */
    public function handle(Request $request, Closure $next, string $interfaces): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Требуется авторизация',
                'error_code' => 'auth_required',
            ], 401);
        }

        // Superadmin и tenant owner имеют полный доступ
        if ($user->isSuperAdmin() || $user->isTenantOwner()) {
            return $next($request);
        }

        // Получаем эффективную роль
        $role = $user->getEffectiveRole();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Роль не назначена. Обратитесь к администратору.',
                'error_code' => 'no_role_assigned',
            ], 403);
        }

        // Проверяем доступ к любому из указанных интерфейсов (OR логика)
        $requiredInterfaces = explode('|', $interfaces);
        $hasAccess = false;
        $deniedInterfaces = [];

        foreach ($requiredInterfaces as $interface) {
            $interface = trim($interface);
            $field = self::INTERFACE_MAP[$interface] ?? null;

            if (!$field) {
                \Log::warning("CheckInterfaceAccess: Unknown interface '{$interface}'");
                continue;
            }

            if ($role->$field) {
                $hasAccess = true;
                break;
            }

            $deniedInterfaces[] = self::INTERFACE_NAMES[$interface] ?? $interface;
        }

        if (!$hasAccess) {
            $interfaceList = implode(', ', $deniedInterfaces);

            return response()->json([
                'success' => false,
                'message' => "У вас нет доступа к: {$interfaceList}",
                'error_code' => 'interface_access_denied',
                'denied_interfaces' => $deniedInterfaces,
                'user_role' => $role->name ?? $role->key,
            ], 403);
        }

        return $next($request);
    }
}
