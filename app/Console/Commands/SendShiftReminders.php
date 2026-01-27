<?php

namespace App\Console\Commands;

use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\StaffNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendShiftReminders extends Command
{
    protected $signature = 'shifts:send-reminders';

    protected $description = 'Send reminders for upcoming shifts (24h and 1h before)';

    protected StaffNotificationService $notificationService;

    public function __construct(StaffNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle(): int
    {
        $this->info('Checking for upcoming shifts...');

        $sent24h = $this->send24HourReminders();
        $sent1h = $this->send1HourReminders();

        $this->info("Sent {$sent24h} 24-hour reminders");
        $this->info("Sent {$sent1h} 1-hour reminders");

        return Command::SUCCESS;
    }

    /**
     * Send reminders for shifts starting in ~24 hours
     */
    protected function send24HourReminders(): int
    {
        $now = now();

        // Find shifts starting between 23-25 hours from now
        $from = $now->copy()->addHours(23);
        $to = $now->copy()->addHours(25);

        $shifts = StaffSchedule::published()
            ->whereNull('reminder_24h_sent_at')
            ->whereDate('date', $from->toDateString())
            ->orWhere(function ($query) use ($from, $to) {
                $query->published()
                    ->whereNull('reminder_24h_sent_at')
                    ->whereDate('date', $to->toDateString());
            })
            ->with('user')
            ->get();

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftStart = $this->getShiftDateTime($shift);

            // Check if shift starts within 23-25 hours window
            $hoursUntil = $now->diffInHours($shiftStart, false);

            if ($hoursUntil >= 23 && $hoursUntil <= 25) {
                if ($this->shouldSendReminder($shift->user, 'shift_reminder')) {
                    $this->sendReminder($shift, '24h');
                    $count++;
                }

                // Mark as sent regardless of user preference
                $shift->update(['reminder_24h_sent_at' => now()]);
            }
        }

        return $count;
    }

    /**
     * Send reminders for shifts starting in ~1 hour
     */
    protected function send1HourReminders(): int
    {
        $now = now();

        // Find shifts starting between 50-70 minutes from now
        $from = $now->copy()->addMinutes(50);
        $to = $now->copy()->addMinutes(70);

        $shifts = StaffSchedule::published()
            ->whereNull('reminder_1h_sent_at')
            ->whereDate('date', $now->toDateString())
            ->with('user')
            ->get();

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftStart = $this->getShiftDateTime($shift);

            // Check if shift starts within 50-70 minutes window
            $minutesUntil = $now->diffInMinutes($shiftStart, false);

            if ($minutesUntil >= 50 && $minutesUntil <= 70) {
                if ($this->shouldSendReminder($shift->user, 'shift_reminder')) {
                    $this->sendReminder($shift, '1h');
                    $count++;
                }

                // Mark as sent regardless of user preference
                $shift->update(['reminder_1h_sent_at' => now()]);
            }
        }

        return $count;
    }

    /**
     * Get shift start datetime
     */
    protected function getShiftDateTime(StaffSchedule $shift): Carbon
    {
        $date = Carbon::parse($shift->date)->format('Y-m-d');
        $time = Carbon::parse($shift->start_time)->format('H:i:s');

        return Carbon::parse("{$date} {$time}");
    }

    /**
     * Check if user wants to receive this type of reminder
     */
    protected function shouldSendReminder(?User $user, string $type): bool
    {
        if (!$user) {
            return false;
        }

        $settings = $user->notification_settings ?? [];

        return $settings[$type] ?? true;
    }

    /**
     * Send reminder notification
     */
    protected function sendReminder(StaffSchedule $shift, string $type): void
    {
        if (!$shift->user) {
            return;
        }

        $startTime = Carbon::parse($shift->start_time)->format('H:i');
        $endTime = Carbon::parse($shift->end_time)->format('H:i');
        $date = Carbon::parse($shift->date);

        if ($type === '24h') {
            $dayLabel = $date->isToday() ? 'сегодня' : ($date->isTomorrow() ? 'завтра' : $date->format('d.m'));
            $title = "Напоминание о смене";
            $message = "Ваша смена {$dayLabel} с {$startTime} до {$endTime}";
        } else {
            $title = "Смена через час";
            $message = "Ваша смена начинается в {$startTime}. Не опаздывайте!";
        }

        $this->notificationService->send(
            $shift->user,
            'shift_reminder',
            $title,
            $message,
            [
                'shift_id' => $shift->id,
                'date' => $shift->date->format('Y-m-d'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reminder_type' => $type,
            ]
        );

        $this->line("  -> Sent {$type} reminder to {$shift->user->name} for shift on {$date->format('d.m')}");
    }
}
