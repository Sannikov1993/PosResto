<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Dish;
use App\Models\KitchenDevice;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты для API кухонных устройств (получение заказов без авторизации пользователя)
 */
class KitchenDeviceOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected KitchenStation $station;
    protected KitchenDevice $device;
    protected Category $category;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём ресторан
        $this->restaurant = Restaurant::factory()->create();

        // Создаём станцию кухни
        $this->station = KitchenStation::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Горячий цех',
            'slug' => 'hot',
            'is_active' => true,
        ]);

        // Создаём устройство кухни
        $this->device = KitchenDevice::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Планшет повара',
            'device_id' => 'test-device-123',
            'kitchen_station_id' => $this->station->id,
            'status' => KitchenDevice::STATUS_ACTIVE,
        ]);

        // Создаём категорию и блюдо
        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Основные блюда',
            'slug' => 'main',
        ]);

        $this->dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Пицца',
            'slug' => 'pizza',
            'price' => 500,
            'kitchen_station_id' => $this->station->id,
            'is_available' => true,
        ]);
    }

    /** @test */
    public function kitchen_device_can_fetch_orders_without_user_auth()
    {
        // Создаём заказ
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-001',
            'daily_number' => '#010226-001',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $order->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 1,
            'total' => 500,
            'status' => 'cooking',
        ]);

        // Запрос заказов с device_id (без авторизации пользователя)
        $response = $this->getJson('/api/kitchen-devices/orders?device_id=' . $this->device->device_id);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($order->id, $response->json('data.0.id'));
    }

    /** @test */
    public function kitchen_device_without_id_returns_error()
    {
        $response = $this->getJson('/api/kitchen-devices/orders');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'device_id не указан',
            ]);
    }

    /** @test */
    public function unknown_device_returns_not_found()
    {
        $response = $this->getJson('/api/kitchen-devices/orders?device_id=unknown-device');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Устройство не найдено',
            ]);
    }

    /** @test */
    public function disabled_device_returns_forbidden()
    {
        $this->device->update(['status' => KitchenDevice::STATUS_DISABLED]);

        $response = $this->getJson('/api/kitchen-devices/orders?device_id=' . $this->device->device_id);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Устройство отключено',
            ]);
    }

    /** @test */
    public function kitchen_device_can_update_order_status()
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-002',
            'daily_number' => '#010226-002',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $order->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 1,
            'total' => 500,
            'status' => 'cooking',
        ]);

        // Повар берёт заказ в работу
        $response = $this->patchJson("/api/kitchen-devices/orders/{$order->id}/status", [
            'device_id' => $this->device->device_id,
            'status' => 'cooking',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус обновлён',
            ]);

        // Проверяем что cooking_started_at установлен
        $order->refresh();
        $item = $order->items->first();
        $this->assertNotNull($item->cooking_started_at);
    }

    /** @test */
    public function kitchen_device_can_mark_order_ready()
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-003',
            'daily_number' => '#010226-003',
            'type' => 'dine_in',
            'status' => 'cooking',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $order->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 1,
            'total' => 500,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        // Повар отмечает заказ готовым
        $response = $this->patchJson("/api/kitchen-devices/orders/{$order->id}/status", [
            'device_id' => $this->device->device_id,
            'status' => 'ready',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('ready', $order->status);
        $this->assertEquals('ready', $order->items->first()->status);
    }

    /** @test */
    public function kitchen_device_only_sees_own_restaurant_orders()
    {
        // Создаём другой ресторан и заказ там
        $otherRestaurant = Restaurant::factory()->create();
        $otherOrder = Order::create([
            'restaurant_id' => $otherRestaurant->id,
            'order_number' => '010226-999',
            'daily_number' => '#010226-999',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 300,
            'total' => 300,
        ]);

        // Создаём заказ в нашем ресторане
        $ourOrder = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-004',
            'daily_number' => '#010226-004',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $ourOrder->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 1,
            'total' => 500,
            'status' => 'cooking',
        ]);

        $response = $this->getJson('/api/kitchen-devices/orders?device_id=' . $this->device->device_id);

        $response->assertOk();

        // Должен быть только наш заказ
        $orderIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($ourOrder->id, $orderIds);
        $this->assertNotContains($otherOrder->id, $orderIds);
    }

    /** @test */
    public function delivery_order_appears_on_kitchen_display()
    {
        // Создаём заказ доставки как это делает DeliveryController
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-005',
            'daily_number' => '#010226-005',
            'type' => 'delivery',
            'status' => 'confirmed', // DeliveryController использует 'confirmed'
            'delivery_status' => 'pending',
            'phone' => '+79001234567',
            'delivery_address' => 'ул. Пушкина, д. 10',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $order->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 2,
            'total' => 1000,
            'status' => 'cooking', // DeliveryController устанавливает 'cooking'
        ]);

        // Запрашиваем заказы через устройство кухни
        $response = $this->getJson('/api/kitchen-devices/orders?device_id=' . $this->device->device_id);

        $response->assertOk();

        // Заказ доставки должен отображаться
        $orderIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($order->id, $orderIds);
    }

    /** @test */
    public function completed_orders_not_shown_on_kitchen()
    {
        $completedOrder = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-006',
            'daily_number' => '#010226-006',
            'type' => 'dine_in',
            'status' => 'completed',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $activeOrder = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '010226-007',
            'daily_number' => '#010226-007',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $activeOrder->items()->create([
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'price' => $this->dish->price,
            'quantity' => 1,
            'total' => 500,
            'status' => 'cooking',
        ]);

        $response = $this->getJson('/api/kitchen-devices/orders?device_id=' . $this->device->device_id);

        $response->assertOk();

        $orderIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($completedOrder->id, $orderIds);
        $this->assertContains($activeOrder->id, $orderIds);
    }
}
