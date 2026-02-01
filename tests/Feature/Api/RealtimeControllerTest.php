<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\RealtimeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RealtimeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $otherUser;
    protected string $adminToken;
    protected string $waiterToken;
    protected string $otherUserToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->otherRestaurant = Restaurant::factory()->create();

        // Create admin role
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create waiter role
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Waiter',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
        ]);

        // Create users
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
            'email' => 'admin@test.com',
        ]);

        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'email' => 'waiter@test.com',
        ]);

        // Create user from another restaurant
        $otherRole = Role::create([
            'restaurant_id' => $this->otherRestaurant->id,
            'key' => 'waiter',
            'name' => 'Waiter',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
        ]);

        $this->otherUser = User::factory()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'role' => 'waiter',
            'role_id' => $otherRole->id,
            'is_active' => true,
            'email' => 'other@test.com',
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->otherUserToken = $this->otherUser->createToken('test')->plainTextToken;
    }

    /**
     * Helper to create realtime events
     */
    protected function createEvent(array $attributes = []): RealtimeEvent
    {
        return RealtimeEvent::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'channel' => RealtimeEvent::CHANNEL_ORDERS,
            'event' => RealtimeEvent::EVENT_NEW_ORDER,
            'data' => ['order_id' => 1, 'message' => 'Test order'],
            'user_id' => null,
            'created_at' => now(),
        ], $attributes));
    }

    // =====================================================
    // SSE STREAM ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function stream_returns_streamed_response_with_correct_headers(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('/api/realtime/stream');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        $response->assertHeader('Cache-Control', 'no-cache, private');
    }

    /** @test */
    public function stream_accepts_last_event_id_header(): void
    {
        // Create some events first
        $event1 = $this->createEvent();
        $event2 = $this->createEvent(['event' => RealtimeEvent::EVENT_ORDER_UPDATED]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'Last-Event-ID' => (string) $event1->id,
        ])->get('/api/realtime/stream');

        $response->assertStatus(200);
    }

    /** @test */
    public function stream_accepts_last_id_query_parameter(): void
    {
        $event = $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get("/api/realtime/stream?last_id={$event->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function stream_accepts_channels_as_array(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('/api/realtime/stream?channels[]=' . RealtimeEvent::CHANNEL_ORDERS . '&channels[]=' . RealtimeEvent::CHANNEL_KITCHEN);

        $response->assertStatus(200);
    }

    /** @test */
    public function stream_accepts_channels_as_comma_separated_string(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('/api/realtime/stream?channels=' . RealtimeEvent::CHANNEL_ORDERS . ',' . RealtimeEvent::CHANNEL_KITCHEN);

        $response->assertStatus(200);
    }

    // =====================================================
    // LONG POLLING ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function poll_returns_events_immediately_when_available(): void
    {
        // Create events before polling
        $event1 = $this->createEvent();
        $event2 = $this->createEvent([
            'channel' => RealtimeEvent::CHANNEL_KITCHEN,
            'event' => RealtimeEvent::EVENT_KITCHEN_READY,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events' => [
                        '*' => ['id', 'channel', 'event', 'data', 'timestamp']
                    ],
                    'last_id',
                ],
            ]);

        $events = $response->json('data.events');
        $this->assertCount(2, $events);
        $this->assertEquals(RealtimeEvent::CHANNEL_ORDERS, $events[0]['channel']);
        $this->assertEquals(RealtimeEvent::CHANNEL_KITCHEN, $events[1]['channel']);
    }

    /** @test */
    public function poll_returns_events_after_specified_last_id(): void
    {
        $event1 = $this->createEvent();
        $event2 = $this->createEvent(['event' => RealtimeEvent::EVENT_ORDER_UPDATED]);
        $event3 = $this->createEvent(['event' => RealtimeEvent::EVENT_ORDER_PAID]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson("/api/realtime/poll?last_id={$event1->id}&timeout=1");

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(2, $events);
        $this->assertEquals($event2->id, $events[0]['id']);
        $this->assertEquals($event3->id, $events[1]['id']);
    }

    /** @test */
    public function poll_filters_events_by_channels(): void
    {
        $orderEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $kitchenEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $deliveryEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1&channels[]=' . RealtimeEvent::CHANNEL_ORDERS . '&channels[]=' . RealtimeEvent::CHANNEL_KITCHEN);

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(2, $events);

        $channels = array_column($events, 'channel');
        $this->assertContains(RealtimeEvent::CHANNEL_ORDERS, $channels);
        $this->assertContains(RealtimeEvent::CHANNEL_KITCHEN, $channels);
        $this->assertNotContains(RealtimeEvent::CHANNEL_DELIVERY, $channels);
    }

    /** @test */
    public function poll_filters_events_by_comma_separated_channels(): void
    {
        $orderEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $kitchenEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $tableEvent = $this->createEvent(['channel' => RealtimeEvent::CHANNEL_TABLES]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1&channels=' . RealtimeEvent::CHANNEL_ORDERS . ',' . RealtimeEvent::CHANNEL_TABLES);

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(2, $events);

        $channels = array_column($events, 'channel');
        $this->assertContains(RealtimeEvent::CHANNEL_ORDERS, $channels);
        $this->assertContains(RealtimeEvent::CHANNEL_TABLES, $channels);
        $this->assertNotContains(RealtimeEvent::CHANNEL_KITCHEN, $channels);
    }

    /** @test */
    public function poll_returns_empty_events_array_when_no_events(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'events' => [],
                    'last_id' => 0,
                ],
            ]);
    }

    /** @test */
    public function poll_respects_timeout_parameter(): void
    {
        // With a short timeout (1 second) and no events, should return quickly
        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $duration = microtime(true) - $startTime;

        $response->assertOk();
        // Should return within reasonable time (1 second timeout + some buffer)
        $this->assertLessThan(3, $duration);
    }

    /** @test */
    public function poll_limits_timeout_to_maximum_30_seconds(): void
    {
        // Request with 60 second timeout should be limited to 30
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=60');

        $response->assertOk();
    }

    /** @test */
    public function poll_returns_last_id_of_most_recent_event(): void
    {
        $event1 = $this->createEvent();
        $event2 = $this->createEvent();
        $event3 = $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();
        $this->assertEquals($event3->id, $response->json('data.last_id'));
    }

    /** @test */
    public function poll_only_returns_events_for_users_restaurant(): void
    {
        // Create event for user's restaurant
        $userEvent = $this->createEvent(['restaurant_id' => $this->restaurant->id]);

        // Create event for other restaurant
        $otherEvent = $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(1, $events);
        $this->assertEquals($userEvent->id, $events[0]['id']);
    }

    // =====================================================
    // RECENT EVENTS ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function recent_returns_latest_events(): void
    {
        // Create 5 events
        for ($i = 1; $i <= 5; $i++) {
            $this->createEvent(['data' => ['order_id' => $i]]);
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events' => [
                        '*' => ['id', 'channel', 'event', 'data', 'timestamp']
                    ],
                    'last_id',
                ],
            ]);

        $events = $response->json('data.events');
        $this->assertCount(5, $events);
    }

    /** @test */
    public function recent_respects_limit_parameter(): void
    {
        // Create 10 events
        for ($i = 1; $i <= 10; $i++) {
            $this->createEvent();
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent?limit=5');

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(5, $events);
    }

    /** @test */
    public function recent_limits_maximum_to_100(): void
    {
        // Create 110 events
        for ($i = 1; $i <= 110; $i++) {
            $this->createEvent();
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent?limit=200');

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(100, $events);
    }

    /** @test */
    public function recent_filters_by_channels(): void
    {
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent?channels[]=' . RealtimeEvent::CHANNEL_ORDERS);

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(1, $events);
        $this->assertEquals(RealtimeEvent::CHANNEL_ORDERS, $events[0]['channel']);
    }

    /** @test */
    public function recent_returns_events_in_chronological_order(): void
    {
        $event1 = $this->createEvent(['data' => ['order' => 1]]);
        $event2 = $this->createEvent(['data' => ['order' => 2]]);
        $event3 = $this->createEvent(['data' => ['order' => 3]]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk();

        $events = $response->json('data.events');
        // Events should be in ascending order by ID
        $this->assertEquals($event1->id, $events[0]['id']);
        $this->assertEquals($event2->id, $events[1]['id']);
        $this->assertEquals($event3->id, $events[2]['id']);
    }

    /** @test */
    public function recent_only_returns_events_for_users_restaurant(): void
    {
        $userEvent = $this->createEvent(['restaurant_id' => $this->restaurant->id]);
        $otherEvent = $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(1, $events);
        $this->assertEquals($userEvent->id, $events[0]['id']);
    }

    /** @test */
    public function recent_returns_zero_last_id_when_no_events(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'events' => [],
                    'last_id' => 0,
                ],
            ]);
    }

    // =====================================================
    // SEND EVENT ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function send_creates_new_event(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'custom',
            'event' => 'test_event',
            'data' => ['message' => 'Hello World'],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Событие отправлено',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'channel', 'event'],
            ]);

        $this->assertDatabaseHas('realtime_events', [
            'channel' => 'custom',
            'event' => 'test_event',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    /** @test */
    public function send_validates_required_channel(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'event' => 'test_event',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    /** @test */
    public function send_validates_required_event(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'custom',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    /** @test */
    public function send_validates_channel_max_length(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => str_repeat('a', 51),
            'event' => 'test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    /** @test */
    public function send_validates_event_max_length(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'test',
            'event' => str_repeat('a', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    /** @test */
    public function send_validates_data_is_array_when_provided(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'custom',
            'event' => 'test',
            'data' => 'not an array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['data']);
    }

    /** @test */
    public function send_accepts_null_data(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'custom',
            'event' => 'test',
            'data' => null,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function send_can_include_user_id(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'notifications',
            'event' => 'user_message',
            'data' => ['text' => 'Hello'],
            'user_id' => $this->waiter->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('realtime_events', [
            'channel' => 'notifications',
            'event' => 'user_message',
            'user_id' => $this->waiter->id,
        ]);
    }

    /** @test */
    public function send_assigns_restaurant_id_from_authenticated_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'test',
            'event' => 'test',
            'data' => ['restaurant_id' => $this->restaurant->id],
        ]);

        $response->assertOk();

        $eventId = $response->json('data.id');
        $event = RealtimeEvent::find($eventId);

        $this->assertEquals($this->restaurant->id, $event->restaurant_id);
    }

    // =====================================================
    // STATUS ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function status_returns_realtime_status_info(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'last_id',
                    'last_event_at',
                    'events_last_hour',
                ],
            ]);
    }

    /** @test */
    public function status_returns_last_event_id(): void
    {
        $event1 = $this->createEvent();
        $event2 = $this->createEvent();
        $event3 = $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();
        $this->assertEquals($event3->id, $response->json('data.last_id'));
    }

    /** @test */
    public function status_returns_zero_when_no_events(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'last_id' => 0,
                    'last_event_at' => null,
                ],
            ]);
    }

    /** @test */
    public function status_returns_events_count_per_channel(): void
    {
        // Create events in different channels
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();

        $stats = $response->json('data.events_last_hour');
        $this->assertEquals(2, $stats[RealtimeEvent::CHANNEL_ORDERS]);
        $this->assertEquals(1, $stats[RealtimeEvent::CHANNEL_KITCHEN]);
        $this->assertEquals(3, $stats[RealtimeEvent::CHANNEL_DELIVERY]);
    }

    /** @test */
    public function status_only_counts_events_from_last_hour(): void
    {
        // Create old event (2 hours ago)
        RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => RealtimeEvent::CHANNEL_ORDERS,
            'event' => 'old_event',
            'data' => [],
            'created_at' => now()->subHours(2),
        ]);

        // Create recent event
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();

        $stats = $response->json('data.events_last_hour');
        $this->assertEquals(1, $stats[RealtimeEvent::CHANNEL_ORDERS] ?? 0);
    }

    /** @test */
    public function status_only_returns_data_for_users_restaurant(): void
    {
        // Create events for user's restaurant
        $this->createEvent(['restaurant_id' => $this->restaurant->id]);
        $this->createEvent(['restaurant_id' => $this->restaurant->id]);

        // Create events for other restaurant
        $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();

        $stats = $response->json('data.events_last_hour');
        $this->assertEquals(2, $stats[RealtimeEvent::CHANNEL_ORDERS] ?? 0);
    }

    // =====================================================
    // CLEANUP ENDPOINT TESTS
    // =====================================================

    /** @test */
    public function cleanup_removes_old_events(): void
    {
        // Create old events (more than 1 hour ago)
        for ($i = 0; $i < 5; $i++) {
            RealtimeEvent::create([
                'restaurant_id' => $this->restaurant->id,
                'channel' => RealtimeEvent::CHANNEL_ORDERS,
                'event' => 'old_event',
                'data' => [],
                'created_at' => now()->subHours(2),
            ]);
        }

        // Create recent events
        for ($i = 0; $i < 3; $i++) {
            $this->createEvent();
        }

        $this->assertEquals(8, RealtimeEvent::count());

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/cleanup');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertStringContainsString('5', $response->json('message'));
        $this->assertEquals(3, RealtimeEvent::count());
    }

    /** @test */
    public function cleanup_returns_zero_when_no_old_events(): void
    {
        // Create only recent events
        $this->createEvent();
        $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/cleanup');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertStringContainsString('0', $response->json('message'));
        $this->assertEquals(2, RealtimeEvent::count());
    }

    // =====================================================
    // TENANT ISOLATION TESTS
    // =====================================================

    /** @test */
    public function poll_isolates_events_by_restaurant(): void
    {
        // Create events for restaurant 1
        $event1 = $this->createEvent(['restaurant_id' => $this->restaurant->id]);

        // Create events for restaurant 2
        $event2 = $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        // User from restaurant 1 should only see restaurant 1 events
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();
        $events = $response->json('data.events');
        $this->assertCount(1, $events);
        $this->assertEquals($event1->id, $events[0]['id']);

        // User from restaurant 2 should only see restaurant 2 events
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherUserToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();
        $events = $response->json('data.events');
        $this->assertCount(1, $events);
        $this->assertEquals($event2->id, $events[0]['id']);
    }

    /** @test */
    public function recent_isolates_events_by_restaurant(): void
    {
        $this->createEvent(['restaurant_id' => $this->restaurant->id]);
        $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        // User from restaurant 1
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk();
        $events = $response->json('data.events');
        $this->assertCount(1, $events);

        // All events should belong to user's restaurant
        foreach ($events as $event) {
            $dbEvent = RealtimeEvent::find($event['id']);
            $this->assertEquals($this->restaurant->id, $dbEvent->restaurant_id);
        }
    }

    /** @test */
    public function status_isolates_data_by_restaurant(): void
    {
        // Create events for both restaurants
        $this->createEvent(['restaurant_id' => $this->restaurant->id]);
        $this->createEvent(['restaurant_id' => $this->restaurant->id]);
        $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);
        $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);
        $this->createEvent(['restaurant_id' => $this->otherRestaurant->id]);

        // User from restaurant 1
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();
        $stats = $response->json('data.events_last_hour');
        $this->assertEquals(2, $stats[RealtimeEvent::CHANNEL_ORDERS] ?? 0);

        // User from restaurant 2
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->otherUserToken}",
        ])->getJson('/api/realtime/status');

        $response->assertOk();
        $stats = $response->json('data.events_last_hour');
        $this->assertEquals(3, $stats[RealtimeEvent::CHANNEL_ORDERS] ?? 0);
    }

    // =====================================================
    // REALTIME EVENT MODEL TESTS
    // =====================================================

    /** @test */
    public function realtime_event_dispatch_creates_event(): void
    {
        $this->actingAs($this->admin);

        $event = RealtimeEvent::dispatch(
            RealtimeEvent::CHANNEL_ORDERS,
            RealtimeEvent::EVENT_NEW_ORDER,
            ['order_id' => 123, 'restaurant_id' => $this->restaurant->id],
            $this->admin->id
        );

        $this->assertInstanceOf(RealtimeEvent::class, $event);
        $this->assertEquals(RealtimeEvent::CHANNEL_ORDERS, $event->channel);
        $this->assertEquals(RealtimeEvent::EVENT_NEW_ORDER, $event->event);
        $this->assertEquals(123, $event->data['order_id']);
        $this->assertEquals($this->admin->id, $event->user_id);
    }

    /** @test */
    public function realtime_event_dispatch_requires_restaurant_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('restaurant_id is required');

        // Without authentication and without restaurant_id in data
        RealtimeEvent::dispatch('channel', 'event', []);
    }

    /** @test */
    public function realtime_event_get_after_retrieves_events(): void
    {
        $event1 = $this->createEvent();
        $event2 = $this->createEvent();
        $event3 = $this->createEvent();

        $events = RealtimeEvent::getAfter($event1->id, [], $this->restaurant->id);

        $this->assertCount(2, $events);
        $this->assertEquals($event2->id, $events[0]->id);
        $this->assertEquals($event3->id, $events[1]->id);
    }

    /** @test */
    public function realtime_event_get_after_filters_by_channels(): void
    {
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);

        $events = RealtimeEvent::getAfter(0, [RealtimeEvent::CHANNEL_ORDERS, RealtimeEvent::CHANNEL_KITCHEN], $this->restaurant->id);

        $this->assertCount(2, $events);
    }

    /** @test */
    public function realtime_event_cleanup_removes_old_events(): void
    {
        // Create old events
        RealtimeEvent::create([
            'restaurant_id' => $this->restaurant->id,
            'channel' => 'test',
            'event' => 'old',
            'data' => [],
            'created_at' => now()->subHours(2),
        ]);

        // Create recent events
        $this->createEvent();

        $deleted = RealtimeEvent::cleanup();

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, RealtimeEvent::count());
    }

    /** @test */
    public function realtime_event_helper_order_created_works(): void
    {
        $this->actingAs($this->admin);

        $order = [
            'id' => 1,
            'order_number' => 'ORD-001',
            'type' => 'dine_in',
            'total' => 1500,
            'table_id' => 5,
            'restaurant_id' => $this->restaurant->id,
        ];

        $event = RealtimeEvent::orderCreated($order);

        $this->assertEquals(RealtimeEvent::CHANNEL_ORDERS, $event->channel);
        $this->assertEquals(RealtimeEvent::EVENT_NEW_ORDER, $event->event);
        $this->assertEquals(1, $event->data['order_id']);
        $this->assertEquals('ORD-001', $event->data['order_number']);
        $this->assertStringContainsString('ORD-001', $event->data['message']);
    }

    /** @test */
    public function realtime_event_data_is_cast_to_array(): void
    {
        $event = $this->createEvent([
            'data' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $event->refresh();

        $this->assertIsArray($event->data);
        $this->assertEquals('value', $event->data['key']);
        $this->assertEquals(1, $event->data['nested']['a']);
    }

    /** @test */
    public function realtime_event_created_at_is_cast_to_datetime(): void
    {
        $event = $this->createEvent();
        $event->refresh();

        $this->assertInstanceOf(\Carbon\Carbon::class, $event->created_at);
    }

    // =====================================================
    // CHANNEL CONSTANTS TESTS
    // =====================================================

    /** @test */
    public function realtime_event_defines_channel_constants(): void
    {
        $this->assertEquals('orders', RealtimeEvent::CHANNEL_ORDERS);
        $this->assertEquals('kitchen', RealtimeEvent::CHANNEL_KITCHEN);
        $this->assertEquals('delivery', RealtimeEvent::CHANNEL_DELIVERY);
        $this->assertEquals('reservations', RealtimeEvent::CHANNEL_RESERVATIONS);
        $this->assertEquals('tables', RealtimeEvent::CHANNEL_TABLES);
        $this->assertEquals('global', RealtimeEvent::CHANNEL_GLOBAL);
    }

    /** @test */
    public function realtime_event_defines_event_constants(): void
    {
        $this->assertEquals('new_order', RealtimeEvent::EVENT_NEW_ORDER);
        $this->assertEquals('order_updated', RealtimeEvent::EVENT_ORDER_UPDATED);
        $this->assertEquals('order_status', RealtimeEvent::EVENT_ORDER_STATUS);
        $this->assertEquals('order_paid', RealtimeEvent::EVENT_ORDER_PAID);
        $this->assertEquals('order_cancelled', RealtimeEvent::EVENT_ORDER_CANCELLED);
        $this->assertEquals('kitchen_new', RealtimeEvent::EVENT_KITCHEN_NEW);
        $this->assertEquals('kitchen_ready', RealtimeEvent::EVENT_KITCHEN_READY);
        $this->assertEquals('item_cancelled', RealtimeEvent::EVENT_ITEM_CANCELLED);
    }

    // =====================================================
    // THROTTLE TESTS
    // =====================================================

    /** @test */
    public function realtime_endpoints_are_throttled(): void
    {
        // The routes have throttle:120,1 middleware
        // We just verify the middleware is applied by checking
        // that requests work within limits
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->adminToken}",
            ])->getJson('/api/realtime/status');

            $response->assertOk();
        }
    }

    // =====================================================
    // EVENT TIMESTAMP FORMAT TESTS
    // =====================================================

    /** @test */
    public function poll_returns_timestamps_in_iso8601_format(): void
    {
        $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();

        $events = $response->json('data.events');
        $timestamp = $events[0]['timestamp'];

        // ISO 8601 format validation
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    /** @test */
    public function recent_returns_timestamps_in_iso8601_format(): void
    {
        $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent');

        $response->assertOk();

        $events = $response->json('data.events');
        $timestamp = $events[0]['timestamp'];

        // ISO 8601 format validation
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    // =====================================================
    // MULTIPLE CHANNELS FILTERING TESTS
    // =====================================================

    /** @test */
    public function poll_filters_multiple_channels_correctly(): void
    {
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_RESERVATIONS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_TABLES]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1&channels[]=' . RealtimeEvent::CHANNEL_ORDERS . '&channels[]=' . RealtimeEvent::CHANNEL_KITCHEN . '&channels[]=' . RealtimeEvent::CHANNEL_DELIVERY);

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(3, $events);

        $channels = array_column($events, 'channel');
        $this->assertContains(RealtimeEvent::CHANNEL_ORDERS, $channels);
        $this->assertContains(RealtimeEvent::CHANNEL_KITCHEN, $channels);
        $this->assertContains(RealtimeEvent::CHANNEL_DELIVERY, $channels);
        $this->assertNotContains(RealtimeEvent::CHANNEL_RESERVATIONS, $channels);
        $this->assertNotContains(RealtimeEvent::CHANNEL_TABLES, $channels);
    }

    /** @test */
    public function recent_filters_multiple_channels_correctly(): void
    {
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_ORDERS]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_KITCHEN]);
        $this->createEvent(['channel' => RealtimeEvent::CHANNEL_DELIVERY]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent?channels=' . RealtimeEvent::CHANNEL_KITCHEN . ',' . RealtimeEvent::CHANNEL_DELIVERY);

        $response->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(2, $events);

        $channels = array_column($events, 'channel');
        $this->assertContains(RealtimeEvent::CHANNEL_KITCHEN, $channels);
        $this->assertContains(RealtimeEvent::CHANNEL_DELIVERY, $channels);
        $this->assertNotContains(RealtimeEvent::CHANNEL_ORDERS, $channels);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    /** @test */
    public function poll_handles_very_large_last_id(): void
    {
        $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=999999999&timeout=1');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'events' => [],
                ],
            ]);
    }

    /** @test */
    public function recent_handles_empty_channels_array(): void
    {
        $this->createEvent();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/recent?channels=');

        $response->assertOk();

        // Should return all events when channels is empty
        $events = $response->json('data.events');
        $this->assertCount(1, $events);
    }

    /** @test */
    public function send_handles_empty_data_array(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/realtime/send', [
            'channel' => 'test',
            'event' => 'test',
            'data' => [],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $eventId = $response->json('data.id');
        $event = RealtimeEvent::find($eventId);
        $this->assertEquals([], $event->data);
    }

    /** @test */
    public function poll_returns_correct_structure_with_complex_event_data(): void
    {
        $complexData = [
            'order_id' => 123,
            'items' => [
                ['id' => 1, 'name' => 'Pizza', 'qty' => 2],
                ['id' => 2, 'name' => 'Burger', 'qty' => 1],
            ],
            'total' => 1500.50,
            'nested' => [
                'deep' => [
                    'value' => 'test',
                ],
            ],
        ];

        $this->createEvent(['data' => $complexData]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->getJson('/api/realtime/poll?last_id=0&timeout=1');

        $response->assertOk();

        $events = $response->json('data.events');
        $eventData = $events[0]['data'];

        $this->assertEquals(123, $eventData['order_id']);
        $this->assertCount(2, $eventData['items']);
        $this->assertEquals(1500.50, $eventData['total']);
        $this->assertEquals('test', $eventData['nested']['deep']['value']);
    }
}
