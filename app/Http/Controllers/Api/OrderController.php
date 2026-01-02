<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Table;
use App\Models\RealtimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Список заказов
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        // Фильтр по дате
        if ($request->boolean('today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтр по типу
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Только для кухни (новые и готовящиеся)
        if ($request->boolean('kitchen')) {
            $query->whereIn('status', ['new', 'cooking']);
        }

        // Только доставка
        if ($request->boolean('delivery')) {
            $query->where('type', 'delivery');
        }

        $orders = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Создание заказа
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:dine_in,delivery,pickup',
            'table_id' => 'nullable|integer|exists:tables,id',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
            // Поля для доставки
            'phone' => 'nullable|string|max:20',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        // Генерируем номер заказа
        $today = Carbon::today();
        $orderCount = Order::whereDate('created_at', $today)->count() + 1;
        $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);
        $dailyNumber = '#' . $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

        // Создаём заказ
        $order = Order::create([
            'restaurant_id' => $restaurantId,
            'order_number' => $orderNumber,
            'daily_number' => $dailyNumber,
            'type' => $validated['type'],
            'table_id' => $validated['table_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'waiter_id' => $request->input('waiter_id'),
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'notes' => $validated['notes'] ?? null,
            // Поля доставки
            'phone' => $validated['phone'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'delivery_notes' => $validated['delivery_notes'] ?? null,
            'delivery_status' => $validated['type'] === 'delivery' ? 'pending' : null,
        ]);

        // Добавляем позиции
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $dish = Dish::find($item['dish_id']);
            if (!$dish) continue;

            $itemTotal = $dish->price * $item['quantity'];
            $subtotal += $itemTotal;

            OrderItem::create([
                'order_id' => $order->id,
                'dish_id' => $dish->id,
                'name' => $dish->name,
                'price' => $dish->price,
                'quantity' => $item['quantity'],
                'total' => $itemTotal,
                'modifiers' => $item['modifiers'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        // Обновляем сумму заказа
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        // Занимаем стол если это зал
        if ($validated['type'] === 'dine_in' && $validated['table_id']) {
            Table::where('id', $validated['table_id'])->update(['status' => 'occupied']);
            
            // Broadcast table status
            RealtimeEvent::tableStatusChanged($validated['table_id'], 'occupied');
        }

        $order->load(['items.dish', 'table']);

        // 🔔 BROADCAST: Новый заказ
        RealtimeEvent::orderCreated($order->toArray());
        
        // Если доставка - дополнительное событие
        if ($validated['type'] === 'delivery') {
            RealtimeEvent::deliveryNew($order->toArray());
        }

        return response()->json([
            'success' => true,
            'message' => 'Заказ создан',
            'data' => $order,
        ], 201);
    }

    /**
     * Показать заказ
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $order->load(['items.dish', 'table', 'waiter', 'customer']),
        ]);
    }

    /**
     * Обновить статус заказа
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,cooking,ready,completed,cancelled',
        ]);

        $oldStatus = $order->status;
        $newStatus = $validated['status'];

        $order->update(['status' => $newStatus]);

        // Освобождаем стол при завершении/отмене
        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            Table::where('id', $order->table_id)->update(['status' => 'free']);
            RealtimeEvent::tableStatusChanged($order->table_id, 'free');
        }

        // Обновляем delivery_status если это доставка
        if ($order->type === 'delivery') {
            $deliveryStatusMap = [
                'cooking' => 'preparing',
                'ready' => 'ready',
                'completed' => 'delivered',
            ];
            if (isset($deliveryStatusMap[$newStatus])) {
                $order->update(['delivery_status' => $deliveryStatusMap[$newStatus]]);
            }
        }

        // 🔔 BROADCAST: Статус изменён
        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, $newStatus);

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
            'data' => $order->fresh(['items.dish', 'table']),
        ]);
    }

    /**
     * Оплата заказа
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,card,online',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $order->update([
            'payment_status' => 'paid',
            'payment_method' => $validated['method'],
            'paid_at' => now(),
        ]);

        // 🔔 BROADCAST: Заказ оплачен
        RealtimeEvent::orderPaid($order->fresh()->toArray(), $validated['method']);

        return response()->json([
            'success' => true,
            'message' => 'Оплата принята',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Обновить статус доставки
     */
    public function updateDeliveryStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'delivery_status' => 'required|in:pending,preparing,ready,picked_up,in_transit,delivered,cancelled',
        ]);

        $order->update([
            'delivery_status' => $validated['delivery_status'],
            'picked_up_at' => $validated['delivery_status'] === 'picked_up' ? now() : $order->picked_up_at,
            'delivered_at' => $validated['delivery_status'] === 'delivered' ? now() : $order->delivered_at,
        ]);

        // Если доставлено - завершаем заказ
        if ($validated['delivery_status'] === 'delivered') {
            $order->update(['status' => 'completed']);
        }

        // 🔔 BROADCAST: Статус доставки изменён
        RealtimeEvent::deliveryStatusChanged($order->fresh()->toArray(), $validated['delivery_status']);

        return response()->json([
            'success' => true,
            'message' => 'Статус доставки обновлён',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Назначить курьера
     */
    public function assignCourier(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => 'required|integer',
        ]);

        $order->update([
            'courier_id' => $validated['courier_id'],
            'delivery_status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        // 🔔 BROADCAST: Курьер назначен
        RealtimeEvent::dispatch('delivery', 'delivery_assigned', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'courier_id' => $validated['courier_id'],
            'message' => "Курьер назначен на заказ #{$order->order_number}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Добавить позицию в заказ
     */
    public function addItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id',
            'quantity' => 'required|integer|min:1',
            'modifiers' => 'nullable|array',
            'notes' => 'nullable|string|max:255',
        ]);

        $dish = Dish::find($validated['dish_id']);
        $itemTotal = $dish->price * $validated['quantity'];

        $item = OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->price,
            'quantity' => $validated['quantity'],
            'total' => $itemTotal,
            'modifiers' => $validated['modifiers'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Пересчитываем сумму
        $subtotal = $order->items()->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $order->discount + $order->tax,
        ]);

        // 🔔 BROADCAST: Заказ обновлён
        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => 'item_added',
            'item' => $item->toArray(),
            'new_total' => $order->fresh()->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Позиция добавлена',
            'data' => $order->fresh(['items.dish', 'table']),
        ]);
    }

    /**
     * Удалить позицию из заказа
     */
    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ], 400);
        }

        $item->delete();

        // Пересчитываем сумму
        $subtotal = $order->items()->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $order->discount + $order->tax,
        ]);

        // 🔔 BROADCAST: Заказ обновлён
        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => 'item_removed',
            'new_total' => $order->fresh()->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Позиция удалена',
            'data' => $order->fresh(['items.dish', 'table']),
        ]);
    }
}