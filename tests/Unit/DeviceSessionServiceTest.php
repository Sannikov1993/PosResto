<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\DeviceSession;
use App\Services\DeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unit тесты для DeviceSessionService
 */
class DeviceSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DeviceSessionService $service;
    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $ownerRole;
    protected Role $cookRole;
    protected Role $waiterRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeviceSessionService();

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

        $this->ownerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'owner',
            'name' => 'Владелец',
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        $this->cookRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'cook',
            'name' => 'Повар',
            'can_access_pos' => false,
            'can_access_backoffice' => false,
            'can_access_kitchen' => true,
            'can_access_delivery' => false,
        ]);

        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        $isTenantOwner = $attributes['is_tenant_owner'] ?? false;
        unset($attributes['is_tenant_owner']);

        $user = User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test User ' . rand(1000, 9999),
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role->key,
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));

        if ($isTenantOwner) {
            $user->forceFill(['is_tenant_owner' => true])->save();
        }

        return $user->refresh();
    }

    // ==================== CAN ACCESS APP TESTS ====================

    /** @test */
    public function cook_can_access_kitchen_app()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertTrue($this->service->canAccessApp($cook, 'kitchen'));
    }

    /** @test */
    public function cook_cannot_access_pos_app()
    {
        $cook = $this->createUser($this->cookRole);

        $this->assertFalse($this->service->canAccessApp($cook, 'pos'));
    }

    /** @test */
    public function waiter_can_access_pos_app()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertTrue($this->service->canAccessApp($waiter, 'pos'));
    }

    /** @test */
    public function waiter_cannot_access_kitchen_app()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->assertFalse($this->service->canAccessApp($waiter, 'kitchen'));
    }

    /** @test */
    public function owner_can_access_all_apps()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->assertTrue($this->service->canAccessApp($owner, 'pos'));
        $this->assertTrue($this->service->canAccessApp($owner, 'kitchen'));
        $this->assertTrue($this->service->canAccessApp($owner, 'backoffice'));
        $this->assertTrue($this->service->canAccessApp($owner, 'delivery'));
    }

    /** @test */
    public function tenant_owner_can_access_all_apps()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Despite having cook role, tenant owner can access all apps
        $this->assertTrue($this->service->canAccessApp($tenantOwner, 'pos'));
        $this->assertTrue($this->service->canAccessApp($tenantOwner, 'backoffice'));
    }

    /** @test */
    public function super_admin_can_access_all_apps()
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

        $this->assertTrue($this->service->canAccessApp($superAdmin, 'pos'));
        $this->assertTrue($this->service->canAccessApp($superAdmin, 'kitchen'));
        $this->assertTrue($this->service->canAccessApp($superAdmin, 'backoffice'));
    }

    /** @test */
    public function waiter_app_uses_pos_access()
    {
        $waiter = $this->createUser($this->waiterRole);
        $cook = $this->createUser($this->cookRole);

        // waiter app_type maps to can_access_pos
        $this->assertTrue($this->service->canAccessApp($waiter, 'waiter'));
        $this->assertFalse($this->service->canAccessApp($cook, 'waiter'));
    }

    /** @test */
    public function courier_app_uses_delivery_access()
    {
        $waiter = $this->createUser($this->waiterRole);
        $owner = $this->createUser($this->ownerRole);

        // courier app_type maps to can_access_delivery
        $this->assertFalse($this->service->canAccessApp($waiter, 'courier'));
        $this->assertTrue($this->service->canAccessApp($owner, 'courier'));
    }

    // ==================== CREATE SESSION TESTS ====================

    /** @test */
    public function can_create_session_for_allowed_app()
    {
        $waiter = $this->createUser($this->waiterRole);

        $session = $this->service->createSession(
            $waiter,
            'test-fingerprint-123',
            'pos',
            'Test Device'
        );

        $this->assertInstanceOf(DeviceSession::class, $session);
        $this->assertEquals($waiter->id, $session->user_id);
        $this->assertEquals('pos', $session->app_type);
        $this->assertEquals('Test Device', $session->device_name);
    }

    /** @test */
    public function cannot_create_session_for_disallowed_app()
    {
        $cook = $this->createUser($this->cookRole);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('не имеет доступа к приложению pos');

        $this->service->createSession(
            $cook,
            'test-fingerprint-123',
            'pos',
            'Test Device'
        );
    }

    /** @test */
    public function reuses_existing_active_session()
    {
        $waiter = $this->createUser($this->waiterRole);
        $fingerprint = 'same-fingerprint-' . rand(1000, 9999);

        $session1 = $this->service->createSession($waiter, $fingerprint, 'pos');
        $session2 = $this->service->createSession($waiter, $fingerprint, 'pos');

        // Should reuse the same session
        $this->assertEquals($session1->id, $session2->id);
    }

    // ==================== GET USER BY TOKEN TESTS ====================

    /** @test */
    public function can_get_user_by_valid_token()
    {
        $waiter = $this->createUser($this->waiterRole);
        $session = $this->service->createSession($waiter, 'fingerprint-123', 'pos');

        $foundUser = $this->service->getUserByToken($session->token);

        $this->assertNotNull($foundUser);
        $this->assertEquals($waiter->id, $foundUser->id);
    }

    /** @test */
    public function returns_null_for_invalid_token()
    {
        $foundUser = $this->service->getUserByToken('invalid-token-123');

        $this->assertNull($foundUser);
    }

    /** @test */
    public function returns_null_for_inactive_user()
    {
        $waiter = $this->createUser($this->waiterRole);
        $session = $this->service->createSession($waiter, 'fingerprint-123', 'pos');

        // Deactivate user
        $waiter->update(['is_active' => false]);

        $foundUser = $this->service->getUserByToken($session->token);

        $this->assertNull($foundUser);
    }

    // ==================== GET DEVICE USERS TESTS ====================

    /** @test */
    public function get_device_users_returns_users_with_access()
    {
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);
        $owner = $this->createUser($this->ownerRole, ['name' => 'Owner']);
        $cook = $this->createUser($this->cookRole, ['name' => 'Cook']);

        $fingerprint = 'shared-device-' . rand(1000, 9999);

        // Create sessions for all users
        $this->service->createSession($waiter, $fingerprint, 'pos');
        $this->service->createSession($owner, $fingerprint, 'pos');
        $this->service->createSession($cook, $fingerprint, 'kitchen');

        // Get users for POS app
        $posUsers = $this->service->getDeviceUsers($fingerprint, 'pos');

        // Should include waiter and owner (have pos access)
        // Should NOT include cook (no pos access, different app_type anyway)
        $names = collect($posUsers)->pluck('name')->toArray();

        $this->assertContains('Waiter', $names);
        $this->assertContains('Owner', $names);
        $this->assertNotContains('Cook', $names);
    }

    /** @test */
    public function get_device_users_excludes_users_who_lost_access()
    {
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);
        $fingerprint = 'test-device-' . rand(1000, 9999);

        // Create session
        $this->service->createSession($waiter, $fingerprint, 'pos');

        // Change user's role to cook (no POS access)
        $waiter->update([
            'role' => 'cook',
            'role_id' => $this->cookRole->id,
        ]);

        // Get users for POS
        $posUsers = $this->service->getDeviceUsers($fingerprint, 'pos');

        // Should not include waiter anymore (lost access)
        $names = collect($posUsers)->pluck('name')->toArray();
        $this->assertNotContains('Waiter', $names);
    }

    /** @test */
    public function get_device_users_excludes_inactive_users()
    {
        $waiter = $this->createUser($this->waiterRole, ['name' => 'Waiter']);
        $fingerprint = 'test-device-' . rand(1000, 9999);

        $this->service->createSession($waiter, $fingerprint, 'pos');

        // Deactivate user
        $waiter->update(['is_active' => false]);

        $posUsers = $this->service->getDeviceUsers($fingerprint, 'pos');

        $this->assertEmpty($posUsers);
    }

    // ==================== REVOKE SESSION TESTS ====================

    /** @test */
    public function can_revoke_session_by_token()
    {
        $waiter = $this->createUser($this->waiterRole);
        $session = $this->service->createSession($waiter, 'fingerprint-123', 'pos');

        $revoked = $this->service->revokeSession($session->token);

        $this->assertTrue($revoked);
        $this->assertNull(DeviceSession::find($session->id));
    }

    /** @test */
    public function can_revoke_all_user_sessions()
    {
        $waiter = $this->createUser($this->waiterRole);

        $this->service->createSession($waiter, 'fingerprint-1', 'pos');
        $this->service->createSession($waiter, 'fingerprint-2', 'pos');

        $this->assertEquals(2, DeviceSession::where('user_id', $waiter->id)->count());

        $this->service->revokeAllUserSessions($waiter->id);

        $this->assertEquals(0, DeviceSession::where('user_id', $waiter->id)->count());
    }

    /** @test */
    public function can_revoke_user_sessions_for_specific_app()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->service->createSession($owner, 'fingerprint-1', 'pos');
        $this->service->createSession($owner, 'fingerprint-2', 'kitchen');

        $revoked = $this->service->revokeUserAppSessions($owner->id, 'pos');

        $this->assertEquals(1, $revoked);
        $this->assertEquals(1, DeviceSession::where('user_id', $owner->id)->count());
        $this->assertEquals('kitchen', DeviceSession::where('user_id', $owner->id)->first()->app_type);
    }

    // ==================== STATIC HELPER TESTS ====================

    /** @test */
    public function get_app_access_for_role_returns_correct_apps()
    {
        // Using the legacy static array
        $waiterApps = DeviceSessionService::getAppAccessForRole('waiter');
        $cookApps = DeviceSessionService::getAppAccessForRole('cook');
        $ownerApps = DeviceSessionService::getAppAccessForRole('owner');

        $this->assertContains('waiter', $waiterApps);
        $this->assertContains('kitchen', $cookApps);
        $this->assertContains('pos', $ownerApps);
        $this->assertContains('backoffice', $ownerApps);
    }
}
