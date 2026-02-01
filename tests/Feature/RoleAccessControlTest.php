<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Enterprise-level тесты для системы ролей и прав доступа
 */
class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $ownerRole;
    protected Role $cookRole;
    protected Role $waiterRole;
    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём тенант и ресторан
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->restaurant = Restaurant::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
        ]);

        // Создаём роли с разными правами доступа
        $this->ownerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'owner',
            'name' => 'Владелец',
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
            'max_discount_percent' => 100,
            'max_refund_amount' => 999999,
            'max_cancel_amount' => 999999,
            'is_system' => true,
        ]);

        $this->cookRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'cook',
            'name' => 'Повар',
            'can_access_pos' => false,
            'can_access_backoffice' => false,
            'can_access_kitchen' => true,
            'can_access_delivery' => false,
            'max_discount_percent' => 0,
            'max_refund_amount' => 0,
            'max_cancel_amount' => 0,
            'is_system' => true,
        ]);

        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
            'max_discount_percent' => 10,
            'max_refund_amount' => 0,
            'max_cancel_amount' => 0,
            'is_system' => true,
        ]);

        $this->cashierRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'cashier',
            'name' => 'Кассир',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
            'max_discount_percent' => 15,
            'max_refund_amount' => 5000,
            'max_cancel_amount' => 5000,
            'is_system' => true,
        ]);

        // Создаём permissions
        $this->createDefaultPermissions();
    }

    protected function createDefaultPermissions(): void
    {
        $permissions = [
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund',
            'menu.view', 'menu.edit',
            'staff.view', 'staff.edit',
        ];

        foreach ($permissions as $key) {
            Permission::create([
                'restaurant_id' => null, // Системные permissions
                'key' => $key,
                'name' => $key,
                'is_system' => true,
            ]);
        }

        // Привязываем permissions к ролям
        $ownerPermissions = Permission::whereIn('key', ['orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund', 'menu.view', 'menu.edit', 'staff.view', 'staff.edit'])->pluck('id');
        $this->ownerRole->permissions()->sync($ownerPermissions);

        $waiterPermissions = Permission::whereIn('key', ['orders.view', 'orders.create', 'orders.edit', 'orders.discount', 'menu.view'])->pluck('id');
        $this->waiterRole->permissions()->sync($waiterPermissions);

        $cookPermissions = Permission::whereIn('key', ['orders.view', 'menu.view'])->pluck('id');
        $this->cookRole->permissions()->sync($cookPermissions);

        $cashierPermissions = Permission::whereIn('key', ['orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund', 'menu.view'])->pluck('id');
        $this->cashierRole->permissions()->sync($cashierPermissions);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test User',
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'role' => $role->key,
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    // ==================== ТЕСТЫ ДОСТУПА К ИНТЕРФЕЙСАМ ====================

    /** @test */
    public function cook_cannot_login_to_pos()
    {
        $cook = $this->createUser($this->cookRole, ['name' => 'Повар Иван']);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $cook->id,
            'app_type' => 'pos',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'interface_access_denied',
            ])
            ->assertJsonFragment([
                'denied_interface' => 'pos',
            ]);
    }

    /** @test */
    public function cook_can_login_to_kitchen()
    {
        $cook = $this->createUser($this->cookRole, ['name' => 'Повар Иван']);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $cook->id,
            'app_type' => 'kitchen',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token', 'permissions', 'limits', 'interface_access'],
            ]);
    }

    /** @test */
    public function waiter_can_login_to_pos()
    {
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Официант Мария']);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $waiter->id,
            'app_type' => 'pos',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function waiter_cannot_login_to_backoffice()
    {
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Официант Мария']);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $waiter->id,
            'app_type' => 'backoffice',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'interface_access_denied',
            ]);
    }

    /** @test */
    public function owner_can_login_to_any_interface()
    {
        $owner = $this->createUser($this->ownerRole, ['name' => 'Владелец']);

        $interfaces = ['pos', 'backoffice', 'kitchen', 'delivery'];

        foreach ($interfaces as $interface) {
            $response = $this->postJson('/api/auth/login-pin', [
                'pin' => '1234',
                'user_id' => $owner->id,
                'app_type' => $interface,
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function tenant_owner_can_login_to_any_interface()
    {
        $tenantOwner = $this->createUser($this->waiterRole, [
            'name' => 'Tenant Owner',
            'is_tenant_owner' => true,
        ]);

        // Даже с ролью официанта, tenant owner имеет полный доступ
        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $tenantOwner->id,
            'app_type' => 'backoffice',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ==================== ТЕСТЫ ПРАВ (PERMISSIONS) ====================

    /** @test */
    public function user_has_correct_permissions_from_role()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($waiter->hasPermission('orders.view'));
        $this->assertTrue($waiter->hasPermission('orders.create'));
        $this->assertTrue($waiter->hasPermission('orders.discount'));
        $this->assertFalse($waiter->hasPermission('orders.cancel'));
        $this->assertFalse($waiter->hasPermission('orders.refund'));
        $this->assertFalse($waiter->hasPermission('staff.edit'));
    }

    /** @test */
    public function cook_has_minimal_permissions()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertTrue($cook->hasPermission('orders.view'));
        $this->assertTrue($cook->hasPermission('menu.view'));
        $this->assertFalse($cook->hasPermission('orders.create'));
        $this->assertFalse($cook->hasPermission('orders.edit'));
        $this->assertFalse($cook->hasPermission('orders.cancel'));
    }

    /** @test */
    public function cashier_can_process_refunds()
    {
        $cashier = $this->createUser($this->cashierRole);

        $this->assertTrue($cashier->hasPermission('orders.refund'));
        $this->assertTrue($cashier->hasPermission('orders.cancel'));
    }

    /** @test */
    public function tenant_owner_has_all_permissions()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Tenant owner имеет все права, независимо от роли
        $this->assertTrue($tenantOwner->hasPermission('orders.view'));
        $this->assertTrue($tenantOwner->hasPermission('orders.cancel'));
        $this->assertTrue($tenantOwner->hasPermission('staff.edit'));
        $this->assertTrue($tenantOwner->hasPermission('any.permission'));
    }

    // ==================== ТЕСТЫ ЛИМИТОВ ====================

    /** @test */
    public function waiter_discount_limit_is_respected()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($waiter->canApplyDiscount(5));
        $this->assertTrue($waiter->canApplyDiscount(10));
        $this->assertFalse($waiter->canApplyDiscount(15));
        $this->assertFalse($waiter->canApplyDiscount(50));
    }

    /** @test */
    public function cashier_refund_limit_is_respected()
    {
        $cashier = $this->createUser($this->cashierRole);

        $this->assertTrue($cashier->canRefund(1000));
        $this->assertTrue($cashier->canRefund(5000));
        $this->assertFalse($cashier->canRefund(5001));
        $this->assertFalse($cashier->canRefund(10000));
    }

    /** @test */
    public function cashier_cancel_limit_is_respected()
    {
        $cashier = $this->createUser($this->cashierRole);

        $this->assertTrue($cashier->canCancelOrder(3000));
        $this->assertTrue($cashier->canCancelOrder(5000));
        $this->assertFalse($cashier->canCancelOrder(5001));
    }

    /** @test */
    public function cook_cannot_apply_any_discount()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertFalse($cook->canApplyDiscount(1));
        $this->assertFalse($cook->canApplyDiscount(5));
    }

    /** @test */
    public function owner_has_no_limits()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->assertTrue($owner->canApplyDiscount(100));
        $this->assertTrue($owner->canRefund(1000000));
        $this->assertTrue($owner->canCancelOrder(1000000));
    }

    /** @test */
    public function tenant_owner_bypasses_all_limits()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Даже с ролью повара (нулевые лимиты), tenant owner не имеет ограничений
        $this->assertTrue($tenantOwner->canApplyDiscount(100));
        $this->assertTrue($tenantOwner->canRefund(1000000));
        $this->assertTrue($tenantOwner->canCancelOrder(1000000));
    }

    // ==================== ТЕСТЫ INTERFACE ACCESS DATA ====================

    /** @test */
    public function login_returns_correct_interface_access()
    {
        $cook = $this->createUser($this->cookRole);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $cook->id,
            'app_type' => 'kitchen',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.interface_access.can_access_pos', false)
            ->assertJsonPath('data.interface_access.can_access_backoffice', false)
            ->assertJsonPath('data.interface_access.can_access_kitchen', true)
            ->assertJsonPath('data.interface_access.can_access_delivery', false);
    }

    /** @test */
    public function login_returns_correct_limits()
    {
        $cashier = $this->createUser($this->cashierRole);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $cashier->id,
            'app_type' => 'pos',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.limits.max_discount_percent', 15)
            ->assertJsonPath('data.limits.max_refund_amount', 5000.0)
            ->assertJsonPath('data.limits.max_cancel_amount', 5000.0);
    }

    // ==================== ТЕСТЫ РОЛИ БЕЗ ЗАПИСИ В БД ====================

    /** @test */
    public function user_without_role_record_cannot_login()
    {
        $userWithoutRole = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'User Without Role',
            'email' => 'norole@example.com',
            'password' => Hash::make('password'),
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'role' => 'unknown_role', // Роли нет в БД
            'role_id' => null,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $userWithoutRole->id,
            'app_type' => 'pos',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'no_role_assigned',
            ]);
    }

    // ==================== ТЕСТЫ PASSWORD LOGIN ====================

    /** @test */
    public function password_login_checks_interface_access()
    {
        $cook = $this->createUser($this->cookRole, [
            'email' => 'cook@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'cook@example.com',
            'password' => 'password123',
            'app_type' => 'pos',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'interface_access_denied',
            ]);
    }

    /** @test */
    public function password_login_without_app_type_succeeds()
    {
        $cook = $this->createUser($this->cookRole, [
            'email' => 'cook2@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Без app_type — универсальный логин (без проверки интерфейса)
        $response = $this->postJson('/api/auth/login', [
            'login' => 'cook2@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ==================== ТЕСТЫ USERS LIST FILTERING ====================

    /** @test */
    public function users_list_filters_by_interface_access()
    {
        // Создаём пользователей с разными ролями
        $owner = $this->createUser($this->ownerRole, ['name' => 'Owner']);
        $cook = $this->createUser($this->cookRole, ['name' => 'Cook']);
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);
        $cashier = $this->createUser($this->cashierRole, ['name' => 'Cashier']);

        // Авторизуемся как owner
        $this->actingAs($owner);

        // Запрос списка пользователей для POS
        $response = $this->getJson('/api/auth/users?app_type=pos');

        $response->assertStatus(200);

        $users = collect($response->json('data'));

        // Повар не должен быть в списке (can_access_pos = false)
        $this->assertFalse($users->contains('name', 'Cook'));

        // Остальные должны быть (can_access_pos = true)
        $this->assertTrue($users->contains('name', 'Owner'));
        $this->assertTrue($users->contains('name', 'Waiter'));
        $this->assertTrue($users->contains('name', 'Cashier'));
    }

    /** @test */
    public function users_list_for_kitchen_shows_only_cooks()
    {
        $owner = $this->createUser($this->ownerRole, ['name' => 'Owner']);
        $cook = $this->createUser($this->cookRole, ['name' => 'Cook']);
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);

        $this->actingAs($owner);

        $response = $this->getJson('/api/auth/users?app_type=kitchen');

        $response->assertStatus(200);

        $users = collect($response->json('data'));

        // Только повар и владелец имеют доступ к kitchen
        $this->assertTrue($users->contains('name', 'Cook'));
        $this->assertTrue($users->contains('name', 'Owner'));
        $this->assertFalse($users->contains('name', 'Waiter'));
    }

    // ==================== ТЕСТЫ EFFECTIVE ROLE ====================

    /** @test */
    public function effective_role_uses_role_id_first()
    {
        $user = $this->createUser($this->waiterRole, [
            'role' => 'cook', // Строковое поле указывает на cook
            'role_id' => $this->waiterRole->id, // Но role_id на waiter
        ]);

        // Должен использоваться role_id (waiter), а не строковое поле (cook)
        $effectiveRole = $user->getEffectiveRole();

        $this->assertEquals('waiter', $effectiveRole->key);
        $this->assertTrue($user->hasPermission('orders.create')); // waiter permission
    }

    /** @test */
    public function effective_role_falls_back_to_string_role()
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => Hash::make('password'),
            'role' => 'waiter', // Только строковое поле
            'role_id' => null, // Без role_id
            'is_active' => true,
        ]);

        $effectiveRole = $user->getEffectiveRole();

        // Должен найти роль по строковому ключу
        $this->assertNotNull($effectiveRole);
        $this->assertEquals('waiter', $effectiveRole->key);
    }
}
