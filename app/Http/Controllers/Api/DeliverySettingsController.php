<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\DeliveryZone;
use App\Models\DeliverySetting;
use App\Models\DeliveryProblem;
use App\Models\Courier;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Delivery\Enums\DeliveryStatus;

/**
 * Контроллер настроек доставки, аналитики и проблем
 *
 * Методы: settings, updateSettings, analytics, problems, createProblem, resolveProblem, cancelProblem
 */
class DeliverySettingsController extends Controller
{
    use BroadcastsEvents;
    use Traits\ResolvesRestaurantId;

    /**
     * Получить настройки доставки
     */
    public function settings(Request $request): JsonResponse
    {
        $settings = DeliverySetting::getAllSettings($this->getRestaurantId($request));

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Обновить настройки доставки
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $allowedKeys = [
            'delivery_enabled', 'min_order_amount', 'free_delivery_amount',
            'default_delivery_fee', 'delivery_radius_km', 'max_delivery_time',
            'auto_assign_courier', 'pickup_enabled', 'pickup_discount_percent',
            'working_hours_start', 'working_hours_end', 'working_days',
            'preorder_enabled', 'preorder_max_days', 'sms_notifications',
            'push_notifications', 'customer_tracking_enabled',
            'courier_tracking_enabled', 'auto_complete_minutes',
            'problem_auto_escalate_minutes', 'max_concurrent_orders_per_courier',
        ];

        $validated = $request->validate(
            collect($allowedKeys)->mapWithKeys(fn (string $key) => [
                $key => 'sometimes|nullable',
            ])->toArray()
        );

        $restaurantId = $this->getRestaurantId($request);

        foreach ($validated as $key => $value) {
            DeliverySetting::setValue($key, $value, $restaurantId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
            'data' => DeliverySetting::getAllSettings($restaurantId),
        ]);
    }

    /**
     * Аналитика доставки
     */
    public function analytics(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 'today');

        $startDate = match($period) {
            'today' => TimeHelper::today($restaurantId),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => TimeHelper::today($restaurantId),
        };

        $query = Order::where('restaurant_id', $restaurantId)
            ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
            ->where('created_at', '>=', $startDate);

        // Общая статистика
        $totalOrders = (clone $query)->count();
        $completedOrders = (clone $query)->where('delivery_status', DeliveryStatus::DELIVERED->value)->count();
        $totalRevenue = (clone $query)->where('delivery_status', DeliveryStatus::DELIVERED->value)->sum('total');

        // Среднее время доставки (SQLite-совместимо)
        $deliveredOrders = (clone $query)
            ->where('delivery_status', DeliveryStatus::DELIVERED->value)
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at')
            ->select('picked_up_at', 'delivered_at', 'created_at')
            ->get();

        $avgDeliveryTime = 0;
        $onTimeCount = 0;
        if ($deliveredOrders->count() > 0) {
            $totalMinutes = 0;
            foreach ($deliveredOrders as $order) {
                $pickedUp = Carbon::parse($order->picked_up_at);
                $delivered = Carbon::parse($order->delivered_at);
                $created = Carbon::parse($order->created_at);

                $totalMinutes += $pickedUp->diffInMinutes($delivered);

                if ($created->diffInMinutes($delivered) <= 60) {
                    $onTimeCount++;
                }
            }
            $avgDeliveryTime = round($totalMinutes / $deliveredOrders->count());
        }
        $onTimePercent = $completedOrders > 0 ? round(($onTimeCount / $completedOrders) * 100) : 0;

        // По курьерам (SQLite-совместимо)
        $couriers = User::where('is_courier', true)
            ->where('restaurant_id', $restaurantId)
            ->get();

        $courierStats = $couriers->map(function ($courier) use ($startDate) {
            $courierOrders = Order::where('courier_id', $courier->id)
                ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
                ->where('delivery_status', DeliveryStatus::DELIVERED->value)
                ->where('created_at', '>=', $startDate)
                ->get();

            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'orders' => $courierOrders->count(),
                'revenue' => $courierOrders->sum('total') ?? 0,
            ];
        })->sortByDesc('orders')->values();

        // По зонам
        $zoneStats = DeliveryZone::where('restaurant_id', $restaurantId)
            ->withCount(['orders as orders_count' => function ($q) use ($startDate) {
                $q->where('delivery_status', DeliveryStatus::DELIVERED->value)
                  ->where('created_at', '>=', $startDate);
            }])
            ->get()
            ->map(fn($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'orders' => $z->orders_count ?? 0,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'total_revenue' => $totalRevenue,
                'avg_delivery_time' => round($avgDeliveryTime ?? 0),
                'on_time_percent' => $onTimePercent,
                'by_couriers' => $courierStats,
                'by_zones' => $zoneStats,
            ],
        ]);
    }

    /**
     * Список проблем доставки
     */
    public function problems(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $query = DeliveryProblem::forRestaurant($restaurantId)
            ->with(['deliveryOrder', 'courier', 'resolvedBy'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('unresolved')) {
            $query->unresolved();
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        $problems = $query->limit($request->input('limit', 50))->get();

        $stats = [
            'open' => DeliveryProblem::forRestaurant($restaurantId)->open()->count(),
            'in_progress' => DeliveryProblem::forRestaurant($restaurantId)->where('status', 'in_progress')->count(),
            'resolved_today' => DeliveryProblem::forRestaurant($restaurantId)->where('status', 'resolved')->today()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $problems,
            'stats' => $stats,
        ]);
    }

    /**
     * Создать проблему доставки
     */
    public function createProblem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:customer_unavailable,wrong_address,door_locked,payment_issue,damaged_item,other',
            'description' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('delivery-problems', 'public');
        }

        $courierId = null;
        if (auth()->user()?->is_courier) {
            $courier = Courier::where('user_id', auth()->id())->first();
            $courierId = $courier?->id;
        }

        $problem = DeliveryProblem::create([
            'restaurant_id' => $order->restaurant_id,
            'delivery_order_id' => $order->id,
            'courier_id' => $courierId ?? $order->courier_id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'photo_path' => $photoPath,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'status' => 'open',
        ]);

        $problem->load(['deliveryOrder', 'courier']);

        // Broadcast через Reverb: Новая проблема
        $this->broadcast('delivery', 'delivery_problem_created', [
            'problem_id' => $problem->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? $order->daily_number,
            'type' => $problem->type,
            'type_label' => $problem->type_label,
            'restaurant_id' => $order->restaurant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Проблема зарегистрирована',
            'data' => $problem,
        ], 201);
    }

    /**
     * Решить проблему
     */
    public function resolveProblem(Request $request, DeliveryProblem $problem): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string|max:1000',
        ]);

        $problem->resolve($validated['resolution'], auth()->id());

        $order = $problem->deliveryOrder;
        $this->broadcast('delivery', 'delivery_problem_resolved', [
            'problem_id' => $problem->id,
            'order_id' => $problem->delivery_order_id,
            'restaurant_id' => $order?->restaurant_id ?? auth()->user()?->restaurant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Проблема решена',
            'data' => $problem->fresh(['deliveryOrder', 'courier', 'resolvedBy']),
        ]);
    }

    /**
     * Отменить проблему
     */
    public function cancelProblem(DeliveryProblem $problem): JsonResponse
    {
        $problem->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Проблема отменена',
        ]);
    }
}
