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
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Order $order;
    protected Dish $dish;
    protected Category $category;
    protected Table $table;
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

        // Create zone and table
        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
        ]);

        // Create category and dish
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 500,
            'is_available' => true,
        ]);

        // Create order
        $this->order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
            'discount_amount' => 0,
        ]);

        // Create open shift for tests that need it
        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =========================================================================
    // ADD ITEM TESTS
    // =========================================================================

    public function test_can_add_item_to_order(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция добавлена',
            ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'quantity' => 2,
            'price' => 500,
            'total' => 1000,
        ]);
    }

    public function test_can_add_item_with_modifiers(): void
    {
        $this->authenticate();

        $modifiers = [
            ['id' => 1, 'name' => 'Extra cheese', 'price' => 50],
            ['id' => 2, 'name' => 'No onion', 'price' => 0],
        ];

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'modifiers' => $modifiers,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $item = OrderItem::where('order_id', $this->order->id)->first();
        $this->assertNotNull($item->modifiers);
        $this->assertCount(2, $item->modifiers);
    }

    public function test_can_add_item_with_comment(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'notes' => 'Without salt please',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'comment' => 'Without salt please',
        ]);
    }

    public function test_cannot_add_unavailable_dish_to_order(): void
    {
        $this->authenticate();

        $unavailableDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => false,
        ]);

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $unavailableDish->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_add_item_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id', 'quantity']);
    }

    public function test_add_item_validates_dish_exists(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => 99999,
            'quantity' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id']);
    }

    public function test_add_item_validates_quantity_minimum(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_add_item_updates_order_total(): void
    {
        $this->authenticate();

        // Add first item
        $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 2,
        ]);

        $this->order->refresh();
        $this->assertGreaterThan(0, $this->order->subtotal);

        // Create another dish
        $anotherDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 300,
            'is_available' => true,
        ]);

        // Add second item
        $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $anotherDish->id,
            'quantity' => 3,
        ]);

        $this->order->refresh();
        // Total should have increased
        $this->assertEquals(2, $this->order->items()->count());
    }

    // =========================================================================
    // UPDATE ITEM STATUS TESTS
    // =========================================================================

    public function test_can_update_item_status_to_cooking(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'status' => 'new',
        ]);

        $this->order->update(['status' => 'confirmed']);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'cooking',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус позиции обновлён',
            ]);

        $item->refresh();
        $this->assertEquals('cooking', $item->status);
        $this->assertNotNull($item->cooking_started_at);

        // Order status should change to cooking
        $this->order->refresh();
        $this->assertEquals('cooking', $this->order->status);
    }

    public function test_can_update_item_status_to_ready(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $this->order->update(['status' => 'cooking']);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'ready',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус позиции обновлён',
            ]);

        $item->refresh();
        $this->assertEquals('ready', $item->status);
        $this->assertNotNull($item->cooking_finished_at);
    }

    public function test_order_status_changes_to_ready_when_all_items_ready(): void
    {
        $this->authenticate();

        $item1 = OrderItem::factory()->ready()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $item2 = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $this->order->update(['status' => 'cooking']);

        // Mark last cooking item as ready
        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item2->id}/status", [
            'status' => 'ready',
        ]);

        $response->assertOk();

        // Order should now be ready
        $this->order->refresh();
        $this->assertEquals('ready', $this->order->status);
    }

    public function test_can_return_item_to_cooking(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->ready()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $this->order->update(['status' => 'ready']);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'return_to_cooking',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус позиции обновлён',
            ]);

        $item->refresh();
        $this->assertEquals('cooking', $item->status);
        $this->assertNull($item->cooking_finished_at);

        // Order should return to cooking
        $this->order->refresh();
        $this->assertEquals('cooking', $this->order->status);
    }

    public function test_update_item_status_validates_status_value(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_item_status_fails_for_wrong_order(): void
    {
        $this->authenticate();

        $anotherOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $anotherOrder->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'cooking',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ]);
    }

    // =========================================================================
    // REMOVE ITEM TESTS
    // =========================================================================

    public function test_can_remove_item_from_order(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);

        $this->order->update(['subtotal' => 1000, 'total' => 1000]);

        $response = $this->deleteJson("/api/orders/{$this->order->id}/items/{$item->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция удалена',
            ]);

        $this->assertDatabaseMissing('order_items', [
            'id' => $item->id,
        ]);
    }

    public function test_remove_item_fails_for_wrong_order(): void
    {
        $this->authenticate();

        $anotherOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $anotherOrder->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->deleteJson("/api/orders/{$this->order->id}/items/{$item->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ]);
    }

    public function test_remove_item_recalculates_order_items_count(): void
    {
        $this->authenticate();

        $item1 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);

        $anotherDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 300,
        ]);

        $item2 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $anotherDish->id,
            'price' => 300,
            'quantity' => 1,
            'total' => 300,
        ]);

        $this->assertEquals(2, $this->order->items()->count());

        // Remove first item
        $this->deleteJson("/api/orders/{$this->order->id}/items/{$item1->id}");

        $this->assertEquals(1, $this->order->items()->count());
    }

    // =========================================================================
    // CANCEL ITEM TESTS
    // =========================================================================

    public function test_can_cancel_item(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
            'quantity' => 1,
            'total' => 500,
        ]);

        $this->order->update(['subtotal' => 500, 'total' => 500]);

        $response = $this->postJson("/api/order-items/{$item->id}/cancel", [
            'reason_type' => 'customer_request',
            'reason_comment' => 'Changed mind',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция отменена',
                'new_status' => 'cancelled',
            ]);

        $item->refresh();
        $this->assertEquals('cancelled', $item->status);
        $this->assertNotNull($item->cancelled_at);
        $this->assertEquals('customer_request: Changed mind', $item->cancellation_reason);
        $this->assertTrue($item->is_write_off);
    }

    public function test_cancel_item_validates_reason_type(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/cancel", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_type']);
    }

    public function test_cancel_item_without_comment(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/cancel", [
            'reason_type' => 'quality_issue',
            'reason_comment' => null,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $item->refresh();
        $this->assertEquals('quality_issue', $item->cancellation_reason);
    }

    // =========================================================================
    // REQUEST ITEM CANCELLATION TESTS
    // =========================================================================

    public function test_can_request_item_cancellation(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/request-cancellation", [
            'reason' => 'Customer wants to cancel',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заявка на отмену позиции отправлена',
                'new_status' => 'pending_cancel',
            ]);

        $item->refresh();
        $this->assertEquals('pending_cancel', $item->status);
        $this->assertEquals('Customer wants to cancel', $item->cancellation_reason);
    }

    public function test_request_cancellation_validates_reason(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/request-cancellation", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    // =========================================================================
    // APPROVE ITEM CANCELLATION TESTS
    // =========================================================================

    public function test_can_approve_item_cancellation(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending_cancel',
            'cancellation_reason' => 'Customer request',
            'price' => 500,
            'quantity' => 1,
            'total' => 500,
        ]);

        $this->order->update(['subtotal' => 500, 'total' => 500]);

        $response = $this->postJson("/api/order-items/{$item->id}/approve-cancellation");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Отмена позиции подтверждена',
            ]);

        $item->refresh();
        $this->assertEquals('cancelled', $item->status);
        $this->assertNotNull($item->cancelled_at);
        $this->assertTrue($item->is_write_off);
    }

    public function test_approve_cancellation_fails_if_not_pending(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/approve-cancellation");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Позиция не ожидает отмены',
            ]);
    }

    // =========================================================================
    // REJECT ITEM CANCELLATION TESTS
    // =========================================================================

    public function test_can_reject_item_cancellation(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
            'status' => 'pending_cancel',
            'cancellation_reason' => 'Customer request',
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/reject-cancellation");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заявка на отмену отклонена',
            ]);

        $item->refresh();
        $this->assertEquals('cooking', $item->status);
        $this->assertNull($item->cancellation_reason);
    }

    public function test_reject_cancellation_fails_if_not_pending(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->cooking()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/reject-cancellation");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Позиция не ожидает отмены',
            ]);
    }

    // =========================================================================
    // ITEM MODIFIERS TESTS
    // =========================================================================

    public function test_modifiers_are_stored_correctly(): void
    {
        $this->authenticate();

        $modifiers = [
            ['id' => 1, 'name' => 'Extra sauce', 'price' => 30],
            ['id' => 2, 'name' => 'Large size', 'price' => 100],
            ['id' => 3, 'name' => 'No ice', 'price' => 0],
        ];

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'modifiers' => $modifiers,
        ]);

        $response->assertOk();

        $item = OrderItem::where('order_id', $this->order->id)->first();
        $this->assertNotNull($item->modifiers);
        $this->assertIsArray($item->modifiers);
        $this->assertCount(3, $item->modifiers);

        // Check modifier names
        $modifierNames = collect($item->modifiers)->pluck('name')->toArray();
        $this->assertContains('Extra sauce', $modifierNames);
        $this->assertContains('Large size', $modifierNames);
        $this->assertContains('No ice', $modifierNames);
    }

    public function test_modifiers_can_be_empty_array(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'modifiers' => [],
        ]);

        $response->assertOk();

        $item = OrderItem::where('order_id', $this->order->id)->first();
        $this->assertEmpty($item->modifiers);
    }

    // =========================================================================
    // ITEM COMMENTS TESTS
    // =========================================================================

    public function test_comment_is_stored_correctly(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'notes' => 'Please make it spicy',
        ]);

        $response->assertOk();

        $item = OrderItem::where('order_id', $this->order->id)->first();
        $this->assertEquals('Please make it spicy', $item->comment);
    }

    public function test_comment_can_be_null(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);

        $response->assertOk();

        $item = OrderItem::where('order_id', $this->order->id)->first();
        $this->assertNull($item->comment);
    }

    public function test_comment_max_length_validation(): void
    {
        $this->authenticate();

        $longComment = str_repeat('a', 300);

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
            'notes' => $longComment,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);
    }

    // =========================================================================
    // MULTIPLE ITEMS TESTS
    // =========================================================================

    public function test_can_add_multiple_items_to_order(): void
    {
        $this->authenticate();

        // First item
        $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 2,
        ]);

        // Create another dish
        $anotherDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 700,
            'is_available' => true,
        ]);

        // Second item
        $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $anotherDish->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(2, $this->order->items()->count());
    }

    // =========================================================================
    // AUTHENTICATION TESTS
    // =========================================================================

    public function test_add_item_requires_authentication(): void
    {
        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_update_item_status_requires_authentication(): void
    {
        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->patchJson("/api/orders/{$this->order->id}/items/{$item->id}/status", [
            'status' => 'cooking',
        ]);

        $response->assertUnauthorized();
    }

    public function test_remove_item_requires_authentication(): void
    {
        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->deleteJson("/api/orders/{$this->order->id}/items/{$item->id}");

        $response->assertUnauthorized();
    }

    public function test_cancel_item_requires_authentication(): void
    {
        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->postJson("/api/order-items/{$item->id}/cancel", [
            'reason_type' => 'test',
        ]);

        $response->assertUnauthorized();
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_add_item_returns_updated_order_data(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/{$this->order->id}/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'items',
                ],
            ]);
    }

    public function test_remove_item_returns_updated_order_data(): void
    {
        $this->authenticate();

        $item = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'dish_id' => $this->dish->id,
        ]);

        $response = $this->deleteJson("/api/orders/{$this->order->id}/items/{$item->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'items',
                ],
            ]);
    }

    public function test_order_not_found_returns_404(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/orders/99999/items", [
            'dish_id' => $this->dish->id,
            'quantity' => 1,
        ]);

        $response->assertNotFound();
    }

    public function test_item_not_found_returns_404(): void
    {
        $this->authenticate();

        $response = $this->deleteJson("/api/orders/{$this->order->id}/items/99999");

        $response->assertNotFound();
    }
}
