<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KitchenStationController extends Controller
{
    /**
     * Получить список цехов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $stations = KitchenStation::withCount('dishes')
            ->where('restaurant_id', $restaurantId)
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stations,
        ]);
    }

    /**
     * Получить только активные цеха
     */
    public function active(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $stations = KitchenStation::where('restaurant_id', $restaurantId)
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stations,
        ]);
    }

    /**
     * Создать цех
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'slug' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'notification_sound' => 'nullable|string|max:30|in:bell,chime,ding,kitchen,alert,gong',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_bar' => 'nullable|boolean',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Генерируем slug если не указан
        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'station-' . time();
        }

        // Проверяем уникальность slug
        $baseSlug = $slug;
        $counter = 1;
        while (KitchenStation::where('restaurant_id', $restaurantId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $station = KitchenStation::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? '#6366F1',
            'description' => $validated['description'] ?? null,
            'notification_sound' => $validated['notification_sound'] ?? 'bell',
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'is_bar' => $validated['is_bar'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Цех создан',
            'data' => $station,
        ], 201);
    }

    /**
     * Получить цех по ID
     */
    public function show(KitchenStation $kitchenStation): JsonResponse
    {
        $kitchenStation->loadCount('dishes');

        return response()->json([
            'success' => true,
            'data' => $kitchenStation,
        ]);
    }

    /**
     * Обновить цех
     */
    public function update(Request $request, KitchenStation $kitchenStation): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'slug' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'notification_sound' => 'nullable|string|max:30|in:bell,chime,ding,kitchen,alert,gong',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_bar' => 'nullable|boolean',
        ]);

        // Если обновляется slug, проверяем уникальность
        if (isset($validated['slug'])) {
            $existingStation = KitchenStation::where('restaurant_id', $kitchenStation->restaurant_id)
                ->where('slug', $validated['slug'])
                ->where('id', '!=', $kitchenStation->id)
                ->first();

            if ($existingStation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Цех с таким slug уже существует',
                ], 422);
            }
        }

        $kitchenStation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Цех обновлён',
            'data' => $kitchenStation->fresh(),
        ]);
    }

    /**
     * Удалить цех
     */
    public function destroy(KitchenStation $kitchenStation): JsonResponse
    {
        // Блюда с этим цехом получат kitchen_station_id = NULL (nullOnDelete)
        $kitchenStation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Цех удалён',
        ]);
    }

    /**
     * Переключить активность цеха
     */
    public function toggle(KitchenStation $kitchenStation): JsonResponse
    {
        $kitchenStation->update(['is_active' => !$kitchenStation->is_active]);

        return response()->json([
            'success' => true,
            'message' => $kitchenStation->is_active ? 'Цех активирован' : 'Цех деактивирован',
            'data' => $kitchenStation,
        ]);
    }

    /**
     * Обновить порядок цехов
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stations' => 'required|array',
            'stations.*.id' => 'required|exists:kitchen_stations,id',
            'stations.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['stations'] as $stationData) {
            KitchenStation::where('id', $stationData['id'])
                ->update(['sort_order' => $stationData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Порядок обновлён',
        ]);
    }

    /**
     * Получить барную станцию (если настроена)
     */
    public function getBar(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $barStation = KitchenStation::where('restaurant_id', $restaurantId)
            ->where('is_bar', true)
            ->where('is_active', true)
            ->first();

        if (!$barStation) {
            return response()->json([
                'success' => false,
                'message' => 'Бар не настроен',
                'has_bar' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'has_bar' => true,
            'data' => $barStation,
        ]);
    }

    /**
     * Получить позиции бара (из заказов)
     */
    public function getBarOrders(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Находим барную станцию
        $barStation = KitchenStation::where('restaurant_id', $restaurantId)
            ->where('is_bar', true)
            ->where('is_active', true)
            ->first();

        if (!$barStation) {
            return response()->json([
                'success' => false,
                'message' => 'Бар не настроен',
                'data' => [],
            ]);
        }

        // Получаем заказы с позициями бара
        $orders = \App\Models\Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['confirmed', 'cooking', 'ready'])
            ->whereDate('created_at', today())
            ->whereHas('items', function ($q) use ($barStation) {
                $q->whereHas('dish', fn($dq) => $dq->where('kitchen_station_id', $barStation->id))
                  ->whereIn('status', ['cooking', 'ready']);
            })
            ->orderBy('created_at')
            ->get();

        // Фильтруем только позиции бара и форматируем
        $barItems = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->dish && $item->dish->kitchen_station_id === $barStation->id) {
                    if (in_array($item->status, ['cooking', 'ready'])) {
                        $barItems[] = [
                            'id' => $item->id,
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'table' => $order->table ? [
                                'id' => $order->table->id,
                                'name' => $order->table->name,
                                'number' => $order->table->number,
                            ] : null,
                            'waiter' => $order->waiter ? [
                                'id' => $order->waiter->id,
                                'name' => $order->waiter->name,
                            ] : null,
                            'dish_id' => $item->dish_id,
                            'dish_name' => $item->dish->name ?? $item->name,
                            'quantity' => $item->quantity,
                            'status' => $item->status,
                            'cooking_started_at' => $item->cooking_started_at,
                            'notes' => $item->notes,
                            'created_at' => $item->created_at,
                            'order_type' => $order->type,
                        ];
                    }
                }
            }
        }

        // Сортируем: сначала новые (без cooking_started_at), потом в работе
        usort($barItems, function ($a, $b) {
            $aStarted = $a['cooking_started_at'] ? 1 : 0;
            $bStarted = $b['cooking_started_at'] ? 1 : 0;
            if ($aStarted !== $bStarted) return $aStarted - $bStarted;
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        return response()->json([
            'success' => true,
            'data' => $barItems,
            'station' => $barStation,
            'counts' => [
                'new' => count(array_filter($barItems, fn($i) => !$i['cooking_started_at'] && $i['status'] === 'cooking')),
                'in_progress' => count(array_filter($barItems, fn($i) => $i['cooking_started_at'] && $i['status'] === 'cooking')),
                'ready' => count(array_filter($barItems, fn($i) => $i['status'] === 'ready')),
            ],
        ]);
    }

    /**
     * Обновить статус позиции бара
     */
    public function updateBarItemStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:order_items,id',
            'status' => 'required|in:cooking,ready',
        ]);

        $item = \App\Models\OrderItem::findOrFail($validated['item_id']);

        if ($validated['status'] === 'cooking' && !$item->cooking_started_at) {
            // Взять в работу
            $item->update(['cooking_started_at' => now()]);
        } elseif ($validated['status'] === 'ready') {
            // Готово
            $item->update([
                'status' => 'ready',
                'cooking_finished_at' => now(),
            ]);

            // Проверяем, все ли позиции заказа готовы
            $order = $item->order;
            $hasCookingItems = $order->items()->where('status', 'cooking')->exists();
            if (!$hasCookingItems) {
                $order->update(['status' => 'ready']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
        ]);
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        if ($request->has('restaurant_id') && $user) {
            if ($user->isSuperAdmin()) {
                return (int) $request->restaurant_id;
            }
            $restaurant = \App\Models\Restaurant::where('id', $request->restaurant_id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
            if ($restaurant) {
                return $restaurant->id;
            }
        }

        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        abort(401, 'Требуется авторизация');
    }
}
