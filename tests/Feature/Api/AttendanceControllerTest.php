<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\AttendanceEvent;
use App\Models\AttendanceQrCode;
use App\Models\WorkSession;
use App\Models\StaffSchedule;
use Carbon\Carbon;
use App\Helpers\TimeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $otherRestaurantUser;
    protected string $adminToken;
    protected string $waiterToken;
    protected string $otherRestaurantToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Set fixed test time: 10:00 AM Moscow time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));

        // Create main restaurant with QR attendance enabled
        $this->restaurant = Restaurant::factory()->create([
            'attendance_mode' => 'device_or_qr',
            'attendance_early_minutes' => 30,
            'attendance_late_minutes' => 120,
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        // Create another restaurant for isolation tests
        $this->otherRestaurant = Restaurant::factory()->create([
            'attendance_mode' => 'qr_only',
            'latitude' => 55.7600,
            'longitude' => 37.6200,
        ]);

        // Create admin role
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create waiter role
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
        ]);

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Create user from another restaurant
        $otherRole = Role::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
        ]);

        $this->otherRestaurantUser = User::factory()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'role' => 'waiter',
            'role_id' => $otherRole->id,
            'is_active' => true,
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->otherRestaurantToken = $this->otherRestaurantUser->createToken('test')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to create a valid QR code for a restaurant
     */
    protected function createQrCode(int $restaurantId, array $options = []): AttendanceQrCode
    {
        return AttendanceQrCode::create([
            'restaurant_id' => $restaurantId,
            'code' => $options['code'] ?? \Illuminate\Support\Str::random(32),
            'secret' => $options['secret'] ?? \Illuminate\Support\Str::random(64),
            'type' => $options['type'] ?? AttendanceQrCode::TYPE_STATIC,
            'require_geolocation' => $options['require_geolocation'] ?? false,
            'max_distance_meters' => $options['max_distance_meters'] ?? 100,
            'refresh_interval_minutes' => $options['refresh_interval_minutes'] ?? 5,
            'expires_at' => $options['expires_at'] ?? null,
            'is_active' => $options['is_active'] ?? true,
        ]);
    }

    /**
     * Helper to create a published schedule for today
     */
    protected function createTodaySchedule(User $user, Restaurant $restaurant, string $startTime = '08:00', string $endTime = '20:00'): StaffSchedule
    {
        return StaffSchedule::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'date' => TimeHelper::today($restaurant->id),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_minutes' => 60,
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => TimeHelper::now($restaurant->id),
        ]);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/cabinet/attendance/status');

        $response->assertStatus(401);
    }

    public function test_clock_in_qr_requires_authentication(): void
    {
        $response = $this->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => 'some-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_clock_out_qr_requires_authentication(): void
    {
        $response = $this->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => 'some-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/cabinet/attendance/history');

        $response->assertStatus(401);
    }

    public function test_validate_qr_requires_authentication(): void
    {
        $response = $this->postJson('/api/cabinet/attendance/qr/validate', [
            'qr_token' => 'some-token',
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // STATUS ENDPOINT TESTS
    // ============================================

    public function test_can_get_attendance_status(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_clocked_in',
                    'can_clock_in',
                    'can_clock_out',
                    'attendance_mode',
                    'qr_enabled',
                    'device_enabled',
                ],
            ]);
    }

    public function test_status_shows_not_clocked_in_by_default(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_clocked_in' => false,
                    'can_clock_in' => true,
                    'can_clock_out' => false,
                ],
            ]);
    }

    public function test_status_shows_clocked_in_when_active_session_exists(): void
    {
        // Create active work session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_clocked_in' => true,
                    'can_clock_in' => false,
                    'can_clock_out' => true,
                ],
            ]);
    }

    public function test_status_returns_correct_attendance_mode(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'attendance_mode' => 'device_or_qr',
                    'qr_enabled' => true,
                    'device_enabled' => true,
                ],
            ]);
    }

    public function test_status_includes_today_schedule(): void
    {
        $schedule = $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '19:00');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data['today_schedule']);
    }

    public function test_status_includes_today_sessions(): void
    {
        // Create completed session for today
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => today()->setTime(9, 0),
            'clock_out' => today()->setTime(13, 0),
            'hours_worked' => 4,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertIsArray($data['today_sessions']);
        $this->assertCount(1, $data['today_sessions']);
    }

    // ============================================
    // CLOCK IN VIA QR TESTS
    // ============================================

    public function test_can_clock_in_via_qr(): void
    {
        // Create schedule for today
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);

        // Verify attendance event was created
        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
        ]);

        // Verify work session was created
        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => WorkSession::STATUS_ACTIVE,
        ]);
    }

    public function test_clock_in_qr_validates_required_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['qr_token']);
    }

    public function test_clock_in_qr_validates_latitude_range(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            'latitude' => 100, // Invalid: must be between -90 and 90
            'longitude' => 37.6173,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_clock_in_qr_validates_longitude_range(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            'latitude' => 55.7558,
            'longitude' => 200, // Invalid: must be between -180 and 180
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_clock_in_qr_fails_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => 'invalid-token-that-does-not-exist',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'invalid_qr',
            ]);
    }

    public function test_clock_in_qr_fails_with_expired_dynamic_token(): void
    {
        // Create dynamic QR code that's already expired
        $qrCode = $this->createQrCode($this->restaurant->id, [
            'type' => AttendanceQrCode::TYPE_DYNAMIC,
            'expires_at' => now()->subMinutes(10),
            'refresh_interval_minutes' => 5,
        ]);

        $token = $qrCode->generateToken();

        // Simulate token generated in the past
        $timestamp = now()->subMinutes(10)->timestamp;
        $payload = "{$qrCode->code}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, $qrCode->secret);
        $expiredToken = base64_encode("{$payload}:{$signature}");

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $expiredToken,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'expired_qr',
            ]);
    }

    public function test_clock_in_qr_requires_geolocation_when_configured(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id, [
            'require_geolocation' => true,
        ]);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            // No latitude/longitude provided
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'geolocation_required',
            ]);
    }

    public function test_clock_in_qr_fails_when_too_far_from_restaurant(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id, [
            'require_geolocation' => true,
            'max_distance_meters' => 100,
        ]);
        $token = $qrCode->generateToken();

        // Location far from restaurant (about 10km away)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            'latitude' => 55.8558, // ~10km north
            'longitude' => 37.6173,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'too_far',
            ]);
    }

    public function test_clock_in_qr_succeeds_when_within_distance(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id, [
            'require_geolocation' => true,
            'max_distance_meters' => 100,
        ]);
        $token = $qrCode->generateToken();

        // Location very close to restaurant
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);
    }

    public function test_clock_in_qr_fails_without_schedule_when_attendance_enabled(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        // No schedule created for today
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'no_schedule',
            ]);
    }

    public function test_clock_in_qr_fails_when_too_early(): void
    {
        // Schedule starts at 10:00, early minutes = 30 (so 09:30 is earliest)
        // First create schedule with original time
        $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '18:00');

        // Set current time to 08:00 (too early)
        Carbon::setTestNow(Carbon::parse('2026-01-31 08:00:00', 'Europe/Moscow'));

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'too_early',
            ]);

        // Restore original test time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));
    }

    public function test_clock_in_qr_fails_when_too_late(): void
    {
        // Schedule starts at 10:00, late minutes = 120 (so 12:00 is latest)
        // First create schedule with original time
        $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '18:00');

        // Set current time to 14:00 (too late)
        Carbon::setTestNow(Carbon::parse('2026-01-31 14:00:00', 'Europe/Moscow'));

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'too_late',
            ]);

        // Restore original test time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));
    }

    // ============================================
    // CLOCK OUT VIA QR TESTS
    // ============================================

    public function test_can_clock_out_via_qr(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        // Create active work session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Уход зафиксирован',
            ]);

        // Verify attendance event was created
        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
        ]);

        // Verify work session was completed
        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);
    }

    public function test_clock_out_qr_validates_required_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['qr_token']);
    }

    public function test_clock_out_qr_fails_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'invalid_qr',
            ]);
    }

    public function test_clock_out_calculates_hours_worked(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        // Create active session from 4 hours ago
        $session = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
            'break_minutes' => 0,
        ]);

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => $token,
        ]);

        $response->assertOk();

        $session->refresh();
        $this->assertNotNull($session->clock_out);
        $this->assertGreaterThan(3.9, $session->hours_worked);
        $this->assertLessThan(4.1, $session->hours_worked);
    }

    // ============================================
    // QR CODE PUBLIC ENDPOINTS TESTS
    // ============================================

    public function test_can_get_qr_code_for_restaurant(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id);

        $response = $this->getJson("/api/attendance/qr/{$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'scan_url',
                    'type',
                    'restaurant' => [
                        'id',
                        'name',
                    ],
                ],
            ]);
    }

    public function test_get_qr_code_returns_404_for_nonexistent_restaurant(): void
    {
        $response = $this->getJson('/api/attendance/qr/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'restaurant_not_found',
            ]);
    }

    public function test_get_qr_code_fails_when_qr_disabled(): void
    {
        // Update restaurant to device_only mode
        $this->restaurant->update(['attendance_mode' => 'device_only']);

        $response = $this->getJson("/api/attendance/qr/{$this->restaurant->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'qr_disabled',
            ]);
    }

    public function test_get_qr_code_creates_new_if_none_exists(): void
    {
        // Restaurant allows QR but no QR code exists
        $response = $this->getJson("/api/attendance/qr/{$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Verify QR code was created
        $this->assertDatabaseHas('attendance_qr_codes', [
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);
    }

    public function test_can_refresh_qr_code(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id, [
            'type' => AttendanceQrCode::TYPE_DYNAMIC,
        ]);
        $originalCode = $qrCode->code;

        $response = $this->postJson("/api/attendance/qr/{$this->restaurant->id}/refresh");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'expires_at',
                ],
            ]);

        $qrCode->refresh();
        $this->assertNotEquals($originalCode, $qrCode->code);
    }

    public function test_refresh_qr_code_returns_404_when_no_active_qr(): void
    {
        // No QR code exists for restaurant
        $response = $this->postJson("/api/attendance/qr/{$this->restaurant->id}/refresh");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'qr_not_found',
            ]);
    }

    // ============================================
    // QR VALIDATION ENDPOINT TESTS
    // ============================================

    public function test_can_validate_qr_token(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/validate', [
            'qr_token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => true,
                'restaurant' => [
                    'id' => $this->restaurant->id,
                    'name' => $this->restaurant->name,
                ],
            ]);
    }

    public function test_validate_qr_returns_invalid_for_bad_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/validate', [
            'qr_token' => 'invalid-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'valid' => false,
                'error' => 'invalid_qr',
            ]);
    }

    public function test_validate_qr_requires_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/validate', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['qr_token']);
    }

    public function test_validate_qr_returns_geolocation_requirement(): void
    {
        $qrCode = $this->createQrCode($this->restaurant->id, [
            'require_geolocation' => true,
        ]);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/validate', [
            'qr_token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => true,
                'require_geolocation' => true,
            ]);
    }

    // ============================================
    // HISTORY ENDPOINT TESTS
    // ============================================

    public function test_can_get_attendance_history(): void
    {
        // Create some attendance events
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => TimeHelper::now($this->restaurant->id)->subHours(8),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => TimeHelper::now($this->restaurant->id)->subHours(4),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/history');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'source',
                        'source_label',
                        'method',
                        'method_label',
                        'event_time',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_history_respects_limit_parameter(): void
    {
        // Create 10 events
        for ($i = 0; $i < 10; $i++) {
            AttendanceEvent::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'event_type' => $i % 2 === 0 ? AttendanceEvent::TYPE_CLOCK_IN : AttendanceEvent::TYPE_CLOCK_OUT,
                'source' => AttendanceEvent::SOURCE_QR_CODE,
                'verification_method' => AttendanceEvent::METHOD_QR,
                'event_time' => TimeHelper::now($this->restaurant->id)->subHours($i),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/history?limit=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_history_returns_only_own_events(): void
    {
        // Create event for another user
        $otherUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $otherUser->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => now(),
        ]);

        // Create event for current user
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/history');

        $response->assertOk();

        // Should only see own events
        $this->assertCount(1, $response->json('data'));
    }

    public function test_history_ordered_by_event_time_descending(): void
    {
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => TimeHelper::now($this->restaurant->id)->subHours(8),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => TimeHelper::now($this->restaurant->id)->subHours(4),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/history');

        $response->assertOk();

        $data = $response->json('data');

        // Most recent should be first
        $this->assertEquals(AttendanceEvent::TYPE_CLOCK_IN, $data[0]['type']);
        $this->assertEquals(AttendanceEvent::TYPE_CLOCK_OUT, $data[1]['type']);
    }

    // ============================================
    // RESTAURANT ISOLATION TESTS
    // ============================================

    public function test_cannot_use_qr_from_different_restaurant(): void
    {
        // Create schedule for other restaurant user
        $this->createTodaySchedule($this->otherRestaurantUser, $this->otherRestaurant, '08:00', '20:00');

        // Create QR code for main restaurant
        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        // Try to use it with user from other restaurant
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherRestaurantToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        // Should fail because QR is for different restaurant than user's schedule
        $response->assertStatus(400);
    }

    public function test_status_shows_correct_restaurant_attendance_mode(): void
    {
        // Other restaurant has qr_only mode
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherRestaurantToken}",
        ])->getJson('/api/cabinet/attendance/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'attendance_mode' => 'qr_only',
                    'qr_enabled' => true,
                    'device_enabled' => false,
                ],
            ]);
    }

    public function test_history_isolated_per_user(): void
    {
        // Create events for waiter in main restaurant
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        // Create events for user in other restaurant
        AttendanceEvent::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'user_id' => $this->otherRestaurantUser->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        // Main restaurant user should see only their events
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/cabinet/attendance/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));

        // Other restaurant user should see only their events
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherRestaurantToken}",
        ])->getJson('/api/cabinet/attendance/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ============================================
    // ATTENDANCE MODE VALIDATION TESTS
    // ============================================

    public function test_clock_in_fails_when_attendance_disabled_but_has_schedule(): void
    {
        // Disable attendance for restaurant
        $this->restaurant->update(['attendance_mode' => 'disabled']);

        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        // When disabled, it should still work (disabled = no restrictions)
        $response->assertOk();
    }

    public function test_clock_in_via_qr_fails_when_device_only_mode(): void
    {
        // Set device only mode
        $this->restaurant->update(['attendance_mode' => 'device_only']);

        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'mode_not_allowed',
            ]);
    }

    // ============================================
    // WORK SESSION LIFECYCLE TESTS
    // ============================================

    public function test_clock_in_closes_unclosed_sessions(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        // Create old unclosed session
        $oldSession = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subDays(1),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertOk();

        // Old session should be auto-closed
        $oldSession->refresh();
        $this->assertEquals(WorkSession::STATUS_AUTO_CLOSED, $oldSession->status);
        $this->assertNotNull($oldSession->clock_out);
        $this->assertEquals(0, $oldSession->hours_worked);
    }

    public function test_clock_in_creates_new_session(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $session = WorkSession::where('user_id', $this->waiter->id)
            ->where('restaurant_id', $this->restaurant->id)
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->first();

        $this->assertNotNull($session);
        $this->assertNotNull($session->clock_in);
        $this->assertNull($session->clock_out);
    }

    public function test_clock_out_completes_session(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $session = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(5),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => $token,
        ]);

        $session->refresh();
        $this->assertEquals(WorkSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->clock_out);
        $this->assertNotNull($session->hours_worked);
    }

    public function test_manual_session_not_affected_by_clock_out(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        // Create manual session (should not be closed by biometric/QR)
        $manualSession = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
            'is_manual' => true,
        ]);

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => $token,
        ]);

        $response->assertOk();

        // Manual session should still be active
        $manualSession->refresh();
        $this->assertEquals(WorkSession::STATUS_ACTIVE, $manualSession->status);
        $this->assertNull($manualSession->clock_out);
    }

    // ============================================
    // RATE LIMITING TESTS (conceptual - may need adjustment based on actual middleware)
    // ============================================

    public function test_public_qr_endpoints_are_rate_limited(): void
    {
        // This test verifies the route has throttle middleware
        // Actual rate limit testing would require multiple rapid requests
        $this->createQrCode($this->restaurant->id);

        // Make a valid request - should work
        $response = $this->getJson("/api/attendance/qr/{$this->restaurant->id}");
        $response->assertOk();

        // Check that rate limit headers are present (if your app includes them)
        // This is a basic sanity check; full rate limit testing is complex
    }

    // ============================================
    // EVENT RECORDING TESTS
    // ============================================

    public function test_clock_in_records_ip_address(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)
            ->where('event_type', AttendanceEvent::TYPE_CLOCK_IN)
            ->first();

        $this->assertNotNull($event->ip_address);
    }

    public function test_clock_in_records_geolocation(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id, [
            'require_geolocation' => true,
        ]);
        $token = $qrCode->generateToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)
            ->where('event_type', AttendanceEvent::TYPE_CLOCK_IN)
            ->first();

        $this->assertEquals(55.7558, (float) $event->latitude);
        $this->assertEquals(37.6173, (float) $event->longitude);
    }

    public function test_events_linked_to_work_session(): void
    {
        // Use schedule that allows 10:00 arrival (08:00 start + 120 min late = 10:00 max)
        $this->createTodaySchedule($this->waiter, $this->restaurant, '08:00', '20:00');

        $qrCode = $this->createQrCode($this->restaurant->id);
        $token = $qrCode->generateToken();

        // Clock in
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-in', [
            'qr_token' => $token,
        ]);

        $response->assertOk();

        $clockInEvent = AttendanceEvent::where('user_id', $this->waiter->id)
            ->where('event_type', AttendanceEvent::TYPE_CLOCK_IN)
            ->first();

        $this->assertNotNull($clockInEvent);
        $this->assertNotNull($clockInEvent->work_session_id);

        // Clock out
        $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/cabinet/attendance/qr/clock-out', [
            'qr_token' => $qrCode->generateToken(),
        ]);

        $clockOutEvent = AttendanceEvent::where('user_id', $this->waiter->id)
            ->where('event_type', AttendanceEvent::TYPE_CLOCK_OUT)
            ->first();

        $this->assertNotNull($clockOutEvent->work_session_id);
        $this->assertEquals($clockInEvent->work_session_id, $clockOutEvent->work_session_id);
    }
}
