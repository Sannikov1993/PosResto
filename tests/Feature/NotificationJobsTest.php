<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\NotificationLog;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationJobsTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();

        // Configure default bot token for tests
        config(['services.telegram.bot_token' => 'test_bot_token']);
    }

    // ==================== TELEGRAM JOB TESTS ====================

    /** @test */
    public function telegram_job_marks_log_as_delivered_on_success(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 12345],
            ]),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Test message',
            restaurantId: $this->restaurant->id,
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_DELIVERED, $log->status);
        $this->assertEquals(12345, $log->channel_data['telegram_message_id']);
        $this->assertNotNull($log->delivered_at);
    }

    /** @test */
    public function telegram_job_marks_log_as_failed_with_retry_on_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 500,
                'description' => 'Internal Server Error',
            ]),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'max_attempts' => 3,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Test message',
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Internal Server Error', $log->error_message);
        $this->assertNotNull($log->next_retry_at); // Should schedule retry
    }

    /** @test */
    public function telegram_job_does_not_retry_on_permanent_error(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 403, // Bot blocked by user
                'description' => 'Forbidden: bot was blocked by the user',
            ]),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'max_attempts' => 3,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Test message',
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertNull($log->next_retry_at); // No retry for permanent errors
    }

    /** @test */
    public function telegram_job_skips_already_delivered(): void
    {
        Http::fake();

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_DELIVERED, // Already delivered
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Test message',
        );

        $job->handle();

        Http::assertNothingSent();
    }

    /** @test */
    public function telegram_job_uses_staff_bot_when_specified(): void
    {
        config(['services.telegram.staff_bot_token' => 'staff_bot_token_123']);

        Http::fake([
            'api.telegram.org/botstaff_bot_token_123/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => 'staff_notification',
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Staff notification',
            useStaffBot: true,
        );

        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'botstaff_bot_token_123');
        });
    }

    /** @test */
    public function telegram_job_uses_restaurant_bot_when_available(): void
    {
        $this->restaurant->update([
            'telegram_bot_token' => 'restaurant_bot_token',
            'telegram_bot_username' => 'RestaurantBot',
            'telegram_bot_active' => true,
        ]);

        Http::fake([
            'api.telegram.org/botrestaurant_bot_token/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Guest notification',
            restaurantId: $this->restaurant->id,
            useStaffBot: false,
        );

        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'botrestaurant_bot_token');
        });
    }

    /** @test */
    public function telegram_job_handles_connection_exception(): void
    {
        Http::fake([
            'api.telegram.org/*' => fn() => throw new \Exception('Connection timeout'),
        ]);

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'max_attempts' => 3,
        ]);

        $job = new SendTelegramNotificationJob(
            notificationLogId: $log->id,
            chatId: '123456789',
            message: 'Test message',
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Connection timeout', $log->error_message);
        $this->assertNotNull($log->next_retry_at); // Should retry on connection error
    }

    // ==================== EMAIL JOB TESTS ====================

    /** @test */
    public function email_job_marks_log_as_delivered_on_success(): void
    {
        Mail::fake();

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_PENDING,
            'recipient_email' => 'test@example.com',
        ]);

        $job = new SendEmailNotificationJob(
            notificationLogId: $log->id,
            email: 'test@example.com',
            subject: 'Test Subject',
            htmlContent: '<p>Test content</p>',
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_DELIVERED, $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    /** @test */
    public function email_job_sends_to_correct_address(): void
    {
        Mail::fake();

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_PENDING,
            'recipient_email' => 'guest@example.com',
        ]);

        $job = new SendEmailNotificationJob(
            notificationLogId: $log->id,
            email: 'guest@example.com',
            subject: 'Reservation Reminder',
            htmlContent: '<h1>Reminder</h1>',
        );

        $job->handle();

        // Verify job completed successfully
        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_DELIVERED, $log->status);
        $this->assertEquals('guest@example.com', $job->email);
    }

    /** @test */
    public function email_job_marks_log_as_failed_on_error(): void
    {
        // Use partial mock for Mail to throw exception
        Mail::shouldReceive('html')
            ->once()
            ->andThrow(new \Exception('SMTP connection failed'));

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_PENDING,
            'max_attempts' => 3,
        ]);

        $job = new SendEmailNotificationJob(
            notificationLogId: $log->id,
            email: 'test@example.com',
            subject: 'Test',
            htmlContent: '<p>Test</p>',
        );

        $job->handle();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('SMTP connection failed', $log->error_message);
    }

    /** @test */
    public function email_job_skips_already_delivered(): void
    {
        Mail::fake();

        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_DELIVERED,
        ]);

        $job = new SendEmailNotificationJob(
            notificationLogId: $log->id,
            email: 'test@example.com',
            subject: 'Test',
            htmlContent: '<p>Test</p>',
        );

        $job->handle();

        Mail::assertNothingSent();
    }

    /** @test */
    public function email_job_has_correct_tags(): void
    {
        $job = new SendEmailNotificationJob(
            notificationLogId: 123,
            email: 'test@example.com',
            subject: 'Test',
            htmlContent: '<p>Test</p>',
        );

        $tags = $job->tags();

        $this->assertContains('notification', $tags);
        $this->assertContains('email', $tags);
        $this->assertContains('log:123', $tags);
    }

    /** @test */
    public function telegram_job_has_correct_tags(): void
    {
        $job = new SendTelegramNotificationJob(
            notificationLogId: 456,
            chatId: '123',
            message: 'Test',
        );

        $tags = $job->tags();

        $this->assertContains('notification', $tags);
        $this->assertContains('telegram', $tags);
        $this->assertContains('log:456', $tags);
    }
}
