<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenStation;
use App\Traits\BroadcastsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kitchen API Controller
 *
 * Kitchen Display System (KDS) integration.
 * Get orders queue, update item status, manage cooking workflow.
 */
class KitchenController extends BaseApiController
{
    use BroadcastsEvents;
    /**
     * Get list of kitchen stations
     *
     * GET /api/v1/kitchen/stations
     */
    public function stations(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $stations = KitchenStation::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success(
            $stations->map(function ($station) {
                return [
                    'id' => $station->id,
                    'name' => $station->name,
                    'slug' => $station->slug,
                    'icon' => $station->icon,
                    'color' => $station->color,
                    'is_bar' => $station->is_bar,
                ];
            })
        );
    }

    /**
     * Get kitchen queue (orders with items to cook)
     *
     * GET /api/v1/kitchen/queue
     */
    public function queue(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'station' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:pending,sent,cooking,ready',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        // Get orders with pending kitchen items
        $query = Order::where('restaurant_id', $restaurantId)
            ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::COOKING->value, OrderStatus::READY->value])
            ->with(['items' => function ($q) use ($data) {
                $q->whereIn('status', ['sent', 'cooking', 'ready'])
                  ->orderBy('sent_at');

                if (!empty($data['station'])) {
                    $q->where('station', $data['station']);
                }

                if (!empty($data['status'])) {
                    $q->where('status', $data['status']);
                }
            }, 'table', 'waiter'])
            ->whereHas('items', function ($q) use ($data) {
                $q->whereIn('status', ['sent', 'cooking', 'ready']);

                if (!empty($data['station'])) {
                    $q->where('station', $data['station']);
                }
            })
            ->orderBy('created_at');

        $orders = $query->limit($data['limit'] ?? 50)->get();

        return $this->success(
            $orders->map(function ($order) {
                return $this->formatOrderForKitchen($order);
            })
        );
    }

    /**
     * Get single order details for kitchen
     *
     * GET /api/v1/kitchen/orders/{orderId}
     */
    public function order(Request $request, int $orderId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)
            ->with(['items', 'table', 'waiter', 'customer'])
            ->find($orderId);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success($this->formatOrderForKitchen($order, true));
    }

    /**
     * Get items for a specific station
     *
     * GET /api/v1/kitchen/items
     */
    public function items(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'station' => 'required|string|max:50',
            'status' => 'nullable|string|in:sent,cooking,ready',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = OrderItem::where('restaurant_id', $restaurantId)
            ->where('station', $data['station'])
            ->with(['order.table', 'order.waiter', 'dish'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::COOKING->value, OrderStatus::READY->value]);
            });

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        } else {
            $query->whereIn('status', ['sent', 'cooking', 'ready']);
        }

        $total = $query->count();

        $items = $query
            ->orderBy('sent_at')
            ->limit($data['limit'] ?? 100)
            ->offset($data['offset'] ?? 0)
            ->get();

        return $this->success([
            'items' => $items->map(function ($item) {
                return $this->formatItemForKitchen($item);
            }),
            'pagination' => [
                'total' => $total,
                'limit' => $data['limit'] ?? 100,
                'offset' => $data['offset'] ?? 0,
            ],
        ]);
    }

    /**
     * Start cooking an item
     *
     * POST /api/v1/kitchen/items/{itemId}/start
     */
    public function startItem(Request $request, int $itemId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $item = OrderItem::where('restaurant_id', $restaurantId)
            ->with(['order', 'dish'])
            ->find($itemId);

        if (!$item) {
            return $this->notFound('Item not found');
        }

        if (!in_array($item->status, [OrderItem::STATUS_SENT, OrderItem::STATUS_PENDING])) {
            return $this->businessError(
                'INVALID_STATUS',
                "Cannot start cooking item with status '{$item->status}'"
            );
        }

        $item->startCooking();

        // Update order status if needed
        if ($item->order->status === OrderStatus::CONFIRMED->value) {
            $oldStatus = $item->order->status;
            $item->order->update(['status' => OrderStatus::COOKING->value]);
            // Broadcast order status change to POS
            $this->broadcastOrderStatusChanged($item->order->fresh(), $oldStatus, OrderStatus::COOKING->value);
        } else {
            // Broadcast item update even if order status didn't change
            $this->broadcastOrderUpdated($item->order->fresh());
        }

        // Dispatch webhook
        $this->dispatchWebhook('kitchen.item_started', [
            'order_id' => $item->order_id,
            'item_id' => $item->id,
            'dish_name' => $item->name,
            'station' => $item->station,
        ], $restaurantId);

        return $this->success([
            'item_id' => $item->id,
            'status' => $item->status,
            'cooking_started_at' => $this->formatDateTime($item->cooking_started_at),
        ], 'Cooking started');
    }

    /**
     * Mark item as ready
     *
     * POST /api/v1/kitchen/items/{itemId}/ready
     */
    public function readyItem(Request $request, int $itemId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $item = OrderItem::where('restaurant_id', $restaurantId)
            ->with(['order', 'dish'])
            ->find($itemId);

        if (!$item) {
            return $this->notFound('Item not found');
        }

        if (!in_array($item->status, [OrderItem::STATUS_COOKING, OrderItem::STATUS_SENT])) {
            return $this->businessError(
                'INVALID_STATUS',
                "Cannot mark as ready item with status '{$item->status}'"
            );
        }

        $item->markReady();

        // Check if all items are ready
        $allReady = $item->order->items()
            ->whereNotIn('status', [OrderItem::STATUS_READY, OrderItem::STATUS_SERVED, OrderItem::STATUS_CANCELLED, OrderItem::STATUS_VOIDED])
            ->count() === 0;

        if ($allReady && $item->order->status === OrderStatus::COOKING->value) {
            $oldStatus = $item->order->status;
            $item->order->update(['status' => OrderStatus::READY->value]);
            // Broadcast order status change to POS (this also triggers kitchen_ready event)
            $this->broadcastOrderStatusChanged($item->order->fresh(), $oldStatus, OrderStatus::READY->value);
        } else {
            // Broadcast item update even if order status didn't change
            $this->broadcastOrderUpdated($item->order->fresh());
        }

        // Dispatch webhook
        $this->dispatchWebhook('kitchen.item_ready', [
            'order_id' => $item->order_id,
            'item_id' => $item->id,
            'dish_name' => $item->name,
            'station' => $item->station,
            'cooking_time_minutes' => $item->getCookingTime(),
            'all_items_ready' => $allReady,
        ], $restaurantId);

        return $this->success([
            'item_id' => $item->id,
            'status' => $item->status,
            'cooking_finished_at' => $this->formatDateTime($item->cooking_finished_at),
            'cooking_time_minutes' => $item->getCookingTime(),
            'all_items_ready' => $allReady,
        ], 'Item ready');
    }

    /**
     * Mark item as served
     *
     * POST /api/v1/kitchen/items/{itemId}/served
     */
    public function servedItem(Request $request, int $itemId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $item = OrderItem::where('restaurant_id', $restaurantId)
            ->with(['order'])
            ->find($itemId);

        if (!$item) {
            return $this->notFound('Item not found');
        }

        if ($item->status !== OrderItem::STATUS_READY) {
            return $this->businessError(
                'INVALID_STATUS',
                "Cannot mark as served item with status '{$item->status}'"
            );
        }

        $item->markServed();

        // Broadcast item update to POS
        $this->broadcastOrderUpdated($item->order->fresh());

        return $this->success([
            'item_id' => $item->id,
            'status' => $item->status,
            'served_at' => $this->formatDateTime($item->served_at),
        ], 'Item served');
    }

    /**
     * Recall item (move back to cooking)
     *
     * POST /api/v1/kitchen/items/{itemId}/recall
     */
    public function recallItem(Request $request, int $itemId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'reason' => 'nullable|string|max:500',
        ]);

        $item = OrderItem::where('restaurant_id', $restaurantId)
            ->with(['order'])
            ->find($itemId);

        if (!$item) {
            return $this->notFound('Item not found');
        }

        if (!in_array($item->status, [OrderItem::STATUS_READY, OrderItem::STATUS_SERVED])) {
            return $this->businessError(
                'INVALID_STATUS',
                "Cannot recall item with status '{$item->status}'"
            );
        }

        $previousStatus = $item->status;

        $item->update([
            'status' => OrderItem::STATUS_COOKING,
            'cooking_started_at' => now(),
            'cooking_finished_at' => null,
            'served_at' => null,
        ]);

        // If order was 'ready', change back to 'cooking' since item was recalled
        if ($item->order->status === OrderStatus::READY->value) {
            $oldStatus = $item->order->status;
            $item->order->update(['status' => OrderStatus::COOKING->value]);
            $this->broadcastOrderStatusChanged($item->order->fresh(), $oldStatus, OrderStatus::COOKING->value);
        } else {
            // Broadcast item update to POS
            $this->broadcastOrderUpdated($item->order->fresh());
        }

        // Dispatch webhook
        $this->dispatchWebhook('kitchen.item_recalled', [
            'order_id' => $item->order_id,
            'item_id' => $item->id,
            'dish_name' => $item->name,
            'previous_status' => $previousStatus,
            'reason' => $data['reason'] ?? null,
        ], $restaurantId);

        return $this->success([
            'item_id' => $item->id,
            'status' => $item->status,
            'previous_status' => $previousStatus,
        ], 'Item recalled to kitchen');
    }

    /**
     * Bulk update items status
     *
     * POST /api/v1/kitchen/items/bulk-status
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'item_ids' => 'required|array|min:1|max:50',
            'item_ids.*' => 'integer',
            'status' => 'required|string|in:cooking,ready,served',
        ]);

        $items = OrderItem::where('restaurant_id', $restaurantId)
            ->whereIn('id', $data['item_ids'])
            ->with(['order'])
            ->get();

        if ($items->isEmpty()) {
            return $this->notFound('No items found');
        }

        $updated = [];
        $errors = [];

        foreach ($items as $item) {
            try {
                switch ($data['status']) {
                    case 'cooking':
                        if (in_array($item->status, [OrderItem::STATUS_SENT, OrderItem::STATUS_PENDING])) {
                            $item->startCooking();
                            $updated[] = $item->id;
                        } else {
                            $errors[$item->id] = "Invalid status transition from '{$item->status}'";
                        }
                        break;

                    case 'ready':
                        if (in_array($item->status, [OrderItem::STATUS_COOKING, OrderItem::STATUS_SENT])) {
                            $item->markReady();
                            $updated[] = $item->id;
                        } else {
                            $errors[$item->id] = "Invalid status transition from '{$item->status}'";
                        }
                        break;

                    case 'served':
                        if ($item->status === OrderItem::STATUS_READY) {
                            $item->markServed();
                            $updated[] = $item->id;
                        } else {
                            $errors[$item->id] = "Invalid status transition from '{$item->status}'";
                        }
                        break;
                }
            } catch (\Exception $e) {
                $errors[$item->id] = config('app.debug') ? $e->getMessage() : 'Item update failed';
            }
        }

        return $this->success([
            'updated' => $updated,
            'updated_count' => count($updated),
            'errors' => $errors,
        ], count($updated) . ' items updated');
    }

    /**
     * Get kitchen statistics
     *
     * GET /api/v1/kitchen/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'station' => 'nullable|string|max:50',
            'since' => 'nullable|date',
        ]);

        $since = $data['since'] ?? now()->startOfDay();

        $baseQuery = OrderItem::where('restaurant_id', $restaurantId);

        if (!empty($data['station'])) {
            $baseQuery->where('station', $data['station']);
        }

        // Current queue
        $pendingCount = (clone $baseQuery)->where('status', OrderItem::STATUS_SENT)->count();
        $cookingCount = (clone $baseQuery)->where('status', OrderItem::STATUS_COOKING)->count();
        $readyCount = (clone $baseQuery)->where('status', OrderItem::STATUS_READY)->count();

        // Today's stats
        $completedToday = (clone $baseQuery)
            ->where('cooking_finished_at', '>=', $since)
            ->whereNotNull('cooking_finished_at')
            ->count();

        // Average cooking time
        $avgCookingTime = (clone $baseQuery)
            ->where('cooking_finished_at', '>=', $since)
            ->whereNotNull('cooking_started_at')
            ->whereNotNull('cooking_finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, cooking_started_at, cooking_finished_at)) as avg_time')
            ->value('avg_time');

        return $this->success([
            'queue' => [
                'pending' => $pendingCount,
                'cooking' => $cookingCount,
                'ready' => $readyCount,
                'total' => $pendingCount + $cookingCount + $readyCount,
            ],
            'today' => [
                'completed' => $completedToday,
                'avg_cooking_time_minutes' => round($avgCookingTime ?? 0, 1),
            ],
            'station' => $data['station'] ?? 'all',
            'since' => $this->formatDateTime($since),
        ]);
    }

    /**
     * Format order for kitchen display
     */
    protected function formatOrderForKitchen(Order $order, bool $detailed = false): array
    {
        $data = [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'status' => $order->status,
            'type' => $order->type,
            'table' => $order->table ? [
                'id' => $order->table->id,
                'name' => $order->table->name,
                'zone' => $order->table->zone?->name,
            ] : null,
            'waiter' => $order->waiter ? [
                'id' => $order->waiter->id,
                'name' => $order->waiter->name,
            ] : null,
            'guests_count' => $order->guests_count,
            'created_at' => $this->formatDateTime($order->created_at),
            'elapsed_minutes' => $order->created_at->diffInMinutes(now()),
            'items' => $order->items->map(function ($item) {
                return $this->formatItemForKitchen($item, false);
            }),
            'item_counts' => [
                'total' => $order->items->count(),
                'pending' => $order->items->where('status', OrderItem::STATUS_SENT)->count(),
                'cooking' => $order->items->where('status', OrderItem::STATUS_COOKING)->count(),
                'ready' => $order->items->where('status', OrderItem::STATUS_READY)->count(),
            ],
        ];

        if ($detailed && $order->customer) {
            $data['customer'] = [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
            ];
        }

        if ($detailed) {
            $data['comment'] = $order->comment;
        }

        return $data;
    }

    /**
     * Format item for kitchen display
     */
    protected function formatItemForKitchen(OrderItem $item, bool $includeOrder = true): array
    {
        $data = [
            'id' => $item->id,
            'dish_id' => $item->dish_id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'status' => $item->status,
            'status_label' => $item->getStatusLabel(),
            'station' => $item->station,
            'guest_number' => $item->guest_number,
            'modifiers' => $item->modifiers,
            'modifiers_text' => $item->getModifiersText(),
            'comment' => $item->comment,
            'sent_at' => $this->formatDateTime($item->sent_at),
            'cooking_started_at' => $this->formatDateTime($item->cooking_started_at),
            'cooking_finished_at' => $this->formatDateTime($item->cooking_finished_at),
            'cooking_time_minutes' => $item->getCookingTime(),
        ];

        if ($includeOrder && $item->order) {
            $data['order'] = [
                'id' => $item->order->id,
                'number' => $item->order->number,
                'table' => $item->order->table?->name,
                'waiter' => $item->order->waiter?->name,
            ];
        }

        return $data;
    }

    /**
     * Dispatch webhook event
     */
    protected function dispatchWebhook(string $eventType, array $data, int $restaurantId): void
    {
        try {
            app(\App\Services\WebhookService::class)->dispatch($eventType, $data, $restaurantId);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
