<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1; // We handle retries via WebhookDelivery

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $deliveryId
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookService $webhookService): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (!$delivery) {
            return;
        }

        // Skip if already delivered or expired
        if ($delivery->status !== WebhookDelivery::STATUS_PENDING) {
            return;
        }

        if ($delivery->expires_at->isPast()) {
            $delivery->markExpired();
            return;
        }

        // Attempt delivery
        $success = $webhookService->deliver($delivery);

        // If failed and should retry, schedule next attempt
        if (!$success && $delivery->shouldRetry()) {
            $delay = $delivery->next_attempt_at->diffInSeconds(now());
            if ($delay > 0) {
                self::dispatch($this->deliveryId)->delay($delay);
            }
        }
    }
}
