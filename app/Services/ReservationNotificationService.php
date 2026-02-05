<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Recipients\GuestRecipient;
use App\Notifications\Reservation\DepositPaidNotification;
use App\Notifications\Reservation\ReservationCancelledNotification;
use App\Notifications\Reservation\ReservationConfirmedNotification;
use App\Notifications\Reservation\ReservationCreatedNotification;
use App\Notifications\Reservation\ReservationReminderNotification;
use App\Notifications\Staff\DepositPaidStaffNotification;
use App\Notifications\Staff\GuestNoShowNotification;
use App\Notifications\Staff\GuestSeatedNotification;
use App\Notifications\Staff\NewReservationNotification;
use App\Notifications\Staff\ReservationCancelledStaffNotification;
use App\Notifications\Staff\ReservationConfirmedStaffNotification;
use App\Notifications\Staff\UpcomingReservationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Service for sending reservation notifications to guests and staff.
 *
 * Handles:
 * - Guest notifications via email/Telegram based on preferences
 * - Staff notifications via restaurant Telegram group
 * - Logging all notifications to notification_logs table
 *
 * Usage:
 *   $service = app(ReservationNotificationService::class);
 *   $service->notifyCreated($reservation);
 *   $service->notifyConfirmed($reservation);
 */
class ReservationNotificationService
{
    /**
     * Notify about new reservation created.
     */
    public function notifyCreated(Reservation $reservation): array
    {
        return $this->sendDual(
            reservation: $reservation,
            guestNotification: new ReservationCreatedNotification($reservation),
            staffNotification: new NewReservationNotification($reservation),
        );
    }

    /**
     * Notify about reservation confirmed.
     */
    public function notifyConfirmed(Reservation $reservation): array
    {
        return $this->sendDual(
            reservation: $reservation,
            guestNotification: new ReservationConfirmedNotification($reservation),
            staffNotification: new ReservationConfirmedStaffNotification($reservation),
        );
    }

    /**
     * Notify about reservation cancelled.
     */
    public function notifyCancelled(Reservation $reservation, bool $depositRefunded = false): array
    {
        $metadata = ['deposit_refunded' => $depositRefunded];

        return $this->sendDual(
            reservation: $reservation,
            guestNotification: new ReservationCancelledNotification($reservation, $metadata),
            staffNotification: new ReservationCancelledStaffNotification($reservation, $metadata),
        );
    }

    /**
     * Notify about deposit paid.
     */
    public function notifyDepositPaid(Reservation $reservation): array
    {
        return $this->sendDual(
            reservation: $reservation,
            guestNotification: new DepositPaidNotification($reservation),
            staffNotification: new DepositPaidStaffNotification($reservation),
        );
    }

    /**
     * Notify about guest seated (staff only).
     */
    public function notifySeated(Reservation $reservation): array
    {
        return $this->sendToStaff(
            reservation: $reservation,
            notification: new GuestSeatedNotification($reservation),
        );
    }

    /**
     * Notify about guest no-show (staff only).
     */
    public function notifyNoShow(Reservation $reservation, bool $depositForfeited = false): array
    {
        $metadata = ['deposit_forfeited' => $depositForfeited];

        return $this->sendToStaff(
            reservation: $reservation,
            notification: new GuestNoShowNotification($reservation, $metadata),
        );
    }

    /**
     * Send reminder notification.
     */
    public function sendReminder(Reservation $reservation, int $hoursUntil = 2): array
    {
        $metadata = ['hours_until' => $hoursUntil];

        return $this->sendDual(
            reservation: $reservation,
            guestNotification: new ReservationReminderNotification($reservation, $metadata),
            staffNotification: new UpcomingReservationNotification($reservation, ['minutes_until' => $hoursUntil * 60]),
        );
    }

    // ===== INTERNAL METHODS =====

    /**
     * Send notification to both guest and staff.
     */
    protected function sendDual(
        Reservation $reservation,
        Notification $guestNotification,
        Notification $staffNotification,
    ): array {
        $results = [
            'guest' => $this->sendToGuest($reservation, $guestNotification),
            'staff' => $this->sendToStaff($reservation, $staffNotification),
        ];

        return $results;
    }

    /**
     * Send notification to guest.
     */
    protected function sendToGuest(Reservation $reservation, Notification $notification): array
    {
        $results = ['success' => false, 'channels' => []];

        // Get the notifiable (Customer or GuestRecipient)
        $notifiable = $this->getGuestNotifiable($reservation);

        if (!$notifiable) {
            Log::info('ReservationNotificationService: No notifiable for guest', [
                'reservation_id' => $reservation->id,
            ]);
            return $results;
        }

        try {
            // Get channels from notification
            $channels = $notification->via($notifiable);

            foreach ($channels as $channel) {
                $channelResult = $this->sendViaChannel($notifiable, $notification, $channel);
                $channelName = $this->getChannelName($channel);
                $results['channels'][$channelName] = $channelResult;

                // Log to database
                $this->log(
                    reservation: $reservation,
                    notification: $notification,
                    channel: $channelName,
                    recipient: 'guest',
                    success: $channelResult['success'] ?? false,
                    error: $channelResult['error'] ?? null,
                    notifiable: $notifiable,
                );
            }

            $results['success'] = !empty($results['channels']);

        } catch (\Throwable $e) {
            Log::error('ReservationNotificationService: Guest notification failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Send notification to restaurant staff.
     */
    protected function sendToStaff(Reservation $reservation, Notification $notification): array
    {
        $results = ['success' => false, 'channels' => []];

        $chatId = $this->getRestaurantTelegramChatId($reservation);

        if (!$chatId) {
            Log::debug('ReservationNotificationService: No Telegram chat_id for restaurant', [
                'reservation_id' => $reservation->id,
                'restaurant_id' => $reservation->restaurant_id,
            ]);
            return $results;
        }

        try {
            // Create recipient for the restaurant chat
            $recipient = GuestRecipient::make(
                name: $reservation->restaurant?->name ?? 'Restaurant',
                telegramChatId: $chatId,
            );

            $channelResult = $this->sendViaChannel($recipient, $notification, TelegramChannel::class);
            $results['channels']['telegram'] = $channelResult;
            $results['success'] = $channelResult['success'] ?? false;

            // Log to database
            $this->log(
                reservation: $reservation,
                notification: $notification,
                channel: 'telegram',
                recipient: 'staff',
                success: $channelResult['success'] ?? false,
                error: $channelResult['error'] ?? null,
                notifiable: null,
            );

        } catch (\Throwable $e) {
            Log::error('ReservationNotificationService: Staff notification failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Send notification to notifiable.
     */
    protected function sendViaChannel($notifiable, Notification $notification, $channel): array
    {
        try {
            // Use notifiable's notify method which respects via() channels
            $notifiable->notify($notification);
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the notifiable entity for the guest.
     */
    protected function getGuestNotifiable(Reservation $reservation)
    {
        // If reservation has linked customer with contact info, use it
        if ($reservation->customer_id && $reservation->customer) {
            $customer = $reservation->customer;
            if ($customer->canBeNotified()) {
                return $customer;
            }
        }

        // Otherwise create a guest recipient from reservation data
        $guest = GuestRecipient::fromReservation($reservation);

        if ($guest->canBeNotified()) {
            return $guest;
        }

        return null;
    }

    /**
     * Get restaurant's Telegram notification chat ID.
     */
    protected function getRestaurantTelegramChatId(Reservation $reservation): ?string
    {
        $restaurant = $reservation->restaurant;

        if (!$restaurant) {
            return null;
        }

        return $restaurant->getSetting('telegram_notification_chat_id');
    }

    /**
     * Get channel name from channel class or string.
     */
    protected function getChannelName($channel): string
    {
        if ($channel === TelegramChannel::class) {
            return 'telegram';
        }

        return match ($channel) {
            'mail' => 'email',
            'database' => 'database',
            'sms' => 'sms',
            default => is_string($channel) ? $channel : 'unknown',
        };
    }

    /**
     * Log notification to database.
     */
    protected function log(
        Reservation $reservation,
        Notification $notification,
        string $channel,
        string $recipient,
        bool $success,
        ?string $error = null,
        $notifiable = null,
    ): void {
        try {
            $type = method_exists($notification, 'getType')
                ? $notification->getType()
                : class_basename($notification);

            $logData = [
                'restaurant_id' => $reservation->restaurant_id,
                'notification_type' => $type,
                'channel' => $channel,
                'subject' => $recipient === 'staff' ? 'Staff notification' : 'Guest notification',
                'related_type' => Reservation::class,
                'related_id' => $reservation->id,
                'status' => $success ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
                'error_message' => $error,
                'attempts' => 1,
                'last_attempt_at' => now(),
            ];

            // Set notifiable info
            if ($notifiable instanceof Model) {
                $logData['notifiable_type'] = get_class($notifiable);
                $logData['notifiable_id'] = $notifiable->getKey();
            } else {
                $logData['recipient_name'] = $reservation->guest_name;
                $logData['recipient_email'] = $reservation->guest_email;
                $logData['recipient_phone'] = $reservation->guest_phone;
            }

            NotificationLog::create($logData);

        } catch (\Throwable $e) {
            Log::error('ReservationNotificationService: Failed to log notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
