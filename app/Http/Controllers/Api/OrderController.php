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
use App\Models\CashOperation;
use App\Models\Reservation;
use App\Services\BonusService;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        // Filter by specific date or today
        if ($request->has('date')) {
            $filterDate = Carbon::parse($request->input('date'));
            // Сравниваем дату напрямую с сегодняшней датой ресторана (без учёта времени)
            $restaurantToday = TimeHelper::today($request->input('restaurant_id', 1));
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
            $today = TimeHelper::today($request->input('restaurant_id', 1));
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
                ->where('restaurant_id', $request->input('restaurant_id', 1))
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
                ->where('restaurant_id', $request->input('restaurant_id', 1))
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
        $restaurantId = $request->input('restaurant_id', 1);
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:dine_in,delivery,pickup',
            'table_id' => 'nullable|integer|exists:tables,id',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
            // Pickup/Delivery scheduling
            'is_asap' => 'nullable|boolean',
            'scheduled_at' => 'nullable|date',
            'payment_method' => 'nullable|in:cash,card,online',
            // Статус и предоплата
            'delivery_status' => 'nullable|in:pending,preparing,ready,picked_up,delivered',
            'prepayment' => 'nullable|numeric|min:0',
            'prepayment_method' => 'nullable|in:cash,card',
            // Скидки
            'discount_amount' => 'nullable|numeric|min:0',
            'manual_discount_percent' => 'nullable|integer|min:0|max:100',
            'promotion_id' => 'nullable|integer',
        ]);

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
        $restaurantId = $validated['restaurant_id'] ?? $request->input('restaurant_id', 1);
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
            RealtimeEvent::tableStatusChanged($validated['table_id'], 'occupied');
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

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,cooking,ready,completed,cancelled,return_to_new,return_to_cooking',
            'station' => 'nullable|string', // slug цеха для фильтрации позиций
        ]);

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
            RealtimeEvent::tableStatusChanged($order->table_id, 'free');
        }

        // Обновляем delivery_status для delivery, pickup и preorder заказов
        if (in_array($order->type, ['delivery', 'pickup', 'preorder'])) {
            $map = ['cooking' => 'preparing', 'ready' => 'ready', 'completed' => 'delivered'];
            if (isset($map[$newStatus])) $order->update(['delivery_status' => $map[$newStatus]]);
        }

        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, $newStatus);
        return response()->json(['success' => true, 'message' => 'Статус обновлён', 'data' => $order->fresh(['items.dish', 'table'])]);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,card,online',
            'amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'bonus_used' => 'nullable|numeric|min:0',
            'promo_code' => 'nullable|string',
        ]);

        // Проверяем, не оплачен ли уже заказ
        if ($order->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Заказ уже оплачен'], 422);
        }

        // Проверяем открытую кассовую смену
        $restaurantId = $order->restaurant_id ?? 1;
        $shift = \App\Models\CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Откройте кассовую смену перед оплатой'], 422);
        }

        // Проверяем, что смена открыта сегодня
        $shiftDate = $shift->opened_at->toDateString();
        $today = now()->toDateString();

        if ($shiftDate !== $today) {
            $shiftDateFormatted = $shift->opened_at->format('d.m.Y');
            return response()->json([
                'success' => false,
                'message' => "Смена от {$shiftDateFormatted}. Закройте её и откройте новую смену для сегодняшних операций.",
                'error_code' => 'SHIFT_OUTDATED'
            ], 422);
        }

        // Применяем скидку если передана
        $discountAmount = $validated['discount_amount'] ?? 0;
        $bonusUsed = $validated['bonus_used'] ?? 0;

        if ($discountAmount > 0 || $bonusUsed > 0) {
            $order->update([
                'discount_amount' => $discountAmount,
                'bonus_used' => $bonusUsed,
                'total' => max(0, $order->subtotal - $discountAmount - $bonusUsed + ($order->delivery_fee ?? 0)),
                'promo_code' => $validated['promo_code'] ?? null,
            ]);
        }

        // Обновляем заказ
        $order->update([
            'payment_status' => 'paid',
            'payment_method' => $validated['method'],
            'paid_at' => now()
        ]);

        // Записываем операцию в кассу
        \App\Models\CashOperation::recordOrderPayment(
            $order,
            $validated['method'],
            null, // staffId
            null, // fiscalReceipt
            $validated['amount'] ?? null
        );

        // Обновляем статистику клиента и работаем с бонусами через BonusService
        if ($order->customer_id && $order->customer) {
            $order->customer->updateStats();

            $bonusService = new BonusService($restaurantId);

            // Списываем бонусы если использовались
            if ($bonusUsed > 0) {
                $bonusService->spendForOrder($order, (int) $bonusUsed);
            }

            // Начисляем бонусы за заказ
            $bonusService->earnForOrder($order);
        }

        // Автоматическое списание со склада
        $this->deductInventoryForOrder($order, $restaurantId);

        RealtimeEvent::orderPaid($order->fresh()->toArray(), $validated['method']);
        return response()->json(['success' => true, 'message' => 'Оплата принята', 'data' => $order->fresh()]);
    }

    /**
     * Списать ингредиенты со склада при оплате заказа
     */
    protected function deductInventoryForOrder(Order $order, int $restaurantId): void
    {
        try {
            // Получаем склад по умолчанию
            $warehouseId = \App\Models\Warehouse::where('restaurant_id', $restaurantId)
                ->where('is_default', true)
                ->value('id')
                ?? \App\Models\Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

            if (!$warehouseId) {
                return; // Нет склада - пропускаем
            }

            // Списываем ингредиенты по каждой позиции
            foreach ($order->items as $item) {
                if ($item->dish_id) {
                    \App\Models\Recipe::deductIngredientsForDish(
                        $item->dish_id,
                        $warehouseId,
                        $item->quantity,
                        $order->id,
                        null // userId
                    );
                }
            }

            // Помечаем заказ как обработанный
            $order->update(['inventory_deducted' => true]);
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем оплату
            \Log::warning('Inventory deduction failed for order #' . $order->id . ': ' . $e->getMessage());
        }
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

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id',
            'quantity' => 'required|integer|min:1',
            'modifiers' => 'nullable|array',
            'notes' => 'nullable|string|max:255',
        ]);

        $dish = Dish::find($validated['dish_id']);
        if ($dish->is_stopped || !$dish->is_available) {
            return response()->json(['success' => false, 'message' => "Блюдо '{$dish->name}' недоступно"], 422);
        }

        $itemTotal = $dish->price * $validated['quantity'];
        $item = OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->price,
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

    public function cancelWithWriteOff(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'manager_id' => 'required|integer|exists:users,id',
        ]);

        // Логируем для отладки
        \Log::info('cancelWithWriteOff', [
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'linked_table_ids' => $order->linked_table_ids,
        ]);

        $oldStatus = $order->status;
        $tableId = $order->table_id; // Сохраняем до update
        $linkedTableIds = $order->linked_table_ids ?? [];
        $reservationId = $order->reservation_id;

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $validated['reason'],
            'cancelled_by' => $validated['manager_id'],
            'is_write_off' => true,
        ]);

        // Обрабатываем бронирование - отменяем его тоже
        if ($reservationId) {
            $reservation = Reservation::find($reservationId);
            if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                $reservation->update(['status' => 'cancelled']);
                \Log::info('Reservation cancelled', ['reservation_id' => $reservationId]);
            }
        }

        if ($tableId) {
            \Log::info('Freeing table', ['table_id' => $tableId]);
            Table::where('id', $tableId)->update(['status' => 'free']);
            RealtimeEvent::tableStatusChanged($tableId, 'free');
        } else {
            \Log::warning('No table_id found on order', ['order_id' => $order->id]);
        }

        // Освобождаем связанные столы
        if (!empty($linkedTableIds)) {
            foreach ($linkedTableIds as $linkedTableId) {
                if ($linkedTableId != $tableId) {
                    Table::where('id', $linkedTableId)->update(['status' => 'free']);
                    RealtimeEvent::tableStatusChanged($linkedTableId, 'free');
                }
            }
        }

        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, 'cancelled');
        return response()->json(['success' => true, 'message' => 'Заказ отменён со списанием', 'data' => $order->fresh(['items.dish', 'table'])]);
    }

    /**
     * Создать заявку на отмену заказа
     */
    public function requestCancellation(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'requested_by' => 'nullable|integer|exists:users,id',
        ]);

        $order->update([
            'pending_cancellation' => true,
            'cancel_request_reason' => $validated['reason'],
            'cancel_requested_by' => $validated['requested_by'] ?? null,
            'cancel_requested_at' => now(),
        ]);

        RealtimeEvent::dispatch('cancellations', 'cancellation_requested', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Заявка на отмену отправлена',
            'data' => $order->fresh()
        ]);
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
            ->with(['order.table', 'order.customer', 'dish'])
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
                'requested_by' => 'Неизвестно',
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
     * Подтвердить отмену заказа
     */
    public function approveCancellation(Request $request, Order $order): JsonResponse
    {
        if (!$order->pending_cancellation) {
            return response()->json(['success' => false, 'message' => 'Заказ не ожидает отмены'], 400);
        }

        $validated = $request->validate([
            'refund_method' => 'nullable|in:cash,card',
        ]);

        $isPaid = $order->payment_status === 'paid' || $order->prepayment > 0;

        // Если заказ был оплачен - создаём возврат
        if ($isPaid) {
            $refundAmount = $order->prepayment ?: $order->total;
            $refundMethod = $validated['refund_method'] ?? 'cash';

            CashOperation::recordOrderRefund(
                $order->restaurant_id ?? 1,
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
            'status' => 'cancelled',
            'delivery_status' => $order->type !== 'dine_in' ? 'cancelled' : $order->delivery_status,
            'cancelled_at' => now(),
            'cancel_reason' => $order->cancel_request_reason,
            'is_write_off' => true,
            'pending_cancellation' => false,
        ]);

        // Обрабатываем бронирование - отменяем его тоже
        if ($reservationId) {
            $reservation = Reservation::find($reservationId);
            if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                $reservation->update(['status' => 'cancelled']);
            }
        }

        if ($tableId) {
            Table::where('id', $tableId)->update(['status' => 'free']);
            RealtimeEvent::tableStatusChanged($tableId, 'free');
        }

        // Освобождаем связанные столы
        if (!empty($linkedTableIds)) {
            foreach ($linkedTableIds as $linkedTableId) {
                if ($linkedTableId != $tableId) {
                    Table::where('id', $linkedTableId)->update(['status' => 'free']);
                    RealtimeEvent::tableStatusChanged($linkedTableId, 'free');
                }
            }
        }

        RealtimeEvent::orderStatusChanged($order->fresh()->toArray(), $oldStatus, 'cancelled');

        return response()->json(['success' => true, 'message' => 'Отмена подтверждена']);
    }

    /**
     * Отклонить заявку на отмену
     */
    public function rejectCancellation(Request $request, Order $order): JsonResponse
    {
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
        $restaurantId = $request->input('restaurant_id', 1);
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

    /**
     * Перенести заказ на другой стол
     */
    public function transfer(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'target_table_id' => 'required|integer|exists:tables,id',
        ]);

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

        $targetTable = Table::find($targetTableId);
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
                    RealtimeEvent::tableStatusChanged($oldTableId, 'free');
                }
            }

            // Обновляем статус целевого стола
            $targetTable->update(['status' => 'occupied']);
            RealtimeEvent::tableStatusChanged($targetTableId, 'occupied');

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