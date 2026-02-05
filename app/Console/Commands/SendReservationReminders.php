<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Send reminder notifications for upcoming reservations.
 *
 * Enterprise features:
 * - Notifies GUESTS via their preferred channels (Telegram, Email)
 * - Notifies STAFF via Telegram staff bot
 * - Full logging via NotificationLog
 * - Retry support for failed notifications
 * - Uses restaurant's white-label bot for guest Telegram
 */
class SendReservationReminders extends Command
{
    protected $signature = 'reservations:send-reminders
                            {--hours=2 : Hours before reservation to send reminder}
                            {--dry-run : Show what would be sent without actually sending}
                            {--guest-only : Only send to guests}
                            {--staff-only : Only send to staff}';

    protected $description = 'Send reminder notifications for upcoming reservations to guests and staff';

    public function __construct(
        protected NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $guestOnly = $this->option('guest-only');
        $staffOnly = $this->option('staff-only');

        $this->info("Looking for reservations starting in ~{$hours} hours...");

        $reservations = $this->getUpcomingReservations($hours);

        if ($reservations->isEmpty()) {
            $this->info('No reservations found to remind.');
            return self::SUCCESS;
        }

        $this->info("Found {$reservations->count()} reservations.");

        $stats = [
            'guest_notified' => 0,
            'staff_notified' => 0,
            'skipped' => 0,
        ];

        foreach ($reservations as $reservation) {
            $this->line('');
            $this->line("Processing reservation #{$reservation->id}: {$reservation->guest_name}");

            // Send to guest
            if (!$staffOnly) {
                $guestResult = $this->sendGuestReminder($reservation, $hours, $dryRun);
                if ($guestResult) {
                    $stats['guest_notified']++;
                }
            }

            // Send to staff
            if (!$guestOnly) {
                $staffResult = $this->sendStaffReminder($reservation, $hours, $dryRun);
                if ($staffResult) {
                    $stats['staff_notified']++;
                }
            }

            // Mark reminder as sent (if not dry run)
            if (!$dryRun && ($stats['guest_notified'] > 0 || $stats['staff_notified'] > 0)) {
                $reservation->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => now(),
                ]);
            }
        }

        $this->line('');
        $this->info("Done. Guests notified: {$stats['guest_notified']}, Staff notified: {$stats['staff_notified']}");

        Log::info('SendReservationReminders: Completed', [
            'hours' => $hours,
            'reservations_processed' => $reservations->count(),
            'stats' => $stats,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get reservations that need reminders.
     */
    protected function getUpcomingReservations(int $hours): \Illuminate\Support\Collection
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->addHours($hours)->subMinutes(10);
        $windowEnd = $now->copy()->addHours($hours)->addMinutes(10);

        // Use database-agnostic concatenation
        $driver = \DB::connection()->getDriverName();
        $concat = $driver === 'sqlite'
            ? "(date || ' ' || time_from)"
            : "CONCAT(date, ' ', time_from)";

        return Reservation::query()
            ->where('status', 'confirmed')
            ->whereDate('date', Carbon::today())
            ->where('reminder_sent', false)
            ->whereRaw("{$concat} BETWEEN ? AND ?", [
                $windowStart->format('Y-m-d H:i:s'),
                $windowEnd->format('Y-m-d H:i:s'),
            ])
            ->with(['table', 'restaurant', 'customer'])
            ->get();
    }

    /**
     * Send reminder to guest via their preferred channels.
     */
    protected function sendGuestReminder(Reservation $reservation, int $hours, bool $dryRun): bool
    {
        $customer = $reservation->customer;
        $hasEmail = !empty($reservation->guest_email) || !empty($customer?->email);
        $hasTelegram = $customer && $customer->hasTelegram();

        if (!$hasEmail && !$hasTelegram) {
            $this->warn("  Guest: No contact channels available");
            return false;
        }

        $message = $this->buildGuestMessage($reservation, $hours);
        $subject = "Напоминание о бронировании";
        $htmlEmail = $this->buildGuestEmailHtml($reservation, $hours);

        if ($dryRun) {
            $channels = [];
            if ($hasTelegram) $channels[] = 'telegram';
            if ($hasEmail) $channels[] = 'email';
            $this->info("  Guest: Would send via " . implode(', ', $channels));
            return true;
        }

        try {
            $logs = $this->dispatcher->sendGuestReminder(
                reservation: $reservation,
                message: $message,
                subject: $subject,
                htmlEmail: $htmlEmail,
            );

            $channelsSent = array_unique(array_map(fn($l) => $l->channel, $logs));
            $this->info("  Guest: Queued via " . implode(', ', $channelsSent));

            return !empty($logs);
        } catch (\Throwable $e) {
            $this->error("  Guest: Error - {$e->getMessage()}");

            Log::error('SendReservationReminders: Guest reminder failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send reminder to restaurant staff.
     */
    protected function sendStaffReminder(Reservation $reservation, int $hours, bool $dryRun): bool
    {
        $restaurant = $reservation->restaurant;

        if (!$restaurant) {
            return false;
        }

        $message = $this->buildStaffMessage($reservation, $hours);

        if ($dryRun) {
            $this->info("  Staff: Would notify restaurant #{$restaurant->id}");
            return true;
        }

        try {
            $logs = $this->dispatcher->notifyStaff(
                restaurant: $restaurant,
                notificationType: 'reservation_reminder',
                message: $message,
                related: $reservation,
                roles: ['owner', 'manager', 'hostess'],
            );

            if (!empty($logs)) {
                $this->info("  Staff: Notified " . count($logs) . " staff members");
            } else {
                $this->warn("  Staff: No staff configured for notifications");
            }

            return !empty($logs);
        } catch (\Throwable $e) {
            $this->error("  Staff: Error - {$e->getMessage()}");

            Log::error('SendReservationReminders: Staff reminder failed', [
                'reservation_id' => $reservation->id,
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build reminder message for guest (Telegram).
     */
    protected function buildGuestMessage(Reservation $reservation, int $hours): string
    {
        $restaurantName = $reservation->restaurant?->name ?? 'ресторан';
        $date = Carbon::parse($reservation->date)->translatedFormat('j F');
        $time = $reservation->time_from;
        $guests = $reservation->guests_count;
        $table = $reservation->table?->name ?? '';

        $message = "Напоминание о бронировании\n\n";
        $message .= "{$restaurantName}\n";
        $message .= "{$date} в {$time}\n";
        $message .= "Гостей: {$guests}";

        if ($table) {
            $message .= "\nСтолик: {$table}";
        }

        if ($reservation->deposit > 0 && !$reservation->deposit_paid) {
            $message .= "\n\nДепозит: " . number_format($reservation->deposit, 0, '', ' ') . " р. (не оплачен)";
        }

        $message .= "\n\nЖдём вас!";

        return $message;
    }

    /**
     * Build reminder message for staff (Telegram).
     */
    protected function buildStaffMessage(Reservation $reservation, int $hours): string
    {
        $time = $reservation->time_from;
        $guests = $reservation->guests_count;
        $table = $reservation->table?->name ?? 'не назначен';
        $name = $reservation->guest_name;
        $phone = $reservation->guest_phone;

        $message = "Напоминание: бронирование через {$hours} ч.\n\n";
        $message .= "{$time} — {$name}\n";
        $message .= "Тел: {$phone}\n";
        $message .= "Гостей: {$guests}\n";
        $message .= "Столик: {$table}";

        if ($reservation->deposit > 0) {
            $status = $reservation->deposit_paid ? 'оплачен' : 'НЕ ОПЛАЧЕН';
            $message .= "\nДепозит: " . number_format($reservation->deposit, 0, '', ' ') . " р. ({$status})";
        }

        if ($reservation->notes) {
            $message .= "\n\nПримечание: {$reservation->notes}";
        }

        return $message;
    }

    /**
     * Build HTML email for guest.
     */
    protected function buildGuestEmailHtml(Reservation $reservation, int $hours): string
    {
        $restaurantName = e($reservation->restaurant?->name ?? 'Ресторан');
        $date = Carbon::parse($reservation->date)->translatedFormat('j F Y');
        $time = e($reservation->time_from);
        $guests = $reservation->guests_count;
        $table = e($reservation->table?->name ?? '');
        $guestName = e($reservation->guest_name);

        $depositHtml = '';
        if ($reservation->deposit > 0) {
            $depositAmount = number_format($reservation->deposit, 0, '', ' ');
            $depositStatus = $reservation->deposit_paid
                ? '<span style="color: #16a34a;">оплачен</span>'
                : '<span style="color: #dc2626;">не оплачен</span>';
            $depositHtml = "<p style=\"margin: 16px 0;\"><strong>Депозит:</strong> {$depositAmount} р. ({$depositStatus})</p>";
        }

        $tableHtml = $table ? "<p><strong>Столик:</strong> {$table}</p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">Напоминание о бронировании</h1>
    </div>

    <div style="background: #ffffff; padding: 32px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px;">
        <p style="margin: 0 0 24px; font-size: 16px;">Здравствуйте, {$guestName}!</p>

        <p style="margin: 0 0 24px;">Напоминаем, что вас ждут в <strong>{$restaurantName}</strong>.</p>

        <div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 12px;"><strong>Дата:</strong> {$date}</p>
            <p style="margin: 0 0 12px;"><strong>Время:</strong> {$time}</p>
            <p style="margin: 0;"><strong>Гостей:</strong> {$guests}</p>
            {$tableHtml}
        </div>

        {$depositHtml}

        <p style="margin: 24px 0 0; text-align: center; color: #6b7280;">
            Ждём вас!
        </p>
    </div>

    <p style="font-size: 12px; color: #9ca3af; margin-top: 24px; text-align: center;">
        Это автоматическое уведомление. Если у вас возникли вопросы, свяжитесь с рестораном напрямую.
    </p>
</body>
</html>
HTML;
    }
}
