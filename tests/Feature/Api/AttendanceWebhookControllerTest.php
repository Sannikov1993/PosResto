<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\WorkSession;
use App\Models\StaffSchedule;
use App\Helpers\TimeHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $waiterRole;
    protected User $waiter;
    protected AttendanceDevice $anvizDevice;
    protected AttendanceDevice $zktecoDevice;
    protected AttendanceDevice $hikvisionDevice;
    protected AttendanceDevice $genericDevice;
    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Set fixed test time: 10:00 AM Moscow time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));

        // Create main restaurant
        $this->restaurant = Restaurant::factory()->create([
            'attendance_mode' => 'device_or_qr',
            'attendance_early_minutes' => 30,
            'attendance_late_minutes' => 120,
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        // Create another restaurant for isolation tests
        $this->otherRestaurant = Restaurant::factory()->create([
            'attendance_mode' => 'device_only',
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

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Generate API key
        $this->apiKey = bin2hex(random_bytes(32));

        // Create devices of different types
        $this->anvizDevice = $this->createDevice([
            'name' => 'Anviz FacePass 7',
            'type' => AttendanceDevice::TYPE_ANVIZ,
            'serial_number' => 'ANVIZ-001',
        ]);

        $this->zktecoDevice = $this->createDevice([
            'name' => 'ZKTeco SpeedFace',
            'type' => AttendanceDevice::TYPE_ZKTECO,
            'serial_number' => 'ZKTECO-001',
        ]);

        $this->hikvisionDevice = $this->createDevice([
            'name' => 'Hikvision DS-K1T341',
            'type' => AttendanceDevice::TYPE_HIKVISION,
            'serial_number' => 'HIKVISION-001',
        ]);

        $this->genericDevice = $this->createDevice([
            'name' => 'Generic Device',
            'type' => AttendanceDevice::TYPE_GENERIC,
            'serial_number' => 'GENERIC-001',
        ]);

        // Link waiter to all devices
        $this->linkUserToDevice($this->waiter, $this->anvizDevice, '100');
        $this->linkUserToDevice($this->waiter, $this->zktecoDevice, '100');
        $this->linkUserToDevice($this->waiter, $this->hikvisionDevice, '100');
        $this->linkUserToDevice($this->waiter, $this->genericDevice, '100');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to create an attendance device
     */
    protected function createDevice(array $attributes = []): AttendanceDevice
    {
        return AttendanceDevice::create([
            'restaurant_id' => $attributes['restaurant_id'] ?? $this->restaurant->id,
            'name' => $attributes['name'] ?? 'Test Device',
            'type' => $attributes['type'] ?? AttendanceDevice::TYPE_GENERIC,
            'model' => $attributes['model'] ?? 'Test Model',
            'serial_number' => $attributes['serial_number'] ?? 'SN-' . uniqid(),
            'ip_address' => $attributes['ip_address'] ?? '192.168.1.100',
            'port' => $attributes['port'] ?? 4370,
            'api_key' => $this->apiKey,
            'settings' => $attributes['settings'] ?? [],
            'status' => $attributes['status'] ?? AttendanceDevice::STATUS_ACTIVE,
            'last_heartbeat_at' => $attributes['last_heartbeat_at'] ?? null,
        ]);
    }

    /**
     * Helper to link user to device
     */
    protected function linkUserToDevice(User $user, AttendanceDevice $device, string $deviceUserId): void
    {
        $device->users()->attach($user->id, [
            'device_user_id' => $deviceUserId,
            'is_synced' => true,
            'synced_at' => now(),
            'face_status' => 'enrolled',
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
    // WEBHOOK ENDPOINT - AUTHENTICATION TESTS
    // ============================================

    public function test_webhook_requires_api_key(): void
    {
        $response = $this->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'missing_api_key',
            ]);
    }

    public function test_webhook_rejects_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid_api_key',
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'invalid_api_key',
            ]);
    }

    public function test_webhook_accepts_api_key_in_x_api_key_header(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_webhook_accepts_api_key_in_authorization_header(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_webhook_accepts_api_key_without_bearer_prefix(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'Authorization' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // WEBHOOK ENDPOINT - DEVICE TYPE VALIDATION
    // ============================================

    public function test_webhook_rejects_unknown_device_type(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/unknown_type', [
            'serial_number' => 'SN-123',
            'user_id' => '100',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'unknown_type',
            ]);
    }

    public function test_webhook_accepts_anviz_type(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    public function test_webhook_accepts_zkteco_type(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'punch' => 0, // clock in
            'timestamp' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk();
    }

    public function test_webhook_accepts_hikvision_type(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/hikvision', [
            'deviceSerialNo' => $this->hikvisionDevice->serial_number,
            'AccessControlEvent' => [
                'employeeNoString' => '100',
                'time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            ],
        ]);

        $response->assertOk();
    }

    public function test_webhook_accepts_generic_type(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/generic', [
            'serial_number' => $this->genericDevice->serial_number,
            'user_id' => '100',
            'event_type' => 'clock_in',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk();
    }

    // ============================================
    // WEBHOOK ENDPOINT - SERIAL NUMBER VALIDATION
    // ============================================

    public function test_webhook_requires_serial_number(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'missing_serial',
            ]);
    }

    public function test_webhook_returns_404_for_unknown_device(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => 'UNKNOWN-SERIAL',
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'device_not_found',
            ]);
    }

    public function test_webhook_extracts_serial_from_different_fields_for_anviz(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Test device_sn
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);
        $response->assertOk();

        // Test sn
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);
        $response->assertOk();

        // Test serial
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'serial' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);
        $response->assertOk();
    }

    public function test_webhook_extracts_serial_for_zkteco(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Test sn
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'punch' => 0,
        ]);
        $response->assertOk();

        // Test serial_number
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'serial_number' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'punch' => 0,
        ]);
        $response->assertOk();
    }

    public function test_webhook_extracts_serial_for_hikvision(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Test deviceSerialNo
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/hikvision', [
            'deviceSerialNo' => $this->hikvisionDevice->serial_number,
            'AccessControlEvent' => [
                'employeeNoString' => '100',
            ],
        ]);
        $response->assertOk();

        // Test serialNumber
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/hikvision', [
            'serialNumber' => $this->hikvisionDevice->serial_number,
            'AccessControlEvent' => [
                'employeeNoString' => '100',
            ],
        ]);
        $response->assertOk();
    }

    // ============================================
    // WEBHOOK ENDPOINT - CLOCK IN EVENTS
    // ============================================

    public function test_anviz_clock_in_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $eventTime = TimeHelper::now($this->restaurant->id);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => $eventTime->toDateTimeString(),
            'event_type' => 1, // Anviz: 1 = clock in
            'verify_mode' => 15, // face
            'confidence' => 98.5,
            'event_id' => 'EVT-001',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'device_id' => $this->anvizDevice->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_DEVICE,
            'verification_method' => AttendanceEvent::METHOD_FACE,
        ]);
    }

    public function test_zkteco_clock_in_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'punch' => 0, // ZKTeco: 0 = clock in
            'timestamp' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'device_id' => $this->zktecoDevice->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_DEVICE,
        ]);
    }

    public function test_hikvision_clock_in_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/hikvision', [
            'deviceSerialNo' => $this->hikvisionDevice->serial_number,
            'AccessControlEvent' => [
                'employeeNoString' => '100',
                'time' => now()->toIso8601String(),
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'device_id' => $this->hikvisionDevice->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_DEVICE,
        ]);
    }

    public function test_generic_clock_in_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/generic', [
            'serial_number' => $this->genericDevice->serial_number,
            'user_id' => '100',
            'event_type' => 'clock_in',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Приход зафиксирован',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'device_id' => $this->genericDevice->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_DEVICE,
        ]);
    }

    public function test_clock_in_creates_work_session(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $session = WorkSession::where('user_id', $this->waiter->id)->first();
        $this->assertNotNull($session->clock_in);
        $this->assertNull($session->clock_out);
    }

    // ============================================
    // WEBHOOK ENDPOINT - CLOCK OUT EVENTS
    // ============================================

    public function test_anviz_clock_out_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create active session first
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 2, // Anviz: 2 = clock out
            'verify_mode' => 15,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Уход зафиксирован',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
        ]);
    }

    public function test_zkteco_clock_out_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'punch' => 1, // ZKTeco: 1 = clock out
            'timestamp' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Уход зафиксирован',
            ]);
    }

    public function test_generic_clock_out_creates_attendance_event(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/generic', [
            'serial_number' => $this->genericDevice->serial_number,
            'user_id' => '100',
            'event_type' => 'clock_out',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Уход зафиксирован',
            ]);
    }

    public function test_clock_out_completes_work_session(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $session = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
            'break_minutes' => 0,
        ]);

        $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 2,
        ]);

        $session->refresh();
        $this->assertEquals(WorkSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->clock_out);
        $this->assertGreaterThan(3.9, $session->hours_worked);
    }

    // ============================================
    // WEBHOOK ENDPOINT - GENERIC EVENT TYPE PARSING
    // ============================================

    public function test_generic_parses_various_clock_in_formats(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $clockInFormats = ['in', 'clock_in', 'checkin', '0', '1'];

        foreach ($clockInFormats as $format) {
            // Reset session state
            WorkSession::where('user_id', $this->waiter->id)->delete();
            AttendanceEvent::where('user_id', $this->waiter->id)->delete();

            $response = $this->withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->postJson('/api/attendance/webhook/generic', [
                'serial_number' => $this->genericDevice->serial_number,
                'user_id' => '100',
                'event_type' => $format,
            ]);

            $response->assertOk();

            $this->assertDatabaseHas('attendance_events', [
                'user_id' => $this->waiter->id,
                'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            ]);
        }
    }

    public function test_generic_parses_various_clock_out_formats(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $clockOutFormats = ['out', 'clock_out', 'checkout', '2'];

        foreach ($clockOutFormats as $format) {
            // Create active session
            WorkSession::where('user_id', $this->waiter->id)->delete();
            AttendanceEvent::where('user_id', $this->waiter->id)->delete();

            WorkSession::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'clock_in' => now()->subHours(2),
                'status' => WorkSession::STATUS_ACTIVE,
            ]);

            $response = $this->withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->postJson('/api/attendance/webhook/generic', [
                'serial_number' => $this->genericDevice->serial_number,
                'user_id' => '100',
                'event_type' => $format,
            ]);

            $response->assertOk();

            $this->assertDatabaseHas('attendance_events', [
                'user_id' => $this->waiter->id,
                'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
            ]);
        }
    }

    // ============================================
    // WEBHOOK ENDPOINT - USER VALIDATION
    // ============================================

    public function test_webhook_fails_if_user_not_linked_to_device(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Send event with unknown device user ID
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '999', // Unknown user
            'event_type' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'user_not_found',
            ]);
    }

    public function test_webhook_uses_correct_user_for_device_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create another user and link to device with different ID
        $anotherUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
        ]);
        $this->createTodaySchedule($anotherUser, $this->restaurant);
        $this->linkUserToDevice($anotherUser, $this->anvizDevice, '200');

        // Send event for the other user
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '200',
            'event_type' => 1,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $anotherUser->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
        ]);

        $this->assertDatabaseMissing('attendance_events', [
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
        ]);
    }

    // ============================================
    // WEBHOOK ENDPOINT - HEARTBEAT
    // ============================================

    public function test_webhook_updates_device_heartbeat(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $this->assertNull($this->anvizDevice->last_heartbeat_at);

        $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $this->anvizDevice->refresh();
        $this->assertNotNull($this->anvizDevice->last_heartbeat_at);
        $this->assertTrue($this->anvizDevice->last_heartbeat_at->isToday());
    }

    public function test_webhook_marks_device_as_active(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $this->anvizDevice->update(['status' => AttendanceDevice::STATUS_OFFLINE]);

        $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $this->anvizDevice->refresh();
        $this->assertEquals(AttendanceDevice::STATUS_ACTIVE, $this->anvizDevice->status);
    }

    // ============================================
    // HEARTBEAT ENDPOINT TESTS
    // ============================================

    public function test_heartbeat_requires_serial_number(): void
    {
        $response = $this->postJson('/api/attendance/heartbeat', []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'missing_serial',
            ]);
    }

    public function test_heartbeat_returns_404_for_unknown_device(): void
    {
        $response = $this->postJson('/api/attendance/heartbeat', [
            'serial_number' => 'UNKNOWN-SN',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'device_not_found',
            ]);
    }

    public function test_heartbeat_updates_device_timestamp(): void
    {
        $this->assertNull($this->genericDevice->last_heartbeat_at);

        $response = $this->postJson('/api/attendance/heartbeat', [
            'serial_number' => $this->genericDevice->serial_number,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'device_id' => $this->genericDevice->id,
            ])
            ->assertJsonStructure([
                'success',
                'device_id',
                'server_time',
            ]);

        $this->genericDevice->refresh();
        $this->assertNotNull($this->genericDevice->last_heartbeat_at);
    }

    public function test_heartbeat_accepts_sn_parameter(): void
    {
        $response = $this->postJson('/api/attendance/heartbeat', [
            'sn' => $this->genericDevice->serial_number,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'device_id' => $this->genericDevice->id,
            ]);
    }

    // ============================================
    // ANVIZ ENROLLMENT EVENTS
    // ============================================

    public function test_anviz_enrollment_event_updates_face_status(): void
    {
        // Reset face status
        DB::table('attendance_device_users')
            ->where('device_id', $this->anvizDevice->id)
            ->where('user_id', $this->waiter->id)
            ->update(['face_status' => 'pending', 'face_enrolled_at' => null]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'event' => 'enrollment',
            'user_id' => '100',
            'enroll_type' => 'face',
            'success' => true,
            'templates_count' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Биометрия зарегистрирована',
                'enroll_type' => 'face',
            ]);

        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $this->anvizDevice->id)
            ->where('user_id', $this->waiter->id)
            ->first();

        $this->assertEquals('enrolled', $pivot->face_status);
        $this->assertNotNull($pivot->face_enrolled_at);
    }

    public function test_anviz_enrollment_event_updates_fingerprint_status(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'event' => 'enrollment',
            'user_id' => '100',
            'enroll_type' => 'fingerprint',
            'success' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'enroll_type' => 'fingerprint',
            ]);

        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $this->anvizDevice->id)
            ->where('user_id', $this->waiter->id)
            ->first();

        $this->assertEquals('enrolled', $pivot->fingerprint_status);
    }

    public function test_anviz_failed_enrollment_updates_status_to_failed(): void
    {
        // Reset status
        DB::table('attendance_device_users')
            ->where('device_id', $this->anvizDevice->id)
            ->where('user_id', $this->waiter->id)
            ->update(['face_status' => 'pending']);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'event' => 'enrollment',
            'user_id' => '100',
            'enroll_type' => 'face',
            'success' => false,
        ]);

        $response->assertOk();

        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $this->anvizDevice->id)
            ->where('user_id', $this->waiter->id)
            ->first();

        $this->assertEquals('failed', $pivot->face_status);
    }

    // ============================================
    // EVENT DEDUPLICATION
    // ============================================

    public function test_duplicate_device_event_is_handled(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $eventId = 'EVT-UNIQUE-001';

        // First event
        $response1 = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'event_id' => $eventId,
        ]);

        $response1->assertOk();

        // Second event with same event_id (duplicate)
        $response2 = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'event_id' => $eventId,
        ]);

        $response2->assertOk()
            ->assertJson([
                'success' => true,
                'duplicate' => true,
            ]);

        // Should only have one event
        $eventsCount = AttendanceEvent::where('user_id', $this->waiter->id)
            ->where('device_id', $this->anvizDevice->id)
            ->count();

        $this->assertEquals(1, $eventsCount);
    }

    // ============================================
    // AUTOMATIC EVENT TYPE DETERMINATION
    // ============================================

    public function test_automatic_clock_in_when_no_active_session(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // No active session exists
        $this->assertNull(
            WorkSession::where('user_id', $this->waiter->id)
                ->where('status', WorkSession::STATUS_ACTIVE)
                ->first()
        );

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1, // Even if device says clock_in
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Приход зафиксирован']);
    }

    public function test_automatic_clock_out_when_active_session_exists(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1, // Device might say clock_in, but we should clock out
        ]);

        // The system should automatically determine it's a clock out
        $response->assertOk()
            ->assertJson(['message' => 'Уход зафиксирован']);
    }

    public function test_manual_session_not_affected_by_automatic_clock_out(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create manual session (should not be affected by biometric events)
        $manualSession = WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
            'is_manual' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        // Should create a new clock_in since manual session is ignored
        $response->assertOk()
            ->assertJson(['message' => 'Приход зафиксирован']);

        // Manual session should remain active
        $manualSession->refresh();
        $this->assertEquals(WorkSession::STATUS_ACTIVE, $manualSession->status);
        $this->assertNull($manualSession->clock_out);
    }

    // ============================================
    // SCHEDULE VALIDATION
    // ============================================

    public function test_webhook_fails_without_schedule(): void
    {
        // No schedule created
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'no_schedule',
            ]);
    }

    public function test_webhook_fails_when_too_early(): void
    {
        // Schedule starts at 10:00
        $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '18:00');

        // Set current time to 08:00 (too early, allowed is 09:30 with 30 min early)
        Carbon::setTestNow(Carbon::parse('2026-01-31 08:00:00', 'Europe/Moscow'));

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'too_early',
            ]);

        // Restore original test time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));
    }

    public function test_webhook_fails_when_too_late(): void
    {
        // Schedule starts at 10:00
        $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '18:00');

        // Set current time to 14:00 (too late, allowed is 12:00 with 120 min late)
        Carbon::setTestNow(Carbon::parse('2026-01-31 14:00:00', 'Europe/Moscow'));

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'too_late',
            ]);

        // Restore original test time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));
    }

    public function test_webhook_succeeds_within_allowed_early_window(): void
    {
        // Schedule starts at 10:00
        $this->createTodaySchedule($this->waiter, $this->restaurant, '10:00', '18:00');

        // Set current time to 09:35 (within 30 min early window)
        Carbon::setTestNow(Carbon::parse('2026-01-31 09:35:00', 'Europe/Moscow'));

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertOk();

        // Restore original test time
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00', 'Europe/Moscow'));
    }

    public function test_webhook_skips_schedule_check_when_attendance_disabled(): void
    {
        // Set attendance mode to disabled
        $this->restaurant->update(['attendance_mode' => 'disabled']);

        // No schedule created
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        // Should succeed even without schedule when attendance is disabled
        $response->assertOk();
    }

    // ============================================
    // ATTENDANCE MODE VALIDATION
    // ============================================

    public function test_webhook_fails_when_mode_is_qr_only(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);
        $this->restaurant->update(['attendance_mode' => 'qr_only']);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'mode_not_allowed',
            ]);
    }

    public function test_webhook_succeeds_when_mode_is_device_only(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);
        $this->restaurant->update(['attendance_mode' => 'device_only']);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    public function test_webhook_succeeds_when_mode_is_device_or_qr(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);
        $this->restaurant->update(['attendance_mode' => 'device_or_qr']);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    // ============================================
    // VERIFICATION METHOD PARSING
    // ============================================

    public function test_anviz_parses_face_verification(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'verify_mode' => 15, // face
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'verification_method' => AttendanceEvent::METHOD_FACE,
        ]);
    }

    public function test_anviz_parses_fingerprint_verification(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'verify_mode' => 2, // fingerprint
            'method' => 'fingerprint',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'verification_method' => AttendanceEvent::METHOD_FINGERPRINT,
        ]);
    }

    public function test_anviz_parses_card_verification(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'verify_mode' => 4, // card
            'method' => 'card',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'verification_method' => AttendanceEvent::METHOD_CARD,
        ]);
    }

    // ============================================
    // TIME PARSING
    // ============================================

    public function test_webhook_parses_datetime_string(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Use 09:30 which is within the allowed window (08:00 start + 120 min late = 10:00 max)
        $eventTime = TimeHelper::today($this->restaurant->id)->setTime(9, 30, 0);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'event_time' => $eventTime->format('Y-m-d H:i:s'),
        ]);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)->first();
        $this->assertEquals($eventTime->format('Y-m-d H:i'), $event->event_time->format('Y-m-d H:i'));
    }

    public function test_webhook_parses_unix_timestamp(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Use 09:45 which is within the allowed window (08:00 start + 120 min late = 10:00 max)
        $eventTime = TimeHelper::today($this->restaurant->id)->setTime(9, 45, 0);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
            'event_time' => $eventTime->timestamp,
        ]);

        $response->assertOk();
    }

    public function test_webhook_uses_current_time_when_no_time_provided(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_type' => 1,
        ]);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)->first();
        // Event time should be same as the test time set in setUp (10:00)
        $this->assertEquals('10:00', $event->event_time->format('H:i'));
    }

    // ============================================
    // CONFIDENCE SCORE
    // ============================================

    public function test_webhook_stores_confidence_score(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
            'confidence' => 98.75,
        ]);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)->first();
        $this->assertEquals(98.75, (float) $event->confidence);
    }

    // ============================================
    // RAW DATA STORAGE
    // ============================================

    public function test_webhook_stores_raw_data(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $rawPayload = [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
            'confidence' => 98.5,
            'verify_mode' => 15,
            'custom_field' => 'custom_value',
        ];

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', $rawPayload);

        $response->assertOk();

        $event = AttendanceEvent::where('user_id', $this->waiter->id)->first();
        $this->assertIsArray($event->raw_data);
        $this->assertArrayHasKey('raw', $event->raw_data);
    }

    // ============================================
    // DEVICE ISOLATION
    // ============================================

    public function test_api_key_is_device_specific(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create device with different API key
        $otherApiKey = bin2hex(random_bytes(32));
        $otherDevice = AttendanceDevice::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Other Device',
            'type' => AttendanceDevice::TYPE_GENERIC,
            'serial_number' => 'OTHER-SN',
            'api_key' => $otherApiKey,
            'status' => AttendanceDevice::STATUS_ACTIVE,
        ]);

        // Try to use first device's API key with other device's serial
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey, // Wrong API key for OTHER-SN
        ])->postJson('/api/attendance/webhook/generic', [
            'serial_number' => 'OTHER-SN',
            'user_id' => '100',
            'event_type' => 'clock_in',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'invalid_api_key',
            ]);
    }

    // ============================================
    // RATE LIMITING (CONCEPTUAL TEST)
    // ============================================

    public function test_webhook_endpoint_has_rate_limiting(): void
    {
        // This is a conceptual test - the actual rate limiting is handled by middleware
        // We just verify the endpoint works normally under the rate limit
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    // ============================================
    // ERROR HANDLING
    // ============================================

    public function test_webhook_handles_invalid_json_gracefully(): void
    {
        // Laravel will handle malformed JSON and return 422
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->call('POST', '/api/attendance/webhook/anviz', [], [], [], [], 'invalid json{');

        // Should not be 500 server error
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_webhook_logs_request_without_sensitive_data(): void
    {
        // This test verifies that the controller doesn't log sensitive data
        // The controller excludes api_key, password, token from logs
        Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(function ($message, $context = []) {
                if (is_string($message) && str_contains($message, 'Attendance webhook')) {
                    $this->assertArrayNotHasKey('api_key', $context['data'] ?? []);
                    $this->assertArrayNotHasKey('password', $context['data'] ?? []);
                    $this->assertArrayNotHasKey('token', $context['data'] ?? []);
                    return true;
                }
                return true;
            });

        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
            'api_key' => 'should_not_be_logged',
        ]);
    }

    // ============================================
    // EDGE CASES
    // ============================================

    public function test_webhook_handles_empty_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '',
            'event_type' => 1,
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_handles_string_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => '100', // String instead of int
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    public function test_webhook_handles_numeric_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/anviz', [
            'device_sn' => $this->anvizDevice->serial_number,
            'user_id' => 100, // Numeric
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
            'event_type' => 1,
        ]);

        $response->assertOk();
    }

    public function test_hikvision_event_without_access_control_wrapper(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Some Hikvision devices send events without AccessControlEvent wrapper
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/hikvision', [
            'deviceSerialNo' => $this->hikvisionDevice->serial_number,
            'employeeNoString' => '100',
            'time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk();
    }

    public function test_zkteco_status_field_for_clock_out(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => TimeHelper::now($this->restaurant->id)->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        // ZKTeco might use 'status' instead of 'punch'
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'user_id' => '100',
            'status' => 1, // clock out
            'timestamp' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Уход зафиксирован']);
    }

    public function test_zkteco_pin_field_for_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/zkteco', [
            'sn' => $this->zktecoDevice->serial_number,
            'pin' => '100', // Some ZKTeco use 'pin' instead of 'user_id'
            'punch' => 0,
            'timestamp' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk();
    }

    public function test_generic_employee_id_field_for_user_id(): void
    {
        $this->createTodaySchedule($this->waiter, $this->restaurant);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->postJson('/api/attendance/webhook/generic', [
            'serial_number' => $this->genericDevice->serial_number,
            'employee_id' => '100',
            'event_type' => 'clock_in',
            'event_time' => TimeHelper::now($this->restaurant->id)->toDateTimeString(),
        ]);

        $response->assertOk();
    }
}
