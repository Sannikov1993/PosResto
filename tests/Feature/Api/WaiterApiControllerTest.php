<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Zone;
use App\Models\Table;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class WaiterApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Zone $zone;
    protected Table $table;
    protected Category $category;
    protected Dish $dish;
    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create restaurant
        $this->restaurant = Restaurant::factory()->create();

        // Create user with api_token and waiter role
        $this->apiToken = Str::random(60);
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'api_token' => $this->apiToken,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        // Create zone and table
        $this->zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
            'status' => 'free',
        ]);

        // Create category and dish
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'parent_id' => null,
        ]);

        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 500,
            'is_available' => true,
        ]);
    }

    /**
     * Helper to make authenticated API request with Bearer token
     */
    protected function apiRequest(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ])->{$method}($uri, $data);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_waiter_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/waiter/tables');
        $response->assertStatus(401);
    }

    public function test_waiter_endpoints_reject_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ])->getJson('/api/waiter/tables');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Недействительный токен',
            ]);
    }

    public function test_waiter_endpoints_reject_inactive_user(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->apiRequest('getJson', '/api/waiter/tables');

        $response->assertStatus(401);
    }

    // =====================================================
    // TABLES AND ZONES TESTS
    // =====================================================

    public function test_can_get_tables_and_zones(): void
    {
        // Create additional zones and tables
        Zone::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
        ])->each(function ($zone) {
            Table::factory()->count(3)->create([
                'restaurant_id' => $this->restaurant->id,
                'zone_id' => $zone->id,
            ]);
        });

        $response = $this->apiRequest('getJson', "/api/waiter/tables?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'tables']
                ]
            ]);
    }

    public function test_tables_include_orders_count(): void
    {
        // Create order for the table
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/tables?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Check that orders_count is present in table data
        $zones = $response->json('data');
        $foundTable = false;
        foreach ($zones as $zone) {
            foreach ($zone['tables'] as $table) {
                if ($table['id'] === $this->table->id) {
                    $this->assertArrayHasKey('orders_count', $table);
                    $this->assertEquals(1, $table['orders_count']);
                    $foundTable = true;
                }
            }
        }
        $this->assertTrue($foundTable, 'Table not found in response');
    }

    public function test_can_get_single_table_details(): void
    {
        // Create order with items for the table
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/table/{$this->table->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'number',
                    'orders',
                    'zone',
                ],
                'guest_colors',
            ]);
    }

    public function test_get_table_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('getJson', '/api/waiter/table/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // MENU CATEGORIES TESTS
    // =====================================================

    public function test_can_get_menu_categories(): void
    {
        // Create subcategory
        Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'parent_id' => $this->category->id,
            'is_active' => true,
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/menu/categories?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'children']
                ]
            ]);
    }

    public function test_menu_categories_only_returns_active(): void
    {
        // Create inactive category
        Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
            'parent_id' => null,
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/menu/categories?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        // All returned categories should be active
        $categories = $response->json('data');
        foreach ($categories as $category) {
            $this->assertTrue($category['is_active']);
        }
    }

    public function test_menu_categories_only_returns_root_categories(): void
    {
        // Create a child category
        $childCategory = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'parent_id' => $this->category->id,
            'is_active' => true,
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/menu/categories?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        // Root categories should have parent_id = null
        $categories = $response->json('data');
        foreach ($categories as $category) {
            $this->assertNull($category['parent_id']);
        }
    }

    // =====================================================
    // CATEGORY PRODUCTS TESTS
    // =====================================================

    public function test_can_get_category_products(): void
    {
        // Create additional dishes in category
        Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/menu/category/{$this->category->id}/products");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'price', 'is_available']
                ]
            ]);

        $this->assertCount(4, $response->json('data')); // 1 from setUp + 3 created
    }

    public function test_category_products_only_returns_available(): void
    {
        // Create unavailable dish
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => false,
        ]);

        $response = $this->apiRequest('getJson', "/api/waiter/menu/category/{$this->category->id}/products");

        $response->assertOk();

        // All returned dishes should be available
        $dishes = $response->json('data');
        foreach ($dishes as $dish) {
            $this->assertTrue($dish['is_available']);
        }
    }

    // =====================================================
    // ADD ORDER ITEM TESTS
    // =====================================================

    public function test_can_add_item_to_new_order(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
            'quantity' => 2,
            'comment' => 'No onions',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Позиция добавлена',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'dish_id', 'quantity', 'price', 'total']
            ]);

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'table_id' => $this->table->id,
            'type' => 'dine_in',
            'status' => 'new',
        ]);

        // Verify order item was created
        $this->assertDatabaseHas('order_items', [
            'dish_id' => $this->dish->id,
            'quantity' => 2,
            'guest_number' => 1,
            'comment' => 'No onions',
            'status' => 'pending',
        ]);
    }

    public function test_can_add_item_to_existing_order(): void
    {
        // Create existing order
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);

        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify item was added to existing order
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 2,
        ]);
    }

    public function test_add_item_validates_required_fields(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['table_id', 'dish_id', 'guest_number']);
    }

    public function test_add_item_validates_table_exists(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => 99999,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_add_item_validates_dish_exists(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => 99999,
            'guest_number' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id']);
    }

    public function test_add_item_validates_guest_number_range(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 25, // Max is 20
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_number']);
    }

    public function test_add_item_defaults_quantity_to_one(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
            // quantity not provided
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('order_items', [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);
    }

    // =====================================================
    // UPDATE ORDER ITEM TESTS
    // =====================================================

    public function test_can_update_order_item_quantity(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('patchJson', "/api/waiter/order/item/{$item->id}", [
            'quantity' => 3,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'quantity' => 3,
            'total' => 1500, // 3 * 500
        ]);
    }

    public function test_update_item_with_zero_quantity_deletes_it(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('patchJson', "/api/waiter/order/item/{$item->id}", [
            'quantity' => 0,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция удалена',
            ]);

        $this->assertDatabaseMissing('order_items', [
            'id' => $item->id,
        ]);
    }

    public function test_cannot_update_item_already_in_kitchen(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'cooking', // Already cooking
        ]);

        $response = $this->apiRequest('patchJson', "/api/waiter/order/item/{$item->id}", [
            'quantity' => 5,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя изменить - уже на кухне',
            ]);
    }

    public function test_update_item_validates_quantity(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('patchJson', "/api/waiter/order/item/{$item->id}", [
            'quantity' => -1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_update_item_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('patchJson', '/api/waiter/order/item/99999', [
            'quantity' => 2,
        ]);

        $response->assertNotFound();
    }

    // =====================================================
    // DELETE ORDER ITEM TESTS
    // =====================================================

    public function test_can_delete_pending_order_item(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('deleteJson', "/api/waiter/order/item/{$item->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Удалено',
            ]);

        $this->assertDatabaseMissing('order_items', [
            'id' => $item->id,
        ]);
    }

    public function test_cannot_delete_item_already_in_kitchen(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiRequest('deleteJson', "/api/waiter/order/item/{$item->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя удалить',
            ]);
    }

    public function test_delete_item_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('deleteJson', '/api/waiter/order/item/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // SEND TO KITCHEN TESTS
    // =====================================================

    public function test_can_send_order_to_kitchen(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'new',
        ]);

        OrderItem::factory()->count(3)->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/send-kitchen");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Отправлено',
                'sent_count' => 3,
            ]);

        // Verify items status changed
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'status' => 'cooking',
        ]);

        // Verify order status changed
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cooking',
        ]);
    }

    public function test_send_to_kitchen_only_sends_pending_items(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'cooking',
        ]);

        // Create items with different statuses
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking', // Already cooking
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'ready', // Already ready
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/send-kitchen");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'sent_count' => 1, // Only the pending one
            ]);
    }

    public function test_send_to_kitchen_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/99999/send-kitchen');

        $response->assertNotFound();
    }

    // =====================================================
    // SERVE ORDER TESTS
    // =====================================================

    public function test_can_serve_ready_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'ready',
        ]);

        OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
            'status' => 'ready',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/serve");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Выдано',
            ]);

        // Verify items status changed to served
        $this->assertEquals(
            2,
            OrderItem::where('order_id', $order->id)->where('status', 'served')->count()
        );
    }

    public function test_serve_only_updates_ready_items(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        // Create items with different statuses
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'ready',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/serve");

        $response->assertOk();

        // Only the ready item should become served
        $this->assertEquals(1, OrderItem::where('order_id', $order->id)->where('status', 'served')->count());
        $this->assertEquals(1, OrderItem::where('order_id', $order->id)->where('status', 'cooking')->count());
    }

    public function test_serve_order_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/99999/serve');

        $response->assertNotFound();
    }

    // =====================================================
    // PAY ORDER TESTS
    // =====================================================

    public function test_can_pay_order_with_cash(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'ready',
            'payment_status' => 'pending',
            'total' => 1000,
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/pay", [
            'payment_method' => 'cash',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Оплачено',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);
    }

    public function test_can_pay_order_with_card(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'ready',
            'payment_status' => 'pending',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/pay", [
            'payment_method' => 'card',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'card',
        ]);
    }

    public function test_pay_order_defaults_to_cash(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'ready',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/pay");

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cash',
        ]);
    }

    public function test_pay_order_frees_table(): void
    {
        $this->table->update(['status' => 'occupied']);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'ready',
        ]);

        $response = $this->apiRequest('postJson', "/api/waiter/order/{$order->id}/pay");

        $response->assertOk();

        $this->assertDatabaseHas('tables', [
            'id' => $this->table->id,
            'status' => 'free',
        ]);
    }

    public function test_pay_order_returns_404_for_nonexistent(): void
    {
        $response = $this->apiRequest('postJson', '/api/waiter/order/99999/pay');

        $response->assertNotFound();
    }

    // =====================================================
    // LIST ORDERS TESTS
    // =====================================================

    public function test_can_list_waiter_orders(): void
    {
        // Create orders for this waiter
        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'user_id' => $this->user->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiRequest('getJson', '/api/waiter/orders');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'status', 'table', 'items']
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_orders_only_shows_active_statuses(): void
    {
        // Create orders with various statuses
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'status' => 'new',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'status' => 'completed', // Should not appear
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'status' => 'cancelled', // Should not appear
        ]);

        $response = $this->apiRequest('getJson', '/api/waiter/orders');

        $response->assertOk();

        // Only the 'new' order should appear
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('new', $response->json('data.0.status'));
    }

    public function test_list_orders_only_shows_own_orders(): void
    {
        // Create order for another waiter
        $anotherUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $anotherUser->id,
            'status' => 'cooking',
        ]);

        // Create order for our waiter
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiRequest('getJson', '/api/waiter/orders');

        $response->assertOk();

        // Only own order should appear
        $this->assertCount(1, $response->json('data'));
    }

    // =====================================================
    // PROFILE STATS TESTS
    // =====================================================

    public function test_can_get_profile_stats(): void
    {
        // Create orders for today
        Order::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total' => 1000,
            'tips' => 100,
            'created_at' => now(),
        ]);

        // Create order from yesterday (should not count)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total' => 500,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->apiRequest('getJson', '/api/waiter/profile/stats');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders_today',
                    'revenue_today',
                    'tips_today',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['orders_today']);
        $this->assertEquals(2000, $data['revenue_today']); // 2 * 1000
    }

    public function test_profile_stats_only_counts_paid_orders_for_revenue(): void
    {
        // Create paid order
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total' => 1000,
            'created_at' => now(),
        ]);

        // Create unpaid order
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
            'total' => 500,
            'created_at' => now(),
        ]);

        $response = $this->apiRequest('getJson', '/api/waiter/profile/stats');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(2, $data['orders_today']); // Both orders count
        $this->assertEquals(1000, $data['revenue_today']); // Only paid
    }

    public function test_profile_stats_returns_zero_for_new_waiter(): void
    {
        // No orders created for this waiter

        $response = $this->apiRequest('getJson', '/api/waiter/profile/stats');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(0, $data['orders_today']);
        $this->assertEquals(0, $data['revenue_today']);
        $this->assertEquals(0, $data['tips_today']);
    }

    // =====================================================
    // PERMISSION TESTS
    // =====================================================

    public function test_add_item_requires_orders_create_permission(): void
    {
        // Create user without permissions (regular user with custom role)
        $userWithoutPermission = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'api_token' => 'no-permission-token',
            'role' => 'cook', // Cook doesn't have orders.create permission
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer no-permission-token',
            'Accept' => 'application/json',
        ])->postJson('/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_item_requires_orders_edit_permission(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        // Create user without edit permission
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'api_token' => 'no-edit-token',
            'role' => 'cook',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer no-edit-token',
            'Accept' => 'application/json',
        ])->patchJson("/api/waiter/order/item/{$item->id}", [
            'quantity' => 2,
        ]);

        $response->assertStatus(403);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_can_use_sanctum_token_instead_of_api_token(): void
    {
        // Create Sanctum token for user
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson("/api/waiter/tables?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $superAdmin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'api_token' => 'super-admin-token',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer super-admin-token',
            'Accept' => 'application/json',
        ])->postJson('/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
        ]);

        $response->assertStatus(201);
    }

    public function test_tenant_owner_bypasses_permission_checks(): void
    {
        $tenantOwner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'api_token' => 'tenant-owner-token',
            'role' => 'manager',
            'is_tenant_owner' => true,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer tenant-owner-token',
            'Accept' => 'application/json',
        ])->postJson('/api/waiter/order/add-item', [
            'table_id' => $this->table->id,
            'dish_id' => $this->dish->id,
            'guest_number' => 1,
        ]);

        $response->assertStatus(201);
    }
}
