<?php

namespace App\Console\Commands;

use App\Domain\Reservation\Actions\MarkNoShow;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkNoShowReservations extends Command
{
    protected $signature = 'reservations:mark-no-show
                            {--grace-minutes=30 : Minutes after reservation start time to wait before marking no-show}
                            {--dry-run : Do not actually update reservations}';

    protected $description = 'Automatically mark overdue confirmed reservations as no-show';

    public function handle(MarkNoShow $markNoShow): int
    {
        $graceMinutes = (int) $this->option('grace-minutes');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for overdue reservations (grace period: {$graceMinutes} min)...");

        // Cutoff time: current time minus grace period
        $cutoffTime = Carbon::now()->subMinutes($graceMinutes);

        // Find reservations that:
        // - Are confirmed (not pending - need confirmation first)
        // - Are today or before
        // - Start time has passed (with grace period)
        // - Are NOT seated, completed, cancelled, or already no_show
        $reservations = Reservation::query()
            ->where('status', 'confirmed')
            ->where(function ($query) use ($cutoffTime) {
                // Today's reservations with passed time
                $query->where(function ($q) use ($cutoffTime) {
                    $q->whereDate('date', Carbon::today())
                      ->whereRaw("CONCAT(date, ' ', time_from) <= ?", [$cutoffTime->format('Y-m-d H:i:s')]);
                })
                // Or past dates (any time)
                ->orWhere('date', '<', Carbon::today());
            })
            ->with(['table', 'customer'])
            ->get();

        $this->info("Found {$reservations->count()} overdue reservations");

        $marked = 0;
        $errors = 0;

        foreach ($reservations as $reservation) {
            $timeInfo = "{$reservation->date->format('Y-m-d')} {$reservation->time_from}";

            if ($dryRun) {
                $this->info("Reservation #{$reservation->id} ({$timeInfo}): Would mark as no-show");
                $marked++;
                continue;
            }

            try {
                // Use domain action for proper event dispatching
                $markNoShow->execute(
                    $reservation,
                    null, // System action, no user
                    'Автоматически отмечено системой: гости не пришли'
                );

                $this->info("Reservation #{$reservation->id} ({$timeInfo}): Marked as no-show");
                $marked++;

                Log::info('Reservation automatically marked as no-show', [
                    'reservation_id' => $reservation->id,
                    'date' => $reservation->date->format('Y-m-d'),
                    'time_from' => $reservation->time_from,
                    'guest_name' => $reservation->guest_name ?? $reservation->customer?->name,
                ]);
            } catch (\Throwable $e) {
                $this->error("Reservation #{$reservation->id}: Failed - {$e->getMessage()}");
                $errors++;

                Log::error('Failed to mark reservation as no-show', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Marked: {$marked}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
