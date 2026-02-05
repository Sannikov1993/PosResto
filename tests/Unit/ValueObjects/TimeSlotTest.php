<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\TimeSlot;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeSlotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set a fixed "now" for consistent testing
        Carbon::setTestNow(Carbon::parse('2026-02-05 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============ Factory Method Tests ============

    public function test_from_date_and_times_creates_valid_slot(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');

        $this->assertEquals('2026-02-05', $slot->getDate());
        $this->assertEquals('19:00', $slot->getTimeFrom());
        $this->assertEquals('21:00', $slot->getTimeTo());
        $this->assertEquals(120, $slot->durationMinutes());
    }

    public function test_from_date_and_times_detects_midnight_crossing(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');

        $this->assertTrue($slot->crossesMidnight());
        $this->assertEquals('2026-02-05', $slot->getDate());
        $this->assertEquals('2026-02-06', $slot->getEndDate());
        $this->assertEquals('22:00', $slot->getTimeFrom());
        $this->assertEquals('02:00', $slot->getTimeTo());
        $this->assertEquals(240, $slot->durationMinutes()); // 4 hours
    }

    public function test_from_date_and_times_handles_same_day(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '10:00', '12:00', 'UTC');

        $this->assertFalse($slot->crossesMidnight());
        $this->assertEquals('2026-02-05', $slot->getDate());
        $this->assertEquals('2026-02-05', $slot->getEndDate());
    }

    public function test_from_datetimes_creates_valid_slot(): void
    {
        $start = Carbon::parse('2026-02-05 19:00:00', 'UTC');
        $end = Carbon::parse('2026-02-05 21:00:00', 'UTC');

        $slot = TimeSlot::fromDatetimes($start, $end);

        $this->assertEquals('19:00', $slot->getTimeFrom());
        $this->assertEquals('21:00', $slot->getTimeTo());
        $this->assertEquals(120, $slot->durationMinutes());
    }

    public function test_from_datetimes_throws_when_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $start = Carbon::parse('2026-02-05 21:00:00', 'UTC');
        $end = Carbon::parse('2026-02-05 19:00:00', 'UTC');

        TimeSlot::fromDatetimes($start, $end);
    }

    public function test_from_start_and_duration(): void
    {
        $start = Carbon::parse('2026-02-05 19:00:00', 'UTC');
        $slot = TimeSlot::fromStartAndDuration($start, 120, 'UTC');

        $this->assertEquals('19:00', $slot->getTimeFrom());
        $this->assertEquals('21:00', $slot->getTimeTo());
        $this->assertEquals(120, $slot->durationMinutes());
    }

    // ============ Overlap Detection Tests ============

    public function test_overlaps_detects_overlapping_slots(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');
        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');

        $this->assertTrue($slot1->overlaps($slot2));
        $this->assertTrue($slot2->overlaps($slot1));
    }

    public function test_overlaps_returns_false_for_adjacent_slots(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');
        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '20:00', '22:00', 'UTC');

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function test_overlaps_returns_false_for_non_overlapping_slots(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '10:00', '12:00', 'UTC');
        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function test_overlaps_works_with_midnight_crossing(): void
    {
        // Overnight slot: 22:00 - 02:00 (next day)
        $overnightSlot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');

        // Same day evening slot that overlaps
        $eveningSlot = TimeSlot::fromDateAndTimes('2026-02-05', '21:00', '23:00', 'UTC');
        $this->assertTrue($overnightSlot->overlaps($eveningSlot));

        // Next day morning slot that overlaps
        $morningSlot = TimeSlot::fromDateAndTimes('2026-02-06', '01:00', '03:00', 'UTC');
        $this->assertTrue($overnightSlot->overlaps($morningSlot));

        // Non-overlapping afternoon slot
        $afternoonSlot = TimeSlot::fromDateAndTimes('2026-02-05', '14:00', '16:00', 'UTC');
        $this->assertFalse($overnightSlot->overlaps($afternoonSlot));
    }

    public function test_overlaps_detects_contained_slot(): void
    {
        $outer = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '22:00', 'UTC');
        $inner = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');

        $this->assertTrue($outer->overlaps($inner));
        $this->assertTrue($inner->overlaps($outer));
    }

    // ============ Contains Tests ============

    public function test_contains_datetime(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '22:00', 'UTC');

        $this->assertTrue($slot->contains(Carbon::parse('2026-02-05 19:00:00', 'UTC')));
        $this->assertTrue($slot->contains(Carbon::parse('2026-02-05 18:00:00', 'UTC'))); // Start is included
        $this->assertFalse($slot->contains(Carbon::parse('2026-02-05 22:00:00', 'UTC'))); // End is excluded
        $this->assertFalse($slot->contains(Carbon::parse('2026-02-05 12:00:00', 'UTC')));
    }

    public function test_contains_works_with_midnight_crossing(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');

        $this->assertTrue($slot->contains('2026-02-05 23:00:00'));
        $this->assertTrue($slot->contains('2026-02-06 01:00:00'));
        $this->assertFalse($slot->contains('2026-02-06 03:00:00'));
    }

    // ============ Midnight Crossing Tests ============

    public function test_crosses_midnight_detection(): void
    {
        $normalSlot = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '22:00', 'UTC');
        $this->assertFalse($normalSlot->crossesMidnight());

        $overnightSlot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');
        $this->assertTrue($overnightSlot->crossesMidnight());

        $endAtMidnight = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '00:00', 'UTC');
        $this->assertTrue($endAtMidnight->crossesMidnight());
    }

    // ============ Duration Tests ============

    public function test_duration_minutes(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:30', 'UTC');
        $this->assertEquals(150, $slot->durationMinutes());
    }

    public function test_duration_minutes_for_overnight(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');
        $this->assertEquals(240, $slot->durationMinutes()); // 4 hours
    }

    public function test_duration_for_humans(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');
        $this->assertEquals('2ч', $slot1->durationForHumans());

        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '18:45', 'UTC');
        $this->assertEquals('45м', $slot2->durationForHumans());

        $slot3 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:30', 'UTC');
        $this->assertEquals('2ч 30м', $slot3->durationForHumans());
    }

    // ============ Timezone Conversion Tests ============

    public function test_to_utc_converts_correctly(): void
    {
        // Create a slot in Moscow time (UTC+3)
        $moscowSlot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '23:00', 'Europe/Moscow');
        $utcSlot = $moscowSlot->toUtc();

        // Moscow 22:00 = UTC 19:00
        $this->assertEquals('19:00', $utcSlot->getTimeFrom());
        $this->assertEquals('20:00', $utcSlot->getTimeTo());
        $this->assertEquals('UTC', $utcSlot->timezone());
    }

    public function test_to_timezone_converts_correctly(): void
    {
        $utcSlot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $moscowSlot = $utcSlot->toTimezone('Europe/Moscow');

        // UTC 19:00 = Moscow 22:00
        $this->assertEquals('22:00', $moscowSlot->getTimeFrom());
        $this->assertEquals('00:00', $moscowSlot->getTimeTo());
        $this->assertEquals('Europe/Moscow', $moscowSlot->timezone());
    }

    // ============ Validation Tests ============

    public function test_is_valid_duration(): void
    {
        $validSlot = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');
        $this->assertTrue($validSlot->isValidDuration());

        $tooShort = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '18:15', 'UTC');
        $this->assertFalse($tooShort->isValidDuration()); // Less than 30 min default

        $tooLong = TimeSlot::fromDateAndTimes('2026-02-05', '08:00', '22:00', 'UTC');
        $this->assertFalse($tooLong->isValidDuration()); // 14 hours > 12 hours max
    }

    public function test_is_past(): void
    {
        // Current test time is 2026-02-05 12:00:00 UTC
        $pastSlot = TimeSlot::fromDateAndTimes('2026-02-05', '09:00', '11:00', 'UTC');
        $this->assertTrue($pastSlot->isPast());

        $futureSlot = TimeSlot::fromDateAndTimes('2026-02-05', '13:00', '15:00', 'UTC');
        $this->assertFalse($futureSlot->isPast());

        $currentSlot = TimeSlot::fromDateAndTimes('2026-02-05', '11:00', '13:00', 'UTC');
        $this->assertFalse($currentSlot->isPast()); // Not fully past
    }

    public function test_starts_in_past(): void
    {
        // Current test time is 2026-02-05 12:00:00 UTC
        $startedSlot = TimeSlot::fromDateAndTimes('2026-02-05', '11:00', '14:00', 'UTC');
        $this->assertTrue($startedSlot->startsInPast());

        $futureSlot = TimeSlot::fromDateAndTimes('2026-02-05', '13:00', '15:00', 'UTC');
        $this->assertFalse($futureSlot->startsInPast());
    }

    // ============ Legacy Support Tests ============

    public function test_legacy_getters(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');

        $this->assertEquals('2026-02-05', $slot->getDate());
        $this->assertEquals('19:00', $slot->getTimeFrom());
        $this->assertEquals('21:00', $slot->getTimeTo());
        $this->assertEquals('2026-02-05', $slot->getEndDate());
    }

    public function test_get_time_range(): void
    {
        $normalSlot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $this->assertEquals('19:00 - 21:00', $normalSlot->getTimeRange());

        $overnightSlot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');
        $this->assertEquals('22:00 - 02:00 (+1)', $overnightSlot->getTimeRange());
    }

    // ============ Comparison Tests ============

    public function test_equals(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $slot3 = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '22:00', 'UTC');

        $this->assertTrue($slot1->equals($slot2));
        $this->assertFalse($slot1->equals($slot3));
    }

    public function test_starts_before(): void
    {
        $slot1 = TimeSlot::fromDateAndTimes('2026-02-05', '18:00', '20:00', 'UTC');
        $slot2 = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');

        $this->assertTrue($slot1->startsBefore($slot2));
        $this->assertFalse($slot2->startsBefore($slot1));
    }

    // ============ Serialization Tests ============

    public function test_to_array(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');
        $array = $slot->toArray();

        $this->assertArrayHasKey('starts_at', $array);
        $this->assertArrayHasKey('ends_at', $array);
        $this->assertArrayHasKey('timezone', $array);
        $this->assertArrayHasKey('duration_minutes', $array);
        $this->assertArrayHasKey('crosses_midnight', $array);
        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('time_from', $array);
        $this->assertArrayHasKey('time_to', $array);

        $this->assertTrue($array['crosses_midnight']);
        $this->assertEquals(240, $array['duration_minutes']);
    }

    public function test_to_string(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '22:00', '02:00', 'UTC');
        $string = (string) $slot;

        $this->assertStringContainsString('2026-02-05', $string);
        $this->assertStringContainsString('22:00', $string);
        $this->assertStringContainsString('02:00', $string);
    }

    public function test_json_serialization(): void
    {
        $slot = TimeSlot::fromDateAndTimes('2026-02-05', '19:00', '21:00', 'UTC');
        $json = json_encode($slot);
        $decoded = json_decode($json, true);

        $this->assertEquals('2026-02-05', $decoded['date']);
        $this->assertEquals('19:00', $decoded['time_from']);
        $this->assertEquals('21:00', $decoded['time_to']);
    }
}
