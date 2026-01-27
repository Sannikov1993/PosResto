<?php

namespace App\Console\Commands;

use App\Models\StaffSchedule;
use App\Models\WorkSession;
use App\Models\User;
use App\Services\StaffNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendClockReminders extends Command
{
    protected $signature = 'clock:send-reminders';

    protected $description = 'Send reminders to employees who forgot to clock in/out';

    protected StaffNotificationService $notificationService;

    public function __construct(StaffNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle(): int
    {
        $this->info('Checking for missing clock in/out...');

        $clockInReminders = $this->sendClockInReminders();
        $clockOutReminders = $this->sendClockOutReminders();
        $unclosedReminders = $this->sendUnclosedSessionReminders();

        $this->info("Sent {$clockInReminders} clock-in reminders");
        $this->info("Sent {$clockOutReminders} clock-out reminders");
        $this->info("Sent {$unclosedReminders} unclosed session reminders");

        return Command::SUCCESS;
    }

    /**
     * Remind employees who have a shift starting but haven't clocked in
     * Sends reminder 15 minutes after shift start
     */
    protected function sendClockInReminders(): int
    {
        $now = now();

        // Find shifts that started 15-30 minutes ago
        $reminderWindowStart = $now->copy()->subMinutes(30);
        $reminderWindowEnd = $now->copy()->subMinutes(15);

        $shifts = StaffSchedule::published()
            ->whereDate('date', $now->toDateString())
            ->whereNull('clock_in_reminder_sent_at')
            ->with('user')
            ->get();

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftStart = $this->getShiftDateTime($shift);

            // Check if shift started 15-30 minutes ago
            if ($shiftStart->between($reminderWindowStart, $reminderWindowEnd)) {
                // Check if employee clocked in
                $hasClockIn = WorkSession::where('user_id', $shift->user_id)
                    ->whereDate('clock_in', $now->toDateString())
                    ->where('clock_in', '>=', $shiftStart->copy()->subMinutes(30))
                    ->exists();

                if (!$hasClockIn && $shift->user) {
                    if ($this->shouldSendReminder($shift->user, 'clock_reminder')) {
                        $this->sendClockInReminder($shift);
                        $count++;
                    }

                    // Mark as sent regardless of user preference
                    $shift->update(['clock_in_reminder_sent_at' => now()]);
                }
            }
        }

        return $count;
    }

    /**
     * Remind employees who have a shift ending but haven't clocked out
     * Sends reminder 15 minutes after shift should have ended
     */
    protected function sendClockOutReminders(): int
    {
        $now = now();

        // Find shifts that ended 15-30 minutes ago
        $reminderWindowStart = $now->copy()->subMinutes(30);
        $reminderWindowEnd = $now->copy()->subMinutes(15);

        $shifts = StaffSchedule::published()
            ->whereDate('date', $now->toDateString())
            ->whereNull('clock_out_reminder_sent_at')
            ->with('user')
            ->get();

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftEnd = $this->getShiftEndDateTime($shift);

            // Check if shift ended 15-30 minutes ago
            if ($shiftEnd->between($reminderWindowStart, $reminderWindowEnd)) {
                // Check if employee has active (unclosed) session
                $activeSession = WorkSession::where('user_id', $shift->user_id)
                    ->whereDate('clock_in', $now->toDateString())
                    ->whereNull('clock_out')
                    ->first();

                if ($activeSession && $shift->user) {
                    if ($this->shouldSendReminder($shift->user, 'clock_reminder')) {
                        $this->sendClockOutReminder($shift, $activeSession);
                        $count++;
                    }

                    $shift->update(['clock_out_reminder_sent_at' => now()]);
                }
            }
        }

        return $count;
    }

    /**
     * Remind employees with sessions open for too long (>12 hours)
     */
    protected function sendUnclosedSessionReminders(): int
    {
        $threshold = now()->subHours(12);

        $sessions = WorkSession::whereNull('clock_out')
            ->where('clock_in', '<', $threshold)
            ->whereNull('unclosed_reminder_sent_at')
            ->with('user')
            ->get();

        $count = 0;

        foreach ($sessions as $session) {
            if ($session->user && $this->shouldSendReminder($session->user, 'clock_reminder')) {
                $this->sendUnclosedSessionReminder($session);
                $count++;
            }

            // Mark as sent
            $session->update(['unclosed_reminder_sent_at' => now()]);
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
     * Get shift end datetime
     */
    protected function getShiftEndDateTime(StaffSchedule $shift): Carbon
    {
        $date = Carbon::parse($shift->date)->format('Y-m-d');
        $time = Carbon::parse($shift->end_time)->format('H:i:s');
        $end = Carbon::parse("{$date} {$time}");

        // Handle overnight shifts
        if ($end->lt($this->getShiftDateTime($shift))) {
            $end->addDay();
        }

        return $end;
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
     * Send clock-in reminder
     */
    protected function sendClockInReminder(StaffSchedule $shift): void
    {
        if (!$shift->user) {
            return;
        }

        $startTime = Carbon::parse($shift->start_time)->format('H:i');

        $this->notificationService->send(
            $shift->user,
            'clock_reminder',
            'Не забудьте отметиться!',
            "Ваша смена началась в {$startTime}. Пожалуйста, отметьте начало работы.",
            [
                'shift_id' => $shift->id,
                'reminder_type' => 'clock_in',
                'shift_start' => $startTime,
            ]
        );

        $this->line("  -> Sent clock-in reminder to {$shift->user->name}");
    }

    /**
     * Send clock-out reminder
     */
    protected function sendClockOutReminder(StaffSchedule $shift, WorkSession $session): void
    {
        if (!$shift->user) {
            return;
        }

        $endTime = Carbon::parse($shift->end_time)->format('H:i');
        $hoursWorked = round($session->clock_in->diffInMinutes(now()) / 60, 1);

        $this->notificationService->send(
            $shift->user,
            'clock_reminder',
            'Не забудьте завершить смену!',
            "Ваша смена должна была закончиться в {$endTime}. Отработано: {$hoursWorked} ч. Не забудьте отметить конец работы.",
            [
                'shift_id' => $shift->id,
                'session_id' => $session->id,
                'reminder_type' => 'clock_out',
                'shift_end' => $endTime,
                'hours_worked' => $hoursWorked,
            ]
        );

        $this->line("  -> Sent clock-out reminder to {$shift->user->name}");
    }

    /**
     * Send unclosed session reminder
     */
    protected function sendUnclosedSessionReminder(WorkSession $session): void
    {
        if (!$session->user) {
            return;
        }

        $clockInTime = $session->clock_in->format('H:i');
        $hoursOpen = round($session->clock_in->diffInHours(now()), 1);

        $this->notificationService->send(
            $session->user,
            'clock_reminder',
            'Незакрытая смена!',
            "У вас открыта смена с {$clockInTime} ({$hoursOpen} ч назад). Пожалуйста, закройте её или обратитесь к менеджеру.",
            [
                'session_id' => $session->id,
                'reminder_type' => 'unclosed_session',
                'clock_in_time' => $clockInTime,
                'hours_open' => $hoursOpen,
            ]
        );

        $this->line("  -> Sent unclosed session reminder to {$session->user->name}");
    }
}
