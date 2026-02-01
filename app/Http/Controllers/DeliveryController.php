<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryZone;
use App\Models\Courier;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Customer;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Контроллер модуля доставки (Blade)
 */
class DeliveryController extends Controller
{
    /**
     * Главная страница модуля доставки
     */
    public function index(Request $request): View
    {
        $status = $request->get('status');

        $ordersQuery = DeliveryOrder::with(['customer', 'items', 'courier', 'zone'])
            ->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $ordersQuery->where('status', $status);
        } else {
            // По умолчанию показываем только активные заказы
            $ordersQuery->whereNotIn('status', ['completed', 'cancelled']);
        }

        $orders = $ordersQuery->get();

        // Статистика по статусам
        $statusCounts = DeliveryOrder::select('status', DB::raw('count(*) as count'))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Доступные курьеры
        $couriers = Courier::where('is_active', true)
            ->with('activeOrders')
            ->get();

        // Категории для модалки нового заказа
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Выбранный заказ (первый или по ID)
        $selectedOrderId = $request->get('order_id', $orders->first()?->id);
        $selectedOrder = $selectedOrderId
            ? DeliveryOrder::with(['items.dish', 'customer', 'courier', 'zone', 'history.user'])->find($selectedOrderId)
            : null;

        return view('delivery.index', compact(
            'orders',
            'statusCounts',
            'couriers',
            'categories',
            'selectedOrder'
        ));
    }

    /**
     * Создание нового заказа на доставку
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:delivery,pickup',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_comment' => 'nullable|string',
            'address_street' => 'required_if:type,delivery|nullable|string|max:255',
            'address_house' => 'required_if:type,delivery|nullable|string|max:50',
            'address_apartment' => 'nullable|string|max:20',
            'address_entrance' => 'nullable|string|max:10',
            'address_floor' => 'nullable|string|max:10',
            'address_intercom' => 'nullable|string|max:20',
            'address_comment' => 'nullable|string',
            'deliver_at' => 'nullable|date',
            'delivery_zone_id' => 'nullable|exists:delivery_zones,id',
            'payment_method' => 'required|in:cash,card,online',
            'change_from' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.comment' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Найти или создать клиента
            $customer = Customer::firstOrCreate(
                ['phone' => $validated['customer_phone']],
                ['name' => $validated['customer_name']]
            );

            // Рассчитать стоимость доставки
            $deliveryCost = 0;
            if ($validated['type'] === 'delivery' && !empty($validated['delivery_zone_id'])) {
                $zone = DeliveryZone::find($validated['delivery_zone_id']);
                $deliveryCost = $zone?->delivery_fee ?? 0;
            }

            // Создать заказ
            $order = DeliveryOrder::create([
                'order_number' => DeliveryOrder::generateOrderNumber(),
                'type' => $validated['type'],
                'status' => DeliveryOrder::STATUS_NEW,
                'customer_id' => $customer->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_comment' => $validated['customer_comment'] ?? null,
                'address_street' => $validated['address_street'] ?? null,
                'address_house' => $validated['address_house'] ?? null,
                'address_apartment' => $validated['address_apartment'] ?? null,
                'address_entrance' => $validated['address_entrance'] ?? null,
                'address_floor' => $validated['address_floor'] ?? null,
                'address_intercom' => $validated['address_intercom'] ?? null,
                'address_comment' => $validated['address_comment'] ?? null,
                'delivery_zone_id' => $validated['delivery_zone_id'] ?? null,
                'deliver_at' => $validated['deliver_at'] ?? now()->addMinutes(60),
                'payment_method' => $validated['payment_method'],
                'change_from' => $validated['change_from'] ?? null,
                'delivery_cost' => $deliveryCost,
                'created_by' => auth()->id(),
            ]);

            // Добавить позиции
            $subtotal = 0;
            foreach ($validated['items'] as $itemData) {
                $dish = Dish::find($itemData['product_id']);
                $modifiersPrice = 0;
                $modifiers = [];

                if (!empty($itemData['modifiers'])) {
                    // Логика модификаторов
                    foreach ($itemData['modifiers'] as $mod) {
                        $modifiers[] = [
                            'id' => $mod['id'] ?? 0,
                            'name' => $mod['name'] ?? '',
                            'price' => $mod['price'] ?? 0,
                        ];
                        $modifiersPrice += $mod['price'] ?? 0;
                    }
                }

                $itemTotal = ($dish->price + $modifiersPrice) * $itemData['quantity'];
                $subtotal += $itemTotal;

                DeliveryOrderItem::create([
                    'delivery_order_id' => $order->id,
                    'dish_id' => $dish->id,
                    'product_name' => $dish->name,
                    'price' => $dish->price + $modifiersPrice,
                    'quantity' => $itemData['quantity'],
                    'modifiers' => $modifiers ?: null,
                    'comment' => $itemData['comment'] ?? null,
                    'total' => $itemTotal,
                ]);
            }

            // Обновить итоговые суммы
            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + $deliveryCost - $order->discount,
            ]);

            // Записать в историю
            $order->logHistory('created', null, 'Заказ создан');

            DB::commit();

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'message' => 'Заказ успешно создан',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка создания заказа: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить детали заказа (AJAX)
     */
    public function show(DeliveryOrder $order): JsonResponse
    {
        $order->load(['items.dish', 'customer', 'courier', 'zone', 'history.user']);

        return response()->json($order);
    }

    /**
     * Обновить статус заказа
     */
    public function updateStatus(Request $request, DeliveryOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,cooking,ready,delivering,completed,cancelled',
            'comment' => 'nullable|string',
        ]);

        $oldStatus = $order->status;

        switch ($validated['status']) {
            case 'cooking':
                $order->markAsCooking();
                break;
            case 'ready':
                $order->markAsReady();
                break;
            case 'completed':
                $order->markAsDelivered();
                break;
            case 'cancelled':
                $order->cancel($validated['comment'] ?? null);
                break;
            default:
                $order->update(['status' => $validated['status']]);
                $order->logHistory('status_changed', $oldStatus, $validated['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
            'order' => $order->fresh(['courier', 'zone']),
        ]);
    }

    /**
     * Назначить курьера
     */
    public function assignCourier(Request $request, DeliveryOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => 'required|exists:couriers,id',
        ]);

        $courier = Courier::findOrFail($validated['courier_id']);

        if ($courier->status === 'busy' && $courier->activeOrders()->count() >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Курьер уже занят максимальным количеством заказов',
            ], 422);
        }

        $order->assignCourier($courier);

        return response()->json([
            'success' => true,
            'message' => 'Курьер назначен',
            'order' => $order->fresh(['courier']),
        ]);
    }

    /**
     * Список доступных курьеров
     */
    public function couriers(): JsonResponse
    {
        $couriers = Courier::where('is_active', true)
            ->withCount(['activeOrders'])
            ->get();

        return response()->json($couriers);
    }

    /**
     * Получить товары для модалки
     */
    public function products(Request $request): JsonResponse
    {
        $query = Dish::where('is_active', true);

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->get('popular')) {
            $query->where('is_popular', true);
        }

        $products = $query->with('category')->get();

        return response()->json($products);
    }

    /**
     * Определить зону доставки по адресу
     */
    public function detectZone(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'street' => 'nullable|string',
            'house' => 'nullable|string',
        ]);

        // Формируем полный адрес
        $address = $validated['address'];
        if (!empty($validated['street']) && !empty($validated['house'])) {
            $address = $validated['street'] . ', ' . $validated['house'];
        }

        // Геокодируем адрес и определяем зону
        $result = $geocoding->geocodeWithZone($address);

        if (!$result['success']) {
            // Fallback: возвращаем первую активную зону если геокодирование не настроено
            if (empty(config('services.yandex.geocoder_key'))) {
                $zone = DeliveryZone::where('is_active', true)->first();
                return response()->json([
                    'success' => true,
                    'zone' => $zone,
                    'delivery_cost' => $zone?->delivery_fee ?? 0,
                    'delivery_time' => $zone ? "{$zone->estimated_time} мин" : null,
                    'warning' => 'Геокодирование не настроено, используется зона по умолчанию',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'zone' => null,
                'delivery_cost' => 0,
                'delivery_time' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'zone' => $result['zone'],
            'zone_id' => $result['zone']?->id,
            'delivery_cost' => $result['delivery_cost'],
            'delivery_time' => $result['delivery_time'] ? "{$result['delivery_time']} мин" : null,
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
        ]);

        $suggestions = $geocoding->suggest($validated['query']);

        return response()->json($suggestions);
    }

    /**
     * Поиск клиента по телефону
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        $phone = $request->get('phone');

        if (strlen($phone) < 4) {
            return response()->json([]);
        }

        $customers = Customer::where('phone', 'like', "%{$phone}%")
            ->orWhere('name', 'like', "%{$phone}%")
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    /**
     * Аналитика доставки
     */
    public function analytics(): JsonResponse
    {
        $today = now()->startOfDay();

        $stats = [
            'total_orders' => DeliveryOrder::whereDate('created_at', $today)->count(),
            'completed_orders' => DeliveryOrder::whereDate('created_at', $today)
                ->where('status', 'completed')->count(),
            'cancelled_orders' => DeliveryOrder::whereDate('created_at', $today)
                ->where('status', 'cancelled')->count(),
            'total_revenue' => DeliveryOrder::whereDate('created_at', $today)
                ->where('status', 'completed')->sum('total'),
            'avg_delivery_time' => 45, // TODO: рассчитать реальное
            'active_couriers' => Courier::whereIn('status', ['available', 'busy'])->count(),
        ];

        return response()->json($stats);
    }
}
