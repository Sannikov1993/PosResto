<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Restaurant;
use App\ValueObjects\TimeSlot;
use App\Helpers\TimeHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill command to populate starts_at/ends_at from legacy date/time fields.
 *
 * This command migrates existing reservations from the legacy format (date, time_from, time_to)
 * to the new datetime format (starts_at, ends_at) with proper midnight-crossing support.
 *
 * Usage:
 *   php artisan reservations:backfill-datetimes           # Run migration
 *   php artisan reservations:backfill-datetimes --dry-run # Preview changes only
 *   php artisan reservations:backfill-datetimes --force   # Force re-migration
 */
class BackfillReservationDatetimes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:backfill-datetimes
                            {--dry-run : Preview changes without applying them}
                            {--force : Re-process all records, even those already migrated}
                            {--batch=500 : Number of records to process per batch}
                            {--restaurant= : Process only a specific restaurant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill starts_at/ends_at datetime columns from legacy date/time fields';

    /**
     * Statistics for the run.
     */
    private int $processed = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $midnightCrossing = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch');
        $restaurantId = $this->option('restaurant');

        $this->info('=== Reservation DateTime Backfill ===');
        $this->info('');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->info('');
        }

        // Build query
        $query = Reservation::query()
            ->whereNotNull('date')
            ->whereNotNull('time_from')
            ->whereNotNull('time_to');

        if (!$force) {
            $query->whereNull('starts_at');
        }

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No reservations to process.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} reservations to process.");
        $this->info('');

        // Load restaurant timezones for efficiency
        $timezones = $this->loadRestaurantTimezones($restaurantId);

        // Process in batches
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        $query->orderBy('id')
            ->chunk($batchSize, function ($reservations) use ($dryRun, $timezones, $progressBar) {
                foreach ($reservations as $reservation) {
                    $this->processReservation($reservation, $timezones, $dryRun);
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        // Print summary
        $this->printSummary($dryRun);

        return $this->errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Process a single reservation.
     */
    private function processReservation(Reservation $reservation, array $timezones, bool $dryRun): void
    {
        try {
            // Get timezone for this restaurant
            $tz = $timezones[$reservation->restaurant_id] ?? 'UTC';

            // Get date as string
            $dateStr = $reservation->date instanceof Carbon
                ? $reservation->date->format('Y-m-d')
                : substr($reservation->date, 0, 10);

            // Normalize time strings
            $timeFrom = substr($reservation->time_from, 0, 5);
            $timeTo = substr($reservation->time_to, 0, 5);

            // Create TimeSlot (handles midnight crossing automatically)
            $timeSlot = TimeSlot::fromDateAndTimes($dateStr, $timeFrom, $timeTo, $tz);
            $utcSlot = $timeSlot->toUtc();

            // Track midnight-crossing reservations
            if ($timeSlot->crossesMidnight()) {
                $this->midnightCrossing++;

                if ($this->output->isVerbose()) {
                    $this->info("  Midnight crossing: {$dateStr} {$timeFrom}-{$timeTo} ({$reservation->guest_name})");
                }
            }

            // Update if not dry run
            if (!$dryRun) {
                $reservation->update([
                    'starts_at' => $utcSlot->startsAt(),
                    'ends_at' => $utcSlot->endsAt(),
                    'duration_minutes' => $timeSlot->durationMinutes(),
                    'timezone' => $tz,
                ]);
            }

            $this->processed++;

        } catch (\Exception $e) {
            $this->errors++;

            if ($this->output->isVerbose()) {
                $this->error("  Error processing reservation #{$reservation->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Load timezones for all restaurants.
     */
    private function loadRestaurantTimezones(?int $restaurantId): array
    {
        $query = Restaurant::query();

        if ($restaurantId) {
            $query->where('id', $restaurantId);
        }

        $timezones = [];

        $query->each(function ($restaurant) use (&$timezones) {
            // Try to get timezone from TimeHelper (which may use restaurant settings)
            try {
                $tz = TimeHelper::getTimezone($restaurant->id);
            } catch (\Exception $e) {
                $tz = 'UTC';
            }

            $timezones[$restaurant->id] = $tz;
        });

        return $timezones;
    }

    /**
     * Print summary of the operation.
     */
    private function printSummary(bool $dryRun): void
    {
        $this->info('=== Summary ===');
        $this->info('');

        $action = $dryRun ? 'Would process' : 'Processed';
        $this->info("{$action}: {$this->processed} reservations");
        $this->info("Skipped: {$this->skipped} reservations");
        $this->info("Midnight-crossing: {$this->midnightCrossing} reservations");

        if ($this->errors > 0) {
            $this->error("Errors: {$this->errors} reservations");
        } else {
            $this->info("Errors: 0");
        }

        $this->info('');

        if ($dryRun && $this->processed > 0) {
            $this->warn('Run without --dry-run to apply changes.');
        }

        if ($this->midnightCrossing > 0) {
            $this->info("Note: {$this->midnightCrossing} reservations cross midnight (e.g., 22:00-02:00).");
            $this->info('These will now have correct starts_at/ends_at spanning two days.');
        }
    }
}
