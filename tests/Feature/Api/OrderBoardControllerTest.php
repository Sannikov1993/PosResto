<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class OrderBoardControllerTest extends TestCase
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
        ]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // BASIC FUNCTIONALITY TESTS
    // =====================================================

    public function test_can_get_order_board(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'daily_number',
                        'order_number',
                        'status',
                        'type',
                        'created_at',
                        'cooking_started_at',
                        'ready_at',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_order_board_returns_only_cooking_orders(): void
    {
        // Cooking order - should be included
        $cookingOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'cooking_started_at' => now(),
        ]);

        // New order - should NOT be included
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_NEW,
            'daily_number' => '#002',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($cookingOrder->id, $data[0]['id']);
        $this->assertEquals(Order::STATUS_COOKING, $data[0]['status']);
    }

    public function test_order_board_returns_only_ready_orders(): void
    {
        // Ready order - should be included
        $readyOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#001',
            'ready_at' => now(),
        ]);

        // Completed order - should NOT be included
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COMPLETED,
            'daily_number' => '#002',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($readyOrder->id, $data[0]['id']);
        $this->assertEquals(Order::STATUS_READY, $data[0]['status']);
    }

    public function test_order_board_returns_both_cooking_and_ready_orders(): void
    {
        // Cooking order
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        // Ready order
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#002',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    // =====================================================
    // ORDER FILTERING BY STATUS TESTS
    // =====================================================

    public function test_order_board_excludes_new_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_NEW,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_confirmed_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_CONFIRMED,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_served_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_SERVED,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_delivering_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_DELIVERING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_completed_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COMPLETED,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_cancelled_orders(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_CANCELLED,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // DATE FILTERING TESTS
    // =====================================================

    public function test_order_board_returns_only_today_orders(): void
    {
        // Today's order - should be included
        $todayOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'created_at' => now(),
        ]);

        // Yesterday's order - should NOT be included
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#002',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($todayOrder->id, $data[0]['id']);
    }

    public function test_order_board_excludes_future_orders(): void
    {
        // Tomorrow's order - should NOT be included (edge case)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'created_at' => now()->addDay(),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_excludes_old_orders(): void
    {
        // Week old order - should NOT be included
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#001',
            'created_at' => now()->subWeek(),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // RESTAURANT ISOLATION TESTS
    // =====================================================

    public function test_order_board_is_scoped_to_restaurant(): void
    {
        // Order in our restaurant - should be included
        $ourOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        // Order in another restaurant - should NOT be included
        $otherRestaurant = Restaurant::factory()->create();
        Order::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#002',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($ourOrder->id, $data[0]['id']);
    }

    public function test_order_board_shows_correct_restaurant_orders(): void
    {
        // Create orders in our restaurant and another
        $otherRestaurant = Restaurant::factory()->create();

        $ourOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#R1-001',
        ]);

        Order::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#R2-001',
        ]);

        // Should only see our restaurant's orders
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($ourOrder->id, $data[0]['id']);
    }

    // =====================================================
    // ORDERING TESTS
    // =====================================================

    public function test_order_board_orders_by_created_at_ascending(): void
    {
        // Create orders with specific times
        $olderOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'created_at' => now()->subMinutes(30),
        ]);

        $newerOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#002',
            'created_at' => now()->subMinutes(10),
        ]);

        $middleOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#003',
            'created_at' => now()->subMinutes(20),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Oldest first, newest last
        $this->assertEquals($olderOrder->id, $data[0]['id']);
        $this->assertEquals($middleOrder->id, $data[1]['id']);
        $this->assertEquals($newerOrder->id, $data[2]['id']);
    }

    // =====================================================
    // LIMIT TESTS
    // =====================================================

    public function test_order_board_limits_to_100_orders(): void
    {
        // Create 110 orders
        Order::factory()->count(110)->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(100, $data);
    }

    // =====================================================
    // RESPONSE STRUCTURE TESTS
    // =====================================================

    public function test_order_board_returns_correct_fields(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#042',
            'order_number' => 'ORD-2024-0042',
            'type' => Order::TYPE_DINE_IN,
            'cooking_started_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.daily_number', '#042')
            ->assertJsonPath('data.0.order_number', 'ORD-2024-0042')
            ->assertJsonPath('data.0.status', Order::STATUS_COOKING)
            ->assertJsonPath('data.0.type', Order::TYPE_DINE_IN);

        // Verify only selected fields are returned (no sensitive data)
        $this->assertArrayNotHasKey('subtotal', $response->json('data.0'));
        $this->assertArrayNotHasKey('total', $response->json('data.0'));
        $this->assertArrayNotHasKey('customer_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('phone', $response->json('data.0'));
        $this->assertArrayNotHasKey('delivery_address', $response->json('data.0'));
        $this->assertArrayNotHasKey('payment_status', $response->json('data.0'));
        $this->assertArrayNotHasKey('comment', $response->json('data.0'));
    }

    public function test_order_board_returns_timestamps(): void
    {
        $cookingStartedAt = now()->subMinutes(10);
        $readyAt = now()->subMinutes(2);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#001',
            'cooking_started_at' => $cookingStartedAt,
            'ready_at' => $readyAt,
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['cooking_started_at']);
        $this->assertNotNull($data['ready_at']);
    }

    public function test_order_board_returns_empty_array_when_no_orders(): void
    {
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // ORDER TYPE TESTS
    // =====================================================

    public function test_order_board_shows_dine_in_orders(): void
    {
        $order = Order::factory()->dineIn()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Order::TYPE_DINE_IN, $data[0]['type']);
    }

    public function test_order_board_shows_delivery_orders(): void
    {
        $order = Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Order::TYPE_DELIVERY, $data[0]['type']);
    }

    public function test_order_board_shows_pickup_orders(): void
    {
        $order = Order::factory()->pickup()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Order::TYPE_PICKUP, $data[0]['type']);
    }

    public function test_order_board_shows_all_order_types(): void
    {
        Order::factory()->dineIn()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#002',
        ]);

        Order::factory()->pickup()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#003',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);

        $types = collect($data)->pluck('type')->toArray();
        $this->assertContains(Order::TYPE_DINE_IN, $types);
        $this->assertContains(Order::TYPE_DELIVERY, $types);
        $this->assertContains(Order::TYPE_PICKUP, $types);
    }

    // =====================================================
    // AUTHENTICATED ACCESS TESTS
    // =====================================================

    public function test_authenticated_user_can_access_order_board(): void
    {
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_uses_own_restaurant_if_no_restaurant_id_provided(): void
    {
        // Order in user's restaurant
        $userOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        // Order in other restaurant
        $otherRestaurant = Restaurant::factory()->create();
        Order::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#002',
        ]);

        $response = $this->getJson('/api/order-board');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($userOrder->id, $data[0]['id']);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_order_board_handles_order_at_midnight(): void
    {
        // Order created at the start of today
        Carbon::setTestNow(Carbon::today()->setHour(10));

        $midnightOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'created_at' => Carbon::today()->setHour(0)->setMinute(0)->setSecond(1),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($midnightOrder->id, $data[0]['id']);

        Carbon::setTestNow();
    }

    public function test_order_board_handles_order_at_end_of_day(): void
    {
        // Order created at 23:59:59
        Carbon::setTestNow(Carbon::today()->setHour(23)->setMinute(59)->setSecond(59));

        $endOfDayOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#999',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($endOfDayOrder->id, $data[0]['id']);

        Carbon::setTestNow();
    }

    public function test_order_board_handles_large_daily_numbers(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#99999',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.daily_number', '#99999');
    }

    public function test_order_board_handles_mixed_status_orders(): void
    {
        // Create orders with various statuses
        $cookingOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'created_at' => now()->subMinutes(5),
        ]);

        $readyOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#002',
            'created_at' => now()->subMinutes(3),
        ]);

        // These should be excluded
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_NEW,
            'daily_number' => '#003',
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COMPLETED,
            'daily_number' => '#004',
        ]);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $ids = collect($data)->pluck('id')->toArray();
        $this->assertContains($cookingOrder->id, $ids);
        $this->assertContains($readyOrder->id, $ids);
    }

    // =====================================================
    // PERFORMANCE / STRESS TESTS
    // =====================================================

    public function test_order_board_performs_well_with_many_orders(): void
    {
        // Create 50 orders that should appear
        Order::factory()->count(50)->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
        ]);

        // Create 50 orders that should NOT appear
        Order::factory()->count(50)->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $startTime = microtime(true);

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(50, $data);

        // Should complete in under 1 second
        $this->assertLessThan(1000, $executionTime);
    }

    // =====================================================
    // REALTIME DISPLAY SCENARIO TESTS
    // =====================================================

    public function test_order_board_reflects_status_change_to_cooking(): void
    {
        // Create order in 'new' status (should not appear)
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_NEW,
            'daily_number' => '#001',
        ]);

        // Verify not in board
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $this->assertCount(0, $response->json('data'));

        // Update status to cooking
        $order->update([
            'status' => Order::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);

        // Now should appear in board
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($order->id, $data[0]['id']);
    }

    public function test_order_board_reflects_status_change_to_ready(): void
    {
        // Create order in 'cooking' status
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
            'cooking_started_at' => now()->subMinutes(10),
        ]);

        // Verify in board with cooking status
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $this->assertEquals(Order::STATUS_COOKING, $response->json('data.0.status'));

        // Update status to ready
        $order->update([
            'status' => Order::STATUS_READY,
            'ready_at' => now(),
        ]);

        // Should still appear but with ready status
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Order::STATUS_READY, $data[0]['status']);
    }

    public function test_order_board_removes_completed_orders(): void
    {
        // Create order in 'ready' status
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_READY,
            'daily_number' => '#001',
        ]);

        // Verify in board
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $this->assertCount(1, $response->json('data'));

        // Complete the order
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Should disappear from board
        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // DAILY NUMBER DISPLAY TESTS
    // =====================================================

    public function test_order_board_displays_various_daily_number_formats(): void
    {
        // Different daily number formats that might be used
        $formats = ['#001', '#42', '001', '42', '#A-001', 'T5-001'];

        foreach ($formats as $index => $format) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'status' => Order::STATUS_COOKING,
                'daily_number' => $format,
                'created_at' => now()->addSeconds($index), // Ensure order
            ]);
        }

        $response = $this->getJson("/api/order-board?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(6, $data);

        $dailyNumbers = collect($data)->pluck('daily_number')->toArray();
        foreach ($formats as $format) {
            $this->assertContains($format, $dailyNumbers);
        }
    }

    // =====================================================
    // ERROR HANDLING TESTS
    // =====================================================

    public function test_order_board_with_invalid_restaurant_id_returns_empty(): void
    {
        $response = $this->getJson('/api/order-board?restaurant_id=999999');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_order_board_without_restaurant_id_uses_default(): void
    {
        // Use the restaurant from setUp (it's the first one created, so has id=1)
        // The ResolvesRestaurantId trait uses id=1 as default
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => Order::STATUS_COOKING,
            'daily_number' => '#001',
        ]);

        $response = $this->getJson('/api/order-board');

        $response->assertOk();
        // Should either work with default or return empty (depends on implementation)
        $this->assertTrue($response->json('success'));
    }
}
