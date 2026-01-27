<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Текущий тенант (хранится в памяти на время запроса)
     */
    protected static ?Tenant $currentTenant = null;

    /**
     * Текущий ресторан
     */
    protected static ?Restaurant $currentRestaurant = null;

    /**
     * Установить текущий тенант
     */
    public function setCurrentTenant(?Tenant $tenant): void
    {
        static::$currentTenant = $tenant;
    }

    /**
     * Получить текущий тенант
     */
    public function getCurrentTenant(): ?Tenant
    {
        return static::$currentTenant;
    }

    /**
     * Установить текущий ресторан
     */
    public function setCurrentRestaurant(?Restaurant $restaurant): void
    {
        static::$currentRestaurant = $restaurant;
    }

    /**
     * Получить текущий ресторан
     */
    public function getCurrentRestaurant(): ?Restaurant
    {
        return static::$currentRestaurant;
    }

    /**
     * Проверить, установлен ли тенант
     */
    public function hasTenant(): bool
    {
        return static::$currentTenant !== null;
    }

    /**
     * Получить ID текущего тенанта
     */
    public function getCurrentTenantId(): ?int
    {
        return static::$currentTenant?->id;
    }

    /**
     * Создать нового тенанта с первым рестораном и владельцем
     */
    public function createTenant(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Создаём тенанта
            $tenant = Tenant::create([
                'name' => $data['organization_name'] ?? $data['name'],
                'slug' => Tenant::generateSlug($data['organization_name'] ?? $data['name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'plan' => Tenant::PLAN_TRIAL,
                'trial_ends_at' => now()->addDays(14), // 14 дней триала
                'is_active' => true,
            ]);

            // 2. Создаём первый ресторан
            $restaurant = Restaurant::create([
                'tenant_id' => $tenant->id,
                'name' => $data['restaurant_name'] ?? $data['organization_name'] ?? $data['name'],
                'slug' => Str::slug($data['restaurant_name'] ?? $data['organization_name'] ?? $data['name']) . '-' . $tenant->id,
                'is_active' => true,
                'is_main' => true, // Первый ресторан - главный
            ]);

            // 3. Создаём владельца
            $user = User::create([
                'tenant_id' => $tenant->id,
                'restaurant_id' => $restaurant->id,
                'name' => $data['owner_name'] ?? $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => bcrypt($data['password']),
                'role' => User::ROLE_OWNER,
                'is_active' => true,
                'is_tenant_owner' => true,
            ]);

            return [
                'tenant' => $tenant,
                'restaurant' => $restaurant,
                'user' => $user,
            ];
        });
    }

    /**
     * Добавить ресторан к тенанту
     */
    public function addRestaurant(Tenant $tenant, array $data): Restaurant
    {
        return Restaurant::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . $tenant->id . '-' . time(),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => true,
            'is_main' => false,
        ]);
    }

    /**
     * Проверка лимитов тарифа
     */
    public function canAddRestaurant(Tenant $tenant): bool
    {
        $limits = $this->getPlanLimits($tenant->plan);
        $currentCount = $tenant->restaurants()->count();

        return $limits['max_restaurants'] === null || $currentCount < $limits['max_restaurants'];
    }

    /**
     * Проверка лимита пользователей
     */
    public function canAddUser(Tenant $tenant): bool
    {
        $limits = $this->getPlanLimits($tenant->plan);
        $currentCount = $tenant->users()->count();

        return $limits['max_users'] === null || $currentCount < $limits['max_users'];
    }

    /**
     * Получить лимиты тарифного плана
     */
    public function getPlanLimits(string $plan): array
    {
        $plans = [
            Tenant::PLAN_TRIAL => [
                'max_restaurants' => 1,
                'max_users' => 5,
                'max_orders_per_month' => 100,
            ],
            Tenant::PLAN_START => [
                'max_restaurants' => 1,
                'max_users' => 5,
                'max_orders_per_month' => 500,
            ],
            Tenant::PLAN_BUSINESS => [
                'max_restaurants' => 3,
                'max_users' => 15,
                'max_orders_per_month' => 2000,
            ],
            Tenant::PLAN_PREMIUM => [
                'max_restaurants' => null, // безлимит
                'max_users' => null,
                'max_orders_per_month' => null,
            ],
        ];

        return $plans[$plan] ?? $plans[Tenant::PLAN_TRIAL];
    }

    /**
     * Очистить текущий тенант (для тестов и очистки)
     */
    public function clearCurrentTenant(): void
    {
        static::$currentTenant = null;
        static::$currentRestaurant = null;
    }
}
