<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch a webhook event to all subscribed clients
     */
    public function dispatch(string $eventType, array $data, int $restaurantId): void
    {
        // Find all active API clients for this restaurant that:
        // 1. Have a webhook URL configured
        // 2. Are subscribed to this event type
        $clients = ApiClient::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNotNull('webhook_url')
            ->where('webhook_url', '!=', '')
            ->get();

        foreach ($clients as $client) {
            // Check if client is subscribed to this event
            $subscribedEvents = $client->webhook_events ?? [];

            // If no specific events configured, send all events
            // Or if this event type is in the list
            if (empty($subscribedEvents) || in_array($eventType, $subscribedEvents)) {
                $this->createDelivery($client, $eventType, $data, $restaurantId);
            }
        }
    }

    /**
     * Create a webhook delivery record
     */
    public function createDelivery(
        ApiClient $client,
        string $eventType,
        array $data,
        int $restaurantId
    ): WebhookDelivery {
        $delivery = WebhookDelivery::createEvent($client, $eventType, $data, $restaurantId);

        // Dispatch job for immediate delivery attempt
        \App\Jobs\SendWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Attempt to deliver a webhook
     */
    public function deliver(WebhookDelivery $delivery): bool
    {
        $client = $delivery->apiClient;

        if (!$client || !$client->webhook_url) {
            $delivery->markFailed(0, 'No webhook URL configured', 0);
            return false;
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(config('api.webhooks.timeout', 30))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'MenuLab-Webhook/1.0',
                    'X-MenuLab-Event-ID' => $delivery->event_id,
                    'X-MenuLab-Event' => $delivery->event_type,
                    'X-MenuLab-Signature' => $delivery->signature,
                    'X-MenuLab-Timestamp' => $delivery->payload['timestamp'] ?? now()->toIso8601String(),
                    'X-MenuLab-Delivery-Attempt' => (string) ($delivery->attempt_count + 1),
                ])
                ->post($client->webhook_url, $delivery->payload);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $delivery->markDelivered(
                    $response->status(),
                    $response->body(),
                    $responseTimeMs
                );

                Log::info('Webhook delivered', [
                    'event_id' => $delivery->event_id,
                    'event_type' => $delivery->event_type,
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                ]);

                return true;
            }

            // Non-2xx response
            $delivery->markFailed(
                $response->status(),
                $response->body(),
                $responseTimeMs
            );

            Log::warning('Webhook delivery failed', [
                'event_id' => $delivery->event_id,
                'event_type' => $delivery->event_type,
                'status_code' => $response->status(),
                'attempt' => $delivery->attempt_count,
                'next_attempt_at' => $delivery->next_attempt_at?->toIso8601String(),
            ]);

            return false;

        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->markFailed(
                0,
                $e->getMessage(),
                $responseTimeMs
            );

            Log::error('Webhook delivery exception', [
                'event_id' => $delivery->event_id,
                'event_type' => $delivery->event_type,
                'error' => $e->getMessage(),
                'attempt' => $delivery->attempt_count,
            ]);

            return false;
        }
    }

    /**
     * Process pending webhook deliveries
     */
    public function processPending(int $limit = 100): array
    {
        $deliveries = WebhookDelivery::getPendingForDelivery($limit);

        $results = [
            'processed' => 0,
            'delivered' => 0,
            'failed' => 0,
        ];

        foreach ($deliveries as $delivery) {
            $results['processed']++;

            // Check if expired
            if ($delivery->expires_at->isPast()) {
                $delivery->markExpired();
                continue;
            }

            if ($this->deliver($delivery)) {
                $results['delivered']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Retry a specific delivery manually
     */
    public function retry(WebhookDelivery $delivery): bool
    {
        if ($delivery->status === WebhookDelivery::STATUS_DELIVERED) {
            return false;
        }

        // Reset for retry
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => now(),
        ]);

        return $this->deliver($delivery);
    }
}
