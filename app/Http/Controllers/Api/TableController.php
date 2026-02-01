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
     * Если передан zone_id - возвращает данные для конкретной зоны (для редактора)
     */
    public function floorPlan(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $zoneId = $request->input('zone_id');

        // Если запрошена конкретная зона - возвращаем данные для редактора
        if ($zoneId) {
            $zone = Zone::with(['tables' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->where('id', $zoneId)
                ->where('restaurant_id', $restaurantId)
                ->first();

            if (!$zone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Зона не найдена',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'zone' => $zone,
                    'tables' => $zone->tables,
                    'layout' => $zone->floor_layout, // JSON с декором и прочими объектами
                ],
            ]);
        }

        // Иначе возвращаем все зоны со столами
        $zones = Zone::with(['tables' => function ($query) {
                $query->where('is_active', true)
                    ->with(['activeOrder.items', 'nextReservation']);
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
        $reservationDate = $request->input('reservation_date', now()->format('Y-m-d'));

        $query = Table::with(['zone', 'activeOrder'])
            ->where('restaurant_id', $restaurantId);

        // Загружаем бронирования на конкретную дату (где стол - основной)
        $query->with(['reservations' => function ($q) use ($reservationDate) {
            $q->whereDate('date', $reservationDate)
              ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
              ->orderBy('time_from');
        }]);

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

        // Загружаем все брони с linked_table_ids на эту дату
        $linkedReservations = \App\Models\Reservation::whereDate('date', $reservationDate)
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->whereNotNull('linked_table_ids')
            ->where('linked_table_ids', '!=', '[]')
            ->orderBy('time_from')
            ->get();

        // Добавляем next_reservation и all_reservations из загруженных бронирований
        $tables->each(function ($table) use ($reservationDate, $linkedReservations) {
            $currentTime = now()->format('H:i');
            $isToday = $reservationDate === now()->format('Y-m-d');

            // Все прямые брони на выбранную дату (исключая отменённые)
            $directReservations = $table->reservations
                ->filter(function ($r) use ($isToday, $currentTime) {
                    // Исключаем отменённые и завершённые брони
                    if (in_array($r->status, ['cancelled', 'completed', 'no_show'])) {
                        return false;
                    }
                    return !$isToday || $r->time_from >= $currentTime;
                });

            // Добавляем брони где этот стол в linked_table_ids
            $linkedForTable = $linkedReservations->filter(function ($r) use ($table, $isToday, $currentTime) {
                $linkedIds = $r->linked_table_ids ?? [];
                $isLinked = in_array($table->id, $linkedIds);
                $isValid = !$isToday || $r->time_from >= $currentTime;
                return $isLinked && $isValid;
            });

            // Объединяем и сортируем по времени
            $allReservations = $directReservations->merge($linkedForTable)
                ->unique('id')
                ->sortBy('time_from')
                ->values();

            // Ближайшая бронь
            $table->next_reservation = $allReservations->first();
            $table->all_reservations = $allReservations;
            $table->reservations_count = $allReservations->count();
            unset($table->reservations);
        });

        // Загружаем все активные заказы с linked_table_ids
        $linkedOrders = \App\Models\Order::whereNotNull('linked_table_ids')
            ->where('linked_table_ids', '!=', '[]')
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->get();

        // Добавляем сумму связанных заказов к столам
        $tables->each(function ($table) use ($linkedOrders) {
            // Ищем заказ где этот стол в linked_table_ids
            $linkedOrder = $linkedOrders->first(function ($order) use ($table) {
                $linkedIds = $order->linked_table_ids ?? [];
                return in_array($table->id, $linkedIds);
            });

            if ($linkedOrder) {
                // Если стол в связанном заказе - используем этот заказ
                $table->active_order = $linkedOrder;
                $table->active_orders_total = $linkedOrder->total;
            } else {
                // Иначе используем свой activeOrder
                $table->active_orders_total = $table->activeOrder->total ?? 0;
            }
        });

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
     * Сохранить расположение столов и планировку зала
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'tables' => 'nullable|array',
            'tables.*.id' => 'nullable|integer',
            'tables.*.number' => 'required|string|max:10',
            'tables.*.seats' => 'required|integer|min:1',
            'tables.*.shape' => 'required|in:round,square,rectangle,oval',
            'tables.*.position_x' => 'required|integer',
            'tables.*.position_y' => 'required|integer',
            'tables.*.width' => 'nullable|integer',
            'tables.*.height' => 'nullable|integer',
            'tables.*.rotation' => 'nullable|integer',
            'tables.*.min_order' => 'nullable|numeric|min:0',
            'tables.*.surface_style' => 'nullable|string|max:20',
            'tables.*.chair_style' => 'nullable|string|max:20',
            'layout' => 'nullable|array', // Декор: стены, колонны, диваны и т.д.
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $zone = Zone::forRestaurant($restaurantId)->findOrFail($validated['zone_id']);

        // Обновляем или создаём столы
        $tableIds = [];
        foreach ($validated['tables'] ?? [] as $tableData) {
            if (!empty($tableData['id'])) {
                // Обновляем существующий стол
                $table = Table::forRestaurant($restaurantId)->find($tableData['id']);
                if ($table) {
                    $table->update([
                        'number' => $tableData['number'],
                        'seats' => $tableData['seats'],
                        'shape' => $tableData['shape'],
                        'position_x' => $tableData['position_x'],
                        'position_y' => $tableData['position_y'],
                        'width' => $tableData['width'] ?? 80,
                        'height' => $tableData['height'] ?? 80,
                        'rotation' => $tableData['rotation'] ?? 0,
                        'min_order' => $tableData['min_order'] ?? 0,
                        'surface_style' => $tableData['surface_style'] ?? 'wood',
                        'chair_style' => $tableData['chair_style'] ?? 'wood',
                    ]);
                    $tableIds[] = $table->id;
                }
            } else {
                // Создаём новый стол
                $table = Table::create([
                    'restaurant_id' => $restaurantId,
                    'zone_id' => $zone->id,
                    'number' => $tableData['number'],
                    'seats' => $tableData['seats'],
                    'shape' => $tableData['shape'],
                    'position_x' => $tableData['position_x'],
                    'position_y' => $tableData['position_y'],
                    'width' => $tableData['width'] ?? 80,
                    'height' => $tableData['height'] ?? 80,
                    'rotation' => $tableData['rotation'] ?? 0,
                    'min_order' => $tableData['min_order'] ?? 0,
                    'surface_style' => $tableData['surface_style'] ?? 'wood',
                    'chair_style' => $tableData['chair_style'] ?? 'wood',
                ]);
                $tableIds[] = $table->id;
            }
        }

        // Удаляем столы, которых больше нет в редакторе (для этой зоны)
        Table::where('zone_id', $zone->id)
            ->whereNotIn('id', $tableIds)
            ->delete();

        // Сохраняем декор (стены, колонны и т.д.)
        if (isset($validated['layout'])) {
            $zone->update(['floor_layout' => $validated['layout']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Планировка сохранена',
        ]);
    }

    /**
     * Получить ID ресторана из авторизованного пользователя
     */
    protected function getRestaurantId(Request $request): int
    {
        // Приоритет: явный параметр > пользователь из auth
        if ($request->has('restaurant_id')) {
            // Проверяем что запрошенный ресторан принадлежит тенанту пользователя
            $user = auth()->user();
            if ($user && !$user->isSuperAdmin()) {
                $restaurant = \App\Models\Restaurant::where('id', $request->restaurant_id)
                    ->where('tenant_id', $user->tenant_id)
                    ->first();
                if ($restaurant) {
                    return $restaurant->id;
                }
            } elseif ($user && $user->isSuperAdmin()) {
                return (int) $request->restaurant_id;
            }
        }

        // Берём restaurant_id из авторизованного пользователя
        $user = auth()->user();
        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        // Если пользователь не авторизован - это ошибка (middleware должен был отклонить)
        abort(401, 'Требуется авторизация');
    }
}
