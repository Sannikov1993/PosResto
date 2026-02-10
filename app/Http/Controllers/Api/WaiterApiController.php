<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Zone;
use App\Events\OrderEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Enums\PaymentStatus;

class WaiterApiController extends Controller
{
    protected $guestColors = [
        1 => '#22c55e',
        2 => '#f97316',
        3 => '#ec4899',
        4 => '#3b82f6',
        5 => '#8b5cf6',
        6 => '#06b6d4',
        7 => '#eab308',
        8 => '#ef4444',
    ];

    public function tables(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $zones = Zone::with(['tables' => function ($q) {
            $q->withCount(['orders' => function ($q) {
                $q->whereIn('status', [OrderStatus::NEW->value, 'open', OrderStatus::COOKING->value, OrderStatus::READY->value]);
            }])->orderBy('number');
        }])
        ->where('restaurant_id', $restaurantId)
        ->get();

        return response()->json(['success' => true, 'data' => $zones]);
    }

    public function table($id): JsonResponse
    {
        // Global Scope BelongsToRestaurant обеспечивает фильтрацию по restaurant_id
        // Если TenantManager не установлен — выбросится TenantNotSetException
        $table = Table::with(['orders' => function ($q) {
            $q->whereIn('status', [OrderStatus::NEW->value, 'open', OrderStatus::COOKING->value, OrderStatus::READY->value])
              ->with(['items.dish:id,name,price,image', 'customer:id,name,phone'])
              ->orderBy('created_at', 'desc');
        }, 'zone:id,name,color'])
        ->findOrFail($id);

        // Дополнительная проверка принадлежности текущему ресторану
        $table->requireCurrentRestaurant();

        return response()->json([
            'success' => true,
            'data' => $table,
            'guest_colors' => $this->guestColors
        ]);
    }

    public function menuCategories(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $categories = Category::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function categoryProducts($categoryId): JsonResponse
    {
        // Global Scope автоматически фильтрует по restaurant_id
        // Дополнительно проверяем что категория принадлежит текущему ресторану
        $category = Category::findOrFail($categoryId);
        $category->requireCurrentRestaurant();

        $products = Dish::where('category_id', $categoryId)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price', 'image', 'is_available', 'cooking_time', 'weight']);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function addOrderItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'dish_id' => 'required|exists:dishes,id',
            'guest_number' => 'required|integer|min:1|max:20',
            'quantity' => 'nullable|integer|min:1',
            'comment' => 'nullable|string|max:255',
        ]);

        // Проверяем что стол и блюдо принадлежат текущему ресторану
        $table = Table::findOrFail($validated['table_id']);
        $table->requireCurrentRestaurant();

        $dish = Dish::findOrFail($validated['dish_id']);
        $dish->requireCurrentRestaurant();

        $order = Order::where('table_id', $validated['table_id'])
            ->whereIn('status', [OrderStatus::NEW->value, 'open'])
            ->first();

        if (!$order) {
            $restaurantId = $this->getRestaurantId($request);
            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'table_id' => $validated['table_id'],
                'type' => OrderType::DINE_IN->value,
                'status' => OrderStatus::NEW->value,
                'user_id' => auth()->id(),
                'persons' => $validated['guest_number'],
                'order_number' => Order::generateOrderNumber($restaurantId),
            ]);
        }

        $item = OrderItem::create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => $validated['quantity'] ?? 1,
            'price' => $dish->price,
            'total' => $dish->price * ($validated['quantity'] ?? 1),
            'guest_number' => $validated['guest_number'],
            'status' => 'pending',
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Позиция добавлена',
            'data' => $item->load('dish:id,name,price')
        ], 201);
    }

    public function updateOrderItem(Request $request, $id): JsonResponse
    {
        $item = OrderItem::findOrFail($id);
        // Проверяем принадлежность к текущему ресторану
        $item->requireCurrentRestaurant();

        if (!in_array($item->status, ['new', 'pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя изменить - уже на кухне'
            ], 400);
        }

        $validated = $request->validate(['quantity' => 'required|integer|min:0']);

        if ($validated['quantity'] === 0) {
            $item->delete();
            return response()->json(['success' => true, 'message' => 'Позиция удалена']);
        }

        $item->update([
            'quantity' => $validated['quantity'],
            'total' => $item->price * $validated['quantity'],
        ]);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function deleteOrderItem($id): JsonResponse
    {
        $item = OrderItem::findOrFail($id);
        // Проверяем принадлежность к текущему ресторану
        $item->requireCurrentRestaurant();

        if (!in_array($item->status, ['new', 'pending'])) {
            return response()->json(['success' => false, 'message' => 'Нельзя удалить'], 400);
        }
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Удалено']);
    }

    public function sendToKitchen($orderId): JsonResponse
    {
        $order = Order::with('items')->findOrFail($orderId);
        // Проверяем принадлежность к текущему ресторану
        $order->requireCurrentRestaurant();

        $count = $order->items()
            ->where('status', 'pending')
            ->update(['status' => 'cooking', 'sent_at' => now(), 'cooking_started_at' => now()]);

        if ($order->status === OrderStatus::NEW->value) {
            $order->update(['status' => OrderStatus::COOKING->value]);
        }

        return response()->json(['success' => true, 'message' => 'Отправлено', 'sent_count' => $count]);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::with(['table:id,number', 'items'])
            ->where('user_id', auth()->id())
            ->whereIn('status', [OrderStatus::NEW->value, 'open', OrderStatus::COOKING->value, OrderStatus::READY->value])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function serveOrder($orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        // Проверяем принадлежность к текущему ресторану
        $order->requireCurrentRestaurant();

        $order->items()->where('status', 'ready')->update(['status' => 'served', 'served_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Выдано']);
    }

    public function payOrder(Request $request, $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        // Проверяем принадлежность к текущему ресторану
        $order->requireCurrentRestaurant();

        $paymentMethod = $request->input('payment_method', 'cash');

        $order->update([
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
        ]);
        $order->table?->update(['status' => 'free']);

        // Отправляем событие через WebSocket
        OrderEvent::dispatch($order->restaurant_id, 'order_paid', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'method' => $paymentMethod,
            'message' => "Заказ #{$order->order_number} оплачен",
            'sound' => 'payment',
        ]);

        // Записываем в кассу
        try {
            \App\Models\CashOperation::recordOrderPayment($order, $paymentMethod);
        } catch (\Exception $e) {
            \Log::warning('Waiter payOrder cash operation failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Оплачено']);
    }

    public function profileStats(): JsonResponse
    {
        $waiterId = auth()->id();
        $today = now()->startOfDay();

        $stats = [
            'orders_today' => Order::where('user_id', $waiterId)
                ->whereDate('created_at', $today)
                ->count(),
            'revenue_today' => Order::where('user_id', $waiterId)
                ->whereDate('created_at', $today)
                ->where('payment_status', PaymentStatus::PAID->value)
                ->sum('total'),
            'tips_today' => Order::where('user_id', $waiterId)
                ->whereDate('created_at', $today)
                ->where('payment_status', PaymentStatus::PAID->value)
                ->sum('tips'),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        if ($request->has('restaurant_id') && $user) {
            if ($user->isSuperAdmin()) {
                return (int) $request->restaurant_id;
            }
            $restaurant = \App\Models\Restaurant::where('id', $request->restaurant_id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
            if ($restaurant) {
                return $restaurant->id;
            }
        }

        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        abort(401, 'Требуется авторизация');
    }
}
