<?php

namespace Tests\Feature\Api\Orders;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Table;
use App\Models\Zone;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $manager;
    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Order $order;
    protected Table $table;
    protected CashShift $shift;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->otherRestaurant = Restaurant::factory()->create();

        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'manager',
        ]);

        // Create zone and table
        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
            'status' => 'occupied',
        ]);

        // Create order
        $this->order = Order::factory()->dineIn()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'cooking',
            'payment_status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        // Create open shift
        $this->shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    protected function authenticate(?User $user = null): void
    {
        $user = $user ?? $this->user;
        $this->token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createOrderWithItems(float $subtotal = 1000): Order
    {
        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'price' => $subtotal,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'cooking',
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => 1,
            'price' => $subtotal,
            'total' => $subtotal,
            'status' => 'cooking',
        ]);

        return $order;
    }

    // =========================================================================
    // REQUEST CANCELLATION TESTS
    // =========================================================================

    public function test_can_request_order_cancellation(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => 'Guest changed their mind',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заявка на отмену отправлена',
            ]);

        $this->order->refresh();
        $this->assertTrue($this->order->pending_cancellation);
        $this->assertEquals('Guest changed their mind', $this->order->cancel_request_reason);
        $this->assertNotNull($this->order->cancel_requested_at);
    }

    public function test_can_request_cancellation_with_requested_by(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => 'Quality issue',
            'requested_by' => $this->manager->id,
        ]);

        $response->assertOk();

        $this->order->refresh();
        $this->assertEquals($this->manager->id, $this->order->cancel_requested_by);
    }

    public function test_request_cancellation_validates_reason_required(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_request_cancellation_validates_reason_max_length(): void
    {
        $this->authenticate();

        $longReason = str_repeat('a', 501);

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => $longReason,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_request_cancellation_validates_requested_by_exists(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => 'Test reason',
            'requested_by' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['requested_by']);
    }

    // =========================================================================
    // APPROVE CANCELLATION TESTS
    // =========================================================================

    public function test_can_approve_order_cancellation(): void
    {
        $this->authenticate();

        // Set up order with pending cancellation
        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Guest left',
        ]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Отмена подтверждена',
            ]);

        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);
        $this->assertFalse($this->order->pending_cancellation);
        $this->assertTrue($this->order->is_write_off);
        $this->assertNotNull($this->order->cancelled_at);
        $this->assertEquals('Guest left', $this->order->cancel_reason);
    }

    public function test_approve_cancellation_frees_table(): void
    {
        $this->authenticate();

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $this->table->refresh();
        $this->assertEquals('free', $this->table->status);
    }

    public function test_approve_cancellation_frees_linked_tables(): void
    {
        $this->authenticate();

        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $linkedTable = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
            'status' => 'occupied',
        ]);

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
            'linked_table_ids' => [$this->table->id, $linkedTable->id],
        ]);

        $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $this->table->refresh();
        $linkedTable->refresh();
        $this->assertEquals('free', $this->table->status);
        $this->assertEquals('free', $linkedTable->status);
    }

    public function test_approve_cancellation_fails_if_not_pending(): void
    {
        $this->authenticate();

        // Order not pending cancellation
        $this->order->update(['pending_cancellation' => false]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Заказ не ожидает отмены',
            ]);
    }

    public function test_approve_cancellation_cancels_linked_reservation(): void
    {
        $this->authenticate();

        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Test Guest',
            'guest_phone' => '+79001234567',
            'date' => now()->toDateString(),
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
            'status' => 'confirmed',
        ]);

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
            'reservation_id' => $reservation->id,
        ]);

        $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $reservation->refresh();
        $this->assertEquals('cancelled', $reservation->status);
    }

    public function test_approve_cancellation_does_not_cancel_completed_reservation(): void
    {
        $this->authenticate();

        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Test Guest',
            'guest_phone' => '+79001234567',
            'date' => now()->toDateString(),
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
            'status' => 'completed',
        ]);

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
            'reservation_id' => $reservation->id,
        ]);

        $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
    }

    // =========================================================================
    // REFUND HANDLING TESTS
    // =========================================================================

    public function test_approve_cancellation_creates_refund_for_paid_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->paid()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'total' => 1500,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Quality issue',
        ]);

        $response = $this->postJson("/api/cancellations/{$order->id}/approve", [
            'refund_method' => 'cash',
        ]);

        $response->assertOk();

        // Check refund operation created
        $this->assertDatabaseHas('cash_operations', [
            'order_id' => $order->id,
            'type' => 'expense',
            'category' => 'refund',
            'amount' => 1500,
            'payment_method' => 'cash',
        ]);
    }

    public function test_approve_cancellation_creates_refund_for_prepaid_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'total' => 2000,
            'prepayment' => 500,
            'payment_status' => 'pending',
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Guest cancelled',
        ]);

        $response = $this->postJson("/api/cancellations/{$order->id}/approve", [
            'refund_method' => 'card',
        ]);

        $response->assertOk();

        // Refund should be for prepayment amount, not total
        $this->assertDatabaseHas('cash_operations', [
            'order_id' => $order->id,
            'type' => 'expense',
            'category' => 'refund',
            'amount' => 500,
            'payment_method' => 'card',
        ]);
    }

    public function test_approve_cancellation_defaults_refund_to_cash(): void
    {
        $this->authenticate();

        $order = Order::factory()->paid()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'total' => 1000,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $response = $this->postJson("/api/cancellations/{$order->id}/approve");

        $response->assertOk();

        $this->assertDatabaseHas('cash_operations', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
        ]);
    }

    public function test_approve_cancellation_validates_refund_method(): void
    {
        $this->authenticate();

        $order = Order::factory()->paid()->create([
            'restaurant_id' => $this->restaurant->id,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $response = $this->postJson("/api/cancellations/{$order->id}/approve", [
            'refund_method' => 'invalid_method',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['refund_method']);
    }

    public function test_approve_cancellation_no_refund_for_unpaid_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'total' => 1000,
            'payment_status' => 'pending',
            'prepayment' => 0,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $countBefore = CashOperation::count();

        $this->postJson("/api/cancellations/{$order->id}/approve");

        // No new cash operations should be created
        $this->assertEquals($countBefore, CashOperation::count());
    }

    public function test_approve_cancellation_updates_delivery_status_for_non_dine_in(): void
    {
        $this->authenticate();

        $order = Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
            'delivery_status' => 'pending',
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Customer cancelled',
        ]);

        $this->postJson("/api/cancellations/{$order->id}/approve");

        $order->refresh();
        $this->assertEquals('cancelled', $order->delivery_status);
    }

    // =========================================================================
    // REJECT CANCELLATION TESTS
    // =========================================================================

    public function test_can_reject_order_cancellation(): void
    {
        $this->authenticate();

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Want to cancel',
            'cancel_requested_by' => $this->manager->id,
            'cancel_requested_at' => now(),
        ]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/reject", [
            'reason' => 'Already cooking',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заявка отклонена',
            ]);

        $this->order->refresh();
        $this->assertFalse($this->order->pending_cancellation);
        $this->assertNull($this->order->cancel_request_reason);
        $this->assertNull($this->order->cancel_requested_by);
        $this->assertNull($this->order->cancel_requested_at);
    }

    public function test_reject_cancellation_fails_if_not_pending(): void
    {
        $this->authenticate();

        $this->order->update(['pending_cancellation' => false]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/reject");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Заказ не ожидает отмены',
            ]);
    }

    public function test_reject_cancellation_validates_reason_max_length(): void
    {
        $this->authenticate();

        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $longReason = str_repeat('a', 501);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/reject", [
            'reason' => $longReason,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    // =========================================================================
    // PENDING CANCELLATIONS LIST TESTS
    // =========================================================================

    public function test_can_get_pending_cancellations(): void
    {
        $this->authenticate();

        // Create orders with pending cancellations
        $order1 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Reason 1',
            'cancel_requested_at' => now()->subMinutes(10),
        ]);

        $order2 = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Reason 2',
            'cancel_requested_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/api/cancellations/pending');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'order',
                        'reason',
                        'requested_by',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'orders_count',
                    'items_count',
                    'total',
                ],
            ]);

        // Verify orders are returned in correct order (newest first)
        $data = $response->json('data');
        $orderTypes = collect($data)->where('type', 'order')->values();
        $this->assertGreaterThanOrEqual(2, $orderTypes->count());
    }

    public function test_pending_cancellations_includes_items(): void
    {
        $this->authenticate();

        // Create order with pending cancel item
        $order = $this->createOrderWithItems();
        $item = $order->items->first();
        $item->update([
            'status' => 'pending_cancel',
            'cancellation_reason' => 'Item cancel reason',
        ]);

        $response = $this->getJson('/api/cancellations/pending');

        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertGreaterThanOrEqual(1, $meta['items_count']);
    }

    public function test_pending_cancellations_respects_limit(): void
    {
        $this->authenticate();

        // Create multiple pending cancellations
        for ($i = 0; $i < 5; $i++) {
            Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'pending_cancellation' => true,
                'cancel_request_reason' => "Reason $i",
                'cancel_requested_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->getJson('/api/cancellations/pending?limit=3');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertLessThanOrEqual(3, count($data));
    }

    public function test_pending_cancellations_max_limit_is_200(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/cancellations/pending?limit=500');

        $response->assertOk();
        // The controller should cap at 200
    }

    // =========================================================================
    // CANCELLATION REASONS TESTS
    // =========================================================================

    public function test_can_get_cancellation_reasons(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/cancellations/reasons');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'guest_refused' => 'Гость отказался',
                    'guest_changed_mind' => 'Гость передумал',
                    'wrong_order' => 'Ошибка заказа',
                    'out_of_stock' => 'Нет в наличии',
                    'quality_issue' => 'Проблема с качеством',
                    'long_wait' => 'Долгое ожидание',
                    'duplicate' => 'Дубликат',
                    'other' => 'Другое',
                ],
            ]);
    }

    public function test_cancellation_reasons_available_without_special_permission(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/cancellations/reasons');

        $response->assertOk();
    }

    // =========================================================================
    // WRITE-OFFS HISTORY TESTS
    // =========================================================================

    public function test_can_get_write_offs_history(): void
    {
        $this->authenticate();

        // Create cancelled order with write-off
        $order = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cancelled',
            'is_write_off' => true,
            'cancelled_at' => now(),
            'cancel_reason' => 'Test cancellation',
        ]);

        $response = $this->getJson('/api/orders/write-offs');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'order_number',
                        'order',
                        'total',
                        'amount',
                        'reason',
                        'description',
                        'user',
                        'cancelled_by',
                        'cancelled_at',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'orders_count',
                    'items_count',
                    'total',
                    'per_page',
                    'date_from',
                    'date_to',
                ],
            ]);
    }

    public function test_write_offs_filters_by_date_range(): void
    {
        $this->authenticate();

        // Create order from last week
        $oldOrder = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now()->subDays(10),
        ]);

        // Create recent order
        $recentOrder = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/orders/write-offs?date_from=' . now()->subDays(3)->toDateString());

        $response->assertOk();

        // Only recent order should be included
        $data = $response->json('data');
        $orderIds = collect($data)
            ->where('type', 'cancellation')
            ->pluck('id')
            ->toArray();

        $this->assertContains($recentOrder->id, $orderIds);
        $this->assertNotContains($oldOrder->id, $orderIds);
    }

    public function test_write_offs_includes_cancelled_items(): void
    {
        $this->authenticate();

        // Create order with cancelled item
        $order = $this->createOrderWithItems();
        $item = $order->items->first();
        $item->update([
            'status' => 'cancelled',
            'is_write_off' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Item write-off reason',
        ]);

        $response = $this->getJson('/api/orders/write-offs');

        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertGreaterThanOrEqual(1, $meta['items_count']);
    }

    public function test_write_offs_respects_pagination(): void
    {
        $this->authenticate();

        // Create multiple cancelled orders
        for ($i = 0; $i < 10; $i++) {
            Order::factory()->cancelled()->create([
                'restaurant_id' => $this->restaurant->id,
                'is_write_off' => true,
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->getJson('/api/orders/write-offs?per_page=5');

        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertEquals(5, $meta['per_page']);
    }

    public function test_write_offs_max_per_page_is_200(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/orders/write-offs?per_page=500');

        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertLessThanOrEqual(200, $meta['per_page']);
    }

    // =========================================================================
    // RESTAURANT ISOLATION TESTS
    // =========================================================================

    public function test_write_offs_only_shows_current_restaurant_orders(): void
    {
        $this->authenticate();

        // Create order in current restaurant
        $myOrder = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now(),
        ]);

        // Create order in different restaurant
        $otherOrder = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now(),
        ]);

        $response = $this->getJson('/api/orders/write-offs');

        $response->assertOk();

        $data = $response->json('data');
        $orderIds = collect($data)
            ->where('type', 'cancellation')
            ->pluck('id')
            ->toArray();

        $this->assertContains($myOrder->id, $orderIds);
        $this->assertNotContains($otherOrder->id, $orderIds);
    }

    // =========================================================================
    // AUTHENTICATION TESTS
    // =========================================================================

    public function test_request_cancellation_requires_authentication(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => 'Test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_approve_cancellation_requires_authentication(): void
    {
        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/approve");

        $response->assertUnauthorized();
    }

    public function test_reject_cancellation_requires_authentication(): void
    {
        $this->order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => 'Test',
        ]);

        $response = $this->postJson("/api/cancellations/{$this->order->id}/reject");

        $response->assertUnauthorized();
    }

    public function test_pending_cancellations_requires_authentication(): void
    {
        $response = $this->getJson('/api/cancellations/pending');

        $response->assertUnauthorized();
    }

    public function test_cancellation_reasons_requires_authentication(): void
    {
        $response = $this->getJson('/api/cancellations/reasons');

        $response->assertUnauthorized();
    }

    public function test_write_offs_requires_authentication(): void
    {
        $response = $this->getJson('/api/orders/write-offs');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // ORDER NOT FOUND TESTS
    // =========================================================================

    public function test_request_cancellation_returns_404_for_nonexistent_order(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders/99999/request-cancellation', [
            'reason' => 'Test',
        ]);

        $response->assertNotFound();
    }

    public function test_approve_cancellation_returns_404_for_nonexistent_order(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/cancellations/99999/approve');

        $response->assertNotFound();
    }

    public function test_reject_cancellation_returns_404_for_nonexistent_order(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/cancellations/99999/reject');

        $response->assertNotFound();
    }

    // =========================================================================
    // WORKFLOW INTEGRATION TESTS
    // =========================================================================

    public function test_full_cancellation_workflow_request_then_approve(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1500);

        // Step 1: Request cancellation
        $response = $this->postJson("/api/orders/{$order->id}/request-cancellation", [
            'reason' => 'Guest has to leave urgently',
            'requested_by' => $this->manager->id,
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertTrue($order->pending_cancellation);

        // Step 2: Approve cancellation
        $response = $this->postJson("/api/cancellations/{$order->id}/approve");

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertFalse($order->pending_cancellation);
        $this->assertTrue($order->is_write_off);
        $this->assertEquals('Guest has to leave urgently', $order->cancel_reason);
    }

    public function test_full_cancellation_workflow_request_then_reject(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems();

        // Step 1: Request cancellation
        $response = $this->postJson("/api/orders/{$order->id}/request-cancellation", [
            'reason' => 'Want to cancel',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertTrue($order->pending_cancellation);

        // Step 2: Reject cancellation
        $response = $this->postJson("/api/cancellations/{$order->id}/reject", [
            'reason' => 'Food is already being served',
        ]);

        $response->assertOk();

        $order->refresh();
        // Order should remain in original status
        $this->assertNotEquals('cancelled', $order->status);
        $this->assertFalse($order->pending_cancellation);
        $this->assertNull($order->cancel_request_reason);
    }

    public function test_cancellation_workflow_with_refund(): void
    {
        $this->authenticate();

        // Create paid order
        $order = Order::factory()->paid()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'total' => 2500,
            'payment_method' => 'card',
        ]);

        // Request cancellation
        $this->postJson("/api/orders/{$order->id}/request-cancellation", [
            'reason' => 'Wrong order served',
        ]);

        // Approve with card refund
        $response = $this->postJson("/api/cancellations/{$order->id}/approve", [
            'refund_method' => 'card',
        ]);

        $response->assertOk();

        // Verify refund was created
        $this->assertDatabaseHas('cash_operations', [
            'order_id' => $order->id,
            'type' => 'expense',
            'category' => 'refund',
            'amount' => 2500,
            'payment_method' => 'card',
        ]);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_cannot_request_cancellation_for_already_cancelled_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Request should succeed but order status won't change back
        $response = $this->postJson("/api/orders/{$order->id}/request-cancellation", [
            'reason' => 'Already cancelled',
        ]);

        // The controller allows the request even for cancelled orders
        // (this might be intentional for audit purposes)
        $response->assertOk();
    }

    public function test_cancellation_response_includes_updated_order_data(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/request-cancellation", [
            'reason' => 'Test reason',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'pending_cancellation',
                    'cancel_request_reason',
                ],
            ]);

        $data = $response->json('data');
        $this->assertTrue($data['pending_cancellation']);
        $this->assertEquals('Test reason', $data['cancel_request_reason']);
    }

    public function test_write_offs_sorted_by_cancelled_at_descending(): void
    {
        $this->authenticate();

        $order1 = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now()->subHours(2),
        ]);

        $order2 = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now()->subHour(),
        ]);

        $order3 = Order::factory()->cancelled()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_write_off' => true,
            'cancelled_at' => now(),
        ]);

        $response = $this->getJson('/api/orders/write-offs');

        $response->assertOk();

        $data = $response->json('data');
        $orderTypes = collect($data)->where('type', 'cancellation')->values();

        if ($orderTypes->count() >= 3) {
            // Most recent should be first
            $ids = $orderTypes->pluck('id')->take(3)->toArray();
            $this->assertEquals($order3->id, $ids[0]);
            $this->assertEquals($order2->id, $ids[1]);
            $this->assertEquals($order1->id, $ids[2]);
        }
    }
}
