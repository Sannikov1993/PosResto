<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unit тесты для модели User
 */
class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $waiterRole;
    protected Role $cookRole;
    protected Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Создаём роли
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
        ]);

        // Создаём permissions
        $permissions = ['orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund'];
        foreach ($permissions as $key) {
            Permission::create(['key' => $key, 'name' => $key, 'group' => 'orders', 'is_system' => true]);
        }

        // Привязываем permissions
        $waiterPerms = Permission::whereIn('key', ['orders.view', 'orders.create', 'orders.edit', 'orders.discount'])->pluck('id');
        $this->waiterRole->permissions()->sync($waiterPerms);

        $ownerPerms = Permission::pluck('id');
        $this->ownerRole->permissions()->sync($ownerPerms);

        $cookPerms = Permission::whereIn('key', ['orders.view'])->pluck('id');
        $this->cookRole->permissions()->sync($cookPerms);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test User',
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role->key,
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    // ==================== EFFECTIVE ROLE TESTS ====================

    /** @test */
    public function user_gets_effective_role_from_role_id()
    {
        $user = $this->createUser($this->waiterRole);

        $effectiveRole = $user->getEffectiveRole();

        $this->assertNotNull($effectiveRole);
        $this->assertEquals('waiter', $effectiveRole->key);
    }

    /** @test */
    public function user_falls_back_to_string_role_when_no_role_id()
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => Hash::make('password'),
            'role' => 'waiter',
            'role_id' => null,
            'is_active' => true,
        ]);

        $effectiveRole = $user->getEffectiveRole();

        $this->assertNotNull($effectiveRole);
        $this->assertEquals('waiter', $effectiveRole->key);
    }

    /** @test */
    public function user_returns_null_when_no_role_found()
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

    /** @test */
    public function role_id_takes_priority_over_string_role()
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mixed User',
            'email' => 'mixed@example.com',
            'password' => Hash::make('password'),
            'role' => 'cook', // String says cook
            'role_id' => $this->waiterRole->id, // But role_id points to waiter
            'is_active' => true,
        ]);

        $effectiveRole = $user->getEffectiveRole();

        // role_id should win
        $this->assertEquals('waiter', $effectiveRole->key);
    }

    // ==================== PERMISSION TESTS ====================

    /** @test */
    public function user_has_permissions_from_role()
    {
        $user = $this->createUser($this->waiterRole);

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertTrue($user->hasPermission('orders.create'));
        $this->assertTrue($user->hasPermission('orders.discount'));
        $this->assertFalse($user->hasPermission('orders.cancel'));
        $this->assertFalse($user->hasPermission('orders.refund'));
    }

    /** @test */
    public function tenant_owner_has_all_permissions()
    {
        $user = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Tenant owner bypasses all permission checks
        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertTrue($user->hasPermission('orders.cancel'));
        $this->assertTrue($user->hasPermission('orders.refund'));
        $this->assertTrue($user->hasPermission('any.permission'));
    }

    /** @test */
    public function super_admin_has_all_permissions()
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasPermission('any.permission'));
        $this->assertTrue($user->hasPermission('orders.cancel'));
    }

    // ==================== DISCOUNT LIMIT TESTS ====================

    /** @test */
    public function user_can_apply_discount_within_role_limit()
    {
        $user = $this->createUser($this->waiterRole);

        $this->assertTrue($user->canApplyDiscount(5));
        $this->assertTrue($user->canApplyDiscount(10));
    }

    /** @test */
    public function user_cannot_apply_discount_above_role_limit()
    {
        $user = $this->createUser($this->waiterRole);

        $this->assertFalse($user->canApplyDiscount(11));
        $this->assertFalse($user->canApplyDiscount(50));
    }

    /** @test */
    public function cook_cannot_apply_any_discount()
    {
        $user = $this->createUser($this->cookRole);

        $this->assertFalse($user->canApplyDiscount(1));
        $this->assertFalse($user->canApplyDiscount(5));
    }

    /** @test */
    public function tenant_owner_can_apply_any_discount()
    {
        $user = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        $this->assertTrue($user->canApplyDiscount(100));
    }

    // ==================== REFUND LIMIT TESTS ====================

    /** @test */
    public function user_cannot_refund_if_role_limit_is_zero()
    {
        $user = $this->createUser($this->waiterRole);

        $this->assertFalse($user->canRefund(1));
        $this->assertFalse($user->canRefund(100));
    }

    /** @test */
    public function owner_can_refund_large_amounts()
    {
        $user = $this->createUser($this->ownerRole);

        $this->assertTrue($user->canRefund(100000));
        $this->assertTrue($user->canRefund(999999));
    }

    /** @test */
    public function tenant_owner_bypasses_refund_limits()
    {
        $user = $this->createUser($this->waiterRole, [
            'is_tenant_owner' => true,
        ]);

        $this->assertTrue($user->canRefund(1000000));
    }

    // ==================== CANCEL ORDER LIMIT TESTS ====================

    /** @test */
    public function user_cannot_cancel_if_role_limit_is_zero()
    {
        $user = $this->createUser($this->waiterRole);

        $this->assertFalse($user->canCancelOrder(1));
    }

    /** @test */
    public function owner_can_cancel_large_orders()
    {
        $user = $this->createUser($this->ownerRole);

        $this->assertTrue($user->canCancelOrder(500000));
    }

    // ==================== HELPER METHOD TESTS ====================

    /** @test */
    public function is_admin_returns_true_for_admin_roles()
    {
        $owner = $this->createUser($this->ownerRole);
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($owner->isAdmin());
        $this->assertFalse($waiter->isAdmin());
    }

    /** @test */
    public function is_manager_returns_true_for_manager_level_roles()
    {
        $owner = $this->createUser($this->ownerRole);
        $waiter = $this->createUser($this->waiterRole);
        $cook = $this->createUser($this->cookRole);

        $this->assertTrue($owner->isManager());
        $this->assertFalse($waiter->isManager());
        $this->assertFalse($cook->isManager());
    }

    /** @test */
    public function is_tenant_owner_returns_correct_value()
    {
        $regular = $this->createUser($this->waiterRole);
        $tenantOwner = $this->createUser($this->waiterRole, ['is_tenant_owner' => true]);

        $this->assertFalse($regular->isTenantOwner());
        $this->assertTrue($tenantOwner->isTenantOwner());
    }

    // ==================== PIN TESTS ====================

    /** @test */
    public function user_can_verify_pin()
    {
        $user = $this->createUser($this->waiterRole);
        $user->setPin('1234');

        $this->assertTrue($user->verifyPin('1234'));
        $this->assertFalse($user->verifyPin('0000'));
        $this->assertFalse($user->verifyPin('5678'));
    }

    /** @test */
    public function user_can_clear_pin()
    {
        $user = $this->createUser($this->waiterRole);
        $user->setPin('1234');

        $this->assertTrue($user->has_pin);

        $user->clearPin();
        $user->refresh();

        $this->assertFalse($user->has_pin);
    }

    /** @test */
    public function generate_pin_returns_4_digit_string()
    {
        $pin = User::generatePin();

        $this->assertEquals(4, strlen($pin));
        $this->assertTrue(is_numeric($pin));
    }

    // ==================== ACCESSOR TESTS ====================

    /** @test */
    public function role_label_returns_human_readable_role()
    {
        $waiter = $this->createUser($this->waiterRole);
        $cook = $this->createUser($this->cookRole);

        $this->assertEquals('Официант', $waiter->role_label);
        $this->assertEquals('Повар', $cook->role_label);
    }

    /** @test */
    public function initials_returns_first_letters_of_name()
    {
        $user = $this->createUser($this->waiterRole, ['name' => 'Иван Петров']);

        $this->assertEquals('ИП', $user->initials);
    }

    /** @test */
    public function initials_handles_single_word_name()
    {
        $user = $this->createUser($this->waiterRole, ['name' => 'Иван']);

        // Для одного слова возвращается только первая буква
        $this->assertEquals('И', $user->initials);
    }

    /** @test */
    public function has_password_returns_correct_value()
    {
        $userWithPassword = $this->createUser($this->waiterRole, [
            'password' => Hash::make('realpassword'),
        ]);

        $this->assertTrue($userWithPassword->has_password);
    }

    /** @test */
    public function has_pin_returns_correct_value()
    {
        $userWithPin = $this->createUser($this->waiterRole, [
            'pin_code' => Hash::make('1234'),
        ]);

        $userWithoutPin = $this->createUser($this->waiterRole, [
            'pin_code' => null,
        ]);

        $this->assertTrue($userWithPin->has_pin);
        $this->assertFalse($userWithoutPin->has_pin);
    }

    // ==================== SCOPE TESTS ====================

    /** @test */
    public function active_scope_filters_inactive_users()
    {
        $activeUser = $this->createUser($this->waiterRole, ['is_active' => true]);
        $inactiveUser = $this->createUser($this->waiterRole, ['is_active' => false]);

        $activeUsers = User::active()->pluck('id');

        $this->assertTrue($activeUsers->contains($activeUser->id));
        $this->assertFalse($activeUsers->contains($inactiveUser->id));
    }

    /** @test */
    public function by_role_scope_filters_by_role()
    {
        $waiter = $this->createUser($this->waiterRole);
        $cook = $this->createUser($this->cookRole);

        $waiters = User::byRole('waiter')->pluck('id');

        $this->assertTrue($waiters->contains($waiter->id));
        $this->assertFalse($waiters->contains($cook->id));
    }

    /** @test */
    public function staff_scope_returns_only_staff_roles()
    {
        $waiter = $this->createUser($this->waiterRole);
        $cook = $this->createUser($this->cookRole);
        $owner = $this->createUser($this->ownerRole);

        $staff = User::staff()->pluck('id');

        $this->assertTrue($staff->contains($waiter->id));
        $this->assertTrue($staff->contains($cook->id));
        $this->assertFalse($staff->contains($owner->id));
    }
}
