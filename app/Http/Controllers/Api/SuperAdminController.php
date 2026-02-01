<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Order;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Проверка доступа супер-админа
     */
    protected function checkSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== User::ROLE_SUPER_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён. Требуются права супер-администратора.',
            ], 403);
        }
        return null;
    }

    /**
     * Дашборд супер-админа
     * GET /api/super-admin/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $trialTenants = Tenant::where('plan', 'trial')->count();
        $paidTenants = Tenant::whereIn('plan', ['start', 'business', 'premium'])->count();

        $totalRestaurants = Restaurant::count();
        $totalUsers = User::count();

        // Новые тенанты за последние 30 дней
        $newTenantsThisMonth = Tenant::where('created_at', '>=', now()->subDays(30))->count();

        // Тенанты с истекающей подпиской (в течение 7 дней)
        $expiringTenants = Tenant::where(function ($q) {
            $q->where('plan', 'trial')
              ->where('trial_ends_at', '<=', now()->addDays(7))
              ->where('trial_ends_at', '>', now());
        })->orWhere(function ($q) {
            $q->whereIn('plan', ['start', 'business', 'premium'])
              ->where('subscription_ends_at', '<=', now()->addDays(7))
              ->where('subscription_ends_at', '>', now());
        })->count();

        // Распределение по тарифам
        $planDistribution = Tenant::select('plan', DB::raw('count(*) as count'))
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'trial_tenants' => $trialTenants,
                'paid_tenants' => $paidTenants,
                'total_restaurants' => $totalRestaurants,
                'total_users' => $totalUsers,
                'new_tenants_this_month' => $newTenantsThisMonth,
                'expiring_tenants' => $expiringTenants,
                'plan_distribution' => $planDistribution,
            ],
        ]);
    }

    /**
     * Список всех тенантов
     * GET /api/super-admin/tenants
     */
    public function tenants(Request $request): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $query = Tenant::query()
            ->withCount(['restaurants', 'users']);

        // Поиск
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Фильтр по плану
        if ($plan = $request->input('plan')) {
            $query->where('plan', $plan);
        }

        // Фильтр по статусу
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $tenants = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $tenants->items(),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    /**
     * Детали тенанта
     * GET /api/super-admin/tenants/{id}
     */
    public function tenantDetails(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::withCount(['restaurants', 'users'])
            ->with(['restaurants', 'users' => function ($q) {
                $q->select('id', 'tenant_id', 'restaurant_id', 'name', 'email', 'role', 'is_active', 'is_tenant_owner', 'last_active_at');
            }])
            ->find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Статистика заказов
        $restaurantIds = $tenant->restaurants->pluck('id');
        $ordersCount = Order::whereIn('restaurant_id', $restaurantIds)->count();
        $ordersThisMonth = Order::whereIn('restaurant_id', $restaurantIds)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $revenue = Order::whereIn('restaurant_id', $restaurantIds)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('total');

        $plans = $this->tenantService->getPlansConfig();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'plan_info' => $plans[$tenant->plan] ?? null,
                'stats' => [
                    'total_orders' => $ordersCount,
                    'orders_this_month' => $ordersThisMonth,
                    'total_revenue' => $revenue,
                ],
            ],
        ]);
    }

    /**
     * Обновить тенанта
     * PUT /api/super-admin/tenants/{id}
     */
    public function updateTenant(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:30',
            'plan' => 'sometimes|string|in:trial,start,business,premium',
            'is_active' => 'sometimes|boolean',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
        ]);

        $tenant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Тенант обновлён',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Заблокировать тенанта
     * POST /api/super-admin/tenants/{id}/block
     */
    public function blockTenant(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $reason = $request->input('reason', 'Заблокирован администратором');
        $tenant->block($reason);

        return response()->json([
            'success' => true,
            'message' => 'Тенант заблокирован',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Разблокировать тенанта
     * POST /api/super-admin/tenants/{id}/unblock
     */
    public function unblockTenant(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $tenant->unblock();

        return response()->json([
            'success' => true,
            'message' => 'Тенант разблокирован',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Продлить подписку тенанта
     * POST /api/super-admin/tenants/{id}/extend
     */
    public function extendTenantSubscription(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        if ($tenant->plan === 'trial') {
            $currentEnd = $tenant->trial_ends_at ?? now();
            $tenant->update([
                'trial_ends_at' => $currentEnd->addDays($validated['days']),
            ]);
        } else {
            $currentEnd = $tenant->subscription_ends_at ?? now();
            $tenant->update([
                'subscription_ends_at' => $currentEnd->addDays($validated['days']),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Подписка продлена на {$validated['days']} дней",
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Изменить тариф тенанта
     * POST /api/super-admin/tenants/{id}/change-plan
     */
    public function changeTenantPlan(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        $validated = $request->validate([
            'plan' => 'required|string|in:trial,start,business,premium',
            'days' => 'required|integer|min:1|max:365',
        ]);

        $newEndDate = now()->addDays($validated['days']);

        if ($validated['plan'] === 'trial') {
            $tenant->update([
                'plan' => $validated['plan'],
                'trial_ends_at' => $newEndDate,
                'subscription_ends_at' => null,
            ]);
        } else {
            $tenant->update([
                'plan' => $validated['plan'],
                'trial_ends_at' => null,
                'subscription_ends_at' => $newEndDate,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Тариф изменён',
            'data' => $tenant->fresh(),
        ]);
    }

    /**
     * Войти как тенант (impersonate)
     * POST /api/super-admin/tenants/{id}/impersonate
     */
    public function impersonate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Находим владельца тенанта
        $owner = User::where('tenant_id', $tenant->id)
            ->where('is_tenant_owner', true)
            ->first();

        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Владелец тенанта не найден',
            ], 404);
        }

        // Создаём токен для владельца
        $token = $owner->createToken('impersonate')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Токен создан для входа под тенантом',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ],
            ],
        ]);
    }

    /**
     * Удалить тенанта (soft delete)
     * DELETE /api/super-admin/tenants/{id}
     */
    public function deleteTenant(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkSuperAdmin($request)) return $error;

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Тенант не найден',
            ], 404);
        }

        // Блокируем и удаляем (soft delete)
        $tenant->block('Удалён администратором');
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Тенант удалён',
        ]);
    }
}
