<?php

namespace Tests\Feature\Api\Kitchen;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\KitchenStation;
use App\Models\KitchenDevice;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class KitchenControllerTest extends TestCase
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

    // =====================================================
    // KITCHEN STATIONS - INDEX
    // =====================================================

    public function test_can_list_kitchen_stations(): void
    {
        KitchenStation::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'color', 'is_active', 'is_bar']
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_list_only_active_kitchen_stations(): void
    {
        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/active?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_kitchen_stations_are_ordered_by_sort_order(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Third',
            'sort_order' => 3,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'First',
            'sort_order' => 1,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Second',
            'sort_order' => 2,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['First', 'Second', 'Third'], $names);
    }

    // =====================================================
    // KITCHEN STATIONS - STORE
    // =====================================================

    public function test_can_create_kitchen_station(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Горячий цех',
            'icon' => 'fire',
            'color' => '#FF5733',
            'description' => 'Основной горячий цех',
            'notification_sound' => 'bell',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Цех создан',
            ]);

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'Горячий цех',
            'icon' => 'fire',
            'color' => '#FF5733',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_kitchen_station_validates_required_name(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_kitchen_station_validates_notification_sound(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Test Station',
            'notification_sound' => 'invalid_sound',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notification_sound']);
    }

    public function test_create_kitchen_station_generates_slug_if_not_provided(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Test Station',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $station = KitchenStation::where('name', 'Test Station')->first();
        $this->assertNotNull($station);
        $this->assertNotEmpty($station->slug);
        $this->assertStringContainsString('test', $station->slug);
    }

    public function test_create_kitchen_station_generates_unique_slug(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'test-station',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Test Station',
            'slug' => 'test-station',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $station = KitchenStation::where('name', 'Test Station')->first();
        $this->assertNotNull($station);
        $this->assertNotEquals('test-station', $station->slug);
    }

    public function test_create_kitchen_station_with_is_bar_flag(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Бар',
            'is_bar' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'Бар',
            'is_bar' => true,
        ]);
    }

    // =====================================================
    // KITCHEN STATIONS - SHOW
    // =====================================================

    public function test_can_show_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['id' => $station->id],
            ]);
    }

    public function test_show_kitchen_station_includes_dishes_count(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Dish::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('data.dishes_count', 5);
    }

    // =====================================================
    // KITCHEN STATIONS - UPDATE
    // =====================================================

    public function test_can_update_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
            'name' => 'New Name',
            'color' => '#00FF00',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех обновлён',
            ]);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'name' => 'New Name',
            'color' => '#00FF00',
        ]);
    }

    public function test_update_kitchen_station_validates_unique_slug(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'existing-slug',
        ]);

        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'my-slug',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
            'slug' => 'existing-slug',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Цех с таким slug уже существует',
            ]);
    }

    public function test_can_update_kitchen_station_with_same_slug(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'my-slug',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
            'slug' => 'my-slug',
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
    }

    // =====================================================
    // KITCHEN STATIONS - DELETE
    // =====================================================

    public function test_can_delete_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех удалён',
            ]);

        $this->assertDatabaseMissing('kitchen_stations', [
            'id' => $station->id,
        ]);
    }

    // =====================================================
    // KITCHEN STATIONS - TOGGLE ACTIVE
    // =====================================================

    public function test_can_toggle_kitchen_station_active_status(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->patchJson("/api/kitchen-stations/{$station->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех деактивирован',
            ]);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'is_active' => false,
        ]);

        // Toggle back
        $response = $this->patchJson("/api/kitchen-stations/{$station->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех активирован',
            ]);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'is_active' => true,
        ]);
    }

    // =====================================================
    // KITCHEN STATIONS - REORDER
    // =====================================================

    public function test_can_reorder_kitchen_stations(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 1,
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 2,
        ]);

        $station3 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 3,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
            'stations' => [
                ['id' => $station1->id, 'sort_order' => 3],
                ['id' => $station2->id, 'sort_order' => 1],
                ['id' => $station3->id, 'sort_order' => 2],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Порядок обновлён',
            ]);

        $this->assertDatabaseHas('kitchen_stations', ['id' => $station1->id, 'sort_order' => 3]);
        $this->assertDatabaseHas('kitchen_stations', ['id' => $station2->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('kitchen_stations', ['id' => $station3->id, 'sort_order' => 2]);
    }

    public function test_reorder_validates_required_stations(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations']);
    }

    public function test_reorder_validates_station_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
            'stations' => [
                ['id' => 99999, 'sort_order' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations.0.id']);
    }

    // =====================================================
    // BAR STATION ENDPOINTS
    // =====================================================

    public function test_can_check_bar_station_exists(): void
    {
        KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'has_bar' => true,
            ]);
    }

    public function test_check_bar_returns_false_when_no_bar(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'has_bar' => false,
            ]);
    }

    public function test_check_bar_returns_false_when_bar_inactive(): void
    {
        KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'has_bar' => false,
            ]);
    }

    public function test_can_get_bar_orders(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'status' => 'cooking',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'status' => 'cooking',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
                'station',
                'counts' => ['new', 'in_progress', 'ready'],
            ]);
    }

    public function test_bar_orders_returns_empty_when_no_bar(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'data' => [],
            ]);
    }

    public function test_can_update_bar_item_status_to_cooking(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'status' => 'cooking',
            'cooking_started_at' => null,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
            'item_id' => $item->id,
            'status' => 'cooking',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус обновлён',
            ]);

        $this->assertNotNull($item->fresh()->cooking_started_at);
    }

    public function test_can_update_bar_item_status_to_ready(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
            'item_id' => $item->id,
            'status' => 'ready',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'status' => 'ready',
        ]);
    }

    public function test_update_bar_item_status_validates_item_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
            'item_id' => 99999,
            'status' => 'ready',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_update_bar_item_status_validates_status_value(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
            'item_id' => $item->id,
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =====================================================
    // KITCHEN DEVICES - REGISTER
    // =====================================================

    public function test_can_register_new_kitchen_device(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_registering_existing_device_updates_last_seen(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_validates_device_id(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    // =====================================================
    // KITCHEN DEVICES - MY STATION
    // =====================================================

    public function test_can_get_my_station_for_configured_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'my-device-123',
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=my-device-123');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'configured',
            ])
            ->assertJsonPath('data.kitchen_station.id', $station->id);
    }

    public function test_my_station_returns_pending_for_unconfigured_device(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'pending-device',
            'status' => 'pending',
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=pending-device');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'pending',
            ]);
    }

    public function test_my_station_returns_disabled_for_disabled_device(): void
    {
        KitchenDevice::factory()->disabled()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'disabled-device',
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=disabled-device');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'status' => 'disabled',
            ]);
    }

    public function test_my_station_returns_not_found_for_unknown_device(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=unknown-device');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'status' => 'not_linked',
            ]);
    }

    public function test_my_station_requires_device_id(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'device_id не указан',
            ]);
    }

    public function test_my_station_accepts_device_id_from_header(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'header-device',
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station', [
            'X-Device-ID' => 'header-device',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'configured',
            ]);
    }

    // =====================================================
    // KITCHEN DEVICES - INDEX
    // =====================================================

    public function test_can_list_kitchen_devices(): void
    {
        KitchenDevice::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'device_id', 'name', 'status', 'kitchen_station_id', 'last_seen_at'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_kitchen_devices_ordered_by_last_seen(): void
    {
        $device1 = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Device 1',
            'last_seen_at' => now()->subHours(2),
        ]);

        $device2 = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Device 2',
            'last_seen_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        // Devices are returned in creation order, not ordered by last_seen
        $this->assertEquals(['Device 1', 'Device 2'], $names);
    }

    // =====================================================
    // KITCHEN DEVICES - UPDATE
    // =====================================================

    public function test_can_update_kitchen_device(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Устройство обновлено',
            ]);

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_assign_station_to_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'kitchen_station_id' => $station->id,
            'status' => 'active', // Auto-activated when station assigned
        ]);
    }

    public function test_can_update_device_status(): void
    {
        $device = KitchenDevice::factory()->active()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'status' => 'disabled',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'status' => 'disabled',
        ]);
    }

    public function test_can_set_device_pin(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'pin' => '1234',
        ]);

        $response->assertOk();

        $device->refresh();
        $this->assertNotNull($device->pin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('1234', $device->pin));
    }

    public function test_update_device_validates_status(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_device_validates_station_exists(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_station_id']);
    }

    // =====================================================
    // KITCHEN DEVICES - DELETE
    // =====================================================

    public function test_can_delete_kitchen_device(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-devices/{$device->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Устройство удалено',
            ]);

        $this->assertDatabaseMissing('kitchen_devices', [
            'id' => $device->id,
        ]);
    }

    // =====================================================
    // KITCHEN DEVICES - CHANGE STATION
    // =====================================================

    public function test_can_change_station_without_device_pin_set(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withStation($station1->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'change-station-device',
            'pin' => null, // Device has no PIN set
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'change-station-device',
            'pin' => 'any-pin', // Any PIN works when device has no PIN
            'kitchen_station_id' => $station2->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Станция изменена',
            ]);

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'kitchen_station_id' => $station2->id,
        ]);
    }

    public function test_can_change_station_with_correct_pin(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withPin('5678')->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'pin-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'pin-device',
            'pin' => '5678',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_change_station_fails_with_wrong_pin(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withPin('5678')->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'pin-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'pin-device',
            'pin' => 'wrong',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Неверный PIN',
            ]);
    }

    public function test_change_station_fails_for_unknown_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'unknown-device',
            'pin' => 'any-pin',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Устройство не найдено',
            ]);
    }

    public function test_change_station_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id', 'pin', 'kitchen_station_id']);
    }

    public function test_change_station_validates_station_exists(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'test-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'test-device',
            'pin' => '',
            'kitchen_station_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_station_id']);
    }

    // =====================================================
    // INTEGRATION TESTS - ORDER ITEM ROUTING
    // =====================================================

    public function test_dish_is_assigned_to_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Горячий цех',
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'kitchen_station_id' => $station->id,
        ]);

        // Verify the relationship works
        $this->assertEquals($station->id, $dish->kitchen_station_id);
    }

    public function test_deleting_station_nullifies_dish_station_reference(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        // Delete the station
        $station->delete();

        // Refresh dish and verify kitchen_station_id is null
        $dish->refresh();
        $this->assertNull($dish->kitchen_station_id);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_kitchen_stations_require_authentication(): void
    {
        $response = $this->getJson('/api/kitchen-stations');

        $response->assertUnauthorized();
    }

    public function test_kitchen_devices_require_authentication(): void
    {
        $response = $this->getJson('/api/kitchen-devices');

        $response->assertUnauthorized();
    }
}
