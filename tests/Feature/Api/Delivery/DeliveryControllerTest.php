<?php

namespace Tests\Feature\Api\Delivery;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Dish;
use App\Models\Category;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =========================================================================
    // DELIVERY ORDERS LIST
    // =========================================================================

    public function test_can_list_delivery_orders(): void
    {
        $this->authenticate();

        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'pending',
            'created_at' => now(),
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'dine_in',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/delivery/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'stats',
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_filter_orders_by_delivery_status(): void
    {
        $this->authenticate();

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'pending',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'in_transit',
        ]);

        $response = $this->getJson('/api/delivery/orders?delivery_status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_search_orders(): void
    {
        $this->authenticate();

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'phone' => '+79001234567',
            'delivery_address' => 'ул. Тестовая, д. 1',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'phone' => '+79009876543',
            'delivery_address' => 'ул. Другая, д. 2',
        ]);

        $response = $this->getJson('/api/delivery/orders?search=1234567');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_orders_returns_stats(): void
    {
        $this->authenticate();

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'pending',
        ]);

        $response = $this->getJson('/api/delivery/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total',
                    'pending',
                    'preparing',
                    'in_transit',
                    'delivered',
                    'cancelled',
                ],
            ]);
    }

    // =========================================================================
    // CREATE DELIVERY ORDER
    // =========================================================================

    public function test_create_order_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/delivery/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'delivery_address', 'payment_method', 'items']);
    }

    public function test_create_order_validates_items(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/delivery/orders', [
            'phone' => '+79001234567',
            'delivery_address' => 'ул. Тестовая, д. 1',
            'payment_method' => 'cash',
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_create_order_validates_payment_method(): void
    {
        $this->authenticate();

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'price' => 500,
        ]);

        $response = $this->postJson('/api/delivery/orders', [
            'phone' => '+79001234567',
            'delivery_address' => 'ул. Тестовая, д. 1',
            'payment_method' => 'invalid',
            'items' => [
                ['dish_id' => $dish->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    // =========================================================================
    // SHOW DELIVERY ORDER
    // =========================================================================

    public function test_can_show_delivery_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
        ]);

        $response = $this->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_show_order_includes_enriched_data(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'pending',
        ]);

        $response = $this->getJson("/api/delivery/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'delivery_status_label',
                    'delivery_status_color',
                    'payment_status_label',
                    'wait_time_minutes',
                    'urgency',
                ],
            ]);
    }

    // =========================================================================
    // UPDATE DELIVERY STATUS
    // =========================================================================

    public function test_update_status_validates_status(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
        ]);

        $response = $this->patchJson("/api/delivery/orders/{$order->id}/status", [
            'delivery_status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_status']);
    }

    // =========================================================================
    // ASSIGN COURIER
    // =========================================================================

    public function test_assign_courier_validates_courier_exists(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
        ]);

        $response = $this->postJson("/api/delivery/orders/{$order->id}/assign-courier", [
            'courier_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['courier_id']);
    }

    // =========================================================================
    // COURIERS
    // =========================================================================

    public function test_can_list_couriers(): void
    {
        $this->authenticate();

        User::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_courier' => true,
            'courier_status' => 'available',
        ]);

        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_courier' => false,
        ]);

        $response = $this->getJson('/api/delivery/couriers');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_couriers_include_order_info(): void
    {
        $this->authenticate();

        $courier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_courier' => true,
            'courier_status' => 'busy',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'courier_id' => $courier->id,
            'delivery_status' => 'in_transit',
        ]);

        $response = $this->getJson('/api/delivery/couriers');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'current_orders',
                        'today_orders',
                        'today_earnings',
                    ],
                ],
            ]);
    }

    public function test_courier_status_validates_status(): void
    {
        $this->authenticate();

        $courier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_courier' => true,
        ]);

        $response = $this->patchJson("/api/delivery/couriers/{$courier->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =========================================================================
    // DELIVERY ZONES
    // =========================================================================

    public function test_can_list_zones(): void
    {
        $this->authenticate();

        DeliveryZone::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson('/api/delivery/zones');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_zone(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/delivery/zones', [
            'name' => 'Zone A',
            'min_distance' => 0,
            'max_distance' => 5,
            'delivery_fee' => 150,
            'estimated_time' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Зона доставки создана',
            ]);

        $this->assertDatabaseHas('delivery_zones', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Zone A',
            'delivery_fee' => 150,
        ]);
    }

    public function test_create_zone_validates_distance(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/delivery/zones', [
            'name' => 'Zone B',
            'min_distance' => 10,
            'max_distance' => 5,
            'delivery_fee' => 200,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_distance']);
    }

    public function test_can_update_zone(): void
    {
        $this->authenticate();

        $zone = DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
            'delivery_fee' => 100,
        ]);

        $response = $this->putJson("/api/delivery/zones/{$zone->id}", [
            'name' => 'New Name',
            'delivery_fee' => 250,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зона обновлена',
            ]);

        $zone->refresh();
        $this->assertEquals('New Name', $zone->name);
        $this->assertEquals(250, $zone->delivery_fee);
    }

    public function test_can_delete_zone(): void
    {
        $this->authenticate();

        $zone = DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->deleteJson("/api/delivery/zones/{$zone->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зона удалена',
            ]);

        $this->assertDatabaseMissing('delivery_zones', ['id' => $zone->id]);
    }

    public function test_can_deactivate_zone(): void
    {
        $this->authenticate();

        $zone = DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/delivery/zones/{$zone->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $zone->refresh();
        $this->assertFalse($zone->is_active);
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    public function test_can_get_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/delivery/settings');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_can_update_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/delivery/settings', [
            'default_delivery_time' => '45',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки сохранены',
            ]);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function test_can_get_analytics(): void
    {
        $this->authenticate();

        Order::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_status' => 'delivered',
            'total' => 1000,
        ]);

        $response = $this->getJson('/api/delivery/analytics?period=today');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'completed_orders',
                    'total_revenue',
                ],
            ]);
    }

    public function test_analytics_supports_period_filter(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/delivery/analytics?period=week');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // =========================================================================
    // GEOCODING
    // =========================================================================

    public function test_can_detect_zone(): void
    {
        $this->authenticate();

        DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Central Zone',
            'delivery_fee' => 200,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/delivery/detect-zone', [
            'address' => 'ул. Тестовая, д. 1',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_detect_zone_returns_zone_info(): void
    {
        $this->authenticate();

        DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Zone',
            'delivery_fee' => 150,
            'free_delivery_from' => 2000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/delivery/detect-zone', [
            'address' => 'ул. Тестовая, д. 1',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'zone_id',
                'delivery_cost',
                'free_delivery_from',
            ]);
    }

    public function test_detect_zone_considers_order_total_for_free_delivery(): void
    {
        $this->authenticate();

        DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'delivery_fee' => 200,
            'free_delivery_from' => 1500,
            'is_active' => true,
        ]);

        $response1 = $this->postJson('/api/delivery/detect-zone', [
            'address' => 'ул. Тестовая, д. 1',
            'total' => 1000,
        ]);

        $response1->assertOk();
        $this->assertEquals(200, $response1->json('delivery_cost'));

        $response2 = $this->postJson('/api/delivery/detect-zone', [
            'address' => 'ул. Тестовая, д. 1',
            'total' => 2000,
        ]);

        $response2->assertOk();
        $this->assertEquals(0, $response2->json('delivery_cost'));
    }

    // =========================================================================
    // MAP DATA
    // =========================================================================

    public function test_can_get_map_data(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/delivery/map-data');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'couriers',
                    'orders',
                    'zones',
                    'restaurant',
                ],
            ]);
    }
}
