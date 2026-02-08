<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Http\Requests\Inventory\StoreInventoryCheckRequest;
use App\Http\Requests\Inventory\UpdateCheckItemRequest;
use App\Models\Ingredient;
use App\Models\InventoryCheck;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCheckController extends Controller
{
    use ResolvesRestaurantId;

    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = InventoryCheck::with(['warehouse', 'creator'])
            ->withCount('items')
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $checks = $query->orderByDesc('date')->orderByDesc('id')->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $checks,
        ]);
    }

    public function store(StoreInventoryCheckRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);
        $userId = $request->input('user_id') ?? auth()->id();

        $existing = InventoryCheck::where('warehouse_id', $validated['warehouse_id'])
            ->whereIn('status', ['draft', 'in_progress'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Для этого склада уже есть незавершённая инвентаризация',
                'data' => $existing,
            ], 422);
        }

        $check = InventoryCheck::create([
            'restaurant_id' => $restaurantId,
            'warehouse_id' => $validated['warehouse_id'],
            'created_by' => $userId,
            'number' => InventoryCheck::generateNumber(),
            'status' => 'draft',
            'date' => now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $check->populateFromStock();

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация создана',
            'data' => $check->load(['items.ingredient.unit', 'warehouse']),
        ], 201);
    }

    public function show(InventoryCheck $inventoryCheck): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $inventoryCheck->load(['items.ingredient.unit', 'warehouse', 'creator', 'completer']),
        ]);
    }

    public function updateItem(UpdateCheckItemRequest $request, InventoryCheck $inventoryCheck, $itemId): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя редактировать завершённую инвентаризацию',
            ], 422);
        }

        $item = $inventoryCheck->items()->findOrFail($itemId);
        $validated = $request->validated();

        $item->actual_quantity = $validated['actual_quantity'];
        $item->notes = $validated['notes'] ?? $item->notes;
        $item->save();

        if ($inventoryCheck->status === 'draft') {
            $inventoryCheck->start();
        }

        return response()->json([
            'success' => true,
            'message' => 'Позиция обновлена',
            'data' => $item->fresh('ingredient.unit'),
        ]);
    }

    public function addItem(Request $request, InventoryCheck $inventoryCheck): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя редактировать завершённую инвентаризацию',
            ], 422);
        }

        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
        ]);

        $exists = $inventoryCheck->items()->where('ingredient_id', $validated['ingredient_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ингредиент уже есть в инвентаризации',
            ], 422);
        }

        $ingredient = Ingredient::forRestaurant($inventoryCheck->restaurant_id)->findOrFail($validated['ingredient_id']);
        $stock = $ingredient->getStockInWarehouse($inventoryCheck->warehouse_id);

        $item = $inventoryCheck->items()->create([
            'restaurant_id' => $inventoryCheck->restaurant_id,
            'ingredient_id' => $ingredient->id,
            'expected_quantity' => $stock,
            'cost_price' => $ingredient->cost_price ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Позиция добавлена',
            'data' => $item->load('ingredient.unit'),
        ], 201);
    }

    public function complete(Request $request, InventoryCheck $inventoryCheck): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Инвентаризация уже завершена',
            ], 422);
        }

        $unfilled = $inventoryCheck->items()->whereNull('actual_quantity')->count();
        if ($unfilled > 0) {
            return response()->json([
                'success' => false,
                'message' => "Не заполнено позиций: {$unfilled}",
            ], 422);
        }

        $userId = $request->input('user_id') ?? auth()->id();
        $success = $inventoryCheck->complete($userId);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось завершить инвентаризацию',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация завершена, остатки скорректированы',
            'data' => $inventoryCheck->fresh(['items.ingredient.unit', 'warehouse']),
        ]);
    }

    public function cancel(InventoryCheck $inventoryCheck): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить завершённую инвентаризацию',
            ], 422);
        }

        $inventoryCheck->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация отменена',
        ]);
    }
}
