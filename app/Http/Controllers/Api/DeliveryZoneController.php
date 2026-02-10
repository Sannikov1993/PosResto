<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\BroadcastsEvents;

/**
 * Контроллер зон доставки и геокодирования
 *
 * Методы: zones, createZone, updateZone, deleteZone, detectZone, suggestAddress, geocode
 */
class DeliveryZoneController extends Controller
{
    use BroadcastsEvents;
    use Traits\ResolvesRestaurantId;

    /**
     * Список зон доставки
     */
    public function zones(Request $request): JsonResponse
    {
        $zones = DeliveryZone::where('restaurant_id', $this->getRestaurantId($request))
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * Создать зону доставки
     */
    public function createZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'min_distance' => 'required|numeric|min:0',
            'max_distance' => 'required|numeric|gt:min_distance',
            'delivery_fee' => 'required|numeric|min:0',
            'free_delivery_from' => 'nullable|numeric|min:0',
            'estimated_time' => 'integer|min:1',
            'color' => 'nullable|string|max:20',
            'polygon' => 'nullable|array',
        ]);

        $zone = DeliveryZone::create([
            'restaurant_id' => $this->getRestaurantId($request),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Зона доставки создана',
            'data' => $zone,
        ], 201);
    }

    /**
     * Обновить зону доставки
     */
    public function updateZone(Request $request, DeliveryZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'min_distance' => 'numeric|min:0',
            'max_distance' => 'numeric',
            'delivery_fee' => 'numeric|min:0',
            'free_delivery_from' => 'nullable|numeric|min:0',
            'estimated_time' => 'integer|min:1',
            'color' => 'nullable|string|max:20',
            'polygon' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Зона обновлена',
            'data' => $zone,
        ]);
    }

    /**
     * Удалить зону доставки
     */
    public function deleteZone(DeliveryZone $zone): JsonResponse
    {
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Зона удалена',
        ]);
    }

    /**
     * Определить зону доставки по адресу
     */
    public function detectZone(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'order_total' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $orderTotal = floatval($validated['order_total'] ?? $validated['total'] ?? 0);
        $result = $geocoding->geocodeWithZone($validated['address'], $restaurantId);

        if (!$result['success']) {
            // Fallback если геокодирование не настроено
            $restaurant = \App\Models\Restaurant::find($restaurantId);
            $yandexSettings = $restaurant?->getSetting('yandex', []) ?? [];
            $hasApiKey = !empty($yandexSettings['api_key']) || !empty(config('services.yandex.geocoder_key'));

            if (!$hasApiKey) {
                $zone = DeliveryZone::where('restaurant_id', $restaurantId)->active()->first();
                $deliveryCost = $zone ? $zone->getDeliveryFee($orderTotal) : 0;
                return response()->json([
                    'success' => true,
                    'zone' => $zone,
                    'zone_id' => $zone?->id,
                    'delivery_cost' => $deliveryCost,
                    'free_delivery_from' => $zone?->free_delivery_from,
                    'delivery_time' => $zone?->estimated_time,
                    'warning' => 'Геокодирование не настроено',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 422);
        }

        $zone = $result['zone'];
        $deliveryCost = $zone ? $zone->getDeliveryFee($orderTotal) : 0;

        return response()->json([
            'success' => true,
            'zone' => $zone,
            'zone_id' => $zone?->id,
            'delivery_cost' => $deliveryCost,
            'free_delivery_from' => $zone?->free_delivery_from,
            'delivery_time' => $result['delivery_time'],
            'distance' => $result['distance'],
            'formatted_address' => $result['formatted_address'],
            'coordinates' => $result['coordinates'],
        ]);
    }

    /**
     * Подсказки адресов (автокомплит)
     */
    public function suggestAddress(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3',
            'limit' => 'integer|min:1|max:10',
        ]);

        $suggestions = $geocoding->suggest(
            $validated['query'],
            $validated['limit'] ?? 5
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Геокодировать адрес (получить координаты)
     */
    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
        ]);

        $result = $geocoding->geocode($validated['address']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить координаты адреса',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
