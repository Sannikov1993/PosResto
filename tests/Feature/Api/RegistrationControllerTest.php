<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\StaffInvitation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class RegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'org@example.com',
            'phone' => '+79991234567',
            'plan' => Tenant::PLAN_BUSINESS,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addMonths(1),
            'is_active' => true,
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Restaurant',
            'is_main' => true,
            'is_active' => true,
        ]);

        // Create admin user who can create invitations
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    // ============================================
    // VALIDATE TOKEN TESTS
    // ============================================

    public function test_validate_token_returns_invitation_data_for_valid_token(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'valid-test-token-12345',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+79991112233',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/register/validate-token?token=valid-test-token-12345');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'John Doe',
                    'role' => 'waiter',
                    'email' => 'john@example.com',
                    'phone' => '+79991112233',
                ],
            ]);
    }

    public function test_validate_token_returns_error_when_token_not_provided(): void
    {
        $response = $this->getJson('/api/register/validate-token');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Токен не указан',
            ]);
    }

    public function test_validate_token_returns_error_when_token_empty(): void
    {
        $response = $this->getJson('/api/register/validate-token?token=');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Токен не указан',
            ]);
    }

    public function test_validate_token_returns_404_for_nonexistent_token(): void
    {
        $response = $this->getJson('/api/register/validate-token?token=nonexistent-token');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено или уже использовано',
            ]);
    }

    public function test_validate_token_returns_404_for_already_accepted_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'accepted-token',
            'name' => 'Jane Doe',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_ACCEPTED,
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        $response = $this->getJson('/api/register/validate-token?token=accepted-token');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено или уже использовано',
            ]);
    }

    public function test_validate_token_returns_error_for_expired_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'expired-token',
            'name' => 'Expired User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->subDays(1), // Expired yesterday
        ]);

        $response = $this->getJson('/api/register/validate-token?token=expired-token');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия приглашения истек',
            ]);
    }

    public function test_validate_token_returns_404_for_cancelled_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'cancelled-token',
            'name' => 'Cancelled User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_CANCELLED,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/register/validate-token?token=cancelled-token');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено или уже использовано',
            ]);
    }

    public function test_validate_token_includes_role_label(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'role-label-token',
            'name' => 'Staff Member',
            'role' => 'cook',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/register/validate-token?token=role-label-token');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'name',
                    'role',
                    'role_label',
                    'email',
                    'phone',
                ],
            ]);

        // role_label should be the human-readable version
        $this->assertNotEmpty($response->json('data.role_label'));
    }

    // ============================================
    // REGISTER STAFF TESTS
    // ============================================

    public function test_can_register_staff_with_valid_invitation(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'register-token',
            'name' => 'New Staff Member',
            'email' => null,
            'phone' => '+79991234567',
            'role' => 'waiter',
            'salary_type' => 'fixed',
            'salary_amount' => 50000,
            'hourly_rate' => null,
            'percent_rate' => null,
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'register-token',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Регистрация успешна! Добро пожаловать в команду!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'login',
                        'role',
                        'role_label',
                        'email',
                        'avatar',
                        'has_password',
                        'has_pin',
                    ],
                    'token',
                ],
            ]);

        // Verify user was created with correct data
        $this->assertDatabaseHas('users', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'phone' => '+79991234567',
            'role' => 'waiter',
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'invitation_id' => $invitation->id,
            'salary_type' => 'fixed',
            'salary' => 50000,
        ]);

        // Verify invitation was marked as accepted
        $invitation->refresh();
        $this->assertEquals(StaffInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertNotNull($invitation->accepted_by);
    }

    public function test_register_creates_api_token(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'token-test',
            'name' => 'Token Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'token-test',
            'email' => 'tokentest@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Verify token is returned and is not empty
        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify token is valid (can be used for authentication)
        $user = User::where('email', 'tokentest@example.com')->first();
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'cabinet',
        ]);
    }

    public function test_register_updates_last_login_at(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'login-time-test',
            'name' => 'Login Time User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'login-time-test',
            'email' => 'logintime@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $user = User::where('email', 'logintime@example.com')->first();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_register_with_avatar(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'avatar-test',
            'name' => 'Avatar Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $avatarUrl = 'https://example.com/avatar.jpg';

        $response = $this->postJson('/api/register', [
            'token' => 'avatar-test',
            'email' => 'avatartest@example.com',
            'password' => 'password123',
            'avatar' => $avatarUrl,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'avatartest@example.com',
            'avatar' => $avatarUrl,
        ]);
    }

    public function test_register_copies_salary_fields_from_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'salary-test',
            'name' => 'Salary Test User',
            'role' => 'waiter',
            'salary_type' => 'mixed',
            'salary_amount' => 30000,
            'hourly_rate' => 500,
            'percent_rate' => 5,
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'salary-test',
            'email' => 'salarytest@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'salarytest@example.com',
            'salary_type' => 'mixed',
            'salary' => 30000,
            'hourly_rate' => 500,
            'percent_rate' => 5,
        ]);
    }

    public function test_register_copies_role_id_from_invitation(): void
    {
        // Create a custom role first
        $customRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'custom_waiter',
            'name' => 'Custom Waiter Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'role-id-test',
            'name' => 'Role ID Test User',
            'role' => 'waiter',
            'role_id' => $customRole->id, // Use the created role's ID
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'role-id-test',
            'email' => 'roleidtest@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'roleidtest@example.com',
            'role_id' => $customRole->id,
        ]);
    }

    // ============================================
    // REGISTER VALIDATION TESTS
    // ============================================

    public function test_register_validates_required_token(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_register_validates_required_email(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'email-required-test',
            'name' => 'Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'email-required-test',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_email_format(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'email-format-test',
            'name' => 'Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'email-format-test',
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_unique_email(): void
    {
        // Create existing user with this email
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'unique-email-test',
            'name' => 'Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'unique-email-test',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_required_password(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'password-required-test',
            'name' => 'Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'password-required-test',
            'email' => 'test@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_validates_password_minimum_length(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'password-length-test',
            'name' => 'Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'password-length-test',
            'email' => 'test@example.com',
            'password' => '12345', // Only 5 characters, needs 6
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ============================================
    // REGISTER ERROR HANDLING TESTS
    // ============================================

    public function test_register_returns_404_for_invalid_token(): void
    {
        $response = $this->postJson('/api/register', [
            'token' => 'nonexistent-token',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено',
            ]);
    }

    public function test_register_returns_error_for_expired_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'expired-register-token',
            'name' => 'Expired User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->subDays(1), // Expired
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'expired-register-token',
            'email' => 'expired@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия приглашения истек',
            ]);
    }

    public function test_register_returns_404_for_already_used_invitation(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'used-token',
            'name' => 'Already Registered',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_ACCEPTED,
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'used-token',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Приглашение не найдено',
            ]);
    }

    public function test_register_password_is_properly_hashed(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'hash-test',
            'name' => 'Hash Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $plainPassword = 'mysecretpassword';

        $response = $this->postJson('/api/register', [
            'token' => 'hash-test',
            'email' => 'hashtest@example.com',
            'password' => $plainPassword,
        ]);

        $response->assertOk();

        $user = User::where('email', 'hashtest@example.com')->first();

        // Password should be hashed, not stored as plain text
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function test_register_sets_login_as_email(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'login-test',
            'name' => 'Login Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'login-test',
            'email' => 'logintest@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'logintest@example.com',
            'login' => 'logintest@example.com',
        ]);
    }

    // ============================================
    // REGISTER DIFFERENT ROLES TESTS
    // ============================================

    public function test_can_register_as_cook(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'cook-token',
            'name' => 'Cook User',
            'role' => 'cook',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'cook-token',
            'email' => 'cook@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'cook@example.com',
            'role' => 'cook',
        ]);
    }

    public function test_can_register_as_cashier(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'cashier-token',
            'name' => 'Cashier User',
            'role' => 'cashier',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'cashier-token',
            'email' => 'cashier@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'cashier@example.com',
            'role' => 'cashier',
        ]);
    }

    public function test_can_register_as_courier(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'courier-token',
            'name' => 'Courier User',
            'role' => 'courier',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'courier-token',
            'email' => 'courier@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'courier@example.com',
            'role' => 'courier',
        ]);
    }

    public function test_can_register_as_manager(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'manager-token',
            'name' => 'Manager User',
            'role' => 'manager',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'manager-token',
            'email' => 'manager@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_can_register_as_hostess(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'hostess-token',
            'name' => 'Hostess User',
            'role' => 'hostess',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'hostess-token',
            'email' => 'hostess@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'hostess@example.com',
            'role' => 'hostess',
        ]);
    }

    // ============================================
    // TENANT REGISTRATION TESTS
    // ============================================

    public function test_can_register_new_tenant(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'New Restaurant Chain',
            'restaurant_name' => 'First Location',
            'owner_name' => 'John Owner',
            'email' => 'john@newrestaurant.com',
            'phone' => '+79998887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Регистрация успешна! Добро пожаловать в MenuLab!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tenant' => ['id', 'name', 'plan', 'trial_ends_at'],
                    'restaurant' => ['id', 'name'],
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ]);

        // Verify tenant was created
        $this->assertDatabaseHas('tenants', [
            'name' => 'New Restaurant Chain',
            'email' => 'john@newrestaurant.com',
            'plan' => Tenant::PLAN_TRIAL,
            'is_active' => true,
        ]);
    }

    public function test_tenant_registration_creates_trial_period(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Trial Test Org',
            'owner_name' => 'Trial Owner',
            'email' => 'trial@testorg.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::where('email', 'trial@testorg.com')->first();

        // Verify trial period is set
        $this->assertEquals(Tenant::PLAN_TRIAL, $tenant->plan);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());

        // Trial should be approximately 14 days from now
        $expectedTrialEnd = now()->addDays(14);
        $this->assertTrue(
            $tenant->trial_ends_at->diffInDays($expectedTrialEnd) <= 1,
            'Trial period should be approximately 14 days'
        );
    }

    public function test_tenant_registration_creates_main_restaurant(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Restaurant Test Org',
            'restaurant_name' => 'My First Restaurant',
            'owner_name' => 'Restaurant Owner',
            'email' => 'restaurant@testorg.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        // Verify restaurant was created
        $this->assertDatabaseHas('restaurants', [
            'name' => 'My First Restaurant',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    public function test_tenant_registration_uses_organization_name_if_no_restaurant_name(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Default Restaurant Org',
            'owner_name' => 'Default Owner',
            'email' => 'default@testorg.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        // Restaurant name should default to organization name
        $this->assertDatabaseHas('restaurants', [
            'name' => 'Default Restaurant Org',
            'is_main' => true,
        ]);
    }

    public function test_tenant_registration_creates_owner_user(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Owner Test Org',
            'owner_name' => 'The Owner',
            'email' => 'owner@testorg.com',
            'phone' => '+79991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        // Verify owner user was created
        $this->assertDatabaseHas('users', [
            'name' => 'The Owner',
            'email' => 'owner@testorg.com',
            'phone' => '+79991234567',
            'role' => User::ROLE_OWNER,
            'is_tenant_owner' => true,
            'is_active' => true,
        ]);
    }

    public function test_tenant_registration_returns_auth_token(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Token Test Org',
            'owner_name' => 'Token Owner',
            'email' => 'token@testorg.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Token should be usable for authentication
        $user = User::where('email', 'token@testorg.com')->first();
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'web',
        ]);
    }

    // ============================================
    // TENANT REGISTRATION VALIDATION TESTS
    // ============================================

    public function test_tenant_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/register/tenant', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'organization_name',
                'owner_name',
                'email',
                'password',
            ]);
    }

    public function test_tenant_registration_validates_email_format(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_registration_validates_unique_email_in_users(): void
    {
        // Create existing user with this email
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_registration_validates_unique_email_in_tenants(): void
    {
        // Email already exists in tenants table
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Duplicate Org',
            'owner_name' => 'Test Owner',
            'email' => 'org@example.com', // Already used by $this->tenant
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_tenant_registration_validates_password_confirmation(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_tenant_registration_validates_password_minimum_length(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'short@example.com',
            'password' => '12345', // Only 5 characters
            'password_confirmation' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_tenant_registration_validates_organization_name_max_length(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => str_repeat('a', 300), // Too long
            'owner_name' => 'Test Owner',
            'email' => 'toolong@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_name']);
    }

    // ============================================
    // TENANT REGISTRATION EDGE CASES
    // ============================================

    public function test_tenant_registration_generates_unique_slug(): void
    {
        // Create first tenant
        $this->postJson('/api/register/tenant', [
            'organization_name' => 'Same Name Org',
            'owner_name' => 'First Owner',
            'email' => 'first@samename.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Create second tenant with same organization name
        $this->postJson('/api/register/tenant', [
            'organization_name' => 'Same Name Org',
            'owner_name' => 'Second Owner',
            'email' => 'second@samename.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Both tenants should exist with different slugs
        $tenants = Tenant::where('name', 'Same Name Org')->get();
        $this->assertCount(2, $tenants);

        $slugs = $tenants->pluck('slug')->toArray();
        $this->assertCount(2, array_unique($slugs)); // All slugs should be unique
    }

    public function test_tenant_registration_is_public_endpoint(): void
    {
        // Registration should not require authentication
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Public Registration Org',
            'owner_name' => 'Public Owner',
            'email' => 'public@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Should not return 401 Unauthorized
        $this->assertNotEquals(401, $response->status());
    }

    public function test_tenant_registration_handles_optional_phone(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'No Phone Org',
            'owner_name' => 'No Phone Owner',
            'email' => 'nophone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // phone is not provided
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tenants', [
            'name' => 'No Phone Org',
            'phone' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'nophone@example.com',
            'phone' => null,
        ]);
    }

    public function test_tenant_registration_handles_optional_restaurant_name(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Optional Restaurant Org',
            'owner_name' => 'Optional Owner',
            'email' => 'optional@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // restaurant_name is not provided
        ]);

        $response->assertStatus(201);

        // Should use organization_name as restaurant name
        $this->assertDatabaseHas('restaurants', [
            'name' => 'Optional Restaurant Org',
        ]);
    }

    // ============================================
    // REGISTRATION TRANSACTION TESTS
    // ============================================

    public function test_staff_registration_is_atomic(): void
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'atomic-test',
            'name' => 'Atomic Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        // First registration should succeed
        $response = $this->postJson('/api/register', [
            'token' => 'atomic-test',
            'email' => 'atomic@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Invitation should be marked as accepted
        $invitation->refresh();
        $this->assertEquals(StaffInvitation::STATUS_ACCEPTED, $invitation->status);

        // User should be created
        $this->assertDatabaseHas('users', [
            'email' => 'atomic@example.com',
        ]);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_staff_registration_does_not_require_authentication(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'no-auth-test',
            'name' => 'No Auth User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        // Request without authentication should still work
        $response = $this->postJson('/api/register', [
            'token' => 'no-auth-test',
            'email' => 'noauth@example.com',
            'password' => 'password123',
        ]);

        // Should not return 401 Unauthorized
        $this->assertNotEquals(401, $response->status());
    }

    public function test_validate_token_does_not_require_authentication(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'validate-no-auth',
            'name' => 'Validate No Auth',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        // Request without authentication should still work
        $response = $this->getJson('/api/register/validate-token?token=validate-no-auth');

        // Should not return 401 Unauthorized
        $this->assertNotEquals(401, $response->status());
        $response->assertOk();
    }

    // ============================================
    // INVITATION EXPIRY EDGE CASES
    // ============================================

    public function test_invitation_exactly_at_expiry_time_is_expired(): void
    {
        // Create invitation that expires right now
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'exact-expiry-token',
            'name' => 'Exact Expiry User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->subSecond(), // Just expired
        ]);

        $response = $this->getJson('/api/register/validate-token?token=exact-expiry-token');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия приглашения истек',
            ]);
    }

    public function test_invitation_with_null_expiry_is_valid(): void
    {
        // Some invitations might not have expiry date
        $invitation = StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'no-expiry-token',
            'name' => 'No Expiry User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => null, // No expiry
        ]);

        $response = $this->getJson('/api/register/validate-token?token=no-expiry-token');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    // ============================================
    // RESPONSE STRUCTURE TESTS
    // ============================================

    public function test_successful_registration_returns_user_without_password(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'no-password-response',
            'name' => 'Response Test User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'no-password-response',
            'email' => 'response@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        // User data should not contain password
        $userData = $response->json('data.user');
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('pin_code', $userData);
    }

    public function test_registration_response_indicates_has_password(): void
    {
        StaffInvitation::create([
            'restaurant_id' => $this->restaurant->id,
            'created_by' => $this->admin->id,
            'token' => 'has-password-test',
            'name' => 'Has Password User',
            'role' => 'waiter',
            'status' => StaffInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/register', [
            'token' => 'has-password-test',
            'email' => 'haspassword@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Should indicate that user has password
        $this->assertTrue($response->json('data.user.has_password'));
        $this->assertFalse($response->json('data.user.has_pin'));
    }
}
