<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\StaffInvitation;
use App\Models\SalaryPayment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class StaffManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $manager;
    protected User $waiter;
    protected string $adminToken;
    protected string $managerToken;
    protected string $waiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with full staff management permissions
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

        // Create manager role with limited staff permissions
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

        // Create waiter role without staff management permissions
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

        // Create all needed permissions
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

        // Assign full permissions to admin role
        $adminPermissionIds = Permission::whereIn('key', $allPermissions)->pluck('id');
        $this->adminRole->permissions()->sync($adminPermissionIds);

        // Assign limited permissions to manager role (view, create, edit but not delete)
        $managerPermissionKeys = [
            'staff.view', 'staff.create', 'staff.edit',
            'menu.view', 'menu.edit',
            'orders.view', 'orders.create', 'orders.edit', 'orders.discount',
            'settings.view',
        ];
        $managerPermissionIds = Permission::whereIn('key', $managerPermissionKeys)->pluck('id');
        $this->managerRole->permissions()->sync($managerPermissionIds);

        // Assign minimal permissions to waiter role
        $waiterPermissionKeys = ['menu.view', 'orders.view', 'orders.create'];
        $waiterPermissionIds = Permission::whereIn('key', $waiterPermissionKeys)->pluck('id');
        $this->waiterRole->permissions()->sync($waiterPermissionIds);

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
            'salary_type' => 'fixed',
            'salary' => 50000,
        ]);

        // Create manager user
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'role_id' => $this->managerRole->id,
            'is_active' => true,
            'salary_type' => 'fixed',
            'salary' => 40000,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'salary_type' => 'hourly',
            'hourly_rate' => 300,
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
    }

    // ============================================
    // STAFF INDEX TESTS
    // ============================================

    public function test_can_list_staff_members(): void
    {
        // Create additional staff members
        User::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                    ]
                ]
            ]);
    }

    public function test_can_filter_staff_by_role(): void
    {
        User::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff?role=cook');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        foreach ($data as $staff) {
            $this->assertEquals('cook', $staff['role']);
        }
    }

    public function test_can_filter_staff_by_status_active(): void
    {
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => false,
            'fired_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff?status=active');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $staff) {
            $this->assertTrue($staff['is_active']);
        }
    }

    public function test_can_filter_staff_by_status_fired(): void
    {
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => false,
            'fired_at' => now(),
            'fire_reason' => 'Test reason',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff?status=fired');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $staff) {
            $this->assertNotNull($staff['fired_at']);
        }
    }

    public function test_can_search_staff_by_name(): void
    {
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Unique Test Name',
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff?search=Unique');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_unauthenticated_cannot_list_staff(): void
    {
        $response = $this->getJson('/api/staff');

        $response->assertUnauthorized();
    }

    // ============================================
    // STAFF CREATION TESTS
    // ============================================

    public function test_admin_can_create_staff_member(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'phone' => '+79001234567',
            'role' => 'waiter',
            'salary_type' => 'hourly',
            'hourly_rate' => 350,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Сотрудник создан',
            ])
            ->assertJsonPath('data.name', 'New Staff Member')
            ->assertJsonPath('data.role', 'waiter');

        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@example.com',
            'role' => 'waiter',
        ]);
    }

    public function test_can_create_staff_with_pin_code(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'PIN Staff',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'pin_code' => '1234',
        ]);

        $response->assertCreated();

        $user = User::where('name', 'PIN Staff')->first();
        $this->assertNotNull($user->pin_lookup);
        $this->assertEquals(User::hashPinForLookup('1234'), $user->pin_lookup);
    }

    public function test_cannot_create_waiter_with_duplicate_pin(): void
    {
        // Create first waiter with PIN
        $existingWaiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'pin_code' => Hash::make('5555'),
            'pin_lookup' => User::hashPinForLookup('5555'),
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'New Waiter',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'pin_code' => '5555',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_staff_creation_requires_valid_role(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'Invalid Role Staff',
            'role' => 'invalid_role',
            'salary_type' => 'fixed',
        ]);

        $response->assertStatus(422);
    }

    public function test_staff_creation_requires_valid_salary_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'Invalid Salary Staff',
            'role' => 'waiter',
            'salary_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_waiter_cannot_create_staff(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff', [
            'name' => 'Unauthorized Staff',
            'role' => 'waiter',
            'salary_type' => 'fixed',
        ]);

        $response->assertForbidden();
    }

    // ============================================
    // STAFF UPDATE TESTS
    // ============================================

    public function test_can_update_staff_member(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/staff/{$this->waiter->id}", [
            'name' => 'Updated Name',
            'phone' => '+79009876543',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Данные обновлены',
            ]);

        $this->waiter->refresh();
        $this->assertEquals('Updated Name', $this->waiter->name);
    }

    public function test_can_update_staff_salary(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/{$this->waiter->id}/salary", [
            'salary_type' => 'mixed',
            'salary' => 30000,
            'hourly_rate' => 200,
        ]);

        $response->assertOk();

        $this->waiter->refresh();
        $this->assertEquals('mixed', $this->waiter->salary_type);
    }

    // ============================================
    // PIN CODE MANAGEMENT TESTS
    // ============================================

    public function test_can_update_pin_code(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/{$this->waiter->id}/pin", [
            'pin_code' => '9999',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PIN-код обновлён',
            ]);

        $this->waiter->refresh();
        $this->assertEquals(User::hashPinForLookup('9999'), $this->waiter->pin_lookup);
    }

    public function test_cannot_update_to_duplicate_pin_for_waiter(): void
    {
        // Create another waiter with PIN
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'pin_code' => Hash::make('8888'),
            'pin_lookup' => User::hashPinForLookup('8888'),
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/{$this->waiter->id}/pin", [
            'pin_code' => '8888',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_delete_pin_code(): void
    {
        // First set a PIN
        $this->waiter->setPin('1111');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/staff/{$this->waiter->id}/pin");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PIN-код удалён',
            ]);

        $this->waiter->refresh();
        $this->assertNull($this->waiter->pin_lookup);
    }

    // ============================================
    // PASSWORD MANAGEMENT TESTS
    // ============================================

    public function test_can_update_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/{$this->waiter->id}/password", [
            'password' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Пароль обновлён',
            ]);

        $this->waiter->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->waiter->password));
    }

    public function test_password_must_be_minimum_6_characters(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/{$this->waiter->id}/password", [
            'password' => '12345',
        ]);

        $response->assertStatus(422);
    }

    // ============================================
    // STAFF DEACTIVATION (FIRE) TESTS
    // ============================================

    public function test_can_fire_staff_member(): void
    {
        $staffToFire = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);
        $staffToFire->createToken('device_token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/staff/{$staffToFire->id}/fire", [
            'reason' => 'Performance issues',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $staffToFire->refresh();
        $this->assertFalse($staffToFire->is_active);
        $this->assertNotNull($staffToFire->fired_at);
        $this->assertEquals('Performance issues', $staffToFire->fire_reason);
    }

    public function test_fire_revokes_all_tokens(): void
    {
        $staffToFire = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);
        $staffToFire->createToken('device1');
        $staffToFire->createToken('device2');

        $this->assertEquals(2, $staffToFire->tokens()->count());

        $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/staff/{$staffToFire->id}/fire");

        $this->assertEquals(0, $staffToFire->tokens()->count());
    }

    public function test_manager_cannot_fire_staff(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson("/api/staff/{$this->waiter->id}/fire");

        $response->assertForbidden();
    }

    // ============================================
    // STAFF RESTORATION TESTS
    // ============================================

    public function test_can_restore_fired_staff(): void
    {
        $firedStaff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => false,
            'fired_at' => now(),
            'fire_reason' => 'Test reason',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/staff/{$firedStaff->id}/restore");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сотрудник восстановлен',
            ]);

        $firedStaff->refresh();
        $this->assertTrue($firedStaff->is_active);
        $this->assertNull($firedStaff->fired_at);
        $this->assertNull($firedStaff->fire_reason);
    }

    // ============================================
    // INVITATION LISTING TESTS
    // ============================================

    public function test_can_list_invitations(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => StaffInvitation::generateToken(),
            'name' => 'Invited Staff',
            'email' => 'invited@example.com',
            'role' => 'waiter',
            'salary_type' => 'hourly',
            'salary_amount' => 300,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff/invitations');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'token',
                        'name',
                        'email',
                        'role',
                        'status',
                        'expires_at',
                    ]
                ]
            ]);
    }

    // ============================================
    // INVITATION CREATION TESTS
    // ============================================

    public function test_can_create_invitation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/invitations', [
            'name' => 'New Invite',
            'email' => 'newinvite@example.com',
            'role' => 'waiter',
            'salary_type' => 'hourly',
            'hourly_rate' => 400,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Приглашение создано',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'token',
                    'name',
                    'email',
                    'role',
                ],
                'invite_url',
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'email' => 'newinvite@example.com',
            'role' => 'waiter',
            'status' => 'pending',
        ]);
    }

    public function test_can_create_invitation_without_contact_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/invitations', [
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'salary_amount' => 30000,
        ]);

        $response->assertCreated();
    }

    public function test_invitation_creation_requires_valid_role(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/invitations', [
            'role' => 'super_admin',
            'salary_type' => 'fixed',
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_can_create_invitation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff/invitations', [
            'name' => 'Manager Invite',
            'role' => 'waiter',
            'salary_type' => 'hourly',
            'hourly_rate' => 300,
        ]);

        $response->assertCreated();
    }

    public function test_waiter_cannot_create_invitation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff/invitations', [
            'name' => 'Unauthorized Invite',
            'role' => 'waiter',
            'salary_type' => 'fixed',
        ]);

        $response->assertForbidden();
    }

    // ============================================
    // INVITATION RETRIEVAL BY TOKEN TESTS
    // ============================================

    public function test_can_get_invitation_by_token(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'test_token_12345',
            'name' => 'Test Person',
            'email' => 'test@example.com',
            'role' => 'waiter',
            'salary_type' => 'hourly',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // This is a public endpoint - no auth required
        $response = $this->getJson('/api/invite/test_token_12345');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Person',
                    'email' => 'test@example.com',
                    'role' => 'waiter',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_invitation(): void
    {
        $response = $this->getJson('/api/invite/nonexistent_token');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено',
            ]);
    }

    public function test_returns_410_for_expired_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'expired_token_123',
            'name' => 'Expired Person',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/invite/expired_token_123');

        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_returns_410_for_already_accepted_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'accepted_token_123',
            'name' => 'Accepted Person',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'accepted',
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
            'accepted_by' => $this->waiter->id,
        ]);

        $response = $this->getJson('/api/invite/accepted_token_123');

        $response->assertStatus(410);
    }

    // ============================================
    // INVITATION ACCEPTANCE (ONBOARDING) TESTS
    // ============================================

    public function test_can_accept_invitation_and_create_account(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'accept_test_token',
            'name' => 'New Employee',
            'email' => 'newemployee@example.com',
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'salary_type' => 'hourly',
            'salary_amount' => 0,
            'hourly_rate' => 350,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/invite/accept_test_token/accept', [
            'password' => 'securepassword123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Аккаунт активирован! Теперь вы можете войти в систему.',
            ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'newemployee@example.com',
            'name' => 'New Employee',
            'role' => 'waiter',
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Verify invitation was accepted
        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_can_accept_invitation_with_user_provided_contact(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'no_contact_token',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/invite/no_contact_token/accept', [
            'name' => 'Self Named Employee',
            'email' => 'selfnamed@example.com',
            'password' => 'mypassword123',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'selfnamed@example.com',
            'name' => 'Self Named Employee',
        ]);
    }

    public function test_cannot_accept_expired_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'expired_accept_token',
            'name' => 'Expired Employee',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->postJson('/api/invite/expired_accept_token/accept', [
            'password' => 'password123',
        ]);

        $response->assertStatus(410);
    }

    public function test_cannot_accept_cancelled_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'cancelled_token',
            'name' => 'Cancelled Employee',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'cancelled',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/invite/cancelled_token/accept', [
            'password' => 'password123',
        ]);

        $response->assertStatus(410);
    }

    public function test_accept_invitation_requires_password(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'no_password_token',
            'name' => 'Test Employee',
            'email' => 'test@test.com',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/invite/no_password_token/accept', []);

        $response->assertStatus(422);
    }

    // ============================================
    // SEND USER INVITE TESTS
    // ============================================

    public function test_can_send_invite_to_existing_user(): void
    {
        // Create user without password (simulating user created by admin without onboarding)
        $userWithoutPassword = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'email' => 'nopassword@example.com',
            'is_active' => true,
            'salary_type' => 'fixed',
        ]);

        // Set placeholder password directly in DB to bypass the 'hashed' cast
        \DB::table('users')
            ->where('id', $userWithoutPassword->id)
            ->update(['password' => '$2y$12$hash_placeholder']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/staff/{$userWithoutPassword->id}/invite");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приглашение создано',
            ]);

        // Verify invitation was created
        $this->assertDatabaseHas('staff_invitations', [
            'email' => 'nopassword@example.com',
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
        ]);
    }

    // ============================================
    // INVITATION CANCELLATION TESTS
    // ============================================

    public function test_can_cancel_invitation(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'cancel_test_token',
            'name' => 'To Be Cancelled',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/staff/invitations/{$invitation->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приглашение отменено',
            ]);

        $invitation->refresh();
        $this->assertEquals('cancelled', $invitation->status);
    }

    public function test_manager_cannot_cancel_invitation(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'manager_cancel_token',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->deleteJson("/api/staff/invitations/{$invitation->id}");

        $response->assertForbidden();
    }

    // ============================================
    // INVITATION RESEND TESTS
    // ============================================

    public function test_can_resend_invitation(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'old_token',
            'name' => 'Resend Test',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(1),
        ]);

        $oldToken = $invitation->token;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/staff/invitations/{$invitation->id}/resend");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приглашение обновлено',
            ])
            ->assertJsonStructure([
                'data' => ['token'],
                'invite_url',
            ]);

        $invitation->refresh();
        $this->assertNotEquals($oldToken, $invitation->token);
        $this->assertEquals('pending', $invitation->status);
    }

    // ============================================
    // ROLES AND PERMISSIONS TESTS
    // ============================================

    public function test_can_list_roles(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/roles');

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
                    ]
                ]
            ]);
    }

    public function test_can_list_all_permissions(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/roles/permissions');

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

    public function test_can_update_role_permissions(): void
    {
        // Create a custom role for testing
        $customRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'custom_test',
            'name' => 'Custom Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/roles/{$customRole->id}", [
            'permissions' => ['staff.view', 'menu.view', 'orders.view'],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $customRole->refresh();
        $this->assertTrue($customRole->hasPermission('staff.view'));
        $this->assertTrue($customRole->hasPermission('menu.view'));
    }

    // ============================================
    // SALARY TYPES REFERENCE DATA TESTS
    // ============================================

    public function test_can_get_salary_types(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff/salary-types');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'fixed' => 'Оклад (в месяц)',
                    'hourly' => 'Почасовая',
                    'mixed' => 'Оклад + почасовая',
                    'percent' => 'Процент от продаж',
                ],
            ]);
    }

    // ============================================
    // AVAILABLE ROLES REFERENCE DATA TESTS
    // ============================================

    public function test_can_get_available_roles(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff/available-roles');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $roles = $response->json('data');
        $this->assertArrayHasKey('waiter', $roles);
        $this->assertArrayHasKey('cook', $roles);
        $this->assertArrayHasKey('cashier', $roles);
    }

    // ============================================
    // SALARY PAYMENTS TESTS
    // ============================================

    public function test_can_list_salary_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'salary',
            'amount' => 30000,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff/salary-payments');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'type',
                        'amount',
                        'status',
                    ]
                ]
            ]);
    }

    public function test_can_filter_salary_payments_by_user(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'salary',
            'amount' => 30000,
            'status' => 'pending',
        ]);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->manager->id,
            'created_by' => $this->admin->id,
            'type' => 'bonus',
            'amount' => 5000,
            'status' => 'paid',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/staff/salary-payments?user_id={$this->waiter->id}");

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $payment) {
            $this->assertEquals($this->waiter->id, $payment['user_id']);
        }
    }

    public function test_can_filter_salary_payments_by_type(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'bonus',
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/staff/salary-payments?type=bonus');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $payment) {
            $this->assertEquals('bonus', $payment['type']);
        }
    }

    public function test_can_create_salary_payment(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/salary-payments', [
            'user_id' => $this->waiter->id,
            'type' => 'bonus',
            'amount' => 10000,
            'description' => 'Performance bonus',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Начисление создано',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'type' => 'bonus',
            'amount' => 10000,
        ]);
    }

    public function test_can_create_penalty_payment(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/salary-payments', [
            'user_id' => $this->waiter->id,
            'type' => 'penalty',
            'amount' => -1000,
            'description' => 'Late arrival',
        ]);

        $response->assertCreated();
    }

    public function test_can_update_salary_payment_status(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'salary',
            'amount' => 30000,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/salary-payments/{$payment->id}", [
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Начисление обновлено',
            ]);

        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_can_cancel_salary_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'bonus',
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/staff/salary-payments/{$payment->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk();

        $payment->refresh();
        $this->assertEquals('cancelled', $payment->status);
    }

    public function test_can_delete_pending_salary_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'bonus',
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/staff/salary-payments/{$payment->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Начисление удалено',
            ]);

        $this->assertDatabaseMissing('salary_payments', ['id' => $payment->id]);
    }

    public function test_cannot_delete_paid_salary_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'salary',
            'amount' => 30000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/staff/salary-payments/{$payment->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить выплаченное начисление',
            ]);
    }

    // ============================================
    // AUTHENTICATION REQUIREMENTS TESTS
    // ============================================

    public function test_all_staff_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/staff'],
            ['POST', '/api/staff'],
            ['GET', '/api/staff/invitations'],
            ['POST', '/api/staff/invitations'],
            ['GET', '/api/staff/salary-types'],
            ['GET', '/api/staff/available-roles'],
            ['GET', '/api/staff/salary-payments'],
            ['POST', '/api/staff/salary-payments'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertUnauthorized();
        }
    }

    public function test_public_invitation_endpoints_do_not_require_auth(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'public_test_token',
            'name' => 'Public Test',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // GET invitation by token - public
        $response = $this->getJson('/api/invite/public_test_token');
        $response->assertOk();

        // Accept invitation - public
        // Since invitation doesn't have email/phone, we need to provide at least one
        $response = $this->postJson('/api/invite/public_test_token/accept', [
            'password' => 'password123',
            'email' => 'publictest@example.com',
        ]);
        // Should work without auth (201 created)
        $response->assertCreated();
    }

    // ============================================
    // PERMISSION REQUIREMENTS TESTS
    // ============================================

    public function test_staff_view_permission_required_for_listing(): void
    {
        // Waiter doesn't have staff.view permission
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff');

        $response->assertForbidden();
    }

    public function test_staff_create_permission_required_for_creation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff', [
            'name' => 'Test',
            'role' => 'waiter',
            'salary_type' => 'fixed',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_edit_permission_required_for_updates(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->putJson("/api/staff/{$this->manager->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_delete_permission_required_for_firing(): void
    {
        // Manager has staff.view, create, edit but not delete
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson("/api/staff/{$this->waiter->id}/fire");

        $response->assertForbidden();
    }

    // ============================================
    // EDGE CASES AND ERROR HANDLING TESTS
    // ============================================

    public function test_cannot_create_staff_with_existing_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff', [
            'name' => 'Duplicate Email',
            'email' => $this->waiter->email,
            'role' => 'cook',
            'salary_type' => 'fixed',
        ]);

        $response->assertStatus(422);
    }

    public function test_salary_payment_requires_valid_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/salary-payments', [
            'user_id' => $this->waiter->id,
            'type' => 'invalid_type',
            'amount' => 1000,
        ]);

        $response->assertStatus(422);
    }

    public function test_salary_payment_requires_existing_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff/salary-payments', [
            'user_id' => 99999,
            'type' => 'bonus',
            'amount' => 1000,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_create_staff_with_all_salary_types(): void
    {
        $salaryTypes = ['fixed', 'hourly', 'mixed', 'percent'];

        foreach ($salaryTypes as $index => $type) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->postJson('/api/staff', [
                'name' => "Staff {$type}",
                'email' => "staff_{$type}_{$index}@example.com",
                'role' => 'waiter',
                'salary_type' => $type,
                'salary' => 30000,
                'hourly_rate' => 300,
                'percent_rate' => 5,
            ]);

            $response->assertCreated();
        }
    }

    public function test_can_create_staff_with_all_valid_roles(): void
    {
        $roles = ['admin', 'manager', 'waiter', 'cook', 'cashier', 'courier', 'hostess'];

        // Ensure all roles exist in the database
        $roleNames = [
            'admin' => 'Администратор',
            'manager' => 'Менеджер',
            'waiter' => 'Официант',
            'cook' => 'Повар',
            'cashier' => 'Кассир',
            'courier' => 'Курьер',
            'hostess' => 'Хостес',
        ];
        foreach ($roles as $roleKey) {
            \App\Models\Role::firstOrCreate(
                ['restaurant_id' => $this->restaurant->id, 'key' => $roleKey],
                [
                    'name' => $roleNames[$roleKey],
                    'is_system' => true,
                    'can_login' => true,
                    'can_use_pos' => true,
                    'can_use_kitchen' => false,
                    'can_manage_staff' => false,
                    'can_manage_menu' => false,
                    'can_manage_orders' => false,
                    'can_view_reports' => false,
                    'can_manage_settings' => false,
                ]
            );
        }

        foreach ($roles as $index => $role) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->postJson('/api/staff', [
                'name' => "Staff {$role}",
                'email' => "staff_{$role}_{$index}@example.com",
                'role' => $role,
                'salary_type' => 'fixed',
            ]);

            $response->assertCreated();
        }
    }
}
