<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reservation;
use App\Services\ChannelLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * API for guests to manage notification channels.
 *
 * These endpoints are public (no auth required) but:
 * - Rate limited
 * - Require phone verification via token/reservation
 */
class GuestChannelController extends Controller
{
    public function __construct(
        protected ChannelLinkingService $linkingService,
    ) {}

    /**
     * Generate Telegram linking deep link.
     *
     * POST /api/guest/channels/telegram/link
     * {
     *   "restaurant_id": 1,
     *   "phone": "+79001234567",
     *   "reservation_id": 123  // optional, for context
     * }
     */
    public function generateTelegramLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'phone' => 'required|string|min:10|max:20',
            'reservation_id' => 'nullable|integer|exists:reservations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Per-phone cooldown: 60 секунд между запросами для одного номера
        $phoneHash = hash('sha256', preg_replace('/\D/', '', $data['phone']));
        $cooldownKey = "telegram_link_cooldown:{$phoneHash}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'error' => 'cooldown',
                'message' => 'Подождите минуту перед повторным запросом.',
            ], 429);
        }
        Cache::put($cooldownKey, true, 60);

        // Get reservation context if provided
        $reservation = null;
        if (!empty($data['reservation_id'])) {
            $reservation = Reservation::find($data['reservation_id']);

            // Verify reservation belongs to the restaurant and phone matches
            if ($reservation) {
                if ($reservation->restaurant_id != $data['restaurant_id']) {
                    $reservation = null;
                } elseif (!$this->phonesMatch($reservation->guest_phone, $data['phone'])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'phone_mismatch',
                        'message' => 'Номер телефона не совпадает с бронированием.',
                    ], 403);
                }
            }
        }

        // Generate link
        $result = $this->linkingService->generateLinkByPhone(
            restaurantId: $data['restaurant_id'],
            phone: $data['phone'],
            reservation: $reservation,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (!$result['success']) {
            $status = match ($result['error'] ?? 'error') {
                'customer_not_found' => 404,
                'already_linked' => 409,
                default => 400,
            };

            return response()->json($result, $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'deep_link' => $result['deep_link'],
                'expires_at' => $result['expires_at'],
                'expires_in_seconds' => $result['expires_in_seconds'],
            ],
        ]);
    }

    /**
     * Get notification channel status for a customer.
     *
     * POST /api/guest/channels/status
     * {
     *   "restaurant_id": 1,
     *   "phone": "+79001234567"
     * }
     */
    public function getStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'phone' => 'required|string|min:10|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Find customer
        $normalizedPhone = Customer::normalizePhone($data['phone']);
        $customer = Customer::where('restaurant_id', $data['restaurant_id'])
            ->byPhone($normalizedPhone)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'customer_not_found',
                'message' => 'Клиент с таким номером не найден.',
            ], 404);
        }

        $status = $this->linkingService->getChannelStatus($customer);

        return response()->json([
            'success' => true,
            'data' => [
                'channels' => $status,
                'preferences' => $customer->notification_preferences ?? [],
                'preferred_channel' => $customer->preferred_channel,
            ],
        ]);
    }

    /**
     * Update notification preferences.
     *
     * POST /api/guest/channels/preferences
     * {
     *   "restaurant_id": 1,
     *   "phone": "+79001234567",
     *   "preferences": {
     *     "reservation": ["telegram", "email"],
     *     "reminder": ["telegram"],
     *     "marketing": []
     *   },
     *   "preferred_channel": "telegram"
     * }
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'phone' => 'required|string|min:10|max:20',
            'preferences' => 'nullable|array',
            'preferences.*' => 'array',
            'preferences.*.*' => 'string|in:email,telegram,sms',
            'preferred_channel' => 'nullable|string|in:email,telegram,sms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Find customer
        $normalizedPhone = Customer::normalizePhone($data['phone']);
        $customer = Customer::where('restaurant_id', $data['restaurant_id'])
            ->byPhone($normalizedPhone)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'customer_not_found',
                'message' => 'Клиент с таким номером не найден.',
            ], 404);
        }

        // Update preferences
        $updates = [];

        if (isset($data['preferences'])) {
            $this->linkingService->updatePreferences($customer, $data['preferences']);
        }

        if (isset($data['preferred_channel'])) {
            $customer->update(['preferred_channel' => $data['preferred_channel']]);
        }

        // Return updated status
        $status = $this->linkingService->getChannelStatus($customer);

        return response()->json([
            'success' => true,
            'data' => [
                'channels' => $status,
                'preferences' => $customer->fresh()->notification_preferences ?? [],
                'preferred_channel' => $customer->preferred_channel,
            ],
        ]);
    }

    /**
     * Check if two phone numbers match (normalized).
     */
    protected function phonesMatch(?string $phone1, ?string $phone2): bool
    {
        if (!$phone1 || !$phone2) {
            return false;
        }

        $normalized1 = Customer::normalizePhone($phone1);
        $normalized2 = Customer::normalizePhone($phone2);

        return $normalized1 === $normalized2;
    }
}
