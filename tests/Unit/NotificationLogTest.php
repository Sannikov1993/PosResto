<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\NotificationLog;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLogTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    /** @test */
    public function it_creates_notification_log_with_pending_status(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'recipient_phone' => '+79001234567',
            'recipient_name' => 'Test Guest',
        ]);

        $this->assertEquals(NotificationLog::STATUS_PENDING, $log->status);
        $this->assertEquals(0, $log->attempts);
        $this->assertEquals(3, $log->max_attempts);
    }

    /** @test */
    public function it_marks_notification_as_sent(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CREATED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        $log->markSent(['telegram_message_id' => 12345]);

        $this->assertEquals(NotificationLog::STATUS_SENT, $log->status);
        $this->assertEquals(1, $log->attempts);
        $this->assertNotNull($log->last_attempt_at);
        $this->assertEquals(12345, $log->channel_data['telegram_message_id']);
    }

    /** @test */
    public function it_marks_notification_as_delivered(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_SENT,
        ]);

        $log->markDelivered(['sent_at' => now()->toIso8601String()]);

        $this->assertEquals(NotificationLog::STATUS_DELIVERED, $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    /** @test */
    public function it_marks_notification_as_failed_with_retry(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $log->markFailed('Connection timeout', true);

        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertEquals('Connection timeout', $log->error_message);
        $this->assertEquals(1, $log->attempts);
        $this->assertNotNull($log->next_retry_at);
        // First retry should be in ~5 minutes
        $this->assertTrue($log->next_retry_at->isFuture());
        $this->assertTrue($log->next_retry_at->diffInMinutes(now()) <= 6);
    }

    /** @test */
    public function it_marks_notification_as_failed_without_retry(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        $log->markFailed('Invalid chat_id', false);

        $this->assertEquals(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertNull($log->next_retry_at);
    }

    /** @test */
    public function it_does_not_schedule_retry_when_max_attempts_reached(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'attempts' => 2,
            'max_attempts' => 3,
        ]);

        $log->markFailed('Still failing', true);

        $this->assertEquals(3, $log->attempts);
        $this->assertNull($log->next_retry_at); // No more retries
    }

    /** @test */
    public function it_can_check_if_retry_is_allowed(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $this->assertTrue($log->canRetry());

        $log->update(['attempts' => 3]);
        $this->assertFalse($log->canRetry());

        $log->update(['attempts' => 1, 'status' => NotificationLog::STATUS_DELIVERED]);
        $this->assertFalse($log->canRetry());
    }

    /** @test */
    public function it_can_check_if_due_for_retry(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(), // Past
        ]);

        $this->assertTrue($log->isDueForRetry());

        $log->update(['next_retry_at' => now()->addHour()]); // Future
        $this->assertFalse($log->isDueForRetry());

        $log->update(['next_retry_at' => null]);
        $this->assertFalse($log->isDueForRetry());
    }

    /** @test */
    public function it_resets_for_retry(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'error_message' => 'Previous error',
            'next_retry_at' => now(),
        ]);

        $log->resetForRetry();

        $this->assertEquals(NotificationLog::STATUS_PENDING, $log->status);
        $this->assertNull($log->error_message);
        $this->assertNull($log->next_retry_at);
    }

    /** @test */
    public function it_uses_exponential_backoff_for_retries(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // First failure: 5 minutes
        $log->markFailed('Error 1', true);
        $this->assertTrue($log->next_retry_at->isFuture());
        $this->assertEqualsWithDelta(5, abs($log->next_retry_at->diffInMinutes(now())), 1);

        // Reset and second failure: 15 minutes
        $log->resetForRetry();
        $log->markFailed('Error 2', true);
        $this->assertTrue($log->next_retry_at->isFuture());
        $this->assertEqualsWithDelta(15, abs($log->next_retry_at->diffInMinutes(now())), 1);

        // Reset and third failure: no retry (max attempts)
        $log->resetForRetry();
        $log->markFailed('Error 3', true);
        $this->assertNull($log->next_retry_at);
    }

    /** @test */
    public function it_scopes_due_for_retry(): void
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

        // Max attempts reached
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_FAILED,
            'attempts' => 3,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);

        // Already delivered
        NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_REMINDER,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'status' => NotificationLog::STATUS_DELIVERED,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $dueForRetry = NotificationLog::dueForRetry()->get();

        $this->assertCount(1, $dueForRetry);
        $this->assertEquals($dueLog->id, $dueForRetry->first()->id);
    }

    /** @test */
    public function it_returns_correct_display_attributes(): void
    {
        $log = NotificationLog::create([
            'restaurant_id' => $this->restaurant->id,
            'notification_type' => NotificationLog::TYPE_RESERVATION_CONFIRMED,
            'channel' => NotificationLog::CHANNEL_TELEGRAM,
            'recipient_name' => 'John Doe',
        ]);

        $this->assertEquals('John Doe', $log->recipient_display);
        $this->assertEquals('Бронь подтверждена', $log->type_display);
        $this->assertEquals('Telegram', $log->channel_display);
    }
}
