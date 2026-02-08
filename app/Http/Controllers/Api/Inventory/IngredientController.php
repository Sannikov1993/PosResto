<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Http\Requests\Inventory\StoreIngredientRequest;
use App\Http\Requests\Inventory\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\IngredientPackaging;
use App\Models\IngredientStock;
use App\Models\Warehouse;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    use ResolvesRestaurantId;

    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');

        $query = Ingredient::with(['category', 'unit', 'stocks', 'packagings.unit'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock')) {
            $query->where('track_stock', true)
                ->whereHas('stocks', function ($q) {
                    $q->havingRaw('SUM(quantity) <= ingredients.min_stock');
                });
        }

        if ($request->boolean('semi_finished')) {
            $query->where('is_semi_finished', true);
        }

        $perPage = min($request->input('per_page', 100), 500);

        // Subquery для warehouse_stock вместо N+1
        if ($warehouseId) {
            $query->addSelect(['*',
                'warehouse_stock' => IngredientStock::select('quantity')
                    ->whereColumn('ingredient_id', 'ingredients.id')
                    ->where('warehouse_id', $warehouseId)
                    ->limit(1)
            ]);
        }

        if ($request->has('page')) {
            $paginated = $query->orderBy('name')->paginate($perPage);

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

        $ingredients = $query->orderBy('name')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }

    public function store(StoreIngredientRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurantId,
            'category_id' => $validated['category_id'] ?? null,
            'unit_id' => $validated['unit_id'],
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'description' => $validated['description'] ?? null,
            'cost_price' => $validated['cost_price'] ?? 0,
            'min_stock' => $validated['min_stock'] ?? 0,
            'max_stock' => $validated['max_stock'] ?? null,
            'shelf_life_days' => $validated['shelf_life_days'] ?? null,
            'storage_conditions' => $validated['storage_conditions'] ?? null,
            'is_semi_finished' => $validated['is_semi_finished'] ?? false,
            'track_stock' => $validated['track_stock'] ?? true,
            'is_active' => true,
            'piece_weight' => $validated['piece_weight'] ?? null,
            'density' => $validated['density'] ?? null,
            'cold_loss_percent' => $validated['cold_loss_percent'] ?? 0,
            'hot_loss_percent' => $validated['hot_loss_percent'] ?? 0,
        ]);

        if (($validated['initial_stock'] ?? 0) > 0) {
            $warehouseId = $validated['warehouse_id']
                ?? Warehouse::where('restaurant_id', $restaurantId)->where('is_default', true)->value('id')
                ?? Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

            if ($warehouseId) {
                $ingredient->addStock(
                    $warehouseId,
                    $validated['initial_stock'],
                    $validated['cost_price'] ?? 0,
                    $request->input('user_id')
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент создан',
            'data' => $ingredient->load(['category', 'unit', 'stocks']),
        ], 201);
    }

    public function show(Ingredient $ingredient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ingredient->load(['category', 'unit', 'stocks.warehouse', 'recipes.dish', 'packagings.unit']),
        ]);
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ingredient->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент обновлён',
            'data' => $ingredient->fresh(['category', 'unit', 'stocks', 'packagings.unit']),
        ]);
    }

    public function destroy(Ingredient $ingredient): JsonResponse
    {
        if ($ingredient->recipes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить: ингредиент используется в рецептах',
            ], 422);
        }

        $ingredient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент удалён',
        ]);
    }

    // Packagings

    public function packagings(Ingredient $ingredient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ingredient->packagings()->with('unit')->get(),
        ]);
    }

    public function storePackaging(Request $request, Ingredient $ingredient): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|integer|exists:units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'barcode' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_purchase' => 'nullable|boolean',
        ]);

        if ($ingredient->packagings()->where('unit_id', $validated['unit_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Фасовка с такой единицей измерения уже существует',
            ], 422);
        }

        $packaging = $ingredient->packagings()->create([
            'restaurant_id' => $ingredient->restaurant_id,
            'unit_id' => $validated['unit_id'],
            'quantity' => $validated['quantity'],
            'barcode' => $validated['barcode'] ?? null,
            'name' => $validated['name'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_purchase' => $validated['is_purchase'] ?? true,
        ]);

        if ($packaging->is_default) {
            $packaging->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Фасовка создана',
            'data' => $packaging->load('unit'),
        ], 201);
    }

    public function updatePackaging(Request $request, Ingredient $ingredient, IngredientPackaging $packaging): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|numeric|min:0.0001',
            'barcode' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_purchase' => 'nullable|boolean',
        ]);

        $packaging->update($validated);

        if ($validated['is_default'] ?? false) {
            $packaging->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Фасовка обновлена',
            'data' => $packaging->fresh('unit'),
        ]);
    }

    public function destroyPackaging(Ingredient $ingredient, IngredientPackaging $packaging): JsonResponse
    {
        $packaging->delete();

        return response()->json([
            'success' => true,
            'message' => 'Фасовка удалена',
        ]);
    }

    // Unit conversion helpers

    public function availableUnits(Ingredient $ingredient, UnitConversionService $conversionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $conversionService->getAvailableUnits($ingredient),
        ]);
    }

    public function suggestParameters(Ingredient $ingredient, UnitConversionService $conversionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $conversionService->suggestParameters($ingredient),
        ]);
    }
}
