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

class OrderController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Список заказов
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Filter by specific date or today
        if ($request->has('date')) {
            $filterDate = Carbon::parse($request->input('date'));
            // Сравниваем дату напрямую с сегодняшней датой ресторана (без учёта времени)
            $restaurantToday = TimeHelper::today($this->getRestaurantId($request));
            $isToday = $filterDate->format('Y-m-d') === $restaurantToday->format('Y-m-d');

            // Логика: показываем заказы которые "принадлежат" этой дате
            // 1. Если есть scheduled_at - смотрим на scheduled_at
            // 2. Если нет scheduled_at - смотрим на created_at
            $query->where(function ($q) use ($filterDate, $isToday) {
                // Заказы запланированные на эту дату
                $q->whereDate('scheduled_at', $filterDate);

                // Заказы без scheduled_at (обычные), созданные в эту дату
                $q->orWhere(function ($sq) use ($filterDate) {
                    $sq->whereNull('scheduled_at')
                       ->whereDate('created_at', $filterDate);
                });

                // Для сегодня также показываем активные заказы без scheduled_at
                // (чтобы не потерять заказы которые готовятся прямо сейчас)
                if ($isToday) {
                    $q->orWhere(function ($sq) {
                        $sq->whereNull('scheduled_at')
                           ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready']);
                    });
                }
            });
        } elseif ($request->boolean('today')) {
            // Include orders created today OR preorders scheduled for today
            // OR active orders (cooking/confirmed/ready) regardless of date - they should always appear on kitchen
            $today = TimeHelper::today($this->getRestaurantId($request));
            $query->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                  ->orWhereDate('scheduled_at', $today)
                  ->orWhereIn('status', ['new', 'confirmed', 'cooking', 'ready']);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('kitchen')) {
            $query->whereIn('status', ['new', 'cooking']);
        }

        // Фильтрация по цеху кухни (station)
        if ($stationSlug = $request->input('station')) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $this->getRestaurantId($request))
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
            $query->where('type', 'delivery');
        }

        if ($request->has('table_id')) {
            $query->where('table_id', $request->input('table_id'));
        }

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);
        $usePagination = !$request->has('station'); // Для station используем коллекцию с limit

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
        if ($stationSlug = $request->input('station')) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $this->getRestaurantId($request))
                ->first();

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
        }

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Получить все активные заказы на столе
     */
    public function tableOrders(int $tableId): JsonResponse
    {
        $orders = Order::with(['items.dish', 'table', 'waiter'])
            ->where('table_id', $tableId)
            ->whereNotIn('status', ['completed', 'cancelled'])
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
        $today = TimeHelper::today($restaurantId);
        $startDate = Carbon::parse($request->input('start_date', $today->copy()->subDays(7)));
        $endDate = Carbon::parse($request->input('end_date', $today->copy()->addDays(30)));

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
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereIn('status', ['confirmed', 'cooking', 'ready']);

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

        // Получаем заказы в диапазоне дат
        $orders = $query->where(function ($q) use ($startDate, $endDate) {
                // Предзаказы по scheduled_at
                $q->where(function ($sq) use ($startDate, $endDate) {
                    $sq->whereNotNull('scheduled_at')
                       ->where('is_asap', false)
                       ->whereBetween('scheduled_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                });
                // Обычные заказы по created_at
                $q->orWhere(function ($sq) use ($startDate, $endDate) {
                    $sq->where(function ($asap) {
                           $asap->whereNull('scheduled_at')
                                ->orWhere('is_asap', true);
                       })
                       ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                });
            })
            ->get(['id', 'scheduled_at', 'created_at', 'is_asap']);

        // Группируем по датам
        $counts = [];
        foreach ($orders as $order) {
            // Определяем дату: для предзаказов - scheduled_at, для обычных - created_at
            if ($order->scheduled_at && !$order->is_asap) {
                $date = Carbon::parse($order->scheduled_at)->format('Y-m-d');
            } else {
                $date = Carbon::parse($order->created_at)->format('Y-m-d');
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
     * Создание заказа (с исправлениями критических багов)
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

        // Проверка что телефон полный (для доставки и самовывоза обязательно)
        if (in_array($validated['type'], ['delivery', 'pickup'])) {
            if (empty($validated['phone']) || !Customer::isPhoneComplete($validated['phone'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Введите полный номер телефона (минимум 10 цифр)',
                ], 422);
            }
        }

        // Форматируем имя клиента
        if (!empty($validated['customer_name'])) {
            $validated['customer_name'] = Customer::formatName($validated['customer_name']);
        }

        // Валидация restaurant_id
        $restaurantId = $validated['restaurant_id'] ?? $this->getRestaurantId($request);
        if (!Restaurant::where('id', $restaurantId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 422);
        }

        // Проверка стоп-листа (из поля is_available и is_stopped в dishes)
        $dishIds = collect($validated['items'])->pluck('dish_id')->unique();
        $stoppedDishes = Dish::whereIn('id', $dishIds)
            ->where(function ($q) {
                $q->where('is_stopped', true)->orWhere('is_available', false);
            })
            ->pluck('name')
            ->toArray();

        // Также проверяем таблицу stop_list
        $stopListDishIds = \App\Models\StopList::where('restaurant_id', $restaurantId)
            ->whereIn('dish_id', $dishIds)
            ->active()
            ->pluck('dish_id')
            ->toArray();

        if (!empty($stopListDishIds)) {
            $stopListDishNames = Dish::whereIn('id', $stopListDishIds)
                ->pluck('name')
                ->toArray();
            $stoppedDishes = array_unique(array_merge($stoppedDishes, $stopListDishNames));
        }

        if (!empty($stoppedDishes)) {
            return response()->json([
                'success' => false,
                'message' => 'Блюда недоступны: ' . implode(', ', $stoppedDishes),
                'stopped_dishes' => $stoppedDishes,
            ], 422);
        }

        try {
            // Автоматическая привязка или создание клиента по телефону
            $customerId = $validated['customer_id'] ?? null;
            if (!$customerId && !empty($validated['phone'])) {
                // Нормализуем телефон - оставляем только цифры
                $normalizedPhone = preg_replace('/[^0-9]/', '', $validated['phone']);

                $customer = Customer::where('restaurant_id', $restaurantId)
                    ->byPhone($normalizedPhone)
                    ->first();

                if ($customer) {
                    $customerId = $customer->id;
                    // Обновляем имя если передано
                    if (!empty($validated['customer_name']) && $validated['customer_name'] !== 'Клиент') {
                        $customer->update(['name' => $validated['customer_name']]);
                    }
                } elseif ($validated['type'] === 'pickup') {
                    // For pickup orders, create customer if not found
                    $customer = Customer::create([
                        'restaurant_id' => $restaurantId,
                        'phone' => $normalizedPhone,
                        'name' => $validated['customer_name'] ?? 'Клиент',
                    ]);
                    $customerId = $customer->id;
                }
            }

            $order = DB::transaction(function () use ($validated, $restaurantId, $request, $customerId) {
                // Атомарная проверка стола
                if ($validated['type'] === 'dine_in' && !empty($validated['table_id'])) {
                    $table = Table::where('id', $validated['table_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$table) {
                        throw new \Exception('Стол не найден');
                    }
                    if ($table->status === 'occupied') {
                        throw new \Exception('Стол уже занят');
                    }
                    $table->update(['status' => 'occupied']);
                }

                // Генерация номера с retry
                $today = TimeHelper::today($restaurantId);
                $maxAttempts = 5;
                $order = null;

                for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                    $lastOrder = Order::whereDate('created_at', $today)
                        ->where('restaurant_id', $restaurantId)
                        ->lockForUpdate()
                        ->orderByDesc('id')
                        ->first();

                    $orderCount = 1;
                    if ($lastOrder && preg_match('/-(\d{3})$/', $lastOrder->order_number, $matches)) {
                        $orderCount = intval($matches[1]) + 1;
                    }

                    $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);
                    $dailyNumber = '#' . $orderNumber;

                    try {
                        // Определяем delivery_status для pickup/delivery заказов
                        $deliveryStatus = null;
                        if (in_array($validated['type'], ['delivery', 'pickup'])) {
                            $deliveryStatus = $validated['delivery_status'] ?? 'pending';
                        }

                        $order = Order::create([
                            'restaurant_id' => $restaurantId,
                            'price_list_id' => $validated['price_list_id'] ?? null,
                            'order_number' => $orderNumber,
                            'daily_number' => $dailyNumber,
                            'type' => $validated['type'],
                            'table_id' => $validated['table_id'] ?? null,
                            'customer_id' => $customerId,
                            'user_id' => $request->input('waiter_id'),
                            'status' => 'cooking',
                            'payment_status' => 'pending',
                            'payment_method' => $validated['payment_method'] ?? null,
                            'subtotal' => 0,
                            'discount_amount' => $validated['discount_amount'] ?? 0,
                            'total' => 0,
                            'comment' => $validated['notes'] ?? null,
                            'phone' => $validated['phone'] ?? null,
                            'delivery_address' => $validated['delivery_address'] ?? null,
                            'delivery_notes' => $validated['delivery_notes'] ?? null,
                            'delivery_status' => $deliveryStatus,
                            // Scheduling
                            'is_asap' => $validated['is_asap'] ?? true,
                            'scheduled_at' => $validated['scheduled_at'] ?? null,
                            // Предоплата
                            'prepayment' => $validated['prepayment'] ?? 0,
                            'prepayment_method' => $validated['prepayment_method'] ?? null,
                        ]);
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($attempt === $maxAttempts - 1) throw $e;
                        usleep(50000);
                    }
                }

                if (!$order) throw new \Exception('Не удалось создать заказ');

                // Позиции
                $subtotal = 0;
                $priceListId = $validated['price_list_id'] ?? null;
                $priceListService = $priceListId ? new \App\Services\PriceListService() : null;

                foreach ($validated['items'] as $item) {
                    $dish = Dish::forRestaurant($restaurantId)->find($item['dish_id']);
                    if (!$dish) {
                        throw new \Exception("Блюдо с ID {$item['dish_id']} не найдено");
                    }

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
                        'comment' => $item['notes'] ?? null,
                        'status' => 'cooking', // Default status for kitchen display
                    ]);
                }

                // Вычисляем итоговую сумму с учётом скидки
                $discountAmount = floatval($validated['discount_amount'] ?? 0);
                $total = max(0, $subtotal - $discountAmount);

                // Определяем статус оплаты на основе предоплаты
                $prepayment = floatval($validated['prepayment'] ?? 0);
                $paymentStatus = 'pending';
                if ($prepayment > 0) {
                    $paymentStatus = $prepayment >= $total ? 'paid' : 'partial';
                }

                // Логируем для отладки
                \Log::info('OrderController: Payment calculation', [
                    'subtotal' => $subtotal,
                    'discountAmount' => $discountAmount,
                    'total' => $total,
                    'prepayment' => $prepayment,
                    'paymentStatus' => $paymentStatus,
                    'is_asap' => $validated['is_asap'] ?? null,
                ]);

                $order->update([
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'payment_status' => $paymentStatus,
                ]);
                return $order;
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($validated['type'] === 'dine_in' && !empty($validated['table_id'])) {
            RealtimeEvent::tableStatusChanged($validated['table_id'], 'occupied', $order->restaurant_id);
        }

        $order->load(['items.dish', 'table']);
        RealtimeEvent::orderCreated($order->toArray());

        if ($validated['type'] === 'delivery') {
            RealtimeEvent::deliveryNew($order->toArray());
        }

        // Автоматическая печать на кухню
        $orderService = new \App\Services\OrderService();
        $printResult = $orderService->autoPrintToKitchen($order);

        return response()->json([
            'success' => true,
            'message' => 'Заказ создан',
            'data' => $order,
            'print_result' => $printResult,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $order->load(['items.dish', 'table', 'waiter', 'customer']),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
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
                if ($order->status !== 'cooking') {
                    $order->update(['status' => 'cooking']);
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
                    $order->update(['status' => 'ready']);
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

                if (!$hasStartedItems && $order->status === 'cooking') {
                    $order->update(['status' => 'confirmed']);
                }

                $newStatus = 'confirmed'; // Для события
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
                $order->update(['status' => 'cooking']);
                $newStatus = 'cooking'; // Для события
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

        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            Table::where('id', $order->table_id)->update(['status' => 'free']);
            RealtimeEvent::tableStatusChanged($order->table_id, 'free', $order->restaurant_id);
        }

        // Обновляем delivery_status для delivery, pickup и preorder заказов
        if (in_array($order->type, ['delivery', 'pickup', 'preorder'])) {
            $map = ['cooking' => 'preparing', 'ready' => 'ready', 'completed' => 'delivered'];
            if (isset($map[$newStatus])) $order->update(['delivery_status' => $map[$newStatus]]);
        }

        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, $newStatus);
        return response()->json(['success' => true, 'message' => 'Статус обновлён', 'data' => $order->fresh(['items.dish', 'table'])]);
    }

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

        if ($validated['delivery_status'] === 'delivered') {
            $order->update(['status' => 'completed']);
        }

        RealtimeEvent::deliveryStatusChanged($order->fresh()->toArray(), $validated['delivery_status']);
        return response()->json(['success' => true, 'message' => 'Статус доставки обновлён', 'data' => $order->fresh()]);
    }

    public function assignCourier(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(['courier_id' => 'required|integer']);
        $order->update(['courier_id' => $validated['courier_id'], 'delivery_status' => 'picked_up', 'picked_up_at' => now()]);

        RealtimeEvent::dispatch('delivery', 'delivery_assigned', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'courier_id' => $validated['courier_id'],
            'message' => "Курьер назначен на заказ #{$order->order_number}",
        ]);

        return response()->json(['success' => true, 'message' => 'Курьер назначен', 'data' => $order->fresh()]);
    }

    /**
     * Перенести заказ на другой стол
     */
    public function transfer(TransferOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);

        $targetTableId = $validated['target_table_id'];

        // Проверяем, что заказ активен
        if (in_array($order->status, ['completed', 'cancelled'])) {
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
        $sourceTable = $order->table;

        return DB::transaction(function () use ($order, $targetTable, $sourceTable, $targetTableId) {
            $oldTableId = $order->table_id;

            // Просто переносим заказ на другой стол
            // Если там уже есть заказы - они будут как отдельные табы
            $order->update(['table_id' => $targetTableId]);

            // Обновляем статус исходного стола
            if ($sourceTable) {
                // Проверяем, есть ли ещё заказы на исходном столе
                $otherOrdersOnSource = Order::where('table_id', $oldTableId)
                    ->where('id', '!=', $order->id)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->exists();

                if (!$otherOrdersOnSource) {
                    $sourceTable->update(['status' => 'free']);
                    RealtimeEvent::tableStatusChanged($oldTableId, 'free', $sourceTable->restaurant_id);
                }
            }

            // Обновляем статус целевого стола
            $targetTable->update(['status' => 'occupied']);
            RealtimeEvent::tableStatusChanged($targetTableId, 'occupied', $targetTable->restaurant_id);

            // Отправляем событие о переносе
            RealtimeEvent::dispatch('orders', 'order_transferred', [
                'order_id' => $order->id,
                'from_table_id' => $oldTableId,
                'to_table_id' => $targetTableId,
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
