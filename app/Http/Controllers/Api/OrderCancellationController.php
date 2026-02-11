<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\RealtimeEvent;
use App\Models\CashOperation;
use App\Models\Reservation;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;

class OrderCancellationController extends Controller
{
    use Traits\ResolvesRestaurantId;
    use BroadcastsEvents;
    /**
     * Создать заявку на отмену заказа
     */
    public function requestCancellation(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'requested_by' => 'nullable|integer|exists:users,id',
        ]);

        $order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => $validated['reason'],
            'cancel_requested_by' => $validated['requested_by'] ?? auth()->id(),
            'cancel_requested_at' => now(),
        ]);

        $this->broadcast('orders', 'cancellation_requested', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => $validated['reason'],
            'restaurant_id' => $order->restaurant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Заявка на отмену отправлена',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Подтвердить отмену заказа
     */
    public function approveCancellation(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        if (!$order->pending_cancellation) {
            return response()->json(['success' => false, 'message' => 'Заказ не ожидает отмены'], 400);
        }

        $validated = $request->validate([
            'refund_method' => 'nullable|in:cash,card',
        ]);

        $isPaid = $order->payment_status === 'paid' || $order->prepayment > 0;

        // Если заказ был оплачен - создаём возврат
        if ($isPaid) {
            // Используем > 0 вместо ?:, т.к. decimal cast возвращает "0.00" (truthy string)
            $refundAmount = $order->prepayment > 0 ? $order->prepayment : $order->total;
            $refundMethod = $validated['refund_method'] ?? 'cash';

            CashOperation::recordOrderRefund(
                $order->restaurant_id,
                $order->id,
                $refundAmount,
                $refundMethod,
                null,
                $order->order_number,
                $order->cancel_request_reason
            );
        }

        $oldStatus = $order->status;
        $tableId = $order->table_id;
        $linkedTableIds = $order->linked_table_ids ?? [];
        $reservationId = $order->reservation_id;

        $order->update([
            'status' => OrderStatus::CANCELLED->value,
            'delivery_status' => $order->type !== OrderType::DINE_IN->value ? 'cancelled' : $order->delivery_status,
            'cancelled_at' => now(),
            'cancel_reason' => $order->cancel_request_reason,
            'is_write_off' => true,
            'pending_cancellation' => false,
        ]);

        // Обрабатываем бронирование - отменяем его тоже
        if ($reservationId) {
            $reservation = Reservation::forRestaurant($order->restaurant_id)->find($reservationId);
            if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                $reservation->update(['status' => 'cancelled']);
            }
        }

        if ($tableId) {
            Table::where('id', $tableId)
                ->where('restaurant_id', $order->restaurant_id)
                ->update(['status' => 'free']);
            $this->broadcastTableStatusChanged($tableId, 'free', $order->restaurant_id);
        }

        // Освобождаем связанные столы
        if (!empty($linkedTableIds)) {
            foreach ($linkedTableIds as $linkedTableId) {
                if ($linkedTableId != $tableId) {
                    Table::where('id', $linkedTableId)
                        ->where('restaurant_id', $order->restaurant_id)
                        ->update(['status' => 'free']);
                    $this->broadcastTableStatusChanged($linkedTableId, 'free', $order->restaurant_id);
                }
            }
        }

        $freshOrder = $order->fresh();
        $freshOrder->load('table');
        $this->broadcastOrderStatusChanged($freshOrder, $oldStatus, 'cancelled');

        return response()->json(['success' => true, 'message' => 'Отмена подтверждена']);
    }

    /**
     * Отклонить заявку на отмену
     */
    public function rejectCancellation(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        if (!$order->pending_cancellation) {
            return response()->json(['success' => false, 'message' => 'Заказ не ожидает отмены'], 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order->update([
            'pending_cancellation' => false,
            'cancel_request_reason' => null,
            'cancel_requested_by' => null,
            'cancel_requested_at' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Заявка отклонена']);
    }

    /**
     * Получить заявки на отмену (pending)
     */
    public function pendingCancellations(Request $request): JsonResponse
    {
        // Лимит для предотвращения перегрузки (обычно pending заявок немного)
        $limit = min($request->input('limit', 100), 200);

        // 1. Заказы с заявкой на полную отмену
        $orders = Order::where('pending_cancellation', true)
            ->with(['items.dish', 'customer', 'cancelRequestedBy'])
            ->orderBy('cancel_requested_at', 'desc')
            ->limit($limit)
            ->get();

        $formatted = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'type' => 'order',
                'order' => $order,
                'reason' => $order->cancel_request_reason,
                'requested_by' => $order->cancelRequestedBy?->name ?? 'Неизвестно',
                'created_at' => $order->cancel_requested_at,
            ];
        });

        // 2. Позиции с заявкой на отмену (pending_cancel)
        $pendingItems = OrderItem::where('status', 'pending_cancel')
            ->with(['order.table', 'order.customer', 'dish', 'cancelledByUser'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        $itemsFormatted = $pendingItems->map(function ($item) {
            return [
                'id' => 'item_' . $item->id,
                'type' => 'item',
                'item' => $item,
                'order' => $item->order,
                'reason' => $item->cancellation_reason,
                'requested_by' => $item->cancelledByUser?->name ?? 'Неизвестно',
                'created_at' => $item->updated_at,
            ];
        });

        // Объединяем и сортируем по дате
        $all = $formatted->concat($itemsFormatted)->sortByDesc('created_at')->values();

        return response()->json([
            'success' => true,
            'data' => $all,
            'meta' => [
                'orders_count' => $orders->count(),
                'items_count' => $pendingItems->count(),
                'total' => $all->count(),
            ],
        ]);
    }

    /**
     * Получить причины отмены
     */
    public function getCancellationReasons(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'guest_refused' => 'Гость отказался',
                'guest_changed_mind' => 'Гость передумал',
                'wrong_order' => 'Ошибка заказа',
                'out_of_stock' => 'Нет в наличии',
                'quality_issue' => 'Проблема с качеством',
                'long_wait' => 'Долгое ожидание',
                'duplicate' => 'Дубликат',
                'other' => 'Другое',
            ]
        ]);
    }

    /**
     * История списаний (отменённые заказы и позиции)
     */
    public function writeOffs(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId);
        $dateFrom = $request->input('date_from', $today->copy()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', $today->toDateString());

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);
        $page = max($request->input('page', 1), 1);

        // 1. Отменённые заказы со списанием
        $cancelledOrders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->where('is_write_off', true)
            ->whereDate('cancelled_at', '>=', $dateFrom)
            ->whereDate('cancelled_at', '<=', $dateTo)
            ->with(['items.dish', 'table', 'customer', 'cancelledByUser'])
            ->orderBy('cancelled_at', 'desc')
            ->limit($perPage)
            ->get();

        $ordersFormatted = $cancelledOrders->map(function ($order) {
            // Форматируем items для отображения
            $formattedItems = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name ?? $item->dish?->name ?? 'Неизвестно',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total ?? ($item->price * $item->quantity),
                    'status' => $item->status,
                ];
            });

            return [
                'id' => $order->id,
                'type' => 'cancellation',
                'order_number' => $order->order_number,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'table' => $order->table,
                    'items' => $formattedItems,
                ],
                'total' => $order->total,
                'amount' => $order->total, // Для совместимости с фронтендом
                'reason' => $order->cancel_reason,
                'description' => $order->cancel_reason, // Для совместимости с фронтендом
                'user' => [
                    'name' => $order->cancelledByUser?->name ?? 'Система',
                ],
                'cancelled_by' => $order->cancelledByUser?->name ?? 'Система',
                'cancelled_at' => $order->cancelled_at,
                'created_at' => $order->cancelled_at,
            ];
        });

        // 2. Отменённые позиции со списанием
        $cancelledItems = OrderItem::where('status', 'cancelled')
            ->where('is_write_off', true)
            ->whereDate('cancelled_at', '>=', $dateFrom)
            ->whereDate('cancelled_at', '<=', $dateTo)
            ->whereHas('order', fn($q) => $q->where('restaurant_id', $restaurantId))
            ->with(['order.table', 'order.customer', 'dish'])
            ->orderBy('cancelled_at', 'desc')
            ->limit($perPage)
            ->get();

        $itemsFormatted = $cancelledItems->map(function ($item) {
            $itemTotal = $item->total ?? ($item->price * $item->quantity);
            return [
                'id' => 'item_' . $item->id,
                'type' => 'item_cancellation',
                'order_number' => $item->order->order_number ?? '',
                'order' => [
                    'id' => $item->order->id ?? null,
                    'order_number' => $item->order->order_number ?? '',
                    'table' => $item->order->table ?? null,
                ],
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name ?? $item->dish?->name ?? 'Неизвестно',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $itemTotal,
                ],
                'item_name' => $item->name ?? $item->dish?->name ?? 'Неизвестно',
                'quantity' => $item->quantity,
                'total' => $itemTotal,
                'amount' => $itemTotal, // Для совместимости с фронтендом
                'reason' => $item->cancellation_reason,
                'description' => $item->cancellation_reason, // Для совместимости с фронтендом
                'user' => [
                    'name' => 'Система',
                ],
                'cancelled_by' => 'Система',
                'cancelled_at' => $item->cancelled_at,
                'created_at' => $item->cancelled_at,
            ];
        });

        // Объединяем и сортируем по дате
        $all = $ordersFormatted->concat($itemsFormatted)
            ->sortByDesc('cancelled_at')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $all,
            'meta' => [
                'orders_count' => $cancelledOrders->count(),
                'items_count' => $cancelledItems->count(),
                'total' => $all->count(),
                'per_page' => $perPage,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
