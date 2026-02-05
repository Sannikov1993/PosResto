<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'webhooks:process
                            {--limit=100 : Maximum number of webhooks to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending webhook deliveries';

    /**
     * Execute the console command.
     */
    public function handle(WebhookService $webhookService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing pending webhooks (limit: {$limit})...");

        $results = $webhookService->processPending($limit);

        $this->info("Processed: {$results['processed']}");
        $this->info("Delivered: {$results['delivered']}");
        $this->info("Failed: {$results['failed']}");

        return Command::SUCCESS;
    }
}
