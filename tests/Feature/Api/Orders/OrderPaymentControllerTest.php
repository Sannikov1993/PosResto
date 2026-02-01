<?php

namespace Tests\Feature\Api\Orders;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\LegalEntity;
use App\Models\CashRegister;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected CashShift $shift;
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

        $this->shift = CashShift::factory()->create([
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
        ]);

        return $order;
    }

    // =========================================================================
    // PAY TESTS
    // =========================================================================

    public function test_can_pay_order_with_cash(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1000);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Оплата принята',
            ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('cash', $order->payment_method);
        $this->assertNotNull($order->paid_at);

        // Check cash operation created
        $this->assertDatabaseHas('cash_operations', [
            'order_id' => $order->id,
            'type' => 'income',
            'category' => 'order',
            'payment_method' => 'cash',
        ]);
    }

    public function test_can_pay_order_with_card(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1500);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('card', $order->payment_method);
    }

    public function test_can_pay_order_with_online(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(2000);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'online',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('online', $order->payment_method);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->paid()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_pay_without_open_shift(): void
    {
        $this->authenticate();

        // Close the shift
        $this->shift->update(['status' => 'closed']);

        $order = $this->createOrderWithItems();

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_pay_with_outdated_shift(): void
    {
        $this->authenticate();

        // Make shift from yesterday
        $this->shift->update(['opened_at' => now()->subDay()]);

        $order = $this->createOrderWithItems();

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'SHIFT_OUTDATED');
    }

    public function test_pay_validates_method(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems();

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'invalid_method',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_pay_applies_discount(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1000);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
            'discount_amount' => 100,
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(100, $order->discount_amount);
        $this->assertEquals(900, $order->total);
    }

    public function test_pay_applies_bonus(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1000);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
            'bonus_used' => 50,
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals(50, $order->bonus_used);
        $this->assertEquals(950, $order->total);
    }

    // =========================================================================
    // LEGAL ENTITY SPLIT TESTS
    // =========================================================================

    public function test_pay_creates_split_operations_for_multiple_legal_entities(): void
    {
        $this->authenticate();

        // Create two legal entities
        $entityFood = LegalEntity::factory()->ie()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ИП Еда',
        ]);

        $entityAlcohol = LegalEntity::factory()->llc()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ООО Алкоголь',
        ]);

        // Create cash registers
        CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityFood->id,
        ]);

        CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityAlcohol->id,
        ]);

        // Create categories with legal entities
        $categoryFood = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityFood->id,
        ]);

        $categoryAlcohol = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityAlcohol->id,
        ]);

        // Create dishes
        $dishFood = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryFood->id,
            'price' => 500,
        ]);

        $dishAlcohol = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryAlcohol->id,
            'price' => 800,
        ]);

        // Create order with items from both entities
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'pending',
            'subtotal' => 1300,
            'total' => 1300,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishFood->id,
            'name' => $dishFood->name,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishAlcohol->id,
            'name' => $dishAlcohol->name,
            'quantity' => 1,
            'price' => 800,
            'total' => 800,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'card',
        ]);

        $response->assertOk();

        // Check payment_split is saved
        $order->refresh();
        $this->assertNotNull($order->payment_split);
        $this->assertArrayHasKey('splits', $order->payment_split);
        $this->assertCount(2, $order->payment_split['splits']);

        // Check two cash operations created
        $operations = CashOperation::where('order_id', $order->id)->get();
        $this->assertCount(2, $operations);

        // Check legal entities are assigned
        $legalEntityIds = $operations->pluck('legal_entity_id')->toArray();
        $this->assertContains($entityFood->id, $legalEntityIds);
        $this->assertContains($entityAlcohol->id, $legalEntityIds);
    }

    public function test_pay_creates_single_operation_for_single_legal_entity(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1000);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertOk();

        // Check only one cash operation created
        $operations = CashOperation::where('order_id', $order->id)->get();
        $this->assertCount(1, $operations);
    }

    // =========================================================================
    // CANCEL WITH WRITE-OFF TESTS
    // =========================================================================

    public function test_can_cancel_order_with_writeoff(): void
    {
        $this->authenticate();

        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'status' => 'cooking',
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", [
            'reason' => 'Клиент передумал',
            'manager_id' => $manager->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заказ отменён со списанием',
            ]);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Клиент передумал', $order->cancel_reason);
        $this->assertEquals($manager->id, $order->cancelled_by);
        $this->assertTrue($order->is_write_off);

        // Table should be freed
        $table->refresh();
        $this->assertEquals('free', $table->status);
    }

    public function test_cancel_validates_required_fields(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason', 'manager_id']);
    }

    public function test_cancel_validates_manager_exists(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", [
            'reason' => 'Test',
            'manager_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['manager_id']);
    }

    public function test_cancel_releases_linked_tables(): void
    {
        $this->authenticate();

        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        $table2 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table1->id,
            'linked_table_ids' => [$table1->id, $table2->id],
            'status' => 'cooking',
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", [
            'reason' => 'Test',
            'manager_id' => $manager->id,
        ]);

        $response->assertOk();

        // Both tables should be freed
        $table1->refresh();
        $table2->refresh();
        $this->assertEquals('free', $table1->status);
        $this->assertEquals('free', $table2->status);
    }

    // =========================================================================
    // AUTHORIZATION TESTS
    // =========================================================================

    public function test_pay_requires_authentication(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'method' => 'cash',
        ]);

        $response->assertUnauthorized();
    }

    public function test_cancel_requires_authentication(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/cancel-with-writeoff", [
            'reason' => 'Test',
            'manager_id' => 1,
        ]);

        $response->assertUnauthorized();
    }

    // =========================================================================
    // PAYMENT SPLIT PREVIEW TESTS
    // =========================================================================

    public function test_payment_split_preview_returns_no_split_for_single_entity(): void
    {
        $this->authenticate();

        $order = $this->createOrderWithItems(1000);

        $response = $this->getJson("/api/orders/{$order->id}/payment-split-preview");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'has_split' => false,
                'splits' => [],
            ]);
    }

    public function test_payment_split_preview_returns_splits_for_multiple_entities(): void
    {
        $this->authenticate();

        // Create two legal entities
        $entityFood = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ИП Иванов',
            'short_name' => 'ИП',
            'is_default' => true,
        ]);

        $entityAlcohol = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ООО Ресторан',
            'short_name' => 'ООО',
        ]);

        // Create categories with different legal entities
        $categoryFood = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityFood->id,
        ]);

        $categoryAlcohol = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityAlcohol->id,
        ]);

        // Create dishes
        $dishFood = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryFood->id,
            'price' => 500,
        ]);

        $dishAlcohol = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryAlcohol->id,
            'price' => 800,
        ]);

        // Create order with items from both entities
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'pending',
            'subtotal' => 1300,
            'total' => 1300,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishFood->id,
            'name' => $dishFood->name,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishAlcohol->id,
            'name' => $dishAlcohol->name,
            'quantity' => 1,
            'price' => 800,
            'total' => 800,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment-split-preview");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'has_split' => true,
            ]);

        $data = $response->json();
        $this->assertCount(2, $data['splits']);
        $this->assertEquals(1300, $data['total']);

        // Check both legal entities are present
        $entityNames = collect($data['splits'])->pluck('legal_entity_name')->toArray();
        $this->assertContains('ИП Иванов', $entityNames);
        $this->assertContains('ООО Ресторан', $entityNames);
    }

    public function test_payment_split_preview_requires_authentication(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment-split-preview");

        $response->assertUnauthorized();
    }
}
