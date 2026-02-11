<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Тесты для middleware CheckInterfaceAccess
 */
class CheckInterfaceAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $ownerRole;
    protected Role $cookRole;
    protected Role $waiterRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём тенант и ресторан
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

        // Регистрируем тестовые маршруты с middleware
        Route::middleware(['auth:sanctum', 'interface:pos'])->get('/test/pos-only', function () {
            return response()->json(['success' => true, 'message' => 'POS access granted']);
        });

        Route::middleware(['auth:sanctum', 'interface:kitchen'])->get('/test/kitchen-only', function () {
            return response()->json(['success' => true, 'message' => 'Kitchen access granted']);
        });

        Route::middleware(['auth:sanctum', 'interface:backoffice'])->get('/test/backoffice-only', function () {
            return response()->json(['success' => true, 'message' => 'Backoffice access granted']);
        });

        Route::middleware(['auth:sanctum', 'interface:pos|kitchen'])->get('/test/pos-or-kitchen', function () {
            return response()->json(['success' => true, 'message' => 'POS or Kitchen access granted']);
        });
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        $isTenantOwner = $attributes['is_tenant_owner'] ?? false;
        unset($attributes['is_tenant_owner']);

        $user = User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test User',
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role->key,
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));

        if ($isTenantOwner) {
            $user->forceFill(['is_tenant_owner' => true])->save();
        }

        return $user;
    }

    // ==================== SINGLE INTERFACE TESTS ====================

    /** @test */
    public function cook_cannot_access_pos_routes()
    {
        $cook = $this->createUser($this->cookRole);

        $response = $this->actingAs($cook)->getJson('/test/pos-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'interface_access_denied',
            ]);
    }

    /** @test */
    public function cook_can_access_kitchen_routes()
    {
        $cook = $this->createUser($this->cookRole);

        $response = $this->actingAs($cook)->getJson('/test/kitchen-only');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kitchen access granted',
            ]);
    }

    /** @test */
    public function waiter_can_access_pos_routes()
    {
        $waiter = $this->createUser($this->waiterRole);

        $response = $this->actingAs($waiter)->getJson('/test/pos-only');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'POS access granted',
            ]);
    }

    /** @test */
    public function waiter_cannot_access_kitchen_routes()
    {
        $waiter = $this->createUser($this->waiterRole);

        $response = $this->actingAs($waiter)->getJson('/test/kitchen-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'interface_access_denied',
            ]);
    }

    /** @test */
    public function waiter_cannot_access_backoffice_routes()
    {
        $waiter = $this->createUser($this->waiterRole);

        $response = $this->actingAs($waiter)->getJson('/test/backoffice-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'interface_access_denied',
            ]);
    }

    // ==================== MULTIPLE INTERFACES (OR LOGIC) ====================

    /** @test */
    public function cook_can_access_pos_or_kitchen_routes()
    {
        $cook = $this->createUser($this->cookRole);

        // Cook has kitchen access, so should pass pos|kitchen
        $response = $this->actingAs($cook)->getJson('/test/pos-or-kitchen');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'POS or Kitchen access granted',
            ]);
    }

    /** @test */
    public function waiter_can_access_pos_or_kitchen_routes()
    {
        $waiter = $this->createUser($this->waiterRole);

        // Waiter has POS access, so should pass pos|kitchen
        $response = $this->actingAs($waiter)->getJson('/test/pos-or-kitchen');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'POS or Kitchen access granted',
            ]);
    }

    // ==================== OWNER/SUPERADMIN BYPASS ====================

    /** @test */
    public function owner_can_access_all_routes()
    {
        $owner = $this->createUser($this->ownerRole);

        $this->actingAs($owner)->getJson('/test/pos-only')->assertStatus(200);
        $this->actingAs($owner)->getJson('/test/kitchen-only')->assertStatus(200);
        $this->actingAs($owner)->getJson('/test/backoffice-only')->assertStatus(200);
    }

    /** @test */
    public function tenant_owner_bypasses_interface_check()
    {
        $tenantOwner = $this->createUser($this->cookRole, [
            'is_tenant_owner' => true,
        ]);

        // Cook role has no POS access, but tenant owner bypasses
        $response = $this->actingAs($tenantOwner)->getJson('/test/pos-only');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    // ==================== ERROR RESPONSE FORMAT ====================

    /** @test */
    public function error_response_includes_denied_interfaces()
    {
        $cook = $this->createUser($this->cookRole);

        $response = $this->actingAs($cook)->getJson('/test/pos-only');

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'error_code',
                'denied_interfaces',
                'user_role',
            ])
            ->assertJsonPath('denied_interfaces', ['POS-терминал'])
            ->assertJsonPath('user_role', 'Повар');
    }

    /** @test */
    public function error_response_for_backoffice_shows_correct_name()
    {
        $cook = $this->createUser($this->cookRole);

        $response = $this->actingAs($cook)->getJson('/test/backoffice-only');

        $response->assertStatus(403)
            ->assertJsonPath('denied_interfaces', ['Бэк-офис']);
    }

    // ==================== AUTHENTICATION REQUIRED ====================

    /** @test */
    public function unauthenticated_user_gets_401()
    {
        $response = $this->getJson('/test/pos-only');

        $response->assertStatus(401);
    }

    // ==================== NO ROLE ASSIGNED ====================

    /** @test */
    public function user_without_role_gets_403_with_no_role_assigned()
    {
        $userWithoutRole = User::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'No Role User',
            'email' => 'norole@example.com',
            'password' => Hash::make('password'),
            'role' => 'nonexistent',
            'role_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($userWithoutRole)->getJson('/test/pos-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'no_role_assigned',
            ]);
    }
}
