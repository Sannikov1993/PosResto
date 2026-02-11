<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Table;
use App\Models\Restaurant;
use App\Models\Customer;
use App\Models\RealtimeEvent;
use App\Models\KitchenStation;
use App\Helpers\TimeHelper;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Requests\Order\TransferOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Enums\PaymentStatus;

class OrderController extends Controller
{
    use Traits\ResolvesRestaurantId;
    use BroadcastsEvents;
    /**
     * Список заказов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Валидация входных параметров
        $filters = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'status' => ['nullable', 'string', 'regex:/^(new|confirmed|cooking|ready|served|completed|cancelled)(,(new|confirmed|cooking|ready|served|completed|cancelled))*$/'],
            'type' => 'nullable|string|in:dine_in,delivery,pickup,preorder',
            'station' => 'nullable|string|max:50',
            'table_id' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'today' => 'nullable|boolean',
            'kitchen' => 'nullable|boolean',
            'delivery' => 'nullable|boolean',
        ]);

        $query = Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $restaurantId);

        if (!empty($filters['date'])) {
            $query->forDate($filters['date'], $restaurantId, true);
        } elseif ($request->boolean('today')) {
            $query->today($restaurantId);
        }

        if (!empty($filters['status'])) {
            $statuses = explode(',', $filters['status']);
            $query->whereIn('status', $statuses);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if ($request->boolean('kitchen')) {
            $query->whereIn('status', [OrderStatus::NEW->value, OrderStatus::COOKING->value]);
        }

        // Фильтрация по цеху кухни (station)
        $station = null;
        if ($stationSlug = $filters['station'] ?? null) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $restaurantId)
                ->first();

            if ($station) {
                // Фильтруем заказы, у которых есть позиции для этого цеха
                // или позиции без цеха (показываются везде)
                $query->whereHas('items', function ($q) use ($station) {
                    $q->whereHas('dish', function ($dq) use ($station) {
                        $dq->where('kitchen_station_id', $station->id)
                            ->orWhereNull('kitchen_station_id');
                    });
                });
            }
        }

        if ($request->boolean('delivery')) {
            $query->where('type', OrderType::DELIVERY->value);
        }

        if (!empty($filters['table_id'])) {
            $query->where('table_id', (int) $filters['table_id']);
        }

        // Пагинация
        $perPage = (int) ($filters['per_page'] ?? 50);
        $usePagination = !$station; // Для station используем коллекцию с limit

        if ($usePagination) {
            // Стандартная пагинация для обычных запросов
            $paginated = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // Для запросов с station - пост-обработка требует коллекцию
        // Ограничиваем максимум 500 записей для производительности
        $orders = $query->orderByDesc('created_at')->limit(500)->get();

        // Пост-обработка: если указан station, фильтруем items внутри каждого заказа
        if ($station) {
            $orders = $orders->map(function ($order) use ($station) {
                // Фильтруем items: только те, что принадлежат этому цеху или без цеха
                $filteredItems = $order->items->filter(function ($item) use ($station) {
                    $dish = $item->dish;
                    if (!$dish) return true; // Если блюдо удалено, показываем
                    return $dish->kitchen_station_id === $station->id
                        || $dish->kitchen_station_id === null;
                })->values();

                $order->setRelation('items', $filteredItems);
                return $order;
            })->filter(function ($order) {
                // Убираем заказы без позиций после фильтрации
                return $order->items->count() > 0;
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Получить все активные заказы на столе
     */
    public function tableOrders(Request $request, int $tableId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $orders = Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $restaurantId)
            ->where('table_id', $tableId)
            ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Получить количество заказов в колонке "Новые" по датам (для календаря кухни)
     * Считает заказы у которых есть позиции не взятые в работу
     */
    public function countByDates(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $tz = TimeHelper::getTimezone($restaurantId);
        $today = TimeHelper::today($restaurantId);

        // Parse dates in restaurant's timezone
        $startDate = Carbon::parse($request->input('start_date', $today->copy()->subDays(7)), $tz);
        $endDate = Carbon::parse($request->input('end_date', $today->copy()->addDays(30)), $tz);

        // Convert to UTC range for database query
        $startDateUtc = $startDate->copy()->startOfDay()->utc();
        $endDateUtc = $endDate->copy()->endOfDay()->utc();

        // Получаем станцию если указана
        $stationId = null;
        if ($stationSlug = $request->input('station')) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $restaurantId)
                ->first();
            $stationId = $station?->id;
        }

        // Базовый запрос - заказы не завершённые/отменённые
        $query = Order::where('restaurant_id', $restaurantId)
            ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
            ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::COOKING->value, OrderStatus::READY->value]);

        // Фильтрация по станции
        if ($stationId) {
            $query->whereHas('items', function ($q) use ($stationId) {
                $q->whereHas('dish', function ($dq) use ($stationId) {
                    $dq->where('kitchen_station_id', $stationId)
                       ->orWhereNull('kitchen_station_id');
                });
            });
        }

        // Заказы у которых есть позиции не взятые в работу (status=cooking, cooking_started_at=null)
        // ИЛИ предзаказы которые ещё не начали готовить
        $query->where(function ($q) {
            // Заказы с позициями не взятыми в работу
            $q->whereHas('items', function ($iq) {
                $iq->where('status', 'cooking')
                   ->whereNull('cooking_started_at');
            });
            // ИЛИ предзаказы у которых все позиции ещё не начаты
            $q->orWhere(function ($pq) {
                $pq->whereNotNull('scheduled_at')
                   ->where('is_asap', false)
                   ->whereDoesntHave('items', function ($iq) {
                       $iq->whereNotNull('cooking_started_at');
                   });
            });
        });

        // Получаем заказы в диапазоне дат (using UTC)
        $orders = $query->where(function ($q) use ($startDateUtc, $endDateUtc) {
                // Предзаказы по scheduled_at
                $q->where(function ($sq) use ($startDateUtc, $endDateUtc) {
                    $sq->whereNotNull('scheduled_at')
                       ->where('is_asap', false)
                       ->whereBetween('scheduled_at', [$startDateUtc, $endDateUtc]);
                });
                // Обычные заказы по created_at
                $q->orWhere(function ($sq) use ($startDateUtc, $endDateUtc) {
                    $sq->where(function ($asap) {
                           $asap->whereNull('scheduled_at')
                                ->orWhere('is_asap', true);
                       })
                       ->whereBetween('created_at', [$startDateUtc, $endDateUtc]);
                });
            })
            ->get(['id', 'scheduled_at', 'created_at', 'is_asap']);

        // Группируем по датам (converting UTC to restaurant timezone for grouping)
        $counts = [];
        foreach ($orders as $order) {
            // Определяем дату: для предзаказов - scheduled_at, для обычных - created_at
            // Convert from UTC to restaurant timezone for correct date grouping
            if ($order->scheduled_at && !$order->is_asap) {
                $date = Carbon::parse($order->scheduled_at)->setTimezone($tz)->format('Y-m-d');
            } else {
                $date = Carbon::parse($order->created_at)->setTimezone($tz)->format('Y-m-d');
            }

            if (!isset($counts[$date])) {
                $counts[$date] = 0;
            }
            $counts[$date]++;
        }

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Создание заказа
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Проверка лимита ручной скидки
        if (!empty($validated['manual_discount_percent']) && $validated['manual_discount_percent'] > 0) {
            $user = $request->user();
            if ($user && !$user->canApplyDiscount((int) $validated['manual_discount_percent'])) {
                $role = $user->getEffectiveRole();
                $maxDiscount = $role ? $role->max_discount_percent : 0;
                return response()->json([
                    'success' => false,
                    'message' => "Вы не можете применить скидку {$validated['manual_discount_percent']}%. Максимум для вашей роли: {$maxDiscount}%",
                ], 403);
            }
        }

        $restaurantId = $validated['restaurant_id'] ?? $this->getRestaurantId($request);
        $orderService = new \App\Services\OrderService();

        try {
            $result = $orderService->createFromRequest($validated, $restaurantId, $request->user());
        } catch (\App\Exceptions\PhoneIncompleteException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\App\Exceptions\DishesUnavailableException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'stopped_dishes' => $e->getDishes(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Order creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Ошибка создания заказа',
            ], 422);
        }

        $order = $result['order'];

        if ($validated['type'] === OrderType::DINE_IN->value && !empty($validated['table_id'])) {
            $this->broadcastTableStatusChanged($validated['table_id'], 'occupied', $order->restaurant_id);
        }

        $this->broadcastOrderCreated($order);

        return response()->json([
            'success' => true,
            'message' => 'Заказ создан',
            'data' => $order,
            'print_result' => $result['print_result'],
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'success' => true,
            'data' => $order->load(['items.dish', 'table', 'waiter', 'customer']),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);

        $validated = $request->validated();

        $oldStatus = $order->status;
        $newStatus = $validated['status'];

        // Получаем ID станции если передан slug
        $stationId = null;
        if (!empty($validated['station'])) {
            $station = \App\Models\KitchenStation::where('slug', $validated['station'])->first();
            $stationId = $station?->id;
        }

        // Обновляем статусы позиций для корректного отображения на кухне
        switch ($newStatus) {
            case 'cooking':
                // Повар взял заказ в работу
                // Сначала переводим pending позиции в cooking (для предзаказов)
                $pendingItemsQuery = $order->items()->where('status', 'pending');
                if ($stationId) {
                    // Обновляем позиции своего цеха ИЛИ без цеха (они показываются везде)
                    $pendingItemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $pendingItemsQuery->update(['status' => 'cooking']);

                // Теперь устанавливаем cooking_started_at для всех cooking позиций
                $itemsQuery = $order->items()
                    ->where('status', 'cooking')
                    ->whereNull('cooking_started_at');

                if ($stationId) {
                    // Обновляем позиции своего цеха ИЛИ без цеха
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }

                $itemsQuery->update(['cooking_started_at' => now()]);

                // Статус заказа меняем на cooking только если он ещё не cooking
                if ($order->status !== OrderStatus::COOKING->value) {
                    $order->update(['status' => OrderStatus::COOKING->value]);
                }
                break;

            case 'ready':
                // Заказ готов - обновляем статус позиций
                // Только для позиций своего цеха ИЛИ без цеха (или всех если цех не указан)
                $itemsQuery = $order->items()->where('status', 'cooking');

                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }

                $itemsQuery->update([
                    'status' => 'ready',
                    'cooking_finished_at' => now(),
                ]);

                // Статус заказа меняем на ready только если ВСЕ позиции готовы
                $hasCookingItems = $order->items()->where('status', 'cooking')->exists();
                if (!$hasCookingItems) {
                    $order->update(['status' => OrderStatus::READY->value]);
                }
                break;

            case 'return_to_new':
                // Вернуть заказ из "Готовится" обратно в "Новые"
                // Убираем cooking_started_at у позиций своего цеха
                $itemsQuery = $order->items()
                    ->where('status', 'cooking')
                    ->whereNotNull('cooking_started_at');

                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }

                $itemsQuery->update(['cooking_started_at' => null]);

                // Если все позиции вернулись в "новые", меняем статус заказа на confirmed
                $hasStartedItems = $order->items()
                    ->where('status', 'cooking')
                    ->whereNotNull('cooking_started_at')
                    ->exists();

                if (!$hasStartedItems && $order->status === OrderStatus::COOKING->value) {
                    $order->update(['status' => OrderStatus::CONFIRMED->value]);
                }

                $newStatus = OrderStatus::CONFIRMED->value; // Для события
                break;

            case 'return_to_cooking':
                // Вернуть заказ из "Готово" обратно в "Готовится"
                // Возвращаем статус позициям и убираем cooking_finished_at
                $itemsQuery = $order->items()->where('status', 'ready');

                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }

                $itemsQuery->update([
                    'status' => 'cooking',
                    'cooking_finished_at' => null,
                ]);

                // Обновляем статус заказа на cooking
                $order->update(['status' => OrderStatus::COOKING->value]);
                $newStatus = OrderStatus::COOKING->value; // Для события
                break;

            case 'completed':
                // Заказ завершён
                $order->items()->update(['status' => 'served']);
                break;
            case 'cancelled':
                // Заказ отменён
                $order->items()->update(['status' => 'cancelled']);
                break;
        }

        if (in_array($newStatus, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value]) && $order->table_id) {
            Table::where('id', $order->table_id)
                ->where('restaurant_id', $order->restaurant_id)
                ->update(['status' => 'free']);
            $this->broadcastTableStatusChanged($order->table_id, 'free', $order->restaurant_id);
        }

        // Обновляем delivery_status для delivery, pickup и preorder заказов
        if (in_array($order->type, [OrderType::DELIVERY->value, OrderType::PICKUP->value, OrderType::PREORDER->value])) {
            $map = ['cooking' => 'preparing', 'ready' => 'ready', 'completed' => 'delivered'];
            if (isset($map[$newStatus])) $order->update(['delivery_status' => $map[$newStatus]]);
        }

        $freshOrder = $order->fresh();
        $freshOrder->load('table');
        $this->broadcastOrderStatusChanged($freshOrder, $oldStatus, $newStatus);
        return response()->json(['success' => true, 'message' => 'Статус обновлён', 'data' => $freshOrder->load(['items.dish', 'table'])]);
    }

    public function updateDeliveryStatus(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'delivery_status' => 'required|in:pending,preparing,ready,picked_up,in_transit,delivered,cancelled',
        ]);

        $order->update([
            'delivery_status' => $validated['delivery_status'],
            'picked_up_at' => $validated['delivery_status'] === 'picked_up' ? now() : $order->picked_up_at,
            'delivered_at' => $validated['delivery_status'] === 'delivered' ? now() : $order->delivered_at,
        ]);

        if ($validated['delivery_status'] === 'delivered') {
            $order->update(['status' => OrderStatus::COMPLETED->value]);
        }

        $this->broadcastDeliveryStatusChanged($order->fresh(), $validated['delivery_status']);
        return response()->json(['success' => true, 'message' => 'Статус доставки обновлён', 'data' => $order->fresh()]);
    }

    public function assignCourier(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate(['courier_id' => 'required|integer']);
        $order->update(['courier_id' => $validated['courier_id'], 'delivery_status' => 'picked_up', 'picked_up_at' => now()]);

        // Get courier info
        $courier = \App\Models\User::find($validated['courier_id']);
        if ($courier) {
            $this->broadcastCourierAssigned($order->fresh(), $courier);
        }

        return response()->json(['success' => true, 'message' => 'Курьер назначен', 'data' => $order->fresh()]);
    }

    /**
     * Перенести заказ на другой стол
     */
    public function transfer(TransferOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);

        $targetTableId = $validated['target_table_id'];

        // Проверяем, что заказ активен
        if (in_array($order->status, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя перенести завершённый или отменённый заказ'
            ], 400);
        }

        // Проверяем, что целевой стол не тот же самый
        if ($order->table_id === $targetTableId) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ уже на этом столе'
            ], 400);
        }

        $targetTable = Table::forRestaurant($restaurantId)->find($targetTableId);
        if (!$targetTable) {
            return response()->json([
                'success' => false,
                'message' => 'Целевой стол не найден'
            ], 404);
        }

        // Проверяем, есть ли активные заказы на целевом столе
        $force = $validated['force'] ?? false;
        if (!$force) {
            $activeOrdersCount = Order::where('table_id', $targetTableId)
                ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
                ->count();

            if ($activeOrdersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'На столе ' . $targetTable->number . ' уже есть активный заказ',
                    'code' => 'TARGET_TABLE_OCCUPIED',
                    'data' => [
                        'active_orders_count' => $activeOrdersCount,
                        'target_table_number' => $targetTable->number,
                    ],
                ], 409);
            }
        }

        $sourceTable = $order->table;

        return DB::transaction(function () use ($order, $targetTable, $sourceTable, $targetTableId) {
            $oldTableId = $order->table_id;

            $order->update(['table_id' => $targetTableId]);

            // Обновляем статус исходного стола
            if ($sourceTable) {
                // Проверяем, есть ли ещё заказы на исходном столе
                $otherOrdersOnSource = Order::where('table_id', $oldTableId)
                    ->where('id', '!=', $order->id)
                    ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
                    ->exists();

                if (!$otherOrdersOnSource) {
                    $sourceTable->update(['status' => 'free']);
                    $this->broadcastTableStatusChanged($oldTableId, 'free', $sourceTable->restaurant_id);
                }
            }

            // Обновляем статус целевого стола
            $targetTable->update(['status' => 'occupied']);
            $this->broadcastTableStatusChanged($targetTableId, 'occupied', $targetTable->restaurant_id);

            // Отправляем событие о переносе
            $this->broadcast('orders', 'order_transferred', [
                'order_id' => $order->id,
                'from_table_id' => $oldTableId,
                'to_table_id' => $targetTableId,
                'restaurant_id' => $order->restaurant_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Заказ перенесён на стол ' . $targetTable->number,
                'data' => $order->fresh(['items.dish', 'table']),
            ]);
        });
    }

    /**
     * Вызвать официанта для готового заказа
     */
    public function callWaiter(Order $order): JsonResponse
    {
        // Проверяем что есть официант (user_id - кто создал заказ)
        if (!$order->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'У заказа нет назначенного официанта',
            ], 400);
        }

        // Загружаем связи если не загружены
        $order->loadMissing(['waiter', 'table']);

        // Создаём событие для realtime системы
        RealtimeEvent::create([
            'channel' => 'pos',
            'event' => 'waiter_call',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'waiter_id' => $order->user_id,
                'waiter_name' => $order->waiter?->name,
                'table_id' => $order->table_id,
                'table_name' => $order->table?->name ?? $order->table?->number,
                'message' => "Заказ #{$order->order_number} готов к выдаче!",
                'called_at' => now()->toISOString(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Официант вызван',
        ]);
    }
}
