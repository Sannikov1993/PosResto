<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Services\DeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Тесты для системы ролей и прав доступа
 *
 * API endpoints тестируются в CheckInterfaceAccessMiddlewareTest
 * Здесь тестируется логика моделей и сервисов
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
    protected DeviceSessionService $deviceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceService = new DeviceSessionService();

        // Создаём тенант и ресторан
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@example.com',
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
            $group = explode('.', $key)[0];
            Permission::create([
                'restaurant_id' => null,
                'key' => $key,
                'name' => $key,
                'group' => $group,
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

    // ==================== ТЕСТЫ ДОСТУПА К ИНТЕРФЕЙСАМ (через DeviceSessionService) ====================

    /** @test */
    public function cook_cannot_access_pos_via_service()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertFalse($this->deviceService->canAccessApp($cook, 'pos'));
    }

    /** @test */
    public function cook_can_access_kitchen_via_service()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertTrue($this->deviceService->canAccessApp($cook, 'kitchen'));
    }

    /** @test */
    public function waiter_can_access_pos_via_service()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($this->deviceService->canAccessApp($waiter, 'pos'));
    }

    /** @test */
    public function waiter_cannot_access_backoffice_via_service()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertFalse($this->deviceService->canAccessApp($waiter, 'backoffice'));
    }

    /** @test */
    public function owner_can_access_all_interfaces_via_service()
    {
        $owner = $this->createUser($this->ownerRole);

        $interfaces = ['pos', 'backoffice', 'kitchen', 'delivery'];

        foreach ($interfaces as $interface) {
            $this->assertTrue(
                $this->deviceService->canAccessApp($owner, $interface),
                "Owner should have access to {$interface}"
            );
        }
    }

    /** @test */
    public function tenant_owner_bypasses_role_restrictions()
    {
        // Cook с флагом tenant_owner должен иметь доступ ко всему
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        $this->assertTrue($this->deviceService->canAccessApp($tenantOwner, 'pos'));
        $this->assertTrue($this->deviceService->canAccessApp($tenantOwner, 'backoffice'));
        $this->assertTrue($this->deviceService->canAccessApp($tenantOwner, 'kitchen'));
    }

    /** @test */
    public function super_admin_has_access_to_all_interfaces()
    {
        $superAdmin = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->assertTrue($this->deviceService->canAccessApp($superAdmin, 'pos'));
        $this->assertTrue($this->deviceService->canAccessApp($superAdmin, 'backoffice'));
        $this->assertTrue($this->deviceService->canAccessApp($superAdmin, 'kitchen'));
    }

    // ==================== ТЕСТЫ PERMISSIONS ====================

    /** @test */
    public function cook_has_limited_permissions()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertTrue($cook->hasPermission('orders.view'));
        $this->assertTrue($cook->hasPermission('menu.view'));
        $this->assertFalse($cook->hasPermission('orders.create'));
        $this->assertFalse($cook->hasPermission('orders.cancel'));
        $this->assertFalse($cook->hasPermission('staff.edit'));
    }

    /** @test */
    public function waiter_has_order_permissions()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($waiter->hasPermission('orders.view'));
        $this->assertTrue($waiter->hasPermission('orders.create'));
        $this->assertTrue($waiter->hasPermission('orders.edit'));
        $this->assertTrue($waiter->hasPermission('orders.discount'));
        $this->assertFalse($waiter->hasPermission('orders.cancel'));
        $this->assertFalse($waiter->hasPermission('orders.refund'));
    }

    /** @test */
    public function owner_has_all_permissions()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->assertTrue($owner->hasPermission('orders.view'));
        $this->assertTrue($owner->hasPermission('orders.cancel'));
        $this->assertTrue($owner->hasPermission('orders.refund'));
        $this->assertTrue($owner->hasPermission('staff.edit'));
        $this->assertTrue($owner->hasPermission('menu.edit'));
    }

    /** @test */
    public function tenant_owner_bypasses_permission_check()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Despite being cook, tenant owner has all permissions
        $this->assertTrue($tenantOwner->hasPermission('orders.cancel'));
        $this->assertTrue($tenantOwner->hasPermission('staff.edit'));
        $this->assertTrue($tenantOwner->hasPermission('any.random.permission'));
    }

    // ==================== ТЕСТЫ ЛИМИТОВ ====================

    /** @test */
    public function waiter_can_only_apply_limited_discount()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($waiter->canApplyDiscount(5));
        $this->assertTrue($waiter->canApplyDiscount(10));
        $this->assertFalse($waiter->canApplyDiscount(11));
        $this->assertFalse($waiter->canApplyDiscount(50));
    }

    /** @test */
    public function cashier_has_higher_discount_limit()
    {
        $cashier = $this->createUser($this->cashierRole);

        $this->assertTrue($cashier->canApplyDiscount(10));
        $this->assertTrue($cashier->canApplyDiscount(15));
        $this->assertFalse($cashier->canApplyDiscount(16));
    }

    /** @test */
    public function owner_can_apply_any_discount()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->assertTrue($owner->canApplyDiscount(50));
        $this->assertTrue($owner->canApplyDiscount(100));
    }

    /** @test */
    public function cook_cannot_apply_any_discount()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertFalse($cook->canApplyDiscount(1));
        $this->assertFalse($cook->canApplyDiscount(5));
    }

    /** @test */
    public function waiter_cannot_refund()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertFalse($waiter->canRefund(100));
        $this->assertFalse($waiter->canRefund(1));
    }

    /** @test */
    public function cashier_can_refund_within_limit()
    {
        $cashier = $this->createUser($this->cashierRole);

        $this->assertTrue($cashier->canRefund(1000));
        $this->assertTrue($cashier->canRefund(5000));
        $this->assertFalse($cashier->canRefund(5001));
    }

    /** @test */
    public function owner_can_refund_large_amounts()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->assertTrue($owner->canRefund(100000));
        $this->assertTrue($owner->canRefund(999999));
    }

    /** @test */
    public function tenant_owner_bypasses_all_limits()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Despite being cook (max_discount=0, max_refund=0), tenant owner can do everything
        $this->assertTrue($tenantOwner->canApplyDiscount(100));
        $this->assertTrue($tenantOwner->canRefund(1000000));
        $this->assertTrue($tenantOwner->canCancelOrder(1000000));
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

        $this->assertNotNull($effectiveRole);
        $this->assertEquals('waiter', $effectiveRole->key);
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
            'role' => 'waiter',
            'role_id' => null, // Нет role_id
            'is_active' => true,
        ]);

        $effectiveRole = $user->getEffectiveRole();

        $this->assertNotNull($effectiveRole);
        $this->assertEquals('waiter', $effectiveRole->key);
    }

    /** @test */
    public function user_without_valid_role_returns_null()
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'No Role User',
            'email' => 'norole@example.com',
            'password' => Hash::make('password'),
            'role' => 'nonexistent_role',
            'role_id' => null,
            'is_active' => true,
        ]);

        $effectiveRole = $user->getEffectiveRole();

        $this->assertNull($effectiveRole);
    }

    // ==================== ТЕСТЫ АКТИВНОСТИ ПОЛЬЗОВАТЕЛЯ ====================

    /** @test */
    public function inactive_user_cannot_access_anything()
    {
        $inactiveWaiter = $this->createUser($this->waiterRole, [
            'is_active' => false,
        ]);

        // Inactive users are filtered out by scopes
        $activeUsers = User::active()->where('id', $inactiveWaiter->id)->exists();

        $this->assertFalse($activeUsers);
    }

    /** @test */
    public function active_scope_returns_only_active_users()
    {
        $activeUser = $this->createUser($this->waiterRole, ['is_active' => true, 'name' => 'Active']);
        $inactiveUser = $this->createUser($this->waiterRole, ['is_active' => false, 'name' => 'Inactive']);

        $activeUsers = User::active()->pluck('name')->toArray();

        $this->assertContains('Active', $activeUsers);
        $this->assertNotContains('Inactive', $activeUsers);
    }

    // ==================== ТЕСТЫ ROLE SCOPES ====================

    /** @test */
    public function by_role_scope_filters_correctly()
    {
        $waiter1 = $this->createUser($this->waiterRole, ['name' => 'Waiter 1']);
        $waiter2 = $this->createUser($this->waiterRole, ['name' => 'Waiter 2']);
        $cook = $this->createUser($this->cookRole, ['name' => 'Cook']);

        $waiters = User::byRole('waiter')->pluck('name')->toArray();

        $this->assertContains('Waiter 1', $waiters);
        $this->assertContains('Waiter 2', $waiters);
        $this->assertNotContains('Cook', $waiters);
    }

    /** @test */
    public function staff_scope_excludes_owners_and_admins()
    {
        $owner = $this->createUser($this->ownerRole, ['name' => 'Owner']);
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);
        $cook = $this->createUser($this->cookRole, ['name' => 'Cook']);

        $staff = User::staff()->pluck('name')->toArray();

        $this->assertContains('Waiter', $staff);
        $this->assertContains('Cook', $staff);
        $this->assertNotContains('Owner', $staff);
    }
}
