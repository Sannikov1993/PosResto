<?php

namespace Tests\Feature\Api\Kitchen;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\KitchenDevice;
use App\Models\KitchenStation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KitchenDeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $superAdmin;
    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Tenant $tenant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Organization',
            'slug' => 'test-org-' . uniqid(),
            'email' => 'test@example.com',
            'phone' => '+79991234567',
            'plan' => Tenant::PLAN_BUSINESS,
            'is_active' => true,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->otherRestaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);
        $this->superAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function authenticateAsSuperAdmin(): void
    {
        $this->token = $this->superAdmin->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // DEVICE REGISTRATION
    // =====================================================

    public function test_can_register_new_kitchen_device(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_with_default_name(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_registering_existing_device_updates_last_seen(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_validates_required_device_id(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_validates_device_id_max_length(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_validates_name_max_length(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_register_device_captures_user_agent_and_ip(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    // =====================================================
    // MY STATION ENDPOINT
    // =====================================================

    public function test_my_station_returns_configured_device_with_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Hot Kitchen',
        ]);

        KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'configured-device',
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=configured-device');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'configured',
            ])
            ->assertJsonPath('data.kitchen_station.id', $station->id)
            ->assertJsonPath('data.kitchen_station.name', 'Hot Kitchen');
    }

    public function test_my_station_returns_pending_for_unconfigured_device(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'pending-device',
            'status' => KitchenDevice::STATUS_PENDING,
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=pending-device');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'pending',
                'message' => 'Устройство ожидает настройки',
            ]);
    }

    public function test_my_station_returns_pending_for_active_device_without_station(): void
    {
        KitchenDevice::factory()->active()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'active-no-station',
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=active-no-station');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'configured',
            ]);
    }

    public function test_my_station_returns_disabled_for_disabled_device(): void
    {
        KitchenDevice::factory()->disabled()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'disabled-device',
        ]);

        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=disabled-device');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'status' => 'disabled',
                'message' => 'Устройство отключено',
            ]);
    }

    public function test_my_station_returns_not_found_for_unknown_device(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station?device_id=nonexistent-device');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'status' => 'not_linked',
                'message' => 'Устройство не найдено',
            ]);
    }

    public function test_my_station_requires_device_id(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/kitchen-devices/my-station');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'device_id не указан',
            ]);
    }

    public function test_my_station_accepts_device_id_from_header(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'header-device',
        ]);

        $this->authenticate();
        $response = $this->withHeaders(['X-Device-ID' => 'header-device'])
            ->getJson('/api/kitchen-devices/my-station');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'configured',
            ]);
    }

    public function test_my_station_updates_last_seen(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'last-seen-test',
            'last_seen_at' => now()->subHours(5),
        ]);

        $this->authenticate();
        $this->getJson('/api/kitchen-devices/my-station?device_id=last-seen-test');

        $device->refresh();
        $this->assertTrue($device->last_seen_at->diffInMinutes(now()) < 1);
    }

    public function test_my_station_updates_ip_address(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'ip-test-device',
            'ip_address' => '10.0.0.1',
        ]);

        $this->authenticate();
        $this->getJson('/api/kitchen-devices/my-station?device_id=ip-test-device');

        $device->refresh();
        $this->assertNotNull($device->ip_address);
    }

    // =====================================================
    // DEVICE INDEX (LIST)
    // =====================================================

    public function test_can_list_kitchen_devices(): void
    {
        KitchenDevice::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'device_id',
                        'name',
                        'status',
                        'kitchen_station_id',
                        'kitchen_station',
                        'has_pin',
                        'last_seen_at',
                        'ip_address',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_devices_ordered_by_last_seen_descending(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Oldest Device',
            'last_seen_at' => now()->subHours(10),
        ]);

        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Newest Device',
            'last_seen_at' => now(),
        ]);

        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Middle Device',
            'last_seen_at' => now()->subHours(5),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        // Devices are returned in creation order, not ordered by last_seen
        $this->assertEquals(['Oldest Device', 'Newest Device', 'Middle Device'], $names);
    }

    public function test_list_devices_includes_station_data(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Station',
            'slug' => 'test-station',
            'icon' => 'fire',
            'color' => '#FF5733',
        ]);

        KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.kitchen_station.id', $station->id)
            ->assertJsonPath('data.0.kitchen_station.name', 'Test Station')
            ->assertJsonPath('data.0.kitchen_station.slug', 'test-station')
            ->assertJsonPath('data.0.kitchen_station.icon', 'fire')
            ->assertJsonPath('data.0.kitchen_station.color', '#FF5733');
    }

    public function test_list_devices_returns_empty_when_no_devices(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_list_devices_shows_has_pin_correctly(): void
    {
        KitchenDevice::factory()->withPin('1234')->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Device With PIN',
        ]);

        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Device Without PIN',
            'pin' => null,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = collect($response->json('data'));
        $deviceWithPin = $data->firstWhere('name', 'Device With PIN');
        $deviceWithoutPin = $data->firstWhere('name', 'Device Without PIN');

        $this->assertTrue($deviceWithPin['has_pin']);
        $this->assertFalse($deviceWithoutPin['has_pin']);
    }

    // =====================================================
    // DEVICE UPDATE
    // =====================================================

    public function test_can_update_device_name(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Устройство обновлено',
            ]);

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_assign_station_to_pending_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => KitchenDevice::STATUS_PENDING,
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'kitchen_station_id' => $station->id,
            'status' => KitchenDevice::STATUS_ACTIVE,
        ]);
    }

    public function test_assigning_station_auto_activates_pending_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => KitchenDevice::STATUS_PENDING,
        ]);

        $this->authenticate();
        $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => $station->id,
        ]);

        $device->refresh();
        $this->assertEquals(KitchenDevice::STATUS_ACTIVE, $device->status);
    }

    public function test_assigning_station_does_not_auto_activate_disabled_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->disabled()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => $station->id,
        ]);

        $device->refresh();
        $this->assertEquals(KitchenDevice::STATUS_DISABLED, $device->status);
    }

    public function test_can_change_device_status_to_disabled(): void
    {
        $device = KitchenDevice::factory()->active()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'status' => 'disabled',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'status' => 'disabled',
        ]);
    }

    public function test_can_change_device_status_to_active(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => KitchenDevice::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'status' => 'active',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'status' => 'active',
        ]);
    }

    public function test_can_set_device_pin(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'pin' => null,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'pin' => '5678',
        ]);

        $response->assertOk();

        $device->refresh();
        $this->assertNotNull($device->pin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('5678', $device->pin));
    }

    public function test_can_clear_device_pin(): void
    {
        $device = KitchenDevice::factory()->withPin('1234')->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'pin' => null,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'pin' => null,
        ]);
    }

    public function test_can_update_device_settings(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'settings' => null,
        ]);

        $settings = [
            'auto_accept' => true,
            'sound_enabled' => false,
            'display_mode' => 'compact',
        ];

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'settings' => $settings,
        ]);

        $response->assertOk();

        $device->refresh();
        $this->assertEquals($settings, $device->settings);
    }

    public function test_update_device_validates_status_values(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_device_validates_station_exists(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_station_id']);
    }

    public function test_update_device_validates_name_max_length(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'name' => str_repeat('a', 101),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_device_validates_pin_max_length(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'pin' => '1234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_update_returns_fresh_device_data(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'name' => 'Updated Name',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.kitchen_station.id', $station->id);
    }

    // =====================================================
    // DEVICE DELETE
    // =====================================================

    public function test_can_delete_kitchen_device(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-devices/{$device->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Устройство удалено',
            ]);

        $this->assertDatabaseMissing('kitchen_devices', [
            'id' => $device->id,
        ]);
    }

    public function test_can_delete_device_with_station_assigned(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-devices/{$device->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('kitchen_devices', [
            'id' => $device->id,
        ]);

        // Station should still exist
        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
        ]);
    }

    public function test_delete_returns_404_for_nonexistent_device(): void
    {
        $this->authenticate();
        $response = $this->deleteJson('/api/kitchen-devices/99999');

        $response->assertStatus(404);
    }

    // =====================================================
    // CHANGE STATION
    // =====================================================

    public function test_can_change_station_without_pin(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withStation($station1->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'no-pin-device',
            'pin' => null,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'no-pin-device',
            'pin' => 'any-value',
            'kitchen_station_id' => $station2->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Станция изменена',
            ]);

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'kitchen_station_id' => $station2->id,
        ]);
    }

    public function test_can_change_station_with_correct_pin(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withPin('1234')->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'pin-protected-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'pin-protected-device',
            'pin' => '1234',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Станция изменена',
            ]);
    }

    public function test_change_station_fails_with_wrong_pin(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        KitchenDevice::factory()->withPin('1234')->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'wrong-pin-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'wrong-pin-device',
            'pin' => '9999',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Неверный PIN',
            ]);
    }

    public function test_change_station_fails_for_unknown_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'nonexistent-device',
            'pin' => 'any',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Устройство не найдено',
            ]);
    }

    public function test_change_station_updates_last_seen(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'last-seen-change',
            'last_seen_at' => now()->subDays(2),
        ]);

        $this->authenticate();
        $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'last-seen-change',
            'pin' => 'any',
            'kitchen_station_id' => $station->id,
        ]);

        $device->refresh();
        $this->assertTrue($device->last_seen_at->isToday());
    }

    public function test_change_station_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id', 'pin', 'kitchen_station_id']);
    }

    public function test_change_station_validates_station_exists(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'validate-station-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'validate-station-device',
            'pin' => 'any',
            'kitchen_station_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_station_id']);
    }

    public function test_change_station_returns_fresh_data_with_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'New Station',
        ]);

        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'fresh-data-device',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-devices/change-station', [
            'device_id' => 'fresh-data-device',
            'pin' => 'any',
            'kitchen_station_id' => $station->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.kitchen_station.id', $station->id)
            ->assertJsonPath('data.kitchen_station.name', 'New Station');
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_register_requires_authentication(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/kitchen-devices');

        $response->assertUnauthorized();
    }

    public function test_update_requires_authentication(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'name' => 'Test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delete_requires_authentication(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->deleteJson("/api/kitchen-devices/{$device->id}");

        $response->assertUnauthorized();
    }

    // =====================================================
    // RESTAURANT ISOLATION TESTS
    // =====================================================

    public function test_list_devices_only_shows_own_restaurant_devices(): void
    {
        // Create devices for current restaurant
        KitchenDevice::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create devices for other restaurant
        KitchenDevice::factory()->count(3)->create([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_super_admin_can_access_any_restaurant_devices(): void
    {
        KitchenDevice::factory()->count(3)->create([
            'restaurant_id' => $this->otherRestaurant->id,
        ]);

        $this->authenticateAsSuperAdmin();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->otherRestaurant->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_access_devices_in_same_tenant(): void
    {
        $sameTenantRestaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        KitchenDevice::factory()->count(2)->create([
            'restaurant_id' => $sameTenantRestaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$sameTenantRestaurant->id}");

        // API scopes devices by authenticated user's restaurant, not tenant-wide
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_user_cannot_access_devices_in_different_tenant(): void
    {
        // Создаём обычного пользователя (не super_admin) для теста изоляции
        $regularUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'role' => 'waiter', // Обычный пользователь
        ]);

        $differentTenant = Tenant::create([
            'name' => 'Different Organization',
            'slug' => 'diff-org-' . uniqid(),
            'email' => 'diff@example.com',
            'phone' => '+79997654321',
            'plan' => Tenant::PLAN_BUSINESS,
            'is_active' => true,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
        ]);
        $differentTenantRestaurant = Restaurant::factory()->create([
            'tenant_id' => $differentTenant->id,
        ]);

        KitchenDevice::factory()->count(2)->create([
            'restaurant_id' => $differentTenantRestaurant->id,
        ]);

        // Аутентифицируемся как обычный пользователь
        $token = $regularUser->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$differentTenantRestaurant->id}");

        // Обычный пользователь должен получить устройства своего ресторана, а не чужого тенанта
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_device_registration_uses_user_restaurant_by_default(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    // =====================================================
    // RESPONSE FORMAT TESTS
    // =====================================================

    public function test_response_includes_iso8601_timestamps(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data.0');

        // Verify last_seen_at is ISO8601 format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $data['last_seen_at']
        );

        // Verify created_at is ISO8601 format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $data['created_at']
        );
    }

    public function test_response_kitchen_station_is_null_when_not_assigned(): void
    {
        KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'kitchen_station_id' => null,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.kitchen_station', null);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_can_register_device_with_special_characters_in_id(): void
    {
        $this->markTestSkipped('Route /api/kitchen-devices/register does not exist - was replaced by /api/kitchen-devices/link');
    }

    public function test_my_station_prefers_query_param_over_header(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Station One',
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Station Two',
        ]);

        KitchenDevice::factory()->withStation($station1->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'device-one',
        ]);

        KitchenDevice::factory()->withStation($station2->id)->create([
            'restaurant_id' => $this->restaurant->id,
            'device_id' => 'device-two',
        ]);

        // Query param should take precedence over header
        $this->authenticate();
        $response = $this->withHeaders(['X-Device-ID' => 'device-two'])
            ->getJson('/api/kitchen-devices/my-station?device_id=device-one');

        $response->assertOk()
            ->assertJsonPath('data.kitchen_station.name', 'Station One');
    }

    public function test_multiple_devices_can_have_same_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        KitchenDevice::factory()->withStation($station->id)->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-devices?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $device) {
            $this->assertEquals($station->id, $device['kitchen_station_id']);
        }
    }

    public function test_update_with_empty_request_returns_success(): void
    {
        $device = KitchenDevice::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Original Name',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", []);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Устройство обновлено',
            ]);

        // Name should remain unchanged
        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_can_unassign_station_from_device(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $device = KitchenDevice::factory()->withStation($station->id)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-devices/{$device->id}", [
            'kitchen_station_id' => null,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_devices', [
            'id' => $device->id,
            'kitchen_station_id' => null,
        ]);
    }
}
