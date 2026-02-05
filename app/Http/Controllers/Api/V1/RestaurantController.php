<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Restaurant API Controller
 *
 * Restaurant info, working hours, and status.
 */
class RestaurantController extends BaseApiController
{
    /**
     * Get restaurant info
     *
     * GET /api/v1/restaurant/info
     */
    public function info(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return $this->notFound('Restaurant not found');
        }

        return $this->success([
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'description' => $restaurant->description,
            'logo_url' => $restaurant->logo ? asset('storage/' . $restaurant->logo) : null,
            'cover_url' => $restaurant->cover_image ? asset('storage/' . $restaurant->cover_image) : null,

            'contact' => [
                'phone' => $restaurant->phone,
                'email' => $restaurant->email,
                'website' => $restaurant->website,
            ],

            'address' => [
                'full' => $restaurant->address,
                'city' => $restaurant->city,
                'latitude' => $restaurant->latitude ? (float) $restaurant->latitude : null,
                'longitude' => $restaurant->longitude ? (float) $restaurant->longitude : null,
            ],

            'settings' => [
                'currency' => $restaurant->currency ?? 'RUB',
                'timezone' => $restaurant->timezone ?? 'Europe/Moscow',
                'locale' => $restaurant->locale ?? 'ru',
            ],

            'features' => [
                'delivery_enabled' => $restaurant->delivery_enabled ?? false,
                'pickup_enabled' => $restaurant->pickup_enabled ?? true,
                'reservations_enabled' => $restaurant->reservations_enabled ?? true,
                'online_payments_enabled' => $restaurant->online_payments_enabled ?? false,
            ],

            'social' => [
                'instagram' => $restaurant->instagram,
                'facebook' => $restaurant->facebook,
                'vk' => $restaurant->vk,
                'telegram' => $restaurant->telegram_channel,
            ],
        ]);
    }

    /**
     * Get working hours
     *
     * GET /api/v1/restaurant/hours
     */
    public function hours(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return $this->notFound('Restaurant not found');
        }

        $workingHours = $restaurant->working_hours ?? [];

        // Default schedule if not set
        $defaultSchedule = [
            'monday' => ['open' => '10:00', 'close' => '22:00', 'is_open' => true],
            'tuesday' => ['open' => '10:00', 'close' => '22:00', 'is_open' => true],
            'wednesday' => ['open' => '10:00', 'close' => '22:00', 'is_open' => true],
            'thursday' => ['open' => '10:00', 'close' => '22:00', 'is_open' => true],
            'friday' => ['open' => '10:00', 'close' => '23:00', 'is_open' => true],
            'saturday' => ['open' => '10:00', 'close' => '23:00', 'is_open' => true],
            'sunday' => ['open' => '10:00', 'close' => '22:00', 'is_open' => true],
        ];

        $schedule = array_merge($defaultSchedule, $workingHours);

        return $this->success([
            'timezone' => $restaurant->timezone ?? 'Europe/Moscow',
            'schedule' => $schedule,
            'special_hours' => $restaurant->special_hours ?? [],
            'holidays' => $restaurant->holidays ?? [],
        ]);
    }

    /**
     * Check if restaurant is open now
     *
     * GET /api/v1/restaurant/is-open
     */
    public function isOpen(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return $this->notFound('Restaurant not found');
        }

        $timezone = $restaurant->timezone ?? 'Europe/Moscow';
        $now = now()->setTimezone($timezone);
        $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');

        $workingHours = $restaurant->working_hours ?? [];
        $todaySchedule = $workingHours[$dayOfWeek] ?? [
            'open' => '10:00',
            'close' => '22:00',
            'is_open' => true,
        ];

        $isOpen = false;
        $opensAt = null;
        $closesAt = null;

        if ($todaySchedule['is_open'] ?? true) {
            $openTime = $todaySchedule['open'] ?? '10:00';
            $closeTime = $todaySchedule['close'] ?? '22:00';

            $isOpen = $currentTime >= $openTime && $currentTime < $closeTime;
            $opensAt = $openTime;
            $closesAt = $closeTime;
        }

        // Check special hours (holidays, etc.)
        $dateKey = $now->format('Y-m-d');
        $specialHours = $restaurant->special_hours ?? [];

        if (isset($specialHours[$dateKey])) {
            $special = $specialHours[$dateKey];
            if ($special['is_closed'] ?? false) {
                $isOpen = false;
                $opensAt = null;
                $closesAt = null;
            } else {
                $openTime = $special['open'] ?? $opensAt;
                $closeTime = $special['close'] ?? $closesAt;
                $isOpen = $currentTime >= $openTime && $currentTime < $closeTime;
                $opensAt = $openTime;
                $closesAt = $closeTime;
            }
        }

        // Find next open time if currently closed
        $nextOpenTime = null;
        if (!$isOpen) {
            // Check later today
            if ($opensAt && $currentTime < $opensAt) {
                $nextOpenTime = $now->copy()->setTimeFromTimeString($opensAt)->toIso8601String();
            } else {
                // Check next days
                for ($i = 1; $i <= 7; $i++) {
                    $checkDate = $now->copy()->addDays($i);
                    $checkDay = strtolower($checkDate->format('l'));
                    $checkSchedule = $workingHours[$checkDay] ?? ['is_open' => true, 'open' => '10:00'];

                    if ($checkSchedule['is_open'] ?? true) {
                        $nextOpenTime = $checkDate
                            ->setTimeFromTimeString($checkSchedule['open'] ?? '10:00')
                            ->toIso8601String();
                        break;
                    }
                }
            }
        }

        return $this->success([
            'is_open' => $isOpen,
            'current_time' => $now->toIso8601String(),
            'timezone' => $timezone,
            'today' => [
                'day' => $dayOfWeek,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ],
            'next_open_at' => $nextOpenTime,
            'message' => $isOpen
                ? "Open until {$closesAt}"
                : ($nextOpenTime ? 'Currently closed' : 'Closed'),
        ]);
    }
}
