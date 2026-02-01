<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Table $table;
    protected Dish $dish;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём ресторан
        $this->restaurant = Restaurant::factory()->create();

        // Создаём пользователя
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);

        // Создаём зону и стол
        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
        ]);

        // Создаём категорию и блюдо
        $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'price' => 500,
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // ===== INDEX TESTS =====

    public function test_can_list_orders(): void
    {
        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'status', 'total', 'type']
                ]
            ])
            ->assertJson(['success' => true]);
    }

    public function test_can_filter_orders_by_status(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/orders?restaurant_id={$this->restaurant->id}&status=new");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('new', $response->json('data.0.status'));
    }

    public function test_can_filter_orders_by_table(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $otherTable = Table::factory()->create(['restaurant_id' => $this->restaurant->id]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $otherTable->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/orders?restaurant_id={$this->restaurant->id}&table_id={$this->table->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_filter_kitchen_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/orders?restaurant_id={$this->restaurant->id}&kitchen=1");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ===== STORE TESTS =====

    public function test_can_create_dine_in_order(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 2,
                ]
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'status', 'total', 'items']
            ]);

        $this->assertDatabaseHas('orders', [
            'type' => 'dine_in',
            'table_id' => $this->table->id,
            'status' => 'cooking',
        ]);
    }

    public function test_create_order_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'items']);
    }

    public function test_create_order_validates_items_array(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_create_order_validates_dish_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'items' => [
                ['dish_id' => 99999, 'quantity' => 1]
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.dish_id']);
    }

    public function test_create_order_calculates_total(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 3,
                ]
            ],
        ]);

        $response->assertStatus(201);
        // 3 * 500 = 1500
        $this->assertEquals(1500, $response->json('data.total'));
    }

    // ===== SHOW TESTS =====

    public function test_can_show_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['id' => $order->id]
            ]);
    }

    public function test_show_order_returns_404_for_nonexistent(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/orders/99999');

        $response->assertNotFound();
    }

    // ===== STATUS UPDATE TESTS =====

    public function test_can_update_order_status(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);

        $this->authenticate();
        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'cooking',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cooking',
        ]);
    }

    public function test_update_status_validates_status_value(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ===== CANCEL TESTS =====

    public function test_can_cancel_order_with_writeoff(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", [
            'reason' => 'Customer requested cancellation',
            'manager_id' => $this->user->id,
            'is_write_off' => false,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    // ===== ADD ITEM TESTS =====

    public function test_can_add_item_to_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/orders/{$order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
        ]);
    }
}
