<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\AttendanceQrCode;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AttendanceDeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $otherRestaurantAdmin;
    protected string $adminToken;
    protected string $waiterToken;
    protected string $otherRestaurantToken;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Attach required permissions for attendance device management
        foreach (['staff.view', 'staff.edit'] as $permKey) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $permKey,
            ], [
                'name' => $permKey,
                'group' => 'staff',
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

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
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Attach permissions for other restaurant admin
        foreach (['staff.view', 'staff.edit'] as $permKey) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->otherRestaurant->id,
                'key' => $permKey,
            ], [
                'name' => $permKey,
                'group' => 'staff',
            ]);
            $otherRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->otherRestaurantAdmin = User::factory()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'role' => 'admin',
            'role_id' => $otherRole->id,
            'is_active' => true,
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->otherRestaurantToken = $this->otherRestaurantAdmin->createToken('test')->plainTextToken;
    }

    /**
     * Helper to create an attendance device
     */
    protected function createDevice(array $attributes = []): AttendanceDevice
    {
        $defaults = [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Device',
            'type' => AttendanceDevice::TYPE_GENERIC,
            'model' => 'Test Model',
            'serial_number' => 'SN-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'api_key' => bin2hex(random_bytes(32)),
            'settings' => [],
            'status' => AttendanceDevice::STATUS_ACTIVE,
            'last_heartbeat_at' => null,
            'last_sync_at' => null,
        ];

        // Merge with array_replace to allow explicit null values
        return AttendanceDevice::create(array_replace($defaults, $attributes));
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/backoffice/attendance/devices');

        $response->assertStatus(401);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Test Device',
            'type' => 'generic',
            'serial_number' => 'SN-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_show_requires_authentication(): void
    {
        $device = $this->createDevice();

        $response = $this->getJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertStatus(401);
    }

    public function test_update_requires_authentication(): void
    {
        $device = $this->createDevice();

        $response = $this->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_requires_authentication(): void
    {
        $device = $this->createDevice();

        $response = $this->deleteJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertStatus(401);
    }

    // ============================================
    // DEVICE CRUD - INDEX
    // ============================================

    public function test_can_list_devices(): void
    {
        $this->createDevice(['name' => 'Device A']);
        $this->createDevice(['name' => 'Device B']);
        $this->createDevice(['name' => 'Device C']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'type_label',
                        'model',
                        'serial_number',
                        'ip_address',
                        'status',
                        'is_online',
                        'users_count',
                        'last_heartbeat_at',
                        'last_sync_at',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_devices_list_ordered_by_name(): void
    {
        $this->createDevice(['name' => 'Zebra Device']);
        $this->createDevice(['name' => 'Alpha Device']);
        $this->createDevice(['name' => 'Beta Device']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        $names = array_column($response->json('data'), 'name');
        $this->assertEquals(['Alpha Device', 'Beta Device', 'Zebra Device'], $names);
    }

    public function test_devices_list_includes_users_count(): void
    {
        $device = $this->createDevice();

        // Add users to device
        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '1',
            'is_synced' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        $deviceData = collect($response->json('data'))->firstWhere('id', $device->id);
        $this->assertEquals(1, $deviceData['users_count']);
    }

    public function test_devices_list_shows_online_status(): void
    {
        // Online device (heartbeat within 5 minutes)
        $onlineDevice = $this->createDevice([
            'name' => 'Online Device',
            'last_heartbeat_at' => now()->subMinutes(2),
        ]);

        // Offline device (heartbeat more than 5 minutes ago)
        $offlineDevice = $this->createDevice([
            'name' => 'Offline Device',
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        // Device without heartbeat
        $newDevice = $this->createDevice([
            'name' => 'New Device',
            'last_heartbeat_at' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        $data = collect($response->json('data'));

        $this->assertTrue($data->firstWhere('id', $onlineDevice->id)['is_online']);
        $this->assertFalse($data->firstWhere('id', $offlineDevice->id)['is_online']);
        $this->assertFalse($data->firstWhere('id', $newDevice->id)['is_online']);
    }

    // ============================================
    // DEVICE CRUD - STORE
    // ============================================

    public function test_can_create_device(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Новое устройство',
            'type' => 'anviz',
            'model' => 'C2 Pro',
            'serial_number' => 'SN-12345678',
            'ip_address' => '192.168.1.200',
            'port' => 5010,
            'settings' => ['mode' => 'face'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'serial_number',
                ],
                'api_key',
                'webhook_url',
            ]);

        // API key should be returned only on creation
        $this->assertNotEmpty($response->json('api_key'));
        $this->assertStringContainsString('/api/attendance/webhook/anviz', $response->json('webhook_url'));

        $this->assertDatabaseHas('attendance_devices', [
            'name' => 'Новое устройство',
            'type' => 'anviz',
            'model' => 'C2 Pro',
            'serial_number' => 'SN-12345678',
            'ip_address' => '192.168.1.200',
            'port' => 5010,
            'restaurant_id' => $this->restaurant->id,
            'status' => AttendanceDevice::STATUS_ACTIVE,
        ]);
    }

    public function test_create_device_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'serial_number']);
    }

    public function test_create_device_validates_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Test Device',
            'type' => 'invalid_type',
            'serial_number' => 'SN-123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_device_validates_unique_serial_number(): void
    {
        $this->createDevice(['serial_number' => 'EXISTING-SN']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Test Device',
            'type' => 'generic',
            'serial_number' => 'EXISTING-SN',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_create_device_validates_ip_address(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Test Device',
            'type' => 'generic',
            'serial_number' => 'SN-123',
            'ip_address' => 'invalid-ip',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ip_address']);
    }

    public function test_create_device_validates_port_range(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices', [
            'name' => 'Test Device',
            'type' => 'generic',
            'serial_number' => 'SN-123',
            'port' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['port']);
    }

    public function test_create_device_with_all_types(): void
    {
        $types = ['anviz', 'zkteco', 'hikvision', 'generic'];

        foreach ($types as $type) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->postJson('/api/backoffice/attendance/devices', [
                'name' => "Device {$type}",
                'type' => $type,
                'serial_number' => "SN-{$type}-" . uniqid(),
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('attendance_devices', 4);
    }

    // ============================================
    // DEVICE CRUD - SHOW
    // ============================================

    public function test_can_show_device(): void
    {
        $device = $this->createDevice(['type' => 'anviz']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => 'anviz',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'type_label',
                    'model',
                    'serial_number',
                    'ip_address',
                    'port',
                    'settings',
                    'status',
                    'is_online',
                    'last_heartbeat_at',
                    'last_sync_at',
                    'users',
                    'webhook_url',
                ],
            ]);
    }

    public function test_show_device_includes_users(): void
    {
        $device = $this->createDevice();

        // Add user to device
        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '100',
            'is_synced' => true,
            'synced_at' => now(),
            'face_status' => 'enrolled',
            'face_enrolled_at' => now(),
            'face_templates_count' => 1,
            'fingerprint_status' => 'none',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertOk();

        $users = $response->json('data.users');
        $this->assertCount(1, $users);
        $this->assertEquals($this->waiter->id, $users[0]['id']);
        $this->assertEquals('100', $users[0]['device_user_id']);
        // SQLite returns 0/1 for booleans, so use assertEquals instead of assertTrue
        $this->assertEquals(1, $users[0]['is_synced']);
        $this->assertEquals('enrolled', $users[0]['face_status']);
        $this->assertEquals(1, $users[0]['has_biometric']);
        $this->assertEquals(0, $users[0]['needs_enrollment']);
    }

    public function test_show_device_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'not_found',
            ]);
    }

    // ============================================
    // DEVICE CRUD - UPDATE
    // ============================================

    public function test_can_update_device(): void
    {
        $device = $this->createDevice(['name' => 'Old Name']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'name' => 'New Name',
            'model' => 'Updated Model',
            'ip_address' => '192.168.1.250',
            'port' => 8080,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('attendance_devices', [
            'id' => $device->id,
            'name' => 'New Name',
            'model' => 'Updated Model',
            'ip_address' => '192.168.1.250',
            'port' => 8080,
        ]);
    }

    public function test_can_update_device_status(): void
    {
        $device = $this->createDevice(['status' => AttendanceDevice::STATUS_ACTIVE]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_devices', [
            'id' => $device->id,
            'status' => 'inactive',
        ]);
    }

    public function test_update_device_validates_status(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_device_validates_unique_serial_number(): void
    {
        $device1 = $this->createDevice(['serial_number' => 'SN-FIRST']);
        $device2 = $this->createDevice(['serial_number' => 'SN-SECOND']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device2->id}", [
            'serial_number' => 'SN-FIRST',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_update_device_allows_same_serial_number(): void
    {
        $device = $this->createDevice(['serial_number' => 'SN-SAME']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'serial_number' => 'SN-SAME',
            'name' => 'New Name',
        ]);

        $response->assertOk();
    }

    public function test_update_device_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/devices/99999', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_can_update_device_settings(): void
    {
        $device = $this->createDevice(['settings' => ['old' => 'setting']]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$device->id}", [
            'settings' => ['mode' => 'face', 'threshold' => 80],
        ]);

        $response->assertOk();

        $device->refresh();
        $this->assertEquals(['mode' => 'face', 'threshold' => 80], $device->settings);
    }

    // ============================================
    // DEVICE CRUD - DESTROY
    // ============================================

    public function test_can_delete_device(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('attendance_devices', [
            'id' => $device->id,
        ]);
    }

    public function test_delete_device_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson('/api/backoffice/attendance/devices/99999');

        $response->assertStatus(404);
    }

    // ============================================
    // DEVICE API KEY MANAGEMENT
    // ============================================

    public function test_can_regenerate_api_key(): void
    {
        $device = $this->createDevice(['api_key' => 'old_api_key_12345']);
        $oldKey = $device->api_key;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/regenerate-key");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'api_key',
            ]);

        $newKey = $response->json('api_key');
        $this->assertNotEquals($oldKey, $newKey);
        $this->assertEquals(64, strlen($newKey)); // 32 bytes = 64 hex chars

        $device->refresh();
        $this->assertEquals($newKey, $device->api_key);
    }

    public function test_regenerate_key_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices/99999/regenerate-key');

        $response->assertStatus(404);
    }

    // ============================================
    // DEVICE USER SYNC
    // ============================================

    public function test_can_sync_users_to_device(): void
    {
        $device = $this->createDevice(['type' => 'generic']);

        $user1 = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
        ]);
        $user2 = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/sync-users", [
            'user_ids' => [$user1->id, $user2->id],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'results' => [
                    '*' => [
                        'user_id',
                        'user_name',
                        'success',
                    ],
                ],
            ]);

        // Verify users are linked to device
        $this->assertDatabaseHas('attendance_device_users', [
            'device_id' => $device->id,
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseHas('attendance_device_users', [
            'device_id' => $device->id,
            'user_id' => $user2->id,
        ]);
    }

    public function test_sync_users_validates_user_ids(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/sync-users", [
            'user_ids' => [99999, 99998],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.0', 'user_ids.1']);
    }

    public function test_sync_users_requires_user_ids(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/sync-users", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    // ============================================
    // DEVICE CONNECTION TEST
    // ============================================

    public function test_test_connection_without_ip_returns_error(): void
    {
        $device = $this->createDevice(['ip_address' => null, 'type' => 'generic']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/test-connection");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'no_ip',
            ]);
    }

    public function test_test_connection_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/devices/99999/test-connection');

        $response->assertStatus(404);
    }

    // ============================================
    // DEVICE USERS MANAGEMENT
    // ============================================

    public function test_can_add_user_to_device(): void
    {
        $device = $this->createDevice(['type' => 'generic']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/device-users", [
            'user_id' => $this->waiter->id,
            'device_user_id' => 100,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'device_user_id',
                'sync_status',
            ]);

        $this->assertDatabaseHas('attendance_device_users', [
            'device_id' => $device->id,
            'user_id' => $this->waiter->id,
            'device_user_id' => '100',
        ]);
    }

    public function test_add_user_to_device_auto_generates_device_user_id(): void
    {
        $device = $this->createDevice();

        // Add first user
        $device->users()->attach($this->admin->id, [
            'device_user_id' => '5',
            'is_synced' => true,
        ]);

        // Add second user without specifying device_user_id
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/device-users", [
            'user_id' => $this->waiter->id,
        ]);

        $response->assertOk();

        // Should get device_user_id = 6 (max + 1)
        $this->assertEquals(6, $response->json('device_user_id'));
    }

    public function test_add_user_validates_user_belongs_to_restaurant(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/device-users", [
            'user_id' => $this->otherRestaurantAdmin->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'user_not_found',
            ]);
    }

    public function test_can_remove_user_from_device(): void
    {
        $device = $this->createDevice();

        // Add user to device
        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '100',
            'is_synced' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/devices/{$device->id}/device-users/100");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Доступ отозван',
            ]);

        $this->assertDatabaseMissing('attendance_device_users', [
            'device_id' => $device->id,
            'device_user_id' => '100',
        ]);
    }

    public function test_can_update_device_user_id(): void
    {
        $device = $this->createDevice();

        // Add user with device_user_id 100
        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '100',
            'is_synced' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/backoffice/attendance/devices/{$device->id}/device-users/100", [
            'device_user_id' => 200,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'device_user_id' => 200,
            ]);

        $this->assertDatabaseHas('attendance_device_users', [
            'device_id' => $device->id,
            'user_id' => $this->waiter->id,
            'device_user_id' => '200',
        ]);
    }

    public function test_update_device_user_id_validates_uniqueness(): void
    {
        $device = $this->createDevice();

        // Add two users
        $device->users()->attach($this->admin->id, [
            'device_user_id' => '100',
            'is_synced' => true,
        ]);
        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '200',
            'is_synced' => true,
        ]);

        // Try to change waiter's ID to admin's ID
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->patchJson("/api/backoffice/attendance/devices/{$device->id}/device-users/200", [
            'device_user_id' => 100,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'duplicate_id',
            ]);
    }

    public function test_can_link_device_user(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$device->id}/link-user", [
            'device_user_id' => '500',
            'user_id' => $this->waiter->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Пользователь связан',
            ]);

        $this->assertDatabaseHas('attendance_device_users', [
            'device_id' => $device->id,
            'user_id' => $this->waiter->id,
            'device_user_id' => '500',
        ]);
    }

    public function test_can_unlink_device_user(): void
    {
        $device = $this->createDevice();

        $device->users()->attach($this->waiter->id, [
            'device_user_id' => '123',
            'is_synced' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/devices/{$device->id}/unlink-user/123");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Связь удалена',
            ]);

        $this->assertDatabaseMissing('attendance_device_users', [
            'device_id' => $device->id,
            'device_user_id' => '123',
        ]);
    }

    // ============================================
    // USER DEVICES ACCESS
    // ============================================

    public function test_can_get_user_devices(): void
    {
        $device1 = $this->createDevice(['name' => 'Device 1']);
        $device2 = $this->createDevice(['name' => 'Device 2']);

        // Grant access to device1 only
        $device1->users()->attach($this->waiter->id, [
            'device_user_id' => '1',
            'is_synced' => true,
            'face_status' => 'enrolled',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/users/{$this->waiter->id}/devices");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'role',
                    ],
                    'devices' => [
                        '*' => [
                            'device' => [
                                'id',
                                'name',
                                'type',
                            ],
                            'access' => [
                                'granted',
                            ],
                        ],
                    ],
                ],
            ]);

        $devices = collect($response->json('data.devices'));

        $deviceWithAccess = $devices->firstWhere('device.id', $device1->id);
        $this->assertTrue($deviceWithAccess['access']['granted']);
        $this->assertEquals('enrolled', $deviceWithAccess['access']['face_status']);

        $deviceWithoutAccess = $devices->firstWhere('device.id', $device2->id);
        $this->assertFalse($deviceWithoutAccess['access']['granted']);
    }

    public function test_get_user_devices_returns_404_for_other_restaurant_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/users/{$this->otherRestaurantAdmin->id}/devices");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'user_not_found',
            ]);
    }

    public function test_can_get_user_biometric_status(): void
    {
        $device1 = $this->createDevice(['name' => 'Device 1']);
        $device2 = $this->createDevice(['name' => 'Device 2']);

        $device1->users()->attach($this->waiter->id, [
            'device_user_id' => '1',
            'is_synced' => true,
            'face_status' => 'enrolled',
        ]);

        $device2->users()->attach($this->waiter->id, [
            'device_user_id' => '2',
            'is_synced' => true,
            'fingerprint_status' => 'enrolled',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/users/{$this->waiter->id}/biometric-status");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'overall_status',
                    'stats' => [
                        'total_devices',
                        'devices_with_access',
                        'devices_synced',
                        'face_enrolled',
                        'fingerprint_enrolled',
                    ],
                    'devices',
                ],
            ]);

        $stats = $response->json('data.stats');
        $this->assertEquals(2, $stats['devices_with_access']);
        $this->assertEquals(1, $stats['face_enrolled']);
        $this->assertEquals(1, $stats['fingerprint_enrolled']);
        $this->assertEquals('enrolled', $response->json('data.overall_status'));
    }

    // ============================================
    // RESTAURANT SETTINGS
    // ============================================

    public function test_can_get_attendance_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/settings');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'attendance_mode' => 'device_or_qr',
                    'attendance_early_minutes' => 30,
                    'attendance_late_minutes' => 120,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'attendance_mode',
                    'attendance_early_minutes',
                    'attendance_late_minutes',
                    'latitude',
                    'longitude',
                    'qr_code',
                    'modes',
                ],
            ]);
    }

    public function test_can_update_attendance_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/settings', [
            'attendance_mode' => 'qr_only',
            'attendance_early_minutes' => 15,
            'attendance_late_minutes' => 60,
            'latitude' => 55.8000,
            'longitude' => 37.7000,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'attendance_mode' => 'qr_only',
            'attendance_early_minutes' => 15,
            'attendance_late_minutes' => 60,
        ]);
    }

    public function test_update_settings_validates_attendance_mode(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/settings', [
            'attendance_mode' => 'invalid_mode',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['attendance_mode']);
    }

    public function test_update_settings_validates_latitude_range(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/settings', [
            'latitude' => 100,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_update_settings_validates_longitude_range(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/settings', [
            'longitude' => 200,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    // ============================================
    // QR SETTINGS
    // ============================================

    public function test_can_update_qr_settings(): void
    {
        // First create a QR code
        AttendanceQrCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'test_code_123',
            'secret' => 'test_secret_456',
            'type' => 'static',
            'require_geolocation' => false,
            'max_distance_meters' => 100,
            'refresh_interval_minutes' => 5,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/qr-settings', [
            'type' => 'dynamic',
            'require_geolocation' => true,
            'max_distance_meters' => 200,
            'refresh_interval_minutes' => 10,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_qr_codes', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'dynamic',
            'require_geolocation' => true,
            'max_distance_meters' => 200,
            'refresh_interval_minutes' => 10,
        ]);
    }

    public function test_update_qr_settings_creates_qr_if_not_exists(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/qr-settings', [
            'type' => 'static',
            'require_geolocation' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendance_qr_codes', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'static',
            'is_active' => true,
        ]);
    }

    public function test_update_qr_settings_validates_type(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/qr-settings', [
            'type' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_update_qr_settings_validates_max_distance_range(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson('/api/backoffice/attendance/qr-settings', [
            'max_distance_meters' => 5000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_distance_meters']);
    }

    // ============================================
    // EVENTS CRUD
    // ============================================

    public function test_can_list_events(): void
    {
        // Create events
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => now()->subHours(2),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_OUT,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/events');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'event_type',
                        'source',
                        'event_time',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'total',
                ],
            ]);
    }

    public function test_can_filter_events_by_user(): void
    {
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/events?user_id={$this->waiter->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->waiter->id, $data[0]['user_id']);
    }

    public function test_can_filter_events_by_date(): void
    {
        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => today()->setTime(10, 0),
        ]);

        AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => today()->subDay()->setTime(10, 0),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/events?date=' . today()->format('Y-m-d'));

        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_event(): void
    {
        $event = AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'verification_method' => AttendanceEvent::METHOD_QR,
            'event_time' => now(),
            'latitude' => 55.7558,
            'longitude' => 37.6173,
            'ip_address' => '192.168.1.1',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/events/{$event->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $event->id,
                    'event_type' => 'clock_in',
                    'source' => 'qr_code',
                    'latitude' => 55.7558,
                    'longitude' => 37.6173,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'event_type',
                    'event_time',
                    'source',
                    'verification_method',
                    'user',
                    'device',
                    'work_session',
                ],
            ]);
    }

    public function test_can_create_manual_event(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/events', [
            'user_id' => $this->waiter->id,
            'event_type' => 'clock_in',
            'event_time' => now()->subHours(2)->toIso8601String(),
            'notes' => 'Ручная корректировка',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'user_id' => $this->waiter->id,
            'event_type' => 'clock_in',
            'source' => AttendanceEvent::SOURCE_MANUAL,
            'verification_method' => AttendanceEvent::METHOD_MANUAL,
        ]);
    }

    public function test_create_event_validates_user_belongs_to_restaurant(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/backoffice/attendance/events', [
            'user_id' => $this->otherRestaurantAdmin->id,
            'event_type' => 'clock_in',
            'event_time' => now()->toIso8601String(),
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'user_not_found',
            ]);
    }

    public function test_can_update_event(): void
    {
        $event = AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_MANUAL,
            'verification_method' => AttendanceEvent::METHOD_MANUAL,
            'event_time' => today()->setTime(10, 0),
        ]);

        $newTime = today()->setTime(9, 30);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/events/{$event->id}", [
            'event_time' => $newTime->toIso8601String(),
            'notes' => 'Корректировка времени',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $event->refresh();
        $this->assertEquals($newTime->format('Y-m-d H:i'), $event->event_time->format('Y-m-d H:i'));
    }

    public function test_can_delete_manual_event(): void
    {
        $event = AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_MANUAL,
            'verification_method' => AttendanceEvent::METHOD_MANUAL,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/events/{$event->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('attendance_events', [
            'id' => $event->id,
        ]);
    }

    public function test_cannot_delete_device_event(): void
    {
        $event = AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_DEVICE,
            'verification_method' => AttendanceEvent::METHOD_FACE,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/events/{$event->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'cannot_delete_device_event',
            ]);

        $this->assertDatabaseHas('attendance_events', [
            'id' => $event->id,
        ]);
    }

    // ============================================
    // RESTAURANT ISOLATION TESTS
    // ============================================

    public function test_cannot_see_other_restaurant_devices(): void
    {
        // Create device in main restaurant
        $myDevice = $this->createDevice([
            'name' => 'My Device',
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create device in other restaurant
        $otherDevice = $this->createDevice([
            'name' => 'Other Device',
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        $deviceIds = array_column($response->json('data'), 'id');
        $this->assertContains($myDevice->id, $deviceIds);
        $this->assertNotContains($otherDevice->id, $deviceIds);
    }

    public function test_cannot_access_other_restaurant_device(): void
    {
        $otherDevice = $this->createDevice([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/devices/{$otherDevice->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'not_found',
            ]);
    }

    public function test_cannot_update_other_restaurant_device(): void
    {
        $otherDevice = $this->createDevice([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->putJson("/api/backoffice/attendance/devices/{$otherDevice->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_other_restaurant_device(): void
    {
        $otherDevice = $this->createDevice([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->deleteJson("/api/backoffice/attendance/devices/{$otherDevice->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('attendance_devices', [
            'id' => $otherDevice->id,
        ]);
    }

    public function test_cannot_regenerate_key_for_other_restaurant_device(): void
    {
        $otherDevice = $this->createDevice([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson("/api/backoffice/attendance/devices/{$otherDevice->id}/regenerate-key");

        $response->assertStatus(404);
    }

    public function test_cannot_see_other_restaurant_events(): void
    {
        // Create event in my restaurant
        $myEvent = AttendanceEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        // Create event in other restaurant
        $otherEvent = AttendanceEvent::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'user_id' => $this->otherRestaurantAdmin->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/events');

        $response->assertOk();

        $eventIds = array_column($response->json('data'), 'id');
        $this->assertContains($myEvent->id, $eventIds);
        $this->assertNotContains($otherEvent->id, $eventIds);
    }

    public function test_cannot_access_other_restaurant_event(): void
    {
        $otherEvent = AttendanceEvent::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'user_id' => $this->otherRestaurantAdmin->id,
            'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
            'source' => AttendanceEvent::SOURCE_QR_CODE,
            'event_time' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/events/{$otherEvent->id}");

        $response->assertStatus(404);
    }

    public function test_settings_are_restaurant_specific(): void
    {
        // Get settings for main restaurant
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/settings');

        $response1->assertOk()
            ->assertJson([
                'data' => [
                    'attendance_mode' => 'device_or_qr',
                ],
            ]);

        // Get settings for other restaurant
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherRestaurantToken}",
        ])->getJson('/api/backoffice/attendance/settings');

        $response2->assertOk()
            ->assertJson([
                'data' => [
                    'attendance_mode' => 'qr_only',
                ],
            ]);
    }

    // ============================================
    // DEVICE TYPE LABELS
    // ============================================

    public function test_device_types_have_correct_labels(): void
    {
        $this->createDevice(['name' => 'Anviz Device', 'type' => 'anviz']);
        $this->createDevice(['name' => 'ZKTeco Device', 'type' => 'zkteco']);
        $this->createDevice(['name' => 'Hikvision Device', 'type' => 'hikvision']);
        $this->createDevice(['name' => 'Generic Device', 'type' => 'generic']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        $data = collect($response->json('data'));

        $this->assertEquals('Anviz', $data->firstWhere('type', 'anviz')['type_label']);
        $this->assertEquals('ZKTeco', $data->firstWhere('type', 'zkteco')['type_label']);
        $this->assertEquals('Hikvision', $data->firstWhere('type', 'hikvision')['type_label']);
        $this->assertEquals('Другое', $data->firstWhere('type', 'generic')['type_label']);
    }

    // ============================================
    // PAGINATION TESTS
    // ============================================

    public function test_events_support_pagination(): void
    {
        // Create many events
        for ($i = 0; $i < 60; $i++) {
            AttendanceEvent::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'event_type' => $i % 2 === 0 ? AttendanceEvent::TYPE_CLOCK_IN : AttendanceEvent::TYPE_CLOCK_OUT,
                'source' => AttendanceEvent::SOURCE_QR_CODE,
                'event_time' => now()->subHours($i),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/events?per_page=20');

        $response->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(3, $response->json('meta.last_page'));
        $this->assertEquals(60, $response->json('meta.total'));
    }

    public function test_events_pagination_page_navigation(): void
    {
        // Create events
        for ($i = 0; $i < 30; $i++) {
            AttendanceEvent::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'event_type' => AttendanceEvent::TYPE_CLOCK_IN,
                'source' => AttendanceEvent::SOURCE_QR_CODE,
                'event_time' => now()->subHours($i),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/events?per_page=10&page=2');

        $response->assertOk();

        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(10, $response->json('data'));
    }

    // ============================================
    // DEVICE MODEL TESTS
    // ============================================

    public function test_device_is_online_check(): void
    {
        $onlineDevice = $this->createDevice([
            'last_heartbeat_at' => now()->subMinutes(3),
        ]);

        $offlineDevice = $this->createDevice([
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->assertTrue($onlineDevice->isOnline());
        $this->assertFalse($offlineDevice->isOnline());
    }

    public function test_device_validates_api_key(): void
    {
        $apiKey = 'test_api_key_12345';
        $device = $this->createDevice(['api_key' => $apiKey]);

        $this->assertTrue($device->validateApiKey($apiKey));
        $this->assertFalse($device->validateApiKey('wrong_key'));
    }

    public function test_device_settings_get_and_set(): void
    {
        $device = $this->createDevice(['settings' => ['mode' => 'face']]);

        $this->assertEquals('face', $device->getSetting('mode'));
        $this->assertEquals('default', $device->getSetting('nonexistent', 'default'));

        $device->setSetting('threshold', 80);
        $device->refresh();

        $this->assertEquals(80, $device->getSetting('threshold'));
        $this->assertEquals('face', $device->getSetting('mode')); // Original setting preserved
    }

    // ============================================
    // API KEY HIDDEN TEST
    // ============================================

    public function test_api_key_is_hidden_in_index_response(): void
    {
        $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/backoffice/attendance/devices');

        $response->assertOk();

        foreach ($response->json('data') as $device) {
            $this->assertArrayNotHasKey('api_key', $device);
        }
    }

    public function test_api_key_is_hidden_in_show_response(): void
    {
        $device = $this->createDevice();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/backoffice/attendance/devices/{$device->id}");

        $response->assertOk();

        $this->assertArrayNotHasKey('api_key', $response->json('data'));
    }
}
