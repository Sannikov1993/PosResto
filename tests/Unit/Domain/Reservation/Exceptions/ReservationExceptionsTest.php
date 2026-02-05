<?php

namespace Tests\Unit\Domain\Reservation\Exceptions;

use App\Domain\Reservation\Exceptions\DepositException;
use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\Exceptions\ReservationConflictException;
use App\Domain\Reservation\Exceptions\ReservationNotFoundException;
use App\Domain\Reservation\Exceptions\ReservationValidationException;
use App\Domain\Reservation\Exceptions\TableOccupiedException;
use App\Models\Reservation;
use App\ValueObjects\TimeSlot;
use PHPUnit\Framework\TestCase;

class ReservationExceptionsTest extends TestCase
{
    // ============ ReservationConflictException Tests ============

    public function test_conflict_exception_with_message(): void
    {
        $exception = ReservationConflictException::withMessage('Конфликт времени');

        $this->assertEquals('reservation_conflict', $exception->getErrorCode());
        $this->assertEquals(409, $exception->getHttpStatus());
        $this->assertEquals('Конфликт времени', $exception->getMessage());
    }

    public function test_conflict_exception_for_tables(): void
    {
        $timeSlot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $conflicts = collect();

        $exception = ReservationConflictException::forTables([1, 2], $timeSlot, $conflicts);

        $this->assertEquals('reservation_conflict', $exception->getErrorCode());
        $this->assertEquals(409, $exception->getHttpStatus());
        $this->assertStringContainsString('заняты', $exception->getMessage());
        $this->assertEquals([1, 2], $exception->getTableIds());
        $this->assertSame($timeSlot, $exception->getRequestedSlot());
    }

    public function test_conflict_exception_to_array(): void
    {
        $timeSlot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $exception = ReservationConflictException::forTables([1], $timeSlot, collect());

        $array = $exception->toArray();

        $this->assertArrayHasKey('error', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('table_ids', $array);
        $this->assertArrayHasKey('requested_time', $array);
        $this->assertEquals('reservation_conflict', $array['error']);
    }

    // ============ InvalidStateTransitionException Tests ============

    public function test_invalid_state_create(): void
    {
        $exception = InvalidStateTransitionException::create(
            'pending',
            'seated',
            ['confirmed', 'cancelled'],
            123
        );

        $this->assertEquals('invalid_state_transition', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals('pending', $exception->currentState);
        $this->assertEquals('seated', $exception->targetState);
        $this->assertEquals(['confirmed', 'cancelled'], $exception->allowedTransitions);
        $this->assertEquals(123, $exception->reservationId);
    }

    public function test_invalid_state_already_in_state(): void
    {
        $reservation = new Reservation(['id' => 1, 'status' => 'confirmed']);

        $exception = InvalidStateTransitionException::alreadyInState($reservation, 'confirmed');

        $this->assertStringContainsString('уже имеет статус', $exception->getMessage());
        $this->assertStringContainsString('подтверждено', $exception->getMessage());
    }

    public function test_invalid_state_to_array(): void
    {
        $exception = InvalidStateTransitionException::create('pending', 'seated', ['confirmed']);

        $array = $exception->toArray();

        $this->assertEquals('invalid_state_transition', $array['error']);
        $this->assertEquals('pending', $array['current_state']);
        $this->assertEquals('seated', $array['target_state']);
        $this->assertEquals(['confirmed'], $array['allowed_transitions']);
    }

    // ============ DepositException Tests ============

    public function test_deposit_already_paid(): void
    {
        $reservation = new Reservation([
            'id' => 1,
            'deposit' => 1000,
            'deposit_status' => 'paid',
        ]);

        $exception = DepositException::alreadyPaid($reservation);

        $this->assertEquals('deposit_error', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals(1000.0, $exception->amount);
        $this->assertEquals('paid', $exception->depositStatus);
        $this->assertStringContainsString('уже оплачен', $exception->getMessage());
    }

    public function test_deposit_invalid_amount(): void
    {
        $exception = DepositException::invalidAmount(500, 1000);

        $this->assertStringContainsString('500', $exception->getMessage());
        $this->assertStringContainsString('1000', $exception->getMessage());
        $this->assertEquals(500.0, $exception->amount);
    }

    public function test_deposit_invalid_payment_method(): void
    {
        $exception = DepositException::invalidPaymentMethod('bitcoin');

        $this->assertStringContainsString('bitcoin', $exception->getMessage());
        $this->assertStringContainsString('cash', $exception->getMessage());
    }

    // ============ TableOccupiedException Tests ============

    public function test_table_occupied_single(): void
    {
        $exception = TableOccupiedException::table(5);

        $this->assertEquals('tables_occupied', $exception->getErrorCode());
        $this->assertEquals(409, $exception->getHttpStatus());
        $this->assertEquals(['5'], $exception->tableNumbers);
        $this->assertStringContainsString('5', $exception->getMessage());
        $this->assertStringContainsString('занят', $exception->getMessage());
    }

    public function test_table_occupied_multiple(): void
    {
        $tables = collect([
            (object) ['id' => 1, 'name' => 'A1', 'number' => 1, 'current_order_id' => 10],
            (object) ['id' => 2, 'name' => 'A2', 'number' => 2, 'current_order_id' => 11],
        ]);

        $exception = TableOccupiedException::tables($tables);

        $this->assertEquals([1, 2], $exception->tableIds);
        $this->assertEquals(['A1', 'A2'], $exception->tableNumbers);
        $this->assertEquals([10, 11], $exception->orderIds);
        $this->assertStringContainsString('заняты', $exception->getMessage());
    }

    public function test_table_has_table_check(): void
    {
        $exception = TableOccupiedException::tables(collect([
            (object) ['id' => 1, 'name' => 'A1'],
            (object) ['id' => 3, 'name' => 'A3'],
        ]));

        $this->assertTrue($exception->hasTable(1));
        $this->assertTrue($exception->hasTable(3));
        $this->assertFalse($exception->hasTable(2));
    }

    // ============ ReservationValidationException Tests ============

    public function test_validation_capacity_exceeded(): void
    {
        $exception = ReservationValidationException::capacityExceeded(10, 4, ['A1', 'A2']);

        $this->assertEquals('validation_error', $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals('guests_count', $exception->field);
        $this->assertEquals('max_capacity', $exception->rule);
        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertStringContainsString('4', $exception->getMessage());
    }

    public function test_validation_time_in_past(): void
    {
        $exception = ReservationValidationException::timeInPast('2026-02-05', '10:00');

        $this->assertEquals('time_from', $exception->field);
        $this->assertEquals('future_time', $exception->rule);
        $this->assertStringContainsString('прошло', $exception->getMessage());
    }

    public function test_validation_duration_too_short(): void
    {
        $exception = ReservationValidationException::durationTooShort(15, 30);

        $this->assertEquals('time_to', $exception->field);
        $this->assertStringContainsString('30', $exception->getMessage());
        $this->assertStringContainsString('15', $exception->getMessage());
    }

    public function test_validation_incomplete_phone(): void
    {
        $exception = ReservationValidationException::incompletePhone('+7 (999) 12', 11);

        $this->assertEquals('guest_phone', $exception->field);
        $this->assertStringContainsString('неполный', $exception->getMessage());
    }

    // ============ ReservationNotFoundException Tests ============

    public function test_not_found_with_id(): void
    {
        $exception = ReservationNotFoundException::withId(999);

        $this->assertEquals('reservation_not_found', $exception->getErrorCode());
        $this->assertEquals(404, $exception->getHttpStatus());
        $this->assertEquals(999, $exception->reservationId);
        $this->assertStringContainsString('999', $exception->getMessage());
        $this->assertStringContainsString('не найдено', $exception->getMessage());
    }

    public function test_not_found_for_restaurant(): void
    {
        $exception = ReservationNotFoundException::forRestaurant(123, 5);

        $this->assertEquals(123, $exception->reservationId);
        $this->assertArrayHasKey('restaurant_id', $exception->getContext());
        $this->assertEquals(5, $exception->getContext()['restaurant_id']);
    }
}
