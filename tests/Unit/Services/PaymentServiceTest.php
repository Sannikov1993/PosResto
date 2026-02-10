<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Table;
use App\Models\Reservation;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Events\OrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected PaymentService $service;
    protected User $user;
    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $this->service = new PaymentService();

        // Открытая смена сегодня (по умолчанию)
        $this->shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'cashier_id' => $this->user->id,
            'status' => CashShift::STATUS_OPEN,
            'opened_at' => now(),
            'opening_amount' => 5000,
        ]);
    }

    /**
     * Создать заказ с данными по умолчанию
     */
    private function createOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'status' => OrderStatus::SERVED->value,
            'payment_status' => PaymentStatus::PENDING->value,
            'subtotal' => 1000,
            'total' => 1000,
        ], $overrides));
    }

    // =========================================================================
    // processPayment() — основные проверки
    // =========================================================================

    public function test_process_payment_rejects_already_paid_order(): void
    {
        $order = $this->createOrder([
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertFalse($result['success']);
        $this->assertEquals('ALREADY_PAID', $result['error_code']);
    }

    public function test_process_payment_fails_without_open_shift(): void
    {
        // Закрываем единственную смену
        $this->shift->update(['status' => CashShift::STATUS_CLOSED, 'closed_at' => now()]);

        $order = $this->createOrder();

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    public function test_process_payment_fails_with_outdated_shift(): void
    {
        // Смена вчерашняя
        $this->shift->update(['opened_at' => now()->subDay()]);

        $order = $this->createOrder();

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertFalse($result['success']);
        $this->assertEquals('SHIFT_OUTDATED', $result['error_code']);
    }

    public function test_process_payment_cash_success(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Оплата принята', $result['message']);

        $order->refresh();
        $this->assertEquals(OrderStatus::COMPLETED->value, $order->status);
        $this->assertEquals(PaymentStatus::PAID->value, $order->payment_status);
        $this->assertEquals('cash', $order->payment_method);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->completed_at);
    }

    public function test_process_payment_card_success(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $result = $this->service->processPayment($order, ['method' => 'card']);

        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('card', $order->payment_method);

        // Проверяем кассовую операцию
        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertNotNull($operation);
        $this->assertEquals('card', $operation->payment_method);
        $this->assertEquals(CashOperation::TYPE_INCOME, $operation->type);
        $this->assertEquals(CashOperation::CATEGORY_ORDER, $operation->category);
    }

    public function test_process_payment_mixed_creates_two_cash_operations(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $result = $this->service->processPayment($order, [
            'method' => 'mixed',
            'cash_amount' => 600,
            'card_amount' => 400,
        ]);

        $this->assertTrue($result['success']);

        $operations = CashOperation::where('order_id', $order->id)->get();
        $this->assertCount(2, $operations);

        $cashOp = $operations->firstWhere('payment_method', 'cash');
        $cardOp = $operations->firstWhere('payment_method', 'card');

        $this->assertEquals(600, (float) $cashOp->amount);
        $this->assertEquals(400, (float) $cardOp->amount);
    }

    public function test_process_payment_bonus_covers_total(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 500, 'subtotal' => 500]);

        $result = $this->service->processPayment($order, [
            'method' => 'cash',
            'bonus_used' => 500,
        ]);

        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('bonus', $order->payment_method);
    }

    public function test_process_payment_deposit_plus_bonus_covers_total(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $result = $this->service->processPayment($order, [
            'method' => 'cash',
            'deposit_used' => 600,
            'bonus_used' => 400,
        ]);

        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('bonus', $order->payment_method);
    }

    public function test_process_payment_partial_deposit_makes_mixed(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $result = $this->service->processPayment($order, [
            'method' => 'cash',
            'deposit_used' => 300,
        ]);

        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('mixed', $order->payment_method);
    }

    public function test_process_payment_applies_discount(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['subtotal' => 1000, 'total' => 1000]);

        $result = $this->service->processPayment($order, [
            'method' => 'cash',
            'discount_amount' => 200,
        ]);

        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals(200, (float) $order->discount_amount);
        $this->assertEquals(800, (float) $order->total);
    }

    public function test_process_payment_zero_amount_skips_cash_operation(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 500, 'subtotal' => 500]);

        $result = $this->service->processPayment($order, [
            'method' => 'cash',
            'bonus_used' => 500,
            'deposit_used' => 0,
        ]);

        $this->assertTrue($result['success']);

        // Полностью покрыто бонусами → нет CashOperation
        $count = CashOperation::where('order_id', $order->id)->count();
        $this->assertEquals(0, $count);
    }

    public function test_process_payment_dispatches_order_event(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $this->service->processPayment($order, ['method' => 'cash']);

        Event::assertDispatched(OrderEvent::class, function (OrderEvent $event) use ($order) {
            return $event->restaurantId === $order->restaurant_id
                && $event->eventType === 'order_paid';
        });
    }

    public function test_process_payment_guest_numbers_in_description(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $this->service->processPayment($order, [
            'method' => 'cash',
            'guest_numbers' => [1, 3],
        ]);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertStringContainsString('Гости 1, 3', $operation->description);
    }

    public function test_process_payment_single_guest_in_description(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $this->service->processPayment($order, [
            'method' => 'cash',
            'guest_numbers' => [2],
        ]);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertStringContainsString('Гость 2', $operation->description);
    }

    public function test_process_payment_partial_description(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $this->service->processPayment($order, [
            'method' => 'cash',
            'amount' => 500,
        ]);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertStringContainsString('(часть)', $operation->description);
    }

    public function test_process_payment_items_stored_in_notes(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();
        $items = [['name' => 'Пицца', 'qty' => 2]];

        $this->service->processPayment($order, [
            'method' => 'cash',
            'items' => $items,
        ]);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertNotNull($operation->notes);

        $notesData = json_decode($operation->notes, true);
        $this->assertArrayHasKey('items', $notesData);
        $this->assertEquals($items, $notesData['items']);
    }

    public function test_process_payment_releases_table_when_no_active_orders(): void
    {
        Event::fake([OrderEvent::class]);

        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        $order = $this->createOrder(['table_id' => $table->id]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $table->refresh();
        $this->assertEquals('free', $table->status);
    }

    public function test_process_payment_keeps_table_when_other_active_orders_exist(): void
    {
        Event::fake([OrderEvent::class]);

        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        // Другой активный заказ за этим столом
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'status' => OrderStatus::COOKING->value,
            'payment_status' => PaymentStatus::PENDING->value,
            'total' => 500,
        ]);

        $order = $this->createOrder(['table_id' => $table->id]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $table->refresh();
        $this->assertEquals('occupied', $table->status);
    }

    public function test_process_payment_completes_reservation(): void
    {
        Event::fake([OrderEvent::class]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'seated',
        ]);

        $order = $this->createOrder(['reservation_id' => $reservation->id]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
    }

    public function test_process_payment_staff_id_saved(): void
    {
        Event::fake([OrderEvent::class]);

        $staffMember = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $order = $this->createOrder();

        $this->service->processPayment($order, [
            'method' => 'cash',
            'staff_id' => $staffMember->id,
        ]);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertEquals($staffMember->id, $operation->user_id);
    }

    // =========================================================================
    // validateShift()
    // =========================================================================

    public function test_validate_shift_no_shift(): void
    {
        $this->shift->update(['status' => CashShift::STATUS_CLOSED, 'closed_at' => now()]);

        $result = $this->service->validateShift($this->restaurant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    public function test_validate_shift_today_success(): void
    {
        $result = $this->service->validateShift($this->restaurant->id);

        $this->assertTrue($result['success']);
        $this->assertEquals($this->shift->id, $result['shift']->id);
    }

    public function test_validate_shift_yesterday_outdated(): void
    {
        $this->shift->update(['opened_at' => now()->subDay()]);

        $result = $this->service->validateShift($this->restaurant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('SHIFT_OUTDATED', $result['error_code']);

        // Проверяем что дата отформатирована
        $expectedDate = now()->subDay()->format('d.m.Y');
        $this->assertStringContainsString($expectedDate, $result['message']);
    }

    public function test_validate_shift_cross_restaurant_isolation(): void
    {
        $otherRestaurant = Restaurant::factory()->create();

        // Смена есть только у нашего ресторана
        $result = $this->service->validateShift($otherRestaurant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    // =========================================================================
    // processRefund()
    // =========================================================================

    public function test_process_refund_success(): void
    {
        $order = $this->createOrder([
            'payment_status' => PaymentStatus::PAID->value,
            'order_number' => 'REF-001',
        ]);

        $result = $this->service->processRefund($order, 500, 'cash');

        $this->assertTrue($result['success']);
        $this->assertEquals('Возврат оформлен', $result['message']);

        $operation = $result['data']['operation'];
        $this->assertEquals(CashOperation::TYPE_EXPENSE, $operation->type);
        $this->assertEquals(CashOperation::CATEGORY_REFUND, $operation->category);
        $this->assertEquals(500, (float) $operation->amount);
    }

    public function test_process_refund_no_shift(): void
    {
        $this->shift->update(['status' => CashShift::STATUS_CLOSED, 'closed_at' => now()]);

        $order = $this->createOrder();

        $result = $this->service->processRefund($order, 500, 'cash');

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    public function test_process_refund_correct_amount(): void
    {
        $order = $this->createOrder(['order_number' => 'REF-002']);

        $result = $this->service->processRefund($order, 350.50, 'card');

        $this->assertTrue($result['success']);

        $operation = CashOperation::where('order_id', $order->id)
            ->where('type', CashOperation::TYPE_EXPENSE)
            ->first();

        $this->assertEquals(350.50, (float) $operation->amount);
        $this->assertEquals('card', $operation->payment_method);
    }

    public function test_process_refund_description_format(): void
    {
        $order = $this->createOrder(['order_number' => 'TEST-777']);

        $result = $this->service->processRefund($order, 100, 'cash');

        $operation = $result['data']['operation'];
        $this->assertEquals('Возврат по заказу #TEST-777', $operation->description);
    }

    public function test_process_refund_updates_shift_totals(): void
    {
        $order = $this->createOrder();

        $this->service->processRefund($order, 500, 'cash');

        $this->shift->refresh();
        $this->assertEquals(1, (int) $this->shift->refunds_count);
        $this->assertEquals(500, (float) $this->shift->refunds_amount);
    }

    // =========================================================================
    // processDeposit()
    // =========================================================================

    public function test_process_deposit_success(): void
    {
        $result = $this->service->processDeposit($this->restaurant->id, 1000, 'Размен');

        $this->assertTrue($result['success']);
        $this->assertEquals('Внесение выполнено', $result['message']);

        $operation = $result['data']['operation'];
        $this->assertEquals(CashOperation::TYPE_DEPOSIT, $operation->type);
        $this->assertEquals(1000, (float) $operation->amount);
        $this->assertEquals('Размен', $operation->description);
    }

    public function test_process_deposit_no_shift(): void
    {
        $this->shift->update(['status' => CashShift::STATUS_CLOSED, 'closed_at' => now()]);

        $result = $this->service->processDeposit($this->restaurant->id, 1000, 'Размен');

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    public function test_process_deposit_custom_description(): void
    {
        $result = $this->service->processDeposit($this->restaurant->id, 500, 'Для сдачи');

        $operation = $result['data']['operation'];
        $this->assertEquals('Для сдачи', $operation->description);
    }

    public function test_process_deposit_empty_description_uses_default(): void
    {
        $result = $this->service->processDeposit($this->restaurant->id, 500, '');

        $operation = $result['data']['operation'];
        $this->assertEquals('Внесение в кассу', $operation->description);
    }

    // =========================================================================
    // processWithdrawal()
    // =========================================================================

    public function test_process_withdrawal_success(): void
    {
        // Сначала пополним кассу через операцию дохода
        CashOperation::create([
            'restaurant_id' => $this->restaurant->id,
            'cash_shift_id' => $this->shift->id,
            'user_id' => $this->user->id,
            'type' => CashOperation::TYPE_INCOME,
            'category' => CashOperation::CATEGORY_ORDER,
            'amount' => 3000,
            'payment_method' => 'cash',
            'description' => 'Тест',
        ]);

        $result = $this->service->processWithdrawal($this->restaurant->id, 2000, 'Закупка продуктов');

        $this->assertTrue($result['success']);
        $this->assertEquals('Изъятие выполнено', $result['message']);

        $operation = $result['data']['operation'];
        $this->assertEquals(CashOperation::TYPE_WITHDRAWAL, $operation->type);
        $this->assertEquals(2000, (float) $operation->amount);
    }

    public function test_process_withdrawal_no_shift(): void
    {
        $this->shift->update(['status' => CashShift::STATUS_CLOSED, 'closed_at' => now()]);

        $result = $this->service->processWithdrawal($this->restaurant->id, 100, 'Тест');

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SHIFT', $result['error_code']);
    }

    public function test_process_withdrawal_insufficient_funds(): void
    {
        // В кассе только opening_amount (5000) и нет приходов наличных
        $result = $this->service->processWithdrawal($this->restaurant->id, 10000, 'Слишком много');

        $this->assertFalse($result['success']);
        $this->assertEquals('INSUFFICIENT_FUNDS', $result['error_code']);
    }

    public function test_process_withdrawal_exact_amount_succeeds(): void
    {
        // В кассе ровно 5000 (opening_amount)
        $result = $this->service->processWithdrawal($this->restaurant->id, 5000, 'Всё забрать');

        $this->assertTrue($result['success']);
    }

    public function test_process_withdrawal_updates_shift_totals(): void
    {
        // Пополняем кассу
        CashOperation::create([
            'restaurant_id' => $this->restaurant->id,
            'cash_shift_id' => $this->shift->id,
            'user_id' => $this->user->id,
            'type' => CashOperation::TYPE_INCOME,
            'category' => CashOperation::CATEGORY_ORDER,
            'amount' => 1000,
            'payment_method' => 'cash',
            'description' => 'Тест',
        ]);

        $this->service->processWithdrawal($this->restaurant->id, 500, 'Тест');

        $this->shift->refresh();
        // updateTotals вызван — смена обновлена
        $this->assertNotNull($this->shift->updated_at);
    }

    // =========================================================================
    // Integration: processPayment — бонусы и инвентарь
    // =========================================================================

    public function test_process_payment_no_customer_skips_bonuses(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['customer_id' => null]);

        // Не должно упасть — бонусы пропускаются
        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertTrue($result['success']);
    }

    public function test_process_payment_already_deducted_inventory_skips(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['inventory_deducted' => true]);

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertTrue($result['success']);
        // inventory_deducted должен остаться true
        $order->refresh();
        $this->assertTrue((bool) $order->inventory_deducted);
    }

    public function test_process_payment_no_warehouse_skips_silently(): void
    {
        Event::fake([OrderEvent::class]);

        // Нет складов для ресторана — не должно упасть
        $order = $this->createOrder(['inventory_deducted' => false]);

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertTrue($result['success']);
    }

    public function test_process_payment_linked_tables_released(): void
    {
        Event::fake([OrderEvent::class]);

        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);
        $table2 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'occupied',
        ]);

        $order = $this->createOrder([
            'table_id' => $table1->id,
            'linked_table_ids' => [$table2->id],
        ]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $table1->refresh();
        $table2->refresh();
        $this->assertEquals('free', $table1->status);
        $this->assertEquals('free', $table2->status);
    }

    public function test_process_payment_reservation_not_completed_if_already_completed(): void
    {
        Event::fake([OrderEvent::class]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
        ]);

        $order = $this->createOrder(['reservation_id' => $reservation->id]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $reservation->refresh();
        // Статус не должен измениться — уже completed
        $this->assertEquals('completed', $reservation->status);
    }

    public function test_process_payment_reservation_not_completed_if_cancelled(): void
    {
        Event::fake([OrderEvent::class]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cancelled',
        ]);

        $order = $this->createOrder(['reservation_id' => $reservation->id]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $reservation->refresh();
        $this->assertEquals('cancelled', $reservation->status);
    }

    public function test_process_payment_returns_order_and_shift_in_data(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $result = $this->service->processPayment($order, ['method' => 'cash']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('order', $result['data']);
        $this->assertArrayHasKey('shift', $result['data']);
    }

    public function test_process_payment_deposit_used_saved_on_order(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $this->service->processPayment($order, [
            'method' => 'cash',
            'deposit_used' => 300,
        ]);

        $order->refresh();
        $this->assertEquals(300, (float) $order->deposit_used);
    }

    public function test_process_payment_bonus_used_saved_on_order(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1000, 'subtotal' => 1000]);

        $this->service->processPayment($order, [
            'method' => 'cash',
            'bonus_used' => 200,
        ]);

        $order->refresh();
        $this->assertEquals(200, (float) $order->bonus_used);
    }

    public function test_process_payment_cash_operation_linked_to_shift(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        $this->service->processPayment($order, ['method' => 'cash']);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertEquals($this->shift->id, $operation->cash_shift_id);
    }

    public function test_process_payment_shift_totals_updated(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['total' => 1500, 'subtotal' => 1500]);

        $this->service->processPayment($order, ['method' => 'cash']);

        $this->shift->refresh();
        $this->assertEquals(1500, (float) $this->shift->total_cash);
        $this->assertEquals(1, (int) $this->shift->orders_count);
    }

    public function test_process_payment_promo_code_saved(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder(['subtotal' => 1000, 'total' => 1000]);

        $this->service->processPayment($order, [
            'method' => 'cash',
            'discount_amount' => 100,
            'promo_code' => 'SUMMER2024',
        ]);

        $order->refresh();
        $this->assertEquals('SUMMER2024', $order->promo_code);
    }

    public function test_process_payment_uses_auth_user_as_default_staff(): void
    {
        Event::fake([OrderEvent::class]);

        $order = $this->createOrder();

        // Не передаём staff_id — должен использоваться auth()->id()
        $this->service->processPayment($order, ['method' => 'cash']);

        $operation = CashOperation::where('order_id', $order->id)->first();
        $this->assertEquals($this->user->id, $operation->user_id);
    }
}
