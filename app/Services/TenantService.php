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
        return $this->getPlansConfig()[$plan]['limits'] ?? $this->getPlansConfig()[Tenant::PLAN_TRIAL]['limits'];
    }

    /**
     * Получить полную конфигурацию всех тарифов
     */
    public function getPlansConfig(): array
    {
        return [
            Tenant::PLAN_TRIAL => [
                'id' => Tenant::PLAN_TRIAL,
                'name' => 'Пробный период',
                'description' => 'Бесплатно 14 дней для тестирования',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'is_free' => true,
                'limits' => [
                    'max_restaurants' => 1,
                    'max_users' => 5,
                    'max_orders_per_month' => 100,
                ],
                'features' => [
                    'POS-терминал',
                    'Базовое меню',
                    'До 100 заказов/мес',
                ],
            ],
            Tenant::PLAN_START => [
                'id' => Tenant::PLAN_START,
                'name' => 'Старт',
                'description' => 'Для небольших заведений',
                'price_monthly' => 1990,
                'price_yearly' => 19900, // ~2 месяца бесплатно
                'is_free' => false,
                'limits' => [
                    'max_restaurants' => 1,
                    'max_users' => 5,
                    'max_orders_per_month' => 500,
                ],
                'features' => [
                    'POS-терминал',
                    'Полное меню с модификаторами',
                    'До 500 заказов/мес',
                    'Базовая аналитика',
                    'Кухонный дисплей',
                ],
            ],
            Tenant::PLAN_BUSINESS => [
                'id' => Tenant::PLAN_BUSINESS,
                'name' => 'Бизнес',
                'description' => 'Для растущего бизнеса',
                'price_monthly' => 4990,
                'price_yearly' => 49900,
                'is_free' => false,
                'is_popular' => true,
                'limits' => [
                    'max_restaurants' => 3,
                    'max_users' => 15,
                    'max_orders_per_month' => 2000,
                ],
                'features' => [
                    'Всё из тарифа Старт',
                    'До 3 точек',
                    'До 15 сотрудников',
                    'До 2000 заказов/мес',
                    'Складской учёт',
                    'Система лояльности',
                    'Доставка и курьеры',
                    'Расширенная аналитика',
                ],
            ],
            Tenant::PLAN_PREMIUM => [
                'id' => Tenant::PLAN_PREMIUM,
                'name' => 'Премиум',
                'description' => 'Для сетей и франшиз',
                'price_monthly' => 9990,
                'price_yearly' => 99900,
                'is_free' => false,
                'limits' => [
                    'max_restaurants' => null,
                    'max_users' => null,
                    'max_orders_per_month' => null,
                ],
                'features' => [
                    'Всё из тарифа Бизнес',
                    'Безлимит точек',
                    'Безлимит сотрудников',
                    'Безлимит заказов',
                    'API интеграции',
                    'Приоритетная поддержка',
                    'Персональный менеджер',
                ],
            ],
        ];
    }

    /**
     * Получить доступные тарифы для смены (кроме trial)
     */
    public function getAvailablePlans(): array
    {
        $plans = $this->getPlansConfig();
        unset($plans[Tenant::PLAN_TRIAL]); // Trial нельзя выбрать
        return array_values($plans);
    }

    /**
     * Сменить тарифный план
     */
    public function changePlan(Tenant $tenant, string $newPlan, string $period = 'monthly'): array
    {
        $plans = $this->getPlansConfig();

        if (!isset($plans[$newPlan]) || $newPlan === Tenant::PLAN_TRIAL) {
            throw new \InvalidArgumentException('Недопустимый тарифный план');
        }

        $planConfig = $plans[$newPlan];
        $price = $period === 'yearly' ? $planConfig['price_yearly'] : $planConfig['price_monthly'];
        $days = $period === 'yearly' ? 365 : 30;

        // Рассчитываем новую дату окончания
        $currentEnd = $tenant->subscription_ends_at;
        if ($currentEnd && $currentEnd->isFuture()) {
            // Если есть активная подписка - добавляем к ней
            // Используем copy() чтобы не мутировать оригинальный объект
            $newEnd = $currentEnd->copy()->addDays($days);
        } else {
            // Иначе - от текущей даты
            $newEnd = now()->addDays($days);
        }

        $tenant->update([
            'plan' => $newPlan,
            'subscription_ends_at' => $newEnd,
            'trial_ends_at' => null, // Убираем триал
        ]);

        return [
            'tenant' => $tenant->fresh(),
            'plan' => $planConfig,
            'price' => $price,
            'period' => $period,
            'expires_at' => $newEnd,
        ];
    }

    /**
     * Продлить текущую подписку
     */
    public function extendSubscription(Tenant $tenant, string $period = 'monthly'): array
    {
        if ($tenant->plan === Tenant::PLAN_TRIAL) {
            throw new \InvalidArgumentException('Нельзя продлить пробный период. Выберите тариф.');
        }

        $plans = $this->getPlansConfig();
        $planConfig = $plans[$tenant->plan];
        $price = $period === 'yearly' ? $planConfig['price_yearly'] : $planConfig['price_monthly'];
        $days = $period === 'yearly' ? 365 : 30;

        $currentEnd = $tenant->subscription_ends_at;
        if ($currentEnd && $currentEnd->isFuture()) {
            // Используем copy() чтобы не мутировать оригинальный объект
            $newEnd = $currentEnd->copy()->addDays($days);
        } else {
            $newEnd = now()->addDays($days);
        }

        $tenant->update([
            'subscription_ends_at' => $newEnd,
        ]);

        return [
            'tenant' => $tenant->fresh(),
            'plan' => $planConfig,
            'price' => $price,
            'period' => $period,
            'expires_at' => $newEnd,
        ];
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
