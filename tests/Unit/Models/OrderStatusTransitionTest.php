<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@example.com',
            'is_active' => true,
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function createOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
        ], $attributes));
    }

    // ===== CONFIRM =====

    public function test_can_confirm_new_order(): void
    {
        $order = $this->createOrder(['status' => Order::STATUS_NEW]);

        $result = $order->confirm();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->confirmed_at);
    }

    // ===== START COOKING =====

    public function test_can_start_cooking_from_new(): void
    {
        $order = $this->createOrder(['status' => Order::STATUS_NEW]);

        $result = $order->startCooking();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_COOKING, $order->status);
        $this->assertNotNull($order->cooking_started_at);
    }

    public function test_can_start_cooking_from_confirmed(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $result = $order->startCooking();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_COOKING, $order->status);
        $this->assertNotNull($order->cooking_started_at);
    }

    // ===== MARK READY =====

    public function test_can_mark_ready(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_COOKING,
            'cooking_started_at' => now()->subMinutes(10),
        ]);

        $result = $order->markReady();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_READY, $order->status);
        $this->assertNotNull($order->ready_at);
        $this->assertNotNull($order->cooking_finished_at);
    }

    // ===== MARK SERVED =====

    public function test_can_mark_served(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_READY,
            'ready_at' => now()->subMinutes(2),
        ]);

        $result = $order->markServed();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_SERVED, $order->status);
    }

    // ===== START DELIVERING =====

    public function test_can_start_delivering(): void
    {
        $courier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'courier',
            'is_active' => true,
        ]);

        $order = $this->createOrder([
            'status' => Order::STATUS_READY,
            'type' => Order::TYPE_DELIVERY,
            'ready_at' => now()->subMinutes(2),
        ]);

        $result = $order->startDelivering($courier->id);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERING, $order->status);
        $this->assertEquals($courier->id, $order->courier_id);
        $this->assertNotNull($order->picked_up_at);
    }

    // ===== COMPLETE =====

    public function test_can_complete_order(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_SERVED,
        ]);

        $result = $order->complete();

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    // ===== CANCEL =====

    public function test_can_cancel_order(): void
    {
        $order = $this->createOrder(['status' => Order::STATUS_NEW]);

        $result = $order->cancel('Customer changed mind');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals('Customer changed mind', $order->cancel_reason);
    }

    public function test_cannot_cancel_completed_order(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $result = $order->cancel('Too late');

        $this->assertFalse($result);
        $order->refresh();
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
    }

    // ===== TABLE FREEING =====

    public function test_complete_frees_table(): void
    {
        $zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $table = Table::factory()->occupied()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
        ]);

        $order = $this->createOrder([
            'status' => Order::STATUS_SERVED,
            'table_id' => $table->id,
        ]);

        $result = $order->complete();

        $this->assertTrue($result);
        $table->refresh();
        $this->assertEquals('free', $table->status);
    }

    public function test_cancel_frees_table(): void
    {
        $zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $table = Table::factory()->occupied()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
        ]);

        $order = $this->createOrder([
            'status' => Order::STATUS_COOKING,
            'table_id' => $table->id,
        ]);

        $order->cancel('Kitchen issue');

        $table->refresh();
        $this->assertEquals('free', $table->status);
    }

    // ===== PAYMENT =====

    public function test_mark_paid_updates_payment_fields(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_SERVED,
            'total' => 1500,
        ]);

        $order->markPaid('cash', 1000);

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('cash', $order->payment_method);
        $this->assertEquals(1000, $order->paid_amount);
    }
}
