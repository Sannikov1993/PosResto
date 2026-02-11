<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * List all API clients for the restaurant
     */
    public function index(Request $request): JsonResponse
    {
        $clients = ApiClient::where('restaurant_id', $this->getRestaurantId($request))
            ->withCount('requestLogs')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'description' => $client->description,
                    'api_key' => $client->api_key,
                    'api_key_masked' => Str::mask($client->api_key, '*', 10, -4),
                    'scopes' => $client->scopes ?? [],
                    'rate_plan' => $client->rate_plan,
                    'is_active' => $client->is_active,
                    'webhook_url' => $client->webhook_url,
                    'webhook_events' => $client->webhook_events ?? [],
                    'allowed_ips' => $client->allowed_ips ?? [],
                    'last_used_at' => $client->last_used_at?->toIso8601String(),
                    'request_logs_count' => $client->request_logs_count,
                    'created_at' => $client->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Get single API client details
     */
    public function show(Request $request, ApiClient $apiClient): JsonResponse
    {
        // Ensure client belongs to this restaurant
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        // Get recent request statistics
        $stats = ApiRequestLog::where('api_client_id', $apiClient->id)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, AVG(response_time_ms) as avg_response_time')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $apiClient->id,
                'name' => $apiClient->name,
                'description' => $apiClient->description,
                'api_key' => $apiClient->api_key,
                'api_secret_set' => !empty($apiClient->api_secret),
                'scopes' => $apiClient->scopes ?? [],
                'rate_plan' => $apiClient->rate_plan,
                'is_active' => $apiClient->is_active,
                'webhook_url' => $apiClient->webhook_url,
                'webhook_secret_set' => !empty($apiClient->webhook_secret),
                'webhook_events' => $apiClient->webhook_events ?? [],
                'allowed_ips' => $apiClient->allowed_ips ?? [],
                'last_used_at' => $apiClient->last_used_at?->toIso8601String(),
                'created_at' => $apiClient->created_at->toIso8601String(),
                'updated_at' => $apiClient->updated_at->toIso8601String(),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Create new API client
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:' . implode(',', array_keys(config('api.scopes', []))),
            'rate_plan' => 'nullable|string|in:free,business,enterprise',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:' . implode(',', config('api.webhooks.events', [])),
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $keys = ApiClient::generateKeyPair();
        $plaintextSecret = $keys['api_secret']; // Save before hashing
        $webhookSecret = !empty($validated['webhook_url']) ? ApiClient::generateWebhookSecret() : null;

        $client = ApiClient::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'api_key' => $keys['api_key'],
            'api_key_prefix' => $keys['api_key_prefix'],
            'api_secret' => $keys['api_secret'],
            'scopes' => $validated['scopes'] ?? ['menu:read'],
            'rate_plan' => $validated['rate_plan'] ?? 'free',
            'is_active' => true,
            'activated_at' => now(),
            'webhook_url' => $validated['webhook_url'] ?? null,
            'webhook_secret' => $webhookSecret,
            'webhook_events' => $validated['webhook_events'] ?? [],
            'allowed_ips' => $validated['allowed_ips'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API клиент создан. Сохраните секрет — он больше не будет показан.',
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'api_key' => $client->api_key,
                'api_secret' => $plaintextSecret, // One-time reveal
                'webhook_secret' => $webhookSecret,
            ],
        ], 201);
    }

    /**
     * Update API client
     */
    public function update(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:' . implode(',', array_keys(config('api.scopes', []))),
            'rate_plan' => 'nullable|string|in:free,business,enterprise',
            'is_active' => 'nullable|boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:' . implode(',', config('api.webhooks.events', [])),
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        // Generate webhook secret if URL is being set for first time
        if (!empty($validated['webhook_url']) && empty($apiClient->webhook_secret)) {
            $validated['webhook_secret'] = ApiClient::generateWebhookSecret();
        }

        $apiClient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'API клиент обновлён',
            'data' => ['id' => $apiClient->id, 'updated' => true],
        ]);
    }

    /**
     * Delete API client
     */
    public function destroy(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        $apiClient->delete();

        return response()->json([
            'success' => true,
            'message' => 'API клиент удалён',
        ]);
    }

    /**
     * Regenerate API credentials
     */
    public function regenerateCredentials(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        $type = $request->input('type', 'both'); // key, secret, webhook, both

        $updates = [];
        $plaintextValues = [];

        if (in_array($type, ['key', 'both']) || in_array($type, ['secret', 'both'])) {
            $keys = ApiClient::generateKeyPair();
            if (in_array($type, ['key', 'both'])) {
                $updates['api_key'] = $keys['api_key'];
                $updates['api_key_prefix'] = $keys['api_key_prefix'];
            }
            if (in_array($type, ['secret', 'both'])) {
                $plaintextValues['api_secret'] = $keys['api_secret']; // Before hash
                $updates['api_secret'] = $keys['api_secret'];
            }
        }

        if ($type === 'webhook' && $apiClient->webhook_url) {
            $plaintextValues['webhook_secret'] = ApiClient::generateWebhookSecret();
            $updates['webhook_secret'] = $plaintextValues['webhook_secret'];
        }

        $apiClient->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Учётные данные перегенерированы. Сохраните — они больше не будут показаны.',
            'data' => [
                'api_key' => $updates['api_key'] ?? $apiClient->api_key,
                'api_secret' => $plaintextValues['api_secret'] ?? null,
                'webhook_secret' => $plaintextValues['webhook_secret'] ?? null,
            ],
        ]);
    }

    /**
     * Toggle API client active status
     */
    public function toggleActive(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        $apiClient->update(['is_active' => !$apiClient->is_active]);

        return response()->json([
            'success' => true,
            'message' => $apiClient->is_active ? 'API клиент активирован' : 'API клиент деактивирован',
            'data' => ['is_active' => $apiClient->is_active],
        ]);
    }

    /**
     * Get API client request logs
     */
    public function logs(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        $logs = ApiRequestLog::where('api_client_id', $apiClient->id)
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 100))
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'request_id' => $log->request_id,
                    'method' => $log->method,
                    'path' => $log->path,
                    'status_code' => $log->status_code,
                    'response_time_ms' => $log->response_time_ms,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get available scopes
     */
    public function scopes(): JsonResponse
    {
        $scopes = config('api.scopes', []);

        return response()->json([
            'success' => true,
            'data' => collect($scopes)->map(function ($description, $scope) {
                return [
                    'scope' => $scope,
                    'description' => $description,
                ];
            })->values(),
        ]);
    }

    /**
     * Get available webhook events
     */
    public function webhookEvents(): JsonResponse
    {
        $events = config('api.webhooks.events', []);

        $descriptions = [
            'order.created' => 'Создан новый заказ',
            'order.updated' => 'Заказ обновлён',
            'order.completed' => 'Заказ завершён',
            'order.cancelled' => 'Заказ отменён',
            'reservation.created' => 'Создана новая бронь',
            'reservation.updated' => 'Бронь обновлена',
            'reservation.cancelled' => 'Бронь отменена',
            'menu.updated' => 'Меню обновлено',
            'customer.created' => 'Создан новый клиент',
            'customer.updated' => 'Данные клиента обновлены',
        ];

        return response()->json([
            'success' => true,
            'data' => collect($events)->map(function ($event) use ($descriptions) {
                return [
                    'event' => $event,
                    'description' => $descriptions[$event] ?? 'Нет описания',
                ];
            })->values(),
        ]);
    }

    /**
     * Test webhook
     */
    public function testWebhook(Request $request, ApiClient $apiClient): JsonResponse
    {
        if ($apiClient->restaurant_id !== $this->getRestaurantId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'API клиент не найден',
            ], 404);
        }

        if (empty($apiClient->webhook_url)) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook URL не настроен',
            ], 422);
        }

        $payload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'Тестовое сообщение от MenuLab API',
                'api_client_id' => $apiClient->id,
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $apiClient->webhook_secret);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(config('api.webhooks.timeout', 30))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-MenuLab-Signature' => $signature,
                    'X-MenuLab-Event' => 'webhook.test',
                    'X-MenuLab-Timestamp' => $payload['timestamp'],
                ])
                ->post($apiClient->webhook_url, $payload);

            return response()->json([
                'success' => true,
                'message' => $response->successful() ? 'Webhook отправлен успешно' : 'Webhook отправлен с ошибкой',
                'data' => [
                    'status_code' => $response->status(),
                    'response' => $response->successful() ? $response->body() : null,
                    'error' => !$response->successful() ? $response->body() : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка отправки webhook',
                'data' => config('app.debug') ? ['error' => $e->getMessage()] : [],
            ], 500);
        }
    }
}
