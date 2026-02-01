<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Customer;
use App\Models\User;
use App\Models\DeliveryZone;
use App\Models\DeliverySetting;
use App\Models\DeliveryProblem;
use App\Models\RealtimeEvent;
use App\Models\Courier;
use App\Models\BonusSetting;
use App\Models\BonusTransaction;
use App\Models\LoyaltySetting;
use App\Services\GeocodingService;
use App\Services\CourierAssignmentService;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Контроллер модуля доставки
 */
class DeliveryController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // ===== ЗАКАЗЫ ДОСТАВКИ =====

    /**
     * Список заказов доставки с фильтрами
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['items.dish', 'customer.loyaltyLevel', 'courier', 'loyaltyLevel'])
            ->whereIn('type', ['delivery', 'pickup'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Фильтр по дате (используем scheduled_at для предзаказов, иначе created_at)
        if ($request->has('date')) {
            $date = $request->input('date');
            $query->where(function ($q) use ($date) {
                $q->whereDate('scheduled_at', $date)
                  ->orWhere(function ($q2) use ($date) {
                      $q2->whereNull('scheduled_at')
                         ->whereDate('created_at', $date);
                  });
            });
        } elseif ($request->boolean('today')) {
            $restaurantId = $this->getRestaurantId($request);
            $today = TimeHelper::today($restaurantId);
            $query->where(function ($q) use ($today) {
                $q->whereDate('scheduled_at', $today)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->whereNull('scheduled_at')
                         ->whereDate('created_at', $today);
                  });
            });
        }

        // Фильтр по статусу доставки
        if ($request->has('delivery_status')) {
            $statuses = is_array($request->input('delivery_status'))
                ? $request->input('delivery_status')
                : [$request->input('delivery_status')];
            $query->whereIn('delivery_status', $statuses);
        }

        // Фильтр по курьеру
        if ($request->has('courier_id')) {
            if ($request->input('courier_id') === 'unassigned') {
                $query->whereNull('courier_id');
            } else {
                $query->where('courier_id', $request->input('courier_id'));
            }
        }

        // Поиск
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('delivery_address', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);

        // Статистика (считаем до пагинации)
        $stats = $this->getDeliveryStats($this->getRestaurantId($request));

        if ($request->has('page')) {
            $paginated = $query->orderByDesc('created_at')->paginate($perPage);

            // Добавляем вычисляемые поля
            $paginated->getCollection()->transform(function ($order) {
                return $this->enrichOrderData($order);
            });

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'stats' => $stats,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // Обратная совместимость: без page возвращаем с лимитом
        $orders = $query->orderByDesc('created_at')->limit($perPage)->get();

        // Добавляем вычисляемые поля
        $orders->transform(function ($order) {
            return $this->enrichOrderData($order);
        });

        return response()->json([
            'success' => true,
            'data' => $orders,
            'stats' => $stats,
        ]);
    }

    /**
     * Создать заказ доставки
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            // Адрес
            'delivery_address' => 'required|string|max:500',
            'delivery_street' => 'nullable|string|max:200',
            'delivery_house' => 'nullable|string|max:20',
            'delivery_building' => 'nullable|string|max:20',
            'delivery_apartment' => 'nullable|string|max:20',
            'delivery_entrance' => 'nullable|string|max:10',
            'delivery_floor' => 'nullable|string|max:10',
            'delivery_intercom' => 'nullable|string|max:20',
            'delivery_notes' => 'nullable|string|max:500',
            'delivery_latitude' => 'nullable|numeric',
            'delivery_longitude' => 'nullable|numeric',
            // Время
            'is_asap' => 'boolean',
            'desired_delivery_time' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            // Позиции
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
            // Оплата
            'payment_method' => 'required|in:cash,card,online',
            'change_from' => 'nullable|numeric|min:0',
            // Прочее
            'promo_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            // Статус и предоплата
            'delivery_status' => 'nullable|in:pending,preparing,ready,picked_up,in_transit,delivered',
            'prepayment' => 'nullable|numeric|min:0',
            'prepayment_method' => 'nullable|in:cash,card',
            // Скидки
            'discount_amount' => 'nullable|numeric|min:0',
            'manual_discount_percent' => 'nullable|integer|min:0|max:100',
            'promotion_id' => 'nullable|integer',
            'applied_discounts' => 'nullable|array', // Единый формат скидок
            // Бонусы
            'bonus_used' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Создаём или находим клиента
        $customerId = $validated['customer_id'] ?? null;
        if (!$customerId && $validated['phone']) {
            // Нормализуем телефон - оставляем только цифры
            $normalizedPhone = preg_replace('/[^0-9]/', '', $validated['phone']);

            // Ищем существующего клиента по нормализованному телефону
            $customer = Customer::where('restaurant_id', $restaurantId)
                ->byPhone($normalizedPhone)
                ->first();

            if ($customer) {
                $customerId = $customer->id;
                // Обновляем имя если передано
                if (!empty($validated['customer_name']) && $validated['customer_name'] !== 'Клиент') {
                    $customer->update(['name' => $validated['customer_name']]);
                }
            } else {
                // Создаём нового клиента с нормализованным телефоном
                $customer = Customer::create([
                    'restaurant_id' => $restaurantId,
                    'phone' => $normalizedPhone,
                    'name' => $validated['customer_name'] ?? 'Клиент',
                    'email' => $validated['email'] ?? null,
                ]);
                $customerId = $customer->id;
            }
        }

        // Определяем зону доставки и стоимость через геокодирование
        $deliveryFee = 0;
        $deliveryZoneId = null;
        $deliveryDistance = null;
        $geocoding = app(GeocodingService::class);

        if (isset($validated['delivery_latitude'], $validated['delivery_longitude'])) {
            // Если координаты уже переданы - определяем зону по ним
            $zone = $geocoding->detectZone(
                $validated['delivery_latitude'],
                $validated['delivery_longitude'],
                $restaurantId
            );
            if ($zone) {
                $deliveryZoneId = $zone->id;
                $deliveryFee = $zone->delivery_fee;
                // Рассчитываем расстояние от ресторана
                $restaurantCoords = $geocoding->getRestaurantCoordinates();
                if ($restaurantCoords['lat'] && $restaurantCoords['lng']) {
                    $deliveryDistance = $geocoding->calculateDistance(
                        $restaurantCoords['lat'], $restaurantCoords['lng'],
                        $validated['delivery_latitude'], $validated['delivery_longitude']
                    );
                }
            }
        } elseif (!empty($validated['delivery_address'])) {
            // Геокодируем адрес и определяем зону
            $geoResult = $geocoding->geocodeWithZone($validated['delivery_address'], $restaurantId);
            if ($geoResult['success'] && $geoResult['zone']) {
                $deliveryZoneId = $geoResult['zone']->id;
                $deliveryFee = $geoResult['delivery_cost'];
                $deliveryDistance = $geoResult['distance'];
                // Сохраняем координаты
                $validated['delivery_latitude'] = $geoResult['coordinates']['lat'] ?? null;
                $validated['delivery_longitude'] = $geoResult['coordinates']['lng'] ?? null;
            }
        }

        // Fallback: используем дефолтную зону если геокодирование не сработало
        if (!$deliveryZoneId) {
            $zone = DeliveryZone::where('restaurant_id', $restaurantId)->active()->first();
            if ($zone) {
                $deliveryZoneId = $zone->id;
                $deliveryFee = $zone->delivery_fee;
            }
        }

        // Генерируем номер заказа
        $today = TimeHelper::today($restaurantId);
        $orderCount = Order::whereDate('created_at', $today)->count() + 1;
        $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

        // Создаём заказ
        $order = Order::create([
            'restaurant_id' => $restaurantId,
            'order_number' => $orderNumber,
            'daily_number' => '#' . $orderNumber,
            'type' => 'delivery',
            'table_id' => null, // Explicitly set null for delivery orders
            'customer_id' => $customerId,
            'phone' => $validated['phone'],
            'status' => 'confirmed', // Confirmed so it appears on kitchen display
            'payment_status' => 'pending',
            'payment_method' => $validated['payment_method'],
            // Адрес
            'delivery_address' => $validated['delivery_address'],
            'delivery_street' => $validated['delivery_street'] ?? null,
            'delivery_house' => $validated['delivery_house'] ?? null,
            'delivery_building' => $validated['delivery_building'] ?? null,
            'delivery_apartment' => $validated['delivery_apartment'] ?? null,
            'delivery_entrance' => $validated['delivery_entrance'] ?? null,
            'delivery_floor' => $validated['delivery_floor'] ?? null,
            'delivery_intercom' => $validated['delivery_intercom'] ?? null,
            'delivery_notes' => $validated['delivery_notes'] ?? null,
            'delivery_latitude' => $validated['delivery_latitude'] ?? null,
            'delivery_longitude' => $validated['delivery_longitude'] ?? null,
            'delivery_zone_id' => $deliveryZoneId,
            'delivery_distance' => $deliveryDistance,
            // Время
            'is_asap' => $validated['is_asap'] ?? true,
            'desired_delivery_time' => $validated['desired_delivery_time'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? $validated['desired_delivery_time'] ?? null,
            'delivery_status' => $validated['delivery_status'] ?? 'pending',
            // Предоплата
            'prepayment' => $validated['prepayment'] ?? 0,
            'prepayment_method' => $validated['prepayment_method'] ?? null,
            // Скидки
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'applied_discounts' => $validated['applied_discounts'] ?? null,
            // Суммы
            'subtotal' => 0,
            'delivery_fee' => $deliveryFee,
            'total' => 0,
            'change_from' => $validated['change_from'] ?? null,
            'comment' => $validated['notes'] ?? null,
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
                'status' => 'cooking', // Default status for kitchen display
            ]);
        }

        // Проверяем бесплатную доставку
        if ($deliveryZoneId) {
            $zone = DeliveryZone::find($deliveryZoneId);
            if ($zone) {
                $deliveryFee = $zone->getDeliveryFee($subtotal);
            }
        }

        // Вычисляем скидку лояльности если есть клиент
        $loyaltyDiscountAmount = 0;
        $loyaltyLevelId = null;

        if ($customerId) {
            $customer = Customer::with('loyaltyLevel')->find($customerId);
            $levelsEnabled = LoyaltySetting::get('levels_enabled', '1', $restaurantId) !== '0';

            if ($levelsEnabled && $customer && $customer->loyaltyLevel && $customer->loyaltyLevel->discount_percent > 0) {
                $loyaltyDiscountAmount = round($subtotal * $customer->loyaltyLevel->discount_percent / 100, 2);
                $loyaltyLevelId = $customer->loyaltyLevel->id;
            }
        }

        // Вычисляем итоговую сумму (с учётом скидок)
        $discountAmount = floatval($validated['discount_amount'] ?? 0);
        $bonusUsed = floatval($validated['bonus_used'] ?? 0);
        $total = max(0, $subtotal + $deliveryFee - $discountAmount - $loyaltyDiscountAmount);

        // Проверяем и ограничиваем использование бонусов
        if ($bonusUsed > 0 && $customerId) {
            $customer = $customer ?? Customer::find($customerId);
            $bonusSetting = BonusSetting::where('restaurant_id', $restaurantId)->first();

            // Проверяем что бонусная система включена
            if (!$bonusSetting || !$bonusSetting->is_enabled) {
                $bonusUsed = 0;
            } else {
                // Ограничение по spend_rate (max % от заказа)
                if ($bonusSetting->spend_rate) {
                    $maxByRate = $total * ($bonusSetting->spend_rate / 100);
                    $bonusUsed = min($bonusUsed, $maxByRate);
                }
                // Ограничение по балансу клиента
                if ($customer) {
                    $bonusUsed = min($bonusUsed, $customer->bonus_balance ?? 0);
                }
                // Не более суммы заказа
                $bonusUsed = min($bonusUsed, $total);
            }
        }

        // Итого к оплате деньгами
        $totalToPay = max(0, $total - $bonusUsed);

        // Определяем статус оплаты на основе предоплаты и бонусов
        $prepayment = floatval($validated['prepayment'] ?? 0);
        $paymentStatus = 'pending';
        $totalPaid = $prepayment + $bonusUsed;
        if ($totalPaid > 0) {
            $paymentStatus = $totalPaid >= $total ? 'paid' : 'partial';
        }

        // Логируем для отладки
        \Log::info('DeliveryController: Payment calculation', [
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'discountAmount' => $discountAmount,
            'loyaltyDiscountAmount' => $loyaltyDiscountAmount,
            'bonusUsed' => $bonusUsed,
            'total' => $total,
            'prepayment' => $prepayment,
            'paymentStatus' => $paymentStatus,
            'is_asap' => $validated['is_asap'] ?? null,
        ]);

        // Обновляем сумму заказа
        $order->update([
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'payment_status' => $paymentStatus,
            'loyalty_discount_amount' => $loyaltyDiscountAmount,
            'loyalty_level_id' => $loyaltyLevelId,
            'bonus_used' => $bonusUsed,
        ]);

        // Списываем бонусы с баланса клиента
        if ($bonusUsed > 0 && $customerId) {
            $customer = $customer ?? Customer::find($customerId);
            if ($customer) {
                $customer->decrement('bonus_balance', $bonusUsed);

                // Записываем транзакцию
                BonusTransaction::create([
                    'customer_id' => $customerId,
                    'restaurant_id' => $restaurantId,
                    'order_id' => $order->id,
                    'amount' => -$bonusUsed,
                    'type' => 'spend',
                    'description' => "Списание за заказ #{$order->order_number}",
                ]);
            }
        }

        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        // Broadcast: Новый заказ доставки
        RealtimeEvent::dispatch('delivery', 'delivery_new', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'address' => $order->delivery_address,
            'total' => $order->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Заказ на доставку создан',
            'data' => $this->enrichOrderData($order),
        ], 201);
    }

    /**
     * Детали заказа
     */
    public function showOrder(Order $order): JsonResponse
    {
        $order->load(['items.dish', 'customer', 'courier', 'statusHistory']);

        return response()->json([
            'success' => true,
            'data' => $this->enrichOrderData($order),
        ]);
    }

    /**
     * Обновить статус доставки
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'delivery_status' => 'required|in:pending,preparing,ready,picked_up,in_transit,delivered,cancelled',
        ]);

        $oldStatus = $order->delivery_status;
        $newStatus = $validated['delivery_status'];

        $updateData = ['delivery_status' => $newStatus];

        // Устанавливаем временные метки
        switch ($newStatus) {
            case 'preparing':
                $updateData['cooking_started_at'] = now();
                $updateData['status'] = 'cooking';
                // Обновляем статусы позиций для отображения на кухне
                // НЕ устанавливаем cooking_started_at - повар сам возьмёт в работу
                $order->items()->update([
                    'status' => 'cooking',
                ]);
                break;
            case 'ready':
                $updateData['cooking_finished_at'] = now();
                $updateData['ready_at'] = now();
                $updateData['status'] = 'ready';
                // Обновляем статусы позиций
                $order->items()->update([
                    'status' => 'ready',
                    'cooking_finished_at' => now(),
                ]);
                break;
            case 'picked_up':
                $updateData['picked_up_at'] = now();
                break;
            case 'in_transit':
                $updateData['status'] = 'delivering';
                break;
            case 'delivered':
                $updateData['delivered_at'] = now();
                $updateData['completed_at'] = now();
                $updateData['status'] = 'completed';
                // НЕ устанавливаем payment_status = 'paid' автоматически!
                // Оплата должна подтверждаться отдельно (для наличных/карты курьеру)
                // Для онлайн-оплаты статус уже 'paid' при создании заказа
                // Обновляем статусы позиций
                $order->items()->update(['status' => 'served']);
                // Освобождаем курьера
                if ($order->courier_id) {
                    User::where('id', $order->courier_id)->update([
                        'courier_today_orders' => DB::raw('courier_today_orders + 1'),
                        'courier_today_earnings' => DB::raw('courier_today_earnings + ' . ($order->delivery_fee ?? 150)),
                    ]);
                }
                // Начисляем бонусы клиенту
                if ($order->customer_id) {
                    try {
                        $bonusSettings = BonusSetting::getForRestaurant($order->restaurant_id);
                        if ($bonusSettings->is_enabled) {
                            $order->load('customer');
                            if ($order->customer) {
                                // Получаем эффективную ставку с учётом уровня лояльности
                                $effectiveRate = $bonusSettings->getEffectiveEarnRate($order->customer);
                                // Сумма для начисления = total - bonus_used (не начисляем бонусы на оплату бонусами)
                                $earnableAmount = $order->total - ($order->bonus_used ?? 0);
                                $bonusEarned = $bonusSettings->calculateEarnAmount($earnableAmount, $effectiveRate);

                                if ($bonusEarned > 0) {
                                    BonusTransaction::earnFromOrder(
                                        $order->customer,
                                        $order,
                                        $effectiveRate
                                    );
                                    \Log::info('Delivery bonus earned', [
                                        'order_id' => $order->id,
                                        'customer_id' => $order->customer_id,
                                        'bonus_earned' => $bonusEarned,
                                        'earn_rate' => $effectiveRate,
                                    ]);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Delivery bonus accrual failed: ' . $e->getMessage());
                    }
                }
                break;
            case 'cancelled':
                $updateData['cancelled_at'] = now();
                $updateData['status'] = 'cancelled';
                // Отменяем все позиции
                $order->items()->update(['status' => 'cancelled']);
                break;
        }

        $order->update($updateData);

        // Broadcast
        RealtimeEvent::dispatch('delivery', 'delivery_status_changed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
            'data' => $this->enrichOrderData($order->fresh(['items.dish', 'customer', 'courier'])),
        ]);
    }

    /**
     * Назначить курьера
     */
    public function assignCourier(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => 'required|integer|exists:users,id',
        ]);

        $order->update([
            'courier_id' => $validated['courier_id'],
        ]);

        // Обновляем статус курьера
        User::where('id', $validated['courier_id'])->update([
            'courier_status' => 'busy',
        ]);

        $courier = User::find($validated['courier_id']);

        // Broadcast
        RealtimeEvent::dispatch('delivery', 'courier_assigned', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'courier_id' => $validated['courier_id'],
            'courier_name' => $courier->name ?? 'Курьер',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен',
            'data' => $this->enrichOrderData($order->fresh(['items.dish', 'customer', 'courier'])),
        ]);
    }

    // ===== КУРЬЕРЫ =====

    /**
     * Список курьеров
     */
    public function couriers(Request $request): JsonResponse
    {
        $couriers = User::where('is_courier', true)
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->orderByRaw("CASE courier_status WHEN 'available' THEN 1 WHEN 'busy' THEN 2 ELSE 3 END")
            ->get();

        // Добавляем информацию о текущих заказах
        $couriers->transform(function ($courier) {
            $activeOrders = Order::where('courier_id', $courier->id)
                ->whereIn('type', ['delivery', 'pickup'])
                ->whereIn('delivery_status', ['picked_up', 'in_transit'])
                ->count();

            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'phone' => $courier->phone,
                'status' => $courier->courier_status ?? 'offline',
                'current_orders' => $activeOrders,
                'today_orders' => $courier->courier_today_orders ?? 0,
                'today_earnings' => $courier->courier_today_earnings ?? 0,
                'last_location' => $courier->courier_last_location,
                'last_seen' => $courier->courier_last_seen,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $couriers,
        ]);
    }

    /**
     * Обновить статус курьера
     */
    public function updateCourierStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:offline,available,busy',
            'location' => 'nullable|array',
        ]);

        $user->update([
            'courier_status' => $validated['status'],
            'courier_last_location' => $validated['location'] ?? $user->courier_last_location,
            'courier_last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Статус курьера обновлён',
        ]);
    }

    // ===== ЗОНЫ ДОСТАВКИ =====

    /**
     * Список зон доставки
     */
    public function zones(Request $request): JsonResponse
    {
        $zones = DeliveryZone::where('restaurant_id', $this->getRestaurantId($request))
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * Создать зону доставки
     */
    public function createZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'min_distance' => 'required|numeric|min:0',
            'max_distance' => 'required|numeric|gt:min_distance',
            'delivery_fee' => 'required|numeric|min:0',
            'free_delivery_from' => 'nullable|numeric|min:0',
            'estimated_time' => 'integer|min:1',
            'color' => 'nullable|string|max:20',
            'polygon' => 'nullable|array',
        ]);

        $zone = DeliveryZone::create([
            'restaurant_id' => $this->getRestaurantId($request),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Зона доставки создана',
            'data' => $zone,
        ], 201);
    }

    /**
     * Обновить зону доставки
     */
    public function updateZone(Request $request, DeliveryZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'min_distance' => 'numeric|min:0',
            'max_distance' => 'numeric',
            'delivery_fee' => 'numeric|min:0',
            'free_delivery_from' => 'nullable|numeric|min:0',
            'estimated_time' => 'integer|min:1',
            'color' => 'nullable|string|max:20',
            'polygon' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Зона обновлена',
            'data' => $zone,
        ]);
    }

    /**
     * Удалить зону доставки
     */
    public function deleteZone(DeliveryZone $zone): JsonResponse
    {
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Зона удалена',
        ]);
    }

    // ===== ГЕОКОДИРОВАНИЕ =====

    /**
     * Определить зону доставки по адресу
     */
    public function detectZone(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'order_total' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0', // алиас для order_total
        ]);

        $restaurantId = $this->getRestaurantId($request);
        // Поддерживаем оба названия: order_total и total
        $orderTotal = floatval($validated['order_total'] ?? $validated['total'] ?? 0);
        $result = $geocoding->geocodeWithZone($validated['address'], $restaurantId);

        if (!$result['success']) {
            // Fallback если геокодирование не настроено
            $restaurant = \App\Models\Restaurant::find($restaurantId);
            $yandexSettings = $restaurant?->getSetting('yandex', []) ?? [];
            $hasApiKey = !empty($yandexSettings['api_key']) || !empty(config('services.yandex.geocoder_key'));

            if (!$hasApiKey) {
                $zone = DeliveryZone::where('restaurant_id', $restaurantId)->active()->first();
                // Используем getDeliveryFee для учёта бесплатной доставки
                $deliveryCost = $zone ? $zone->getDeliveryFee($orderTotal) : 0;
                return response()->json([
                    'success' => true,
                    'zone' => $zone,
                    'zone_id' => $zone?->id,
                    'delivery_cost' => $deliveryCost,
                    'free_delivery_from' => $zone?->free_delivery_from,
                    'delivery_time' => $zone?->estimated_time,
                    'warning' => 'Геокодирование не настроено',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 422);
        }

        // Используем getDeliveryFee для учёта бесплатной доставки
        $zone = $result['zone'];
        $deliveryCost = $zone ? $zone->getDeliveryFee($orderTotal) : 0;

        return response()->json([
            'success' => true,
            'zone' => $zone,
            'zone_id' => $zone?->id,
            'delivery_cost' => $deliveryCost,
            'free_delivery_from' => $zone?->free_delivery_from,
            'delivery_time' => $result['delivery_time'],
            'distance' => $result['distance'],
            'formatted_address' => $result['formatted_address'],
            'coordinates' => $result['coordinates'],
        ]);
    }

    /**
     * Подсказки адресов (автокомплит)
     */
    public function suggestAddress(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3',
            'limit' => 'integer|min:1|max:10',
        ]);

        $suggestions = $geocoding->suggest(
            $validated['query'],
            $validated['limit'] ?? 5
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Геокодировать адрес (получить координаты)
     */
    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
        ]);

        $result = $geocoding->geocode($validated['address']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить координаты адреса',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    // ===== НАСТРОЙКИ =====

    /**
     * Получить настройки доставки
     */
    public function settings(Request $request): JsonResponse
    {
        $settings = DeliverySetting::getAllSettings($this->getRestaurantId($request));

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Обновить настройки доставки
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        foreach ($request->except(['restaurant_id']) as $key => $value) {
            DeliverySetting::setValue($key, $value, $restaurantId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
            'data' => DeliverySetting::getAllSettings($restaurantId),
        ]);
    }

    // ===== АНАЛИТИКА =====

    /**
     * Аналитика доставки
     */
    public function analytics(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 'today');

        $startDate = match($period) {
            'today' => TimeHelper::today($restaurantId),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => TimeHelper::today($restaurantId),
        };

        $query = Order::where('restaurant_id', $restaurantId)
            ->whereIn('type', ['delivery', 'pickup'])
            ->where('created_at', '>=', $startDate);

        // Общая статистика
        $totalOrders = (clone $query)->count();
        $completedOrders = (clone $query)->where('delivery_status', 'delivered')->count();
        $totalRevenue = (clone $query)->where('delivery_status', 'delivered')->sum('total');

        // Среднее время доставки (SQLite-совместимо)
        $deliveredOrders = (clone $query)
            ->where('delivery_status', 'delivered')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at')
            ->select('picked_up_at', 'delivered_at', 'created_at')
            ->get();

        $avgDeliveryTime = 0;
        $onTimeCount = 0;
        if ($deliveredOrders->count() > 0) {
            $totalMinutes = 0;
            foreach ($deliveredOrders as $order) {
                $pickedUp = Carbon::parse($order->picked_up_at);
                $delivered = Carbon::parse($order->delivered_at);
                $created = Carbon::parse($order->created_at);

                $totalMinutes += $pickedUp->diffInMinutes($delivered);

                // Процент вовремя (условно < 60 мин от создания до доставки)
                if ($created->diffInMinutes($delivered) <= 60) {
                    $onTimeCount++;
                }
            }
            $avgDeliveryTime = round($totalMinutes / $deliveredOrders->count());
        }
        $onTimePercent = $completedOrders > 0 ? round(($onTimeCount / $completedOrders) * 100) : 0;

        // По курьерам (SQLite-совместимо)
        $couriers = User::where('is_courier', true)
            ->where('restaurant_id', $restaurantId)
            ->get();

        $courierStats = $couriers->map(function ($courier) use ($startDate) {
            $courierOrders = Order::where('courier_id', $courier->id)
                ->whereIn('type', ['delivery', 'pickup'])
                ->where('delivery_status', 'delivered')
                ->where('created_at', '>=', $startDate)
                ->get();

            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'orders' => $courierOrders->count(),
                'revenue' => $courierOrders->sum('total') ?? 0,
            ];
        })->sortByDesc('orders')->values();

        // По зонам
        $zoneStats = DeliveryZone::where('restaurant_id', $restaurantId)
            ->withCount(['orders as orders_count' => function ($q) use ($startDate) {
                $q->where('delivery_status', 'delivered')
                  ->where('created_at', '>=', $startDate);
            }])
            ->get()
            ->map(fn($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'orders' => $z->orders_count ?? 0,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'total_revenue' => $totalRevenue,
                'avg_delivery_time' => round($avgDeliveryTime ?? 0),
                'on_time_percent' => $onTimePercent,
                'by_couriers' => $courierStats,
                'by_zones' => $zoneStats,
            ],
        ]);
    }

    // ===== HELPERS =====

    /**
     * Обогатить данные заказа
     */
    private function enrichOrderData(Order $order): array
    {
        $data = $order->toArray();

        // Статус на русском
        $data['delivery_status_label'] = $this->getDeliveryStatusLabel($order->delivery_status);
        $data['delivery_status_color'] = $this->getDeliveryStatusColor($order->delivery_status);

        // Статус оплаты на русском
        $data['payment_status_label'] = match($order->payment_status) {
            'paid' => 'Оплачен',
            'pending' => 'Не оплачен',
            'partial' => 'Частично',
            'refunded' => 'Возврат',
            default => $order->payment_status,
        };

        // Способ оплаты на русском
        $data['payment_method_label'] = match($order->payment_method) {
            'cash' => 'Наличными',
            'card' => 'Картой курьеру',
            'online' => 'Онлайн',
            default => $order->payment_method,
        };

        // Время ожидания
        $data['wait_time_minutes'] = $order->created_at
            ? Carbon::parse($order->created_at)->diffInMinutes(now())
            : 0;

        // Срочность
        $data['urgency'] = $this->calculateUrgency($order);

        // Время в пути
        if ($order->picked_up_at && in_array($order->delivery_status, ['in_transit'])) {
            $data['in_transit_minutes'] = Carbon::parse($order->picked_up_at)->diffInMinutes(now());
        }

        // Полный адрес
        $data['full_address'] = $this->buildFullAddress($order);

        return $data;
    }

    /**
     * Собрать полный адрес
     */
    private function buildFullAddress(Order $order): string
    {
        $parts = [];

        if ($order->delivery_street) {
            $addr = $order->delivery_street;
            if ($order->delivery_house) $addr .= ", д. {$order->delivery_house}";
            if ($order->delivery_building) $addr .= ", корп. {$order->delivery_building}";
            if ($order->delivery_apartment) $addr .= ", кв. {$order->delivery_apartment}";
            $parts[] = $addr;
        } else {
            $parts[] = $order->delivery_address;
        }

        $details = [];
        if ($order->delivery_entrance) $details[] = "подъезд {$order->delivery_entrance}";
        if ($order->delivery_floor) $details[] = "этаж {$order->delivery_floor}";
        if ($order->delivery_intercom) $details[] = "домофон {$order->delivery_intercom}";

        if ($details) {
            $parts[] = implode(', ', $details);
        }

        return implode("\n", $parts);
    }

    /**
     * Вычислить срочность
     */
    private function calculateUrgency(Order $order): string
    {
        if (in_array($order->delivery_status, ['delivered', 'cancelled'])) {
            return 'none';
        }

        $targetTime = $order->desired_delivery_time
            ? Carbon::parse($order->desired_delivery_time)
            : Carbon::parse($order->created_at)->addMinutes(60);

        $minutesLeft = now()->diffInMinutes($targetTime, false);

        if ($minutesLeft < 0) return 'overdue';
        if ($minutesLeft <= 15) return 'critical';
        if ($minutesLeft <= 30) return 'warning';
        return 'normal';
    }

    /**
     * Статус доставки на русском
     */
    private function getDeliveryStatusLabel(?string $status): string
    {
        return match($status) {
            'pending' => 'Новый',
            'preparing' => 'Готовится',
            'ready' => 'Готов',
            'picked_up' => 'Забран',
            'in_transit' => 'В пути',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $status ?? 'Неизвестно',
        };
    }

    /**
     * Цвет статуса доставки
     */
    private function getDeliveryStatusColor(?string $status): string
    {
        return match($status) {
            'pending' => '#3B82F6',    // Синий
            'preparing' => '#F59E0B',  // Жёлтый
            'ready' => '#10B981',      // Зелёный
            'picked_up' => '#8B5CF6',  // Фиолетовый
            'in_transit' => '#8B5CF6', // Фиолетовый
            'delivered' => '#6B7280',  // Серый
            'cancelled' => '#EF4444',  // Красный
            default => '#6B7280',
        };
    }

    /**
     * Статистика доставки
     */
    private function getDeliveryStats(int $restaurantId): array
    {
        $today = TimeHelper::today($restaurantId);

        $base = Order::where('restaurant_id', $restaurantId)
            ->whereIn('type', ['delivery', 'pickup'])
            ->whereDate('created_at', $today);

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('delivery_status', 'pending')->count(),
            'preparing' => (clone $base)->whereIn('delivery_status', ['preparing', 'ready'])->count(),
            'in_transit' => (clone $base)->whereIn('delivery_status', ['picked_up', 'in_transit'])->count(),
            'delivered' => (clone $base)->where('delivery_status', 'delivered')->count(),
            'cancelled' => (clone $base)->where('delivery_status', 'cancelled')->count(),
        ];
    }

    // ===== УМНОЕ НАЗНАЧЕНИЕ КУРЬЕРА =====

    /**
     * Получить рекомендацию лучшего курьера для заказа
     *
     * @param Order $order
     * @param CourierAssignmentService $assignmentService
     * @return JsonResponse
     */
    public function suggestCourier(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        if ($order->type !== 'delivery') {
            return response()->json([
                'success' => false,
                'message' => 'Рекомендации курьера доступны только для доставки',
            ], 400);
        }

        $result = $assignmentService->findBestCourier($order, includeScores: true);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступных курьеров',
                'couriers' => [],
            ]);
        }

        // Форматируем данные курьеров для фронтенда
        $couriers = collect($result['all_couriers'] ?? [])->map(function ($item) use ($result) {
            $courier = $item['courier'];
            return [
                'id' => $courier->id,
                'user_id' => $courier->user_id,
                'name' => $courier->name,
                'phone' => $courier->phone,
                'transport' => $courier->transport,
                'transport_icon' => $courier->transport_icon,
                'status' => $courier->status,
                'active_orders' => $item['active_orders'],
                'distance' => round($item['distance'], 1),
                'eta_minutes' => $item['eta_minutes'],
                'score' => round($item['total_score']),
                'is_recommended' => $courier->id === $result['courier']->id,
            ];
        });

        return response()->json([
            'success' => true,
            'recommended' => [
                'id' => $result['courier']->id,
                'user_id' => $result['courier']->user_id,
                'name' => $result['courier']->name,
                'phone' => $result['courier']->phone,
                'transport' => $result['courier']->transport,
                'transport_icon' => $result['courier']->transport_icon,
                'score' => $result['score'],
                'eta_minutes' => $result['eta'],
                'distance' => $result['distance'],
                'reason' => $result['reason'],
            ],
            'couriers' => $couriers,
        ]);
    }

    /**
     * Получить рейтинг курьеров для заказа
     *
     * @param Order $order
     * @param CourierAssignmentService $assignmentService
     * @return JsonResponse
     */
    public function rankedCouriers(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        $ranked = $assignmentService->getRankedCouriers($order);

        return response()->json([
            'success' => true,
            'data' => $ranked->map(function ($item) {
                $courier = $item['courier'];
                return [
                    'id' => $courier->id,
                    'user_id' => $courier->user_id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'transport' => $courier->transport,
                    'transport_icon' => $courier->transport_icon,
                    'status' => $courier->status,
                    'active_orders' => $item['active_orders'],
                    'distance' => $item['distance'],
                    'eta_minutes' => $item['eta'],
                    'score' => $item['score'],
                    'recommended' => $item['recommended'] ?? false,
                ];
            }),
        ]);
    }

    /**
     * Автоматически назначить лучшего курьера
     *
     * @param Order $order
     * @param CourierAssignmentService $assignmentService
     * @return JsonResponse
     */
    public function autoAssignCourier(Order $order, CourierAssignmentService $assignmentService): JsonResponse
    {
        if ($order->type !== 'delivery') {
            return response()->json([
                'success' => false,
                'message' => 'Автоназначение доступно только для доставки',
            ], 400);
        }

        if ($order->courier_id) {
            return response()->json([
                'success' => false,
                'message' => 'Курьер уже назначен',
            ], 400);
        }

        $success = $assignmentService->autoAssign($order);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось назначить курьера. Нет доступных курьеров.',
            ], 400);
        }

        $order->refresh();
        $order->load('courier');

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен автоматически',
            'data' => [
                'order_id' => $order->id,
                'courier_id' => $order->courier_id,
                'courier_name' => $order->courier?->name,
            ],
        ]);
    }

    // ===== ПРОБЛЕМЫ ДОСТАВКИ =====

    /**
     * Список проблем доставки
     */
    public function problems(Request $request): JsonResponse
    {
        $query = DeliveryProblem::with(['deliveryOrder', 'courier', 'resolvedBy'])
            ->orderByDesc('created_at');

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Только нерешённые
        if ($request->boolean('unresolved')) {
            $query->unresolved();
        }

        // Только за сегодня
        if ($request->boolean('today')) {
            $query->today();
        }

        $problems = $query->limit($request->input('limit', 50))->get();

        // Статистика
        $stats = [
            'open' => DeliveryProblem::open()->count(),
            'in_progress' => DeliveryProblem::where('status', 'in_progress')->count(),
            'resolved_today' => DeliveryProblem::where('status', 'resolved')->today()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $problems,
            'stats' => $stats,
        ]);
    }

    /**
     * Создать проблему доставки
     */
    public function createProblem(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:customer_unavailable,wrong_address,door_locked,payment_issue,damaged_item,other',
            'description' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Сохраняем фото если есть
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('delivery-problems', 'public');
        }

        // Определяем курьера
        $courierId = null;
        if (auth()->user()?->is_courier) {
            $courier = Courier::where('user_id', auth()->id())->first();
            $courierId = $courier?->id;
        }

        $problem = DeliveryProblem::create([
            'delivery_order_id' => $order->id,
            'courier_id' => $courierId ?? $order->courier_id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'photo_path' => $photoPath,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'status' => 'open',
        ]);

        $problem->load(['deliveryOrder', 'courier']);

        // Broadcast: Новая проблема
        RealtimeEvent::dispatch('delivery', 'delivery_problem_created', [
            'problem_id' => $problem->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? $order->daily_number,
            'type' => $problem->type,
            'type_label' => $problem->type_label,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Проблема зарегистрирована',
            'data' => $problem,
        ], 201);
    }

    /**
     * Решить проблему
     */
    public function resolveProblem(Request $request, DeliveryProblem $problem): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string|max:1000',
        ]);

        $problem->resolve($validated['resolution'], auth()->id());

        // Broadcast
        RealtimeEvent::dispatch('delivery', 'delivery_problem_resolved', [
            'problem_id' => $problem->id,
            'order_id' => $problem->delivery_order_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Проблема решена',
            'data' => $problem->fresh(['deliveryOrder', 'courier', 'resolvedBy']),
        ]);
    }

    /**
     * Отменить проблему
     */
    public function cancelProblem(DeliveryProblem $problem): JsonResponse
    {
        $problem->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Проблема отменена',
        ]);
    }

    // ===== КАРТА КУРЬЕРОВ =====

    /**
     * Данные для карты курьеров
     */
    public function mapData(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Курьеры с позициями
        $couriers = Courier::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['available', 'busy'])
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get()
            ->map(function ($courier) {
                $activeOrders = Order::where('courier_id', $courier->user_id)
                    ->whereIn('type', ['delivery', 'pickup'])
                    ->whereIn('delivery_status', ['picked_up', 'in_transit'])
                    ->count();

                return [
                    'id' => $courier->id,
                    'user_id' => $courier->user_id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'status' => $courier->status,
                    'transport' => $courier->transport,
                    'transport_icon' => $courier->transport_icon,
                    'lat' => (float) $courier->current_lat,
                    'lng' => (float) $courier->current_lng,
                    'location_updated_at' => $courier->location_updated_at,
                    'active_orders' => $activeOrders,
                    'today_orders' => $courier->today_orders ?? 0,
                    'today_earnings' => $courier->today_earnings ?? 0,
                ];
            });

        // Активные заказы доставки с координатами
        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereIn('type', ['delivery', 'pickup'])
            ->whereIn('delivery_status', ['pending', 'preparing', 'ready', 'picked_up', 'in_transit'])
            ->whereNotNull('delivery_latitude')
            ->whereNotNull('delivery_longitude')
            ->with(['courier'])
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number ?? $order->daily_number,
                    'status' => $order->delivery_status,
                    'status_label' => $this->getDeliveryStatusLabel($order->delivery_status),
                    'status_color' => $this->getDeliveryStatusColor($order->delivery_status),
                    'lat' => (float) $order->delivery_latitude,
                    'lng' => (float) $order->delivery_longitude,
                    'address' => $order->delivery_address,
                    'total' => $order->total,
                    'courier_id' => $order->courier_id,
                    'courier_name' => $order->courier?->name,
                    'created_at' => $order->created_at,
                ];
            });

        // Зоны доставки
        $zones = DeliveryZone::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'color' => $zone->color ?? '#3B82F6',
                    'min_distance' => $zone->min_distance,
                    'max_distance' => $zone->max_distance,
                    'polygon' => $zone->polygon,
                ];
            });

        // Координаты ресторана - из настроек в базе или из config
        $restaurantModel = \App\Models\Restaurant::find($restaurantId);
        $yandexSettings = $restaurantModel?->getSetting('yandex', []) ?? [];
        $restaurant = [
            'lat' => (float) ($yandexSettings['restaurant_lat'] ?? config('services.yandex.restaurant_lat', 55.7558)),
            'lng' => (float) ($yandexSettings['restaurant_lng'] ?? config('services.yandex.restaurant_lng', 37.6173)),
            'name' => config('app.name', 'Ресторан'),
        ];

        // API ключ для JavaScript из настроек или config
        $yandexApiKey = $yandexSettings['api_key'] ?? config('services.yandex.js_api_key');

        return response()->json([
            'success' => true,
            'data' => [
                'couriers' => $couriers,
                'orders' => $orders,
                'zones' => $zones,
                'restaurant' => $restaurant,
                'yandex_api_key' => $yandexApiKey,
            ],
        ]);
    }
}
