<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\TenantManager;
use App\Models\KitchenDevice;
use App\Exceptions\TenantNotSetException;

/**
 * Middleware для установки текущего ресторана (restaurant_id)
 *
 * Приоритет определения:
 * 1. Route model binding (Table, Order, Zone, etc.) — самый надёжный
 * 2. Авторизованный пользователь — его restaurant_id
 * 3. Kitchen device (для планшетов без user auth)
 *
 * ВАЖНО: Явные параметры (query, header) разрешены ТОЛЬКО для superadmin
 */
class SetRestaurant
{
    /**
     * Модели, из которых можно извлечь restaurant_id через route binding
     */
    private const ROUTE_MODELS = [
        'table',
        'order',
        'zone',
        'kitchenStation',
        'reservation',
        'category',
        'dish',
        'customer',
        'cashShift',
        'shift',  // Alias для CashShift в routes
        'promotion',
    ];

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $restaurantId = $this->resolveRestaurantId($request);

        if ($restaurantId) {
            // Проверяем авторизацию доступа к этому ресторану
            $this->authorizeAccess($request, $restaurantId);
            $this->tenantManager->set($restaurantId);
        } else {
            // Требуем restaurant_id для авторизованных пользователей (кроме superadmin)
            $this->requireRestaurantIdIfAuthenticated($request);
        }

        return $next($request);
    }

    /**
     * Требует restaurant_id для авторизованных пользователей
     *
     * Если пользователь авторизован и не является superadmin,
     * он ДОЛЖЕН иметь restaurant_id (либо свой, либо из запроса)
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireRestaurantIdIfAuthenticated(Request $request): void
    {
        $user = auth()->user();

        // Нет пользователя — публичный endpoint, пропускаем
        if (!$user) {
            return;
        }

        // Superadmin может работать без контекста ресторана
        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Авторизованный пользователь без restaurant_id — это ошибка конфигурации
        // или попытка обойти изоляцию данных
        abort(403, 'Access denied: restaurant context is required');
    }

    /**
     * Определить restaurant_id из запроса
     */
    protected function resolveRestaurantId(Request $request): ?int
    {
        // 1. Из route model binding — самый надёжный источник
        $fromRoute = $this->resolveFromRouteModels($request);
        if ($fromRoute !== null) {
            return $fromRoute;
        }

        // 2. Из авторизованного пользователя
        $user = $this->getAuthenticatedUser($request);
        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        // 3. Из устройства кухни (для планшетов без user auth)
        $fromDevice = $this->resolveFromKitchenDevice($request);
        if ($fromDevice !== null) {
            return $fromDevice;
        }

        // 4. Явные параметры — ТОЛЬКО для superadmin
        if ($this->isSuperAdmin($user)) {
            return $this->resolveFromExplicitParams($request);
        }

        return null;
    }

    /**
     * Извлечь restaurant_id из route model binding
     */
    protected function resolveFromRouteModels(Request $request): ?int
    {
        foreach (self::ROUTE_MODELS as $modelName) {
            $model = $request->route($modelName);
            if (is_object($model) && isset($model->restaurant_id)) {
                return $model->restaurant_id;
            }
        }
        return null;
    }

    /**
     * Получить авторизованного пользователя (включая api_token и Sanctum token)
     */
    protected function getAuthenticatedUser(Request $request): ?object
    {
        $user = auth()->user();
        if ($user) {
            return $user;
        }

        // Пробуем Bearer token (для API запросов до auth middleware)
        $token = $request->bearerToken() ?: $request->input('token');
        if (!$token) {
            return null;
        }

        // 1) Проверяем по plain api_token (аналогично AuthenticateApiToken)
        $tokenUser = \App\Models\User::where('api_token', $token)
            ->where('is_active', true)
            ->first();

        // 2) Fallback: Sanctum PersonalAccessToken
        if (!$tokenUser) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $tokenUser = $accessToken->tokenable;
                if ($tokenUser && !$tokenUser->is_active) {
                    $tokenUser = null;
                }
            }
        }

        if ($tokenUser) {
            auth()->setUser($tokenUser);
            return $tokenUser;
        }

        return null;
    }

    /**
     * Извлечь restaurant_id из kitchen device
     *
     * ВАЖНО: Используем withoutGlobalScopes() потому что мы ОПРЕДЕЛЯЕМ
     * restaurant_id, а не работаем в его контексте
     */
    protected function resolveFromKitchenDevice(Request $request): ?int
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        if (!$deviceId) {
            return null;
        }

        // Кешируем на уровне запроса
        static $deviceCache = [];
        if (!isset($deviceCache[$deviceId])) {
            // Обходим Global Scope т.к. мы определяем контекст ресторана
            $device = KitchenDevice::withoutGlobalScopes()
                ->where('device_id', $deviceId)
                ->where('status', KitchenDevice::STATUS_ACTIVE)
                ->first();
            $deviceCache[$deviceId] = $device;
        }

        $device = $deviceCache[$deviceId];
        return $device?->restaurant_id;
    }

    /**
     * Извлечь restaurant_id из явных параметров (только для superadmin)
     */
    protected function resolveFromExplicitParams(Request $request): ?int
    {
        if ($request->has('restaurant_id')) {
            return (int) $request->input('restaurant_id');
        }

        if ($request->hasHeader('X-Restaurant-ID')) {
            return (int) $request->header('X-Restaurant-ID');
        }

        return null;
    }

    /**
     * Проверить, является ли пользователь superadmin
     */
    protected function isSuperAdmin(?object $user): bool
    {
        if (!$user) {
            return false;
        }

        // Superadmin может работать с любым рестораном
        // Используем метод модели, а не несуществующее свойство
        if (method_exists($user, 'isSuperAdmin')) {
            return $user->isSuperAdmin();
        }

        return ($user->role ?? null) === 'super_admin';
    }

    /**
     * Проверить авторизацию доступа к ресторану
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeAccess(Request $request, int $restaurantId): void
    {
        $user = auth()->user();

        // Нет пользователя — доступ разрешён (публичные endpoints, kitchen devices)
        if (!$user) {
            return;
        }

        // Superadmin имеет доступ ко всем ресторанам
        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Tenant owner имеет доступ ко всем ресторанам своего tenant
        if ($user->is_tenant_owner ?? false) {
            // Обходим Global Scope т.к. проверяем доступ на уровне middleware
            $restaurant = \App\Models\Restaurant::withoutGlobalScopes()->find($restaurantId);
            if ($restaurant && $restaurant->tenant_id === $user->tenant_id) {
                return;
            }
        }

        // Обычный пользователь — только свой ресторан
        if ($user->restaurant_id !== $restaurantId) {
            abort(403, 'Access denied: you do not have permission to access this restaurant');
        }
    }
}
