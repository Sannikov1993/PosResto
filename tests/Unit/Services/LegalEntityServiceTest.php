<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\LegalEntity;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\User;
use App\Services\LegalEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LegalEntityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected LegalEntityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->service = new LegalEntityService();
    }

    // =========================================================================
    // getDefaultLegalEntity()
    // =========================================================================

    public function test_get_default_legal_entity_returns_default(): void
    {
        $default = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $result = $this->service->getDefaultLegalEntity($this->restaurant->id);

        $this->assertNotNull($result);
        $this->assertEquals($default->id, $result->id);
    }

    public function test_get_default_legal_entity_returns_first_active_if_no_default(): void
    {
        $first = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ]);

        LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        $result = $this->service->getDefaultLegalEntity($this->restaurant->id);

        $this->assertNotNull($result);
        $this->assertEquals($first->id, $result->id);
    }

    public function test_get_default_legal_entity_ignores_inactive(): void
    {
        LegalEntity::factory()->default()->inactive()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $active = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $result = $this->service->getDefaultLegalEntity($this->restaurant->id);

        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
    }

    public function test_get_default_legal_entity_returns_null_if_none(): void
    {
        $result = $this->service->getDefaultLegalEntity($this->restaurant->id);

        $this->assertNull($result);
    }

    // =========================================================================
    // getDefaultCashRegister()
    // =========================================================================

    public function test_get_default_cash_register_returns_default(): void
    {
        $legalEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $default = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
        ]);

        CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $result = $this->service->getDefaultCashRegister($legalEntity->id);

        $this->assertNotNull($result);
        $this->assertEquals($default->id, $result->id);
    }

    public function test_get_default_cash_register_returns_first_active_if_no_default(): void
    {
        $legalEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $first = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $result = $this->service->getDefaultCashRegister($legalEntity->id);

        $this->assertNotNull($result);
        $this->assertEquals($first->id, $result->id);
    }

    public function test_get_default_cash_register_ignores_inactive(): void
    {
        $legalEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        CashRegister::factory()->default()->inactive()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
        ]);

        $active = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $result = $this->service->getDefaultCashRegister($legalEntity->id);

        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
    }

    public function test_get_default_cash_register_returns_null_if_none(): void
    {
        $legalEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $result = $this->service->getDefaultCashRegister($legalEntity->id);

        $this->assertNull($result);
    }

    // =========================================================================
    // splitOrderByLegalEntity()
    // =========================================================================

    public function test_split_order_by_legal_entity_single_entity(): void
    {
        $legalEntity = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $legalEntity->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'price' => 500,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => 2,
            'price' => 500,
            'total' => 1000,
        ]);

        $splits = $this->service->splitOrderByLegalEntity($order);

        $this->assertCount(1, $splits);
        $this->assertArrayHasKey($legalEntity->id, $splits);
        $this->assertEquals($legalEntity->id, $splits[$legalEntity->id]['legal_entity_id']);
        $this->assertEquals(1000, $splits[$legalEntity->id]['total']);
        $this->assertEquals(2, $splits[$legalEntity->id]['items_count']);
    }

    public function test_split_order_by_legal_entity_multiple_entities(): void
    {
        $entityFood = LegalEntity::factory()->ie()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ИП Еда',
        ]);

        $entityAlcohol = LegalEntity::factory()->llc()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ООО Алкоголь',
        ]);

        $categoryFood = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityFood->id,
        ]);

        $categoryAlcohol = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityAlcohol->id,
        ]);

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

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 1800,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishFood->id,
            'name' => $dishFood->name,
            'quantity' => 2,
            'price' => 500,
            'total' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishAlcohol->id,
            'name' => $dishAlcohol->name,
            'quantity' => 1,
            'price' => 800,
            'total' => 800,
        ]);

        $splits = $this->service->splitOrderByLegalEntity($order);

        $this->assertCount(2, $splits);
        $this->assertArrayHasKey($entityFood->id, $splits);
        $this->assertArrayHasKey($entityAlcohol->id, $splits);

        $this->assertEquals(1000, $splits[$entityFood->id]['total']);
        $this->assertEquals(800, $splits[$entityAlcohol->id]['total']);
    }

    public function test_split_order_distributes_discount_proportionally(): void
    {
        $entityFood = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $entityAlcohol = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $categoryFood = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityFood->id,
        ]);

        $categoryAlcohol = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entityAlcohol->id,
        ]);

        $dishFood = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryFood->id,
            'price' => 1000,
        ]);

        $dishAlcohol = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $categoryAlcohol->id,
            'price' => 1000,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 2000,
            'discount_amount' => 200, // 10% discount
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishFood->id,
            'name' => $dishFood->name,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dishAlcohol->id,
            'name' => $dishAlcohol->name,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);

        $splits = $this->service->splitOrderByLegalEntity($order);

        // Each entity has 50% of order, so each gets 50% of discount = 100
        $this->assertEquals(100, $splits[$entityFood->id]['discount']);
        $this->assertEquals(100, $splits[$entityAlcohol->id]['discount']);
        $this->assertEquals(900, $splits[$entityFood->id]['total_after_discount']);
        $this->assertEquals(900, $splits[$entityAlcohol->id]['total_after_discount']);
    }

    public function test_split_order_uses_default_entity_for_null_category(): void
    {
        $defaultEntity = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Category without legal entity
        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => null,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'price' => 500,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
        ]);

        $splits = $this->service->splitOrderByLegalEntity($order);

        $this->assertCount(1, $splits);
        $this->assertArrayHasKey($defaultEntity->id, $splits);
    }

    // =========================================================================
    // needsSplit()
    // =========================================================================

    public function test_needs_split_returns_true_for_multiple_entities(): void
    {
        $entity1 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $entity2 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category1 = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity1->id,
        ]);

        $category2 = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity2->id,
        ]);

        $dish1 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category1->id,
        ]);

        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category2->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish1->id,
            'name' => $dish1->name,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish2->id,
            'name' => $dish2->name,
            'quantity' => 1,
            'price' => 500,
            'total' => 500,
        ]);

        $this->assertTrue($this->service->needsSplit($order));
    }

    public function test_needs_split_returns_false_for_single_entity(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => 2,
            'price' => 500,
            'total' => 1000,
        ]);

        $this->assertFalse($this->service->needsSplit($order));
    }

    // =========================================================================
    // createSplitPaymentOperations()
    // =========================================================================

    public function test_create_split_payment_operations(): void
    {
        $user = User::factory()->create();

        $entity1 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ИП Тест',
            'short_name' => 'ИП',
        ]);

        $entity2 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ООО Тест',
            'short_name' => 'ООО',
        ]);

        $register1 = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity1->id,
        ]);

        $register2 = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity2->id,
        ]);

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => '001',
        ]);

        $splitData = [
            $entity1->id => [
                'legal_entity' => $entity1,
                'legal_entity_id' => $entity1->id,
                'legal_entity_name' => 'ИП Тест',
                'legal_entity_short_name' => 'ИП',
                'items' => [
                    ['name' => 'Пицца', 'quantity' => 1, 'price' => 500, 'total' => 500],
                ],
                'total' => 500,
                'total_after_discount' => 450,
                'discount' => 50,
                'items_count' => 1,
            ],
            $entity2->id => [
                'legal_entity' => $entity2,
                'legal_entity_id' => $entity2->id,
                'legal_entity_name' => 'ООО Тест',
                'legal_entity_short_name' => 'ООО',
                'items' => [
                    ['name' => 'Вино', 'quantity' => 1, 'price' => 800, 'total' => 800],
                ],
                'total' => 800,
                'total_after_discount' => 720,
                'discount' => 80,
                'items_count' => 1,
            ],
        ];

        $operations = $this->service->createSplitPaymentOperations(
            $order,
            $splitData,
            'card',
            $shift,
            $user->id
        );

        $this->assertCount(2, $operations);

        // Check first operation
        $op1 = $operations[0];
        $this->assertEquals($entity1->id, $op1->legal_entity_id);
        $this->assertEquals($register1->id, $op1->cash_register_id);
        $this->assertEquals(450, $op1->amount);
        $this->assertEquals('card', $op1->payment_method);
        $this->assertStringContainsString('001', $op1->description);

        // Check second operation
        $op2 = $operations[1];
        $this->assertEquals($entity2->id, $op2->legal_entity_id);
        $this->assertEquals($register2->id, $op2->cash_register_id);
        $this->assertEquals(720, $op2->amount);
    }

    public function test_create_split_payment_operations_skips_zero_amount(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $splitData = [
            $entity->id => [
                'legal_entity' => $entity,
                'legal_entity_id' => $entity->id,
                'legal_entity_name' => 'Тест',
                'legal_entity_short_name' => null,
                'items' => [],
                'total' => 0,
                'total_after_discount' => 0,
                'discount' => 0,
                'items_count' => 0,
            ],
        ];

        $operations = $this->service->createSplitPaymentOperations(
            $order,
            $splitData,
            'cash',
            $shift
        );

        $this->assertCount(0, $operations);
    }

    // =========================================================================
    // formatPaymentSplit()
    // =========================================================================

    public function test_format_payment_split(): void
    {
        $splitData = [
            1 => [
                'legal_entity_id' => 1,
                'legal_entity_name' => 'ИП Тест',
                'legal_entity_short_name' => 'ИП',
                'total' => 1000,
                'total_after_discount' => 900,
                'discount' => 100,
                'items_count' => 2,
            ],
            2 => [
                'legal_entity_id' => 2,
                'legal_entity_name' => 'ООО Тест',
                'legal_entity_short_name' => 'ООО',
                'total' => 500,
                'total_after_discount' => 500,
                'discount' => 0,
                'items_count' => 1,
            ],
        ];

        $result = $this->service->formatPaymentSplit($splitData);

        $this->assertArrayHasKey('splits', $result);
        $this->assertCount(2, $result['splits']);

        $this->assertEquals(1, $result['splits'][0]['legal_entity_id']);
        $this->assertEquals(900, $result['splits'][0]['amount']);
        $this->assertEquals(1000, $result['splits'][0]['subtotal']);
        $this->assertEquals(100, $result['splits'][0]['discount']);

        $this->assertEquals(2, $result['splits'][1]['legal_entity_id']);
        $this->assertEquals(500, $result['splits'][1]['amount']);
    }

    // =========================================================================
    // getLegalEntitiesWithStats()
    // =========================================================================

    public function test_get_legal_entities_with_stats(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        Category::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        CashRegister::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        // Inactive entity should not be returned
        LegalEntity::factory()->inactive()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $result = $this->service->getLegalEntitiesWithStats($this->restaurant->id);

        $this->assertCount(1, $result);
        $this->assertEquals($entity->id, $result[0]['id']);
        $this->assertEquals(3, $result[0]['categories_count']);
        $this->assertEquals(2, $result[0]['cash_registers_count']);
    }
}
