<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\Order;
use App\Services\TenantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected User $owner;
    protected User $manager;
    protected string $ownerToken;
    protected string $managerToken;

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
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);

        // Create main restaurant
        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Restaurant',
            'is_main' => true,
            'is_active' => true,
        ]);

        // Create owner user
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => User::ROLE_OWNER,
            'is_tenant_owner' => true,
            'is_active' => true,
        ]);

        // Create manager user (not owner)
        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => User::ROLE_MANAGER,
            'is_tenant_owner' => false,
            'is_active' => true,
        ]);

        // Create tokens
        $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;
    }

    // ============================================
    // TENANT REGISTRATION TESTS
    // ============================================

    public function test_can_register_new_tenant(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'New Restaurant Chain',
            'restaurant_name' => 'First Location',
            'owner_name' => 'John Doe',
            'email' => 'john@newrestaurant.com',
            'phone' => '+79998887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
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
        ]);

        // Verify restaurant was created
        $this->assertDatabaseHas('restaurants', [
            'name' => 'First Location',
            'is_main' => true,
        ]);

        // Verify owner user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@newrestaurant.com',
            'role' => User::ROLE_OWNER,
            'is_tenant_owner' => true,
        ]);
    }

    public function test_tenant_registration_creates_trial_subscription(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Trial Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'trial@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::where('email', 'trial@test.com')->first();
        $this->assertEquals(Tenant::PLAN_TRIAL, $tenant->plan);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
    }

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

    public function test_tenant_registration_validates_unique_email(): void
    {
        // Email already exists in tenants table
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Duplicate Org',
            'owner_name' => 'Test',
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
            'email' => 'unique@test.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_tenant_registration_validates_minimum_password_length(): void
    {
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Test Org',
            'owner_name' => 'Test Owner',
            'email' => 'short@test.com',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ============================================
    // TENANT INFO TESTS (GET /api/tenant)
    // ============================================

    public function test_can_get_tenant_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->tenant->id,
                    'name' => 'Test Organization',
                    'slug' => 'test-organization',
                    'email' => 'org@example.com',
                    'plan' => Tenant::PLAN_BUSINESS,
                    'is_active' => true,
                    'timezone' => 'Europe/Moscow',
                    'currency' => 'RUB',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'email',
                    'phone',
                    'plan',
                    'plan_name',
                    'is_on_trial',
                    'trial_ends_at',
                    'subscription_ends_at',
                    'days_until_expiration',
                    'is_active',
                    'timezone',
                    'currency',
                    'restaurants_count',
                ],
            ]);
    }

    public function test_manager_can_get_tenant_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->getJson('/api/tenant');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->tenant->id,
                ],
            ]);
    }

    public function test_tenant_info_requires_authentication(): void
    {
        $response = $this->getJson('/api/tenant');

        $response->assertUnauthorized();
    }

    public function test_tenant_info_returns_404_when_tenant_not_found(): void
    {
        // Create user without tenant (null tenant_id)
        $orphanUser = User::factory()->create([
            'tenant_id' => null,
            'restaurant_id' => null,
            'is_active' => true,
        ]);
        $orphanToken = $orphanUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$orphanToken}",
        ])->getJson('/api/tenant');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    // ============================================
    // TENANT UPDATE TESTS (PUT /api/tenant)
    // ============================================

    public function test_owner_can_update_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson('/api/tenant', [
            'name' => 'Updated Organization Name',
            'phone' => '+79990001122',
            'timezone' => 'Asia/Tokyo',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Данные организации обновлены',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'name' => 'Updated Organization Name',
            'phone' => '+79990001122',
            'timezone' => 'Asia/Tokyo',
        ]);
    }

    public function test_owner_can_update_legal_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson('/api/tenant', [
            'inn' => '1234567890',
            'legal_name' => 'OOO Test Company',
            'legal_address' => '123 Main Street, Moscow',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'inn' => '1234567890',
            'legal_name' => 'OOO Test Company',
            'legal_address' => '123 Main Street, Moscow',
        ]);
    }

    public function test_manager_cannot_update_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->putJson('/api/tenant', [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_update_tenant_validates_unique_email(): void
    {
        // Create another tenant with different email
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@example.com',
            'plan' => Tenant::PLAN_START,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson('/api/tenant', [
            'email' => 'other@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_tenant_allows_same_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson('/api/tenant', [
            'email' => 'org@example.com', // Same email as current
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
    }

    // ============================================
    // RESTAURANT LISTING TESTS
    // ============================================

    public function test_can_list_restaurants(): void
    {
        // Create additional restaurants
        Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Location',
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/restaurants');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'address',
                        'phone',
                        'is_active',
                        'is_main',
                        'is_current',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_restaurants_are_sorted_with_main_first(): void
    {
        // Create additional restaurants
        Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AAA Location', // Alphabetically first
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/restaurants');

        $response->assertOk();

        $data = $response->json('data');
        // Main restaurant should be first regardless of alphabetical order
        $this->assertTrue($data[0]['is_main']);
    }

    public function test_current_restaurant_is_marked(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/restaurants');

        $response->assertOk();

        $data = $response->json('data');
        $currentRestaurant = collect($data)->firstWhere('is_current', true);
        $this->assertEquals($this->restaurant->id, $currentRestaurant['id']);
    }

    // ============================================
    // CREATE RESTAURANT TESTS
    // ============================================

    public function test_owner_can_create_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/restaurants', [
            'name' => 'New Branch',
            'address' => '456 New Street',
            'phone' => '+79991112233',
            'email' => 'branch@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Точка создана',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'address',
                    'phone',
                    'is_active',
                    'is_main',
                ],
            ]);

        $this->assertDatabaseHas('restaurants', [
            'tenant_id' => $this->tenant->id,
            'name' => 'New Branch',
            'address' => '456 New Street',
            'is_main' => false, // Not main since we already have a main
        ]);
    }

    public function test_manager_cannot_create_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/tenant/restaurants', [
            'name' => 'Unauthorized Branch',
        ]);

        $response->assertForbidden();
    }

    public function test_create_restaurant_validates_required_name(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/restaurants', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_restaurant_checks_plan_limit(): void
    {
        // Switch to Start plan which has limit of 1 restaurant
        $this->tenant->update(['plan' => Tenant::PLAN_START]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/restaurants', [
            'name' => 'Over Limit Branch',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'upgrade_required' => true,
            ]);
    }

    public function test_premium_plan_can_create_unlimited_restaurants(): void
    {
        // Switch to Premium plan
        $this->tenant->update(['plan' => Tenant::PLAN_PREMIUM]);

        // Create multiple restaurants
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->ownerToken}",
            ])->postJson('/api/tenant/restaurants', [
                'name' => "Branch {$i}",
            ]);

            $response->assertStatus(201);
        }

        $this->assertEquals(6, $this->tenant->restaurants()->count()); // 1 original + 5 new
    }

    // ============================================
    // UPDATE RESTAURANT TESTS
    // ============================================

    public function test_owner_can_update_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson("/api/tenant/restaurants/{$this->restaurant->id}", [
            'name' => 'Updated Restaurant Name',
            'address' => 'New Address 123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Данные точки обновлены',
            ]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'name' => 'Updated Restaurant Name',
            'address' => 'New Address 123',
        ]);
    }

    public function test_can_deactivate_restaurant(): void
    {
        // Create a non-main restaurant to deactivate
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch to Deactivate',
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson("/api/tenant/restaurants/{$branch->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('restaurants', [
            'id' => $branch->id,
            'is_active' => false,
        ]);
    }

    public function test_manager_cannot_update_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->putJson("/api/tenant/restaurants/{$this->restaurant->id}", [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_update_restaurant_from_different_tenant(): void
    {
        // Create another tenant's restaurant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@test.com',
            'plan' => Tenant::PLAN_START,
            'is_active' => true,
        ]);

        $otherRestaurant = Restaurant::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Restaurant',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->putJson("/api/tenant/restaurants/{$otherRestaurant->id}", [
            'name' => 'Hijacked Name',
        ]);

        $response->assertNotFound();
    }

    // ============================================
    // DELETE RESTAURANT TESTS
    // ============================================

    public function test_owner_can_delete_non_main_restaurant(): void
    {
        // Create a non-main restaurant
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch to Delete',
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->deleteJson("/api/tenant/restaurants/{$branch->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Точка удалена',
            ]);

        $this->assertSoftDeleted('restaurants', [
            'id' => $branch->id,
        ]);
    }

    public function test_cannot_delete_main_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->deleteJson("/api/tenant/restaurants/{$this->restaurant->id}");

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_delete_restaurant_with_active_orders(): void
    {
        // Create a non-main restaurant
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch With Orders',
            'is_main' => false,
            'is_active' => true,
        ]);

        // Create active order
        Order::factory()->create([
            'restaurant_id' => $branch->id,
            'status' => 'new',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->deleteJson("/api/tenant/restaurants/{$branch->id}");

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_delete_restaurant_with_completed_orders(): void
    {
        // Create a non-main restaurant
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch With Completed Orders',
            'is_main' => false,
            'is_active' => true,
        ]);

        // Create completed order (not active)
        Order::factory()->create([
            'restaurant_id' => $branch->id,
            'status' => 'completed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->deleteJson("/api/tenant/restaurants/{$branch->id}");

        $response->assertOk();
    }

    public function test_manager_cannot_delete_restaurant(): void
    {
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch',
            'is_main' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->deleteJson("/api/tenant/restaurants/{$branch->id}");

        $response->assertForbidden();
    }

    // ============================================
    // MAKE MAIN RESTAURANT TESTS
    // ============================================

    public function test_owner_can_make_restaurant_main(): void
    {
        // Create another restaurant
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'New Main Branch',
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson("/api/tenant/restaurants/{$branch->id}/make-main");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Главная точка изменена',
            ]);

        // New branch should be main
        $this->assertDatabaseHas('restaurants', [
            'id' => $branch->id,
            'is_main' => true,
        ]);

        // Old main should no longer be main
        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'is_main' => false,
        ]);
    }

    public function test_manager_cannot_make_restaurant_main(): void
    {
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch',
            'is_main' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson("/api/tenant/restaurants/{$branch->id}/make-main");

        $response->assertForbidden();
    }

    // ============================================
    // SWITCH RESTAURANT TESTS
    // ============================================

    public function test_can_switch_restaurant(): void
    {
        // Create another active restaurant
        $branch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Branch',
            'is_main' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson("/api/tenant/restaurants/{$branch->id}/switch");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'restaurant_id' => $branch->id,
                    'restaurant_name' => 'Second Branch',
                ],
            ]);

        // Verify user's restaurant was updated
        $this->owner->refresh();
        $this->assertEquals($branch->id, $this->owner->restaurant_id);
    }

    public function test_cannot_switch_to_inactive_restaurant(): void
    {
        // Create inactive restaurant
        $inactiveBranch = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Branch',
            'is_main' => false,
            'is_active' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson("/api/tenant/restaurants/{$inactiveBranch->id}/switch");

        $response->assertNotFound();
    }

    public function test_cannot_switch_to_different_tenant_restaurant(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@test.com',
            'plan' => Tenant::PLAN_START,
            'is_active' => true,
        ]);

        $otherRestaurant = Restaurant::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Restaurant',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson("/api/tenant/restaurants/{$otherRestaurant->id}/switch");

        $response->assertNotFound();
    }

    // ============================================
    // LIMITS TESTS
    // ============================================

    public function test_can_get_plan_limits(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/limits');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => Tenant::PLAN_BUSINESS,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'plan',
                    'plan_name',
                    'limits' => [
                        'max_restaurants',
                        'max_users',
                        'max_orders_per_month',
                    ],
                    'current' => [
                        'restaurants',
                        'users',
                    ],
                    'can_add_restaurant',
                    'can_add_user',
                ],
            ]);
    }

    public function test_limits_show_current_usage(): void
    {
        // Create additional users
        User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create additional restaurant
        Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_main' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/limits');

        $response->assertOk();

        $current = $response->json('data.current');
        $this->assertEquals(2, $current['restaurants']); // main + 1
        $this->assertEquals(5, $current['users']); // owner + manager + 3 new
    }

    // ============================================
    // PLANS TESTS
    // ============================================

    public function test_can_get_available_plans(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/plans');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price_monthly',
                        'price_yearly',
                        'limits',
                        'features',
                    ],
                ],
            ]);

        // Trial plan should not be in available plans
        $plans = $response->json('data');
        $planIds = array_column($plans, 'id');
        $this->assertNotContains(Tenant::PLAN_TRIAL, $planIds);
    }

    // ============================================
    // SUBSCRIPTION TESTS
    // ============================================

    public function test_can_get_subscription_status(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/subscription');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'plan' => Tenant::PLAN_BUSINESS,
                    'is_active' => true,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenant_id',
                    'tenant_name',
                    'plan',
                    'plan_info',
                    'is_on_trial',
                    'has_active_subscription',
                    'trial_ends_at',
                    'subscription_ends_at',
                    'days_remaining',
                    'is_active',
                    'current_usage',
                ],
            ]);
    }

    public function test_subscription_shows_trial_status(): void
    {
        $this->tenant->update([
            'plan' => Tenant::PLAN_TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'subscription_ends_at' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/subscription');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'plan' => Tenant::PLAN_TRIAL,
                    'is_on_trial' => true,
                ],
            ]);
    }

    // ============================================
    // CHANGE PLAN TESTS
    // ============================================

    public function test_owner_can_change_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'premium',
            'period' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'plan',
                    'price',
                    'period',
                    'expires_at',
                ],
            ]);

        $this->tenant->refresh();
        $this->assertEquals(Tenant::PLAN_PREMIUM, $this->tenant->plan);
    }

    public function test_owner_can_change_plan_to_yearly(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'start',
            'period' => 'yearly',
        ]);

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertEquals(Tenant::PLAN_START, $this->tenant->plan);
        // Yearly should add 365 days
        $this->assertTrue(abs($this->tenant->subscription_ends_at->diffInDays(now())) >= 360);
    }

    public function test_manager_cannot_change_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'premium',
            'period' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    public function test_change_plan_validates_plan_value(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'invalid_plan',
            'period' => 'monthly',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_change_plan_validates_period_value(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'premium',
            'period' => 'weekly', // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    public function test_cannot_change_to_trial_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/change', [
            'plan' => 'trial',
            'period' => 'monthly',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    // ============================================
    // EXTEND SUBSCRIPTION TESTS
    // ============================================

    public function test_owner_can_extend_subscription(): void
    {
        $originalEnd = $this->tenant->subscription_ends_at;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/extend', [
            'period' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'plan',
                    'price',
                    'period',
                    'expires_at',
                ],
            ]);

        $this->tenant->refresh();
        // Should have added 30 days
        $this->assertTrue($this->tenant->subscription_ends_at->gt($originalEnd));
    }

    public function test_owner_can_extend_subscription_yearly(): void
    {
        $originalEnd = $this->tenant->subscription_ends_at->copy();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/extend', [
            'period' => 'yearly',
        ]);

        $response->assertOk();

        $this->tenant->refresh();
        // Should have added 365 days
        $this->assertTrue(abs($this->tenant->subscription_ends_at->diffInDays($originalEnd)) >= 360);
    }

    public function test_manager_cannot_extend_subscription(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/tenant/subscription/extend', [
            'period' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_extend_trial_subscription(): void
    {
        $this->tenant->update([
            'plan' => Tenant::PLAN_TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'subscription_ends_at' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/extend', [
            'period' => 'monthly',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_extend_validates_period(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->postJson('/api/tenant/subscription/extend', [
            'period' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);
    }

    // ============================================
    // MULTI-TENANCY ISOLATION TESTS
    // ============================================

    public function test_tenant_cannot_see_other_tenant_restaurants(): void
    {
        // Create another tenant with restaurant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'email' => 'other@test.com',
            'plan' => Tenant::PLAN_START,
            'is_active' => true,
        ]);

        $otherRestaurant = Restaurant::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Restaurant',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/tenant/restaurants');

        $response->assertOk();

        $restaurantIds = array_column($response->json('data'), 'id');
        $this->assertNotContains($otherRestaurant->id, $restaurantIds);
    }

    public function test_tenant_data_is_isolated(): void
    {
        // Create multiple tenants
        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
            'email' => 'tenant2@test.com',
            'plan' => Tenant::PLAN_BUSINESS,
            'is_active' => true,
        ]);

        $restaurant2 = Restaurant::factory()->create([
            'tenant_id' => $tenant2->id,
        ]);

        $user2 = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'restaurant_id' => $restaurant2->id,
            'is_tenant_owner' => true,
        ]);

        $token2 = $user2->createToken('test')->plainTextToken;

        // User from tenant 2 should see their own data
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token2}",
        ])->getJson('/api/tenant');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $tenant2->id,
                    'name' => 'Tenant 2',
                ],
            ]);

        // And not see tenant 1's data
        $this->assertNotEquals($this->tenant->id, $response->json('data.id'));
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_all_tenant_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/tenant'],
            ['PUT', '/api/tenant'],
            ['GET', '/api/tenant/restaurants'],
            ['POST', '/api/tenant/restaurants'],
            ['PUT', "/api/tenant/restaurants/{$this->restaurant->id}"],
            ['DELETE', "/api/tenant/restaurants/{$this->restaurant->id}"],
            ['POST', "/api/tenant/restaurants/{$this->restaurant->id}/make-main"],
            ['POST', "/api/tenant/restaurants/{$this->restaurant->id}/switch"],
            ['GET', '/api/tenant/limits'],
            ['GET', '/api/tenant/plans'],
            ['GET', '/api/tenant/subscription'],
            ['POST', '/api/tenant/subscription/change'],
            ['POST', '/api/tenant/subscription/extend'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertUnauthorized();
        }
    }

    public function test_tenant_registration_does_not_require_authentication(): void
    {
        // Registration endpoint should be public
        $response = $this->postJson('/api/register/tenant', [
            'organization_name' => 'Public Registration',
            'owner_name' => 'Test',
            'email' => 'public@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Should not return 401
        $this->assertNotEquals(401, $response->status());
    }
}
