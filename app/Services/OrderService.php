<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Table;
use App\Models\CashShift;
use App\Models\Reservation;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\RealtimeEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Services\PriceListService;

class OrderService
{
    /**
     * Генерация номера заказа
     */
    public function generateOrderNumber(): array
    {
        $today = Carbon::today();
        $orderCount = Order::whereDate('created_at', $today)->count() + 1;
        $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);
        $dailyNumber = '#' . $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

        return [
            'order_number' => $orderNumber,
            'daily_number' => $dailyNumber,
        ];
    }

    /**
     * Создание заказа
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $numbers = $this->generateOrderNumber();

            $order = Order::create([
                'restaurant_id' => $data['restaurant_id'] ?? 1,
                'price_list_id' => $data['price_list_id'] ?? null,
                'order_number' => $numbers['order_number'],
                'daily_number' => $numbers['daily_number'],
                'type' => $data['type'],
                'table_id' => $data['table_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['waiter_id'] ?? null,
                'status' => 'cooking',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'comment' => $data['notes'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'delivery_status' => in_array($data['type'], ['delivery', 'pickup']) ? 'pending' : null,
            ]);

            // Добавляем позиции
            $subtotal = $this->addItemsToOrder($order, $data['items']);

            // Обновляем сумму заказа
            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            // Занимаем стол если это зал
            if ($data['type'] === 'dine_in' && !empty($data['table_id'])) {
                $this->occupyTable($data['table_id']);
            }

            // Broadcast события
            $order->load(['items.dish', 'table']);
            RealtimeEvent::orderCreated($order->toArray());

            if ($data['type'] === 'delivery') {
                RealtimeEvent::deliveryNew($order->toArray());
            }

            // Автоматическая печать на кухню
            if (!empty($data['auto_print']) || !isset($data['auto_print'])) {
                $this->autoPrintToKitchen($order);
            }

            return $order;
        });
    }

    /**
     * Добавление позиций к заказу
     */
    public function addItemsToOrder(Order $order, array $items): float
    {
        $subtotal = 0;
        $priceListId = $order->price_list_id;
        $priceListService = $priceListId ? new PriceListService() : null;

        foreach ($items as $item) {
            $dish = Dish::find($item['dish_id']);
            if (!$dish) continue;

            $basePrice = (float) $dish->price;
            $price = $priceListService
                ? $priceListService->resolvePrice($dish, $priceListId)
                : $basePrice;

            $itemTotal = $price * $item['quantity'];
            $subtotal += $itemTotal;

            OrderItem::create([
                'order_id' => $order->id,
                'price_list_id' => $priceListId,
                'dish_id' => $dish->id,
                'name' => $dish->name,
                'price' => $price,
                'base_price' => $priceListId ? $basePrice : null,
                'quantity' => $item['quantity'],
                'total' => $itemTotal,
                'modifiers' => $item['modifiers'] ?? null,
                'notes' => $item['notes'] ?? null,
                'status' => 'cooking',
            ]);
        }

        return $subtotal;
    }

    /**
     * Добавление одной позиции к заказу
     */
    public function addSingleItem(Order $order, array $itemData): OrderItem
    {
        $dish = Dish::findOrFail($itemData['dish_id']);
        $quantity = $itemData['quantity'] ?? 1;
        $priceListId = $order->price_list_id;

        $basePrice = (float) $dish->price;
        $price = $priceListId
            ? (new PriceListService())->resolvePrice($dish, $priceListId)
            : $basePrice;

        $itemTotal = $price * $quantity;

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'price_list_id' => $priceListId,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'price' => $price,
            'base_price' => $priceListId ? $basePrice : null,
            'quantity' => $quantity,
            'total' => $itemTotal,
            'modifiers' => $itemData['modifiers'] ?? null,
            'notes' => $itemData['notes'] ?? null,
            'status' => 'cooking',
        ]);

        // Пересчитываем сумму заказа
        $this->recalculateOrderTotal($order);

        // Broadcast
        RealtimeEvent::orderItemAdded($order->fresh(['items.dish'])->toArray(), $orderItem->toArray());

        // Автопечать новой позиции на кухню
        $this->autoPrintToKitchen($order, [$orderItem->id]);

        return $orderItem;
    }

    /**
     * Пересчёт суммы заказа
     */
    public function recalculateOrderTotal(Order $order): void
    {
        $subtotal = $order->items()->sum('total');
        $discountAmount = $order->discount_amount ?? 0;
        $deliveryFee = $order->delivery_fee ?? 0;

        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $discountAmount + $deliveryFee,
        ]);
    }

    /**
     * Обновление статуса заказа
     */
    public function updateStatus(Order $order, string $newStatus): Order
    {
        $oldStatus = $order->status;
        $updateData = ['status' => $newStatus];

        // Временные метки для кухни
        if ($newStatus === 'cooking' && !$order->cooking_started_at) {
            $updateData['cooking_started_at'] = now();
        }

        // При статусе 'cooking' - повар берёт позиции в работу
        if ($newStatus === 'cooking') {
            $order->items()
                ->where('status', 'cooking')
                ->whereNull('cooking_started_at')
                ->update(['cooking_started_at' => now()]);
        }

        if ($newStatus === 'ready') {
            $order->items()
                ->where('status', 'cooking')
                ->whereNotNull('cooking_started_at')
                ->update([
                    'status' => 'ready',
                    'cooking_finished_at' => now(),
                ]);

            $hasCookingItems = $order->items()->where('status', 'cooking')->exists();

            if ($hasCookingItems) {
                $updateData['status'] = 'cooking';
            } else {
                $updateData['cooking_finished_at'] = now();
                $updateData['ready_at'] = now();
            }
        }

        $order->update($updateData);

        // Освобождаем стол при завершении/отмене
        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            $this->releaseTableIfNoActiveOrders($order->table_id);
        }

        // Обновляем delivery_status
        if (in_array($order->type, ['delivery', 'pickup'])) {
            $this->syncDeliveryStatus($order, $newStatus);
        }

        // Broadcast
        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, $newStatus);

        return $order->fresh();
    }

    /**
     * Обработка оплаты
     */
    public function processPayment(Order $order, array $paymentData): array
    {
        if ($order->payment_status === 'paid') {
            return ['success' => false, 'message' => 'Заказ уже оплачен'];
        }

        $restaurantId = $order->restaurant_id;
        $shift = CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return ['success' => false, 'message' => 'Откройте кассовую смену перед оплатой'];
        }

        // Проверяем, что смена открыта сегодня
        $shiftDate = $shift->opened_at->toDateString();
        $today = now()->toDateString();

        if ($shiftDate !== $today) {
            $shiftDateFormatted = $shift->opened_at->format('d.m.Y');
            return [
                'success' => false,
                'message' => "Смена от {$shiftDateFormatted}. Закройте её и откройте новую смену для сегодняшних операций.",
                'error_code' => 'SHIFT_OUTDATED'
            ];
        }

        // Проверяем депозит брони
        $depositAmount = 0;
        $reservation = null;

        if ($order->reservation_id) {
            $reservation = Reservation::find($order->reservation_id);
            if ($reservation && $reservation->deposit > 0 && !$reservation->deposit_paid) {
                $depositAmount = min($reservation->deposit, $order->total);
            }
        }

        $order->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => $paymentData['method'],
            'paid_at' => now(),
            'completed_at' => now(),
        ]);

        // Записываем операцию в кассу (с учётом депозита)
        $paymentAmount = $depositAmount > 0 ? ($order->total - $depositAmount) : null;
        \App\Models\CashOperation::recordOrderPayment(
            $order,
            $paymentData['method'],
            null, // staffId
            null, // fiscalReceipt
            $paymentAmount
        );

        // Отмечаем депозит как использованный
        if ($reservation && $depositAmount > 0) {
            $reservation->update(['deposit_paid' => true]);
        }

        // Освобождаем стол
        if ($order->table_id) {
            $this->releaseTableIfNoActiveOrders($order->table_id);
        }

        // Завершаем бронь
        if ($order->reservation_id) {
            Reservation::where('id', $order->reservation_id)->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        // Broadcast
        RealtimeEvent::orderPaid($order->fresh()->toArray());

        return [
            'success' => true,
            'order' => $order->fresh(['items.dish', 'table', 'waiter', 'customer']),
            'deposit_used' => $depositAmount,
        ];
    }

    /**
     * Отмена заказа
     */
    public function cancelOrder(Order $order, string $reason, int $managerId, bool $isWriteOff = false): Order
    {
        return DB::transaction(function () use ($order, $reason, $managerId, $isWriteOff) {
            $oldStatus = $order->status;

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $managerId,
            ]);

            // Освобождаем стол
            if ($order->table_id) {
                $this->releaseTableIfNoActiveOrders($order->table_id);
            }

            // Broadcast
            RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, 'cancelled');

            return $order->fresh();
        });
    }

    /**
     * Занять стол
     */
    public function occupyTable(int $tableId): void
    {
        Table::where('id', $tableId)->update(['status' => 'occupied']);
        RealtimeEvent::tableStatusChanged($tableId, 'occupied');
    }

    /**
     * Освободить стол если нет активных заказов
     */
    public function releaseTableIfNoActiveOrders(int $tableId): void
    {
        $activeOrders = Order::where('table_id', $tableId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        if ($activeOrders === 0) {
            Table::where('id', $tableId)->update(['status' => 'free']);
            RealtimeEvent::tableStatusChanged($tableId, 'free');
        }
    }

    /**
     * Синхронизация статуса доставки
     */
    private function syncDeliveryStatus(Order $order, string $orderStatus): void
    {
        $deliveryStatusMap = [
            'new' => 'pending',
            'cooking' => 'preparing',
            'ready' => 'ready',
            'delivering' => 'in_transit',
            'completed' => 'delivered',
        ];

        if (isset($deliveryStatusMap[$orderStatus])) {
            $order->update(['delivery_status' => $deliveryStatusMap[$orderStatus]]);
        }
    }

    /**
     * Получить активные заказы для стола
     */
    public function getActiveOrdersForTable(int $tableId): \Illuminate\Database\Eloquent\Collection
    {
        return Order::where('table_id', $tableId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['items.dish', 'waiter'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Получить заказы для кухни
     */
    public function getKitchenOrders(int $restaurantId): \Illuminate\Database\Eloquent\Collection
    {
        return Order::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['new', 'cooking', 'ready'])
            ->with(['items.dish', 'table', 'waiter'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Автоматическая печать на кухню
     * Печатает заказ на все активные кухонные принтеры с фильтрацией по цехам
     */
    public function autoPrintToKitchen(Order $order, array $itemIds = null): array
    {
        $restaurantId = $order->restaurant_id;

        // Проверяем включена ли автопечать
        $restaurant = \App\Models\Restaurant::find($restaurantId);
        $printSettings = $restaurant?->getSetting('print', []) ?? [];
        $autoPrintEnabled = $printSettings['auto_print_kitchen'] ?? true;
        if (!$autoPrintEnabled) {
            return ['success' => true, 'message' => 'Автопечать отключена', 'skipped' => true];
        }

        // Если передан itemIds — проверяем настройку печати новых позиций
        if ($itemIds !== null) {
            $autoPrintNewItems = $printSettings['auto_print_new_items'] ?? true;
            if (!$autoPrintNewItems) {
                return ['success' => true, 'message' => 'Печать новых позиций отключена', 'skipped' => true];
            }
        }

        // Получаем кухонные и барные принтеры
        $printers = Printer::with('kitchenStation')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('type', ['kitchen', 'bar'])
            ->where('is_active', true)
            ->get();

        if ($printers->isEmpty()) {
            Log::info("AutoPrint: No kitchen printers configured for restaurant {$restaurantId}");
            return ['success' => true, 'message' => 'Нет настроенных принтеров', 'skipped' => true];
        }

        // Загружаем позиции с категориями
        $order->load(['items.dish.category', 'table', 'waiter']);

        // Если указаны конкретные позиции - печатаем только их
        $allItems = $order->items;
        if ($itemIds) {
            $allItems = $allItems->filter(fn($item) => in_array($item->id, $itemIds));
        }

        if ($allItems->isEmpty()) {
            return ['success' => true, 'message' => 'Нет позиций для печати', 'skipped' => true];
        }

        $results = [];
        $printedStations = [];

        foreach ($printers as $printer) {
            // Фильтруем позиции по цеху принтера
            $items = $this->filterItemsForPrinter($allItems, $printer);

            if ($items->isEmpty()) {
                continue;
            }

            try {
                $service = new ReceiptService($printer);
                $content = $service->generateKitchenOrder($order, $items->toArray());

                $job = PrintJob::create([
                    'restaurant_id' => $restaurantId,
                    'printer_id' => $printer->id,
                    'order_id' => $order->id,
                    'type' => 'kitchen',
                    'status' => 'pending',
                    'content' => $content,
                ]);

                $result = $job->process();
                $results[] = [
                    'printer' => $printer->name,
                    'station' => $printer->kitchenStation?->name,
                    'items_count' => $items->count(),
                    'success' => $result['success'],
                    'message' => $result['message'],
                ];

                if ($printer->kitchen_station_id) {
                    $printedStations[] = $printer->kitchen_station_id;
                }

                Log::info("AutoPrint: Sent to {$printer->name}", [
                    'order_id' => $order->id,
                    'items' => $items->count(),
                    'success' => $result['success'],
                ]);

            } catch (\Exception $e) {
                Log::error("AutoPrint error for printer {$printer->name}: " . $e->getMessage());
                $results[] = [
                    'printer' => $printer->name,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Печатаем позиции без цеха на общий принтер (без привязки к цеху)
        $unassignedItems = $allItems->filter(function ($item) use ($printedStations) {
            $stationId = $item->dish?->category?->kitchen_station_id;
            return !$stationId || !in_array($stationId, $printedStations);
        });

        if ($unassignedItems->isNotEmpty()) {
            $defaultPrinter = $printers->first(fn($p) => !$p->kitchen_station_id && $p->type === 'kitchen');

            if ($defaultPrinter && !collect($results)->contains('printer', $defaultPrinter->name)) {
                try {
                    $service = new ReceiptService($defaultPrinter);
                    $content = $service->generateKitchenOrder($order, $unassignedItems->toArray());

                    $job = PrintJob::create([
                        'restaurant_id' => $restaurantId,
                        'printer_id' => $defaultPrinter->id,
                        'order_id' => $order->id,
                        'type' => 'kitchen',
                        'status' => 'pending',
                        'content' => $content,
                    ]);

                    $result = $job->process();
                    $results[] = [
                        'printer' => $defaultPrinter->name,
                        'station' => null,
                        'items_count' => $unassignedItems->count(),
                        'success' => $result['success'],
                        'message' => $result['message'],
                    ];
                } catch (\Exception $e) {
                    Log::error("AutoPrint error for default printer: " . $e->getMessage());
                }
            }
        }

        $allSuccess = empty($results) || collect($results)->every('success');

        return [
            'success' => $allSuccess,
            'message' => empty($results) ? 'Нет принтеров для печати' : ($allSuccess ? 'Напечатано' : 'Есть ошибки'),
            'results' => $results,
        ];
    }

    /**
     * Фильтрация позиций для конкретного принтера
     */
    private function filterItemsForPrinter($items, Printer $printer)
    {
        // Если у принтера не указан цех — он печатает все позиции своего типа
        if (!$printer->kitchen_station_id) {
            // Для барного принтера — только барные позиции
            if ($printer->type === 'bar') {
                return $items->filter(function ($item) {
                    return $item->dish?->category?->is_bar ?? false;
                });
            }
            // Для кухонного принтера без цеха — все не-барные позиции
            return $items->filter(function ($item) {
                return !($item->dish?->category?->is_bar ?? false);
            });
        }

        // Если указан цех — фильтруем по категориям этого цеха
        return $items->filter(function ($item) use ($printer) {
            $categoryStationId = $item->dish?->category?->kitchen_station_id;
            return $categoryStationId === $printer->kitchen_station_id;
        });
    }
}
