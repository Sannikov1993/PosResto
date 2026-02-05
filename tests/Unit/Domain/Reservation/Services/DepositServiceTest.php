<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation\Services;

use App\Domain\Reservation\Exceptions\DepositException;
use App\Domain\Reservation\Services\DepositService;
use App\Domain\Reservation\Services\DepositTransferResult;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositServiceTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $service;
    private Restaurant $restaurant;
    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DepositService();
        $this->restaurant = Restaurant::factory()->create();
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    // ==================== Check Methods ====================

    public function test_requires_deposit_returns_true_when_deposit_set(): void
    {
        $reservation = $this->createReservation(['deposit' => 500.00]);

        $this->assertTrue($this->service->requiresDeposit($reservation));
    }

    public function test_requires_deposit_returns_false_when_no_deposit(): void
    {
        $reservation = $this->createReservation(['deposit' => 0]);

        $this->assertFalse($this->service->requiresDeposit($reservation));
    }

    public function test_is_paid_returns_true_for_paid_status(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->assertTrue($this->service->isPaid($reservation));
    }

    public function test_is_paid_returns_false_for_pending_status(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PENDING,
        ]);

        $this->assertFalse($this->service->isPaid($reservation));
    }

    public function test_can_collect_returns_true_for_pending_with_deposit(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PENDING,
        ]);

        $this->assertTrue($this->service->canCollect($reservation));
    }

    public function test_can_collect_returns_false_for_already_paid(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->assertFalse($this->service->canCollect($reservation));
    }

    public function test_can_refund_returns_true_for_paid(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->assertTrue($this->service->canRefund($reservation));
    }

    public function test_can_refund_returns_false_for_transferred(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_TRANSFERRED,
        ]);

        $this->assertFalse($this->service->canRefund($reservation));
    }

    public function test_can_transfer_returns_true_for_paid_with_amount(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->assertTrue($this->service->canTransfer($reservation));
    }

    public function test_can_transfer_returns_false_for_zero_deposit(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 0,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->assertFalse($this->service->canTransfer($reservation));
    }

    // ==================== markAsPaid Tests ====================

    public function test_mark_as_paid_success(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PENDING,
        ]);

        $result = $this->service->markAsPaid(
            $reservation,
            paymentMethod: 'card',
            transactionId: 'txn_123',
            userId: 1
        );

        $this->assertEquals(DepositService::STATUS_PAID, $result->deposit_status);
        $this->assertNotNull($result->deposit_paid_at);
        $this->assertEquals(1, $result->deposit_paid_by);
        $this->assertEquals('card', $result->deposit_payment_method);
        $this->assertEquals('txn_123', $result->deposit_transaction_id);
    }

    public function test_mark_as_paid_throws_for_already_paid(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $this->expectException(DepositException::class);
        $this->service->markAsPaid($reservation);
    }

    public function test_mark_as_paid_throws_for_refunded(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_REFUNDED,
        ]);

        $this->expectException(DepositException::class);
        $this->service->markAsPaid($reservation);
    }

    // ==================== refund Tests ====================

    public function test_refund_success(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $result = $this->service->refund(
            $reservation,
            reason: 'Customer request',
            userId: 1
        );

        $this->assertEquals(DepositService::STATUS_REFUNDED, $result->deposit_status);
        $this->assertNotNull($result->deposit_refunded_at);
        $this->assertEquals(1, $result->deposit_refunded_by);
        $this->assertEquals('Customer request', $result->deposit_refund_reason);
    }

    public function test_refund_throws_for_transferred(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_TRANSFERRED,
            'deposit_transferred_to_order_id' => 123,
        ]);

        $this->expectException(DepositException::class);
        $this->service->refund($reservation);
    }

    public function test_refund_throws_for_already_refunded(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_REFUNDED,
        ]);

        $this->expectException(DepositException::class);
        $this->service->refund($reservation);
    }

    public function test_refund_throws_for_pending(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PENDING,
        ]);

        $this->expectException(DepositException::class);
        $this->service->refund($reservation);
    }

    // ==================== transferToOrder Tests ====================

    public function test_transfer_to_order_success(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $order = Order::factory()->create([
            'reservation_id' => $reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'prepaid_amount' => 0,
        ]);

        $result = $this->service->transferToOrder($reservation, $order, userId: 1);

        $this->assertInstanceOf(DepositTransferResult::class, $result);
        $this->assertEquals(500.00, $result->amount);
        $this->assertEquals(DepositService::STATUS_TRANSFERRED, $result->reservation->deposit_status);
        $this->assertEquals($order->id, $result->reservation->deposit_transferred_to_order_id);
        $this->assertEquals(500.00, $result->order->prepaid_amount);
        $this->assertEquals('reservation_deposit', $result->order->prepaid_source);
    }

    public function test_transfer_to_order_throws_for_wrong_order(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        // Create another reservation and link the order to it
        $anotherReservation = $this->createReservation([
            'deposit' => 0,
        ]);

        $wrongOrder = Order::factory()->create([
            'reservation_id' => $anotherReservation->id, // Different reservation
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $this->expectException(DepositException::class);
        $this->service->transferToOrder($reservation, $wrongOrder);
    }

    public function test_transfer_to_order_throws_for_already_transferred(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_TRANSFERRED,
        ]);

        $order = Order::factory()->create([
            'reservation_id' => $reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $this->expectException(DepositException::class);
        $this->service->transferToOrder($reservation, $order);
    }

    public function test_transfer_adds_to_existing_prepaid(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 300.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $order = Order::factory()->create([
            'reservation_id' => $reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'prepaid_amount' => 200.00,
        ]);

        $result = $this->service->transferToOrder($reservation, $order);

        $this->assertEquals(500.00, $result->order->prepaid_amount);
    }

    // ==================== forfeit Tests ====================

    public function test_forfeit_success(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $result = $this->service->forfeit(
            $reservation,
            reason: 'No-show after 30 minutes',
            userId: 1
        );

        $this->assertEquals(DepositService::STATUS_FORFEITED, $result->deposit_status);
        $this->assertNotNull($result->deposit_forfeited_at);
        $this->assertEquals(1, $result->deposit_forfeited_by);
        $this->assertEquals('No-show after 30 minutes', $result->deposit_forfeit_reason);
    }

    public function test_forfeit_uses_default_reason(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $result = $this->service->forfeit($reservation);

        $this->assertEquals('No-show', $result->deposit_forfeit_reason);
    }

    public function test_forfeit_throws_for_pending(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PENDING,
        ]);

        $this->expectException(DepositException::class);
        $this->service->forfeit($reservation);
    }

    // ==================== getStatusLabel Tests ====================

    public function test_get_status_label_returns_correct_labels(): void
    {
        $statuses = [
            DepositService::STATUS_PENDING => 'Ожидает оплаты',
            DepositService::STATUS_PAID => 'Оплачен',
            DepositService::STATUS_REFUNDED => 'Возвращён',
            DepositService::STATUS_TRANSFERRED => 'Перенесён в заказ',
            DepositService::STATUS_FORFEITED => 'Конфискован',
        ];

        foreach ($statuses as $status => $expectedLabel) {
            $reservation = $this->createReservation(['deposit_status' => $status]);
            $this->assertEquals($expectedLabel, $this->service->getStatusLabel($reservation));
        }
    }

    // ==================== getSummary Tests ====================

    public function test_get_summary_returns_complete_data(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
            'deposit_paid_at' => now(),
        ]);

        $summary = $this->service->getSummary($reservation);

        $this->assertEquals(500.00, $summary['amount']);
        $this->assertEquals(DepositService::STATUS_PAID, $summary['status']);
        $this->assertEquals('Оплачен', $summary['status_label']);
        $this->assertTrue($summary['is_paid']);
        $this->assertTrue($summary['can_refund']);
        $this->assertTrue($summary['can_transfer']);
    }

    // ==================== DepositTransferResult Tests ====================

    public function test_deposit_transfer_result_to_array(): void
    {
        $reservation = $this->createReservation([
            'deposit' => 500.00,
            'deposit_status' => DepositService::STATUS_PAID,
        ]);

        $order = Order::factory()->create([
            'reservation_id' => $reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $result = $this->service->transferToOrder($reservation, $order);
        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertEquals($reservation->id, $array['reservation_id']);
        $this->assertEquals($order->id, $array['order_id']);
        $this->assertEquals(500.00, $array['amount']);
        $this->assertStringContainsString('500', $array['message']);
    }

    // ==================== Helper Methods ====================

    private function createReservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'confirmed',
            'date' => now()->toDateString(),
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
            'deposit' => 0,
            'deposit_status' => DepositService::STATUS_PENDING,
        ], $attributes));
    }
}
