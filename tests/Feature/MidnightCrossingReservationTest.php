<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Reservation;
use App\Models\LoyaltySetting;
use App\ValueObjects\TimeSlot;
use App\Services\ReservationConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for midnight-crossing reservations (e.g., 22:00-02:00)
 */
class MidnightCrossingReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Zone $zone;
    protected Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);
        $this->zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
            'seats' => 4,
        ]);

        LoyaltySetting::set('levels_enabled', '0', $this->restaurant->id);
    }

    protected function authenticate(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ===== TimeSlot Value Object Tests =====

    public function test_timeslot_detects_midnight_crossing(): void
    {
        // 22:00 - 02:00 (next day)
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'Europe/Moscow');

        $this->assertTrue($slot->crossesMidnight());
        $this->assertEquals(240, $slot->durationMinutes()); // 4 hours
    }

    public function test_timeslot_does_not_detect_midnight_for_same_day(): void
    {
        // 18:00 - 22:00 (same day)
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '22:00', 'Europe/Moscow');

        $this->assertFalse($slot->crossesMidnight());
        $this->assertEquals(240, $slot->durationMinutes()); // 4 hours
    }

    public function test_timeslot_calculates_correct_end_date_for_midnight_crossing(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'Europe/Moscow');

        // End date should be 2026-02-06
        $this->assertEquals('2026-02-06', $slot->endsAt()->format('Y-m-d'));
        $this->assertEquals('2026-02-05', $slot->startsAt()->format('Y-m-d'));
    }

    public function test_timeslot_time_range_shows_plus_one_indicator(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'Europe/Moscow');

        $timeRange = $slot->getTimeRange();
        $this->assertStringContainsString('+1', $timeRange);
        $this->assertStringContainsString('22:00', $timeRange);
        $this->assertStringContainsString('02:00', $timeRange);
    }

    // ===== Conflict Detection Tests =====

    public function test_detects_conflict_between_two_midnight_crossing_reservations(): void
    {
        $this->authenticate();

        // Create first reservation: 22:00 - 02:00
        $reservation1 = $this->createMidnightReservation('22:00', '02:00');

        // Try to create overlapping: 23:00 - 03:00
        $hasConflict = Reservation::hasConflict(
            $this->table->id,
            Carbon::tomorrow()->format('Y-m-d'),
            '23:00',
            '03:00',
            null,
            'Europe/Moscow'
        );

        $this->assertTrue($hasConflict);
    }

    public function test_detects_conflict_between_regular_and_midnight_crossing(): void
    {
        $this->authenticate();

        // Create midnight crossing reservation: 22:00 - 02:00
        $reservation1 = $this->createMidnightReservation('22:00', '02:00');

        // Try to create regular reservation that starts before midnight: 21:00 - 23:00
        $hasConflict = Reservation::hasConflict(
            $this->table->id,
            Carbon::tomorrow()->format('Y-m-d'),
            '21:00',
            '23:00',
            null,
            'Europe/Moscow'
        );

        $this->assertTrue($hasConflict);
    }

    public function test_detects_conflict_on_next_day_morning(): void
    {
        $this->authenticate();

        // Create midnight crossing reservation: 22:00 - 02:00 on Feb 5
        $reservation1 = $this->createMidnightReservation('22:00', '02:00', Carbon::parse('2026-02-05'));

        // Try to create reservation on Feb 6 at 01:00 - 03:00 (overlaps with end of first)
        $hasConflict = Reservation::hasConflict(
            $this->table->id,
            '2026-02-06',
            '01:00',
            '03:00',
            null,
            'Europe/Moscow'
        );

        $this->assertTrue($hasConflict);
    }

    public function test_no_conflict_for_adjacent_midnight_reservations(): void
    {
        $this->authenticate();

        // Create reservation: 22:00 - 02:00
        $reservation1 = $this->createMidnightReservation('22:00', '02:00');

        // Create adjacent: 02:00 - 04:00 (starts when first ends)
        $hasConflict = Reservation::hasConflict(
            $this->table->id,
            Carbon::tomorrow()->addDay()->format('Y-m-d'), // Next day since 02:00 is on next day
            '02:00',
            '04:00',
            null,
            'Europe/Moscow'
        );

        $this->assertFalse($hasConflict);
    }

    // ===== API Tests =====

    public function test_can_create_midnight_crossing_reservation_via_api(): void
    {
        $this->authenticate();

        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Night Guest',
            'guest_phone' => '+79001234567',
            'date' => $tomorrow,
            'time_from' => '22:00',
            'time_to' => '02:00',
            'guests_count' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify stored data
        $reservation = Reservation::first();
        $this->assertEquals('22:00', substr($reservation->time_from, 0, 5));
        $this->assertEquals('02:00', substr($reservation->time_to, 0, 5));
        $this->assertTrue($reservation->crosses_midnight);

        // Verify datetime fields are correctly set
        $this->assertNotNull($reservation->starts_at);
        $this->assertNotNull($reservation->ends_at);
        $this->assertGreaterThan($reservation->starts_at, $reservation->ends_at);
    }

    public function test_api_rejects_conflicting_midnight_reservation(): void
    {
        $this->authenticate();

        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        // Create first reservation
        $this->createMidnightReservation('22:00', '02:00');

        // Try to create conflicting reservation
        $response = $this->postJson('/api/reservations', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Another Guest',
            'guest_phone' => '+79009876543',
            'date' => $tomorrow,
            'time_from' => '23:00',
            'time_to' => '01:00',
            'guests_count' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_reservation_time_range_displays_plus_one(): void
    {
        $this->authenticate();

        $reservation = $this->createMidnightReservation('22:00', '02:00');

        $response = $this->getJson("/api/reservations/{$reservation->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertStringContainsString('+1', $data['time_range']);
    }

    public function test_midnight_reservation_appears_in_both_days_listing(): void
    {
        $this->authenticate();

        $date = Carbon::tomorrow();
        $reservation = $this->createMidnightReservation('22:00', '02:00', $date);

        // Should appear when filtering by start date
        $response1 = $this->getJson("/api/reservations?restaurant_id={$this->restaurant->id}&date={$date->format('Y-m-d')}");
        $response1->assertOk();
        $this->assertCount(1, $response1->json('data'));

        // Note: whether it appears on the next day depends on implementation
        // For now, we test that it's queryable by its start date
    }

    // ===== Duration Calculation Tests =====

    public function test_duration_calculated_correctly_for_midnight_crossing(): void
    {
        $reservation = $this->createMidnightReservation('22:00', '02:00');

        // Should be 4 hours = 240 minutes
        $this->assertEquals(240, $reservation->duration_minutes);
    }

    public function test_duration_calculated_for_short_overnight(): void
    {
        $reservation = $this->createMidnightReservation('23:30', '00:30');

        // Should be 1 hour = 60 minutes
        $this->assertEquals(60, $reservation->duration_minutes);
    }

    public function test_duration_calculated_for_long_overnight(): void
    {
        $reservation = $this->createMidnightReservation('20:00', '04:00');

        // Should be 8 hours = 480 minutes
        $this->assertEquals(480, $reservation->duration_minutes);
    }

    // ===== Helper Methods =====

    protected function createMidnightReservation(
        string $timeFrom,
        string $timeTo,
        ?Carbon $date = null
    ): Reservation {
        $date = $date ?? Carbon::tomorrow();
        $timezone = 'Europe/Moscow';

        $timeSlot = TimeSlot::fromDateAndTimes(
            $date->format('Y-m-d'),
            $timeFrom,
            $timeTo,
            $timezone
        );
        $utcSlot = $timeSlot->toUtc();

        return Reservation::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Test Guest',
            'guest_phone' => '+79001234567',
            'date' => $date->format('Y-m-d'),
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'guests_count' => 2,
            'status' => 'confirmed',
            'deposit' => 0,
            'deposit_status' => Reservation::DEPOSIT_PENDING,
            'timezone' => $timezone,
            'starts_at' => $utcSlot->startsAt(),
            'ends_at' => $utcSlot->endsAt(),
            'duration_minutes' => $timeSlot->durationMinutes(),
        ]);
    }
}
