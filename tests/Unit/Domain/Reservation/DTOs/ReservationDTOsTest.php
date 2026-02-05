<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation\DTOs;

use App\Domain\Reservation\DTOs\CancelReservationData;
use App\Domain\Reservation\DTOs\CreateReservationData;
use App\Domain\Reservation\DTOs\DepositPaymentData;
use App\Domain\Reservation\DTOs\SeatGuestsData;
use App\Domain\Reservation\DTOs\UpdateReservationData;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReservationDTOsTest extends TestCase
{
    use RefreshDatabase;

    // ==================== CreateReservationData ====================

    public function test_create_reservation_data_from_array(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 4,
            'customer_name' => 'John Doe',
            'customer_phone' => '+7999123456',
            'deposit' => 500.00,
        ]);

        $this->assertEquals(1, $data->restaurantId);
        $this->assertEquals(5, $data->tableId);
        $this->assertEquals('2026-02-10', $data->date);
        $this->assertEquals('18:00', $data->timeFrom);
        $this->assertEquals('20:00', $data->timeTo);
        $this->assertEquals(4, $data->guestsCount);
        $this->assertEquals('John Doe', $data->customerName);
        $this->assertEquals(500.00, $data->deposit);
    }

    public function test_create_reservation_data_get_date_returns_carbon(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $this->assertInstanceOf(Carbon::class, $data->getDate());
        $this->assertEquals('2026-02-10', $data->getDate()->toDateString());
    }

    public function test_create_reservation_data_detects_overnight(): void
    {
        $overnight = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '22:00',
            'time_to' => '02:00',
            'guests_count' => 2,
        ]);

        $normal = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $this->assertTrue($overnight->isOvernight());
        $this->assertFalse($normal->isOvernight());
    }

    public function test_create_reservation_data_calculates_duration(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $this->assertEquals(120, $data->getDurationMinutes());
    }

    public function test_create_reservation_data_has_deposit(): void
    {
        $withDeposit = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
            'deposit' => 500.00,
        ]);

        $withoutDeposit = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $this->assertTrue($withDeposit->hasDeposit());
        $this->assertFalse($withoutDeposit->hasDeposit());
    }

    public function test_create_reservation_data_gets_all_table_ids(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 8,
            'linked_table_ids' => [6, 7],
        ]);

        $this->assertEquals([5, 6, 7], $data->getAllTableIds());
    }

    public function test_create_reservation_data_to_model_attributes(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 4,
            'deposit' => 500.00,
        ]);

        $attributes = $data->toModelAttributes();

        $this->assertEquals(1, $attributes['restaurant_id']);
        $this->assertEquals(5, $attributes['table_id']);
        $this->assertEquals(500.00, $attributes['deposit']);
        $this->assertEquals('pending', $attributes['deposit_status']);
        $this->assertEquals('pending', $attributes['status']);
    }

    public function test_create_reservation_data_has_validation_rules(): void
    {
        $rules = CreateReservationData::rules();

        $this->assertArrayHasKey('restaurant_id', $rules);
        $this->assertArrayHasKey('table_id', $rules);
        $this->assertArrayHasKey('date', $rules);
        $this->assertArrayHasKey('guests_count', $rules);
    }

    // ==================== UpdateReservationData ====================

    public function test_update_reservation_data_from_array(): void
    {
        $data = UpdateReservationData::fromArray([
            'date' => '2026-02-15',
            'guests_count' => 6,
        ]);

        $this->assertEquals('2026-02-15', $data->date);
        $this->assertEquals(6, $data->guestsCount);
        $this->assertNull($data->tableId);
        $this->assertNull($data->timeFrom);
    }

    public function test_update_reservation_data_has_changes(): void
    {
        $withChanges = UpdateReservationData::fromArray([
            'guests_count' => 6,
        ]);

        $empty = UpdateReservationData::fromArray([]);

        $this->assertTrue($withChanges->hasChanges());
        $this->assertFalse($empty->hasChanges());
    }

    public function test_update_reservation_data_is_changing_time_slot(): void
    {
        $changingDate = UpdateReservationData::fromArray(['date' => '2026-02-15']);
        $changingTime = UpdateReservationData::fromArray(['time_from' => '19:00']);
        $changingGuests = UpdateReservationData::fromArray(['guests_count' => 6]);

        $this->assertTrue($changingDate->isChangingTimeSlot());
        $this->assertTrue($changingTime->isChangingTimeSlot());
        $this->assertFalse($changingGuests->isChangingTimeSlot());
    }

    public function test_update_reservation_data_merge_with_reservation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = Table::factory()->create(['restaurant_id' => $restaurant->id]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 4,
        ]);

        $data = UpdateReservationData::fromArray([
            'guests_count' => 6,
        ]);

        $merged = $data->mergeWithReservation($reservation);

        $this->assertEquals($table->id, $merged['table_id']);
        $this->assertEquals('2026-02-10', $merged['date']);
        $this->assertEquals('18:00', $merged['time_from']);
        $this->assertEquals(6, $merged['guests_count']); // Changed
    }

    public function test_update_reservation_data_to_model_attributes_only_changed(): void
    {
        $data = UpdateReservationData::fromArray([
            'guests_count' => 6,
            'notes' => 'VIP',
        ]);

        $attributes = $data->toModelAttributes();

        $this->assertArrayHasKey('guests_count', $attributes);
        $this->assertArrayHasKey('notes', $attributes);
        $this->assertArrayNotHasKey('table_id', $attributes);
        $this->assertArrayNotHasKey('date', $attributes);
    }

    // ==================== SeatGuestsData ====================

    public function test_seat_guests_data_defaults(): void
    {
        $data = SeatGuestsData::default(userId: 1);

        $this->assertTrue($data->createOrder);
        $this->assertTrue($data->transferDeposit);
        $this->assertEquals(1, $data->userId);
    }

    public function test_seat_guests_data_without_order(): void
    {
        $data = SeatGuestsData::withoutOrder(userId: 1);

        $this->assertFalse($data->createOrder);
        $this->assertFalse($data->transferDeposit);
    }

    public function test_seat_guests_data_from_array(): void
    {
        $data = SeatGuestsData::fromArray([
            'create_order' => false,
            'transfer_deposit' => true,
            'guests_count' => 5,
        ]);

        $this->assertFalse($data->createOrder);
        $this->assertTrue($data->transferDeposit);
        $this->assertEquals(5, $data->guestsCount);
    }

    // ==================== CancelReservationData ====================

    public function test_cancel_reservation_data_customer_request(): void
    {
        $data = CancelReservationData::customerRequest(userId: 1);

        $this->assertEquals('По просьбе гостя', $data->reason);
        $this->assertTrue($data->refundDeposit);
    }

    public function test_cancel_reservation_data_by_restaurant(): void
    {
        $data = CancelReservationData::byRestaurant('Овербукинг', userId: 1);

        $this->assertEquals('Овербукинг', $data->reason);
        $this->assertFalse($data->refundDeposit);
    }

    public function test_cancel_reservation_data_has_common_reasons(): void
    {
        $reasons = CancelReservationData::commonReasons();

        $this->assertArrayHasKey('customer_request', $reasons);
        $this->assertArrayHasKey('no_show', $reasons);
        $this->assertArrayHasKey('overbooking', $reasons);
    }

    // ==================== DepositPaymentData ====================

    public function test_deposit_payment_data_cash(): void
    {
        $data = DepositPaymentData::cash(userId: 1);

        $this->assertEquals(DepositPaymentData::METHOD_CASH, $data->paymentMethod);
        $this->assertEquals('Наличные', $data->getMethodLabel());
    }

    public function test_deposit_payment_data_card(): void
    {
        $data = DepositPaymentData::card('txn_123', userId: 1);

        $this->assertEquals(DepositPaymentData::METHOD_CARD, $data->paymentMethod);
        $this->assertEquals('txn_123', $data->transactionId);
        $this->assertEquals('Карта', $data->getMethodLabel());
    }

    public function test_deposit_payment_data_online(): void
    {
        $data = DepositPaymentData::online('pay_abc123', userId: 1);

        $this->assertEquals(DepositPaymentData::METHOD_ONLINE, $data->paymentMethod);
        $this->assertEquals('pay_abc123', $data->transactionId);
        $this->assertEquals('Онлайн', $data->getMethodLabel());
    }

    public function test_deposit_payment_data_from_array(): void
    {
        $data = DepositPaymentData::fromArray([
            'payment_method' => 'card',
            'transaction_id' => 'txn_456',
            'amount' => 500.00,
        ]);

        $this->assertEquals('card', $data->paymentMethod);
        $this->assertEquals('txn_456', $data->transactionId);
        $this->assertEquals(500.00, $data->amount);
    }

    public function test_deposit_payment_data_has_valid_methods(): void
    {
        $this->assertContains('cash', DepositPaymentData::METHODS);
        $this->assertContains('card', DepositPaymentData::METHODS);
        $this->assertContains('online', DepositPaymentData::METHODS);
        $this->assertContains('transfer', DepositPaymentData::METHODS);
    }

    // ==================== toArray Tests ====================

    public function test_dto_to_array_excludes_nulls(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $array = $data->toArray();

        $this->assertArrayNotHasKey('customerName', $array);
        $this->assertArrayNotHasKey('notes', $array);
        $this->assertArrayHasKey('restaurantId', $array);
    }

    public function test_dto_to_array_with_nulls(): void
    {
        $data = CreateReservationData::fromArray([
            'restaurant_id' => 1,
            'table_id' => 5,
            'date' => '2026-02-10',
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
        ]);

        $array = $data->toArrayWithNulls();

        $this->assertArrayHasKey('customerName', $array);
        $this->assertNull($array['customerName']);
    }
}
