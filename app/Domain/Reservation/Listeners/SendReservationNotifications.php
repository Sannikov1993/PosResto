<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Listeners;

use App\Domain\Reservation\Events\DepositPaid;
use App\Domain\Reservation\Events\ReservationCancelled;
use App\Domain\Reservation\Events\ReservationConfirmed;
use App\Domain\Reservation\Events\ReservationCreated;
use App\Domain\Reservation\Events\ReservationNoShow;
use App\Domain\Reservation\Events\ReservationSeated;
use App\Services\ReservationNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications for reservation events.
 *
 * Queued listener for async notification delivery.
 * Uses ReservationNotificationService for unified notification handling:
 * - Guest notifications via email/Telegram based on preferences
 * - Staff notifications via restaurant Telegram group
 * - Automatic logging to notification_logs table
 */
class SendReservationNotifications implements ShouldQueue
{
    /**
     * The queue name.
     */
    public string $queue = 'notifications';

    public function __construct(
        protected ReservationNotificationService $notificationService,
    ) {}

    /**
     * Handle reservation created event.
     */
    public function handleCreated(ReservationCreated $event): void
    {
        try {
            $results = $this->notificationService->notifyCreated($event->reservation);

            Log::info('ReservationCreated: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservationCreated: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reservation confirmed event.
     */
    public function handleConfirmed(ReservationConfirmed $event): void
    {
        try {
            $results = $this->notificationService->notifyConfirmed($event->reservation);

            Log::info('ReservationConfirmed: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservationConfirmed: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reservation cancelled event.
     */
    public function handleCancelled(ReservationCancelled $event): void
    {
        try {
            $depositRefunded = $event->metadata['deposit_refunded'] ?? false;
            $results = $this->notificationService->notifyCancelled($event->reservation, $depositRefunded);

            Log::info('ReservationCancelled: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'deposit_refunded' => $depositRefunded,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservationCancelled: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle deposit paid event.
     */
    public function handleDepositPaid(DepositPaid $event): void
    {
        try {
            $results = $this->notificationService->notifyDepositPaid($event->reservation);

            Log::info('DepositPaid: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'amount' => $event->reservation->deposit,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('DepositPaid: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reservation seated event.
     */
    public function handleSeated(ReservationSeated $event): void
    {
        try {
            $results = $this->notificationService->notifySeated($event->reservation);

            Log::info('ReservationSeated: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservationSeated: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reservation no-show event.
     */
    public function handleNoShow(ReservationNoShow $event): void
    {
        try {
            $depositForfeited = $event->depositForfeited;
            $results = $this->notificationService->notifyNoShow($event->reservation, $depositForfeited);

            Log::info('ReservationNoShow: Notifications sent', [
                'reservation_id' => $event->reservation->id,
                'deposit_forfeited' => $depositForfeited,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReservationNoShow: Notification failed', [
                'reservation_id' => $event->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Subscribe to multiple events.
     *
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events): array
    {
        return [
            ReservationCreated::class => 'handleCreated',
            ReservationConfirmed::class => 'handleConfirmed',
            ReservationCancelled::class => 'handleCancelled',
            ReservationSeated::class => 'handleSeated',
            ReservationNoShow::class => 'handleNoShow',
            DepositPaid::class => 'handleDepositPaid',
        ];
    }
}
