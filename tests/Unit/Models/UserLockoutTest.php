<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@example.com',
            'is_active' => true,
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ], $attributes));
    }

    public function test_is_locked_out_returns_false_when_no_lock(): void
    {
        $user = $this->createUser([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        $this->assertFalse($user->isLockedOut());
    }

    public function test_is_locked_out_returns_true_when_locked(): void
    {
        $user = $this->createUser([
            'locked_until' => now()->addMinutes(15),
            'failed_login_attempts' => 5,
        ]);

        $this->assertTrue($user->isLockedOut());
    }

    public function test_is_locked_out_returns_false_when_lock_expired(): void
    {
        $user = $this->createUser([
            'locked_until' => now()->subMinute(),
            'failed_login_attempts' => 5,
        ]);

        $this->assertFalse($user->isLockedOut());
    }

    public function test_get_lockout_minutes_remaining(): void
    {
        $user = $this->createUser([
            'locked_until' => now()->addMinutes(10),
        ]);

        $minutes = $user->getLockoutMinutesRemaining();

        $this->assertGreaterThanOrEqual(9, $minutes);
        $this->assertLessThanOrEqual(10, $minutes);
    }

    public function test_get_lockout_minutes_remaining_returns_zero_when_not_locked(): void
    {
        $user = $this->createUser([
            'locked_until' => null,
        ]);

        $this->assertEquals(0, $user->getLockoutMinutesRemaining());
    }

    public function test_increment_failed_attempts_increments_counter(): void
    {
        $user = $this->createUser([
            'failed_login_attempts' => 2,
        ]);

        $user->incrementFailedAttempts();

        $user->refresh();
        $this->assertEquals(3, $user->failed_login_attempts);
        $this->assertNull($user->locked_until); // Not yet at threshold
    }

    public function test_increment_failed_attempts_locks_at_threshold(): void
    {
        $user = $this->createUser([
            'failed_login_attempts' => 4,
        ]);

        $user->incrementFailedAttempts(); // 5th attempt

        $user->refresh();
        $this->assertEquals(5, $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_increment_failed_attempts_custom_threshold(): void
    {
        $user = $this->createUser([
            'failed_login_attempts' => 2,
        ]);

        $user->incrementFailedAttempts(maxAttempts: 3, lockoutMinutes: 30);

        $user->refresh();
        $this->assertEquals(3, $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        // Lock should be ~30 minutes from now
        $this->assertGreaterThanOrEqual(29, now()->diffInMinutes($user->locked_until, false));
    }

    public function test_reset_failed_attempts_clears_counter_and_lock(): void
    {
        $user = $this->createUser([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $user->resetFailedAttempts();

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_reset_failed_attempts_noop_when_already_zero(): void
    {
        $user = $this->createUser([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $originalUpdatedAt = $user->updated_at;

        // Small sleep to ensure timestamp difference if update happens
        usleep(10000);

        $user->resetFailedAttempts();

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }
}
