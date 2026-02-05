<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->restaurant = Restaurant::factory()->create(['name' => 'Test Restaurant']);
    }

    // ==================== RETRY FAILED NOTIFICATIONS ====================

    /** @test */
    public function retry_command_finds_due_notifications(): void
    {
        // Due for retry
        $dueLog = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
            'channel_data' => [
                'chat_id' => '123456',
                'message' => 'Test message',
            ],
        ]);

        // Not due yet
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->addHour(),
        ]);

        $this->artisan('notifications:retry')
            ->expectsOutput('Found 1 notifications to retry.')
            ->assertSuccessful();

        $dueLog->refresh();
        $this->assertEquals(NotificationLog::STATUS_PENDING, $dueLog->status);

        Queue::assertPushed(SendTelegramNotificationJob::class, 1);
    }

    /** @test */
    public function retry_command_respects_limit(): void
    {
        // Create 5 notifications due for retry
        for ($i = 0; $i < 5; $i++) {
            NotificationLog::create([
                'restaurant_id' => $this->restaurant->id,
                'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
                'channel' => NotificationLog::CHANNEL_TELEGRAM,
                'status' => NotificationLog::STATUS_FAILED,
                'attempts' => 1,
                'max_attempts' => 3,
                'next_retry_at' => now()->subMinute(),
                'channel_data' => ['chat_id' => "12345{$i}", 'message' => 'Test'],
            ]);
        }

        $this->artisan('notifications:retry --limit=2')
            ->assertSuccessful();

        Queue::assertPushed(SendTelegramNotificationJob::class, 2);
    }

    /** @test */
    public function retry_command_dry_run_shows_what_would_retry(): void
    {
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
            'error_message' => 'Connection timeout',
        ]);

        $this->artisan('notifications:retry --dry-run')
            ->expectsOutput('Found 1 notifications to retry.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    /** @test */
    public function retry_command_handles_empty_queue(): void
    {
        $this->artisan('notifications:retry')
            ->expectsOutput('No notifications due for retry.')
            ->assertSuccessful();
    }

    /** @test */
    public function retry_command_skips_max_attempts_reached(): void
    {
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 3,
            'max_attempts' => 3, // Max reached
            'next_retry_at' => now()->subMinute(),
        ]);

        $this->artisan('notifications:retry')
            ->expectsOutput('No notifications due for retry.')
            ->assertSuccessful();
    }

    /** @test */
    public function retry_command_handles_email_notifications(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
            'recipient_email' => 'test@example.com',
            'subject' => 'Test Subject',
            'channel_data' => ['html_content' => '<p>Test</p>'],
        ]);

        $this->artisan('notifications:retry')
            ->assertSuccessful();

        $log->refresh();
        $this->assertEquals(NotificationLog::STATUS_PENDING, $log->status);

        Queue::assertPushed(SendEmailNotificationJob::class);
    }

    /** @test */
    public function retry_command_resets_error_message_on_retry(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
            'error_message' => 'Previous error message',
            'channel_data' => ['chat_id' => '123', 'message' => 'Test'],
        ]);

        $this->artisan('notifications:retry')
            ->assertSuccessful();

        $log->refresh();
        $this->assertNull($log->error_message);
        $this->assertNull($log->next_retry_at);
    }

    /** @test */
    public function retry_command_only_processes_failed_status(): void
    {
        // Pending (should not be retried)
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // Delivered (should not be retried)
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_DELIVERED,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $this->artisan('notifications:retry')
            ->expectsOutput('No notifications due for retry.')
            ->assertSuccessful();
    }
}
