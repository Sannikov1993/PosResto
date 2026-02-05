<?php

namespace Tests\Unit\Domain\Reservation\StateMachine;

use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use App\Models\Reservation;
use PHPUnit\Framework\TestCase;

class ReservationStateMachineTest extends TestCase
{
    private ReservationStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new ReservationStateMachine();
    }

    // ============ Status Enum Tests ============

    public function test_status_enum_labels(): void
    {
        $this->assertEquals('Ожидает подтверждения', ReservationStatus::PENDING->label());
        $this->assertEquals('Подтверждено', ReservationStatus::CONFIRMED->label());
        $this->assertEquals('Гости за столом', ReservationStatus::SEATED->label());
        $this->assertEquals('Завершено', ReservationStatus::COMPLETED->label());
        $this->assertEquals('Отменено', ReservationStatus::CANCELLED->label());
        $this->assertEquals('Неявка', ReservationStatus::NO_SHOW->label());
    }

    public function test_status_enum_is_terminal(): void
    {
        $this->assertFalse(ReservationStatus::PENDING->isTerminal());
        $this->assertFalse(ReservationStatus::CONFIRMED->isTerminal());
        $this->assertFalse(ReservationStatus::SEATED->isTerminal());
        $this->assertTrue(ReservationStatus::COMPLETED->isTerminal());
        $this->assertTrue(ReservationStatus::CANCELLED->isTerminal());
        $this->assertTrue(ReservationStatus::NO_SHOW->isTerminal());
    }

    public function test_status_enum_is_active(): void
    {
        $this->assertTrue(ReservationStatus::PENDING->isActive());
        $this->assertTrue(ReservationStatus::CONFIRMED->isActive());
        $this->assertTrue(ReservationStatus::SEATED->isActive());
        $this->assertFalse(ReservationStatus::COMPLETED->isActive());
        $this->assertFalse(ReservationStatus::CANCELLED->isActive());
        $this->assertFalse(ReservationStatus::NO_SHOW->isActive());
    }

    public function test_status_enum_is_editable(): void
    {
        $this->assertTrue(ReservationStatus::PENDING->isEditable());
        $this->assertTrue(ReservationStatus::CONFIRMED->isEditable());
        $this->assertFalse(ReservationStatus::SEATED->isEditable());
        $this->assertFalse(ReservationStatus::COMPLETED->isEditable());
    }

    // ============ Transition Tests ============

    public function test_pending_can_transition_to_confirmed(): void
    {
        $reservation = $this->makeReservation('pending');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'confirmed'));
        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, ReservationStatus::CONFIRMED));
        $this->assertTrue($this->stateMachine->canConfirm($reservation));
    }

    public function test_pending_can_transition_to_cancelled(): void
    {
        $reservation = $this->makeReservation('pending');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'cancelled'));
        $this->assertTrue($this->stateMachine->canCancel($reservation));
    }

    public function test_pending_can_transition_to_seated(): void
    {
        // Guests can arrive without confirmation and be seated directly
        $reservation = $this->makeReservation('pending');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'seated'));
        $this->assertTrue($this->stateMachine->canSeat($reservation));
    }

    public function test_confirmed_can_transition_to_seated(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'seated'));
        $this->assertTrue($this->stateMachine->canSeat($reservation));
    }

    public function test_confirmed_can_transition_to_cancelled(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->assertTrue($this->stateMachine->canCancel($reservation));
    }

    public function test_confirmed_can_transition_to_no_show(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'no_show'));
        $this->assertTrue($this->stateMachine->canMarkNoShow($reservation));
    }

    public function test_seated_can_transition_to_completed(): void
    {
        $reservation = $this->makeReservation('seated');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'completed'));
        $this->assertTrue($this->stateMachine->canComplete($reservation));
    }

    public function test_seated_can_transition_to_confirmed_unseat(): void
    {
        $reservation = $this->makeReservation('seated');

        $this->assertTrue($this->stateMachine->canTransitionTo($reservation, 'confirmed'));
        $this->assertTrue($this->stateMachine->canUnseat($reservation));
    }

    public function test_seated_cannot_cancel(): void
    {
        $reservation = $this->makeReservation('seated');

        $this->assertFalse($this->stateMachine->canCancel($reservation));
    }

    public function test_completed_cannot_transition(): void
    {
        $reservation = $this->makeReservation('completed');

        $this->assertEmpty($this->stateMachine->getAllowedTransitions($reservation));
        $this->assertTrue($this->stateMachine->isTerminal($reservation));
    }

    public function test_cancelled_cannot_transition(): void
    {
        $reservation = $this->makeReservation('cancelled');

        $this->assertEmpty($this->stateMachine->getAllowedTransitions($reservation));
        $this->assertTrue($this->stateMachine->isTerminal($reservation));
    }

    // ============ Transition Execution Tests ============

    public function test_transition_to_updates_status(): void
    {
        $reservation = $this->makeReservation('pending');

        $result = $this->stateMachine->transitionTo($reservation, ReservationStatus::CONFIRMED);

        $this->assertEquals('confirmed', $result->status);
        $this->assertSame($reservation, $result);
    }

    public function test_transition_to_with_string_status(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $result = $this->stateMachine->transitionTo($reservation, 'seated');

        $this->assertEquals('seated', $result->status);
    }

    // ============ Exception Tests ============

    public function test_assert_throws_for_invalid_transition(): void
    {
        // pending cannot transition directly to completed
        $reservation = $this->makeReservation('pending');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->assertCanTransitionTo($reservation, 'completed');
    }

    public function test_assert_throws_when_already_in_state(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('уже имеет статус');

        $this->stateMachine->assertCanTransitionTo($reservation, 'confirmed');
    }

    public function test_transition_to_throws_for_invalid(): void
    {
        $reservation = $this->makeReservation('completed');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->transitionTo($reservation, 'cancelled');
    }

    public function test_assert_can_seat_does_not_throw_for_pending(): void
    {
        // Pending reservations can now be seated (guests arrive without confirmation)
        $reservation = $this->makeReservation('pending');

        // Should not throw
        $this->stateMachine->assertCanSeat($reservation);
        $this->assertTrue($this->stateMachine->canSeat($reservation));
    }

    public function test_assert_can_seat_throws_for_cancelled(): void
    {
        $reservation = $this->makeReservation('cancelled');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->assertCanSeat($reservation);
    }

    public function test_assert_can_unseat_throws_for_confirmed(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->assertCanUnseat($reservation);
    }

    public function test_assert_can_complete_throws_for_confirmed(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->assertCanComplete($reservation);
    }

    public function test_assert_can_cancel_throws_for_seated(): void
    {
        $reservation = $this->makeReservation('seated');

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->assertCanCancel($reservation);
    }

    // ============ Get Allowed Transitions Tests ============

    public function test_get_allowed_transitions_for_pending(): void
    {
        $reservation = $this->makeReservation('pending');

        $transitions = $this->stateMachine->getAllowedTransitions($reservation);

        $this->assertCount(3, $transitions);
        $this->assertContains(ReservationStatus::CONFIRMED, $transitions);
        $this->assertContains(ReservationStatus::CANCELLED, $transitions);
        $this->assertContains(ReservationStatus::SEATED, $transitions);
    }

    public function test_get_allowed_transitions_for_confirmed(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $transitions = $this->stateMachine->getAllowedTransitions($reservation);

        $this->assertCount(3, $transitions);
        $this->assertContains(ReservationStatus::SEATED, $transitions);
        $this->assertContains(ReservationStatus::CANCELLED, $transitions);
        $this->assertContains(ReservationStatus::NO_SHOW, $transitions);
    }

    public function test_get_allowed_transitions_for_seated(): void
    {
        $reservation = $this->makeReservation('seated');

        $transitions = $this->stateMachine->getAllowedTransitions($reservation);

        $this->assertCount(2, $transitions);
        $this->assertContains(ReservationStatus::COMPLETED, $transitions);
        $this->assertContains(ReservationStatus::CONFIRMED, $transitions);
    }

    // ============ Status Query Tests ============

    public function test_is_seated(): void
    {
        $seated = $this->makeReservation('seated');
        $confirmed = $this->makeReservation('confirmed');

        $this->assertTrue($this->stateMachine->isSeated($seated));
        $this->assertFalse($this->stateMachine->isSeated($confirmed));
    }

    public function test_is_active(): void
    {
        $pending = $this->makeReservation('pending');
        $confirmed = $this->makeReservation('confirmed');
        $seated = $this->makeReservation('seated');
        $completed = $this->makeReservation('completed');

        $this->assertTrue($this->stateMachine->isActive($pending));
        $this->assertTrue($this->stateMachine->isActive($confirmed));
        $this->assertTrue($this->stateMachine->isActive($seated));
        $this->assertFalse($this->stateMachine->isActive($completed));
    }

    public function test_is_editable(): void
    {
        $pending = $this->makeReservation('pending');
        $confirmed = $this->makeReservation('confirmed');
        $seated = $this->makeReservation('seated');

        $this->assertTrue($this->stateMachine->isEditable($pending));
        $this->assertTrue($this->stateMachine->isEditable($confirmed));
        $this->assertFalse($this->stateMachine->isEditable($seated));
    }

    public function test_get_current_status(): void
    {
        $reservation = $this->makeReservation('confirmed');

        $status = $this->stateMachine->getCurrentStatus($reservation);

        $this->assertEquals(ReservationStatus::CONFIRMED, $status);
    }

    // ============ Static Methods Tests ============

    public function test_get_all_statuses(): void
    {
        $statuses = ReservationStateMachine::getAllStatuses();

        $this->assertCount(6, $statuses);
    }

    public function test_get_transitions_map(): void
    {
        $map = ReservationStateMachine::getTransitionsMap();

        $this->assertArrayHasKey('pending', $map);
        $this->assertArrayHasKey('confirmed', $map);
        $this->assertArrayHasKey('seated', $map);
        $this->assertArrayHasKey('completed', $map);
        $this->assertArrayHasKey('cancelled', $map);
        $this->assertArrayHasKey('no_show', $map);
    }

    public function test_visualize(): void
    {
        $diagram = ReservationStateMachine::visualize();

        $this->assertStringContainsString('PENDING', $diagram);
        $this->assertStringContainsString('CONFIRMED', $diagram);
        $this->assertStringContainsString('SEATED', $diagram);
        $this->assertStringContainsString('COMPLETED', $diagram);
    }

    // ============ Helpers ============

    private function makeReservation(string $status): Reservation
    {
        $reservation = new Reservation();
        $reservation->id = 1;
        $reservation->status = $status;

        return $reservation;
    }
}
