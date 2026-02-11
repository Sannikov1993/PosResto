<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Notification;
use App\Services\StaffNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class StaffNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $manager;
    protected User $waiter;
    protected User $anotherWaiter;
    protected string $adminToken;
    protected string $managerToken;
    protected string $waiterToken;
    protected string $anotherWaiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

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

        // Create manager role
        $this->managerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'manager',
            'name' => 'Manager',
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

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'role_id' => $this->managerRole->id,
            'is_active' => true,
            'email' => 'manager@test.com',
        ]);

        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'email' => 'waiter@test.com',
        ]);

        $this->anotherWaiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'email' => 'another.waiter@test.com',
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->anotherWaiterToken = $this->anotherWaiter->createToken('test')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    /** @test */
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_unread_count_requires_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications/unread-count');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_mark_as_read_requires_authentication(): void
    {
        $notification = $this->createNotification($this->waiter->id);
        $response = $this->postJson("/api/staff-notifications/{$notification->id}/read");
        $response->assertStatus(401);
    }

    /** @test */
    public function test_mark_all_as_read_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/read-all');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_delete_requires_authentication(): void
    {
        $notification = $this->createNotification($this->waiter->id);
        $response = $this->deleteJson("/api/staff-notifications/{$notification->id}");
        $response->assertStatus(401);
    }

    /** @test */
    public function test_get_settings_requires_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications/settings');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_update_settings_requires_authentication(): void
    {
        $response = $this->putJson('/api/staff-notifications/settings', [
            'settings' => [],
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function test_telegram_link_requires_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications/telegram-link');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_disconnect_telegram_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/disconnect-telegram');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_push_token_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/push-token', [
            'token' => 'test_token',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function test_send_test_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/send-test');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_send_to_user_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => 'Test',
            'message' => 'Test',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function test_send_to_all_requires_authentication(): void
    {
        $response = $this->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Test',
            'message' => 'Test',
        ]);
        $response->assertStatus(401);
    }

    // =====================================================
    // INDEX - LIST NOTIFICATIONS
    // =====================================================

    /** @test */
    public function test_can_list_user_notifications(): void
    {
        $this->createNotification($this->waiter->id, 'Notification 1');
        $this->createNotification($this->waiter->id, 'Notification 2');
        $this->createNotification($this->waiter->id, 'Notification 3');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'message',
                        'is_read',
                        'time_ago',
                        'created_at',
                    ]
                ],
                'unread_count',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function test_notifications_list_only_returns_own_notifications(): void
    {
        // Create notifications for waiter
        $this->createNotification($this->waiter->id, 'Waiter Notification');

        // Create notifications for another waiter
        $this->createNotification($this->anotherWaiter->id, 'Another Waiter Notification');

        // Create notification for admin
        $this->createNotification($this->admin->id, 'Admin Notification');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Waiter Notification', $response->json('data.0.title'));
    }

    /** @test */
    public function test_can_filter_unread_only_notifications(): void
    {
        // Create read notification
        $this->createNotification($this->waiter->id, 'Read Notification', now());

        // Create unread notifications
        $this->createNotification($this->waiter->id, 'Unread 1');
        $this->createNotification($this->waiter->id, 'Unread 2');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications?unread_only=1');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function test_can_filter_notifications_by_type(): void
    {
        // Create shift reminder
        $this->createNotification($this->waiter->id, 'Shift Reminder', null, Notification::TYPE_SHIFT_REMINDER);

        // Create salary notification
        $this->createNotification($this->waiter->id, 'Salary Paid', null, Notification::TYPE_SALARY_PAID);

        // Create system notification
        $this->createNotification($this->waiter->id, 'System', null, Notification::TYPE_SYSTEM);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications?type=shift_reminder');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Shift Reminder', $response->json('data.0.title'));
    }

    /** @test */
    public function test_can_limit_notifications_list(): void
    {
        // Create 10 notifications
        for ($i = 1; $i <= 10; $i++) {
            $this->createNotification($this->waiter->id, "Notification {$i}");
        }

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications?limit=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_notifications_are_ordered_by_created_at_desc(): void
    {
        $oldest = $this->createNotification($this->waiter->id, 'Oldest');
        $oldest->created_at = now()->subDays(2);
        $oldest->save();

        $middle = $this->createNotification($this->waiter->id, 'Middle');
        $middle->created_at = now()->subDay();
        $middle->save();

        $newest = $this->createNotification($this->waiter->id, 'Newest');
        $newest->created_at = now();
        $newest->save();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Newest', $data[0]['title']);
        $this->assertEquals('Oldest', $data[2]['title']);
    }

    /** @test */
    public function test_unread_count_is_included_in_response(): void
    {
        // Create read notifications
        $this->createNotification($this->waiter->id, 'Read 1', now());
        $this->createNotification($this->waiter->id, 'Read 2', now());

        // Create unread notifications
        $this->createNotification($this->waiter->id, 'Unread 1');
        $this->createNotification($this->waiter->id, 'Unread 2');
        $this->createNotification($this->waiter->id, 'Unread 3');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications');

        $response->assertOk();
        $this->assertEquals(3, $response->json('unread_count'));
    }

    // =====================================================
    // UNREAD COUNT ENDPOINT
    // =====================================================

    /** @test */
    public function test_can_get_unread_count(): void
    {
        $this->createNotification($this->waiter->id, 'Unread 1');
        $this->createNotification($this->waiter->id, 'Unread 2');
        $this->createNotification($this->waiter->id, 'Read', now());

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    /** @test */
    public function test_unread_count_returns_zero_when_no_notifications(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 0,
            ]);
    }

    /** @test */
    public function test_unread_count_only_counts_own_notifications(): void
    {
        // Create notifications for waiter
        $this->createNotification($this->waiter->id, 'Waiter 1');
        $this->createNotification($this->waiter->id, 'Waiter 2');

        // Create notifications for another waiter
        $this->createNotification($this->anotherWaiter->id, 'Another 1');
        $this->createNotification($this->anotherWaiter->id, 'Another 2');
        $this->createNotification($this->anotherWaiter->id, 'Another 3');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    // =====================================================
    // MARK AS READ - SINGLE NOTIFICATION
    // =====================================================

    /** @test */
    public function test_can_mark_notification_as_read(): void
    {
        $notification = $this->createNotification($this->waiter->id);

        $this->assertNull($notification->read_at);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson("/api/staff-notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $notification = $this->createNotification($this->admin->id);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson("/api/staff-notifications/{$notification->id}/read");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    /** @test */
    public function test_mark_as_read_returns_404_for_nonexistent_notification(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/99999/read');

        $response->assertStatus(404);
    }

    // =====================================================
    // MARK ALL AS READ
    // =====================================================

    /** @test */
    public function test_can_mark_all_notifications_as_read(): void
    {
        $this->createNotification($this->waiter->id, 'Notification 1');
        $this->createNotification($this->waiter->id, 'Notification 2');
        $this->createNotification($this->waiter->id, 'Notification 3');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/read-all');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);

        $unreadCount = Notification::where('user_id', $this->waiter->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    /** @test */
    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        // Create notifications for waiter
        $this->createNotification($this->waiter->id, 'Waiter 1');

        // Create notifications for admin
        $adminNotification = $this->createNotification($this->admin->id, 'Admin 1');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/read-all');

        $response->assertOk();

        // Admin's notification should still be unread
        $adminNotification->refresh();
        $this->assertNull($adminNotification->read_at);
    }

    /** @test */
    public function test_mark_all_as_read_with_no_unread_notifications(): void
    {
        // Create already read notification
        $this->createNotification($this->waiter->id, 'Already Read', now());

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/read-all');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
    }

    // =====================================================
    // DELETE NOTIFICATION
    // =====================================================

    /** @test */
    public function test_can_delete_notification(): void
    {
        $notification = $this->createNotification($this->waiter->id);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->deleteJson("/api/staff-notifications/{$notification->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification deleted',
            ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    /** @test */
    public function test_cannot_delete_other_users_notification(): void
    {
        $notification = $this->createNotification($this->admin->id);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->deleteJson("/api/staff-notifications/{$notification->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }

    /** @test */
    public function test_delete_returns_404_for_nonexistent_notification(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->deleteJson('/api/staff-notifications/99999');

        $response->assertStatus(404);
    }

    // =====================================================
    // GET NOTIFICATION SETTINGS
    // =====================================================

    /** @test */
    public function test_can_get_notification_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'settings',
                    'telegram_connected',
                    'telegram_username',
                    'email',
                    'push_enabled',
                ],
            ]);
    }

    /** @test */
    public function test_get_settings_returns_default_settings_for_new_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();

        $settings = $response->json('data.settings');
        $this->assertArrayHasKey('shift_reminder', $settings);
        $this->assertArrayHasKey('schedule_change', $settings);
        $this->assertArrayHasKey('salary_paid', $settings);
    }

    /** @test */
    public function test_get_settings_returns_custom_settings_when_set(): void
    {
        $customSettings = [
            'shift_reminder' => ['email' => false, 'telegram' => true, 'push' => false],
            'salary_paid' => ['email' => true, 'telegram' => false, 'push' => true],
        ];

        $this->waiter->update(['notification_settings' => $customSettings]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();
        $this->assertEquals($customSettings, $response->json('data.settings'));
    }

    /** @test */
    public function test_get_settings_shows_telegram_connected_status(): void
    {
        // User without Telegram
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();
        $this->assertFalse($response->json('data.telegram_connected'));

        // Connect Telegram
        $this->waiter->update([
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'testuser',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();
        $this->assertTrue($response->json('data.telegram_connected'));
        $this->assertEquals('testuser', $response->json('data.telegram_username'));
    }

    /** @test */
    public function test_get_settings_shows_push_enabled_status(): void
    {
        // User without push token
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();
        $this->assertFalse($response->json('data.push_enabled'));

        // Set push token
        $this->waiter->update(['push_token' => 'test_push_token']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/settings');

        $response->assertOk();
        $this->assertTrue($response->json('data.push_enabled'));
    }

    // =====================================================
    // UPDATE NOTIFICATION SETTINGS
    // =====================================================

    /** @test */
    public function test_can_update_notification_settings(): void
    {
        $newSettings = [
            'shift_reminder' => ['email' => false, 'telegram' => true, 'push' => true],
            'schedule_change' => ['email' => true, 'telegram' => false, 'push' => true],
            'salary_paid' => ['email' => true, 'telegram' => true, 'push' => false],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->putJson('/api/staff-notifications/settings', [
            'settings' => $newSettings,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Settings updated',
            ]);

        $this->waiter->refresh();
        $this->assertEquals($newSettings, $this->waiter->notification_settings);
    }

    /** @test */
    public function test_update_settings_validates_settings_is_required(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->putJson('/api/staff-notifications/settings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    /** @test */
    public function test_update_settings_validates_settings_is_array(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->putJson('/api/staff-notifications/settings', [
            'settings' => 'not_an_array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    /** @test */
    public function test_update_settings_returns_updated_settings(): void
    {
        $newSettings = [
            'shift_reminder' => ['email' => false, 'telegram' => true, 'push' => false],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->putJson('/api/staff-notifications/settings', [
            'settings' => $newSettings,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => $newSettings,
            ]);
    }

    // =====================================================
    // GET TELEGRAM LINK
    // =====================================================

    /** @test */
    public function test_can_get_telegram_link(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('getTelegramConnectLink')
            ->with(Mockery::type(User::class))
            ->andReturn('https://t.me/testbot?start=token123');

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/telegram-link');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'link',
                    'connected',
                    'username',
                ],
            ]);

        $this->assertEquals('https://t.me/testbot?start=token123', $response->json('data.link'));
    }

    /** @test */
    public function test_telegram_link_returns_error_when_bot_not_configured(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('getTelegramConnectLink')
            ->with(Mockery::type(User::class))
            ->andReturn(null);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/telegram-link');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Telegram bot not configured',
            ]);
    }

    /** @test */
    public function test_telegram_link_shows_connected_status(): void
    {
        $this->waiter->update([
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'connected_user',
        ]);

        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('getTelegramConnectLink')
            ->andReturn('https://t.me/testbot?start=token');

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->getJson('/api/staff-notifications/telegram-link');

        $response->assertOk();
        $this->assertTrue($response->json('data.connected'));
        $this->assertEquals('connected_user', $response->json('data.username'));
    }

    // =====================================================
    // DISCONNECT TELEGRAM
    // =====================================================

    /** @test */
    public function test_can_disconnect_telegram(): void
    {
        $this->waiter->update([
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'testuser',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/disconnect-telegram');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Telegram disconnected',
            ]);

        $this->waiter->refresh();
        $this->assertNull($this->waiter->telegram_chat_id);
        $this->assertNull($this->waiter->telegram_username);
    }

    /** @test */
    public function test_disconnect_telegram_when_not_connected(): void
    {
        // User has no Telegram connected
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/disconnect-telegram');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Telegram disconnected',
            ]);
    }

    // =====================================================
    // SAVE PUSH TOKEN
    // =====================================================

    /** @test */
    public function test_can_save_push_token(): void
    {
        $token = 'test_push_token_123456';

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/push-token', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Push token saved',
            ]);

        $this->waiter->refresh();
        $this->assertEquals($token, $this->waiter->push_token);
    }

    /** @test */
    public function test_save_push_token_validates_token_is_required(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/push-token', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    /** @test */
    public function test_save_push_token_validates_token_is_string(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/push-token', [
            'token' => 12345,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    /** @test */
    public function test_save_push_token_can_update_existing_token(): void
    {
        $this->waiter->update(['push_token' => 'old_token']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/push-token', [
            'token' => 'new_token',
        ]);

        $response->assertOk();

        $this->waiter->refresh();
        $this->assertEquals('new_token', $this->waiter->push_token);
    }

    // =====================================================
    // SEND TEST NOTIFICATION (ADMIN ONLY)
    // =====================================================

    /** @test */
    public function test_admin_can_send_test_notification(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('send')
            ->once()
            ->andReturn($this->createNotification($this->admin->id, 'Test'));

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-test', [
            'channel' => 'all',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test notification sent',
            ]);
    }

    /** @test */
    public function test_admin_can_send_test_notification_to_specific_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-test', [
            'user_id' => $this->waiter->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test notification sent',
            ]);
    }

    /** @test */
    public function test_admin_can_send_test_notification_to_specific_channel(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('send')
            ->withArgs(function ($user, $type, $title, $message, $data, $channels) {
                return in_array('telegram', $channels);
            })
            ->once()
            ->andReturn($this->createNotification($this->admin->id, 'Test'));

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-test', [
            'channel' => 'telegram',
        ]);

        $response->assertOk();
    }

    /** @test */
    public function test_manager_cannot_send_test_notification(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-test');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);
    }

    /** @test */
    public function test_waiter_cannot_send_test_notification(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/send-test');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);
    }

    /** @test */
    public function test_send_test_validates_user_id_exists(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-test', [
            'user_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function test_send_test_validates_channel(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-test', [
            'channel' => 'invalid_channel',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    // =====================================================
    // SEND TO USER (MANAGER+)
    // =====================================================

    /** @test */
    public function test_manager_can_send_notification_to_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification sent',
            ]);
    }

    /** @test */
    public function test_admin_can_send_notification_to_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => 'Admin Notification',
            'message' => 'This is an admin notification',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification sent',
            ]);
    }

    /** @test */
    public function test_waiter_cannot_send_notification_to_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->admin->id,
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_send_to_user_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-user', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'title', 'message']);
    }

    /** @test */
    public function test_send_to_user_validates_user_exists(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => 99999,
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function test_send_to_user_validates_title_max_length(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => str_repeat('a', 256),
            'message' => 'Test message',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function test_send_to_user_validates_message_max_length(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => 'Test',
            'message' => str_repeat('a', 2001),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    // =====================================================
    // SEND TO ALL (MANAGER+)
    // =====================================================

    /** @test */
    public function test_manager_can_send_notification_to_all_staff(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->once()
            ->andReturn([
                $this->createNotification($this->waiter->id, 'Broadcast'),
                $this->createNotification($this->anotherWaiter->id, 'Broadcast'),
            ]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Broadcast Message',
            'message' => 'Message to all staff',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertStringContainsString('Notifications sent to 2 users', $response->json('message'));
    }

    /** @test */
    public function test_admin_can_send_notification_to_all_staff(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->once()
            ->andReturn([]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Admin Broadcast',
            'message' => 'Broadcast from admin',
        ]);

        $response->assertOk();
    }

    /** @test */
    public function test_waiter_cannot_send_notification_to_all(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->waiterToken}",
        ])->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_send_to_all_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-all', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'message']);
    }

    /** @test */
    public function test_send_to_all_can_filter_by_roles(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->withArgs(function ($restaurantId, $type, $title, $message, $data, $roles) {
                return $roles === ['waiter', 'cook'];
            })
            ->once()
            ->andReturn([]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Kitchen Staff',
            'message' => 'Message to specific roles',
            'roles' => ['waiter', 'cook'],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function test_send_to_all_uses_current_user_restaurant(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->withArgs(function ($restaurantId, $type, $title, $message, $data, $roles) {
                return $restaurantId === $this->restaurant->id;
            })
            ->once()
            ->andReturn([]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->managerToken}",
        ])->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertOk();
    }

    // =====================================================
    // NOTIFICATION PREFERENCES
    // =====================================================

    /** @test */
    public function test_notification_channels_respect_user_preferences(): void
    {
        $settings = [
            'shift_reminder' => ['email' => false, 'telegram' => true, 'push' => false],
        ];

        $this->waiter->update(['notification_settings' => $settings]);
        $this->waiter->update([
            'email' => 'test@test.com',
            'telegram_chat_id' => '123456',
            'push_token' => 'test_token',
        ]);

        $channels = $this->waiter->getNotificationChannels('shift_reminder');

        $this->assertNotContains('email', $channels);
        $this->assertContains('telegram', $channels);
        $this->assertNotContains('push', $channels);
        $this->assertContains('in_app', $channels); // Always included
    }

    /** @test */
    public function test_notification_channels_only_include_configured_channels(): void
    {
        // User with only email (no telegram or push)
        $this->waiter->update([
            'email' => 'test@test.com',
            'telegram_chat_id' => null,
            'push_token' => null,
        ]);

        $channels = $this->waiter->getNotificationChannels('shift_reminder');

        $this->assertContains('email', $channels);
        $this->assertNotContains('telegram', $channels);
        $this->assertNotContains('push', $channels);
        $this->assertContains('in_app', $channels);
    }

    // =====================================================
    // HELPER METHODS
    // =====================================================

    protected function createNotification(
        int $userId,
        string $title = 'Test Notification',
        ?\DateTime $readAt = null,
        string $type = Notification::TYPE_SYSTEM
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'restaurant_id' => $this->restaurant->id,
            'type' => $type,
            'title' => $title,
            'message' => 'Test message content',
            'channels' => ['in_app'],
            'read_at' => $readAt,
        ]);
    }
}
