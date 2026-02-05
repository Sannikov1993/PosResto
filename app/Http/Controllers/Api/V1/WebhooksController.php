<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Webhooks API Controller
 *
 * Manage webhook configuration for API clients.
 */
class WebhooksController extends BaseApiController
{
    /**
     * Get current webhook configuration
     *
     * GET /api/v1/webhooks
     */
    public function show(Request $request): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        return $this->success([
            'url' => $apiClient->webhook_url,
            'secret' => $apiClient->webhook_secret ? '***' . substr($apiClient->webhook_secret, -4) : null,
            'events' => $apiClient->webhook_events ?? [],
            'is_configured' => !empty($apiClient->webhook_url),
        ]);
    }

    /**
     * Update webhook configuration
     *
     * PUT /api/v1/webhooks
     */
    public function update(Request $request): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        $data = $this->validateRequest($request, [
            'url' => 'nullable|url|max:500',
            'events' => 'nullable|array',
            'events.*' => 'string|in:' . implode(',', config('api.webhooks.events', [])),
            'regenerate_secret' => 'nullable|boolean',
        ]);

        $updateData = [];

        if (array_key_exists('url', $data)) {
            $updateData['webhook_url'] = $data['url'];
        }

        if (array_key_exists('events', $data)) {
            $updateData['webhook_events'] = $data['events'];
        }

        // Generate new secret if requested or if URL is being set for first time
        if (($data['regenerate_secret'] ?? false) || (!$apiClient->webhook_secret && !empty($data['url']))) {
            $updateData['webhook_secret'] = ApiClient::generateWebhookSecret();
        }

        $apiClient->update($updateData);
        $apiClient->refresh();

        return $this->success([
            'url' => $apiClient->webhook_url,
            'secret' => $apiClient->webhook_secret,
            'events' => $apiClient->webhook_events ?? [],
            'is_configured' => !empty($apiClient->webhook_url),
        ], 'Webhook configuration updated');
    }

    /**
     * Test webhook
     *
     * POST /api/v1/webhooks/test
     */
    public function test(Request $request): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        if (empty($apiClient->webhook_url)) {
            return $this->businessError(
                'WEBHOOK_NOT_CONFIGURED',
                'Webhook URL is not configured'
            );
        }

        // Prepare test payload
        $payload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook from MenuLab API',
                'api_client_id' => $apiClient->id,
            ],
        ];

        // Sign payload
        $signature = $this->signPayload($payload, $apiClient->webhook_secret);

        try {
            $response = Http::timeout(config('api.webhooks.timeout', 30))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-MenuLab-Signature' => $signature,
                    'X-MenuLab-Event' => 'webhook.test',
                    'X-MenuLab-Timestamp' => $payload['timestamp'],
                ])
                ->post($apiClient->webhook_url, $payload);

            return $this->success([
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time_ms' => $response->transferStats?->getTransferTime() * 1000,
                'response_body' => $response->successful() ? $response->body() : null,
                'error' => !$response->successful() ? $response->body() : null,
            ], $response->successful() ? 'Webhook test successful' : 'Webhook test failed');
        } catch (\Exception $e) {
            return $this->success([
                'success' => false,
                'error' => $e->getMessage(),
            ], 'Webhook test failed');
        }
    }

    /**
     * List available webhook events
     *
     * GET /api/v1/webhooks/events
     */
    public function events(Request $request): JsonResponse
    {
        $events = config('api.webhooks.events', []);

        $eventDescriptions = [
            'order.created' => 'Triggered when a new order is created',
            'order.updated' => 'Triggered when an order is updated',
            'order.completed' => 'Triggered when an order is completed',
            'order.cancelled' => 'Triggered when an order is cancelled',
            'reservation.created' => 'Triggered when a new reservation is created',
            'reservation.updated' => 'Triggered when a reservation is updated',
            'reservation.cancelled' => 'Triggered when a reservation is cancelled',
            'menu.updated' => 'Triggered when menu items are updated',
            'customer.created' => 'Triggered when a new customer is created',
            'customer.updated' => 'Triggered when customer data is updated',
        ];

        $result = collect($events)->map(function ($event) use ($eventDescriptions) {
            return [
                'event' => $event,
                'description' => $eventDescriptions[$event] ?? 'No description available',
            ];
        })->values();

        return $this->success($result);
    }

    /**
     * List webhook deliveries (for recovery of missed events)
     *
     * GET /api/v1/webhooks/deliveries
     */
    public function deliveries(Request $request): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        $data = $this->validateRequest($request, [
            'status' => 'nullable|in:pending,delivered,failed,expired',
            'event_type' => 'nullable|string|max:100',
            'since' => 'nullable|date',
            'until' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = WebhookDelivery::where('api_client_id', $apiClient->id);

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['event_type'])) {
            $query->where('event_type', $data['event_type']);
        }

        if (!empty($data['since'])) {
            $query->where('created_at', '>=', $data['since']);
        }

        if (!empty($data['until'])) {
            $query->where('created_at', '<=', $data['until']);
        }

        $total = $query->count();

        $deliveries = $query
            ->orderBy('created_at', 'desc')
            ->limit($data['limit'] ?? 50)
            ->offset($data['offset'] ?? 0)
            ->get();

        $result = $deliveries->map(function ($delivery) {
            return [
                'event_id' => $delivery->event_id,
                'event_type' => $delivery->event_type,
                'status' => $delivery->status,
                'attempt_count' => $delivery->attempt_count,
                'last_status_code' => $delivery->last_status_code,
                'created_at' => $this->formatDateTime($delivery->created_at),
                'delivered_at' => $this->formatDateTime($delivery->delivered_at),
                'next_attempt_at' => $this->formatDateTime($delivery->next_attempt_at),
            ];
        });

        return $this->success([
            'deliveries' => $result,
            'pagination' => [
                'total' => $total,
                'limit' => $data['limit'] ?? 50,
                'offset' => $data['offset'] ?? 0,
            ],
        ]);
    }

    /**
     * Get single webhook delivery with full payload
     *
     * GET /api/v1/webhooks/deliveries/{eventId}
     */
    public function delivery(Request $request, string $eventId): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        $delivery = WebhookDelivery::where('api_client_id', $apiClient->id)
            ->where('event_id', $eventId)
            ->first();

        if (!$delivery) {
            return $this->notFound('Webhook delivery not found');
        }

        return $this->success([
            'event_id' => $delivery->event_id,
            'event_type' => $delivery->event_type,
            'payload' => $delivery->payload,
            'signature' => $delivery->signature,
            'status' => $delivery->status,
            'attempt_count' => $delivery->attempt_count,
            'max_attempts' => $delivery->max_attempts,
            'last_status_code' => $delivery->last_status_code,
            'last_error' => $delivery->last_error,
            'last_response_time_ms' => $delivery->last_response_time_ms,
            'created_at' => $this->formatDateTime($delivery->created_at),
            'delivered_at' => $this->formatDateTime($delivery->delivered_at),
            'next_attempt_at' => $this->formatDateTime($delivery->next_attempt_at),
            'expires_at' => $this->formatDateTime($delivery->expires_at),
        ]);
    }

    /**
     * Retry a failed webhook delivery
     *
     * POST /api/v1/webhooks/deliveries/{eventId}/retry
     */
    public function retryDelivery(Request $request, string $eventId): JsonResponse
    {
        $apiClient = $this->getApiClient($request);

        if (!$apiClient) {
            return $this->unauthorized('API client not found');
        }

        $delivery = WebhookDelivery::where('api_client_id', $apiClient->id)
            ->where('event_id', $eventId)
            ->first();

        if (!$delivery) {
            return $this->notFound('Webhook delivery not found');
        }

        if ($delivery->status === WebhookDelivery::STATUS_DELIVERED) {
            return $this->businessError(
                'ALREADY_DELIVERED',
                'Webhook was already delivered successfully'
            );
        }

        // Reset and retry
        $webhookService = app(\App\Services\WebhookService::class);
        $success = $webhookService->retry($delivery);

        $delivery->refresh();

        return $this->success([
            'success' => $success,
            'status' => $delivery->status,
            'attempt_count' => $delivery->attempt_count,
            'last_status_code' => $delivery->last_status_code,
            'last_error' => $delivery->last_error,
        ], $success ? 'Webhook delivered successfully' : 'Webhook delivery failed');
    }

    /**
     * Sign webhook payload
     */
    protected function signPayload(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload);
        return hash_hmac('sha256', $payloadJson, $secret);
    }
}
