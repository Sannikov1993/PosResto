<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WriteOff;
use App\Models\WriteOffItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WriteOffController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Список списаний с фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $dateFrom = $request->input('date_from', Carbon::today()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());
        $type = $request->input('type');

        $query = WriteOff::with(['items', 'user', 'approvedByUser', 'warehouse'])
            ->forRestaurant($restaurantId)
            ->betweenDates($dateFrom, $dateTo)
            ->orderByDesc('created_at');

        if ($type) {
            $query->ofType($type);
        }

        $writeOffs = $query->get()->map(function ($writeOff) {
            return [
                'id' => $writeOff->id,
                'type' => $writeOff->type,
                'type_name' => $writeOff->type_name,
                'amount' => $writeOff->total_amount,
                'description' => $writeOff->description,
                'photo_url' => $writeOff->photo_url,
                'user' => [
                    'id' => $writeOff->user?->id,
                    'name' => $writeOff->user?->name ?? 'Система',
                ],
                'approved_by' => $writeOff->approvedByUser ? [
                    'id' => $writeOff->approvedByUser->id,
                    'name' => $writeOff->approvedByUser->name,
                ] : null,
                'warehouse' => $writeOff->warehouse ? [
                    'id' => $writeOff->warehouse->id,
                    'name' => $writeOff->warehouse->name,
                ] : null,
                'items' => $writeOff->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ]),
                'items_count' => $writeOff->items->count(),
                'created_at' => $writeOff->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $writeOffs,
        ]);
    }

    /**
     * Создание нового списания
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:spoilage,expired,loss,staff_meal,promo,other',
            'description' => 'nullable|string|max:1000',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'items' => 'required_without:amount',
            'amount' => 'required_without:items|nullable|numeric|min:0',
            'manager_id' => 'nullable|integer|exists:users,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $userId = auth()->id() ?? $request->input('user_id', 1);

        // Парсим items если пришли как JSON строка
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        // Рассчитываем общую сумму
        $totalAmount = 0;
        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $totalAmount += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            }
        } elseif (isset($validated['amount'])) {
            $totalAmount = $validated['amount'];
        }

        // Проверяем порог для подтверждения менеджером
        $restaurant = Restaurant::find($restaurantId);
        $threshold = $restaurant?->write_off_approval_threshold ?? 1000;

        if ($totalAmount > $threshold && empty($validated['manager_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Сумма превышает порог. Требуется подтверждение менеджера.',
                'requires_manager_approval' => true,
                'threshold' => $threshold,
                'total_amount' => $totalAmount,
            ], 422);
        }

        return DB::transaction(function () use ($request, $validated, $restaurantId, $userId, $items, $totalAmount) {
            // Сохраняем фото если есть
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('write-offs', 'public');
            }

            // Создаём списание
            $writeOff = WriteOff::create([
                'restaurant_id' => $restaurantId,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'user_id' => $userId,
                'approved_by' => $validated['manager_id'] ?? null,
                'type' => $validated['type'],
                'total_amount' => $totalAmount,
                'description' => $validated['description'] ?? null,
                'photo_path' => $photoPath,
            ]);

            // Создаём позиции
            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    $unitPrice = $item['unit_price'] ?? $item['price'] ?? 0;
                    $quantity = $item['quantity'] ?? 1;

                    WriteOffItem::create([
                        'write_off_id' => $writeOff->id,
                        'item_type' => $item['item_type'] ?? 'manual',
                        'dish_id' => $item['dish_id'] ?? null,
                        'ingredient_id' => $item['ingredient_id'] ?? null,
                        'name' => $item['name'] ?? 'Без названия',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $unitPrice * $quantity,
                    ]);
                }
            } else {
                // Ручной ввод - одна позиция
                WriteOffItem::create([
                    'write_off_id' => $writeOff->id,
                    'item_type' => 'manual',
                    'name' => $validated['description'] ?? 'Ручное списание',
                    'quantity' => 1,
                    'unit_price' => $totalAmount,
                    'total_price' => $totalAmount,
                ]);
            }

            // Списываем со склада если выбран склад
            $writeOff->load('items');
            $writeOff->deductFromInventory();

            return response()->json([
                'success' => true,
                'message' => 'Списание создано',
                'data' => [
                    'id' => $writeOff->id,
                    'total_amount' => $writeOff->total_amount,
                    'items_count' => $writeOff->items->count(),
                ],
            ], 201);
        });
    }

    /**
     * Детали списания
     */
    public function show(WriteOff $writeOff): JsonResponse
    {
        $writeOff->load(['items', 'user', 'approvedByUser', 'warehouse']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $writeOff->id,
                'type' => $writeOff->type,
                'type_name' => $writeOff->type_name,
                'amount' => $writeOff->total_amount,
                'description' => $writeOff->description,
                'photo_url' => $writeOff->photo_url,
                'user' => [
                    'id' => $writeOff->user?->id,
                    'name' => $writeOff->user?->name ?? 'Система',
                ],
                'approved_by' => $writeOff->approvedByUser ? [
                    'id' => $writeOff->approvedByUser->id,
                    'name' => $writeOff->approvedByUser->name,
                ] : null,
                'warehouse' => $writeOff->warehouse ? [
                    'id' => $writeOff->warehouse->id,
                    'name' => $writeOff->warehouse->name,
                ] : null,
                'items' => $writeOff->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_type_name' => $item->item_type_name,
                    'dish_id' => $item->dish_id,
                    'ingredient_id' => $item->ingredient_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ]),
                'created_at' => $writeOff->created_at,
            ],
        ]);
    }

    /**
     * Получить настройки списаний (порог)
     */
    public function settings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        return response()->json([
            'success' => true,
            'data' => [
                'approval_threshold' => $restaurant?->write_off_approval_threshold ?? 1000,
                'types' => WriteOff::TYPES,
            ],
        ]);
    }

    /**
     * Проверка PIN менеджера
     */
    public function verifyManager(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
        ]);

        // Ищем пользователя по PIN (pin_lookup содержит plaintext PIN для быстрого поиска)
        $user = User::where('pin_lookup', $validated['pin'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный PIN-код',
            ], 401);
        }

        // Проверяем роль
        $managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
        if (!in_array($user->role, $managerRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав. Требуется менеджер или выше.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'manager_id' => $user->id,
                'manager_name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }
}
