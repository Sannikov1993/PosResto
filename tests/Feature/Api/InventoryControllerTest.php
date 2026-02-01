<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Warehouse;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\Unit;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\IngredientPackaging;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Warehouse $warehouse;
    protected Unit $unit;
    protected IngredientCategory $category;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if the warehouses table doesn't exist (old schema)
        if (!Schema::hasTable('warehouses')) {
            $this->markTestSkipped('Warehouses table does not exist - tests require new inventory schema');
        }

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with inventory permissions
        $adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create inventory permissions
        $inventoryPermissions = [
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
        ];
        foreach ($inventoryPermissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => 'inventory',
            ]);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        // Authenticate using Sanctum token
        $this->authenticate();

        // Create warehouse
        $this->warehouse = Warehouse::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Warehouse',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create unit - handle both old and new schema
        $this->unit = $this->createUnit([
            'name' => 'Kilogram',
            'short_name' => 'kg',
            'type' => 'weight',
            'base_ratio' => 1,
        ], true);

        // Create category - handle both old and new schema
        $this->category = $this->createCategory([
            'name' => 'Meat',
            'sort_order' => 1,
        ]);
    }

    /**
     * Authenticate using Sanctum token for API routes with auth.api_token middleware
     */
    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Create a unit - handles both old schema (no restaurant_id) and new schema
     */
    protected function createUnit(array $data, bool $isSystem = false): Unit
    {
        $hasRestaurantId = Schema::hasColumn('units', 'restaurant_id');
        $hasTimestamps = Schema::hasColumn('units', 'created_at');
        $hasIsSystem = Schema::hasColumn('units', 'is_system');

        $insertData = [
            'name' => $data['name'],
            'short_name' => $data['short_name'],
            'type' => $data['type'] ?? 'piece',
            'base_ratio' => $data['base_ratio'] ?? 1,
        ];

        if ($hasRestaurantId) {
            $insertData['restaurant_id'] = $this->restaurant->id;
        }
        if ($hasIsSystem) {
            $insertData['is_system'] = $isSystem;
        }
        if ($hasTimestamps) {
            $insertData['created_at'] = now();
            $insertData['updated_at'] = now();
        }

        $id = DB::table('units')->insertGetId($insertData);
        return Unit::find($id);
    }

    /**
     * Create a category - handles both old and new schema
     */
    protected function createCategory(array $data): IngredientCategory
    {
        $hasTimestamps = Schema::hasColumn('ingredient_categories', 'created_at');
        $hasColor = Schema::hasColumn('ingredient_categories', 'color');

        $insertData = [
            'restaurant_id' => $this->restaurant->id,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
        ];

        if ($hasColor) {
            $insertData['color'] = $data['color'] ?? '#6b7280';
        }
        if ($hasTimestamps) {
            $insertData['created_at'] = now();
            $insertData['updated_at'] = now();
        }

        $id = DB::table('ingredient_categories')->insertGetId($insertData);
        return IngredientCategory::find($id);
    }

    /**
     * Check if stock_movements table supports warehouse_id (new schema)
     */
    protected function stockMovementsHasWarehouseId(): bool
    {
        return Schema::hasColumn('stock_movements', 'warehouse_id');
    }

    /**
     * Create a stock movement - handles both old and new schema
     */
    protected function createStockMovement(array $data): StockMovement
    {
        $hasWarehouseId = $this->stockMovementsHasWarehouseId();
        $hasMovementDate = Schema::hasColumn('stock_movements', 'movement_date');

        $insertData = [
            'restaurant_id' => $data['restaurant_id'] ?? $this->restaurant->id,
            'ingredient_id' => $data['ingredient_id'],
            'type' => $data['type'] ?? 'income',
            'quantity' => $data['quantity'],
        ];

        if ($hasWarehouseId && isset($data['warehouse_id'])) {
            $insertData['warehouse_id'] = $data['warehouse_id'];
        }

        if ($hasMovementDate) {
            $insertData['movement_date'] = $data['movement_date'] ?? now();
        }

        // Old schema has quantity_before, quantity_after instead of cost_price, total_cost
        if (Schema::hasColumn('stock_movements', 'quantity_before')) {
            $insertData['quantity_before'] = $data['quantity_before'] ?? 0;
            $insertData['quantity_after'] = $data['quantity_after'] ?? $data['quantity'];
        }

        if (Schema::hasColumn('stock_movements', 'cost_price')) {
            $insertData['cost_price'] = $data['cost_price'] ?? 0;
            $insertData['total_cost'] = $data['total_cost'] ?? 0;
        }

        if (isset($data['user_id']) && Schema::hasColumn('stock_movements', 'user_id')) {
            $insertData['user_id'] = $data['user_id'];
        }

        $id = DB::table('stock_movements')->insertGetId($insertData);
        return StockMovement::find($id);
    }

    // ==========================================
    // WAREHOUSES TESTS
    // ==========================================

    public function test_can_list_warehouses(): void
    {
        Warehouse::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kitchen',
            'type' => 'kitchen',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/inventory/warehouses?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'type', 'is_default', 'is_active']
                ]
            ]);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_can_filter_active_warehouses(): void
    {
        Warehouse::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Inactive Warehouse',
            'type' => 'storage',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/inventory/warehouses?restaurant_id={$this->restaurant->id}&active_only=1");

        $response->assertOk();

        foreach ($response->json('data') as $warehouse) {
            $this->assertTrue($warehouse['is_active']);
        }
    }

    public function test_can_create_warehouse(): void
    {
        $response = $this->postJson('/api/inventory/warehouses', [
            'name' => 'Bar Storage',
            'type' => 'bar',
            'address' => 'Address',
            'description' => 'Bar storage room',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Склад создан',
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Bar Storage',
            'type' => 'bar',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_warehouse_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inventory/warehouses', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_warehouse(): void
    {
        $response = $this->putJson("/api/inventory/warehouses/{$this->warehouse->id}", [
            'name' => 'Updated Warehouse Name',
            'type' => 'storage',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Склад обновлён',
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Updated Warehouse Name',
            'type' => 'storage',
        ]);
    }

    public function test_can_set_warehouse_as_default(): void
    {
        $secondWarehouse = Warehouse::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Second Warehouse',
            'type' => 'storage',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->putJson("/api/inventory/warehouses/{$secondWarehouse->id}", [
            'is_default' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('warehouses', [
            'id' => $secondWarehouse->id,
            'is_default' => true,
        ]);

        // Original default should be removed
        $this->warehouse->refresh();
        $this->assertFalse($this->warehouse->is_default);
    }

    public function test_cannot_delete_warehouse_with_stock(): void
    {
        // Create second warehouse so we can try to delete main one
        Warehouse::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Second',
            'type' => 'storage',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Beef',
            'cost_price' => 500,
            'min_stock' => 10,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'avg_cost' => 500,
        ]);

        $response = $this->deleteJson("/api/inventory/warehouses/{$this->warehouse->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить склад с остатками товаров',
            ]);
    }

    public function test_cannot_delete_last_warehouse(): void
    {
        $response = $this->deleteJson("/api/inventory/warehouses/{$this->warehouse->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить единственный склад',
            ]);
    }

    public function test_can_get_warehouse_types(): void
    {
        $response = $this->getJson('/api/inventory/warehouse-types');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $this->assertArrayHasKey('main', $response->json('data'));
        $this->assertArrayHasKey('kitchen', $response->json('data'));
        $this->assertArrayHasKey('bar', $response->json('data'));
    }

    // ==========================================
    // INGREDIENTS TESTS
    // ==========================================

    public function test_can_list_ingredients(): void
    {
        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'name' => 'Chicken',
            'cost_price' => 300,
            'min_stock' => 5,
            'track_stock' => true,
            'is_active' => true,
        ]);

        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'name' => 'Pork',
            'cost_price' => 400,
            'min_stock' => 5,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/inventory/ingredients?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'unit_id', 'cost_price', 'min_stock']
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_ingredients_by_category(): void
    {
        $otherCategory = $this->createCategory([
            'name' => 'Vegetables',
            'sort_order' => 2,
        ]);

        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'name' => 'Chicken',
            'is_active' => true,
        ]);

        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $otherCategory->id,
            'name' => 'Carrot',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/inventory/ingredients?restaurant_id={$this->restaurant->id}&category_id={$this->category->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Chicken', $response->json('data.0.name'));
    }

    public function test_can_search_ingredients(): void
    {
        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Chicken Breast',
            'sku' => 'CHK-001',
            'is_active' => true,
        ]);

        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Beef Steak',
            'sku' => 'BEEF-001',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/inventory/ingredients?restaurant_id={$this->restaurant->id}&search=chicken");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Chicken Breast', $response->json('data.0.name'));
    }

    public function test_can_create_ingredient(): void
    {
        $response = $this->postJson('/api/inventory/ingredients', [
            'name' => 'Fresh Salmon',
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'sku' => 'SAL-001',
            'barcode' => '1234567890',
            'cost_price' => 800,
            'min_stock' => 5,
            'max_stock' => 50,
            'shelf_life_days' => 3,
            'track_stock' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ингредиент создан',
            ]);

        $this->assertDatabaseHas('ingredients', [
            'name' => 'Fresh Salmon',
            'sku' => 'SAL-001',
            'cost_price' => 800,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_ingredient_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inventory/ingredients', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'unit_id']);
    }

    public function test_can_create_ingredient_with_initial_stock(): void
    {
        // Skip if stock_movements doesn't have warehouse_id - creating with initial stock creates movements
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $response = $this->postJson('/api/inventory/ingredients', [
            'name' => 'Beef Tenderloin',
            'unit_id' => $this->unit->id,
            'cost_price' => 1200,
            'initial_stock' => 25,
            'warehouse_id' => $this->warehouse->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $ingredient = Ingredient::where('name', 'Beef Tenderloin')->first();
        $this->assertNotNull($ingredient);

        $stock = IngredientStock::where('ingredient_id', $ingredient->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals(25, $stock->quantity);
    }

    public function test_can_show_ingredient(): void
    {
        // Skip if recipes table uses old schema (no ingredient_id) - controller loads recipes relation
        if (!Schema::hasColumn('recipes', 'ingredient_id')) {
            $this->markTestSkipped('recipes table uses old schema without ingredient_id column');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'name' => 'Test Ingredient',
            'cost_price' => 500,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/inventory/ingredients/{$ingredient->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['id' => $ingredient->id, 'name' => 'Test Ingredient']
            ]);
    }

    public function test_can_update_ingredient(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Original Name',
            'cost_price' => 500,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/inventory/ingredients/{$ingredient->id}", [
            'name' => 'Updated Name',
            'cost_price' => 600,
            'min_stock' => 10,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Ингредиент обновлён',
            ]);

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'name' => 'Updated Name',
            'cost_price' => 600,
            'min_stock' => 10,
        ]);
    }

    public function test_can_delete_ingredient(): void
    {
        // Skip if recipes table uses old schema (no ingredient_id) - model's recipes() relationship fails
        if (!Schema::hasColumn('recipes', 'ingredient_id')) {
            $this->markTestSkipped('recipes table uses old schema without ingredient_id column');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'To Delete',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/inventory/ingredients/{$ingredient->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Ингредиент удалён',
            ]);

        $this->assertSoftDeleted('ingredients', ['id' => $ingredient->id]);
    }

    // ==========================================
    // INGREDIENT CATEGORIES TESTS
    // ==========================================

    public function test_can_list_ingredient_categories(): void
    {
        $this->createCategory([
            'name' => 'Fish',
            'sort_order' => 2,
        ]);

        $response = $this->getJson("/api/inventory/categories?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name']
                ]
            ]);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_can_create_ingredient_category(): void
    {
        // Skip if ingredient_categories doesn't have timestamps - model tries to set created_at/updated_at
        if (!Schema::hasColumn('ingredient_categories', 'created_at')) {
            $this->markTestSkipped('ingredient_categories table does not have timestamps in this schema');
        }

        // Build request data based on available columns
        $requestData = [
            'name' => 'Dairy Products',
            'icon' => '',
            'restaurant_id' => $this->restaurant->id,
        ];

        // Add color only if column exists
        if (Schema::hasColumn('ingredient_categories', 'color')) {
            $requestData['color'] = '#eab308';
        }

        $response = $this->postJson('/api/inventory/categories', $requestData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Категория создана',
            ]);

        $this->assertDatabaseHas('ingredient_categories', [
            'name' => 'Dairy Products',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_category_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inventory/categories', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_ingredient_category(): void
    {
        // Skip if ingredient_categories doesn't have timestamps - model tries to update updated_at
        if (!Schema::hasColumn('ingredient_categories', 'updated_at')) {
            $this->markTestSkipped('ingredient_categories table does not have timestamps in this schema');
        }

        // Build request data based on available columns
        $requestData = [
            'name' => 'Updated Meat Category',
        ];

        // Add color only if column exists
        if (Schema::hasColumn('ingredient_categories', 'color')) {
            $requestData['color'] = '#ff0000';
        }

        $response = $this->putJson("/api/inventory/categories/{$this->category->id}", $requestData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Категория обновлена',
            ]);

        $this->assertDatabaseHas('ingredient_categories', [
            'id' => $this->category->id,
            'name' => 'Updated Meat Category',
        ]);
    }

    public function test_cannot_delete_category_with_ingredients(): void
    {
        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'name' => 'Chicken',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/inventory/categories/{$this->category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить категорию с ингредиентами',
            ]);
    }

    public function test_can_delete_empty_category(): void
    {
        $emptyCategory = $this->createCategory([
            'name' => 'Empty Category',
            'sort_order' => 10,
        ]);

        $response = $this->deleteJson("/api/inventory/categories/{$emptyCategory->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Категория удалена',
            ]);

        $this->assertDatabaseMissing('ingredient_categories', ['id' => $emptyCategory->id]);
    }

    // ==========================================
    // UNITS TESTS
    // ==========================================

    public function test_can_list_units(): void
    {
        $this->createUnit([
            'name' => 'Gram',
            'short_name' => 'g',
            'type' => 'weight',
            'base_ratio' => 0.001,
        ], true);

        $response = $this->getJson("/api/inventory/units?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'short_name', 'type']
                ]
            ]);
    }

    public function test_can_create_custom_unit(): void
    {
        // Skip if units table doesn't support restaurant_id
        if (!Schema::hasColumn('units', 'restaurant_id')) {
            $this->markTestSkipped('Units table does not support restaurant_id in this schema');
        }

        $response = $this->postJson('/api/inventory/units', [
            'name' => 'Package',
            'short_name' => 'pkg',
            'type' => 'piece',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Единица измерения создана',
            ]);

        $this->assertDatabaseHas('units', [
            'name' => 'Package',
            'short_name' => 'pkg',
        ]);
    }

    public function test_cannot_update_system_unit(): void
    {
        // Skip if units table doesn't have is_system column
        if (!Schema::hasColumn('units', 'is_system')) {
            $this->markTestSkipped('Units table does not support is_system in this schema');
        }

        $response = $this->putJson("/api/inventory/units/{$this->unit->id}", [
            'name' => 'Modified Kilogram',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Системные единицы измерения нельзя редактировать',
            ]);
    }

    public function test_cannot_delete_system_unit(): void
    {
        // Skip if units table doesn't have is_system column
        if (!Schema::hasColumn('units', 'is_system')) {
            $this->markTestSkipped('Units table does not support is_system in this schema');
        }

        $response = $this->deleteJson("/api/inventory/units/{$this->unit->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Системные единицы измерения нельзя удалять',
            ]);
    }

    public function test_cannot_delete_unit_used_by_ingredients(): void
    {
        $customUnit = $this->createUnit([
            'name' => 'Custom Unit',
            'short_name' => 'cu',
            'type' => 'piece',
        ], false);

        Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $customUnit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/inventory/units/{$customUnit->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Единица измерения используется в ингредиентах',
            ]);
    }

    // ==========================================
    // SUPPLIERS TESTS
    // ==========================================

    public function test_can_list_suppliers(): void
    {
        Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Meat Supplier',
            'contact_person' => 'John Doe',
            'phone' => '+79001234567',
            'is_active' => true,
        ]);

        Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Fish Supplier',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/inventory/suppliers?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'is_active']
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_active_suppliers(): void
    {
        Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Active Supplier',
            'is_active' => true,
        ]);

        Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Inactive Supplier',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/inventory/suppliers?restaurant_id={$this->restaurant->id}&active_only=1");

        $response->assertOk();

        foreach ($response->json('data') as $supplier) {
            $this->assertTrue($supplier['is_active']);
        }
    }

    public function test_can_create_supplier(): void
    {
        // Build request data based on available columns
        $requestData = [
            'name' => 'New Supplier LLC',
            'contact_person' => 'Jane Smith',
            'phone' => '+79009876543',
            'email' => 'supplier@example.com',
            'address' => '123 Main St',
            'restaurant_id' => $this->restaurant->id,
        ];

        // Add optional columns if they exist
        if (Schema::hasColumn('suppliers', 'inn')) {
            $requestData['inn'] = '1234567890';
        }
        if (Schema::hasColumn('suppliers', 'payment_terms')) {
            $requestData['payment_terms'] = 'Net 30';
        }
        if (Schema::hasColumn('suppliers', 'delivery_days')) {
            $requestData['delivery_days'] = 2;
        }
        if (Schema::hasColumn('suppliers', 'min_order_amount')) {
            $requestData['min_order_amount'] = 5000;
        }

        $response = $this->postJson('/api/inventory/suppliers', $requestData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Поставщик добавлен',
            ]);

        $assertData = [
            'name' => 'New Supplier LLC',
            'email' => 'supplier@example.com',
            'restaurant_id' => $this->restaurant->id,
        ];

        // Only check inn if column exists
        if (Schema::hasColumn('suppliers', 'inn')) {
            $assertData['inn'] = '1234567890';
        }

        $this->assertDatabaseHas('suppliers', $assertData);
    }

    public function test_create_supplier_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inventory/suppliers', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        // Build request data based on available columns
        $requestData = [
            'name' => 'Updated Supplier Name',
            'phone' => '+79001111111',
        ];

        $assertData = [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Name',
        ];

        // Add delivery_days only if column exists
        if (Schema::hasColumn('suppliers', 'delivery_days')) {
            $requestData['delivery_days'] = 3;
            $assertData['delivery_days'] = 3;
        }

        $response = $this->putJson("/api/inventory/suppliers/{$supplier->id}", $requestData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Поставщик обновлён',
            ]);

        $this->assertDatabaseHas('suppliers', $assertData);
    }

    public function test_can_delete_supplier_without_invoices(): void
    {
        $supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'To Delete',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/inventory/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Поставщик удалён',
            ]);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_with_invoices_is_deactivated_not_deleted(): void
    {
        $supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Supplier With Invoices',
            'is_active' => true,
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test Ingredient',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'completed',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'cost_price' => 100,
            'total' => 1000,
        ]);

        $response = $this->deleteJson("/api/inventory/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Поставщик деактивирован (есть связанные накладные)',
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => false,
        ]);
    }

    // ==========================================
    // QUICK OPERATIONS (STOCK IN/OUT)
    // ==========================================

    public function test_can_quick_income(): void
    {
        // Skip if stock_movements doesn't have warehouse_id - required by Ingredient::addStock()
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Ingredient for Income',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/inventory/quick-income', [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'cost_price' => 120,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход оформлен',
            ]);

        $stock = IngredientStock::where('ingredient_id', $ingredient->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(50, $stock->quantity);

        // Check that cost price was updated
        $ingredient->refresh();
        $this->assertEquals(120, $ingredient->cost_price);
    }

    public function test_quick_income_creates_stock_movement(): void
    {
        // Skip if stock_movements doesn't have warehouse_id
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test Ingredient',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/inventory/quick-income', [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 25,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'ingredient_id' => $ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'income',
            'quantity' => 25,
        ]);
    }

    public function test_can_quick_write_off(): void
    {
        // Skip if stock_movements doesn't have warehouse_id - required by Ingredient::writeOff()
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Ingredient for Write Off',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        // Add stock first
        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'avg_cost' => 100,
        ]);

        $response = $this->postJson('/api/inventory/quick-write-off', [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 20,
            'reason' => 'Expired product',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Списание оформлено',
            ]);

        $stock = IngredientStock::where('ingredient_id', $ingredient->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(80, $stock->quantity);
    }

    public function test_quick_write_off_fails_with_insufficient_stock(): void
    {
        // Skip if stock_movements doesn't have warehouse_id
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'avg_cost' => 100,
        ]);

        $response = $this->postJson('/api/inventory/quick-write-off', [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'reason' => 'Test',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Недостаточно остатка', $response->json('message'));
    }

    // ==========================================
    // STOCK MOVEMENTS TESTS
    // ==========================================

    public function test_can_list_movements(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'cost_price' => 100,
            'is_active' => true,
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'quantity' => 50,
            'cost_price' => 100,
            'total_cost' => 5000,
            'movement_date' => now(),
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'user_id' => $this->user->id,
            'type' => 'expense',
            'quantity' => -10,
            'cost_price' => 100,
            'total_cost' => 1000,
            'movement_date' => now(),
        ]);

        $response = $this->getJson("/api/inventory/movements?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Check structure based on schema
        if ($this->stockMovementsHasWarehouseId()) {
            $response->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'type', 'quantity', 'ingredient_id', 'warehouse_id']
                ]
            ]);
        } else {
            $response->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'type', 'quantity', 'ingredient_id']
                ]
            ]);
        }

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_movements_by_type(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'income',
            'quantity' => 50,
            'movement_date' => now(),
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'write_off',
            'quantity' => -5,
            'movement_date' => now(),
        ]);

        $response = $this->getJson("/api/inventory/movements?restaurant_id={$this->restaurant->id}&type=income");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('income', $response->json('data.0.type'));
    }

    public function test_can_filter_movements_by_ingredient(): void
    {
        $ingredient1 = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Ingredient 1',
            'is_active' => true,
        ]);

        $ingredient2 = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Ingredient 2',
            'is_active' => true,
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient1->id,
            'type' => 'income',
            'quantity' => 50,
            'movement_date' => now(),
        ]);

        $this->createStockMovement([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient2->id,
            'type' => 'income',
            'quantity' => 30,
            'movement_date' => now(),
        ]);

        $response = $this->getJson("/api/inventory/movements?restaurant_id={$this->restaurant->id}&ingredient_id={$ingredient1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($ingredient1->id, $response->json('data.0.ingredient_id'));
    }

    // ==========================================
    // INVOICES TESTS
    // ==========================================

    public function test_can_list_invoices(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'draft',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'cost_price' => 100,
            'total' => 1000,
        ]);

        $response = $this->getJson("/api/inventory/invoices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'type', 'number', 'status']
                ]
            ]);
    }

    public function test_can_create_income_invoice(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test Ingredient',
            'cost_price' => 100,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/inventory/invoices', [
            'type' => 'income',
            'warehouse_id' => $this->warehouse->id,
            'invoice_date' => now()->toDateString(),
            'notes' => 'Test invoice',
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 100,
                    'cost_price' => 150,
                ],
            ],
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Накладная создана',
            ]);

        $this->assertDatabaseHas('invoices', [
            'type' => 'income',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'cost_price' => 150,
        ]);
    }

    public function test_create_invoice_validates_required_fields(): void
    {
        $response = $this->postJson('/api/inventory/invoices', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'warehouse_id', 'items']);
    }

    public function test_can_show_invoice(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'draft',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'cost_price' => 200,
            'total' => 10000,
        ]);

        $response = $this->getJson("/api/inventory/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'type' => 'income',
                    'number' => 'INV-001',
                ],
            ]);
    }

    public function test_can_complete_invoice(): void
    {
        // Skip if stock_movements doesn't have warehouse_id - invoice completion creates movements
        if (!$this->stockMovementsHasWarehouseId()) {
            $this->markTestSkipped('stock_movements table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'cost_price' => 100,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'draft',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'cost_price' => 100,
            'total' => 5000,
        ]);

        $response = $this->postJson("/api/inventory/invoices/{$invoice->id}/complete", [
            'user_id' => $this->user->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Накладная проведена',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'completed',
        ]);

        // Check stock was added
        $stock = IngredientStock::where('ingredient_id', $ingredient->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(50, $stock->quantity);
    }

    public function test_cannot_complete_already_completed_invoice(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'completed',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'cost_price' => 100,
            'total' => 5000,
        ]);

        $response = $this->postJson("/api/inventory/invoices/{$invoice->id}/complete");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Накладная уже проведена',
            ]);
    }

    public function test_can_cancel_draft_invoice(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'draft',
            'invoice_date' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'cost_price' => 100,
            'total' => 5000,
        ]);

        $response = $this->postJson("/api/inventory/invoices/{$invoice->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Накладная отменена',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_completed_invoice(): void
    {
        $invoice = Invoice::create([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'number' => 'INV-001',
            'status' => 'completed',
            'invoice_date' => now(),
        ]);

        $response = $this->postJson("/api/inventory/invoices/{$invoice->id}/cancel");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя отменить проведённую накладную',
            ]);
    }

    // ==========================================
    // INVENTORY CHECKS TESTS
    // ==========================================

    /**
     * Check if inventory_checks table has warehouse_id column (new schema)
     */
    protected function inventoryChecksHasWarehouseId(): bool
    {
        return Schema::hasColumn('inventory_checks', 'warehouse_id');
    }

    /**
     * Check if inventory_check_items table has cost_price column (new schema)
     */
    protected function inventoryCheckItemsHasCostPrice(): bool
    {
        return Schema::hasColumn('inventory_check_items', 'cost_price');
    }

    /**
     * Create an inventory check - handles both old and new schema
     */
    protected function createInventoryCheck(array $data): InventoryCheck
    {
        $hasWarehouseId = $this->inventoryChecksHasWarehouseId();

        $insertData = [
            'restaurant_id' => $data['restaurant_id'] ?? $this->restaurant->id,
            'created_by' => $data['created_by'] ?? $this->user->id,
            'number' => $data['number'],
            'status' => $data['status'] ?? 'draft',
            'date' => $data['date'] ?? now()->toDateString(),
        ];

        if ($hasWarehouseId && isset($data['warehouse_id'])) {
            $insertData['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['notes'])) {
            $insertData['notes'] = $data['notes'];
        }

        $insertData['created_at'] = now();
        $insertData['updated_at'] = now();

        $id = DB::table('inventory_checks')->insertGetId($insertData);
        return InventoryCheck::find($id);
    }

    /**
     * Create an inventory check item - handles both old and new schema
     */
    protected function createInventoryCheckItem(array $data): InventoryCheckItem
    {
        $hasCostPrice = $this->inventoryCheckItemsHasCostPrice();

        $insertData = [
            'inventory_check_id' => $data['inventory_check_id'],
            'ingredient_id' => $data['ingredient_id'],
            'expected_quantity' => $data['expected_quantity'] ?? 0,
        ];

        if (isset($data['actual_quantity'])) {
            $insertData['actual_quantity'] = $data['actual_quantity'];
        }

        if (isset($data['difference'])) {
            $insertData['difference'] = $data['difference'];
        }

        if (isset($data['notes'])) {
            $insertData['notes'] = $data['notes'];
        }

        if ($hasCostPrice && isset($data['cost_price'])) {
            $insertData['cost_price'] = $data['cost_price'];
        }

        $id = DB::table('inventory_check_items')->insertGetId($insertData);
        return InventoryCheckItem::find($id);
    }

    public function test_can_list_inventory_checks(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'draft',
            'date' => now()->toDateString(),
        ]);

        $response = $this->getJson("/api/inventory/checks?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'number', 'status', 'date']
                ]
            ]);
    }

    public function test_can_create_inventory_check(): void
    {
        // Skip if inventory_checks doesn't have warehouse_id
        if (!Schema::hasColumn('inventory_checks', 'warehouse_id')) {
            $this->markTestSkipped('inventory_checks table does not have warehouse_id column in this schema');
        }

        // Create ingredient with stock
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test Ingredient',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'avg_cost' => 100,
        ]);

        $response = $this->postJson('/api/inventory/checks', [
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Monthly inventory check',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Инвентаризация создана',
            ]);

        $this->assertDatabaseHas('inventory_checks', [
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        // Check that items were populated from stock
        $check = InventoryCheck::where('warehouse_id', $this->warehouse->id)->first();
        $this->assertDatabaseHas('inventory_check_items', [
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
        ]);
    }

    public function test_cannot_create_duplicate_inventory_check(): void
    {
        // Skip if inventory_checks doesn't have warehouse_id
        if (!$this->inventoryChecksHasWarehouseId()) {
            $this->markTestSkipped('inventory_checks table does not have warehouse_id column in this schema');
        }

        $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $response = $this->postJson('/api/inventory/checks', [
            'warehouse_id' => $this->warehouse->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Для этого склада уже есть незавершённая инвентаризация',
            ]);
    }

    public function test_can_show_inventory_check(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'draft',
            'date' => now()->toDateString(),
        ]);

        $response = $this->getJson("/api/inventory/checks/{$check->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $check->id,
                    'number' => 'INV-CHECK-001',
                ],
            ]);
    }

    public function test_can_update_inventory_check_item(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'cost_price' => 100,
            'is_active' => true,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'draft',
            'date' => now()->toDateString(),
        ]);

        $item = $this->createInventoryCheckItem([
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
            'cost_price' => 100,
        ]);

        $response = $this->putJson("/api/inventory/checks/{$check->id}/items/{$item->id}", [
            'actual_quantity' => 48,
            'notes' => 'Minor discrepancy',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция обновлена',
            ]);

        $this->assertDatabaseHas('inventory_check_items', [
            'id' => $item->id,
            'actual_quantity' => 48,
            'difference' => -2,
        ]);

        // Check status changed to in_progress
        $check->refresh();
        $this->assertEquals('in_progress', $check->status);
    }

    public function test_cannot_update_completed_inventory_check_item(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'completed',
            'date' => now()->toDateString(),
        ]);

        $item = $this->createInventoryCheckItem([
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
            'actual_quantity' => 50,
            'cost_price' => 100,
        ]);

        $response = $this->putJson("/api/inventory/checks/{$check->id}/items/{$item->id}", [
            'actual_quantity' => 48,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя редактировать завершённую инвентаризацию',
            ]);
    }

    public function test_can_add_item_to_inventory_check(): void
    {
        // Skip if inventory_checks doesn't have warehouse_id - needed for stock lookup
        if (!$this->inventoryChecksHasWarehouseId()) {
            $this->markTestSkipped('inventory_checks table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'New Ingredient',
            'cost_price' => 200,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 30,
            'avg_cost' => 200,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/items", [
            'ingredient_id' => $ingredient->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Позиция добавлена',
            ]);

        $this->assertDatabaseHas('inventory_check_items', [
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 30,
        ]);
    }

    public function test_cannot_add_duplicate_item_to_inventory_check(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $this->createInventoryCheckItem([
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
            'cost_price' => 100,
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/items", [
            'ingredient_id' => $ingredient->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ингредиент уже есть в инвентаризации',
            ]);
    }

    public function test_can_complete_inventory_check(): void
    {
        // Skip if inventory_checks doesn't have warehouse_id
        if (!$this->inventoryChecksHasWarehouseId()) {
            $this->markTestSkipped('inventory_checks table does not have warehouse_id column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'avg_cost' => 100,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $this->createInventoryCheckItem([
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
            'actual_quantity' => 45,
            'difference' => -5,
            'cost_price' => 100,
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/complete", [
            'user_id' => $this->user->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Инвентаризация завершена, остатки скорректированы',
            ]);

        $this->assertDatabaseHas('inventory_checks', [
            'id' => $check->id,
            'status' => 'completed',
        ]);

        // Stock should be adjusted
        $stock = IngredientStock::where('ingredient_id', $ingredient->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(45, $stock->quantity);
    }

    public function test_cannot_complete_inventory_check_with_unfilled_items(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $this->createInventoryCheckItem([
            'inventory_check_id' => $check->id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => 50,
            'actual_quantity' => null,
            'cost_price' => 100,
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/complete", [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Не заполнено', $response->json('message'));
    }

    public function test_can_cancel_inventory_check(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'in_progress',
            'date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Инвентаризация отменена',
            ]);

        $this->assertDatabaseHas('inventory_checks', [
            'id' => $check->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_completed_inventory_check(): void
    {
        // Skip if inventory_check_items doesn't have cost_price - model accessors query it
        if (!$this->inventoryCheckItemsHasCostPrice()) {
            $this->markTestSkipped('inventory_check_items table does not have cost_price column in this schema');
        }

        $check = $this->createInventoryCheck([
            'restaurant_id' => $this->restaurant->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'number' => 'INV-CHECK-001',
            'status' => 'completed',
            'date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/inventory/checks/{$check->id}/cancel");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя отменить завершённую инвентаризацию',
            ]);
    }

    // ==========================================
    // LOW STOCK ALERTS TESTS
    // ==========================================

    public function test_can_get_low_stock_alerts(): void
    {
        $ingredient1 = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Low Stock Item',
            'cost_price' => 100,
            'min_stock' => 20,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 5,
            'avg_cost' => 100,
        ]);

        $ingredient2 = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Normal Stock Item',
            'cost_price' => 100,
            'min_stock' => 10,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 50,
            'avg_cost' => 100,
        ]);

        $response = $this->getJson("/api/inventory/alerts/low-stock?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $lowStockIds = array_column($data, 'id');

        $this->assertContains($ingredient1->id, $lowStockIds);
        $this->assertNotContains($ingredient2->id, $lowStockIds);
    }

    // ==========================================
    // STATISTICS TESTS
    // ==========================================

    public function test_can_get_inventory_stats(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'cost_price' => 100,
            'min_stock' => 10,
            'track_stock' => true,
            'is_active' => true,
        ]);

        IngredientStock::create([
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'avg_cost' => 100,
        ]);

        $response = $this->getJson("/api/inventory/stats?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_items',
                        'total_value',
                        'low_stock_count',
                        'out_of_stock_count',
                    ],
                    'warehouses',
                    'today',
                ],
            ]);
    }

    // ==========================================
    // PACKAGINGS TESTS
    // ==========================================

    public function test_can_list_ingredient_packagings(): void
    {
        // Skip if packagings table doesn't exist
        if (!Schema::hasTable('ingredient_packagings')) {
            $this->markTestSkipped('ingredient_packagings table does not exist in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $packUnit = $this->createUnit([
            'name' => 'Box',
            'short_name' => 'box',
            'type' => 'piece',
        ], false);

        IngredientPackaging::create([
            'ingredient_id' => $ingredient->id,
            'unit_id' => $packUnit->id,
            'quantity' => 10,
            'is_default' => true,
            'is_purchase' => true,
        ]);

        $response = $this->getJson("/api/inventory/ingredients/{$ingredient->id}/packagings");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'unit_id', 'quantity', 'is_default']
                ]
            ]);
    }

    public function test_can_create_packaging(): void
    {
        // Skip if packagings table doesn't exist
        if (!Schema::hasTable('ingredient_packagings')) {
            $this->markTestSkipped('ingredient_packagings table does not exist in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $packUnit = $this->createUnit([
            'name' => 'Crate',
            'short_name' => 'crate',
            'type' => 'piece',
        ], false);

        $response = $this->postJson("/api/inventory/ingredients/{$ingredient->id}/packagings", [
            'unit_id' => $packUnit->id,
            'quantity' => 20,
            'barcode' => 'PACK-001',
            'is_default' => false,
            'is_purchase' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Фасовка создана',
            ]);

        $this->assertDatabaseHas('ingredient_packagings', [
            'ingredient_id' => $ingredient->id,
            'unit_id' => $packUnit->id,
            'quantity' => 20,
        ]);
    }

    public function test_cannot_create_duplicate_packaging_unit(): void
    {
        // Skip if packagings table doesn't exist
        if (!Schema::hasTable('ingredient_packagings')) {
            $this->markTestSkipped('ingredient_packagings table does not exist in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $packUnit = $this->createUnit([
            'name' => 'Box',
            'short_name' => 'box',
            'type' => 'piece',
        ], false);

        IngredientPackaging::create([
            'ingredient_id' => $ingredient->id,
            'unit_id' => $packUnit->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson("/api/inventory/ingredients/{$ingredient->id}/packagings", [
            'unit_id' => $packUnit->id,
            'quantity' => 20,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Фасовка с такой единицей измерения уже существует',
            ]);
    }

    public function test_can_update_packaging(): void
    {
        // Skip if packagings table doesn't exist
        if (!Schema::hasTable('ingredient_packagings')) {
            $this->markTestSkipped('ingredient_packagings table does not exist in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $packUnit = $this->createUnit([
            'name' => 'Box',
            'short_name' => 'box',
            'type' => 'piece',
        ], false);

        $packaging = IngredientPackaging::create([
            'ingredient_id' => $ingredient->id,
            'unit_id' => $packUnit->id,
            'quantity' => 10,
        ]);

        $response = $this->putJson("/api/inventory/packagings/{$packaging->id}", [
            'quantity' => 15,
            'barcode' => 'UPDATED-001',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Фасовка обновлена',
            ]);

        $this->assertDatabaseHas('ingredient_packagings', [
            'id' => $packaging->id,
            'quantity' => 15,
            'barcode' => 'UPDATED-001',
        ]);
    }

    public function test_can_delete_packaging(): void
    {
        // Skip if packagings table doesn't exist
        if (!Schema::hasTable('ingredient_packagings')) {
            $this->markTestSkipped('ingredient_packagings table does not exist in this schema');
        }

        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $packUnit = $this->createUnit([
            'name' => 'Box',
            'short_name' => 'box',
            'type' => 'piece',
        ], false);

        $packaging = IngredientPackaging::create([
            'ingredient_id' => $ingredient->id,
            'unit_id' => $packUnit->id,
            'quantity' => 10,
        ]);

        $response = $this->deleteJson("/api/inventory/packagings/{$packaging->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Фасовка удалена',
            ]);

        $this->assertDatabaseMissing('ingredient_packagings', ['id' => $packaging->id]);
    }

    // ==========================================
    // UNAUTHENTICATED TESTS
    // ==========================================

    public function test_unauthenticated_request_returns_401(): void
    {
        // Create a fresh test instance without authentication
        $this->refreshApplication();

        // Skip the warehouse check for this specific test
        if (!Schema::hasTable('warehouses')) {
            $this->markTestSkipped('Warehouses table does not exist - tests require new inventory schema');
        }

        $response = $this->getJson("/api/inventory/ingredients?restaurant_id={$this->restaurant->id}");

        $response->assertStatus(401);
    }
}
