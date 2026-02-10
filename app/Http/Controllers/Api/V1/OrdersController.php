<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Http\Resources\V1\OrderResource;
use App\Models\Customer;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Orders API Controller
 *
 * CRUD operations for orders via public API.
 */
class OrdersController extends BaseApiController
{
    /**
     * List orders
     *
     * GET /api/v1/orders
     *
     * Query params:
     * - status: string (filter by status)
     * - type: string (filter by type: dine_in, delivery, pickup)
     * - customer_id: int (filter by customer)
     * - from, to, date: date filters
     * - page, per_page: pagination
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['items.dish', 'customer', 'table']);

        // Status filter
        if ($request->has('status')) {
            $statuses = explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        // Type filter
        if ($request->has('type')) {
            $types = explode(',', $request->input('type'));
            $query->whereIn('type', $types);
        }

        // Customer filter
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Payment status filter
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        // Source filter
        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        // External ID filter
        if ($request->has('external_id')) {
            $query->where('external_id', $request->input('external_id'));
        }

        // Date filters
        $this->applyDateFilter($query, $request);

        // Sort
        $sort = $this->getSortParams($request, ['created_at', 'updated_at', 'total', 'status'], 'created_at', 'desc');
        $query->orderBy($sort['field'], $sort['direction']);

        // Paginate
        $pagination = $this->getPaginationParams($request);
        $orders = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return $this->paginated($orders, OrderResource::class);
    }

    /**
     * Get single order
     *
     * GET /api/v1/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)
            ->with(['items.dish', 'customer', 'table', 'waiter:id,name', 'statusHistory'])
            ->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success(new OrderResource($order));
    }

    /**
     * Create new order
     *
     * POST /api/v1/orders
     */
    public function store(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'type' => 'required|in:dine_in,delivery,pickup',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_phone' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'table_id' => 'nullable|integer|exists:tables,id',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.modifiers' => 'nullable|array',
            'items.*.comment' => 'nullable|string|max:500',
            'comment' => 'nullable|string|max:1000',
            'persons' => 'nullable|integer|min:1|max:50',
            'scheduled_at' => 'nullable|date|after:now',
            'delivery_address' => 'required_if:type,delivery|nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            'promo_code' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:50',
            'external_id' => 'nullable|string|max:100',
            'external_data' => 'nullable|array',
        ]);

        try {
            $order = DB::transaction(function () use ($data, $restaurantId, $request) {
                // Find or create customer
                $customerId = $data['customer_id'] ?? null;

                if (!$customerId && !empty($data['customer_phone'])) {
                    $customer = Customer::firstOrCreate(
                        [
                            'restaurant_id' => $restaurantId,
                            'phone' => $data['customer_phone'],
                        ],
                        [
                            'name' => $data['customer_name'] ?? 'Гость',
                            'tenant_id' => $this->getTenantId($request),
                        ]
                    );
                    $customerId = $customer->id;
                }

                // Create order
                $order = Order::create([
                    'restaurant_id' => $restaurantId,
                    'customer_id' => $customerId,
                    'type' => $data['type'],
                    'status' => OrderStatus::NEW->value,
                    'payment_status' => PaymentStatus::PENDING->value,
                    'table_id' => $data['table_id'] ?? null,
                    'comment' => $data['comment'] ?? null,
                    'persons' => $data['persons'] ?? 1,
                    'phone' => $data['customer_phone'] ?? null,
                    'scheduled_at' => $data['scheduled_at'] ?? null,
                    'is_asap' => empty($data['scheduled_at']),
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                    'delivery_latitude' => $data['delivery_latitude'] ?? null,
                    'delivery_longitude' => $data['delivery_longitude'] ?? null,
                    'promo_code' => $data['promo_code'] ?? null,
                    'source' => $data['source'] ?? 'api',
                    'external_id' => $data['external_id'] ?? null,
                    'external_data' => $data['external_data'] ?? null,
                ]);

                // Add items
                foreach ($data['items'] as $itemData) {
                    $dish = Dish::find($itemData['dish_id']);

                    if (!$dish || $dish->restaurant_id !== $restaurantId) {
                        throw new \Exception("Dish {$itemData['dish_id']} not found");
                    }

                    if (!$dish->is_available) {
                        throw new \Exception("Dish '{$dish->name}' is not available");
                    }

                    // Calculate modifiers price
                    $modifiersPrice = 0;
                    $modifiers = $itemData['modifiers'] ?? [];

                    // TODO: Validate and calculate modifier prices

                    $itemTotal = ($dish->price + $modifiersPrice) * $itemData['quantity'];

                    OrderItem::create([
                        'restaurant_id' => $restaurantId,
                        'order_id' => $order->id,
                        'dish_id' => $dish->id,
                        'name' => $dish->getFullName(),
                        'quantity' => $itemData['quantity'],
                        'price' => $dish->price,
                        'modifiers_price' => $modifiersPrice,
                        'total' => $itemTotal,
                        'modifiers' => $modifiers,
                        'comment' => $itemData['comment'] ?? null,
                        'kitchen_station_id' => $dish->kitchen_station_id,
                        'cooking_time' => $dish->cooking_time,
                    ]);
                }

                // Recalculate totals
                $order->recalculateTotal();
                $order->refresh();

                return $order;
            });

            $order->load(['items.dish', 'customer', 'table']);

            return $this->created(new OrderResource($order), 'Order created successfully');
        } catch (\Exception $e) {
            return $this->businessError(
                'ORDER_CREATION_FAILED',
                config('app.debug') ? $e->getMessage() : 'Order creation failed',
                422
            );
        }
    }

    /**
     * Update order
     *
     * PATCH /api/v1/orders/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        // Check if order can be modified
        if (in_array($order->status, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])) {
            return $this->businessError(
                'ORDER_CANNOT_BE_MODIFIED',
                'Completed or cancelled orders cannot be modified'
            );
        }

        $data = $this->validateRequest($request, [
            'comment' => 'nullable|string|max:1000',
            'persons' => 'nullable|integer|min:1|max:50',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
            'external_id' => 'nullable|string|max:100',
            'external_data' => 'nullable|array',
        ]);

        $order->update(array_filter($data, fn($v) => $v !== null));
        $order->refresh();
        $order->load(['items.dish', 'customer', 'table']);

        return $this->success(new OrderResource($order), 'Order updated successfully');
    }

    /**
     * Cancel order
     *
     * POST /api/v1/orders/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $data = $this->validateRequest($request, [
            'reason' => 'nullable|string|max:500',
        ]);

        if (!$order->cancel($data['reason'] ?? null)) {
            return $this->businessError(
                'ORDER_CANNOT_BE_CANCELLED',
                'Order cannot be cancelled in current status'
            );
        }

        $order->refresh();
        $order->load(['items.dish', 'customer', 'table']);

        return $this->success(new OrderResource($order), 'Order cancelled successfully');
    }

    /**
     * Confirm order
     *
     * POST /api/v1/orders/{id}/confirm
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if (!$order->confirm()) {
            return $this->businessError(
                'ORDER_CANNOT_BE_CONFIRMED',
                'Order cannot be confirmed in current status'
            );
        }

        $order->refresh();

        return $this->success(new OrderResource($order), 'Order confirmed');
    }

    /**
     * Mark order as ready
     *
     * POST /api/v1/orders/{id}/ready
     */
    public function markReady(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if (!$order->markReady()) {
            return $this->businessError(
                'ORDER_STATUS_CHANGE_FAILED',
                'Order cannot be marked as ready in current status'
            );
        }

        $order->refresh();

        return $this->success(new OrderResource($order), 'Order marked as ready');
    }

    /**
     * Complete order
     *
     * POST /api/v1/orders/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if (!$order->complete()) {
            return $this->businessError(
                'ORDER_CANNOT_BE_COMPLETED',
                'Order cannot be completed in current status'
            );
        }

        $order->refresh();

        return $this->success(new OrderResource($order), 'Order completed');
    }

    /**
     * Calculate order total (preview)
     *
     * POST /api/v1/orders/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.modifiers' => 'nullable|array',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'promo_code' => 'nullable|string|max:50',
            'type' => 'nullable|in:dine_in,delivery,pickup',
            'delivery_zone_id' => 'nullable|integer',
        ]);

        $subtotal = 0;
        $itemsPreview = [];

        foreach ($data['items'] as $itemData) {
            $dish = Dish::where('restaurant_id', $restaurantId)->find($itemData['dish_id']);

            if (!$dish) {
                return $this->notFound("Dish {$itemData['dish_id']} not found");
            }

            $modifiersPrice = 0; // TODO: Calculate from modifiers
            $itemTotal = ($dish->price + $modifiersPrice) * $itemData['quantity'];
            $subtotal += $itemTotal;

            $itemsPreview[] = [
                'dish_id' => $dish->id,
                'name' => $dish->getFullName(),
                'quantity' => $itemData['quantity'],
                'price' => number_format($dish->price, 2, '.', ''),
                'modifiers_price' => number_format($modifiersPrice, 2, '.', ''),
                'total' => number_format($itemTotal, 2, '.', ''),
            ];
        }

        // TODO: Apply promo code, loyalty discount, delivery fee
        $discount = 0;
        $deliveryFee = 0;
        $total = $subtotal - $discount + $deliveryFee;

        return $this->success([
            'items' => $itemsPreview,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'currency' => 'RUB',
        ]);
    }
}
