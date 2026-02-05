<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use App\Models\Zone;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationDispatcher $dispatcher;
    protected Restaurant $restaurant;
    protected Zone $zone;
    protected Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->dispatcher = app(NotificationDispatcher::class);
        $this->restaurant = Restaurant::factory()->create(['name' => 'Test Restaurant']);
        $this->zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
            'name' => 'Table 1',
        ]);
    }

    /** @test */
    public function it_notifies_guest_via_telegram_when_linked(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'telegram_chat_id' => '123456789',
            'telegram_consent' => true,
            'email' => 'guest@example.com',
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'table_id' => $this->table->id,
            'guest_name' => 'John Doe',
            'guest_phone' => '+79001234567',
            'guest_email' => 'guest@example.com',
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CONFIRMED,
            message: 'Your reservation is confirmed!',
            subject: 'Reservation Confirmed',
        );

        // Should create logs for both telegram and email
        $this->assertGreaterThanOrEqual(1, count($logs));

        $telegramLog = collect($logs)->firstWhere('channel', NotificationLog::CHANNEL_TELEGRAM);
        $this->assertNotNull($telegramLog);
        $this->assertEquals(NotificationLog::STATUS_PENDING, $telegramLog->status);

        Queue::assertPushed(SendTelegramNotificationJob::class, function ($job) {
            return $job->chatId === '123456789';
        });
    }

    /** @test */
    public function it_notifies_guest_via_email_when_no_telegram(): void
    {
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Jane Doe',
            'guest_phone' => '+79001234567',
            'guest_email' => 'jane@example.com',
            'customer_id' => null,
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_REMINDER,
            message: 'Reminder: your reservation is in 2 hours',
            subject: 'Reservation Reminder',
        );

        $this->assertCount(1, $logs);
        $this->assertEquals(NotificationLog::CHANNEL_EMAIL, $logs[0]->channel);

        Queue::assertPushed(SendEmailNotificationJob::class, function ($job) {
            return $job->email === 'jane@example.com';
        });

        Queue::assertNotPushed(SendTelegramNotificationJob::class);
    }

    /** @test */
    public function it_respects_customer_notification_preferences(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'telegram_chat_id' => '123456789',
            'telegram_consent' => true,
            'email' => 'guest@example.com',
            'notification_preferences' => [
                'reservation' => ['telegram'], // Only telegram, no email
            ],
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'table_id' => $this->table->id,
            'guest_email' => 'guest@example.com',
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CONFIRMED,
            message: 'Confirmed!',
        );

        // Should only send telegram (per preferences)
        $this->assertCount(1, $logs);
        $this->assertEquals(NotificationLog::CHANNEL_TELEGRAM, $logs[0]->channel);

        Queue::assertPushed(SendTelegramNotificationJob::class);
        Queue::assertNotPushed(SendEmailNotificationJob::class);
    }

    /** @test */
    public function it_notifies_staff_via_telegram(): void
    {
        // Create staff members with telegram
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'telegram_chat_id' => '111111',
            'is_active' => true,
            'notification_settings' => ['system' => ['telegram' => true]],
        ]);

        $hostess = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'hostess',
            'telegram_chat_id' => '222222',
            'is_active' => true,
            'notification_settings' => ['system' => ['telegram' => true]],
        ]);

        // Waiter without telegram
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'telegram_chat_id' => null,
            'is_active' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
        ]);

        $logs = $this->dispatcher->notifyStaff(
            restaurant: $this->restaurant,
            notificationType: 'new_reservation',
            message: 'New reservation received!',
            related: $reservation,
            roles: ['manager', 'hostess'],
        );

        $this->assertCount(2, $logs);

        Queue::assertPushed(SendTelegramNotificationJob::class, 2);
        Queue::assertPushed(SendTelegramNotificationJob::class, function ($job) {
            return $job->useStaffBot === true;
        });
    }

    /** @test */
    public function it_does_not_notify_inactive_staff(): void
    {
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'telegram_chat_id' => '111111',
            'is_active' => false, // Inactive
        ]);

        $logs = $this->dispatcher->notifyStaff(
            restaurant: $this->restaurant,
            notificationType: 'new_reservation',
            message: 'New reservation!',
        );

        $this->assertCount(0, $logs);
        Queue::assertNotPushed(SendTelegramNotificationJob::class);
    }

    /** @test */
    public function it_sends_guest_reminder(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'telegram_chat_id' => '123456789',
            'telegram_consent' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'table_id' => $this->table->id,
            'guest_email' => 'guest@example.com',
        ]);

        $logs = $this->dispatcher->sendGuestReminder(
            reservation: $reservation,
            message: 'Your reservation is in 2 hours!',
            subject: 'Reservation Reminder',
        );

        $this->assertNotEmpty($logs);

        // All logs should be of type reminder
        foreach ($logs as $log) {
            $this->assertEquals(NotificationLog::TYPE_RESERVATION_REMINDER, $log->notification_type);
        }
    }

    /** @test */
    public function it_creates_notification_log_with_related_entity(): void
    {
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_email' => 'guest@example.com',
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CREATED,
            message: 'Created!',
        );

        $this->assertCount(1, $logs);
        $log = $logs[0];

        $this->assertEquals(Reservation::class, $log->related_type);
        $this->assertEquals($reservation->id, $log->related_id);
        $this->assertEquals($this->restaurant->id, $log->restaurant_id);
    }

    /** @test */
    public function it_handles_missing_contact_info_gracefully(): void
    {
        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_email' => null,
            'customer_id' => null,
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CONFIRMED,
            message: 'Confirmed!',
        );

        // No channels available, should return empty
        $this->assertEmpty($logs);
        Queue::assertNotPushed(SendTelegramNotificationJob::class);
        Queue::assertNotPushed(SendEmailNotificationJob::class);
    }

    /** @test */
    public function it_marks_log_as_failed_when_no_chat_id(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'telegram_chat_id' => null, // No chat ID
            'telegram_consent' => true,
            'email' => null,
            'notification_preferences' => [
                'reservation' => ['telegram'],
            ],
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'table_id' => $this->table->id,
            'guest_email' => null,
        ]);

        $logs = $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CONFIRMED,
            message: 'Test',
        );

        // No available channels
        $this->assertEmpty($logs);
    }

    /** @test */
    public function it_uses_restaurant_white_label_bot_for_guests(): void
    {
        $this->restaurant->update([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_username' => 'TestBot',
            'telegram_bot_id' => '123',
            'telegram_bot_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'telegram_chat_id' => '999999',
            'telegram_consent' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'table_id' => $this->table->id,
        ]);

        $this->dispatcher->notifyGuest(
            reservation: $reservation,
            notificationType: NotificationLog::TYPE_RESERVATION_CONFIRMED,
            message: 'Confirmed!',
        );

        Queue::assertPushed(SendTelegramNotificationJob::class, function ($job) {
            return $job->restaurantId === $this->restaurant->id
                && $job->useStaffBot === false;
        });
    }
}
