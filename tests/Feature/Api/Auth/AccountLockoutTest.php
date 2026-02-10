<?php

namespace Tests\Feature\Api\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AccountLockoutTest extends TestCase
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

    // ===== LOGIN (email/password) =====

    public function test_login_increments_failed_attempts_on_wrong_password(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 0,
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->failed_login_attempts);
    }

    public function test_login_locks_account_after_5_failed_attempts(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 4,
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $user->refresh();
        $this->assertEquals(5, $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_locked_account_returns_423_with_time_remaining(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(423);
        $response->assertJsonStructure(['locked_until']);
    }

    public function test_login_resets_failed_attempts_on_success(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 3,
            'locked_until' => null,
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_lockout_expires_after_configured_time(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(), // Expired 1 minute ago
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    // ===== PIN LOGIN =====

    public function test_pin_login_checks_lockout_when_user_id_provided(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => User::hashPinForLookup('1234'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login-pin', [
            'pin' => '1234',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(423);
        $response->assertJsonStructure(['locked_until']);
    }

    public function test_pin_login_increments_failed_attempts_on_wrong_pin(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'pin_code' => Hash::make('1234'),
            'pin_lookup' => User::hashPinForLookup('1234'),
            'failed_login_attempts' => 0,
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->postJson('/api/auth/login-pin', [
            'pin' => '9999',
            'user_id' => $user->id,
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->failed_login_attempts);
    }

    // ===== DEVICE LOGIN =====

    public function test_device_login_checks_lockout(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login-device', [
            'login' => $user->email,
            'password' => 'correct-password',
            'device_fingerprint' => 'test-fingerprint-123',
            'app_type' => 'pos',
        ]);

        $response->assertStatus(423);
        $response->assertJsonStructure(['locked_until']);
    }

    public function test_device_login_increments_on_failure(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
            'password' => Hash::make('correct-password'),
            'failed_login_attempts' => 0,
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->postJson('/api/auth/login-device', [
            'login' => $user->email,
            'password' => 'wrong-password',
            'device_fingerprint' => 'test-fingerprint-123',
            'app_type' => 'pos',
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->failed_login_attempts);
    }

    // ===== SECURITY =====

    public function test_lockout_does_not_reveal_user_existence(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);

        // Non-existent user should still get 401, not 423
        $response->assertStatus(401);
        $response->assertJsonMissing(['locked_until']);
    }
}
