<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $manager;
    protected User $waiter;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with settings.roles permission
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 1,
            'max_discount_percent' => 100,
            'max_refund_amount' => 100000,
            'max_cancel_amount' => 100000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        // Create manager role with limited permissions
        $this->managerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'manager',
            'name' => 'Менеджер',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 2,
            'max_discount_percent' => 30,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 10000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        // Create waiter role without settings.roles permission
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 3,
            'max_discount_percent' => 10,
            'max_refund_amount' => 0,
            'max_cancel_amount' => 0,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        // Create permissions
        $allPermissions = [
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'menu.view', 'menu.create', 'menu.edit', 'menu.delete',
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund',
            'settings.view', 'settings.edit', 'settings.roles',
            'customers.view', 'customers.create', 'customers.edit',
            'finance.view', 'finance.shifts',
        ];

        foreach ($allPermissions as $key) {
            Permission::firstOrCreate([
                'restaurant_id' => null,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
                'is_system' => true,
            ]);
        }

        // Assign permissions to admin role (all including settings.roles)
        $adminPermissionIds = Permission::whereIn('key', $allPermissions)->pluck('id');
        $this->adminRole->permissions()->sync($adminPermissionIds);

        // Assign limited permissions to manager role (no settings.roles)
        $managerPermissionKeys = [
            'staff.view', 'staff.edit',
            'menu.view', 'menu.edit',
            'orders.view', 'orders.create', 'orders.edit', 'orders.discount',
            'settings.view',
            'customers.view', 'customers.edit',
        ];
        $managerPermissionIds = Permission::whereIn('key', $managerPermissionKeys)->pluck('id');
        $this->managerRole->permissions()->sync($managerPermissionIds);

        // Assign minimal permissions to waiter role (including orders.discount)
        $waiterPermissionKeys = ['menu.view', 'orders.view', 'orders.create', 'orders.discount', 'customers.view'];
        $waiterPermissionIds = Permission::whereIn('key', $waiterPermissionKeys)->pluck('id');
        $this->waiterRole->permissions()->sync($waiterPermissionIds);

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create manager user
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'role_id' => $this->managerRole->id,
            'is_active' => true,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Set default user for authentication
        $this->user = $this->admin;
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function authenticateAs(User $user): void
    {
        $this->user = $user;
        $this->authenticate();
    }

    // ============================================
    // LIST ROLES TESTS
    // ============================================

    public function test_can_list_roles(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'key',
                        'name',
                        'is_system',
                        'is_active',
                        'permissions',
                    ]
                ]
            ]);

        // Should have at least 3 roles (admin, manager, waiter)
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_roles_list_includes_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertOk();

        $roles = $response->json('data');
        $adminRole = collect($roles)->firstWhere('key', 'admin');

        $this->assertNotNull($adminRole);
        $this->assertArrayHasKey('permissions', $adminRole);
        $this->assertIsArray($adminRole['permissions']);
    }

    public function test_roles_list_shows_active_roles_only(): void
    {
        // Create inactive role
        Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'inactive_role',
            'name' => 'Неактивная роль',
            'is_system' => false,
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertOk();

        // All returned roles should be active
        $roles = $response->json('data');
        foreach ($roles as $role) {
            $this->assertTrue($role['is_active']);
        }
    }

    public function test_roles_list_ordered_by_sort_order(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertOk();

        $roles = $response->json('data');
        $sortOrders = array_column($roles, 'sort_order');

        // Verify roles are ordered
        $sortedOrders = $sortOrders;
        sort($sortedOrders);
        $this->assertEquals($sortedOrders, $sortOrders);
    }

    public function test_waiter_can_list_roles(): void
    {
        // Roles list should be accessible without settings.roles permission (for UI)
        $this->authenticateAs($this->waiter);

        $response = $this->getJson('/api/roles');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // SHOW ROLE TESTS
    // ============================================

    public function test_can_show_role(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson("/api/roles/{$this->adminRole->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->adminRole->id,
                    'key' => 'admin',
                    'name' => 'Администратор',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'key',
                    'name',
                    'description',
                    'is_system',
                    'is_active',
                    'permissions',
                ],
            ]);
    }

    public function test_show_role_includes_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson("/api/roles/{$this->adminRole->id}");

        $response->assertOk();

        $permissions = $response->json('data.permissions');
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);

        // Check permission structure
        $firstPermission = $permissions[0];
        $this->assertArrayHasKey('id', $firstPermission);
        $this->assertArrayHasKey('key', $firstPermission);
        $this->assertArrayHasKey('name', $firstPermission);
        $this->assertArrayHasKey('group', $firstPermission);
    }

    public function test_show_nonexistent_role_returns_404(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles/99999');

        $response->assertStatus(404);
    }

    // ============================================
    // CREATE ROLE TESTS
    // ============================================

    public function test_can_create_role(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Новая роль',
            'description' => 'Описание новой роли',
            'color' => '#ff5500',
            'permissions' => ['orders.view', 'orders.create'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Роль создана',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'key',
                    'name',
                    'description',
                    'color',
                    'permissions',
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Новая роль',
            'description' => 'Описание новой роли',
            'is_system' => false,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_role_auto_generates_key(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Тестовая Роль',
        ]);

        $response->assertStatus(201);

        // Key should be auto-generated from name (transliterated)
        $role = Role::where('name', 'Тестовая Роль')->first();
        $this->assertNotNull($role);
        $this->assertNotEmpty($role->key);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $role->key);
    }

    public function test_create_role_with_custom_key(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Custom Role',
            'key' => 'custom_key',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roles', [
            'name' => 'Custom Role',
            'key' => 'custom_key',
        ]);
    }

    public function test_create_role_validates_key_format(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Test Role',
            'key' => 'Invalid-Key!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_create_role_with_duplicate_key_auto_increments(): void
    {
        // Create first role with key 'test_role'
        Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'test_role',
            'name' => 'Test Role 1',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        // Create second role with same key (should auto-increment)
        $response = $this->postJson('/api/roles', [
            'name' => 'Test Role 2',
            'key' => 'test_role',
        ]);

        $response->assertStatus(201);

        // Key should be modified to avoid conflict
        $newRole = Role::where('name', 'Test Role 2')->first();
        $this->assertNotEquals('test_role', $newRole->key);
        $this->assertStringStartsWith('test_role_', $newRole->key);
    }

    public function test_create_role_with_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с разрешениями',
            'permissions' => ['orders.view', 'orders.create', 'menu.view'],
        ]);

        $response->assertStatus(201);

        $role = Role::where('name', 'Роль с разрешениями')->first();
        $permissionKeys = $role->permissions->pluck('key')->toArray();

        $this->assertContains('orders.view', $permissionKeys);
        $this->assertContains('orders.create', $permissionKeys);
        $this->assertContains('menu.view', $permissionKeys);
    }

    public function test_create_role_with_limits(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с лимитами',
            'max_discount_percent' => 25,
            'max_refund_amount' => 5000,
            'max_cancel_amount' => 3000,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roles', [
            'name' => 'Роль с лимитами',
            'max_discount_percent' => 25,
            'max_refund_amount' => 5000,
            'max_cancel_amount' => 3000,
        ]);
    }

    public function test_create_role_with_interface_access(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с доступом',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => true,
            'can_access_delivery' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roles', [
            'name' => 'Роль с доступом',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => true,
            'can_access_delivery' => false,
        ]);
    }

    public function test_create_role_validates_required_name(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_role_validates_max_discount_percent_range(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Test Role',
            'max_discount_percent' => 150, // Invalid: > 100
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_discount_percent']);
    }

    public function test_waiter_cannot_create_role(): void
    {
        $this->authenticateAs($this->waiter);

        $response = $this->postJson('/api/roles', [
            'name' => 'Unauthorized Role',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // UPDATE ROLE TESTS
    // ============================================

    public function test_can_update_role(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'editable_role',
            'name' => 'Редактируемая роль',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'name' => 'Обновлённая роль',
            'description' => 'Новое описание',
            'color' => '#00ff00',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль обновлена',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Обновлённая роль',
            'description' => 'Новое описание',
            'color' => '#00ff00',
        ]);
    }

    public function test_can_update_role_permissions(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_for_permissions',
            'name' => 'Роль для разрешений',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'permissions' => ['staff.view', 'staff.edit', 'menu.view'],
        ]);

        $response->assertOk();

        $role->refresh();
        $permissionKeys = $role->permissions->pluck('key')->toArray();

        $this->assertContains('staff.view', $permissionKeys);
        $this->assertContains('staff.edit', $permissionKeys);
        $this->assertContains('menu.view', $permissionKeys);
    }

    public function test_can_update_role_limits(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_for_limits',
            'name' => 'Роль для лимитов',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
            'max_discount_percent' => 10,
            'max_refund_amount' => 1000,
            'max_cancel_amount' => 1000,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'max_discount_percent' => 50,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 5000,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'max_discount_percent' => 50,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 5000,
        ]);
    }

    public function test_can_update_role_interface_access(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_for_access',
            'name' => 'Роль для доступа',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
            'can_access_pos' => false,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'can_access_pos' => true,
            'can_access_kitchen' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'can_access_pos' => true,
            'can_access_kitchen' => true,
        ]);
    }

    public function test_can_deactivate_role(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'deactivatable_role',
            'name' => 'Деактивируемая роль',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);
    }

    public function test_waiter_cannot_update_role(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'test_role_update',
            'name' => 'Test Role',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->waiter);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // DELETE ROLE TESTS
    // ============================================

    public function test_can_delete_custom_role(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'deletable_role',
            'name' => 'Удаляемая роль',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль удалена',
            ]);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_cannot_delete_system_role(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->deleteJson("/api/roles/{$this->adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Системную роль нельзя удалить',
            ]);

        // Role should still exist
        $this->assertDatabaseHas('roles', [
            'id' => $this->adminRole->id,
        ]);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_with_users',
            'name' => 'Роль с пользователями',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        // Assign user to this role
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'role_with_users',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить роль с назначенными сотрудниками',
            ]);
    }

    public function test_delete_role_removes_permissions(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_with_permissions_delete',
            'name' => 'Роль с разрешениями для удаления',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        // Attach permissions
        $permissionIds = Permission::take(3)->pluck('id');
        $role->permissions()->attach($permissionIds);

        $roleId = $role->id;

        $this->authenticateAs($this->admin);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertOk();

        // Verify role_permission entries are removed
        $this->assertDatabaseMissing('role_permission', [
            'role_id' => $roleId,
        ]);
    }

    public function test_waiter_cannot_delete_role(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'test_role_delete',
            'name' => 'Test Role Delete',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->waiter);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(403);
    }

    // ============================================
    // TOGGLE ACTIVE TESTS
    // ============================================

    public function test_can_toggle_role_active_status(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'toggleable_role',
            'name' => 'Переключаемая роль',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        // Deactivate
        $response = $this->postJson("/api/roles/{$role->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль деактивирована',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);

        // Activate again
        $response = $this->postJson("/api/roles/{$role->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль активирована',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_toggle_system_role(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson("/api/roles/{$this->adminRole->id}/toggle-active");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя деактивировать системную роль',
            ]);
    }

    // ============================================
    // CLONE ROLE TESTS
    // ============================================

    public function test_can_clone_role(): void
    {
        // Add some permissions to the role
        $permissionIds = Permission::take(5)->pluck('id');
        $this->managerRole->permissions()->sync($permissionIds);

        $this->authenticateAs($this->admin);

        $response = $this->postJson("/api/roles/{$this->managerRole->id}/clone");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль скопирована',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'key',
                    'name',
                    'permissions',
                ],
            ]);

        // Verify cloned role exists
        $clonedRole = Role::where('name', 'like', '%Менеджер (копия)%')->first();
        $this->assertNotNull($clonedRole);
        $this->assertFalse($clonedRole->is_system);
        $this->assertNotEquals($this->managerRole->key, $clonedRole->key);

        // Verify permissions were copied
        $originalPermissionIds = $this->managerRole->permissions->pluck('id')->sort()->values();
        $clonedPermissionIds = $clonedRole->permissions->pluck('id')->sort()->values();
        $this->assertEquals($originalPermissionIds->toArray(), $clonedPermissionIds->toArray());
    }

    public function test_cloned_role_has_unique_key(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson("/api/roles/{$this->waiterRole->id}/clone");

        $response->assertOk();

        $clonedRole = Role::where('name', 'like', '%Официант (копия)%')->first();
        $this->assertNotNull($clonedRole);
        $this->assertStringContainsString('waiter_copy_', $clonedRole->key);
    }

    // ============================================
    // REORDER ROLES TESTS
    // ============================================

    public function test_can_reorder_roles(): void
    {
        $role1 = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_order_1',
            'name' => 'Роль 1',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $role2 = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_order_2',
            'name' => 'Роль 2',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $role3 = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_order_3',
            'name' => 'Роль 3',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $this->authenticateAs($this->admin);

        // Reorder: role3, role1, role2
        $response = $this->postJson('/api/roles/reorder', [
            'order' => [$role3->id, $role1->id, $role2->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Порядок обновлён',
            ]);

        $this->assertDatabaseHas('roles', ['id' => $role3->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('roles', ['id' => $role1->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('roles', ['id' => $role2->id, 'sort_order' => 3]);
    }

    public function test_reorder_validates_role_ids(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles/reorder', [
            'order' => [99999, 99998], // Non-existent IDs
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order.0', 'order.1']);
    }

    // ============================================
    // PERMISSIONS ENDPOINT TESTS
    // ============================================

    public function test_can_get_all_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'groups',
                    'all',
                    'interfaces',
                ],
            ]);
    }

    public function test_permissions_response_has_groups_structure(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk();

        $groups = $response->json('data.groups');
        $this->assertIsArray($groups);

        // Check that expected groups exist
        $groupKeys = array_keys($groups);
        $this->assertContains('orders', $groupKeys);
        $this->assertContains('menu', $groupKeys);
        $this->assertContains('staff', $groupKeys);
        $this->assertContains('settings', $groupKeys);
    }

    public function test_permissions_response_has_all_permissions(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk();

        $allPermissions = $response->json('data.all');
        $this->assertIsArray($allPermissions);

        // Check that some expected permissions exist
        $this->assertArrayHasKey('orders.view', $allPermissions);
        $this->assertArrayHasKey('orders.create', $allPermissions);
        $this->assertArrayHasKey('menu.view', $allPermissions);

        // Check permission structure
        $ordersView = $allPermissions['orders.view'];
        $this->assertArrayHasKey('key', $ordersView);
        $this->assertArrayHasKey('name', $ordersView);
        $this->assertArrayHasKey('group', $ordersView);
    }

    public function test_permissions_response_has_interface_options(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk();

        $interfaces = $response->json('data.interfaces');
        $this->assertIsArray($interfaces);

        // Check interface options
        $this->assertArrayHasKey('can_access_pos', $interfaces);
        $this->assertArrayHasKey('can_access_backoffice', $interfaces);
        $this->assertArrayHasKey('can_access_kitchen', $interfaces);
        $this->assertArrayHasKey('can_access_delivery', $interfaces);

        // Check structure
        $posAccess = $interfaces['can_access_pos'];
        $this->assertArrayHasKey('label', $posAccess);
        $this->assertArrayHasKey('description', $posAccess);
    }

    public function test_waiter_can_get_permissions_list(): void
    {
        // Permissions list is readable without settings.roles for UI purposes
        $this->authenticateAs($this->waiter);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // UNAUTHENTICATED TESTS
    // ============================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/roles');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $response = $this->postJson('/api/roles', [
            'name' => 'Test Role',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_permissions_returns_401(): void
    {
        $response = $this->getJson('/api/roles/permissions');

        $response->assertStatus(401);
    }

    // ============================================
    // ROLE MODEL TESTS
    // ============================================

    public function test_role_has_permission_method(): void
    {
        $this->assertTrue($this->adminRole->hasPermission('orders.view'));
        $this->assertTrue($this->adminRole->hasPermission('settings.roles'));
        $this->assertFalse($this->waiterRole->hasPermission('settings.roles'));
    }

    public function test_role_has_any_permission_method(): void
    {
        $this->assertTrue($this->adminRole->hasAnyPermission(['orders.view', 'nonexistent.permission']));
        $this->assertFalse($this->waiterRole->hasAnyPermission(['settings.roles', 'staff.delete']));
    }

    public function test_role_permissions_list_attribute(): void
    {
        $permissionsList = $this->adminRole->permissions_list;
        $this->assertIsArray($permissionsList);
        $this->assertContains('orders.view', $permissionsList);
    }

    public function test_role_users_count_attribute(): void
    {
        $usersCount = $this->adminRole->users_count;
        $this->assertIsInt($usersCount);
        $this->assertGreaterThanOrEqual(1, $usersCount); // At least the admin user
    }

    public function test_role_can_apply_discount_check(): void
    {
        // Admin has orders.discount permission and max 100%
        $this->assertTrue($this->adminRole->canApplyDiscount(50));
        $this->assertTrue($this->adminRole->canApplyDiscount(100));

        // Waiter has orders.discount but max 10%
        $this->assertTrue($this->waiterRole->canApplyDiscount(5));
        $this->assertFalse($this->waiterRole->canApplyDiscount(15));
    }

    public function test_role_can_refund_check(): void
    {
        // Admin can refund up to limit
        $this->assertTrue($this->adminRole->canRefund(50000));
        $this->assertFalse($this->adminRole->canRefund(150000));

        // Waiter cannot refund (max_refund_amount = 0)
        $this->assertFalse($this->waiterRole->canRefund(100));
    }

    public function test_role_can_cancel_order_check(): void
    {
        // Admin can cancel up to limit
        $this->assertTrue($this->adminRole->canCancelOrder(50000));
        $this->assertFalse($this->adminRole->canCancelOrder(150000));

        // Waiter cannot cancel (max_cancel_amount = 0)
        $this->assertFalse($this->waiterRole->canCancelOrder(100));
    }

    // ============================================
    // EDGE CASES
    // ============================================

    public function test_create_role_with_allowed_halls(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с залами',
            'allowed_halls' => [1, 2, 3],
        ]);

        $response->assertStatus(201);

        $role = Role::where('name', 'Роль с залами')->first();
        $this->assertEquals([1, 2, 3], $role->allowed_halls);
    }

    public function test_create_role_with_allowed_payment_methods(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с методами оплаты',
            'allowed_payment_methods' => ['cash', 'card'],
        ]);

        $response->assertStatus(201);

        $role = Role::where('name', 'Роль с методами оплаты')->first();
        $this->assertEquals(['cash', 'card'], $role->allowed_payment_methods);
    }

    public function test_create_role_with_require_manager_confirm(): void
    {
        $this->authenticateAs($this->admin);

        $response = $this->postJson('/api/roles', [
            'name' => 'Роль с подтверждением',
            'require_manager_confirm' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roles', [
            'name' => 'Роль с подтверждением',
            'require_manager_confirm' => true,
        ]);
    }

    public function test_role_can_access_hall_check(): void
    {
        $roleWithHalls = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_hall_check',
            'name' => 'Роль для проверки залов',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
            'allowed_halls' => [1, 2],
        ]);

        $this->assertTrue($roleWithHalls->canAccessHall(1));
        $this->assertTrue($roleWithHalls->canAccessHall(2));
        $this->assertFalse($roleWithHalls->canAccessHall(3));

        // Role without hall restrictions can access all halls
        $this->assertTrue($this->adminRole->canAccessHall(999));
    }

    public function test_role_can_use_payment_method_check(): void
    {
        $roleWithPayments = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_payment_check',
            'name' => 'Роль для проверки оплаты',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
            'allowed_payment_methods' => ['cash', 'card'],
        ]);

        $this->assertTrue($roleWithPayments->canUsePaymentMethod('cash'));
        $this->assertTrue($roleWithPayments->canUsePaymentMethod('card'));
        $this->assertFalse($roleWithPayments->canUsePaymentMethod('crypto'));

        // Role without payment restrictions can use all methods
        $this->assertTrue($this->adminRole->canUsePaymentMethod('any_method'));
    }

    public function test_update_role_with_allowed_halls(): void
    {
        $role = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'role_update_halls',
            'name' => 'Роль для обновления залов',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $this->authenticateAs($this->admin);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'allowed_halls' => [4, 5, 6],
        ]);

        $response->assertOk();

        $role->refresh();
        $this->assertEquals([4, 5, 6], $role->allowed_halls);
    }
}
