<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit тесты для модели Role
 */
class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $role;

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

        $this->role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'test_role',
            'name' => 'Test Role',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => true,
            'can_access_delivery' => false,
            'max_discount_percent' => 20,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 5000,
        ]);

        // Создаём permissions
        Permission::create(['key' => 'orders.view', 'name' => 'View Orders', 'group' => 'orders', 'is_system' => true]);
        Permission::create(['key' => 'orders.create', 'name' => 'Create Orders', 'group' => 'orders', 'is_system' => true]);
        Permission::create(['key' => 'orders.discount', 'name' => 'Apply Discounts', 'group' => 'orders', 'is_system' => true]);
        Permission::create(['key' => 'orders.refund', 'name' => 'Process Refunds', 'group' => 'orders', 'is_system' => true]);
        Permission::create(['key' => 'orders.cancel', 'name' => 'Cancel Orders', 'group' => 'orders', 'is_system' => true]);
        Permission::create(['key' => '*', 'name' => 'Full Access', 'group' => 'system', 'is_system' => true]);
    }

    // ==================== PERMISSION TESTS ====================

    /** @test */
    public function role_can_check_single_permission()
    {
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.view')->first()->id
        );

        $this->assertTrue($this->role->hasPermission('orders.view'));
        $this->assertFalse($this->role->hasPermission('orders.create'));
    }

    /** @test */
    public function role_with_wildcard_permission_has_all_permissions()
    {
        $this->role->permissions()->attach(
            Permission::where('key', '*')->first()->id
        );

        $this->assertTrue($this->role->hasPermission('orders.view'));
        $this->assertTrue($this->role->hasPermission('orders.create'));
        $this->assertTrue($this->role->hasPermission('any.random.permission'));
    }

    /** @test */
    public function role_can_check_any_permission()
    {
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.view')->first()->id
        );

        $this->assertTrue($this->role->hasAnyPermission(['orders.view', 'orders.create']));
        $this->assertFalse($this->role->hasAnyPermission(['orders.create', 'orders.refund']));
    }

    /** @test */
    public function role_can_sync_permissions()
    {
        $this->role->syncPermissions(['orders.view', 'orders.create']);

        $this->assertTrue($this->role->hasPermission('orders.view'));
        $this->assertTrue($this->role->hasPermission('orders.create'));
        $this->assertFalse($this->role->hasPermission('orders.discount'));
    }

    /** @test */
    public function role_can_grant_permission()
    {
        $this->assertFalse($this->role->hasPermission('orders.view'));

        $this->role->grantPermission('orders.view');

        $this->assertTrue($this->role->hasPermission('orders.view'));
    }

    /** @test */
    public function role_can_revoke_permission()
    {
        $this->role->grantPermission('orders.view');
        $this->assertTrue($this->role->hasPermission('orders.view'));

        $this->role->revokePermission('orders.view');

        $this->assertFalse($this->role->hasPermission('orders.view'));
    }

    // ==================== DISCOUNT LIMIT TESTS ====================

    /** @test */
    public function role_allows_discount_within_limit()
    {
        // Роль с max_discount_percent = 20
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.discount')->first()->id
        );

        $this->assertTrue($this->role->canApplyDiscount(10));
        $this->assertTrue($this->role->canApplyDiscount(20));
    }

    /** @test */
    public function role_denies_discount_above_limit()
    {
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.discount')->first()->id
        );

        $this->assertFalse($this->role->canApplyDiscount(21));
        $this->assertFalse($this->role->canApplyDiscount(50));
    }

    /** @test */
    public function role_denies_discount_without_permission()
    {
        // Нет permission orders.discount
        $this->assertFalse($this->role->canApplyDiscount(5));
    }

    // ==================== REFUND LIMIT TESTS ====================

    /** @test */
    public function role_allows_refund_within_limit()
    {
        // Роль с max_refund_amount = 10000
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.refund')->first()->id
        );

        $this->assertTrue($this->role->canRefund(5000));
        $this->assertTrue($this->role->canRefund(10000));
    }

    /** @test */
    public function role_denies_refund_above_limit()
    {
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.refund')->first()->id
        );

        $this->assertFalse($this->role->canRefund(10001));
        $this->assertFalse($this->role->canRefund(50000));
    }

    /** @test */
    public function role_with_zero_refund_limit_cannot_refund()
    {
        $this->role->update(['max_refund_amount' => 0]);
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.refund')->first()->id
        );

        $this->assertFalse($this->role->canRefund(1));
        $this->assertFalse($this->role->canRefund(100));
    }

    /** @test */
    public function role_with_high_refund_limit_can_refund_large_amounts()
    {
        // Устанавливаем очень большой лимит (БД не позволяет null)
        $this->role->update(['max_refund_amount' => 999999999]);
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.refund')->first()->id
        );

        $this->assertTrue($this->role->canRefund(1000000));
        $this->assertTrue($this->role->canRefund(999999999));
    }

    // ==================== CANCEL ORDER LIMIT TESTS ====================

    /** @test */
    public function role_allows_cancel_within_limit()
    {
        // Роль с max_cancel_amount = 5000, нужен permission
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.cancel')->first()->id
        );

        $this->assertTrue($this->role->canCancelOrder(3000));
        $this->assertTrue($this->role->canCancelOrder(5000));
    }

    /** @test */
    public function role_denies_cancel_above_limit()
    {
        $this->role->permissions()->attach(
            Permission::where('key', 'orders.cancel')->first()->id
        );

        $this->assertFalse($this->role->canCancelOrder(5001));
        $this->assertFalse($this->role->canCancelOrder(10000));
    }

    // ==================== INTERFACE ACCESS TESTS ====================

    /** @test */
    public function role_interface_access_flags_are_correct()
    {
        $this->assertTrue($this->role->can_access_pos);
        $this->assertFalse($this->role->can_access_backoffice);
        $this->assertTrue($this->role->can_access_kitchen);
        $this->assertFalse($this->role->can_access_delivery);
    }

    // ==================== HALL ACCESS TESTS ====================

    /** @test */
    public function role_with_no_hall_restrictions_can_access_all_halls()
    {
        $this->role->update(['allowed_halls' => null]);

        $this->assertTrue($this->role->canAccessHall(1));
        $this->assertTrue($this->role->canAccessHall(999));
    }

    /** @test */
    public function role_with_empty_hall_restrictions_can_access_all_halls()
    {
        $this->role->update(['allowed_halls' => []]);

        $this->assertTrue($this->role->canAccessHall(1));
        $this->assertTrue($this->role->canAccessHall(999));
    }

    /** @test */
    public function role_with_hall_restrictions_can_only_access_allowed_halls()
    {
        $this->role->update(['allowed_halls' => [1, 2, 3]]);

        $this->assertTrue($this->role->canAccessHall(1));
        $this->assertTrue($this->role->canAccessHall(2));
        $this->assertTrue($this->role->canAccessHall(3));
        $this->assertFalse($this->role->canAccessHall(4));
        $this->assertFalse($this->role->canAccessHall(99));
    }

    // ==================== PAYMENT METHOD ACCESS TESTS ====================

    /** @test */
    public function role_with_no_payment_restrictions_can_use_all_methods()
    {
        $this->role->update(['allowed_payment_methods' => null]);

        $this->assertTrue($this->role->canUsePaymentMethod('cash'));
        $this->assertTrue($this->role->canUsePaymentMethod('card'));
        $this->assertTrue($this->role->canUsePaymentMethod('transfer'));
    }

    /** @test */
    public function role_with_payment_restrictions_can_only_use_allowed_methods()
    {
        $this->role->update(['allowed_payment_methods' => ['cash', 'card']]);

        $this->assertTrue($this->role->canUsePaymentMethod('cash'));
        $this->assertTrue($this->role->canUsePaymentMethod('card'));
        $this->assertFalse($this->role->canUsePaymentMethod('transfer'));
        $this->assertFalse($this->role->canUsePaymentMethod('crypto'));
    }

    // ==================== DEFAULT ROLES ====================

    /** @test */
    public function default_roles_have_correct_structure()
    {
        $defaultRoles = Role::getDefaultRoles();

        $this->assertIsArray($defaultRoles);
        $this->assertGreaterThan(0, count($defaultRoles));

        foreach ($defaultRoles as $roleData) {
            $this->assertArrayHasKey('key', $roleData);
            $this->assertArrayHasKey('name', $roleData);
            $this->assertArrayHasKey('can_access_pos', $roleData);
            $this->assertArrayHasKey('can_access_backoffice', $roleData);
            $this->assertArrayHasKey('can_access_kitchen', $roleData);
            $this->assertArrayHasKey('can_access_delivery', $roleData);
            $this->assertArrayHasKey('max_discount_percent', $roleData);
        }
    }

    /** @test */
    public function default_cook_role_has_no_pos_access()
    {
        $defaultRoles = Role::getDefaultRoles();
        $cookRole = collect($defaultRoles)->firstWhere('key', 'cook');

        $this->assertNotNull($cookRole);
        $this->assertFalse($cookRole['can_access_pos']);
        $this->assertTrue($cookRole['can_access_kitchen']);
    }

    /** @test */
    public function default_waiter_role_has_pos_access_only()
    {
        $defaultRoles = Role::getDefaultRoles();
        $waiterRole = collect($defaultRoles)->firstWhere('key', 'waiter');

        $this->assertNotNull($waiterRole);
        $this->assertTrue($waiterRole['can_access_pos']);
        $this->assertFalse($waiterRole['can_access_backoffice']);
        $this->assertFalse($waiterRole['can_access_kitchen']);
    }
}
