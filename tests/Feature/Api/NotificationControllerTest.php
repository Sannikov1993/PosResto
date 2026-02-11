<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Services\StaffNotificationService;
use App\Services\WebPushService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $managerRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $manager;
    protected User $waiter;
    protected User $user;
    protected string $token;

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

        // Create users with restaurant_id and is_active set
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

        // Set default user for authenticate()
        $this->user = $this->waiter;
    }

    /**
     * Authenticate using Sanctum token for API routes with auth.api_token middleware
     */
    protected function authenticate(?User $user = null): void
    {
        $user = $user ?? $this->user;
        $this->token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Authenticate as admin
     */
    protected function authenticateAsAdmin(): void
    {
        $this->authenticate($this->admin);
    }

    /**
     * Authenticate as manager
     */
    protected function authenticateAsManager(): void
    {
        $this->authenticate($this->manager);
    }

    /**
     * Authenticate as waiter
     */
    protected function authenticateAsWaiter(): void
    {
        $this->authenticate($this->waiter);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =====================================================
    // STAFF NOTIFICATIONS - LIST
    // =====================================================

    /** @test */
    public function it_can_list_user_notifications(): void
    {
        $this->authenticateAsWaiter();

        // Create notifications for the user
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Shift Reminder',
            'message' => 'Your shift starts at 10:00',
            'channels' => ['in_app'],
        ]);

        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'System Update',
            'message' => 'System maintenance scheduled',
            'channels' => ['in_app'],
        ]);

        $response = $this->getJson('/api/staff-notifications');

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

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_unread_notifications(): void
    {
        $this->authenticateAsWaiter();

        // Create read notification
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Read Notification',
            'message' => 'This was read',
            'channels' => ['in_app'],
            'read_at' => now(),
        ]);

        // Create unread notification
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Unread Notification',
            'message' => 'This is unread',
            'channels' => ['in_app'],
        ]);

        $response = $this->getJson('/api/staff-notifications?unread_only=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Unread Notification', $response->json('data.0.title'));
    }

    /** @test */
    public function it_can_filter_notifications_by_type(): void
    {
        $this->authenticateAsWaiter();

        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Shift Reminder',
            'message' => 'Your shift starts soon',
            'channels' => ['in_app'],
        ]);

        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SALARY_PAID,
            'title' => 'Salary Paid',
            'message' => 'Your salary has been paid',
            'channels' => ['in_app'],
        ]);

        $response = $this->getJson('/api/staff-notifications?type=shift_reminder');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Shift Reminder', $response->json('data.0.title'));
    }

    /** @test */
    public function it_respects_limit_parameter(): void
    {
        $this->authenticateAsWaiter();

        // Create 10 notifications
        for ($i = 1; $i <= 10; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Notification {$i}",
                'message' => "Message {$i}",
                'channels' => ['in_app'],
            ]);
        }

        $response = $this->getJson('/api/staff-notifications?limit=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_only_returns_own_notifications(): void
    {
        $this->authenticateAsWaiter();

        // Create notification for waiter
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Waiter Notification',
            'message' => 'For waiter',
            'channels' => ['in_app'],
        ]);

        // Create notification for admin
        Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'For admin',
            'channels' => ['in_app'],
        ]);

        $response = $this->getJson('/api/staff-notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Waiter Notification', $response->json('data.0.title'));
    }

    // =====================================================
    // STAFF NOTIFICATIONS - UNREAD COUNT
    // =====================================================

    /** @test */
    public function it_returns_unread_count(): void
    {
        $this->authenticateAsWaiter();

        // Create 3 unread notifications
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Unread {$i}",
                'message' => "Message {$i}",
                'channels' => ['in_app'],
            ]);
        }

        // Create 2 read notifications
        for ($i = 1; $i <= 2; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Read {$i}",
                'message' => "Message {$i}",
                'channels' => ['in_app'],
                'read_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/staff-notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 3,
            ]);
    }

    /** @test */
    public function it_returns_zero_when_no_unread_notifications(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->getJson('/api/staff-notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 0,
            ]);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - MARK AS READ
    // =====================================================

    /** @test */
    public function it_can_mark_notification_as_read(): void
    {
        $this->authenticateAsWaiter();

        $notification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'channels' => ['in_app'],
        ]);

        $this->assertNull($notification->read_at);

        $response = $this->postJson("/api/staff-notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function it_cannot_mark_other_users_notification_as_read(): void
    {
        $this->authenticateAsWaiter();

        $notification = Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'For admin only',
            'channels' => ['in_app'],
        ]);

        $response = $this->postJson("/api/staff-notifications/{$notification->id}/read");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - MARK ALL AS READ
    // =====================================================

    /** @test */
    public function it_can_mark_all_notifications_as_read(): void
    {
        $this->authenticateAsWaiter();

        // Create multiple unread notifications
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Notification {$i}",
                'message' => "Message {$i}",
                'channels' => ['in_app'],
            ]);
        }

        $response = $this->postJson('/api/staff-notifications/read-all');

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
    public function mark_all_as_read_only_affects_own_notifications(): void
    {
        $this->authenticateAsWaiter();

        // Create notifications for waiter
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Waiter Notification',
            'message' => 'For waiter',
            'channels' => ['in_app'],
        ]);

        // Create notifications for admin
        $adminNotification = Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'For admin',
            'channels' => ['in_app'],
        ]);

        $response = $this->postJson('/api/staff-notifications/read-all');

        $response->assertOk();

        // Admin's notification should still be unread
        $adminNotification->refresh();
        $this->assertNull($adminNotification->read_at);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - DELETE
    // =====================================================

    /** @test */
    public function it_can_delete_notification(): void
    {
        $this->authenticateAsWaiter();

        $notification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Delete Me',
            'message' => 'This should be deleted',
            'channels' => ['in_app'],
        ]);

        $response = $this->deleteJson("/api/staff-notifications/{$notification->id}");

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
    public function it_cannot_delete_other_users_notification(): void
    {
        $this->authenticateAsWaiter();

        $notification = Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Only',
            'message' => 'Cannot be deleted by waiter',
            'channels' => ['in_app'],
        ]);

        $response = $this->deleteJson("/api/staff-notifications/{$notification->id}");

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
    public function delete_returns_404_for_nonexistent_notification(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->deleteJson('/api/staff-notifications/99999');

        $response->assertStatus(404);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - SETTINGS
    // =====================================================

    /** @test */
    public function it_can_get_notification_settings(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->getJson('/api/staff-notifications/settings');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'settings',
                    'telegram_connected',
                    'email',
                    'push_enabled',
                ],
            ]);
    }

    /** @test */
    public function it_returns_default_settings_for_new_user(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->getJson('/api/staff-notifications/settings');

        $response->assertOk();

        $settings = $response->json('data.settings');
        $this->assertArrayHasKey('shift_reminder', $settings);
        $this->assertArrayHasKey('schedule_change', $settings);
        $this->assertArrayHasKey('salary_paid', $settings);
    }

    /** @test */
    public function it_can_update_notification_settings(): void
    {
        $this->authenticateAsWaiter();

        $newSettings = [
            'shift_reminder' => ['email' => false, 'telegram' => true, 'push' => true],
            'schedule_change' => ['email' => true, 'telegram' => false, 'push' => true],
            'salary_paid' => ['email' => true, 'telegram' => true, 'push' => false],
        ];

        $response = $this->putJson('/api/staff-notifications/settings', [
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
    public function update_settings_validates_required_fields(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->putJson('/api/staff-notifications/settings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - TELEGRAM
    // =====================================================

    /** @test */
    public function it_can_get_telegram_link(): void
    {
        $this->authenticateAsWaiter();

        // Mock the StaffNotificationService
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('getTelegramConnectLink')
            ->with(Mockery::type(User::class))
            ->andReturn('https://t.me/testbot?start=token123');

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->getJson('/api/staff-notifications/telegram-link');

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
    }

    /** @test */
    public function telegram_link_returns_error_when_bot_not_configured(): void
    {
        $this->authenticateAsWaiter();

        // Mock the service to return null
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('getTelegramConnectLink')
            ->with(Mockery::type(User::class))
            ->andReturn(null);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->getJson('/api/staff-notifications/telegram-link');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Telegram bot not configured',
            ]);
    }

    /** @test */
    public function it_can_disconnect_telegram(): void
    {
        $this->authenticateAsWaiter();

        // Connect Telegram first
        $this->waiter->update([
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'testuser',
        ]);

        $response = $this->postJson('/api/staff-notifications/disconnect-telegram');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Telegram disconnected',
            ]);

        $this->waiter->refresh();
        $this->assertNull($this->waiter->telegram_chat_id);
        $this->assertNull($this->waiter->telegram_username);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - PUSH TOKEN
    // =====================================================

    /** @test */
    public function it_can_save_push_token(): void
    {
        $this->authenticateAsWaiter();

        $token = 'test_push_token_123456';

        $response = $this->postJson('/api/staff-notifications/push-token', [
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
    public function save_push_token_validates_required_fields(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->postJson('/api/staff-notifications/push-token', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - SEND TEST (ADMIN ONLY)
    // =====================================================

    /** @test */
    public function admin_can_send_test_notification(): void
    {
        $this->authenticateAsAdmin();

        // Mock the service
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('send')
            ->once()
            ->andReturn(Notification::create([
                'user_id' => $this->admin->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => 'Test Notification',
                'message' => 'Test message',
                'channels' => ['in_app'],
            ]));

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->postJson('/api/staff-notifications/send-test', [
            'channel' => 'all', // Provide channel to avoid null access issue
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test notification sent',
            ]);
    }

    /** @test */
    public function admin_can_send_test_notification_to_specific_user(): void
    {

        $this->authenticateAsAdmin();

        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('send')
            ->once()
            ->andReturn(Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => 'Test Notification',
                'message' => 'Test message',
                'channels' => ['in_app'],
            ]));

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->postJson('/api/staff-notifications/send-test', [
            'user_id' => $this->waiter->id,
            'channel' => 'all', // Provide channel to avoid null access issue
        ]);

        $response->assertOk();
    }

    /** @test */
    public function waiter_cannot_send_test_notification(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->postJson('/api/staff-notifications/send-test');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - SEND TO USER (MANAGER+)
    // =====================================================

    /** @test */
    public function manager_can_send_notification_to_user(): void
    {

        $this->authenticateAsManager();

        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendCustom')
            ->once()
            ->andReturn(Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_CUSTOM,
                'title' => 'Custom Title',
                'message' => 'Custom message',
                'channels' => ['in_app'],
            ]));

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->waiter->id,
            'title' => 'Custom Title',
            'message' => 'Custom message',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification sent',
            ]);
    }

    /** @test */
    public function send_to_user_validates_required_fields(): void
    {
        $this->authenticateAsManager();

        $response = $this->postJson('/api/staff-notifications/send-to-user', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'title', 'message']);
    }

    /** @test */
    public function waiter_cannot_send_notification_to_user(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->postJson('/api/staff-notifications/send-to-user', [
            'user_id' => $this->admin->id,
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertStatus(403);
    }

    // =====================================================
    // STAFF NOTIFICATIONS - SEND TO ALL (MANAGER+)
    // =====================================================

    /** @test */
    public function manager_can_send_notification_to_all_staff(): void
    {
        $this->authenticateAsManager();

        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->once()
            ->andReturn([
                Notification::create([
                    'user_id' => $this->waiter->id,
                    'restaurant_id' => $this->restaurant->id,
                    'type' => Notification::TYPE_CUSTOM,
                    'title' => 'Broadcast',
                    'message' => 'Message to all',
                    'channels' => ['in_app'],
                ]),
            ]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Broadcast',
            'message' => 'Message to all staff',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertStringContainsString('Notifications sent to', $response->json('message'));
    }

    /** @test */
    public function send_to_all_can_filter_by_roles(): void
    {
        $this->authenticateAsManager();

        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('sendToRestaurantStaff')
            ->withArgs(function ($restaurantId, $type, $title, $message, $data, $roles) {
                return $roles === ['waiter', 'cook'];
            })
            ->once()
            ->andReturn([]);

        $this->app->instance(StaffNotificationService::class, $mockService);

        $response = $this->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Kitchen Staff',
            'message' => 'Message to specific roles',
            'roles' => ['waiter', 'cook'],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function waiter_cannot_send_notification_to_all(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->postJson('/api/staff-notifications/send-to-all', [
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertStatus(403);
    }

    // =====================================================
    // WEB PUSH - VAPID KEY
    // =====================================================

    /** @test */
    public function it_can_get_vapid_public_key(): void
    {
        $mockService = Mockery::mock(WebPushService::class);
        $mockService->shouldReceive('getPublicKey')
            ->once()
            ->andReturn('test_public_key_12345');

        $this->app->instance(WebPushService::class, $mockService);

        $response = $this->getJson('/api/notifications/vapid-key');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'public_key' => 'test_public_key_12345',
            ]);
    }

    // =====================================================
    // WEB PUSH - SUBSCRIBE
    // =====================================================

    /** @test */
    public function it_can_subscribe_to_push_notifications(): void
    {
        $this->authenticate();
        $subscription = new PushSubscription([
            'id' => 1,
            'endpoint' => 'https://push.example.com/endpoint',
        ]);

        $mockService = Mockery::mock(WebPushService::class);
        $mockService->shouldReceive('saveSubscription')
            ->once()
            ->andReturn($subscription);

        $this->app->instance(WebPushService::class, $mockService);

        $response = $this->postJson('/api/notifications/push/subscribe', [
            'endpoint' => 'https://push.example.com/endpoint',
            'keys' => [
                'p256dh' => 'test_p256dh_key',
                'auth' => 'test_auth_key',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Подписка сохранена',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'subscription_id',
            ]);
    }

    /** @test */
    public function subscribe_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/notifications/push/subscribe', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    /** @test */
    public function subscribe_validates_endpoint_is_url(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/notifications/push/subscribe', [
            'endpoint' => 'not-a-valid-url',
            'keys' => [
                'p256dh' => 'test_key',
                'auth' => 'test_auth',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint']);
    }

    /** @test */
    public function subscribe_returns_error_on_failure(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(WebPushService::class);
        $mockService->shouldReceive('saveSubscription')
            ->once()
            ->andReturn(null);

        $this->app->instance(WebPushService::class, $mockService);

        $response = $this->postJson('/api/notifications/push/subscribe', [
            'endpoint' => 'https://push.example.com/endpoint',
            'keys' => [
                'p256dh' => 'test_p256dh_key',
                'auth' => 'test_auth_key',
            ],
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    // =====================================================
    // WEB PUSH - UNSUBSCRIBE
    // =====================================================

    /** @test */
    public function it_can_unsubscribe_from_push_notifications(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(WebPushService::class);
        $mockService->shouldReceive('deleteSubscription')
            ->once()
            ->with('https://push.example.com/endpoint')
            ->andReturn(true);

        $this->app->instance(WebPushService::class, $mockService);

        $response = $this->postJson('/api/notifications/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/endpoint',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function unsubscribe_returns_false_when_not_found(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(WebPushService::class);
        $mockService->shouldReceive('deleteSubscription')
            ->once()
            ->andReturn(false);

        $this->app->instance(WebPushService::class, $mockService);

        $response = $this->postJson('/api/notifications/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/nonexistent',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function unsubscribe_validates_endpoint(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/notifications/push/unsubscribe', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint']);
    }

    // =====================================================
    // TELEGRAM BOT INFO
    // =====================================================

    /** @test */
    public function it_can_get_telegram_bot_info(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('getMe')
            ->once()
            ->andReturn([
                'id' => 123456789,
                'username' => 'test_bot',
                'first_name' => 'Test Bot',
            ]);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->getJson('/api/notifications/telegram/bot');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'bot' => [
                    'username' => 'test_bot',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'bot',
                'subscribe_link',
            ]);
    }

    /** @test */
    public function telegram_bot_returns_error_when_not_configured(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('getMe')
            ->once()
            ->andReturn(null);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->getJson('/api/notifications/telegram/bot');

        $response->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    // =====================================================
    // TELEGRAM SUBSCRIBE LINK
    // =====================================================

    /** @test */
    public function it_can_get_telegram_subscribe_link(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('getMe')
            ->once()
            ->andReturn([
                'username' => 'test_bot',
            ]);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->getJson('/api/notifications/telegram/subscribe-link?phone=79001234567');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'bot_username' => 'test_bot',
            ])
            ->assertJsonStructure([
                'success',
                'link',
                'bot_username',
            ]);

        $link = $response->json('link');
        $this->assertStringContainsString('phone_79001234567', $link);
    }

    /** @test */
    public function telegram_subscribe_link_with_customer_id(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('getMe')
            ->once()
            ->andReturn([
                'username' => 'test_bot',
            ]);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->getJson('/api/notifications/telegram/subscribe-link?customer_id=123');

        $response->assertOk();

        $link = $response->json('link');
        $this->assertStringContainsString('customer_123', $link);
    }

    /** @test */
    public function telegram_subscribe_link_error_when_bot_not_configured(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('getMe')
            ->once()
            ->andReturn(null);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->getJson('/api/notifications/telegram/subscribe-link');

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Telegram бот не настроен',
            ]);
    }

    // =====================================================
    // TELEGRAM SET WEBHOOK
    // =====================================================

    /** @test */
    public function it_can_set_telegram_webhook(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('setWebhook')
            ->once()
            ->andReturn(true);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->postJson('/api/notifications/telegram/set-webhook', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function set_telegram_webhook_returns_error_on_failure(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('setWebhook')
            ->once()
            ->andReturn(false);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->postJson('/api/notifications/telegram/set-webhook', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    // =====================================================
    // TELEGRAM WEBHOOK
    // =====================================================

    /** @test */
    public function it_handles_telegram_webhook(): void
    {
        $secret = 'test-webhook-secret-123';
        config(['services.telegram.webhook_secret' => $secret]);

        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('handleWebhook')
            ->once()
            ->andReturn(null);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 123456,
            'message' => [
                'chat' => ['id' => 123],
                'text' => 'Hello',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    // =====================================================
    // SEND TEST NOTIFICATION
    // =====================================================

    /** @test */
    public function it_can_send_test_notification(): void
    {
        $this->authenticate();
        $mockService = Mockery::mock(\App\Services\NotificationService::class);
        $mockService->shouldReceive('sendTestNotification')
            ->once()
            ->andReturn([
                'web_push' => true,
                'telegram' => false,
            ]);

        $this->app->instance(\App\Services\NotificationService::class, $mockService);

        $response = $this->postJson('/api/notifications/test', [
            'phone' => '79001234567',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'results',
            ]);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    /** @test */
    public function staff_notifications_require_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications');
        $response->assertStatus(401);
    }

    /** @test */
    public function unread_count_requires_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications/unread-count');
        $response->assertStatus(401);
    }

    /** @test */
    public function mark_as_read_requires_authentication(): void
    {
        $notification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
            'message' => 'Test',
            'channels' => ['in_app'],
        ]);

        $response = $this->postJson("/api/staff-notifications/{$notification->id}/read");
        $response->assertStatus(401);
    }

    /** @test */
    public function settings_require_authentication(): void
    {
        $response = $this->getJson('/api/staff-notifications/settings');
        $response->assertStatus(401);
    }

    // =====================================================
    // NOTIFICATION MODEL TESTS
    // =====================================================

    /** @test */
    public function notification_is_read_accessor_works(): void
    {
        $unreadNotification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Unread',
            'message' => 'Not read yet',
            'channels' => ['in_app'],
        ]);

        $readNotification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Read',
            'message' => 'Already read',
            'channels' => ['in_app'],
            'read_at' => now(),
        ]);

        $this->assertFalse($unreadNotification->is_read);
        $this->assertTrue($readNotification->is_read);
    }

    /** @test */
    public function notification_mark_as_read_method_works(): void
    {
        $notification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
            'message' => 'Test',
            'channels' => ['in_app'],
        ]);

        $this->assertNull($notification->read_at);

        $notification->markAsRead();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function notification_scopes_work_correctly(): void
    {
        // Create read notification
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Read',
            'message' => 'Read notification',
            'channels' => ['in_app'],
            'read_at' => now(),
        ]);

        // Create unread notification
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SALARY_PAID,
            'title' => 'Unread',
            'message' => 'Unread notification',
            'channels' => ['in_app'],
        ]);

        $unreadCount = Notification::forUser($this->waiter->id)->unread()->count();
        $readCount = Notification::forUser($this->waiter->id)->read()->count();
        $shiftReminderCount = Notification::forUser($this->waiter->id)->ofType(Notification::TYPE_SHIFT_REMINDER)->count();

        $this->assertEquals(1, $unreadCount);
        $this->assertEquals(1, $readCount);
        $this->assertEquals(1, $shiftReminderCount);
    }

    /** @test */
    public function notification_type_labels_are_defined(): void
    {
        $this->assertEquals('Напоминание о смене', Notification::getTypeLabel(Notification::TYPE_SHIFT_REMINDER));
        $this->assertEquals('Зарплата выплачена', Notification::getTypeLabel(Notification::TYPE_SALARY_PAID));
        $this->assertEquals('Системное', Notification::getTypeLabel(Notification::TYPE_SYSTEM));
    }
}
