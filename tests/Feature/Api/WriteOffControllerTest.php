<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Warehouse;
use App\Models\WriteOff;
use App\Models\WriteOffItem;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\Unit;
use App\Models\Role;
use App\Models\Permission;
use Carbon\Carbon;
use App\Helpers\TimeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WriteOffControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected Role $managerRole;
    protected User $admin;
    protected User $waiter;
    protected User $manager;
    protected User $otherUser;
    protected Warehouse $warehouse;
    protected string $adminToken;
    protected string $waiterToken;
    protected string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if write_offs table doesn't exist
        if (!Schema::hasTable('write_offs')) {
            $this->markTestSkipped('WriteOffs table does not exist - tests require write-offs schema');
        }

        // Create restaurants
        $this->restaurant = Restaurant::factory()->create([
            'write_off_approval_threshold' => 1000,
        ]);

        $this->otherRestaurant = Restaurant::factory()->create();

        // Create admin role with full permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create manager role
        $this->managerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'manager',
            'name' => 'Manager',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create waiter role with limited permissions
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Waiter',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
        ]);

        // Create permissions
        $permissions = [
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
            'inventory.view', 'inventory.create', 'inventory.edit',
            'inventory.manage', 'inventory.ingredients', 'inventory.invoices',
            'inventory.checks', 'inventory.write_off', 'inventory.suppliers', 'inventory.settings',
        ];

        foreach ($permissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
            $this->managerRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Waiter has limited permissions
        $waiterPermissions = ['orders.view', 'orders.create'];
        foreach ($waiterPermissions as $key) {
            $perm = Permission::where('restaurant_id', $this->restaurant->id)
                ->where('key', $key)
                ->first();
            if ($perm) {
                $this->waiterRole->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->restaurant->tenant_id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create manager user with PIN
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->restaurant->tenant_id,
            'role' => 'manager',
            'role_id' => $this->managerRole->id,
            'is_active' => true,
            'pin_lookup' => '1234',
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->restaurant->tenant_id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'pin_lookup' => '5678',
        ]);

        // Create user from another restaurant
        $this->otherUser = User::factory()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;

        // Create warehouse if warehouses table exists
        if (Schema::hasTable('warehouses')) {
            $this->warehouse = Warehouse::create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Main Warehouse',
                'type' => 'main',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/write-offs');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_write_offs(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // INDEX TESTS
    // ============================================

    public function test_can_list_write_offs(): void
    {
        // Create write-offs
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'spoilage',
            'total_amount' => 500,
            'description' => 'Test write-off 1',
        ]);

        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'expired',
            'total_amount' => 300,
            'description' => 'Test write-off 2',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'type_name',
                        'amount',
                        'description',
                        'user',
                        'items',
                        'items_count',
                        'created_at',
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_write_offs_by_type(): void
    {
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'spoilage',
            'total_amount' => 500,
        ]);

        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'loss',
            'total_amount' => 300,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs?type=spoilage');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('spoilage', $response->json('data.0.type'));
    }

    public function test_can_filter_write_offs_by_date_range(): void
    {
        $today = TimeHelper::today($this->restaurant->id);

        // Create write-off today
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'spoilage',
            'total_amount' => 500,
            'created_at' => $today,
        ]);

        // Create write-off 10 days ago
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'loss',
            'total_amount' => 300,
            'created_at' => $today->copy()->subDays(10),
        ]);

        $dateFrom = $today->copy()->subDays(5)->toDateString();
        $dateTo = $today->toDateString();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/write-offs?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ============================================
    // RESTAURANT ISOLATION TESTS
    // ============================================

    public function test_write_offs_are_isolated_by_restaurant(): void
    {
        // Create write-off in main restaurant
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'spoilage',
            'total_amount' => 500,
        ]);

        // Create write-off in other restaurant
        WriteOff::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'user_id' => $this->otherUser->id,
            'type' => 'loss',
            'total_amount' => 300,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs');

        $response->assertOk();
        // Should only see write-offs from own restaurant
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(500, $response->json('data.0.amount'));
    }

    // ============================================
    // STORE TESTS
    // ============================================

    public function test_can_create_write_off_with_amount(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'description' => 'Spoiled ingredients',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Списание создано',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'total_amount',
                    'items_count',
                ]
            ]);

        $this->assertDatabaseHas('write_offs', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'spoilage',
            'total_amount' => 500,
            'description' => 'Spoiled ingredients',
        ]);
    }

    public function test_can_create_write_off_with_items(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'expired',
            'description' => 'Expired products',
            'items' => [
                [
                    'name' => 'Milk',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'item_type' => 'ingredient',
                ],
                [
                    'name' => 'Bread',
                    'quantity' => 3,
                    'unit_price' => 50,
                    'item_type' => 'ingredient',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_amount' => 350, // 2*100 + 3*50
                    'items_count' => 2,
                ],
            ]);

        $this->assertDatabaseHas('write_off_items', [
            'name' => 'Milk',
            'quantity' => 2,
            'unit_price' => 100,
            'total_price' => 200,
        ]);

        $this->assertDatabaseHas('write_off_items', [
            'name' => 'Bread',
            'quantity' => 3,
            'unit_price' => 50,
            'total_price' => 150,
        ]);
    }

    public function test_can_create_write_off_with_items_as_json_string(): void
    {
        $items = json_encode([
            [
                'name' => 'Cheese',
                'quantity' => 1,
                'unit_price' => 200,
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'items' => $items,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(200, $response->json('data.total_amount'));
    }

    public function test_can_create_write_off_with_warehouse(): void
    {
        if (!Schema::hasTable('warehouses')) {
            $this->markTestSkipped('Warehouses table does not exist');
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'loss',
            'amount' => 500,
            'warehouse_id' => $this->warehouse->id,
            'description' => 'Loss from warehouse',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('write_offs', [
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_write_off_validates_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'invalid_type',
            'amount' => 500,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_write_off_requires_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'amount' => 500,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_write_off_requires_items_or_amount(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_write_off_validates_all_types(): void
    {
        $validTypes = ['spoilage', 'expired', 'loss', 'staff_meal', 'promo', 'other'];

        foreach ($validTypes as $type) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->postJson('/api/write-offs', [
                'type' => $type,
                'amount' => 100,
            ]);

            $response->assertStatus(201);
        }
    }

    // ============================================
    // APPROVAL WORKFLOW TESTS
    // ============================================

    public function test_write_off_below_threshold_does_not_require_approval(): void
    {
        // Threshold is 1000, amount is 500
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_write_off_above_threshold_requires_manager_approval(): void
    {
        // Threshold is 1000, amount is 1500
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 1500,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'requires_manager_approval' => true,
                'threshold' => 1000,
                'total_amount' => 1500,
            ]);
    }

    public function test_write_off_above_threshold_succeeds_with_manager_id(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 1500,
            'manager_id' => $this->manager->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('write_offs', [
            'total_amount' => 1500,
            'approved_by' => $this->manager->id,
        ]);
    }

    public function test_write_off_with_items_above_threshold_requires_approval(): void
    {
        // Total: 5*300 = 1500 > threshold 1000
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'expired',
            'items' => [
                [
                    'name' => 'Expensive Item',
                    'quantity' => 5,
                    'unit_price' => 300,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'requires_manager_approval' => true,
            ]);
    }

    // ============================================
    // SHOW TESTS
    // ============================================

    public function test_can_show_write_off(): void
    {
        $writeOff = WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'type' => 'spoilage',
            'total_amount' => 500,
            'description' => 'Test write-off',
        ]);

        WriteOffItem::create([
            'write_off_id' => $writeOff->id,
            'item_type' => 'manual',
            'name' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/write-offs/{$writeOff->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $writeOff->id,
                    'type' => 'spoilage',
                    'amount' => 500,
                    'description' => 'Test write-off',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'type',
                    'type_name',
                    'amount',
                    'description',
                    'photo_url',
                    'user',
                    'approved_by',
                    'warehouse',
                    'items' => [
                        '*' => [
                            'id',
                            'item_type',
                            'item_type_name',
                            'name',
                            'quantity',
                            'unit_price',
                            'total_price',
                        ]
                    ],
                    'created_at',
                ],
            ]);
    }

    public function test_show_write_off_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs/99999');

        $response->assertNotFound();
    }

    public function test_show_write_off_includes_approved_by_user(): void
    {
        $writeOff = WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'approved_by' => $this->manager->id,
            'type' => 'spoilage',
            'total_amount' => 1500,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/write-offs/{$writeOff->id}");

        $response->assertOk()
            ->assertJsonPath('data.approved_by.id', $this->manager->id);
    }

    // ============================================
    // SETTINGS TESTS
    // ============================================

    public function test_can_get_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs/settings');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'approval_threshold' => 1000,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'approval_threshold',
                    'types',
                ],
            ]);
    }

    public function test_settings_returns_write_off_types(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs/settings');

        $response->assertOk();

        $types = $response->json('data.types');
        $this->assertArrayHasKey('spoilage', $types);
        $this->assertArrayHasKey('expired', $types);
        $this->assertArrayHasKey('loss', $types);
        $this->assertArrayHasKey('staff_meal', $types);
        $this->assertArrayHasKey('promo', $types);
        $this->assertArrayHasKey('other', $types);
    }

    // ============================================
    // VERIFY MANAGER TESTS
    // ============================================

    public function test_can_verify_manager_pin(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '1234',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'manager_id' => $this->manager->id,
                    'manager_name' => $this->manager->name,
                    'role' => 'manager',
                ],
            ]);
    }

    public function test_verify_manager_fails_with_invalid_pin(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '9999',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ]);
    }

    public function test_verify_manager_fails_for_non_manager_role(): void
    {
        // Waiter has PIN 5678 but is not a manager
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '5678',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Недостаточно прав. Требуется менеджер или выше.',
            ]);
    }

    public function test_verify_manager_validates_pin_format(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '12', // Too short
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_verify_manager_requires_pin(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_verify_manager_accepts_admin_role(): void
    {
        // Create admin with PIN
        $adminWithPin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
            'pin_lookup' => '4321',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '4321',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'manager_id' => $adminWithPin->id,
                    'role' => 'admin',
                ],
            ]);
    }

    public function test_verify_manager_accepts_owner_role(): void
    {
        $owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'owner',
            'is_active' => true,
            'pin_lookup' => '0000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs/verify-manager', [
            'pin' => '0000',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'manager_id' => $owner->id,
                    'role' => 'owner',
                ],
            ]);
    }

    // ============================================
    // STOCK DEDUCTION TESTS
    // ============================================

    public function test_write_off_deducts_from_inventory_when_warehouse_set(): void
    {
        if (!Schema::hasTable('warehouses') || !Schema::hasTable('ingredients') || !Schema::hasTable('ingredient_stocks')) {
            $this->markTestSkipped('Required inventory tables do not exist');
        }

        // Create unit
        $unit = Unit::create([
            'name' => 'Kilogram',
            'short_name' => 'kg',
            'type' => 'weight',
            'base_ratio' => 1,
        ]);

        // Create category
        $category = IngredientCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Category',
        ]);

        // Create ingredient
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Ingredient',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        // Create stock
        IngredientStock::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'name' => $ingredient->name,
                    'item_type' => 'ingredient',
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 3,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // Check stock was deducted
        $stock = IngredientStock::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertEquals(7, $stock->quantity);
    }

    public function test_write_off_does_not_deduct_without_warehouse(): void
    {
        if (!Schema::hasTable('ingredients') || !Schema::hasTable('ingredient_stocks') || !Schema::hasTable('warehouses')) {
            $this->markTestSkipped('Required inventory tables do not exist');
        }

        // Create unit
        $unit = Unit::create([
            'name' => 'Piece',
            'short_name' => 'pc',
            'type' => 'piece',
            'base_ratio' => 1,
        ]);

        // Create category
        $category = IngredientCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Category 2',
        ]);

        // Create ingredient
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Ingredient 2',
            'cost_price' => 50,
            'track_stock' => true,
            'is_active' => true,
        ]);

        // Create stock
        IngredientStock::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 20,
        ]);

        // Create write-off without warehouse
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'loss',
            'items' => [
                [
                    'name' => $ingredient->name,
                    'item_type' => 'ingredient',
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 5,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // Stock should remain unchanged
        $stock = IngredientStock::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertEquals(20, $stock->quantity);
    }

    // ============================================
    // PHOTO UPLOAD TESTS
    // ============================================

    public function test_can_create_write_off_with_photo(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        Storage::fake('public');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'description' => 'Spoiled product with photo',
            'photo' => UploadedFile::fake()->image('spoilage.jpg', 800, 600),
        ]);

        $response->assertStatus(201);

        $writeOff = WriteOff::latest()->first();
        $this->assertNotNull($writeOff->photo_path);
    }

    public function test_write_off_validates_photo_type(): void
    {
        Storage::fake('public');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'photo' => UploadedFile::fake()->create('document.pdf', 1024),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_write_off_validates_photo_size(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        Storage::fake('public');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'photo' => UploadedFile::fake()->image('large.jpg')->size(6000), // 6MB > 5MB limit
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    // ============================================
    // EDGE CASES
    // ============================================

    public function test_write_off_with_zero_quantity_items(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'other',
            'items' => [
                [
                    'name' => 'Test Item',
                    'quantity' => 0,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(0, $response->json('data.total_amount'));
    }

    public function test_write_off_description_max_length(): void
    {
        $longDescription = str_repeat('a', 1001);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'description' => $longDescription,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_write_off_with_different_item_types(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'staff_meal',
            'items' => [
                [
                    'name' => 'Dish Item',
                    'item_type' => 'dish',
                    'dish_id' => null,
                    'quantity' => 1,
                    'unit_price' => 500,
                ],
                [
                    'name' => 'Ingredient Item',
                    'item_type' => 'ingredient',
                    'ingredient_id' => null,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
                [
                    'name' => 'Manual Item',
                    'item_type' => 'manual',
                    'quantity' => 1,
                    'unit_price' => 150,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(850, $response->json('data.total_amount')); // 500 + 200 + 150
        $this->assertEquals(3, $response->json('data.items_count'));
    }

    public function test_write_off_creates_manual_item_when_only_amount(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'other',
            'amount' => 750,
            'description' => 'Manual amount entry',
        ]);

        $response->assertStatus(201);

        $writeOff = WriteOff::latest()->first();
        $this->assertCount(1, $writeOff->items);
        $this->assertEquals('manual', $writeOff->items->first()->item_type);
        $this->assertEquals(750, $writeOff->items->first()->total_price);
    }

    // ============================================
    // TYPE NAME ATTRIBUTE TESTS
    // ============================================

    public function test_write_off_returns_type_names(): void
    {
        $typeExpectations = [
            'spoilage' => 'Порча продукта',
            'expired' => 'Истек срок годности',
            'loss' => 'Потеря/недостача',
            'staff_meal' => 'Питание персонала',
            'promo' => 'Промо/дегустация',
            'other' => 'Другое',
        ];

        foreach ($typeExpectations as $type => $expectedName) {
            $writeOff = WriteOff::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->admin->id,
                'type' => $type,
                'total_amount' => 100,
            ]);

            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->getJson("/api/write-offs/{$writeOff->id}");

            $response->assertOk()
                ->assertJsonPath('data.type_name', $expectedName);
        }
    }

    // ============================================
    // USER ASSOCIATION TESTS
    // ============================================

    public function test_write_off_is_associated_with_authenticated_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
        ]);

        $response->assertStatus(201);

        $writeOff = WriteOff::latest()->first();
        $this->assertEquals($this->waiter->id, $writeOff->user_id);
    }

    public function test_write_off_index_returns_user_info(): void
    {
        WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'type' => 'spoilage',
            'total_amount' => 500,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/write-offs');

        $response->assertOk()
            ->assertJsonPath('data.0.user.id', $this->waiter->id)
            ->assertJsonPath('data.0.user.name', $this->waiter->name);
    }

    // ============================================
    // WAREHOUSE RELATIONSHIP TESTS
    // ============================================

    public function test_write_off_includes_warehouse_info(): void
    {
        if (!Schema::hasTable('warehouses')) {
            $this->markTestSkipped('Warehouses table does not exist');
        }

        $writeOff = WriteOff::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'spoilage',
            'total_amount' => 500,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/write-offs/{$writeOff->id}");

        $response->assertOk()
            ->assertJsonPath('data.warehouse.id', $this->warehouse->id)
            ->assertJsonPath('data.warehouse.name', $this->warehouse->name);
    }

    public function test_write_off_validates_warehouse_exists(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/write-offs', [
            'type' => 'spoilage',
            'amount' => 500,
            'warehouse_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    }
}
