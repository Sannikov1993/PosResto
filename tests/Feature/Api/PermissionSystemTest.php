<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Создаём роли
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 50,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 50000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 10,
            'max_refund_amount' => 0,
            'max_cancel_amount' => 0,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        // Создаём permissions
        $adminPermissions = [
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
            'orders.discount', 'orders.refund',
            'menu.view', 'menu.create', 'menu.edit', 'menu.delete',
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'finance.view', 'finance.shifts', 'finance.operations',
            'settings.view', 'settings.edit', 'settings.roles',
            'customers.view', 'customers.create', 'customers.edit',
            'reports.view', 'reports.analytics',
            'inventory.view', 'inventory.manage',
            'loyalty.view', 'loyalty.edit',
        ];

        $waiterPermissions = [
            'orders.view', 'orders.create', 'orders.edit',
            'orders.discount',
            'menu.view',
            'customers.view',
        ];

        foreach ($adminPermissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        foreach ($waiterPermissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->waiterRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Создаём пользователей
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);
    }

    // ===== User model: getEffectiveRole =====

    public function test_user_gets_effective_role_from_role_id(): void
    {
        $role = $this->admin->getEffectiveRole();
        $this->assertNotNull($role);
        $this->assertEquals('admin', $role->key);
    }

    public function test_user_gets_effective_role_fallback_by_string(): void
    {
        // Убираем role_id, оставляем строку
        $this->admin->update(['role_id' => null]);
        $role = $this->admin->fresh()->getEffectiveRole();
        $this->assertNotNull($role);
        $this->assertEquals('admin', $role->key);
    }

    // ===== User model: hasPermission =====

    public function test_admin_has_orders_cancel_permission(): void
    {
        $this->assertTrue($this->admin->hasPermission('orders.cancel'));
    }

    public function test_waiter_does_not_have_orders_cancel_permission(): void
    {
        $this->assertFalse($this->waiter->hasPermission('orders.cancel'));
    }

    public function test_waiter_has_orders_create_permission(): void
    {
        $this->assertTrue($this->waiter->hasPermission('orders.create'));
    }

    public function test_super_admin_bypasses_all_permissions(): void
    {
        $superAdmin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $this->assertTrue($superAdmin->hasPermission('settings.roles'));
        $this->assertTrue($superAdmin->hasPermission('anything.at.all'));
    }

    // ===== User model: limit checks =====

    public function test_admin_can_apply_discount_within_limit(): void
    {
        $this->assertTrue($this->admin->canApplyDiscount(30));
    }

    public function test_admin_cannot_apply_discount_above_limit(): void
    {
        $this->assertFalse($this->admin->canApplyDiscount(60));
    }

    public function test_waiter_can_apply_small_discount(): void
    {
        $this->assertTrue($this->waiter->canApplyDiscount(5));
    }

    public function test_waiter_cannot_apply_large_discount(): void
    {
        $this->assertFalse($this->waiter->canApplyDiscount(15));
    }

    public function test_waiter_cannot_refund(): void
    {
        $this->assertFalse($this->waiter->canRefund(100));
    }

    public function test_admin_can_refund_within_limit(): void
    {
        $this->assertTrue($this->admin->canRefund(5000));
    }

    public function test_admin_cannot_refund_above_limit(): void
    {
        $this->assertFalse($this->admin->canRefund(15000));
    }

    public function test_waiter_cannot_cancel_order(): void
    {
        $this->assertFalse($this->waiter->canCancelOrder(1000));
    }

    public function test_admin_can_cancel_order_within_limit(): void
    {
        $this->assertTrue($this->admin->canCancelOrder(30000));
    }

    // ===== CheckPermission middleware =====

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/settings');
        $response->assertStatus(401);
    }

    public function test_waiter_cannot_access_settings_edit(): void
    {
        $token = $this->waiter->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->putJson('/api/settings/print', ['receipt_header' => 'test']);

        $response->assertStatus(403);
    }

    public function test_admin_can_access_settings_view(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/settings');

        // Не 401 и не 403
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_waiter_cannot_access_roles_write(): void
    {
        $token = $this->waiter->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/roles', ['name' => 'Test']);

        $response->assertStatus(403);
    }

    // ===== Auth response includes permissions =====

    public function test_login_response_contains_permissions(): void
    {
        $this->admin->update(['password' => bcrypt('testpassword123')]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $this->admin->email,
            'password' => 'testpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'permissions',
                'limits' => ['max_discount_percent', 'max_refund_amount', 'max_cancel_amount'],
                'interface_access' => ['can_access_pos', 'can_access_backoffice'],
            ],
        ]);
    }

    public function test_check_response_contains_permissions(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->getJson('/api/auth/check');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'permissions',
                'limits',
                'interface_access',
            ],
        ]);
    }
}
