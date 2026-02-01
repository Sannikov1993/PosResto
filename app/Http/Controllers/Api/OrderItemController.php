<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\RealtimeEvent;
use App\Http\Requests\Order\AddOrderItemRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    public function addItem(AddOrderItemRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $dish = Dish::forRestaurant($order->restaurant_id)->find($validated['dish_id']);
        if ($dish->is_stopped || !$dish->is_available) {
            return response()->json(['success' => false, 'message' => "Блюдо '{$dish->name}' недоступно"], 422);
        }

        $basePrice = (float) $dish->price;
        $priceListId = $order->price_list_id;
        $price = $priceListId
            ? (new \App\Services\PriceListService())->resolvePrice($dish, $priceListId)
            : $basePrice;

        $itemTotal = $price * $validated['quantity'];
        $item = OrderItem::create([
            'order_id' => $order->id,
            'price_list_id' => $priceListId,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'price' => $price,
            'base_price' => $priceListId ? $basePrice : null,
            'quantity' => $validated['quantity'],
            'total' => $itemTotal,
            'modifiers' => $validated['modifiers'] ?? null,
            'comment' => $validated['notes'] ?? null,
        ]);

        $subtotal = $order->items()->sum('total');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal - $order->discount_amount + ($order->delivery_fee ?? 0)]);

        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id, 'order_number' => $order->order_number,
            'action' => 'item_added', 'item' => $item->toArray(), 'new_total' => $order->fresh()->total,
        ]);

        return response()->json(['success' => true, 'message' => 'Позиция добавлена', 'data' => $order->fresh(['items.dish', 'table'])]);
    }

    /**
     * Обновить статус отдельной позиции заказа (для кухни)
     */
    public function updateItemStatus(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        // Проверяем, что позиция принадлежит заказу
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу'
            ], 400);
        }

        $validated = $request->validate([
            'status' => 'required|in:cooking,ready,return_to_cooking',
        ]);

        $newStatus = $validated['status'];

        switch ($newStatus) {
            case 'cooking':
                // Взять позицию в работу
                $item->update([
                    'status' => 'cooking',
                    'cooking_started_at' => now(),
                ]);
                // Обновляем статус заказа если нужно
                if ($order->status === 'confirmed') {
                    $order->update(['status' => 'cooking']);
                }
                break;

            case 'ready':
                // Отметить позицию как готовую
                $item->update([
                    'status' => 'ready',
                    'cooking_finished_at' => now(),
                ]);
                // Проверяем, все ли позиции готовы
                $hasCookingItems = $order->items()->where('status', 'cooking')->exists();
                if (!$hasCookingItems) {
                    $order->update(['status' => 'ready']);
                }
                break;

            case 'return_to_cooking':
                // Вернуть позицию из "Готово" в "Готовится"
                $item->update([
                    'status' => 'cooking',
                    'cooking_finished_at' => null,
                ]);
                // Если заказ был ready, возвращаем в cooking
                if ($order->status === 'ready') {
                    $order->update(['status' => 'cooking']);
                }
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Статус позиции обновлён',
            'data' => $item->fresh(),
        ]);
    }

    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['success' => false, 'message' => 'Позиция не принадлежит этому заказу'], 400);
        }

        $item->delete();
        $subtotal = $order->items()->sum('total');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal - $order->discount_amount + ($order->delivery_fee ?? 0)]);

        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id, 'order_number' => $order->order_number,
            'action' => 'item_removed', 'new_total' => $order->fresh()->total,
        ]);

        return response()->json(['success' => true, 'message' => 'Позиция удалена', 'data' => $order->fresh(['items.dish', 'table'])]);
    }

    /**
     * Отмена позиции (для позиций на кухне - со списанием)
     */
    public function cancelItem(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'reason_type' => 'required|string|max:100',
            'reason_comment' => 'nullable|string|max:500',
        ]);

        $order = $item->order;

        // Обновляем статус позиции
        $item->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason_type'] . ($validated['reason_comment'] ? ': ' . $validated['reason_comment'] : ''),
            'is_write_off' => true,
        ]);

        // Пересчитываем итого заказа (без отменённых позиций)
        $subtotal = $order->items()
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $order->discount_amount + ($order->delivery_fee ?? 0)
        ]);

        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'action' => 'item_cancelled',
            'item_id' => $item->id,
            'new_total' => $order->fresh()->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Позиция отменена',
            'new_status' => 'cancelled',
            'data' => $order->fresh(['items.dish', 'table'])
        ]);
    }

    /**
     * Заявка на отмену позиции (ожидает одобрения менеджера)
     */
    public function requestItemCancellation(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $item->update([
            'status' => 'pending_cancel',
            'cancellation_reason' => $validated['reason'],
        ]);

        RealtimeEvent::dispatch('cancellations', 'item_cancellation_requested', [
            'order_id' => $item->order_id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Заявка на отмену позиции отправлена',
            'new_status' => 'pending_cancel',
        ]);
    }

    /**
     * Подтвердить отмену позиции
     */
    public function approveItemCancellation(Request $request, OrderItem $item): JsonResponse
    {
        if ($item->status !== 'pending_cancel') {
            return response()->json(['success' => false, 'message' => 'Позиция не ожидает отмены'], 400);
        }

        $order = $item->order;

        $item->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'is_write_off' => true,
        ]);

        // Пересчитываем итого заказа
        $subtotal = $order->items()
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->sum('total');
        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $order->discount_amount + ($order->delivery_fee ?? 0)
        ]);

        RealtimeEvent::dispatch('orders', 'order_updated', [
            'order_id' => $order->id,
            'action' => 'item_cancellation_approved',
            'item_id' => $item->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Отмена позиции подтверждена']);
    }

    /**
     * Отклонить отмену позиции
     */
    public function rejectItemCancellation(Request $request, OrderItem $item): JsonResponse
    {
        if ($item->status !== 'pending_cancel') {
            return response()->json(['success' => false, 'message' => 'Позиция не ожидает отмены'], 400);
        }

        // Возвращаем предыдущий статус (cooking или ready)
        $item->update([
            'status' => 'cooking',
            'cancellation_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Заявка на отмену отклонена']);
    }
}
