<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Delivery API Controller
 *
 * Delivery zones, fee calculation, and order tracking.
 */
class DeliveryController extends BaseApiController
{
    /**
     * Get delivery zones
     *
     * GET /api/v1/delivery/zones
     */
    public function zones(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $zones = DeliveryZone::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $transformed = $zones->map(function ($zone) {
            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'description' => $zone->description,
                'color' => $zone->color,
                'delivery_fee' => number_format($zone->delivery_fee ?? 0, 2, '.', ''),
                'free_delivery_from' => $zone->free_delivery_from
                    ? number_format($zone->free_delivery_from, 2, '.', '')
                    : null,
                'min_order_amount' => $zone->min_order_amount
                    ? number_format($zone->min_order_amount, 2, '.', '')
                    : null,
                'estimated_time_minutes' => $zone->estimated_time,
                'polygon' => $zone->polygon, // GeoJSON coordinates
            ];
        });

        return $this->success($transformed);
    }

    /**
     * Check if address is deliverable
     *
     * POST /api/v1/delivery/check
     */
    public function checkAddress(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = $data['latitude'];
        $lng = $data['longitude'];

        // Find zone that contains this point
        $zones = DeliveryZone::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get();

        $matchedZone = null;

        foreach ($zones as $zone) {
            if ($zone->polygon && $this->pointInPolygon($lat, $lng, $zone->polygon)) {
                $matchedZone = $zone;
                break;
            }
        }

        if (!$matchedZone) {
            return $this->success([
                'deliverable' => false,
                'zone' => null,
                'message' => 'Address is outside delivery area',
            ]);
        }

        return $this->success([
            'deliverable' => true,
            'zone' => [
                'id' => $matchedZone->id,
                'name' => $matchedZone->name,
                'delivery_fee' => number_format($matchedZone->delivery_fee ?? 0, 2, '.', ''),
                'free_delivery_from' => $matchedZone->free_delivery_from
                    ? number_format($matchedZone->free_delivery_from, 2, '.', '')
                    : null,
                'min_order_amount' => $matchedZone->min_order_amount
                    ? number_format($matchedZone->min_order_amount, 2, '.', '')
                    : null,
                'estimated_time_minutes' => $matchedZone->estimated_time,
            ],
        ]);
    }

    /**
     * Calculate delivery fee
     *
     * POST /api/v1/delivery/calculate
     */
    public function calculateFee(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'order_total' => 'required|numeric|min:0',
        ]);

        $zone = null;

        if (!empty($data['zone_id'])) {
            $zone = DeliveryZone::where('restaurant_id', $restaurantId)
                ->find($data['zone_id']);
        } elseif (!empty($data['latitude']) && !empty($data['longitude'])) {
            $zones = DeliveryZone::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->get();

            foreach ($zones as $z) {
                if ($z->polygon && $this->pointInPolygon($data['latitude'], $data['longitude'], $z->polygon)) {
                    $zone = $z;
                    break;
                }
            }
        }

        if (!$zone) {
            return $this->success([
                'deliverable' => false,
                'fee' => null,
                'message' => 'Delivery not available for this location',
            ]);
        }

        $orderTotal = $data['order_total'];
        $fee = $zone->delivery_fee ?? 0;

        // Check free delivery threshold
        if ($zone->free_delivery_from && $orderTotal >= $zone->free_delivery_from) {
            $fee = 0;
        }

        // Check minimum order
        $meetsMinimum = !$zone->min_order_amount || $orderTotal >= $zone->min_order_amount;

        return $this->success([
            'deliverable' => $meetsMinimum,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'fee' => number_format($fee, 2, '.', ''),
            'fee_cents' => (int) ($fee * 100),
            'free_delivery_from' => $zone->free_delivery_from
                ? number_format($zone->free_delivery_from, 2, '.', '')
                : null,
            'is_free_delivery' => $fee == 0,
            'min_order_amount' => $zone->min_order_amount
                ? number_format($zone->min_order_amount, 2, '.', '')
                : null,
            'meets_minimum' => $meetsMinimum,
            'estimated_time_minutes' => $zone->estimated_time,
        ]);
    }

    /**
     * Track delivery order
     *
     * GET /api/v1/delivery/track/{orderId}
     */
    public function track(Request $request, int $orderId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)
            ->where('type', 'delivery')
            ->with(['courier:id,name,courier_last_location,courier_last_seen'])
            ->find($orderId);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'delivery_status' => $order->delivery_status,
            'delivery_address' => $order->delivery_address,
            'estimated_delivery_minutes' => $order->estimated_delivery_minutes,
            'courier' => $order->courier ? [
                'id' => $order->courier->id,
                'name' => $order->courier->name,
                'location' => $order->courier->courier_last_location,
                'last_seen' => $this->formatDateTime($order->courier->courier_last_seen),
            ] : null,
            'timestamps' => [
                'created_at' => $this->formatDateTime($order->created_at),
                'confirmed_at' => $this->formatDateTime($order->confirmed_at),
                'cooking_started_at' => $this->formatDateTime($order->cooking_started_at),
                'ready_at' => $this->formatDateTime($order->ready_at),
                'picked_up_at' => $this->formatDateTime($order->picked_up_at),
                'delivered_at' => $this->formatDateTime($order->delivered_at),
            ],
        ]);
    }

    /**
     * Check if point is inside polygon
     * Uses ray casting algorithm
     */
    protected function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        // Extract coordinates from GeoJSON format
        $coordinates = $polygon['coordinates'][0] ?? $polygon;

        if (empty($coordinates)) {
            return false;
        }

        $n = count($coordinates);
        $inside = false;

        $p1x = $coordinates[0][0]; // longitude
        $p1y = $coordinates[0][1]; // latitude

        for ($i = 1; $i <= $n; $i++) {
            $p2x = $coordinates[$i % $n][0];
            $p2y = $coordinates[$i % $n][1];

            if ($lat > min($p1y, $p2y)) {
                if ($lat <= max($p1y, $p2y)) {
                    if ($lng <= max($p1x, $p2x)) {
                        if ($p1y != $p2y) {
                            $xinters = ($lat - $p1y) * ($p2x - $p1x) / ($p2y - $p1y) + $p1x;
                        }
                        if ($p1x == $p2x || $lng <= $xinters) {
                            $inside = !$inside;
                        }
                    }
                }
            }

            $p1x = $p2x;
            $p1y = $p2y;
        }

        return $inside;
    }
}
