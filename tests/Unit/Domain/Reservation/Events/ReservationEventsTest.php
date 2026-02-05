<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation\Events;

use App\Domain\Reservation\Events\DepositForfeited;
use App\Domain\Reservation\Events\DepositPaid;
use App\Domain\Reservation\Events\DepositRefunded;
use App\Domain\Reservation\Events\DepositTransferred;
use App\Domain\Reservation\Events\ReservationCancelled;
use App\Domain\Reservation\Events\ReservationCompleted;
use App\Domain\Reservation\Events\ReservationConfirmed;
use App\Domain\Reservation\Events\ReservationCreated;
use App\Domain\Reservation\Events\ReservationNoShow;
use App\Domain\Reservation\Events\ReservationSeated;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReservationEventsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Table $table;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'deposit' => 500.00,
        ]);
    }

    // ==================== ReservationCreated ====================

    public function test_reservation_created_event(): void
    {
        $event = new ReservationCreated($this->reservation, userId: 1);

        $this->assertEquals('reservation.created', $event->getEventName());
        $this->assertStringContainsString('Создано бронирование', $event->getDescription());
        $this->assertEquals(1, $event->userId);
    }

    public function test_reservation_created_to_array(): void
    {
        $event = new ReservationCreated($this->reservation);
        $array = $event->toArray();

        $this->assertEquals('reservation.created', $array['event']);
        $this->assertEquals($this->reservation->id, $array['reservation_id']);
        $this->assertEquals($this->restaurant->id, $array['restaurant_id']);
        $this->assertArrayHasKey('timestamp', $array);
    }

    // ==================== ReservationConfirmed ====================

    public function test_reservation_confirmed_event(): void
    {
        $event = new ReservationConfirmed($this->reservation, userId: 2);

        $this->assertEquals('reservation.confirmed', $event->getEventName());
        $this->assertStringContainsString('подтверждено', $event->getDescription());
    }

    // ==================== ReservationSeated ====================

    public function test_reservation_seated_event(): void
    {
        $order = Order::factory()->create([
            'reservation_id' => $this->reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);
        $tables = new Collection([$this->table]);

        $event = new ReservationSeated(
            $this->reservation,
            order: $order,
            tables: $tables,
            depositTransferred: true,
            userId: 1
        );

        $this->assertEquals('reservation.seated', $event->getEventName());
        $this->assertStringContainsString('Гости посажены', $event->getDescription());
        $this->assertTrue($event->depositTransferred);
        $this->assertSame($order, $event->order);
    }

    public function test_reservation_seated_to_array_includes_extra_data(): void
    {
        $order = Order::factory()->create([
            'reservation_id' => $this->reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);
        $tables = new Collection([$this->table]);

        $event = new ReservationSeated(
            $this->reservation,
            order: $order,
            tables: $tables,
            depositTransferred: true
        );

        $array = $event->toArray();

        $this->assertEquals($order->id, $array['order_id']);
        $this->assertContains($this->table->id, $array['table_ids']);
        $this->assertTrue($array['deposit_transferred']);
    }

    // ==================== ReservationCompleted ====================

    public function test_reservation_completed_event(): void
    {
        $event = new ReservationCompleted($this->reservation);

        $this->assertEquals('reservation.completed', $event->getEventName());
        $this->assertStringContainsString('завершено', $event->getDescription());
    }

    // ==================== ReservationCancelled ====================

    public function test_reservation_cancelled_event(): void
    {
        $event = new ReservationCancelled(
            $this->reservation,
            reason: 'Customer request',
            depositRefunded: true,
            userId: 1
        );

        $this->assertEquals('reservation.cancelled', $event->getEventName());
        $this->assertStringContainsString('отменено', $event->getDescription());
        $this->assertStringContainsString('Customer request', $event->getDescription());
        $this->assertTrue($event->depositRefunded);
    }

    public function test_reservation_cancelled_to_array(): void
    {
        $event = new ReservationCancelled(
            $this->reservation,
            reason: 'No longer needed',
            depositRefunded: true
        );

        $array = $event->toArray();

        $this->assertEquals('No longer needed', $array['reason']);
        $this->assertTrue($array['deposit_refunded']);
    }

    // ==================== ReservationNoShow ====================

    public function test_reservation_no_show_event(): void
    {
        $event = new ReservationNoShow(
            $this->reservation,
            depositForfeited: true,
            userId: 1
        );

        $this->assertEquals('reservation.no_show', $event->getEventName());
        $this->assertStringContainsString('Неявка', $event->getDescription());
        $this->assertTrue($event->depositForfeited);
    }

    // ==================== DepositPaid ====================

    public function test_deposit_paid_event(): void
    {
        $event = new DepositPaid(
            $this->reservation,
            amount: 500.00,
            paymentMethod: 'card',
            transactionId: 'txn_123',
            userId: 1
        );

        $this->assertEquals('deposit.paid', $event->getEventName());
        $this->assertStringContainsString('500', $event->getDescription());
        $this->assertEquals(500.00, $event->amount);
        $this->assertEquals('card', $event->paymentMethod);
    }

    public function test_deposit_paid_to_array(): void
    {
        $event = new DepositPaid(
            $this->reservation,
            amount: 500.00,
            paymentMethod: 'card',
            transactionId: 'txn_abc'
        );

        $array = $event->toArray();

        $this->assertEquals(500.00, $array['amount']);
        $this->assertEquals('card', $array['payment_method']);
        $this->assertEquals('txn_abc', $array['transaction_id']);
    }

    // ==================== DepositRefunded ====================

    public function test_deposit_refunded_event(): void
    {
        $event = new DepositRefunded(
            $this->reservation,
            amount: 500.00,
            reason: 'Cancellation',
            userId: 1
        );

        $this->assertEquals('deposit.refunded', $event->getEventName());
        $this->assertStringContainsString('возвращён', $event->getDescription());
        $this->assertEquals('Cancellation', $event->reason);
    }

    // ==================== DepositTransferred ====================

    public function test_deposit_transferred_event(): void
    {
        $order = Order::factory()->create([
            'reservation_id' => $this->reservation->id,
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $event = new DepositTransferred(
            $this->reservation,
            order: $order,
            amount: 500.00,
            userId: 1
        );

        $this->assertEquals('deposit.transferred', $event->getEventName());
        $this->assertStringContainsString('перенесён', $event->getDescription());
        $this->assertStringContainsString((string) $order->id, $event->getDescription());
    }

    // ==================== DepositForfeited ====================

    public function test_deposit_forfeited_event(): void
    {
        $event = new DepositForfeited(
            $this->reservation,
            amount: 500.00,
            reason: 'No-show after 30 min',
            userId: 1
        );

        $this->assertEquals('deposit.forfeited', $event->getEventName());
        $this->assertStringContainsString('конфискован', $event->getDescription());
        $this->assertEquals('No-show after 30 min', $event->reason);
    }

    // ==================== Event Dispatching ====================

    public function test_events_can_be_dispatched(): void
    {
        Event::fake();

        ReservationCreated::dispatch($this->reservation, 1);
        ReservationConfirmed::dispatch($this->reservation, 1);
        ReservationCompleted::dispatch($this->reservation, 1);

        Event::assertDispatched(ReservationCreated::class);
        Event::assertDispatched(ReservationConfirmed::class);
        Event::assertDispatched(ReservationCompleted::class);
    }

    public function test_events_contain_correct_reservation(): void
    {
        Event::fake();

        ReservationCreated::dispatch($this->reservation, 1);

        Event::assertDispatched(ReservationCreated::class, function ($event) {
            return $event->reservation->id === $this->reservation->id
                && $event->userId === 1;
        });
    }
}
