<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Table;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    /**
     * Получить план зала (зоны со столами)
     */
    public function floorPlan(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $zones = Zone::with(['tables' => function ($query) {
                $query->where('is_active', true)
                    ->with(['activeOrder.items']);
            }])
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Статистика столов
        $stats = [
            'total' => Table::where('restaurant_id', $restaurantId)->where('is_active', true)->count(),
            'free' => Table::where('restaurant_id', $restaurantId)->where('is_active', true)->where('status', 'free')->count(),
            'occupied' => Table::where('restaurant_id', $restaurantId)->where('is_active', true)->where('status', 'occupied')->count(),
            'reserved' => Table::where('restaurant_id', $restaurantId)->where('is_active', true)->where('status', 'reserved')->count(),
            'bill' => Table::where('restaurant_id', $restaurantId)->where('is_active', true)->where('status', 'bill')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'zones' => $zones,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Получить все зоны
     */
    public function zones(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $zones = Zone::withCount('tables')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * Создать зону
     */
    public function storeZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $zone = Zone::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#3B82F6',
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Зона создана',
            'data' => $zone,
        ], 201);
    }

    /**
     * Обновить зону
     */
    public function updateZone(Request $request, Zone $zone): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Зона обновлена',
            'data' => $zone,
        ]);
    }

    /**
     * Удалить зону
     */
    public function destroyZone(Zone $zone): JsonResponse
    {
        // Проверить что нет активных заказов на столах этой зоны
        $activeOrders = Order::whereHas('table', function ($q) use ($zone) {
            $q->where('zone_id', $zone->id);
        })->active()->exists();

        if ($activeOrders) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно удалить зону с активными заказами',
            ], 400);
        }

        $zone->tables()->delete();
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Зона удалена',
        ]);
    }

    /**
     * Получить все столы
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $query = Table::with(['zone', 'activeOrder'])
            ->where('restaurant_id', $restaurantId);

        // Фильтр по зоне
        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Только активные
        if ($request->boolean('active', true)) {
            $query->where('is_active', true);
        }

        $tables = $query->orderBy('number')->get();

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    /**
     * Получить стол по ID
     */
    public function show(Table $table): JsonResponse
    {
        $table->load(['zone', 'activeOrder.items.dish', 'activeOrder.customer']);

        return response()->json([
            'success' => true,
            'data' => $table,
        ]);
    }

    /**
     * Создать стол
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'number' => 'required|string|max:10',
            'name' => 'nullable|string|max:50',
            'seats' => 'required|integer|min:1|max:50',
            'min_order' => 'nullable|numeric|min:0',
            'shape' => 'nullable|in:round,square,rectangle,oval',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'width' => 'nullable|integer|min:20',
            'height' => 'nullable|integer|min:20',
            'rotation' => 'nullable|integer|min:0|max:360',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $table = Table::create([
            'restaurant_id' => $restaurantId,
            'zone_id' => $validated['zone_id'],
            'number' => $validated['number'],
            'name' => $validated['name'] ?? null,
            'seats' => $validated['seats'],
            'min_order' => $validated['min_order'] ?? 0,
            'shape' => $validated['shape'] ?? 'square',
            'position_x' => $validated['position_x'] ?? 0,
            'position_y' => $validated['position_y'] ?? 0,
            'width' => $validated['width'] ?? 80,
            'height' => $validated['height'] ?? 80,
            'rotation' => $validated['rotation'] ?? 0,
            'status' => 'free',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Стол создан',
            'data' => $table->load('zone'),
        ], 201);
    }

    /**
     * Обновить стол
     */
    public function update(Request $request, Table $table): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'sometimes|exists:zones,id',
            'number' => 'sometimes|string|max:10',
            'name' => 'nullable|string|max:50',
            'seats' => 'sometimes|integer|min:1|max:50',
            'min_order' => 'nullable|numeric|min:0',
            'shape' => 'nullable|in:round,square,rectangle,oval',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'width' => 'nullable|integer|min:20',
            'height' => 'nullable|integer|min:20',
            'rotation' => 'nullable|integer|min:0|max:360',
            'is_active' => 'nullable|boolean',
        ]);

        $table->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Стол обновлён',
            'data' => $table->fresh('zone'),
        ]);
    }

    /**
     * Удалить стол
     */
    public function destroy(Table $table): JsonResponse
    {
        // Проверить что нет активных заказов
        if ($table->activeOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно удалить стол с активным заказом',
            ], 400);
        }

        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Стол удалён',
        ]);
    }

    /**
     * Изменить статус стола
     */
    public function updateStatus(Request $request, Table $table): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:free,occupied,reserved,bill',
        ]);

        $table->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
            'data' => $table,
        ]);
    }

    /**
     * Сохранить расположение столов (массовое обновление позиций)
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tables' => 'required|array',
            'tables.*.id' => 'required|exists:tables,id',
            'tables.*.position_x' => 'required|integer',
            'tables.*.position_y' => 'required|integer',
            'tables.*.width' => 'nullable|integer',
            'tables.*.height' => 'nullable|integer',
            'tables.*.rotation' => 'nullable|integer',
        ]);

        foreach ($validated['tables'] as $tableData) {
            Table::where('id', $tableData['id'])->update([
                'position_x' => $tableData['position_x'],
                'position_y' => $tableData['position_y'],
                'width' => $tableData['width'] ?? null,
                'height' => $tableData['height'] ?? null,
                'rotation' => $tableData['rotation'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Расположение сохранено',
        ]);
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        if ($request->has('restaurant_id')) {
            return $request->restaurant_id;
        }
        if (auth()->check() && auth()->user()->restaurant_id) {
            return auth()->user()->restaurant_id;
        }
        return 1;
    }
}
