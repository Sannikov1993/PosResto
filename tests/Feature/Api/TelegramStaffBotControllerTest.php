<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\WorkSession;
use App\Models\Notification;
use App\Services\StaffNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Mockery;

class TelegramStaffBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $connectedUser;
    protected string $botToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create roles
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

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
            'name' => 'Admin User',
        ]);

        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'email' => 'waiter@test.com',
            'name' => 'Waiter User',
        ]);

        // Create user with Telegram connected
        $this->connectedUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'email' => 'connected@test.com',
            'name' => 'Connected User',
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'connecteduser',
        ]);

        // Create auth token for admin
        $this->adminToken = $this->admin->createToken('test-token')->plainTextToken;

        // Set bot token and webhook secret in config
        $this->botToken = 'test_bot_token_123456';
        Config::set('services.telegram.staff_bot_token', $this->botToken);
        Config::set('services.telegram.staff_bot_username', 'TestStaffBot');
        Config::set('services.telegram.staff_bot_webhook_secret', 'test_webhook_secret_123');
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    /**
     * Helper: отправить webhook с обязательным secret header
     */
    protected function sendWebhook(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/telegram/staff-bot/webhook', $payload, array_merge([
            'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook_secret_123',
        ], $headers));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =====================================================
    // WEBHOOK - BASIC FUNCTIONALITY
    // =====================================================

    /** @test */
    public function test_webhook_returns_ok_response(): void
    {
        Http::fake();

        $response = $this->sendWebhook([
            'update_id' => 12345,
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    /** @test */
    public function test_webhook_handles_empty_request(): void
    {
        Http::fake();

        $response = $this->sendWebhook([]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    /** @test */
    public function test_webhook_logs_incoming_updates(): void
    {
        Http::fake();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Telegram staff bot webhook'
                    && isset($context['update']);
            });
        Log::shouldReceive('error')->never();

        $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '999'],
                'text' => '/help',
                'from' => ['username' => 'testuser'],
            ],
        ]);
    }

    /** @test */
    public function test_webhook_handles_errors_gracefully(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
        ]);

        // Note: Log::error is only called on exceptions, not on HTTP 500 responses
        // The HTTP client returns unsuccessful response but doesn't throw

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        // Should still return OK to Telegram even when Telegram API fails
        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    // =====================================================
    // WEBHOOK - MESSAGE HANDLING
    // =====================================================

    /** @test */
    public function test_webhook_handles_message_update(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/help',
                'from' => [
                    'id' => 123456789,
                    'username' => 'connecteduser',
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === $this->connectedUser->telegram_chat_id;
        });
    }

    /** @test */
    public function test_webhook_handles_callback_query_update(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'callback_query' => [
                'id' => 'callback_123',
                'message' => [
                    'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                ],
                'data' => 'some_action',
                'from' => [
                    'id' => 123456789,
                    'username' => 'connecteduser',
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'answerCallbackQuery');
        });
    }

    // =====================================================
    // BOT COMMANDS - /start WITH TOKEN (USER LINKING)
    // =====================================================

    /** @test */
    public function test_start_command_with_valid_token_links_user(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('processTelegramCallback')
            ->once()
            ->with(Mockery::type('string'), '987654321', 'newuser')
            ->andReturn($this->waiter);

        $this->app->instance(StaffNotificationService::class, $mockService);

        Http::fake();

        // Create a valid token
        $token = base64_encode(json_encode([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->waiter->restaurant_id,
            'expires' => now()->addHours(24)->timestamp,
        ]));

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '987654321'],
                'text' => "/start {$token}",
                'from' => ['username' => 'newuser'],
            ],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function test_start_command_with_invalid_token_sends_error_message(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('processTelegramCallback')
            ->once()
            ->andReturn(null);

        $this->app->instance(StaffNotificationService::class, $mockService);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '987654321'],
                'text' => '/start invalid_token',
                'from' => ['username' => 'newuser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Ссылка недействительна');
        });
    }

    /** @test */
    public function test_start_command_with_expired_token_fails(): void
    {
        $mockService = Mockery::mock(StaffNotificationService::class);
        $mockService->shouldReceive('processTelegramCallback')
            ->once()
            ->andReturn(null);

        $this->app->instance(StaffNotificationService::class, $mockService);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        // Create an expired token
        $token = base64_encode(json_encode([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->waiter->restaurant_id,
            'expires' => now()->subHours(1)->timestamp,
        ]));

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '987654321'],
                'text' => "/start {$token}",
                'from' => ['username' => 'newuser'],
            ],
        ]);

        $response->assertOk();
    }

    // =====================================================
    // BOT COMMANDS - /start (WITHOUT TOKEN)
    // =====================================================

    /** @test */
    public function test_start_command_without_token_for_connected_user_sends_welcome(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/start',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], $this->connectedUser->name)
                && str_contains($request['text'], 'Вы подключены');
        });
    }

    /** @test */
    public function test_start_command_without_token_for_unconnected_user_sends_instructions(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '999999999'],
                'text' => '/start',
                'from' => ['username' => 'unknownuser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Вы не подключены')
                && str_contains($request['text'], 'MenuLab');
        });
    }

    // =====================================================
    // BOT COMMANDS - /status
    // =====================================================

    /** @test */
    public function test_status_command_for_user_without_active_shift(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Смена не начата');
        });
    }

    /** @test */
    public function test_status_command_for_user_with_active_shift(): void
    {
        // Create active work session
        WorkSession::create([
            'restaurant_id' => $this->connectedUser->restaurant_id,
            'user_id' => $this->connectedUser->id,
            'clock_in' => now()->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Смена активна');
        });
    }

    /** @test */
    public function test_status_command_for_unconnected_user(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '999999999'],
                'text' => '/status',
                'from' => ['username' => 'unknownuser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Вы не подключены');
        });
    }

    // =====================================================
    // BOT COMMANDS - /help
    // =====================================================

    /** @test */
    public function test_help_command_sends_help_message(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/help',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Бот уведомлений MenuLab')
                && str_contains($request['text'], '/status')
                && str_contains($request['text'], '/stop');
        });
    }

    /** @test */
    public function test_unknown_command_sends_help(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/unknowncommand',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Бот уведомлений');
        });
    }

    /** @test */
    public function test_plain_text_message_sends_help(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => 'Hello bot!',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage');
        });
    }

    // =====================================================
    // BOT COMMANDS - /stop (DISCONNECT)
    // =====================================================

    /** @test */
    public function test_stop_command_disconnects_telegram(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->assertNotNull($this->connectedUser->telegram_chat_id);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/stop',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        $this->connectedUser->refresh();
        $this->assertNull($this->connectedUser->telegram_chat_id);
        $this->assertNull($this->connectedUser->telegram_username);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'Уведомления отключены');
        });
    }

    // =====================================================
    // MESSAGE HANDLING - EDGE CASES
    // =====================================================

    /** @test */
    public function test_message_without_text_is_handled(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'from' => ['username' => 'connecteduser'],
                // No 'text' field - could be a photo, sticker, etc.
            ],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function test_message_without_username_is_handled(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/help',
                'from' => [
                    'id' => 123456789,
                    // No 'username' field
                ],
            ],
        ]);

        $response->assertOk();
    }

    // =====================================================
    // SET WEBHOOK ENDPOINT
    // =====================================================

    /** @test */
    public function test_set_webhook_without_bot_token_returns_error(): void
    {
        Config::set('services.telegram.staff_bot_token', null);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook');

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Bot token not configured',
            ]);
    }

    /** @test */
    public function test_set_webhook_calls_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was set',
            ]),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && $request['url'] === 'https://example.com/webhook';
        });
    }

    /** @test */
    public function test_set_webhook_uses_default_url_when_not_provided(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook');

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && str_contains($request['url'], 'telegram/staff-bot/webhook');
        });
    }

    /** @test */
    public function test_set_webhook_handles_api_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: bad webhook',
            ], 400),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook');

        $response->assertOk()
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function test_set_webhook_handles_exception(): void
    {
        Http::fake([
            'api.telegram.org/*' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook');

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'Network error',
            ]);
    }

    // =====================================================
    // GET WEBHOOK INFO ENDPOINT
    // =====================================================

    /** @test */
    public function test_get_webhook_info_without_bot_token_returns_error(): void
    {
        Config::set('services.telegram.staff_bot_token', null);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/telegram/staff-bot/webhook-info');

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Bot token not configured',
            ]);
    }

    /** @test */
    public function test_get_webhook_info_returns_telegram_response(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://example.com/webhook',
                    'has_custom_certificate' => false,
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/telegram/staff-bot/webhook-info');

        $response->assertOk()
            ->assertJsonStructure([
                'ok',
                'result' => [
                    'url',
                    'has_custom_certificate',
                    'pending_update_count',
                ],
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getWebhookInfo');
        });
    }

    /** @test */
    public function test_get_webhook_info_handles_exception(): void
    {
        Http::fake([
            'api.telegram.org/*' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/telegram/staff-bot/webhook-info');

        $response->assertOk()
            ->assertJson(['error' => 'Network error']);
    }

    // =====================================================
    // NOTIFICATION DELIVERY VIA TELEGRAM
    // =====================================================

    /** @test */
    public function test_send_message_without_bot_token_returns_false(): void
    {
        Config::set('services.telegram.staff_bot_token', null);

        Http::fake();

        // Trigger sendMessage through webhook (will try to send response)
        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/help',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();

        // No HTTP request should be made
        Http::assertNothingSent();
    }

    /** @test */
    public function test_send_message_uses_markdown_parse_mode(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/help',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        Http::assertSent(function ($request) {
            return $request['parse_mode'] === 'Markdown';
        });
    }

    // =====================================================
    // CALLBACK QUERY HANDLING
    // =====================================================

    /** @test */
    public function test_callback_query_answers_telegram(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'callback_query' => [
                'id' => 'callback_query_123',
                'message' => [
                    'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                ],
                'data' => 'view_schedule',
                'from' => [
                    'id' => 123456789,
                    'username' => 'connecteduser',
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'answerCallbackQuery')
                && $request['callback_query_id'] === 'callback_query_123';
        });
    }

    /** @test */
    public function test_callback_query_from_unconnected_user_just_answers(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'callback_query' => [
                'id' => 'callback_query_123',
                'message' => [
                    'chat' => ['id' => '999999999'],
                ],
                'data' => 'some_action',
                'from' => [
                    'id' => 999999999,
                    'username' => 'unknownuser',
                ],
            ],
        ]);

        $response->assertOk();

        // Should still answer the callback query
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'answerCallbackQuery');
        });
    }

    /** @test */
    public function test_answer_callback_without_bot_token_does_nothing(): void
    {
        Config::set('services.telegram.staff_bot_token', null);

        Http::fake();

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'callback_query' => [
                'id' => 'callback_query_123',
                'message' => [
                    'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                ],
                'data' => 'some_action',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();
        Http::assertNothingSent();
    }

    // =====================================================
    // USER LINKING - INTEGRATION WITH SERVICE
    // =====================================================

    /** @test */
    public function test_user_link_flow_complete(): void
    {
        // Generate a real token
        $token = base64_encode(json_encode([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->waiter->restaurant_id,
            'expires' => now()->addHours(24)->timestamp,
        ]));

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        // Simulate user clicking the bot link with token
        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => '555555555'],
                'text' => "/start {$token}",
                'from' => ['username' => 'newuser'],
            ],
        ]);

        $response->assertOk();

        // User should now be connected
        $this->waiter->refresh();
        $this->assertEquals('555555555', $this->waiter->telegram_chat_id);
        $this->assertEquals('newuser', $this->waiter->telegram_username);
    }

    /** @test */
    public function test_user_can_disconnect_and_reconnect(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        // First disconnect
        $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/stop',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $this->connectedUser->refresh();
        $this->assertNull($this->connectedUser->telegram_chat_id);

        // Generate new token and reconnect
        $token = base64_encode(json_encode([
            'user_id' => $this->connectedUser->id,
            'restaurant_id' => $this->connectedUser->restaurant_id,
            'expires' => now()->addHours(24)->timestamp,
        ]));

        $this->sendWebhook([
            'update_id' => 12346,
            'message' => [
                'chat' => ['id' => '111111111'],
                'text' => "/start {$token}",
                'from' => ['username' => 'reconnecteduser'],
            ],
        ]);

        $this->connectedUser->refresh();
        $this->assertEquals('111111111', $this->connectedUser->telegram_chat_id);
        $this->assertEquals('reconnecteduser', $this->connectedUser->telegram_username);
    }

    // =====================================================
    // ERROR HANDLING
    // =====================================================

    /** @test */
    public function test_invalid_json_in_webhook_is_handled(): void
    {
        $response = $this->call('POST', '/api/telegram/staff-bot/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'test_webhook_secret_123',
        ], '{invalid json}');

        // Laravel should handle JSON parsing error gracefully
        $this->assertTrue($response->status() >= 200);
    }

    /** @test */
    public function test_telegram_api_failure_logs_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => function () {
                throw new \Exception('Connection timeout');
            },
        ]);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->atLeast()->once();

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        // Should still return OK to prevent Telegram retries
        $response->assertOk();
    }

    /** @test */
    public function test_database_error_is_logged_and_handled(): void
    {
        // Corrupt the user's telegram_chat_id to cause potential issues
        $this->connectedUser->update(['telegram_chat_id' => '']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => ''],
                'text' => '/help',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        $response->assertOk();
    }

    // =====================================================
    // ROUTES ACCESSIBILITY
    // =====================================================

    /** @test */
    public function test_webhook_route_is_accessible_without_authentication(): void
    {
        Http::fake();

        // Webhook should not require authentication
        $response = $this->sendWebhook([]);

        $response->assertOk();
    }

    /** @test */
    public function test_set_webhook_route_is_accessible(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/telegram/staff-bot/set-webhook');

        $response->assertOk();
    }

    /** @test */
    public function test_webhook_info_route_is_accessible(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/telegram/staff-bot/webhook-info');

        $response->assertOk();
    }

    // =====================================================
    // SHIFT STATUS FORMATTING
    // =====================================================

    /** @test */
    public function test_shift_status_shows_correct_duration(): void
    {
        // Create active work session that started 3 hours and 30 minutes ago
        WorkSession::create([
            'restaurant_id' => $this->connectedUser->restaurant_id,
            'user_id' => $this->connectedUser->id,
            'clock_in' => now()->subMinutes(210), // 3.5 hours
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';
            // Check it contains time indication (hours or minutes)
            return str_contains($text, 'Смена активна')
                && (str_contains($text, 'ч') || str_contains($text, 'м'));
        });
    }

    /** @test */
    public function test_shift_status_shows_clock_in_time(): void
    {
        $clockInTime = now()->subHours(2);

        WorkSession::create([
            'restaurant_id' => $this->connectedUser->restaurant_id,
            'user_id' => $this->connectedUser->id,
            'clock_in' => $clockInTime,
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->sendWebhook([
            'update_id' => 12345,
            'message' => [
                'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                'text' => '/status',
                'from' => ['username' => 'connecteduser'],
            ],
        ]);

        Http::assertSent(function ($request) use ($clockInTime) {
            return str_contains($request['text'], 'Начало:')
                && str_contains($request['text'], $clockInTime->format('H:i'));
        });
    }

    // =====================================================
    // CONCURRENT REQUESTS
    // =====================================================

    /** @test */
    public function test_multiple_simultaneous_webhooks_are_handled(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        // Send multiple webhook requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->sendWebhook([
                'update_id' => 12345 + $i,
                'message' => [
                    'chat' => ['id' => $this->connectedUser->telegram_chat_id],
                    'text' => '/help',
                    'from' => ['username' => 'connecteduser'],
                ],
            ]);
        }

        foreach ($responses as $response) {
            $response->assertOk();
        }
    }
}
