<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Batch Operations API Controller
 *
 * Execute multiple operations in a single request.
 * Useful for sync, bulk updates, and reducing API calls.
 */
class BatchController extends BaseApiController
{
    /**
     * Execute multiple operations in batch
     *
     * POST /api/v1/batch
     *
     * @example Request body:
     * {
     *   "operations": [
     *     {"method": "GET", "path": "/menu/dishes/123", "id": "dish1"},
     *     {"method": "PATCH", "path": "/orders/456", "body": {"status": "confirmed"}, "id": "order1"}
     *   ],
     *   "atomic": false
     * }
     */
    public function execute(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request, [
            'operations' => 'required|array|min:1|max:25',
            'operations.*.method' => 'required|string|in:GET,POST,PATCH,PUT,DELETE',
            'operations.*.path' => 'required|string|max:500',
            'operations.*.body' => 'nullable|array',
            'operations.*.id' => 'nullable|string|max:50',
            'atomic' => 'nullable|boolean',
        ]);

        $isAtomic = $data['atomic'] ?? false;
        $results = [];

        if ($isAtomic) {
            DB::beginTransaction();
        }

        try {
            foreach ($data['operations'] as $index => $operation) {
                $operationId = $operation['id'] ?? "op_{$index}";

                try {
                    $result = $this->executeOperation(
                        $request,
                        $operation['method'],
                        $operation['path'],
                        $operation['body'] ?? []
                    );

                    $results[] = [
                        'id' => $operationId,
                        'success' => true,
                        'status' => $result['status'] ?? 200,
                        'data' => $result['data'] ?? null,
                    ];
                } catch (\Exception $e) {
                    $errorResult = [
                        'id' => $operationId,
                        'success' => false,
                        'status' => $this->getErrorStatusCode($e),
                        'error' => [
                            'code' => $this->getErrorCode($e),
                            'message' => $e->getMessage(),
                        ],
                    ];

                    $results[] = $errorResult;

                    if ($isAtomic) {
                        DB::rollBack();
                        return $this->success([
                            'success' => false,
                            'results' => $results,
                            'failed_at' => $operationId,
                            'message' => 'Batch operation failed, all changes rolled back',
                        ]);
                    }
                }
            }

            if ($isAtomic) {
                DB::commit();
            }

            $allSuccess = collect($results)->every(fn($r) => $r['success']);

            return $this->success([
                'success' => $allSuccess,
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => collect($results)->where('success', true)->count(),
                    'failed' => collect($results)->where('success', false)->count(),
                ],
            ]);

        } catch (\Exception $e) {
            if ($isAtomic) {
                DB::rollBack();
            }

            return $this->businessError('BATCH_FAILED', $e->getMessage());
        }
    }

    /**
     * Batch update menu stop list
     *
     * POST /api/v1/batch/stop-list
     */
    public function updateStopList(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'items' => 'required|array|min:1|max:100',
            'items.*.dish_id' => 'required|integer',
            'items.*.is_available' => 'required|boolean',
            'items.*.reason' => 'nullable|string|max:200',
        ]);

        $results = [];
        $updatedCount = 0;

        foreach ($data['items'] as $item) {
            $dish = Dish::where('restaurant_id', $restaurantId)
                ->find($item['dish_id']);

            if (!$dish) {
                $results[] = [
                    'dish_id' => $item['dish_id'],
                    'success' => false,
                    'error' => 'Dish not found',
                ];
                continue;
            }

            $dish->update([
                'is_available' => $item['is_available'],
                'stop_reason' => $item['is_available'] ? null : ($item['reason'] ?? null),
            ]);

            $results[] = [
                'dish_id' => $item['dish_id'],
                'name' => $dish->name,
                'success' => true,
                'is_available' => $item['is_available'],
            ];
            $updatedCount++;
        }

        // Dispatch webhook for menu update
        if ($updatedCount > 0) {
            $this->dispatchWebhook('menu.updated', [
                'type' => 'stop_list',
                'updated_count' => $updatedCount,
            ], $restaurantId);
        }

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($data['items']),
                'updated' => $updatedCount,
                'failed' => count($data['items']) - $updatedCount,
            ],
        ], "{$updatedCount} items updated");
    }

    /**
     * Batch update order items status
     *
     * POST /api/v1/batch/order-items-status
     */
    public function updateOrderItemsStatus(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'items' => 'required|array|min:1|max:100',
            'items.*.item_id' => 'required|integer',
            'items.*.status' => 'required|string|in:cooking,ready,served',
        ]);

        $results = [];
        $updatedCount = 0;

        foreach ($data['items'] as $itemData) {
            $item = OrderItem::where('restaurant_id', $restaurantId)
                ->find($itemData['item_id']);

            if (!$item) {
                $results[] = [
                    'item_id' => $itemData['item_id'],
                    'success' => false,
                    'error' => 'Item not found',
                ];
                continue;
            }

            try {
                switch ($itemData['status']) {
                    case 'cooking':
                        $item->startCooking();
                        break;
                    case 'ready':
                        $item->markReady();
                        break;
                    case 'served':
                        $item->markServed();
                        break;
                }

                $results[] = [
                    'item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'success' => true,
                    'status' => $item->status,
                ];
                $updatedCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'item_id' => $itemData['item_id'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($data['items']),
                'updated' => $updatedCount,
                'failed' => count($data['items']) - $updatedCount,
            ],
        ], "{$updatedCount} items updated");
    }

    /**
     * Batch confirm orders
     *
     * POST /api/v1/batch/confirm-orders
     */
    public function confirmOrders(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_ids' => 'required|array|min:1|max:50',
            'order_ids.*' => 'integer',
            'estimated_time' => 'nullable|integer|min:1|max:180',
        ]);

        $results = [];
        $confirmedCount = 0;

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereIn('id', $data['order_ids'])
            ->get();

        foreach ($data['order_ids'] as $orderId) {
            $order = $orders->firstWhere('id', $orderId);

            if (!$order) {
                $results[] = [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => 'Order not found',
                ];
                continue;
            }

            if ($order->status !== 'new') {
                $results[] = [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => "Cannot confirm order with status '{$order->status}'",
                ];
                continue;
            }

            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'estimated_delivery_minutes' => $data['estimated_time'] ?? null,
            ]);

            $results[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'success' => true,
                'status' => 'confirmed',
            ];
            $confirmedCount++;

            // Dispatch webhook
            $this->dispatchWebhook('order.updated', [
                'order_id' => $order->id,
                'status' => 'confirmed',
            ], $restaurantId);
        }

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($data['order_ids']),
                'confirmed' => $confirmedCount,
                'failed' => count($data['order_ids']) - $confirmedCount,
            ],
        ], "{$confirmedCount} orders confirmed");
    }

    /**
     * Batch update customer tags
     *
     * POST /api/v1/batch/customer-tags
     */
    public function updateCustomerTags(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'customers' => 'required|array|min:1|max:100',
            'customers.*.customer_id' => 'required|integer',
            'customers.*.add_tags' => 'nullable|array',
            'customers.*.add_tags.*' => 'string|max:50',
            'customers.*.remove_tags' => 'nullable|array',
            'customers.*.remove_tags.*' => 'string|max:50',
        ]);

        $results = [];
        $updatedCount = 0;

        foreach ($data['customers'] as $customerData) {
            $customer = Customer::where('restaurant_id', $restaurantId)
                ->find($customerData['customer_id']);

            if (!$customer) {
                $results[] = [
                    'customer_id' => $customerData['customer_id'],
                    'success' => false,
                    'error' => 'Customer not found',
                ];
                continue;
            }

            $currentTags = $customer->tags ?? [];

            // Add tags
            if (!empty($customerData['add_tags'])) {
                $currentTags = array_unique(array_merge($currentTags, $customerData['add_tags']));
            }

            // Remove tags
            if (!empty($customerData['remove_tags'])) {
                $currentTags = array_diff($currentTags, $customerData['remove_tags']);
            }

            $customer->update(['tags' => array_values($currentTags)]);

            $results[] = [
                'customer_id' => $customer->id,
                'success' => true,
                'tags' => $customer->tags,
            ];
            $updatedCount++;
        }

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($data['customers']),
                'updated' => $updatedCount,
                'failed' => count($data['customers']) - $updatedCount,
            ],
        ], "{$updatedCount} customers updated");
    }

    /**
     * Execute a single operation
     */
    protected function executeOperation(Request $parentRequest, string $method, string $path, array $body): array
    {
        // Parse path to determine operation type
        $path = ltrim($path, '/');

        // Simple routing for common operations
        // In a production system, this would be more sophisticated

        if (preg_match('/^menu\/dishes\/(\d+)$/', $path, $matches) && $method === 'GET') {
            return $this->getDish($parentRequest, (int) $matches[1]);
        }

        if (preg_match('/^orders\/(\d+)$/', $path, $matches) && $method === 'PATCH') {
            return $this->updateOrder($parentRequest, (int) $matches[1], $body);
        }

        if (preg_match('/^customers\/(\d+)$/', $path, $matches) && $method === 'PATCH') {
            return $this->updateCustomer($parentRequest, (int) $matches[1], $body);
        }

        throw new \Exception("Unsupported operation: {$method} {$path}");
    }

    /**
     * Get dish for batch operation
     */
    protected function getDish(Request $request, int $dishId): array
    {
        $restaurantId = $this->getRestaurantId($request);
        $dish = Dish::where('restaurant_id', $restaurantId)->find($dishId);

        if (!$dish) {
            throw new \Exception('Dish not found');
        }

        return [
            'status' => 200,
            'data' => [
                'id' => $dish->id,
                'name' => $dish->name,
                'price' => (float) $dish->price,
                'is_available' => $dish->is_available,
            ],
        ];
    }

    /**
     * Update order for batch operation
     */
    protected function updateOrder(Request $request, int $orderId, array $body): array
    {
        $restaurantId = $this->getRestaurantId($request);
        $order = Order::where('restaurant_id', $restaurantId)->find($orderId);

        if (!$order) {
            throw new \Exception('Order not found');
        }

        $allowedFields = ['status', 'comment', 'notes'];
        $updateData = array_intersect_key($body, array_flip($allowedFields));

        if (empty($updateData)) {
            throw new \Exception('No valid fields to update');
        }

        $order->update($updateData);

        return [
            'status' => 200,
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ];
    }

    /**
     * Update customer for batch operation
     */
    protected function updateCustomer(Request $request, int $customerId, array $body): array
    {
        $restaurantId = $this->getRestaurantId($request);
        $customer = Customer::where('restaurant_id', $restaurantId)->find($customerId);

        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        $allowedFields = ['name', 'email', 'notes', 'tags'];
        $updateData = array_intersect_key($body, array_flip($allowedFields));

        if (empty($updateData)) {
            throw new \Exception('No valid fields to update');
        }

        $customer->update($updateData);

        return [
            'status' => 200,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ];
    }

    /**
     * Get error status code from exception
     */
    protected function getErrorStatusCode(\Exception $e): int
    {
        if (str_contains($e->getMessage(), 'not found')) {
            return 404;
        }
        if (str_contains($e->getMessage(), 'Unsupported')) {
            return 400;
        }
        return 422;
    }

    /**
     * Get error code from exception
     */
    protected function getErrorCode(\Exception $e): string
    {
        if (str_contains($e->getMessage(), 'not found')) {
            return 'NOT_FOUND';
        }
        if (str_contains($e->getMessage(), 'Unsupported')) {
            return 'UNSUPPORTED_OPERATION';
        }
        return 'OPERATION_FAILED';
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
