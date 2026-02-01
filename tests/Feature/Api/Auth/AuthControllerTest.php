<?php

namespace Tests\Feature\Api\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\DeviceSession;
use App\Models\WorkSession;
use App\Services\DeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'tenant@test.com',
            'is_active' => true,
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // =========================================================================
    // LOGIN BY EMAIL/PASSWORD TESTS
    // =========================================================================

    public function test_can_login_with_valid_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Добро пожаловать!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role', 'restaurant_id'],
                    'token',
                    'permissions',
                    'limits',
                    'interface_access',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_can_login_with_login_field_instead_of_email(): void
    {
        $user = User::factory()->create([
            'email' => 'login-user@test.com',
            'password' => Hash::make('secret'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'login-user@test.com',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_login_with_phone_number(): void
    {
        $user = User::factory()->create([
            'email' => 'phone-user@test.com',
            'phone' => '+79001234567',
            'password' => Hash::make('phonepass'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '+79001234567',
            'password' => 'phonepass',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong@test.com',
            'password' => Hash::make('correct'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@test.com',
            'password' => 'incorrect',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Неверный логин или пароль',
            ]);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'anypassword',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Неверный логин или пароль',
            ]);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@test.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Неверный логин или пароль',
            ]);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'timestamp@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'timestamp@test.com',
            'password' => 'password',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    // =========================================================================
    // LOGIN BY PIN TESTS
    // =========================================================================

    public function test_can_login_by_pin_for_pos_terminal(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'restaurant_id' => $this->restaurant->id,
            'app_type' => 'pos',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token', 'permissions'],
            ]);
    }

    public function test_can_login_by_pin_with_user_id(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('5678'),
            'pin_lookup' => '5678',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '5678',
            'user_id' => $user->id,
            'app_type' => 'pos',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_pin_login_fails_with_wrong_pin(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '9999',
            'restaurant_id' => $this->restaurant->id,
            'app_type' => 'pos',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ]);
    }

    public function test_pin_login_requires_device_token_for_waiter_app(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'restaurant_id' => $this->restaurant->id,
            'app_type' => 'waiter',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'device_not_authorized',
                'require_full_login' => true,
            ]);
    }

    public function test_pin_login_validates_pin_format(): void
    {
        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '12',
            'app_type' => 'pos',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_pin_login_requires_active_shift_for_waiter(): void
    {
        $user = User::factory()->create([
            'id' => 2,
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => '1234',
            'is_active' => true,
            'role' => 'waiter',
        ]);

        $deviceSession = DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'test-fingerprint',
            'app_type' => 'waiter',
            'token' => 'valid-device-token',
            'expires_at' => now()->addDays(30),
        ]);

        $this->mock(DeviceSessionService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserByToken')
                ->with('valid-device-token')
                ->andReturn($user);
        });

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'restaurant_id' => $this->restaurant->id,
            'app_type' => 'waiter',
            'device_token' => 'valid-device-token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'no_active_shift',
            ]);
    }

    // =========================================================================
    // TOKEN CHECK TESTS
    // =========================================================================

    public function test_check_validates_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/check');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'restaurant_id'],
                    'permissions',
                    'limits',
                    'interface_access',
                ],
            ]);
    }

    public function test_check_fails_without_token(): void
    {
        $response = $this->getJson('/api/auth/check');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Токен не предоставлен',
            ]);
    }

    public function test_check_fails_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/auth/check');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Недействительный токен',
            ]);
    }

    public function test_check_fails_for_inactive_user(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $user->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/check');

        // Middleware CheckUserActive returns 403 for deactivated users
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Ваш доступ заблокирован. Обратитесь к администратору.',
                'reason' => 'user_deactivated',
            ]);
    }

    public function test_check_accepts_x_auth_token_header(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('X-Auth-Token', $token)
            ->getJson('/api/auth/check');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // =========================================================================
    // LOGOUT TESTS
    // =========================================================================

    public function test_can_logout(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вы вышли из системы',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_works_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вы вышли из системы',
            ]);
    }

    // =========================================================================
    // GET USERS LIST TESTS
    // =========================================================================

    public function test_can_get_users_list(): void
    {
        User::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/auth/users?restaurant_id=' . $this->restaurant->id);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'role', 'avatar'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    // =========================================================================
    // CHANGE PIN TESTS
    // =========================================================================

    public function test_can_change_pin(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/change-pin', [
                'current_pin' => '1234',
                'new_pin' => '5678',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PIN-код изменён',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('5678', $user->pin_code));
        $this->assertEquals('5678', $user->pin_lookup);
    }

    public function test_change_pin_fails_with_wrong_current_pin(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/change-pin', [
                'current_pin' => '9999',
                'new_pin' => '5678',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Неверный текущий PIN',
            ]);
    }

    public function test_change_pin_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/change-pin', [
            'current_pin' => '1234',
            'new_pin' => '5678',
        ]);

        $response->assertUnauthorized();
    }

    public function test_change_pin_validates_new_pin_format(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/change-pin', [
                'current_pin' => '1234',
                'new_pin' => '12',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_pin']);
    }

    public function test_waiter_cannot_use_duplicate_pin(): void
    {
        $existingUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_lookup' => '5678',
            'is_active' => true,
        ]);

        $waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin_code' => Hash::make('1234'),
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $token = $waiter->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/change-pin', [
                'current_pin' => '1234',
                'new_pin' => '5678',
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Этот PIN-код уже используется другим официантом. Выберите другой.',
            ]);
    }

    // =========================================================================
    // FORGOT PASSWORD TESTS
    // =========================================================================

    public function test_forgot_password_returns_success_for_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'forgot@test.com',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'contact' => 'forgot@test.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Если аккаунт существует, мы отправили ссылку для сброса пароля',
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'forgot@test.com',
        ]);
    }

    public function test_forgot_password_returns_success_for_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'contact' => 'nonexistent@test.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Если аккаунт существует, мы отправили ссылку для сброса пароля',
            ]);
    }

    public function test_forgot_password_validates_contact_required(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact']);
    }

    // =========================================================================
    // CHECK RESET TOKEN TESTS
    // =========================================================================

    public function test_check_reset_token_validates_token(): void
    {
        $token = 'valid-reset-token-123';

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@test.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/check-reset-token', [
            'token' => $token,
            'email' => 'reset@test.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Токен действителен',
            ]);
    }

    public function test_check_reset_token_fails_for_invalid_token(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@test.com',
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/check-reset-token', [
            'token' => 'wrong-token',
            'email' => 'reset@test.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ]);
    }

    public function test_check_reset_token_fails_for_expired_token(): void
    {
        $token = 'expired-token';

        DB::table('password_reset_tokens')->insert([
            'email' => 'expired@test.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/auth/check-reset-token', [
            'token' => $token,
            'email' => 'expired@test.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия ссылки истёк. Запросите новую.',
            ]);
    }

    // =========================================================================
    // RESET PASSWORD TESTS
    // =========================================================================

    public function test_can_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-pass@test.com',
            'password' => Hash::make('oldpassword'),
            'is_active' => true,
        ]);

        $token = 'reset-token-456';

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset-pass@test.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset-pass@test.com',
            'password' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Пароль успешно изменён! Теперь вы можете войти в систему.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset-pass@test.com',
        ]);
    }

    public function test_reset_password_validates_minimum_length(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'any-token',
            'email' => 'any@test.com',
            'password' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_fails_for_invalid_token(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@test.com',
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'wrong-token',
            'email' => 'reset@test.com',
            'password' => 'newpassword',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Недействительная ссылка сброса пароля',
            ]);
    }

    // =========================================================================
    // LOGIN WITH DEVICE TESTS
    // =========================================================================

    public function test_can_login_with_device(): void
    {
        $user = User::factory()->create([
            'email' => 'device@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login-device', [
            'login' => 'device@test.com',
            'password' => 'password',
            'device_fingerprint' => 'abc123',
            'app_type' => 'pos',
            'remember_device' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Добро пожаловать!',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'role', 'email', 'avatar'],
                    'token',
                ],
            ]);
    }

    public function test_login_with_device_validates_app_type(): void
    {
        $response = $this->postJson('/api/auth/login-device', [
            'login' => 'user@test.com',
            'password' => 'password',
            'device_fingerprint' => 'abc123',
            'app_type' => 'invalid_app',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['app_type']);
    }

    public function test_login_with_device_requires_active_shift_for_waiter(): void
    {
        $user = User::factory()->create([
            'id' => 2,
            'email' => 'waiter@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
        ]);

        $response = $this->postJson('/api/auth/login-device', [
            'login' => 'waiter@test.com',
            'password' => 'password',
            'device_fingerprint' => 'abc123',
            'app_type' => 'waiter',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'reason' => 'no_active_shift',
            ]);
    }

    public function test_login_with_device_creates_device_session_when_remember(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login-device', [
            'login' => 'remember@test.com',
            'password' => 'password',
            'device_fingerprint' => 'fingerprint-123',
            'app_type' => 'pos',
            'remember_device' => true,
            'device_name' => 'Test POS Terminal',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['device_token'],
            ]);

        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $user->id,
            'device_fingerprint' => 'fingerprint-123',
            'app_type' => 'pos',
        ]);
    }

    // =========================================================================
    // DEVICE LOGIN (AUTO-LOGIN) TESTS
    // =========================================================================

    public function test_can_device_login(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
        ]);

        $deviceSession = DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'test-fp',
            'app_type' => 'pos',
            'token' => 'device-token-xyz',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/auth/device-login', [
            'device_token' => 'device-token-xyz',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'role', 'email', 'avatar'],
                    'token',
                ],
            ]);
    }

    public function test_device_login_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/device-login', [
            'device_token' => 'invalid-device-token',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'reason' => 'invalid_device_token',
            ]);
    }

    public function test_device_login_fails_with_expired_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'test-fp',
            'app_type' => 'pos',
            'token' => 'expired-device-token',
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->postJson('/api/auth/device-login', [
            'device_token' => 'expired-device-token',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'reason' => 'invalid_device_token',
            ]);
    }

    // =========================================================================
    // DEVICE USERS TESTS
    // =========================================================================

    public function test_can_get_device_users(): void
    {
        $user1 = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'role' => 'admin',
        ]);

        $user2 = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'role' => 'cashier',
        ]);

        DeviceSession::create([
            'user_id' => $user1->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'shared-terminal',
            'app_type' => 'pos',
            'token' => 'token1',
            'expires_at' => now()->addDays(30),
        ]);

        DeviceSession::create([
            'user_id' => $user2->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'shared-terminal',
            'app_type' => 'pos',
            'token' => 'token2',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/auth/device-users?device_fingerprint=shared-terminal&app_type=pos');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'role', 'role_label', 'avatar', 'has_pin'],
                ],
            ]);
    }

    // =========================================================================
    // LOGOUT DEVICE TESTS
    // =========================================================================

    public function test_can_logout_device(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $apiToken = $user->createToken('test')->plainTextToken;

        $deviceSession = DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp',
            'app_type' => 'pos',
            'token' => 'device-to-revoke',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $apiToken)
            ->postJson('/api/auth/logout-device', [
                'device_token' => 'device-to-revoke',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вы вышли из системы',
            ]);

        $this->assertDatabaseMissing('device_sessions', [
            'token' => 'device-to-revoke',
        ]);
    }

    // =========================================================================
    // DEVICE SESSIONS MANAGEMENT TESTS (AUTHENTICATED)
    // =========================================================================

    public function test_can_get_device_sessions(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp1',
            'device_name' => 'Terminal 1',
            'app_type' => 'pos',
            'token' => 'session1',
            'expires_at' => now()->addDays(30),
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp2',
            'device_name' => 'Terminal 2',
            'app_type' => 'pos',
            'token' => 'session2',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/auth/device-sessions');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'device_name', 'device_fingerprint', 'app_type', 'last_activity_at', 'created_at'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_revoke_device_session(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $session = DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp',
            'app_type' => 'pos',
            'token' => 'to-revoke',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/auth/device-sessions/' . $session->id);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сессия отозвана',
            ]);

        $this->assertDatabaseMissing('device_sessions', ['id' => $session->id]);
    }

    public function test_cannot_revoke_other_users_session(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $otherUser = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $session = DeviceSession::create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp',
            'app_type' => 'pos',
            'token' => 'other-session',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/auth/device-sessions/' . $session->id);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Сессия не найдена',
            ]);
    }

    public function test_can_revoke_all_device_sessions(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp1',
            'app_type' => 'pos',
            'token' => 's1',
            'expires_at' => now()->addDays(30),
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp2',
            'app_type' => 'pos',
            'token' => 's2',
            'expires_at' => now()->addDays(30),
        ]);

        DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'device_fingerprint' => 'fp3',
            'app_type' => 'waiter',
            'token' => 'current-token',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/device-sessions/revoke-all', [
                'device_token' => 'current-token',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'deleted_count' => 2,
            ]);

        $this->assertDatabaseHas('device_sessions', ['token' => 'current-token']);
        $this->assertDatabaseMissing('device_sessions', ['token' => 's1']);
        $this->assertDatabaseMissing('device_sessions', ['token' => 's2']);
    }

    // =========================================================================
    // SETUP STATUS TESTS
    // =========================================================================

    public function test_setup_status_returns_needs_setup_true_when_no_users(): void
    {
        User::query()->delete();

        $response = $this->getJson('/api/auth/setup-status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'needs_setup' => true,
            ]);
    }

    public function test_setup_status_returns_needs_setup_false_when_users_exist(): void
    {
        User::factory()->create();

        $response = $this->getJson('/api/auth/setup-status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'needs_setup' => false,
            ]);
    }

    // =========================================================================
    // INITIAL SETUP TESTS
    // =========================================================================

    public function test_can_perform_initial_setup(): void
    {
        User::query()->delete();
        Restaurant::query()->delete();
        Tenant::query()->delete();

        $response = $this->postJson('/api/auth/setup', [
            'restaurant_name' => 'My Restaurant',
            'owner_name' => 'John Owner',
            'email' => 'owner@restaurant.com',
            'phone' => '+79001234567',
            'password' => 'secretpassword',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Система настроена! Добро пожаловать!',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role', 'restaurant_id'],
                    'token',
                    'permissions',
                    'limits',
                    'interface_access',
                ],
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'My Restaurant',
            'email' => 'owner@restaurant.com',
        ]);

        $this->assertDatabaseHas('restaurants', [
            'name' => 'My Restaurant',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Owner',
            'email' => 'owner@restaurant.com',
            'role' => 'owner',
            'is_tenant_owner' => true,
        ]);
    }

    public function test_setup_fails_when_system_already_configured(): void
    {
        User::factory()->create();

        $response = $this->postJson('/api/auth/setup', [
            'restaurant_name' => 'Another Restaurant',
            'owner_name' => 'Another Owner',
            'email' => 'another@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Система уже настроена',
            ]);
    }

    public function test_setup_validates_required_fields(): void
    {
        User::query()->delete();

        $response = $this->postJson('/api/auth/setup', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_name', 'owner_name', 'email', 'password']);
    }

    public function test_setup_validates_email_format(): void
    {
        User::query()->delete();

        $response = $this->postJson('/api/auth/setup', [
            'restaurant_name' => 'Restaurant',
            'owner_name' => 'Owner',
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_setup_validates_password_minimum_length(): void
    {
        User::query()->delete();

        $response = $this->postJson('/api/auth/setup', [
            'restaurant_name' => 'Restaurant',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
