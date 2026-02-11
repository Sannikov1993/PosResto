<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

class TelegramWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    protected function authenticate(): void
    {
        $token = $this->admin->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =====================================================
    // NOTIFICATION CONTROLLER - TELEGRAM WEBHOOK
    // =====================================================

    /** @test */
    public function notification_webhook_rejects_request_without_secret_when_configured(): void
    {
        Config::set('services.telegram.webhook_secret', 'my-secret-token');

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => ['chat' => ['id' => 123], 'text' => 'Hello'],
        ]);

        $response->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    /** @test */
    public function notification_webhook_rejects_request_with_wrong_secret(): void
    {
        Config::set('services.telegram.webhook_secret', 'correct-secret');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['chat' => ['id' => 123], 'text' => 'Hello'],
            ]);

        $response->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    /** @test */
    public function notification_webhook_accepts_request_with_correct_secret(): void
    {
        Config::set('services.telegram.webhook_secret', 'correct-secret');

        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('handleWebhook')
            ->once()
            ->andReturn(null);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'correct-secret')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
                'message' => ['chat' => ['id' => 123], 'text' => 'Hello'],
            ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    /** @test */
    public function notification_webhook_allows_requests_when_no_secret_configured(): void
    {
        Config::set('services.telegram.webhook_secret', null);

        $mockService = Mockery::mock(TelegramService::class);
        $mockService->shouldReceive('handleWebhook')
            ->once()
            ->andReturn(null);

        $this->app->instance(TelegramService::class, $mockService);

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 12345,
            'message' => ['chat' => ['id' => 123], 'text' => 'Hello'],
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    /** @test */
    public function notification_webhook_logs_warning_on_invalid_secret(): void
    {
        Config::set('services.telegram.webhook_secret', 'real-secret');

        Log::shouldReceive('warning')
            ->once()
            ->with('Telegram webhook: invalid secret token');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'bad-secret')
            ->postJson('/api/telegram/webhook', [
                'update_id' => 12345,
            ]);

        $response->assertStatus(403);
    }

    // =====================================================
    // STAFF BOT CONTROLLER - TELEGRAM WEBHOOK
    // =====================================================

    /** @test */
    public function staff_bot_webhook_rejects_request_without_secret_when_configured(): void
    {
        Config::set('services.telegram.staff_bot_webhook_secret', 'staff-secret');
        Config::set('services.telegram.staff_bot_token', 'test_token');

        $response = $this->postJson('/api/telegram/staff-bot/webhook', [
            'update_id' => 12345,
        ]);

        $response->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    /** @test */
    public function staff_bot_webhook_rejects_request_with_wrong_secret(): void
    {
        Config::set('services.telegram.staff_bot_webhook_secret', 'correct-staff-secret');
        Config::set('services.telegram.staff_bot_token', 'test_token');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson('/api/telegram/staff-bot/webhook', [
                'update_id' => 12345,
            ]);

        $response->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    /** @test */
    public function staff_bot_webhook_accepts_request_with_correct_secret(): void
    {
        Config::set('services.telegram.staff_bot_webhook_secret', 'correct-staff-secret');
        Config::set('services.telegram.staff_bot_token', 'test_token');

        Http::fake();

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'correct-staff-secret')
            ->postJson('/api/telegram/staff-bot/webhook', [
                'update_id' => 12345,
            ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    /** @test */
    public function staff_bot_webhook_rejects_requests_when_no_secret_configured(): void
    {
        Config::set('services.telegram.staff_bot_webhook_secret', null);
        Config::set('services.telegram.staff_bot_token', 'test_token');

        Http::fake();

        $response = $this->postJson('/api/telegram/staff-bot/webhook', [
            'update_id' => 12345,
        ]);

        // Без настроенного secret webhook возвращает 500 (misconfigured)
        $response->assertStatus(500)
            ->assertJson(['ok' => false, 'error' => 'misconfigured']);
    }

    /** @test */
    public function staff_bot_webhook_logs_warning_on_invalid_secret(): void
    {
        Config::set('services.telegram.staff_bot_webhook_secret', 'real-staff-secret');
        Config::set('services.telegram.staff_bot_token', 'test_token');

        Log::shouldReceive('warning')
            ->once()
            ->with('Telegram staff bot webhook: invalid secret token');

        $response = $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'bad-secret')
            ->postJson('/api/telegram/staff-bot/webhook', [
                'update_id' => 12345,
            ]);

        $response->assertStatus(403);
    }

    // =====================================================
    // STAFF BOT - SET WEBHOOK WITH SECRET
    // =====================================================

    /** @test */
    public function set_webhook_passes_secret_token_when_configured(): void
    {
        $this->authenticate();
        Config::set('services.telegram.staff_bot_webhook_secret', 'my-webhook-secret');
        Config::set('services.telegram.staff_bot_token', 'test_token');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->postJson('/api/telegram/staff-bot/set-webhook', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && $request['url'] === 'https://example.com/webhook'
                && $request['secret_token'] === 'my-webhook-secret';
        });
    }

    /** @test */
    public function set_webhook_omits_secret_token_when_not_configured(): void
    {
        $this->authenticate();
        Config::set('services.telegram.staff_bot_webhook_secret', null);
        Config::set('services.telegram.staff_bot_token', 'test_token');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $response = $this->postJson('/api/telegram/staff-bot/set-webhook', [
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && !isset($request['secret_token']);
        });
    }
}
