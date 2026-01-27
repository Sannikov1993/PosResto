<?php

namespace App\Console\Commands;

use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseStaleWorkSessions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:close-stale-sessions {--hours=18 : Закрывать смены старше N часов}';

    /**
     * The console command description.
     */
    protected $description = 'Автоматически закрывает незакрытые смены старше указанного времени (по умолчанию 16 часов)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxHours = (int) $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($maxHours);

        $this->info("Поиск незакрытых смен старше {$maxHours} часов (до {$cutoffTime->format('d.m.Y H:i')})...");

        // Находим все активные смены старше N часов
        $staleSessions = WorkSession::where('status', WorkSession::STATUS_ACTIVE)
            ->whereNull('clock_out')
            ->where('clock_in', '<', $cutoffTime)
            ->get();

        if ($staleSessions->isEmpty()) {
            $this->info('Нет смен для автозакрытия.');
            return Command::SUCCESS;
        }

        $this->info("Найдено {$staleSessions->count()} смен для автозакрытия.");

        $closed = 0;
        foreach ($staleSessions as $session) {
            $session->update([
                'clock_out' => now(),
                'hours_worked' => 0, // Часы = 0, админ проставит вручную
                'status' => WorkSession::STATUS_AUTO_CLOSED,
                'notes' => ($session->notes ? $session->notes . '; ' : '') .
                    "Автозакрыто по таймауту ({$maxHours}ч)",
            ]);

            $this->line("  - Закрыта смена #{$session->id}: пользователь #{$session->user_id}, приход {$session->clock_in->format('d.m.Y H:i')}");
            $closed++;
        }

        $this->info("Закрыто смен: {$closed}");

        return Command::SUCCESS;
    }
}
