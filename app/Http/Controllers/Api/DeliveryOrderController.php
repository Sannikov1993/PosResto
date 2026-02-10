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
use App\Models\RealtimeEvent;
use App\Models\BonusSetting;
use App\Models\BonusTransaction;
use App\Models\LoyaltySetting;
use App\Services\GeocodingService;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Enums\PaymentStatus;

/**
 * Контроллер заказов доставки
 *
 * Методы: orders, createOrder, showOrder, updateStatus, assignCourier
 */
class DeliveryOrderController extends Controller
{
    use BroadcastsEvents;
    use Traits\ResolvesRestaurantId;

    /**
     * Список заказов доставки с фильтрами
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['items.dish', 'customer.loyaltyLevel', 'courier', 'loyaltyLevel'])
            ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
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
            'type' => 'nullable|in:delivery,pickup',
            'delivery_address' => $request->input('type') === 'pickup' ? 'nullable|string|max:500' : 'required|string|max:500',
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
            'applied_discounts' => 'nullable|array',
            // Бонусы
            'bonus_used' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Создаём или находим клиента
        $customerId = $validated['customer_id'] ?? null;
        if (!$customerId && $validated['phone']) {
            $normalizedPhone = preg_replace('/[^0-9]/', '', $validated['phone']);

            $customer = Customer::where('restaurant_id', $restaurantId)
                ->byPhone($normalizedPhone)
                ->first();

            if ($customer) {
                $customerId = $customer->id;
                if (!empty($validated['customer_name']) && $validated['customer_name'] !== 'Клиент') {
                    $customer->update(['name' => $validated['customer_name']]);
                }
            } else {
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
            $zone = $geocoding->detectZone(
                $validated['delivery_latitude'],
                $validated['delivery_longitude'],
                $restaurantId
            );
            if ($zone) {
                $deliveryZoneId = $zone->id;
                $deliveryFee = $zone->delivery_fee;
                $restaurantCoords = $geocoding->getRestaurantCoordinates();
                if ($restaurantCoords['lat'] && $restaurantCoords['lng']) {
                    $deliveryDistance = $geocoding->calculateDistance(
                        $restaurantCoords['lat'], $restaurantCoords['lng'],
                        $validated['delivery_latitude'], $validated['delivery_longitude']
                    );
                }
            }
        } elseif (!empty($validated['delivery_address'])) {
            $geoResult = $geocoding->geocodeWithZone($validated['delivery_address'], $restaurantId);
            if ($geoResult['success'] && $geoResult['zone']) {
                $deliveryZoneId = $geoResult['zone']->id;
                $deliveryFee = $geoResult['delivery_cost'];
                $deliveryDistance = $geoResult['distance'];
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
            'type' => $validated['type'] ?? OrderType::DELIVERY->value,
            'table_id' => null,
            'customer_id' => $customerId,
            'phone' => $validated['phone'],
            'status' => OrderStatus::CONFIRMED->value,
            'payment_status' => PaymentStatus::PENDING->value,
            'payment_method' => $validated['payment_method'],
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
            'is_asap' => $validated['is_asap'] ?? true,
            'desired_delivery_time' => $validated['desired_delivery_time'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? $validated['desired_delivery_time'] ?? null,
            'delivery_status' => $validated['delivery_status'] ?? 'pending',
            'prepayment' => $validated['prepayment'] ?? 0,
            'prepayment_method' => $validated['prepayment_method'] ?? null,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'applied_discounts' => $validated['applied_discounts'] ?? null,
            'subtotal' => 0,
            'delivery_fee' => $deliveryFee,
            'total' => 0,
            'change_from' => $validated['change_from'] ?? null,
            'comment' => $validated['notes'] ?? null,
        ]);

        // Добавляем позиции
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $dish = Dish::forRestaurant($restaurantId)->find($item['dish_id']);
            if (!$dish) continue;

            $itemTotal = $dish->price * $item['quantity'];
            $subtotal += $itemTotal;

            OrderItem::create([
                'restaurant_id' => $restaurantId,
                'order_id' => $order->id,
                'dish_id' => $dish->id,
                'name' => $dish->name,
                'price' => $dish->price,
                'quantity' => $item['quantity'],
                'total' => $itemTotal,
                'modifiers' => $item['modifiers'] ?? null,
                'notes' => $item['notes'] ?? null,
                'status' => 'cooking',
            ]);
        }

        // Проверяем бесплатную доставку
        if ($deliveryZoneId) {
            $zone = DeliveryZone::forRestaurant($restaurantId)->find($deliveryZoneId);
            if ($zone) {
                $deliveryFee = $zone->getDeliveryFee($subtotal);
            }
        }

        // Вычисляем скидку лояльности если есть клиент
        $loyaltyDiscountAmount = 0;
        $loyaltyLevelId = null;

        if ($customerId) {
            $customer = Customer::forRestaurant($restaurantId)->with('loyaltyLevel')->find($customerId);
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
            $customer = $customer ?? Customer::forRestaurant($restaurantId)->find($customerId);
            $bonusSetting = BonusSetting::where('restaurant_id', $restaurantId)->first();

            if (!$bonusSetting || !$bonusSetting->is_enabled) {
                $bonusUsed = 0;
            } else {
                if ($bonusSetting->spend_rate) {
                    $maxByRate = $total * ($bonusSetting->spend_rate / 100);
                    $bonusUsed = min($bonusUsed, $maxByRate);
                }
                if ($customer) {
                    $bonusUsed = min($bonusUsed, $customer->bonus_balance ?? 0);
                }
                $bonusUsed = min($bonusUsed, $total);
            }
        }

        // Итого к оплате деньгами
        $totalToPay = max(0, $total - $bonusUsed);

        // Определяем статус оплаты на основе предоплаты и бонусов
        $prepayment = floatval($validated['prepayment'] ?? 0);
        $paymentStatus = PaymentStatus::PENDING->value;
        $totalPaid = $prepayment + $bonusUsed;
        if ($totalPaid > 0) {
            $paymentStatus = $totalPaid >= $total ? PaymentStatus::PAID->value : PaymentStatus::PARTIAL->value;
        }

        // Логируем для отладки
        \Log::info('DeliveryOrderController: Payment calculation', [
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
            $customer = $customer ?? Customer::forRestaurant($restaurantId)->find($customerId);
            if ($customer) {
                $customer->decrement('bonus_balance', $bonusUsed);

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

        // Broadcast через Reverb: Новый заказ доставки
        $this->broadcastOrderCreated($order);

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
                $updateData['status'] = OrderStatus::COOKING->value;
                $order->items()->update(['status' => 'cooking']);
                break;
            case 'ready':
                $updateData['cooking_finished_at'] = now();
                $updateData['ready_at'] = now();
                $updateData['status'] = OrderStatus::READY->value;
                $order->items()->update([
                    'status' => 'ready',
                    'cooking_finished_at' => now(),
                ]);
                break;
            case 'picked_up':
                $updateData['picked_up_at'] = now();
                break;
            case 'in_transit':
                $updateData['status'] = OrderStatus::DELIVERING->value;
                break;
            case 'delivered':
                $updateData['delivered_at'] = now();
                $updateData['completed_at'] = now();
                $updateData['status'] = OrderStatus::COMPLETED->value;
                $order->items()->update(['status' => 'served']);
                // Освобождаем курьера
                if ($order->courier_id) {
                    $deliveryFee = (float) ($order->delivery_fee ?? 150);
                    User::where('id', $order->courier_id)->update([
                        'courier_today_orders' => DB::raw('courier_today_orders + 1'),
                    ]);
                    User::where('id', $order->courier_id)->increment('courier_today_earnings', $deliveryFee);
                }
                // Начисляем бонусы клиенту
                if ($order->customer_id) {
                    try {
                        $bonusSettings = BonusSetting::getForRestaurant($order->restaurant_id);
                        if ($bonusSettings->is_enabled) {
                            $order->load('customer');
                            if ($order->customer) {
                                $effectiveRate = $bonusSettings->getEffectiveEarnRate($order->customer);
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
                $updateData['status'] = OrderStatus::CANCELLED->value;
                $order->items()->update(['status' => 'cancelled']);
                break;
        }

        $order->update($updateData);

        // Broadcast через Reverb
        $this->broadcastDeliveryStatusChanged($order->fresh(), $newStatus);

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

        $restaurantId = $order->restaurant_id;

        User::forRestaurant($restaurantId)->where('id', $validated['courier_id'])->update([
            'courier_status' => 'busy',
        ]);

        $courier = User::forRestaurant($restaurantId)->find($validated['courier_id']);

        // Broadcast через Reverb
        $this->broadcastCourierAssigned($order, $courier);

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен',
            'data' => $this->enrichOrderData($order->fresh(['items.dish', 'customer', 'courier'])),
        ]);
    }

    // ===== HELPERS =====

    /**
     * Обогатить данные заказа
     */
    private function enrichOrderData(Order $order): array
    {
        $data = $order->toArray();

        $data['delivery_status_label'] = $this->getDeliveryStatusLabel($order->delivery_status);
        $data['delivery_status_color'] = $this->getDeliveryStatusColor($order->delivery_status);

        $data['payment_status_label'] = match($order->payment_status) {
            PaymentStatus::PAID->value => 'Оплачен',
            PaymentStatus::PENDING->value => 'Не оплачен',
            PaymentStatus::PARTIAL->value => 'Частично',
            PaymentStatus::REFUNDED->value => 'Возврат',
            default => $order->payment_status,
        };

        $data['payment_method_label'] = match($order->payment_method) {
            'cash' => 'Наличными',
            'card' => 'Картой курьеру',
            'online' => 'Онлайн',
            default => $order->payment_method,
        };

        $data['wait_time_minutes'] = $order->created_at
            ? Carbon::parse($order->created_at)->diffInMinutes(now())
            : 0;

        $data['urgency'] = $this->calculateUrgency($order);

        if ($order->picked_up_at && in_array($order->delivery_status, ['in_transit'])) {
            $data['in_transit_minutes'] = Carbon::parse($order->picked_up_at)->diffInMinutes(now());
        }

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
            'pending' => '#3B82F6',
            'preparing' => '#F59E0B',
            'ready' => '#10B981',
            'picked_up' => '#8B5CF6',
            'in_transit' => '#8B5CF6',
            'delivered' => '#6B7280',
            'cancelled' => '#EF4444',
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
            ->whereIn('type', [OrderType::DELIVERY->value, OrderType::PICKUP->value])
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
}
