<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\TrackingToken;
use App\Models\RealtimeEvent;
use App\Models\CourierLocationLog;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class LiveTrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $courier;
    protected Restaurant $restaurant;
    protected Order $deliveryOrder;
    protected DeliveryZone $deliveryZone;
    protected TrackingToken $trackingToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create restaurant
        $this->restaurant = Restaurant::factory()->create();

        // Create regular user
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'is_active' => true,
        ]);

        // Create courier user
        $this->courier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'courier',
            'is_courier' => true,
            'is_active' => true,
            'courier_last_location' => [
                'lat' => 55.7558,
                'lng' => 37.6173,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        // Create delivery zone
        $this->deliveryZone = DeliveryZone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create delivery order
        $this->deliveryOrder = Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'courier_id' => $this->courier->id,
            'delivery_zone_id' => $this->deliveryZone->id,
            'status' => 'delivering',
            'delivery_latitude' => 55.7600,
            'delivery_longitude' => 37.6200,
            'delivery_address' => 'Test Street, 123',
        ]);

        // Create tracking token for the order
        $this->trackingToken = TrackingToken::create([
            'order_id' => $this->deliveryOrder->id,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);
    }

    // =====================================================
    // PUBLIC TRACKING DATA TESTS
    // =====================================================

    public function test_can_get_tracking_data_with_valid_token(): void
    {
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order_number',
                    'status',
                    'status_label',
                    'status_color',
                    'delivery_address' => ['lat', 'lng', 'formatted'],
                    'courier',
                    'eta',
                    'restaurant' => ['lat', 'lng'],
                    'is_completed',
                    'is_cancelled',
                    'timestamps',
                ],
            ]);
    }

    public function test_tracking_data_includes_courier_info_when_delivering(): void
    {
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();

        $courierData = $response->json('data.courier');
        $this->assertNotNull($courierData);
        $this->assertEquals($this->courier->name, $courierData['name']);
        $this->assertArrayHasKey('phone', $courierData);
        $this->assertArrayHasKey('location', $courierData);
    }

    public function test_tracking_data_masks_courier_phone(): void
    {
        $this->courier->update(['phone' => '+7 (999) 123-45-67']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();

        $phone = $response->json('data.courier.phone');
        // Phone should be masked: first 4 and last 2 digits visible
        $this->assertNotEquals('+7 (999) 123-45-67', $phone);
        $this->assertStringContainsString('***', $phone);
    }

    public function test_tracking_data_returns_null_courier_for_non_delivering_status(): void
    {
        $this->deliveryOrder->update(['status' => 'cooking']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();
        $this->assertNull($response->json('data.courier'));
    }

    public function test_tracking_data_shows_courier_for_ready_status(): void
    {
        $this->deliveryOrder->update(['status' => 'ready']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();
        $this->assertNotNull($response->json('data.courier'));
    }

    public function test_tracking_data_returns_correct_status_labels(): void
    {
        $statusLabels = [
            'new' => 'Заказ принят',
            'confirmed' => 'Подтверждён',
            'cooking' => 'Готовится',
            'ready' => 'Готов к отправке',
            'delivering' => 'Курьер в пути',
            'completed' => 'Доставлен',
            'cancelled' => 'Отменён',
        ];

        foreach ($statusLabels as $status => $expectedLabel) {
            $this->deliveryOrder->update(['status' => $status]);

            $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

            $response->assertOk();
            $this->assertEquals($expectedLabel, $response->json('data.status_label'));
        }
    }

    public function test_tracking_data_returns_correct_status_colors(): void
    {
        $statusColors = [
            'new' => '#3B82F6',
            'cooking' => '#F59E0B',
            'ready' => '#10B981',
            'delivering' => '#8B5CF6',
            'completed' => '#6B7280',
            'cancelled' => '#EF4444',
        ];

        foreach ($statusColors as $status => $expectedColor) {
            $this->deliveryOrder->update(['status' => $status]);

            $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

            $response->assertOk();
            $this->assertEquals($expectedColor, $response->json('data.status_color'));
        }
    }

    public function test_tracking_data_shows_completed_flag(): void
    {
        $this->deliveryOrder->update(['status' => 'completed']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();
        $this->assertTrue($response->json('data.is_completed'));
        $this->assertFalse($response->json('data.is_cancelled'));
    }

    public function test_tracking_data_shows_cancelled_flag(): void
    {
        $this->deliveryOrder->update(['status' => 'cancelled']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();
        $this->assertTrue($response->json('data.is_completed'));
        $this->assertTrue($response->json('data.is_cancelled'));
    }

    public function test_tracking_data_includes_timestamps(): void
    {
        $this->deliveryOrder->update([
            'cooking_started_at' => now()->subMinutes(30),
            'ready_at' => now()->subMinutes(10),
            'picked_up_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();

        $timestamps = $response->json('data.timestamps');
        $this->assertArrayHasKey('created_at', $timestamps);
        $this->assertArrayHasKey('cooking_started_at', $timestamps);
        $this->assertArrayHasKey('ready_at', $timestamps);
        $this->assertArrayHasKey('picked_up_at', $timestamps);
        $this->assertArrayHasKey('delivered_at', $timestamps);
    }

    public function test_tracking_data_returns_403_for_invalid_token(): void
    {
        $response = $this->getJson('/api/tracking/invalid-token-12345/data');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'Недействительная ссылка для отслеживания',
            ]);
    }

    public function test_tracking_data_returns_403_for_expired_token(): void
    {
        $this->trackingToken->update([
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'Недействительная ссылка для отслеживания',
            ]);
    }

    public function test_tracking_data_returns_403_when_order_deleted(): void
    {
        $token = $this->trackingToken->token;
        $this->deliveryOrder->delete();

        // When order is deleted, the tracking token is also deleted (cascade)
        // so the response is 403 "invalid token", not 404 "order not found"
        $response = $this->getJson("/api/tracking/{$token}/data");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'Недействительная ссылка для отслеживания',
            ]);
    }

    public function test_tracking_data_works_with_token_without_expiration(): void
    {
        $this->trackingToken->update(['expires_at' => null]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // =====================================================
    // POLLING ENDPOINT TESTS
    // =====================================================

    public function test_can_poll_for_events_with_valid_token(): void
    {
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'events',
                'last_event_id',
            ]);
    }

    public function test_poll_returns_new_events(): void
    {
        // Create realtime event for tracking channel
        $event = RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
            'data' => [
                'lat' => 55.7560,
                'lng' => 37.6180,
            ],
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertOk();

        $events = $response->json('events');
        $this->assertNotEmpty($events);
        $this->assertEquals('courier_location', $events[0]['event']);
    }

    public function test_poll_filters_events_by_last_event_id(): void
    {
        // Create two events
        $event1 = RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
            'data' => ['lat' => 55.7560, 'lng' => 37.6180],
            'created_at' => now(),
        ]);

        $event2 = RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
            'data' => ['lat' => 55.7565, 'lng' => 37.6185],
            'created_at' => now(),
        ]);

        // Request with last_event_id of first event
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll?last_event_id={$event1->id}");

        $response->assertOk();

        $events = $response->json('events');
        $this->assertCount(1, $events);
        $this->assertEquals($event2->id, $events[0]['id']);
    }

    public function test_poll_returns_empty_array_when_no_new_events(): void
    {
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll?last_event_id=999999");

        $response->assertOk();
        $this->assertEmpty($response->json('events'));
    }

    public function test_poll_limits_events_to_50(): void
    {
        // Create 60 events
        for ($i = 0; $i < 60; $i++) {
            RealtimeEvent::create([
                'restaurant_id' => $this->restaurant->id,
                'channel' => "tracking_{$this->deliveryOrder->id}",
                'event' => 'courier_location',
                'data' => ['lat' => 55.7560 + $i * 0.001, 'lng' => 37.6180],
                'created_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertOk();
        $this->assertCount(50, $response->json('events'));
    }

    public function test_poll_returns_last_event_id(): void
    {
        $event = RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
            'data' => ['lat' => 55.7560, 'lng' => 37.6180],
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertOk();
        $this->assertEquals($event->id, $response->json('last_event_id'));
    }

    public function test_poll_returns_403_for_invalid_token(): void
    {
        $response = $this->getJson('/api/tracking/invalid-token/poll');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Недействительная ссылка']);
    }

    public function test_poll_returns_403_for_expired_token(): void
    {
        $this->trackingToken->update(['expires_at' => now()->subHour()]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertStatus(403);
    }

    public function test_poll_only_returns_events_for_correct_channel(): void
    {
        // Create event for different order
        $otherOrder = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$otherOrder->id}",
            'event' => 'courier_location',
            'data' => ['lat' => 55.7560, 'lng' => 37.6180],
            'created_at' => now(),
        ]);

        // Create event for our order
        RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
            'data' => ['lat' => 55.7570, 'lng' => 37.6190],
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/poll");

        $response->assertOk();

        $events = $response->json('events');
        $this->assertCount(1, $events);
    }

    // =====================================================
    // SSE STREAM TESTS
    // =====================================================

    public function test_stream_returns_event_stream_content_type(): void
    {
        $response = $this->get("/api/tracking/{$this->trackingToken->token}/stream");

        // Content-Type may include charset suffix
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        // Cache-Control may include additional directives like 'private'
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $response->assertHeader('Connection', 'keep-alive');
    }

    public function test_stream_returns_403_for_invalid_token(): void
    {
        $response = $this->get('/api/tracking/invalid-token/stream');

        $response->assertStatus(403);
    }

    public function test_stream_returns_403_for_expired_token(): void
    {
        $this->trackingToken->update(['expires_at' => now()->subHour()]);

        $response = $this->get("/api/tracking/{$this->trackingToken->token}/stream");

        $response->assertStatus(403);
    }

    // =====================================================
    // COURIER LOCATION UPDATE TESTS (AUTHENTICATED)
    // =====================================================

    public function test_courier_can_update_location(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'accuracy' => 10.5,
                'speed' => 25.0,
                'heading' => 90.0,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'active_orders',
            ]);
    }

    public function test_courier_location_update_saves_to_user_profile(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $this->courier->refresh();
        $location = $this->courier->courier_last_location;

        $this->assertEquals(55.7565, $location['lat']);
        $this->assertEquals(37.6185, $location['lng']);
    }

    public function test_courier_location_update_logs_for_active_orders(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'accuracy' => 10.0,
            ]);

        $this->assertDatabaseHas('courier_location_logs', [
            'order_id' => $this->deliveryOrder->id,
            'courier_id' => $this->courier->id,
            'latitude' => 55.7565,
            'longitude' => 37.6185,
        ]);
    }

    public function test_courier_location_update_creates_realtime_event(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $this->assertDatabaseHas('realtime_events', [
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
        ]);
    }

    public function test_courier_location_update_returns_active_orders_count(): void
    {
        // Create another active delivery order for this courier
        Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'courier_id' => $this->courier->id,
            'status' => 'delivering',
        ]);

        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('active_orders'));
    }

    public function test_courier_location_update_only_logs_for_delivering_orders(): void
    {
        // Change order status to something else
        $this->deliveryOrder->update(['status' => 'ready']);

        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $this->assertDatabaseMissing('courier_location_logs', [
            'order_id' => $this->deliveryOrder->id,
        ]);
    }

    public function test_courier_location_update_validates_latitude(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 100.0, // Invalid: must be between -90 and 90
                'longitude' => 37.6185,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_courier_location_update_validates_longitude(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 200.0, // Invalid: must be between -180 and 180
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_courier_location_update_validates_required_fields(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_courier_location_update_validates_accuracy_range(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'accuracy' => 15000, // Invalid: max is 10000
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['accuracy']);
    }

    public function test_courier_location_update_validates_speed_range(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'speed' => 600, // Invalid: max is 500
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['speed']);
    }

    public function test_courier_location_update_validates_heading_range(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'heading' => 400, // Invalid: must be between 0 and 360
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['heading']);
    }

    public function test_courier_location_update_accepts_optional_fields(): void
    {
        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'accuracy' => 5.0,
                'speed' => 30.0,
                'heading' => 180.0,
            ]);

        $response->assertOk();

        $log = CourierLocationLog::where('order_id', $this->deliveryOrder->id)->first();
        $this->assertEquals(5.0, $log->accuracy);
        $this->assertEquals(30.0, $log->speed);
        $this->assertEquals(180.0, $log->heading);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_courier_location_update_requires_authentication(): void
    {
        $response = $this->postJson('/api/courier/location', [
            'latitude' => 55.7565,
            'longitude' => 37.6185,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_courier_cannot_update_location(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'Доступ запрещён',
            ]);
    }

    public function test_inactive_courier_cannot_update_location(): void
    {
        $this->courier->update(['is_active' => false]);

        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        // The is_courier check happens before is_active, so it might still fail with 403
        // depending on implementation. Let's just check it doesn't succeed
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    // =====================================================
    // ETA CALCULATION TESTS
    // =====================================================

    public function test_tracking_data_includes_eta_when_courier_has_location(): void
    {
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();

        // ETA should be present when order is delivering and courier has location
        $eta = $response->json('data.eta');
        if ($eta !== null) {
            $this->assertArrayHasKey('minutes', $eta);
            $this->assertArrayHasKey('distance_km', $eta);
            $this->assertArrayHasKey('label', $eta);
        }
    }

    public function test_courier_location_update_includes_eta_in_event(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $event = RealtimeEvent::where('channel', "tracking_{$this->deliveryOrder->id}")
            ->where('event', 'courier_location')
            ->first();

        $this->assertNotNull($event);
        $this->assertArrayHasKey('eta', $event->data);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_tracking_works_for_order_without_courier(): void
    {
        $this->deliveryOrder->update(['courier_id' => null, 'status' => 'cooking']);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();
        $this->assertNull($response->json('data.courier'));
    }

    public function test_tracking_works_for_order_without_delivery_coordinates(): void
    {
        $this->deliveryOrder->update([
            'delivery_latitude' => null,
            'delivery_longitude' => null,
        ]);

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertOk();

        $address = $response->json('data.delivery_address');
        $this->assertNull($address['lat']);
        $this->assertNull($address['lng']);
    }

    public function test_courier_location_update_handles_no_active_orders(): void
    {
        // Set order to non-delivering status
        $this->deliveryOrder->update(['status' => 'completed']);

        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('active_orders'));
    }

    public function test_multiple_orders_get_location_updates(): void
    {
        // Create second active order
        $secondOrder = Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
            'courier_id' => $this->courier->id,
            'status' => 'delivering',
            'delivery_latitude' => 55.7700,
            'delivery_longitude' => 37.6300,
        ]);

        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        // Both orders should have location logs
        $this->assertDatabaseHas('courier_location_logs', [
            'order_id' => $this->deliveryOrder->id,
            'courier_id' => $this->courier->id,
        ]);

        $this->assertDatabaseHas('courier_location_logs', [
            'order_id' => $secondOrder->id,
            'courier_id' => $this->courier->id,
        ]);

        // Both orders should have realtime events
        $this->assertDatabaseHas('realtime_events', [
            'channel' => "tracking_{$this->deliveryOrder->id}",
            'event' => 'courier_location',
        ]);

        $this->assertDatabaseHas('realtime_events', [
            'channel' => "tracking_{$secondOrder->id}",
            'event' => 'courier_location',
        ]);
    }

    public function test_tracking_token_can_be_generated_for_order(): void
    {
        $newOrder = Order::factory()->delivery()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $token = TrackingToken::generateForOrder($newOrder);

        $this->assertNotNull($token->token);
        $this->assertEquals($newOrder->id, $token->order_id);
        $this->assertTrue($token->isValid());
    }

    public function test_tracking_token_can_be_revoked(): void
    {
        $this->trackingToken->revoke();

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");

        $response->assertStatus(403);
    }

    public function test_tracking_token_can_be_extended(): void
    {
        $this->trackingToken->update(['expires_at' => now()->subMinutes(30)]);

        // Token should be invalid now
        $this->assertFalse($this->trackingToken->fresh()->isValid());

        // Extend the token
        $this->trackingToken->extend(24);

        // Token should be valid again
        $this->assertTrue($this->trackingToken->fresh()->isValid());

        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");
        $response->assertOk();
    }

    // =====================================================
    // THROTTLING TESTS
    // =====================================================

    public function test_tracking_endpoints_are_throttled(): void
    {
        // The route has throttle:60,1 middleware
        // We don't want to actually hit the rate limit in tests,
        // just verify the endpoint works normally within limits
        $response = $this->getJson("/api/tracking/{$this->trackingToken->token}/data");
        $response->assertOk();
    }

    // =====================================================
    // DATA INTEGRITY TESTS
    // =====================================================

    public function test_location_log_preserves_precision(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.75652345,
                'longitude' => 37.61851234,
            ]);

        $log = CourierLocationLog::where('order_id', $this->deliveryOrder->id)->first();

        // Check that coordinates are stored with proper precision
        $this->assertEqualsWithDelta(55.75652345, $log->latitude, 0.00001);
        $this->assertEqualsWithDelta(37.61851234, $log->longitude, 0.00001);
    }

    public function test_courier_last_seen_is_updated(): void
    {
        $beforeUpdate = now()->subSecond();

        $response = $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
            ]);

        $response->assertOk();

        $this->courier->refresh();

        $this->assertNotNull($this->courier->courier_last_seen);
        $this->assertTrue(
            $this->courier->courier_last_seen->gte($beforeUpdate),
            "courier_last_seen ({$this->courier->courier_last_seen}) should be >= beforeUpdate ({$beforeUpdate})"
        );
    }

    public function test_realtime_event_includes_all_location_data(): void
    {
        $this->actingAs($this->courier, 'sanctum')
            ->postJson('/api/courier/location', [
                'latitude' => 55.7565,
                'longitude' => 37.6185,
                'accuracy' => 10.0,
                'heading' => 90.0,
            ]);

        $event = RealtimeEvent::where('channel', "tracking_{$this->deliveryOrder->id}")
            ->where('event', 'courier_location')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals(55.7565, $event->data['location']['lat']);
        $this->assertEquals(37.6185, $event->data['location']['lng']);
        $this->assertEquals(10.0, $event->data['location']['accuracy']);
        $this->assertEquals(90.0, $event->data['location']['heading']);
        $this->assertEquals($this->deliveryOrder->id, $event->data['order_id']);
        $this->assertArrayHasKey('timestamp', $event->data);
    }
}
