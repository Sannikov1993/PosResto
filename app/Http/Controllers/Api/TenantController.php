<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Регистрация нового тенанта (публичный endpoint для SaaS)
     * POST /api/register/tenant
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
            'restaurant_name' => 'nullable|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:tenants,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            $result = $this->tenantService->createTenant($validated);

            $user = $result['user'];
            $token = $user->createToken('web');

            return response()->json([
                'success' => true,
                'message' => 'Регистрация успешна! Добро пожаловать в MenuLab!',
                'data' => [
                    'tenant' => [
                        'id' => $result['tenant']->id,
                        'name' => $result['tenant']->name,
                        'plan' => $result['tenant']->plan,
                        'trial_ends_at' => $result['tenant']->trial_ends_at,
                    ],
                    'restaurant' => [
                        'id' => $result['restaurant']->id,
                        'name' => $result['restaurant']->name,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'restaurant_id' => $user->restaurant_id,
                    ],
                    'token' => $token->plainTextToken,
                    // Owner имеет полные права
                    'permissions' => ['*'],
                    'limits' => [
                        'max_discount_percent' => 100,
                        'max_refund_amount' => 999999,
                        'max_cancel_amount' => 999999,
                    ],
                    'interface_access' => [
                        'can_access_pos' => true,
                        'can_access_backoffice' => true,
                        'can_access_kitchen' => true,
                        'can_access_delivery' => true,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка регистрации: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Информация о текущем тенанте
     * GET /api/tenant
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'plan' => $tenant->plan,
                'plan_name' => Tenant::PLANS[$tenant->plan] ?? $tenant->plan,
                'is_on_trial' => $tenant->isOnTrial(),
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_ends_at' => $tenant->subscription_ends_at,
                'days_until_expiration' => $tenant->daysUntilExpiration(),
                'is_active' => $tenant->is_active,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'restaurants_count' => $tenant->restaurants()->count(),
            ],
        ]);
    }

    /**
     * Обновить данные тенанта
     * PUT /api/tenant
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Только владелец может редактировать тенанта
        if (!$user->is_tenant_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('tenants')->ignore($tenant->id)],
            'phone' => 'nullable|string|max:30',
            'inn' => 'nullable|string|max:20',
            'legal_name' => 'nullable|string|max:255',
            'legal_address' => 'nullable|string|max:500',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
        ]);

        $tenant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Данные организации обновлены',
            'data' => $tenant->fresh(),
        ]);
    }

    // =====================================================
    // РЕСТОРАНЫ ТЕНАНТА
    // =====================================================

    /**
     * Список ресторанов текущего тенанта
     * GET /api/tenant/restaurants
     */
    public function restaurants(Request $request): JsonResponse
    {
        $user = $request->user();

        $restaurants = Restaurant::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'address' => $r->address,
                'phone' => $r->phone,
                'is_active' => $r->is_active,
                'is_main' => $r->is_main,
                'is_current' => $r->id === $user->restaurant_id,
            ]);

        return response()->json([
            'success' => true,
            'data' => $restaurants,
        ]);
    }

    /**
     * Создать новый ресторан
     * POST /api/tenant/restaurants
     */
    public function createRestaurant(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Только владелец может создавать рестораны
        if (!$user->is_tenant_owner && $user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав для создания точки',
            ], 403);
        }

        // Проверка лимита тарифа
        if (!$this->tenantService->canAddRestaurant($tenant)) {
            $limits = $this->tenantService->getPlanLimits($tenant->plan);
            return response()->json([
                'success' => false,
                'message' => "Достигнут лимит точек для вашего тарифа ({$limits['max_restaurants']}). Перейдите на более высокий тариф.",
                'upgrade_required' => true,
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ]);

        $restaurant = $this->tenantService->addRestaurant($tenant, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Точка создана',
            'data' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'address' => $restaurant->address,
                'phone' => $restaurant->phone,
                'is_active' => $restaurant->is_active,
                'is_main' => $restaurant->is_main,
            ],
        ], 201);
    }

    /**
     * Обновить ресторан
     * PUT /api/tenant/restaurants/{id}
     */
    public function updateRestaurant(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $restaurant = Restaurant::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Точка не найдена',
            ], 404);
        }

        // Только владелец может редактировать
        if (!$user->is_tenant_owner && $user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $restaurant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Данные точки обновлены',
            'data' => $restaurant->fresh(),
        ]);
    }

    /**
     * Удалить ресторан (soft delete)
     * DELETE /api/tenant/restaurants/{id}
     */
    public function deleteRestaurant(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $restaurant = Restaurant::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Точка не найдена',
            ], 404);
        }

        // Нельзя удалить главный ресторан
        if ($restaurant->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить главную точку',
            ], 403);
        }

        // Только владелец может удалять
        if (!$user->is_tenant_owner && $user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав',
            ], 403);
        }

        // Проверяем, есть ли активные заказы
        $activeOrdersCount = $restaurant->orders()
            ->whereIn('status', ['new', 'preparing', 'ready'])
            ->count();

        if ($activeOrdersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Нельзя удалить точку с активными заказами ({$activeOrdersCount})",
            ], 403);
        }

        $restaurant->update(['is_active' => false]);
        $restaurant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Точка удалена',
        ]);
    }

    /**
     * Сделать ресторан главным
     * POST /api/tenant/restaurants/{id}/make-main
     */
    public function makeMain(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $restaurant = Restaurant::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Точка не найдена',
            ], 404);
        }

        if (!$user->is_tenant_owner && $user->role !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав',
            ], 403);
        }

        // Убираем флаг is_main у всех ресторанов тенанта
        Restaurant::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->update(['is_main' => false]);

        // Устанавливаем новый главный
        $restaurant->update(['is_main' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Главная точка изменена',
        ]);
    }

    /**
     * Переключить текущий ресторан пользователя
     * POST /api/tenant/restaurants/{id}/switch
     */
    public function switchRestaurant(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $restaurant = Restaurant::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Точка не найдена или неактивна',
            ], 404);
        }

        // Обновляем restaurant_id пользователя
        $user->update(['restaurant_id' => $restaurant->id]);

        // Устанавливаем текущий ресторан в сервисе
        $this->tenantService->setCurrentRestaurant($restaurant);

        return response()->json([
            'success' => true,
            'message' => "Вы переключились на точку: {$restaurant->name}",
            'data' => [
                'restaurant_id' => $restaurant->id,
                'restaurant_name' => $restaurant->name,
            ],
        ]);
    }

    /**
     * Лимиты текущего тарифа
     * GET /api/tenant/limits
     */
    public function limits(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $limits = $this->tenantService->getPlanLimits($tenant->plan);

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $tenant->plan,
                'plan_name' => Tenant::PLANS[$tenant->plan] ?? $tenant->plan,
                'limits' => $limits,
                'current' => [
                    'restaurants' => $tenant->restaurants()->count(),
                    'users' => $tenant->users()->count(),
                ],
                'can_add_restaurant' => $this->tenantService->canAddRestaurant($tenant),
                'can_add_user' => $this->tenantService->canAddUser($tenant),
            ],
        ]);
    }

    // =====================================================
    // ТАРИФЫ И ПОДПИСКА
    // =====================================================

    /**
     * Список доступных тарифов
     * GET /api/tenant/plans
     */
    public function plans(): JsonResponse
    {
        $plans = $this->tenantService->getAvailablePlans();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Текущий статус подписки
     * GET /api/tenant/subscription
     */
    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $plans = $this->tenantService->getPlansConfig();
        $currentPlan = $plans[$tenant->plan] ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'plan' => $tenant->plan,
                'plan_info' => $currentPlan,
                'is_on_trial' => $tenant->isOnTrial(),
                'has_active_subscription' => $tenant->hasActiveSubscription(),
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_ends_at' => $tenant->subscription_ends_at,
                'days_remaining' => $tenant->daysUntilExpiration(),
                'is_active' => $tenant->is_active,
                'current_usage' => [
                    'restaurants' => $tenant->restaurants()->count(),
                    'users' => $tenant->users()->count(),
                ],
            ],
        ]);
    }

    /**
     * Сменить тарифный план
     * POST /api/tenant/subscription/change
     */
    public function changePlan(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Только владелец может менять тариф
        if (!$user->is_tenant_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Только владелец может менять тариф',
            ], 403);
        }

        $validated = $request->validate([
            'plan' => 'required|string|in:start,business,premium',
            'period' => 'required|string|in:monthly,yearly',
        ]);

        try {
            $result = $this->tenantService->changePlan(
                $tenant,
                $validated['plan'],
                $validated['period']
            );

            return response()->json([
                'success' => true,
                'message' => 'Тариф успешно изменён на ' . $result['plan']['name'],
                'data' => [
                    'plan' => $result['plan'],
                    'price' => $result['price'],
                    'period' => $result['period'],
                    'expires_at' => $result['expires_at'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Продлить текущую подписку
     * POST /api/tenant/subscription/extend
     */
    public function extendSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        if (!$user->is_tenant_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Только владелец может продлить подписку',
            ], 403);
        }

        $validated = $request->validate([
            'period' => 'required|string|in:monthly,yearly',
        ]);

        try {
            $result = $this->tenantService->extendSubscription(
                $tenant,
                $validated['period']
            );

            return response()->json([
                'success' => true,
                'message' => 'Подписка продлена до ' . $result['expires_at']->format('d.m.Y'),
                'data' => [
                    'plan' => $result['plan'],
                    'price' => $result['price'],
                    'period' => $result['period'],
                    'expires_at' => $result['expires_at'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
