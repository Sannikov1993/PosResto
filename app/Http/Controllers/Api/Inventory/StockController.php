<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Http\Requests\Inventory\QuickIncomeRequest;
use App\Http\Requests\Inventory\QuickWriteOffRequest;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\UnitConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    use ResolvesRestaurantId;

    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['ingredient.unit', 'warehouse', 'user'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('ingredient_id')) {
            $query->forIngredient($request->input('ingredient_id'));
        }

        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->forPeriod($request->input('from'), $request->input('to') . ' 23:59:59');
        }

        $perPage = min($request->input('per_page', 100), 500);

        if ($request->has('page')) {
            $paginated = $query->orderByDesc('movement_date')->paginate($perPage);

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

        $movements = $query->orderByDesc('movement_date')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    public function quickIncome(QuickIncomeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);
        $ingredient = Ingredient::forRestaurant($restaurantId)->findOrFail($validated['ingredient_id']);

        if ($validated['cost_price'] ?? null) {
            $ingredient->update(['cost_price' => $validated['cost_price']]);
        }

        $ingredient->addStock(
            $validated['warehouse_id'],
            $validated['quantity'],
            $validated['cost_price'] ?? null,
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Приход оформлен',
            'data' => $ingredient->fresh(['stocks']),
        ]);
    }

    public function quickWriteOff(QuickWriteOffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);
        $ingredient = Ingredient::forRestaurant($restaurantId)->findOrFail($validated['ingredient_id']);
        $stock = $ingredient->getStockInWarehouse($validated['warehouse_id']);

        if ($stock < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => "Недостаточно остатка. Доступно: {$stock}",
            ], 422);
        }

        $ingredient->writeOff(
            $validated['warehouse_id'],
            $validated['quantity'],
            $validated['reason'],
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Списание оформлено',
            'data' => $ingredient->fresh(['stocks']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $warehouseId = $request->input('warehouse_id');

        $summary = Ingredient::getStockSummary($restaurantId, $warehouseId);

        $today = Carbon::today();
        $movementsQuery = StockMovement::where('restaurant_id', $restaurantId)
            ->whereDate('movement_date', $today);

        if ($warehouseId) {
            $movementsQuery->where('warehouse_id', $warehouseId);
        }

        $todayMovements = $movementsQuery
            ->selectRaw("type, SUM(total_cost) as total")
            ->groupBy('type')
            ->pluck('total', 'type');

        $warehouses = Warehouse::where('restaurant_id', $restaurantId)->active()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'warehouses' => $warehouses,
                'today' => [
                    'income' => $todayMovements['income'] ?? 0,
                    'expense' => abs($todayMovements['expense'] ?? 0),
                    'write_off' => abs($todayMovements['write_off'] ?? 0),
                ],
            ],
        ]);
    }

    public function lowStockAlerts(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $ingredients = Ingredient::with(['category', 'unit', 'stocks'])
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->get()
            ->filter(fn($i) => $i->is_low_stock);

        return response()->json([
            'success' => true,
            'data' => $ingredients->values(),
        ]);
    }

    public function convertUnits(Request $request, UnitConversionService $conversionService): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric',
            'from_unit_id' => 'required|integer|exists:units,id',
            'to_unit_id' => 'required|integer|exists:units,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $ingredient = Ingredient::forRestaurant($restaurantId)->findOrFail($validated['ingredient_id']);

        $fromUnit = Unit::where(function ($q) use ($restaurantId) {
            $q->where('restaurant_id', $restaurantId)->orWhere('is_system', true);
        })->findOrFail($validated['from_unit_id']);
        $toUnit = Unit::where(function ($q) use ($restaurantId) {
            $q->where('restaurant_id', $restaurantId)->orWhere('is_system', true);
        })->findOrFail($validated['to_unit_id']);

        $check = $conversionService->canConvert($ingredient, $fromUnit, $toUnit);
        if (!$check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'],
            ], 422);
        }

        $result = $conversionService->convert(
            $ingredient,
            $validated['quantity'],
            $fromUnit,
            $toUnit
        );

        return response()->json([
            'success' => true,
            'data' => [
                'from_quantity' => $validated['quantity'],
                'from_unit' => $fromUnit->short_name,
                'to_quantity' => round($result, 4),
                'to_unit' => $toUnit->short_name,
            ],
        ]);
    }

    public function calculateBruttoNetto(Request $request, UnitConversionService $conversionService): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'direction' => 'required|in:to_net,to_gross',
            'processing_type' => 'nullable|in:none,cold,hot,both',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $ingredient = Ingredient::forRestaurant($restaurantId)->findOrFail($validated['ingredient_id']);
        $processingType = $validated['processing_type'] ?? 'both';

        if ($validated['direction'] === 'to_net') {
            $result = $conversionService->calculateNet($ingredient, $validated['quantity'], $processingType);
        } else {
            $result = $conversionService->calculateGross($ingredient, $validated['quantity'], $processingType);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    // POS integration

    public function checkDishAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'portions' => 'nullable|integer|min:1',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $warehouseId = $validated['warehouse_id']
            ?? Warehouse::where('restaurant_id', $restaurantId)->where('is_default', true)->value('id')
            ?? Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

        if (!$warehouseId) {
            return response()->json([
                'success' => false,
                'message' => 'Склад не найден',
            ], 422);
        }

        $portions = $validated['portions'] ?? 1;
        $result = Recipe::checkAvailability($validated['dish_id'], $warehouseId, $portions);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function deductForOrder(Request $request, \App\Models\Order $order): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $restaurantId = $order->restaurant_id;
        $warehouseId = $validated['warehouse_id']
            ?? Warehouse::where('restaurant_id', $restaurantId)->where('is_default', true)->value('id')
            ?? Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

        if (!$warehouseId) {
            return response()->json([
                'success' => false,
                'message' => 'Склад не найден',
            ], 422);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Заказ не оплачен',
            ], 422);
        }

        if ($order->inventory_deducted) {
            return response()->json([
                'success' => true,
                'message' => 'Ингредиенты уже списаны',
            ]);
        }

        $userId = $request->input('user_id') ?? auth()->id();
        $deductedItems = [];

        foreach ($order->items as $item) {
            if ($item->dish_id) {
                Recipe::deductIngredientsForDish(
                    $item->dish_id,
                    $warehouseId,
                    $item->quantity,
                    $order->id,
                    $userId
                );
                $deductedItems[] = [
                    'dish_id' => $item->dish_id,
                    'dish_name' => $item->name,
                    'quantity' => $item->quantity,
                ];
            }
        }

        $order->update(['inventory_deducted' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Ингредиенты списаны',
            'data' => [
                'order_id' => $order->id,
                'items' => $deductedItems,
            ],
        ]);
    }
}
