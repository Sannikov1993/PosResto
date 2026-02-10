<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\DeliveryZone;
use App\Models\Courier;
use App\Services\CourierAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderType;

/**
 * Контроллер курьеров и карты доставки
 *
 * Методы: couriers, updateCourierStatus, suggestCourier, rankedCouriers, autoAssignCourier, mapData
 */
class DeliveryCourierController extends Controller
{
    use BroadcastsEvents;
    use Traits\ResolvesRestaurantId;

    /**
     * Список курьеров
     */
    public function couriers(Request $request): JsonResponse
    {
        $couriers = User::where('is_courier', true)
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->orderByRaw("CASE courier_status WHEN 'available' THEN 1 WHEN 'busy' THEN 2 ELSE 3 END")
            ->get();

        $couriers->transform(function ($courier) {
            $activeOrders = Order::where('courier_id', $courier->id)
                ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
                ->whereIn('delivery_status', ['picked_up', 'in_transit'])
                ->count();

            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'phone' => $courier->phone,
                'status' => $courier->courier_status ?? 'offline',
                'current_orders' => $activeOrders,
                'today_orders' => $courier->courier_today_orders ?? 0,
                'today_earnings' => $courier->courier_today_earnings ?? 0,
                'last_location' => $courier->courier_last_location,
                'last_seen' => $courier->courier_last_seen,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $couriers,
        ]);
    }

    /**
     * Обновить статус курьера
     */
    public function updateCourierStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:offline,available,busy',
            'location' => 'nullable|array',
        ]);

        $user->update([
            'courier_status' => $validated['status'],
            'courier_last_location' => $validated['location'] ?? $user->courier_last_location,
            'courier_last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Статус курьера обновлён',
        ]);
    }

    /**
     * Получить рекомендацию лучшего курьера для заказа
     */
    public function suggestCourier(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return response()->json([
                'success' => false,
                'message' => 'Рекомендации курьера доступны только для доставки',
            ], 400);
        }

        $result = $assignmentService->findBestCourier($order, includeScores: true);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступных курьеров',
                'couriers' => [],
            ]);
        }

        $couriers = collect($result['all_couriers'] ?? [])->map(function ($item) use ($result) {
            $courier = $item['courier'];
            return [
                'id' => $courier->id,
                'user_id' => $courier->user_id,
                'name' => $courier->name,
                'phone' => $courier->phone,
                'transport' => $courier->transport,
                'transport_icon' => $courier->transport_icon,
                'status' => $courier->status,
                'active_orders' => $item['active_orders'],
                'distance' => round($item['distance'], 1),
                'eta_minutes' => $item['eta_minutes'],
                'score' => round($item['total_score']),
                'is_recommended' => $courier->id === $result['courier']->id,
            ];
        });

        return response()->json([
            'success' => true,
            'recommended' => [
                'id' => $result['courier']->id,
                'user_id' => $result['courier']->user_id,
                'name' => $result['courier']->name,
                'phone' => $result['courier']->phone,
                'transport' => $result['courier']->transport,
                'transport_icon' => $result['courier']->transport_icon,
                'score' => $result['score'],
                'eta_minutes' => $result['eta'],
                'distance' => $result['distance'],
                'reason' => $result['reason'],
            ],
            'couriers' => $couriers,
        ]);
    }

    /**
     * Получить рейтинг курьеров для заказа
     */
    public function rankedCouriers(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        $ranked = $assignmentService->getRankedCouriers($order);

        return response()->json([
            'success' => true,
            'data' => $ranked->map(function ($item) {
                $courier = $item['courier'];
                return [
                    'id' => $courier->id,
                    'user_id' => $courier->user_id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'transport' => $courier->transport,
                    'transport_icon' => $courier->transport_icon,
                    'status' => $courier->status,
                    'active_orders' => $item['active_orders'],
                    'distance' => $item['distance'],
                    'eta_minutes' => $item['eta'],
                    'score' => $item['score'],
                    'recommended' => $item['recommended'] ?? false,
                ];
            }),
        ]);
    }

    /**
     * Автоматически назначить лучшего курьера
     */
    public function autoAssignCourier(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        if ($order->type !== OrderType::DELIVERY->value) {
            return response()->json([
                'success' => false,
                'message' => 'Автоназначение доступно только для доставки',
            ], 400);
        }

        if ($order->courier_id) {
            return response()->json([
                'success' => false,
                'message' => 'Курьер уже назначен',
            ], 400);
        }

        $success = $assignmentService->autoAssign($order);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось назначить курьера. Нет доступных курьеров.',
            ], 400);
        }

        $order->refresh();
        $order->load('courier');

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен автоматически',
            'data' => [
                'order_id' => $order->id,
                'courier_id' => $order->courier_id,
                'courier_name' => $order->courier?->name,
            ],
        ]);
    }

    /**
     * Данные для карты курьеров
     */
    public function mapData(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Курьеры с позициями
        $couriers = Courier::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['available', 'busy'])
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get()
            ->map(function ($courier) {
                $activeOrders = Order::where('courier_id', $courier->user_id)
                    ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
                    ->whereIn('delivery_status', ['picked_up', 'in_transit'])
                    ->count();

                return [
                    'id' => $courier->id,
                    'user_id' => $courier->user_id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'status' => $courier->status,
                    'transport' => $courier->transport,
                    'transport_icon' => $courier->transport_icon,
                    'lat' => (float) $courier->current_lat,
                    'lng' => (float) $courier->current_lng,
                    'location_updated_at' => $courier->location_updated_at,
                    'active_orders' => $activeOrders,
                    'today_orders' => $courier->today_orders ?? 0,
                    'today_earnings' => $courier->today_earnings ?? 0,
                ];
            });

        // Активные заказы доставки с координатами
        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
            ->whereIn('delivery_status', ['pending', 'preparing', 'ready', 'picked_up', 'in_transit'])
            ->whereNotNull('delivery_latitude')
            ->whereNotNull('delivery_longitude')
            ->with(['courier'])
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?? $order->daily_number,
                    'status' => $order->delivery_status,
                    'status_label' => $this->getDeliveryStatusLabel($order->delivery_status),
                    'status_color' => $this->getDeliveryStatusColor($order->delivery_status),
                    'lat' => (float) $order->delivery_latitude,
                    'lng' => (float) $order->delivery_longitude,
                    'address' => $order->delivery_address,
                    'total' => $order->total,
                    'courier_id' => $order->courier_id,
                    'courier_name' => $order->courier?->name,
                    'created_at' => $order->created_at,
                ];
            });

        // Зоны доставки
        $zones = DeliveryZone::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'color' => $zone->color ?? '#3B82F6',
                    'min_distance' => $zone->min_distance,
                    'max_distance' => $zone->max_distance,
                    'polygon' => $zone->polygon,
                ];
            });

        // Координаты ресторана
        $restaurantModel = \App\Models\Restaurant::find($restaurantId);
        $yandexSettings = $restaurantModel?->getSetting('yandex', []) ?? [];
        $restaurant = [
            'lat' => (float) ($yandexSettings['restaurant_lat'] ?? config('services.yandex.restaurant_lat', 55.7558)),
            'lng' => (float) ($yandexSettings['restaurant_lng'] ?? config('services.yandex.restaurant_lng', 37.6173)),
            'name' => config('app.name', 'Ресторан'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'couriers' => $couriers,
                'orders' => $orders,
                'zones' => $zones,
                'restaurant' => $restaurant,
            ],
        ]);
    }

    /**
     * Статус доставки на русском
     */
    private function getDeliveryStatusLabel(?string $status): string
    {
        return match($status) {
            'pending' => 'Новый',
            'preparing' => 'Готовится',
            'ready' => 'Готов',
            'picked_up' => 'Забран',
            'in_transit' => 'В пути',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $status ?? 'Неизвестно',
        };
    }

    /**
     * Цвет статуса доставки
     */
    private function getDeliveryStatusColor(?string $status): string
    {
        return match($status) {
            'pending' => '#3B82F6',
            'preparing' => '#F59E0B',
            'ready' => '#10B981',
            'picked_up' => '#8B5CF6',
            'in_transit' => '#8B5CF6',
            'delivered' => '#6B7280',
            'cancelled' => '#EF4444',
            default => '#6B7280',
        };
    }
}
