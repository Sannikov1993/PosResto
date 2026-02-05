<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Dish;
use App\Models\Order;

class OrdersApiTest extends ApiTestCase
{
    protected Category $category;
    protected Dish $dish;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 500,
            'is_available' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'phone' => '+79991234567',
        ]);
    }

    /** @test */
    public function it_returns_orders_list(): void
    {
        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->apiGet('/orders');

        $this->assertApiSuccess($response);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_filters_orders_by_status(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
        ]);

        $response = $this->apiGet('/orders?status=new');

        $this->assertApiSuccess($response);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'new');
    }

    /** @test */
    public function it_creates_delivery_order(): void
    {
        $response = $this->apiPost('/orders', [
            'type' => 'delivery',
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 2,
                ],
            ],
            'delivery_address' => 'ул. Ленина, 15',
            'phone' => '+79991234567',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.type', 'delivery');
        $response->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'delivery',
            'delivery_address' => 'ул. Ленина, 15',
        ]);
    }

    /** @test */
    public function it_creates_pickup_order(): void
    {
        $response = $this->apiPost('/orders', [
            'type' => 'pickup',
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 1,
                ],
            ],
            'phone' => '+79991234567',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'pickup');
    }

    /** @test */
    public function it_validates_order_items(): void
    {
        $response = $this->apiPost('/orders', [
            'type' => 'delivery',
            'items' => [],
            'delivery_address' => 'ул. Ленина, 15',
        ]);

        $this->assertValidationError($response, 'items');
    }

    /** @test */
    public function it_returns_single_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->apiGet("/orders/{$order->id}");

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.id', $order->id);
    }

    /** @test */
    public function it_updates_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);

        $response = $this->apiPatch("/orders/{$order->id}", [
            'comment' => 'Новый комментарий',
        ]);

        $this->assertApiSuccess($response);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'comment' => 'Новый комментарий',
        ]);
    }

    /** @test */
    public function it_confirms_order(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
        ]);

        $response = $this->apiPost("/orders/{$order->id}/confirm");

        $this->assertApiSuccess($response);
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
    }

    /** @test */
    public function it_marks_order_ready(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $response = $this->apiPost("/orders/{$order->id}/ready");

        $this->assertApiSuccess($response);
        $order->refresh();
        $this->assertEquals('ready', $order->status);
    }

    /** @test */
    public function it_cancels_order(): void
    {
        // Create a pickup order (no table association)
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-TEST-' . uniqid(),
            'daily_number' => 1,
            'type' => 'pickup',
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $response = $this->apiPost("/orders/{$order->id}/cancel", [
            'reason' => 'Клиент передумал',
        ]);

        $this->assertApiSuccess($response);
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Клиент передумал', $order->cancel_reason);
    }

    /** @test */
    public function it_calculates_order_total(): void
    {
        $response = $this->apiPost('/orders/calculate', [
            'type' => 'delivery',
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.subtotal', '1000.00'); // 500 * 2
    }

    /** @test */
    public function it_uses_idempotency_key(): void
    {
        $idempotencyKey = 'test-order-' . uniqid();

        $headers = array_merge($this->headers, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        // First request
        $response1 = $this->withHeaders($headers)->postJson('/api/v1/orders', [
            'type' => 'pickup',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
            'phone' => '+79991234567',
        ]);

        $response1->assertStatus(201);
        $orderId = $response1->json('data.id');

        // Second request with same key - should return cached response
        $response2 = $this->withHeaders($headers)->postJson('/api/v1/orders', [
            'type' => 'pickup',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
            'phone' => '+79991234567',
        ]);

        $response2->assertStatus(201); // Same status as original
        $response2->assertHeader('X-Idempotent-Replayed', 'true');
        $this->assertEquals($orderId, $response2->json('data.id'));

        // Only one order should be created
        $this->assertEquals(1, Order::where('restaurant_id', $this->restaurant->id)->count());
    }

    /** @test */
    public function it_requires_orders_write_scope_for_create(): void
    {
        $limited = $this->createClientWithScopes(['orders:read']);

        $response = $this->withHeaders($limited['headers'])
            ->postJson('/api/v1/orders', [
                'type' => 'pickup',
                'items' => [
                    ['dish_id' => $this->dish->id, 'quantity' => 1],
                ],
            ]);

        $this->assertApiError($response, 403, 'INSUFFICIENT_SCOPE');
    }
}
