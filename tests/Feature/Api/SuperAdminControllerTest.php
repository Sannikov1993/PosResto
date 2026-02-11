<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SuperAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected string $superAdminToken;

    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected Tenant $tenant3;

    protected Restaurant $restaurant1;
    protected Restaurant $restaurant2;

    protected User $tenantOwner;
    protected User $regularUser;
    protected string $ownerToken;
    protected string $regularToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin user (no tenant association)
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'restaurant_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'name' => 'Super Admin',
            'email' => 'superadmin@system.com',
        ]);
        $this->superAdminToken = $this->superAdmin->createToken('test')->plainTextToken;

        // Create Tenant 1 (active, business plan)
        $this->tenant1 = Tenant::create([
            'name' => 'Active Business Tenant',
            'slug' => 'active-business-tenant',
            'email' => 'tenant1@example.com',
            'phone' => '+79991111111',
            'plan' => Tenant::PLAN_BUSINESS,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addMonths(1),
            'is_active' => true,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);

        // Create Tenant 2 (on trial)
        $this->tenant2 = Tenant::create([
            'name' => 'Trial Tenant',
            'slug' => 'trial-tenant',
            'email' => 'tenant2@example.com',
            'phone' => '+79992222222',
            'plan' => Tenant::PLAN_TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'subscription_ends_at' => null,
            'is_active' => true,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);

        // Create Tenant 3 (blocked)
        $this->tenant3 = Tenant::create([
            'name' => 'Blocked Tenant',
            'slug' => 'blocked-tenant',
            'email' => 'tenant3@example.com',
            'phone' => '+79993333333',
            'plan' => Tenant::PLAN_START,
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addDays(15),
            'is_active' => false,
            'blocked_at' => now()->subDays(2),
            'blocked_reason' => 'Payment overdue',
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);

        // Create restaurants for tenant1
        $this->restaurant1 = Restaurant::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Main Restaurant',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->restaurant2 = Restaurant::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Second Restaurant',
            'is_main' => false,
            'is_active' => true,
        ]);

        // Create tenant owner user
        $this->tenantOwner = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'restaurant_id' => $this->restaurant1->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'name' => 'Tenant Owner',
            'email' => 'owner@tenant1.com',
        ]);
        $this->tenantOwner->forceFill(['is_tenant_owner' => true])->save();
        $this->ownerToken = $this->tenantOwner->createToken('test')->plainTextToken;

        // Create regular user (waiter)
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'restaurant_id' => $this->restaurant1->id,
            'role' => User::ROLE_WAITER,
            'is_active' => true,
            'name' => 'Regular Waiter',
            'email' => 'waiter@tenant1.com',
        ]);
        $this->regularToken = $this->regularUser->createToken('test')->plainTextToken;

        // Create restaurant for tenant2
        Restaurant::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Trial Restaurant',
            'is_main' => true,
            'is_active' => true,
        ]);

        // Create users for tenant2
        $tenant2Owner = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'restaurant_id' => $this->tenant2->restaurants->first()->id ?? null,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        $tenant2Owner->forceFill(['is_tenant_owner' => true])->save();
    }

    // ============================================
    // AUTHENTICATION & AUTHORIZATION TESTS
    // ============================================

    public function test_super_admin_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/super-admin/dashboard'],
            ['GET', '/api/super-admin/tenants'],
            ['GET', "/api/super-admin/tenants/{$this->tenant1->id}"],
            ['PUT', "/api/super-admin/tenants/{$this->tenant1->id}"],
            ['DELETE', "/api/super-admin/tenants/{$this->tenant1->id}"],
            ['POST', "/api/super-admin/tenants/{$this->tenant1->id}/block"],
            ['POST', "/api/super-admin/tenants/{$this->tenant1->id}/unblock"],
            ['POST', "/api/super-admin/tenants/{$this->tenant1->id}/extend"],
            ['POST', "/api/super-admin/tenants/{$this->tenant1->id}/change-plan"],
            ['POST', "/api/super-admin/tenants/{$this->tenant1->id}/impersonate"],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertUnauthorized();
        }
    }

    public function test_regular_user_cannot_access_super_admin_endpoints(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->regularToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Доступ запрещён. Требуются права супер-администратора.',
            ]);
    }

    public function test_tenant_owner_cannot_access_super_admin_endpoints(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_manager_cannot_access_super_admin_endpoints(): void
    {
        $manager = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'restaurant_id' => $this->restaurant1->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        $managerToken = $manager->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$managerToken}",
        ])->getJson('/api/super-admin/tenants');

        $response->assertForbidden();
    }

    public function test_admin_cannot_access_super_admin_endpoints(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'restaurant_id' => $this->restaurant1->id,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $adminToken = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_all_endpoints(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // DASHBOARD TESTS
    // ============================================

    public function test_dashboard_returns_correct_structure(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_tenants',
                    'active_tenants',
                    'trial_tenants',
                    'paid_tenants',
                    'total_restaurants',
                    'total_users',
                    'new_tenants_this_month',
                    'expiring_tenants',
                    'plan_distribution',
                ],
            ]);
    }

    public function test_dashboard_returns_correct_tenant_counts(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        $data = $response->json('data');

        // Total tenants: 3 (tenant1, tenant2, tenant3)
        $this->assertEquals(3, $data['total_tenants']);

        // Active tenants: 2 (tenant1 and tenant2 are active, tenant3 is blocked)
        $this->assertEquals(2, $data['active_tenants']);

        // Trial tenants: 1 (tenant2)
        $this->assertEquals(1, $data['trial_tenants']);

        // Paid tenants: 2 (tenant1 on business, tenant3 on start)
        $this->assertEquals(2, $data['paid_tenants']);
    }

    public function test_dashboard_returns_correct_restaurant_count(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        // 2 restaurants for tenant1 + 1 for tenant2 = 3 total
        $this->assertEquals(3, $response->json('data.total_restaurants'));
    }

    public function test_dashboard_returns_correct_user_count(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        // superAdmin + tenantOwner + regularUser + tenant2 owner = 4 users
        $this->assertEquals(4, $response->json('data.total_users'));
    }

    public function test_dashboard_returns_plan_distribution(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        $distribution = $response->json('data.plan_distribution');

        $this->assertEquals(1, $distribution['trial']);     // tenant2
        $this->assertEquals(1, $distribution['start']);     // tenant3
        $this->assertEquals(1, $distribution['business']);  // tenant1
    }

    public function test_dashboard_counts_new_tenants_this_month(): void
    {
        // All 3 tenants were created in setUp, which is "now"
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        // All 3 tenants are new (created within last 30 days)
        $this->assertEquals(3, $response->json('data.new_tenants_this_month'));
    }

    public function test_dashboard_counts_expiring_tenants(): void
    {
        // Create tenant with subscription expiring within 7 days
        Tenant::create([
            'name' => 'Expiring Soon Tenant',
            'slug' => 'expiring-soon',
            'email' => 'expiring@example.com',
            'plan' => Tenant::PLAN_BUSINESS,
            'subscription_ends_at' => now()->addDays(5),
            'is_active' => true,
        ]);

        // tenant2 has trial_ends_at in 7 days (should be counted)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk();

        // tenant2 (trial expiring in 7 days) + new expiring tenant
        $this->assertEquals(2, $response->json('data.expiring_tenants'));
    }

    // ============================================
    // TENANTS LIST TESTS
    // ============================================

    public function test_can_get_tenants_list(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'email',
                        'phone',
                        'plan',
                        'is_active',
                        'restaurants_count',
                        'users_count',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_tenants_list_supports_search_by_name(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?search=Active');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active Business Tenant', $data[0]['name']);
    }

    public function test_tenants_list_supports_search_by_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?search=tenant2@example.com');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Trial Tenant', $data[0]['name']);
    }

    public function test_tenants_list_supports_search_by_phone(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?search=79993333333');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Blocked Tenant', $data[0]['name']);
    }

    public function test_tenants_list_supports_plan_filter(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?plan=trial');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Trial Tenant', $data[0]['name']);
    }

    public function test_tenants_list_supports_active_filter(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?is_active=true');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $tenant) {
            $this->assertTrue($tenant['is_active']);
        }
    }

    public function test_tenants_list_supports_inactive_filter(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?is_active=false');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertFalse($data[0]['is_active']);
        $this->assertEquals('Blocked Tenant', $data[0]['name']);
    }

    public function test_tenants_list_supports_sorting_by_name(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?sort_by=name&sort_dir=asc');

        $response->assertOk();

        $data = $response->json('data');
        $names = array_column($data, 'name');

        $this->assertEquals('Active Business Tenant', $names[0]);
        $this->assertEquals('Blocked Tenant', $names[1]);
        $this->assertEquals('Trial Tenant', $names[2]);
    }

    public function test_tenants_list_supports_sorting_desc(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?sort_by=name&sort_dir=desc');

        $response->assertOk();

        $data = $response->json('data');
        $names = array_column($data, 'name');

        $this->assertEquals('Trial Tenant', $names[0]);
        $this->assertEquals('Blocked Tenant', $names[1]);
        $this->assertEquals('Active Business Tenant', $names[2]);
    }

    public function test_tenants_list_supports_pagination(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?per_page=2&page=1');

        $response->assertOk();

        $this->assertEquals(2, $response->json('meta.per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(3, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_tenants_list_includes_restaurants_count(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants');

        $response->assertOk();

        $data = $response->json('data');
        $tenant1Data = collect($data)->firstWhere('id', $this->tenant1->id);

        $this->assertEquals(2, $tenant1Data['restaurants_count']);
    }

    public function test_tenants_list_includes_users_count(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants');

        $response->assertOk();

        $data = $response->json('data');
        $tenant1Data = collect($data)->firstWhere('id', $this->tenant1->id);

        // tenantOwner + regularUser = 2 users
        $this->assertEquals(2, $tenant1Data['users_count']);
    }

    // ============================================
    // TENANT DETAILS TESTS
    // ============================================

    public function test_can_get_tenant_details(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/super-admin/tenants/{$this->tenant1->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenant' => [
                        'id',
                        'name',
                        'slug',
                        'email',
                        'phone',
                        'plan',
                        'is_active',
                        'restaurants_count',
                        'users_count',
                        'restaurants',
                        'users',
                    ],
                    'plan_info',
                    'stats' => [
                        'total_orders',
                        'orders_this_month',
                        'total_revenue',
                    ],
                ],
            ]);
    }

    public function test_tenant_details_includes_restaurants(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/super-admin/tenants/{$this->tenant1->id}");

        $response->assertOk();

        $restaurants = $response->json('data.tenant.restaurants');
        $this->assertCount(2, $restaurants);
    }

    public function test_tenant_details_includes_users(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/super-admin/tenants/{$this->tenant1->id}");

        $response->assertOk();

        $users = $response->json('data.tenant.users');
        $this->assertCount(2, $users);

        // Check user fields are properly selected
        $firstUser = $users[0];
        $this->assertArrayHasKey('id', $firstUser);
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertArrayHasKey('role', $firstUser);
        $this->assertArrayHasKey('is_active', $firstUser);
        $this->assertArrayHasKey('is_tenant_owner', $firstUser);
    }

    public function test_tenant_details_includes_plan_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/super-admin/tenants/{$this->tenant1->id}");

        $response->assertOk();

        $planInfo = $response->json('data.plan_info');
        $this->assertNotNull($planInfo);
        $this->assertEquals('business', $planInfo['id']);
    }

    public function test_tenant_details_includes_order_stats(): void
    {
        // Create some orders for tenant1 - must be completed and paid
        Order::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant1->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 1000,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/super-admin/tenants/{$this->tenant1->id}");

        $response->assertOk();

        $stats = $response->json('data.stats');
        $this->assertEquals(5, $stats['total_orders']);
        $this->assertEquals(5000, $stats['total_revenue']);
    }

    public function test_tenant_details_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Тенант не найден',
            ]);
    }

    // ============================================
    // UPDATE TENANT TESTS
    // ============================================

    public function test_super_admin_can_update_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'name' => 'Updated Tenant Name',
            'phone' => '+79990000000',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Тенант обновлён',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant1->id,
            'name' => 'Updated Tenant Name',
            'phone' => '+79990000000',
        ]);
    }

    public function test_can_update_tenant_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'email' => 'newemail@example.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant1->id,
            'email' => 'newemail@example.com',
        ]);
    }

    public function test_can_update_tenant_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'plan' => 'premium',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant1->id,
            'plan' => 'premium',
        ]);
    }

    public function test_can_update_tenant_is_active(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant1->id,
            'is_active' => false,
        ]);
    }

    public function test_can_update_tenant_subscription_dates(): void
    {
        $newDate = now()->addMonths(6)->toDateTimeString();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'subscription_ends_at' => $newDate,
        ]);

        $response->assertOk();

        $this->tenant1->refresh();
        $this->assertTrue(abs($this->tenant1->subscription_ends_at->diffInMonths(now())) >= 5);
    }

    public function test_update_tenant_validates_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'plan' => 'invalid_plan',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_update_tenant_validates_email_format(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson("/api/super-admin/tenants/{$this->tenant1->id}", [
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->putJson('/api/super-admin/tenants/99999', [
            'name' => 'Test',
        ]);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Тенант не найден',
            ]);
    }

    // ============================================
    // BLOCK/UNBLOCK TENANT TESTS
    // ============================================

    public function test_super_admin_can_block_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/block", [
            'reason' => 'Violation of terms',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Тенант заблокирован',
            ]);

        $this->tenant1->refresh();
        $this->assertFalse($this->tenant1->is_active);
        $this->assertNotNull($this->tenant1->blocked_at);
        $this->assertEquals('Violation of terms', $this->tenant1->blocked_reason);
    }

    public function test_block_tenant_uses_default_reason_if_not_provided(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/block");

        $response->assertOk();

        $this->tenant1->refresh();
        $this->assertEquals('Заблокирован администратором', $this->tenant1->blocked_reason);
    }

    public function test_super_admin_can_unblock_tenant(): void
    {
        // tenant3 is already blocked
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant3->id}/unblock");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Тенант разблокирован',
            ]);

        $this->tenant3->refresh();
        $this->assertTrue($this->tenant3->is_active);
        $this->assertNull($this->tenant3->blocked_at);
        $this->assertNull($this->tenant3->blocked_reason);
    }

    public function test_block_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson('/api/super-admin/tenants/99999/block');

        $response->assertNotFound();
    }

    public function test_unblock_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson('/api/super-admin/tenants/99999/unblock');

        $response->assertNotFound();
    }

    // ============================================
    // EXTEND SUBSCRIPTION TESTS
    // ============================================

    public function test_super_admin_can_extend_subscription(): void
    {
        $originalEndDate = $this->tenant1->subscription_ends_at;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/extend", [
            'days' => 30,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Подписка продлена на 30 дней',
            ]);

        $this->tenant1->refresh();
        $this->assertTrue($this->tenant1->subscription_ends_at->gt($originalEndDate));
        $this->assertEquals(30, abs($this->tenant1->subscription_ends_at->diffInDays($originalEndDate)));
    }

    public function test_super_admin_can_extend_trial_subscription(): void
    {
        $originalEndDate = $this->tenant2->trial_ends_at;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant2->id}/extend", [
            'days' => 14,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Подписка продлена на 14 дней',
            ]);

        $this->tenant2->refresh();
        $this->assertTrue($this->tenant2->trial_ends_at->gt($originalEndDate));
    }

    public function test_extend_subscription_validates_days(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/extend", [
            'days' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    }

    public function test_extend_subscription_validates_max_days(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/extend", [
            'days' => 500,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    }

    public function test_extend_subscription_requires_days(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/extend", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    }

    public function test_extend_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson('/api/super-admin/tenants/99999/extend', [
            'days' => 30,
        ]);

        $response->assertNotFound();
    }

    // ============================================
    // CHANGE PLAN TESTS
    // ============================================

    public function test_super_admin_can_change_tenant_plan(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant2->id}/change-plan", [
            'plan' => 'business',
            'days' => 30,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Тариф изменён',
            ]);

        $this->tenant2->refresh();
        $this->assertEquals('business', $this->tenant2->plan);
        $this->assertNull($this->tenant2->trial_ends_at);
        $this->assertNotNull($this->tenant2->subscription_ends_at);
    }

    public function test_change_to_trial_plan_sets_trial_ends_at(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/change-plan", [
            'plan' => 'trial',
            'days' => 14,
        ]);

        $response->assertOk();

        $this->tenant1->refresh();
        $this->assertEquals('trial', $this->tenant1->plan);
        $this->assertNotNull($this->tenant1->trial_ends_at);
        $this->assertNull($this->tenant1->subscription_ends_at);
    }

    public function test_change_to_paid_plan_sets_subscription_ends_at(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant2->id}/change-plan", [
            'plan' => 'premium',
            'days' => 365,
        ]);

        $response->assertOk();

        $this->tenant2->refresh();
        $this->assertEquals('premium', $this->tenant2->plan);
        $this->assertNull($this->tenant2->trial_ends_at);
        $this->assertNotNull($this->tenant2->subscription_ends_at);
    }

    public function test_change_plan_validates_plan_value(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/change-plan", [
            'plan' => 'invalid_plan',
            'days' => 30,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_change_plan_validates_days(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/change-plan", [
            'plan' => 'premium',
            'days' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    }

    public function test_change_plan_requires_both_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/change-plan", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan', 'days']);
    }

    public function test_change_plan_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson('/api/super-admin/tenants/99999/change-plan', [
            'plan' => 'business',
            'days' => 30,
        ]);

        $response->assertNotFound();
    }

    // ============================================
    // IMPERSONATE TESTS
    // ============================================

    public function test_super_admin_can_impersonate_tenant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/impersonate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Токен создан для входа под тенантом',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'tenant' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        // Verify returned user is the tenant owner
        $userId = $response->json('data.user.id');
        $this->assertEquals($this->tenantOwner->id, $userId);
    }

    public function test_impersonate_returns_valid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/impersonate");

        $response->assertOk();

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify token works
        $checkResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/auth/check');

        $checkResponse->assertOk();
    }

    public function test_impersonate_returns_tenant_owner(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/impersonate");

        $response->assertOk();

        $this->assertEquals($this->tenantOwner->id, $response->json('data.user.id'));
        $this->assertEquals($this->tenantOwner->name, $response->json('data.user.name'));
        $this->assertEquals($this->tenantOwner->email, $response->json('data.user.email'));
    }

    public function test_impersonate_tenant_without_owner_returns_404(): void
    {
        // Create tenant without owner
        $tenantWithoutOwner = Tenant::create([
            'name' => 'No Owner Tenant',
            'slug' => 'no-owner-tenant',
            'email' => 'noowner@example.com',
            'plan' => Tenant::PLAN_TRIAL,
            'trial_ends_at' => now()->addDays(14),
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$tenantWithoutOwner->id}/impersonate");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Владелец тенанта не найден',
            ]);
    }

    public function test_impersonate_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson('/api/super-admin/tenants/99999/impersonate');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Тенант не найден',
            ]);
    }

    // ============================================
    // DELETE TENANT TESTS
    // ============================================

    public function test_super_admin_can_delete_tenant(): void
    {
        $tenantId = $this->tenant3->id;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->deleteJson("/api/super-admin/tenants/{$tenantId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Тенант удалён',
            ]);

        // Verify soft deleted
        $this->assertSoftDeleted('tenants', [
            'id' => $tenantId,
        ]);
    }

    public function test_delete_tenant_blocks_before_deleting(): void
    {
        $tenantId = $this->tenant1->id;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->deleteJson("/api/super-admin/tenants/{$tenantId}");

        $response->assertOk();

        // Verify tenant is blocked (is_active = false) and soft deleted
        $deletedTenant = Tenant::withTrashed()->find($tenantId);
        $this->assertFalse($deletedTenant->is_active);
        $this->assertNotNull($deletedTenant->deleted_at);
        $this->assertEquals('Удалён администратором', $deletedTenant->blocked_reason);
    }

    public function test_delete_nonexistent_tenant_returns_404(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->deleteJson('/api/super-admin/tenants/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Тенант не найден',
            ]);
    }

    // ============================================
    // COMBINED FILTERS TESTS
    // ============================================

    public function test_tenants_list_supports_combined_filters(): void
    {
        // Create more tenants for testing
        Tenant::create([
            'name' => 'Another Business Tenant',
            'slug' => 'another-business',
            'email' => 'another@example.com',
            'plan' => Tenant::PLAN_BUSINESS,
            'subscription_ends_at' => now()->addMonths(1),
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?plan=business&is_active=true');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $tenant) {
            $this->assertEquals('business', $tenant['plan']);
            $this->assertTrue($tenant['is_active']);
        }
    }

    public function test_tenants_list_search_with_plan_filter(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants?search=Tenant&plan=trial');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Trial Tenant', $data[0]['name']);
    }

    // ============================================
    // EDGE CASES TESTS
    // ============================================

    public function test_dashboard_handles_empty_database(): void
    {
        // Delete all tenants
        Tenant::query()->forceDelete();
        Restaurant::query()->forceDelete();
        User::where('role', '!=', User::ROLE_SUPER_ADMIN)->forceDelete();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/dashboard');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_tenants' => 0,
                    'active_tenants' => 0,
                    'trial_tenants' => 0,
                    'paid_tenants' => 0,
                    'total_restaurants' => 0,
                    'new_tenants_this_month' => 0,
                    'expiring_tenants' => 0,
                ],
            ]);
    }

    public function test_tenants_list_returns_empty_when_no_tenants(): void
    {
        // Delete all tenants
        Tenant::query()->forceDelete();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson('/api/super-admin/tenants');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }

    public function test_extending_tenant_without_current_end_date(): void
    {
        // Set subscription_ends_at to null
        $this->tenant1->update(['subscription_ends_at' => null]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant1->id}/extend", [
            'days' => 30,
        ]);

        $response->assertOk();

        $this->tenant1->refresh();
        // Should start from now
        $this->assertTrue(abs($this->tenant1->subscription_ends_at->diffInDays(now())) >= 29);
    }

    public function test_extending_trial_tenant_without_trial_end_date(): void
    {
        // Set trial_ends_at to null
        $this->tenant2->update(['trial_ends_at' => null]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->postJson("/api/super-admin/tenants/{$this->tenant2->id}/extend", [
            'days' => 14,
        ]);

        $response->assertOk();

        $this->tenant2->refresh();
        // Should start from now
        $this->assertTrue(abs($this->tenant2->trial_ends_at->diffInDays(now())) >= 13);
    }
}
