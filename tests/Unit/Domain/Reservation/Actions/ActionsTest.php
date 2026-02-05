<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation\Actions;

use App\Domain\Reservation\Actions\ActionResult;
use App\Domain\Reservation\Actions\CancelReservation;
use App\Domain\Reservation\Actions\CompleteReservation;
use App\Domain\Reservation\Actions\ConfirmReservation;
use App\Domain\Reservation\Actions\MarkNoShow;
use App\Domain\Reservation\Actions\SeatGuests;
use App\Domain\Reservation\Actions\SeatGuestsResult;
use App\Domain\Reservation\Actions\UnseatGuests;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\ReservationValidationException;
use App\Domain\Reservation\Exceptions\TableOccupiedException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionsTest extends TestCase
{
    use RefreshDatabase;

    private ReservationStateMachine $stateMachine;
    private Restaurant $restaurant;
    private Table $table;
    private \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new ReservationStateMachine();
        $this->restaurant = Restaurant::factory()->create();
        $this->user = \App\Models\User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'free',
        ]);
    }

    // ==================== ActionResult Tests ====================

    public function test_action_result_success_factory(): void
    {
        $reservation = $this->createReservation();

        $result = ActionResult::success(
            reservation: $reservation,
            message: 'Test message',
            metadata: ['key' => 'value']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Test message', $result->message);
        $this->assertEquals(['key' => 'value'], $result->metadata);
        $this->assertSame($reservation, $result->reservation);
    }

    public function test_action_result_to_array(): void
    {
        $reservation = $this->createReservation();

        $result = ActionResult::success(
            reservation: $reservation,
            message: 'Success',
            metadata: ['order_id' => 123]
        );

        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertEquals('Success', $array['message']);
        $this->assertEquals(123, $array['order_id']);
        $this->assertArrayHasKey('reservation', $array);
    }

    // ==================== SeatGuests Tests ====================

    public function test_seat_guests_success(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute($reservation, createOrder: false);

        $this->assertInstanceOf(SeatGuestsResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('seated', $result->reservation->status);
        $this->assertNotNull($result->reservation->seated_at);
    }

    public function test_seat_guests_creates_order(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute($reservation, createOrder: true, userId: $this->user->id);

        $this->assertTrue($result->hasOrder());
        $this->assertNotNull($result->order);
        $this->assertEquals($reservation->id, $result->order->reservation_id);
        $this->assertEquals('open', $result->order->status);
    }

    public function test_seat_guests_updates_table_status(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $action->execute($reservation, createOrder: false);

        $this->table->refresh();
        $this->assertEquals('occupied', $this->table->status);
    }

    public function test_seat_guests_succeeds_for_pending_reservation(): void
    {
        // Guests can arrive without confirmation and be seated directly
        $reservation = $this->createReservation(['status' => 'pending']);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute($reservation);

        $this->assertTrue($result->success);
        $this->assertEquals('seated', $result->reservation->status);
    }

    public function test_seat_guests_fails_for_cancelled_reservation(): void
    {
        $reservation = $this->createReservation(['status' => 'cancelled']);
        $action = new SeatGuests($this->stateMachine);

        $this->expectException(InvalidStateTransitionException::class);
        $action->execute($reservation);
    }

    public function test_seat_guests_fails_for_occupied_table(): void
    {
        // Create an actual active order on the table (not just status field)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'open',
            'payment_status' => 'pending',
        ]);
        $this->table->update(['status' => 'occupied']);

        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $this->expectException(TableOccupiedException::class);
        $action->execute($reservation);
    }

    public function test_seat_guests_auto_fixes_stale_occupied_status(): void
    {
        // Table has status='occupied' but NO active orders (stale status)
        $this->table->update(['status' => 'occupied']);

        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        // Should succeed because there's no actual order, just stale status
        $result = $action->execute($reservation);

        $this->assertInstanceOf(SeatGuestsResult::class, $result);
        $this->assertEquals(ReservationStatus::SEATED->value, $result->reservation->status);
        // Table should now be occupied (with a real order)
        $this->table->refresh();
        $this->assertEquals('occupied', $this->table->status);
    }

    public function test_seat_guests_with_deposit_transfer(): void
    {
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'deposit' => 1000.00,
            'deposit_status' => 'paid',
        ]);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute(
            $reservation,
            createOrder: true,
            transferDeposit: true
        );

        $this->assertTrue($result->depositTransferred);
        $reservation->refresh();
        $this->assertEquals('transferred', $reservation->deposit_status);
        $this->assertEquals($result->order->id, $reservation->deposit_transferred_to_order_id);
    }

    // ==================== UnseatGuests Tests ====================

    public function test_unseat_guests_success(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $this->table->update(['status' => 'occupied']);
        $action = new UnseatGuests($this->stateMachine);

        $result = $action->execute($reservation);

        $this->assertTrue($result->success);
        $this->assertEquals('confirmed', $result->reservation->status);
        $this->assertNotNull($result->reservation->unseated_at);
    }

    public function test_unseat_guests_frees_table(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $this->table->update(['status' => 'occupied']);
        $action = new UnseatGuests($this->stateMachine);

        $action->execute($reservation);

        $this->table->refresh();
        $this->assertEquals('free', $this->table->status);
    }

    public function test_unseat_guests_fails_with_unpaid_orders(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        Order::factory()->create([
            'reservation_id' => $reservation->id,
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'total' => 500,
            'paid_amount' => 0,
        ]);
        $action = new UnseatGuests($this->stateMachine);

        $this->expectException(ReservationValidationException::class);
        $action->execute($reservation, force: false);
    }

    public function test_unseat_guests_with_force_ignores_orders(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        Order::factory()->create([
            'reservation_id' => $reservation->id,
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'total' => 500,
            'paid_amount' => 0,
        ]);
        $action = new UnseatGuests($this->stateMachine);

        $result = $action->execute($reservation, force: true);

        $this->assertTrue($result->success);
        $this->assertEquals('confirmed', $result->reservation->status);
    }

    // ==================== CompleteReservation Tests ====================

    public function test_complete_reservation_success(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $action = new CompleteReservation($this->stateMachine);

        $result = $action->execute($reservation);

        $this->assertTrue($result->success);
        $this->assertEquals('completed', $result->reservation->status);
        $this->assertNotNull($result->reservation->completed_at);
    }

    public function test_complete_reservation_closes_orders(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $order = Order::factory()->create([
            'reservation_id' => $reservation->id,
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'total' => 500,
            'paid_at' => now(),
            'paid_amount' => 500,
        ]);
        $action = new CompleteReservation($this->stateMachine);

        $action->execute($reservation);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertNotNull($order->closed_at);
    }

    public function test_complete_reservation_fails_with_unpaid_orders(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        Order::factory()->create([
            'reservation_id' => $reservation->id,
            'table_id' => $this->table->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'total' => 500,
            'paid_amount' => 100,
        ]);
        $action = new CompleteReservation($this->stateMachine);

        $this->expectException(ReservationValidationException::class);
        $action->execute($reservation, force: false);
    }

    public function test_complete_reservation_frees_table(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $this->table->update(['status' => 'occupied']);
        $action = new CompleteReservation($this->stateMachine);

        $action->execute($reservation);

        $this->table->refresh();
        $this->assertEquals('free', $this->table->status);
    }

    // ==================== CancelReservation Tests ====================

    public function test_cancel_reservation_from_pending(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        $action = new CancelReservation($this->stateMachine);

        $result = $action->execute($reservation, reason: 'Customer request');

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->reservation->status);
        $this->assertEquals('Customer request', $result->reservation->cancellation_reason);
    }

    public function test_cancel_reservation_from_confirmed(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new CancelReservation($this->stateMachine);

        $result = $action->execute($reservation);

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->reservation->status);
    }

    public function test_cancel_reservation_fails_from_seated(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $action = new CancelReservation($this->stateMachine);

        $this->expectException(InvalidStateTransitionException::class);
        $action->execute($reservation);
    }

    public function test_cancel_reservation_with_deposit_refund(): void
    {
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'deposit' => 500.00,
            'deposit_status' => 'paid',
        ]);
        $action = new CancelReservation($this->stateMachine);

        $result = $action->execute($reservation, refundDeposit: true);

        $this->assertTrue($result->metadata['deposit_refunded']);
        $reservation->refresh();
        $this->assertEquals('refunded', $reservation->deposit_status);
    }

    public function test_cancel_reservation_without_deposit_refund(): void
    {
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'deposit' => 500.00,
            'deposit_status' => 'paid',
        ]);
        $action = new CancelReservation($this->stateMachine);

        $result = $action->execute($reservation, refundDeposit: false);

        $this->assertFalse($result->metadata['deposit_refunded']);
        $reservation->refresh();
        $this->assertEquals('paid', $reservation->deposit_status);
    }

    // ==================== ConfirmReservation Tests ====================

    public function test_confirm_reservation_success(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        $action = new ConfirmReservation($this->stateMachine);

        $result = $action->execute($reservation, userId: 1);

        $this->assertTrue($result->success);
        $this->assertEquals('confirmed', $result->reservation->status);
        $this->assertNotNull($result->reservation->confirmed_at);
        $this->assertEquals(1, $result->reservation->confirmed_by);
    }

    public function test_confirm_reservation_fails_from_seated(): void
    {
        $reservation = $this->createReservation(['status' => 'seated']);
        $action = new ConfirmReservation($this->stateMachine);

        $this->expectException(InvalidStateTransitionException::class);
        $action->execute($reservation);
    }

    // ==================== MarkNoShow Tests ====================

    public function test_mark_no_show_success(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new MarkNoShow($this->stateMachine);

        $result = $action->execute($reservation);

        $this->assertTrue($result->success);
        $this->assertEquals('no_show', $result->reservation->status);
        $this->assertNotNull($result->reservation->no_show_at);
    }

    public function test_mark_no_show_with_deposit_forfeiture(): void
    {
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'deposit' => 1000.00,
            'deposit_status' => 'paid',
        ]);
        $action = new MarkNoShow($this->stateMachine);

        $result = $action->execute($reservation, forfeitDeposit: true);

        $this->assertTrue($result->metadata['deposit_forfeited']);
        $reservation->refresh();
        $this->assertEquals('forfeited', $reservation->deposit_status);
    }

    public function test_mark_no_show_with_notes(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new MarkNoShow($this->stateMachine);

        $result = $action->execute(
            $reservation,
            notes: 'Called 3 times, no answer'
        );

        $this->assertStringContains('[No-show]', $result->reservation->notes);
        $this->assertStringContains('Called 3 times', $result->reservation->notes);
    }

    public function test_mark_no_show_fails_from_pending(): void
    {
        $reservation = $this->createReservation(['status' => 'pending']);
        $action = new MarkNoShow($this->stateMachine);

        $this->expectException(InvalidStateTransitionException::class);
        $action->execute($reservation);
    }

    // ==================== SeatGuestsResult Tests ====================

    public function test_seat_guests_result_has_order(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute($reservation, createOrder: true);

        $this->assertTrue($result->hasOrder());
        $this->assertNotEmpty($result->getTableIds());
    }

    public function test_seat_guests_result_without_order(): void
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $action = new SeatGuests($this->stateMachine);

        $result = $action->execute($reservation, createOrder: false);

        $this->assertFalse($result->hasOrder());
        $this->assertNull($result->order);
    }

    // ==================== Helper Methods ====================

    private function createReservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'status' => 'pending',
            'date' => now()->toDateString(),
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ], $attributes));
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
