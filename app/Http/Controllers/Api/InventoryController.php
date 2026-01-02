<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class InventoryController extends Controller
{
    // ==========================================
    // ИНГРЕДИЕНТЫ
    // ==========================================

    public function ingredients(Request $request): JsonResponse
    {
        $query = Ingredient::with(['category', 'unit', 'supplier'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $ingredients = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }

    public function storeIngredient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'unit_id' => 'required|integer|exists:units,id',
            'quantity' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'track_stock' => 'nullable|boolean',
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            ...$validated,
        ]);

        // Если указано начальное количество, создаём приходную операцию
        if ($ingredient->quantity > 0) {
            StockMovement::create([
                'restaurant_id' => $ingredient->restaurant_id,
                'ingredient_id' => $ingredient->id,
                'type' => 'income',
                'quantity' => $ingredient->quantity,
                'quantity_before' => 0,
                'quantity_after' => $ingredient->quantity,
                'cost_price' => $ingredient->cost_price,
                'total_cost' => $ingredient->quantity * $ingredient->cost_price,
                'reason' => 'Начальный остаток',
                'user_id' => $request->input('user_id'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент создан',
            'data' => $ingredient->load(['category', 'unit']),
        ], 201);
    }

    public function updateIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'category_id' => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'unit_id' => 'sometimes|integer|exists:units,id',
            'min_quantity' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'track_stock' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $ingredient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент обновлён',
            'data' => $ingredient->fresh(['category', 'unit']),
        ]);
    }

    public function destroyIngredient(Ingredient $ingredient): JsonResponse
    {
        // Проверяем, используется ли в рецептах
        if ($ingredient->recipeItems()->count() > 0) {
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

    // ==========================================
    // КАТЕГОРИИ И ЕДИНИЦЫ
    // ==========================================

    public function categories(Request $request): JsonResponse
    {
        $categories = IngredientCategory::where('restaurant_id', $request->input('restaurant_id', 1))
            ->withCount('ingredients')
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function units(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Unit::all(),
        ]);
    }

    // ==========================================
    // ДВИЖЕНИЕ ТОВАРОВ
    // ==========================================

    public function stockIncome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'cost_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'document_number' => 'nullable|string|max:50',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $ingredient->addStock(
            $validated['quantity'],
            $validated['cost_price'] ?? null,
            $validated['supplier_id'] ?? null,
            $validated['document_number'] ?? null,
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Приход оформлен',
            'data' => $ingredient->fresh(),
        ]);
    }

    public function stockWriteOff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        
        if ($ingredient->quantity < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно остатка для списания',
            ], 422);
        }

        $ingredient->writeOff(
            $validated['quantity'],
            $validated['reason'],
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Списание оформлено',
            'data' => $ingredient->fresh(),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['ingredient', 'supplier', 'user'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('ingredient_id')) {
            $query->forIngredient($request->input('ingredient_id'));
        }

        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->forPeriod($request->input('from'), $request->input('to') . ' 23:59:59');
        }

        $movements = $query->orderByDesc('created_at')->limit(500)->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    // ==========================================
    // РЕЦЕПТЫ (ТЕХКАРТЫ)
    // ==========================================

    public function recipes(Request $request): JsonResponse
    {
        $recipes = Recipe::with(['dish', 'items.ingredient.unit'])
            ->whereHas('dish', function ($q) use ($request) {
                $q->where('restaurant_id', $request->input('restaurant_id', 1));
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recipes,
        ]);
    }

    public function showRecipe(Recipe $recipe): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $recipe->load(['dish', 'items.ingredient.unit']),
        ]);
    }

    public function storeRecipe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id|unique:recipes,dish_id',
            'output_quantity' => 'nullable|numeric|min:0.1',
            'instructions' => 'nullable|string',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'cook_time_minutes' => 'nullable|integer|min:0',
            'items' => 'nullable|array',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.waste_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $recipe = Recipe::create([
            'dish_id' => $validated['dish_id'],
            'output_quantity' => $validated['output_quantity'] ?? 1,
            'instructions' => $validated['instructions'] ?? null,
            'prep_time_minutes' => $validated['prep_time_minutes'] ?? null,
            'cook_time_minutes' => $validated['cook_time_minutes'] ?? null,
        ]);

        // Добавляем ингредиенты
        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'waste_percent' => $item['waste_percent'] ?? 0,
                ]);
            }
        }

        // Пересчитываем себестоимость
        $recipe->calculateCost();

        return response()->json([
            'success' => true,
            'message' => 'Рецепт создан',
            'data' => $recipe->load(['dish', 'items.ingredient.unit']),
        ], 201);
    }

    public function updateRecipe(Request $request, Recipe $recipe): JsonResponse
    {
        $validated = $request->validate([
            'output_quantity' => 'nullable|numeric|min:0.1',
            'instructions' => 'nullable|string',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'cook_time_minutes' => 'nullable|integer|min:0',
            'items' => 'nullable|array',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.waste_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $recipe->update([
            'output_quantity' => $validated['output_quantity'] ?? $recipe->output_quantity,
            'instructions' => $validated['instructions'] ?? $recipe->instructions,
            'prep_time_minutes' => $validated['prep_time_minutes'] ?? $recipe->prep_time_minutes,
            'cook_time_minutes' => $validated['cook_time_minutes'] ?? $recipe->cook_time_minutes,
        ]);

        // Обновляем ингредиенты если переданы
        if (isset($validated['items'])) {
            $recipe->items()->delete();
            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'waste_percent' => $item['waste_percent'] ?? 0,
                ]);
            }
        }

        $recipe->calculateCost();

        return response()->json([
            'success' => true,
            'message' => 'Рецепт обновлён',
            'data' => $recipe->fresh(['dish', 'items.ingredient.unit']),
        ]);
    }

    public function destroyRecipe(Recipe $recipe): JsonResponse
    {
        $recipe->delete();

        return response()->json([
            'success' => true,
            'message' => 'Рецепт удалён',
        ]);
    }

    // ==========================================
    // ПОСТАВЩИКИ
    // ==========================================

    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::where('restaurant_id', $request->input('restaurant_id', 1))
            ->withCount('ingredients')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Поставщик добавлен',
            'data' => $supplier,
        ], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Поставщик обновлён',
            'data' => $supplier,
        ]);
    }

    // ==========================================
    // ИНВЕНТАРИЗАЦИЯ
    // ==========================================

    public function inventoryChecks(Request $request): JsonResponse
    {
        $checks = InventoryCheck::with(['creator'])
            ->where('restaurant_id', $request->input('restaurant_id', 1))
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $checks,
        ]);
    }

    public function createInventoryCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'category_id' => 'nullable|integer', // опционально для частичной инвентаризации
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        $check = InventoryCheck::create([
            'restaurant_id' => $restaurantId,
            'number' => InventoryCheck::generateNumber(),
            'date' => now(),
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->input('user_id'),
        ]);

        // Добавляем все активные ингредиенты
        $query = Ingredient::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('track_stock', true);

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $ingredients = $query->get();

        foreach ($ingredients as $ingredient) {
            InventoryCheckItem::create([
                'inventory_check_id' => $check->id,
                'ingredient_id' => $ingredient->id,
                'expected_quantity' => $ingredient->quantity,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация создана',
            'data' => $check->load(['items.ingredient.unit']),
        ], 201);
    }

    public function showInventoryCheck(InventoryCheck $inventoryCheck): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $inventoryCheck->load(['items.ingredient.unit', 'creator', 'completer']),
        ]);
    }

    public function updateInventoryCheckItem(Request $request, InventoryCheckItem $item): JsonResponse
    {
        $validated = $request->validate([
            'actual_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $item->update([
            'actual_quantity' => $validated['actual_quantity'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $item->fresh(['ingredient']),
        ]);
    }

    public function completeInventoryCheck(Request $request, InventoryCheck $inventoryCheck): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Инвентаризация уже завершена',
            ], 422);
        }

        // Проверяем, все ли позиции заполнены
        $unfilled = $inventoryCheck->items()->whereNull('actual_quantity')->count();
        if ($unfilled > 0) {
            return response()->json([
                'success' => false,
                'message' => "Не заполнено позиций: {$unfilled}",
            ], 422);
        }

        $inventoryCheck->complete($request->input('user_id'));

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация завершена, остатки скорректированы',
            'data' => $inventoryCheck->fresh(),
        ]);
    }

    // ==========================================
    // СТАТИСТИКА
    // ==========================================

    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $summary = Ingredient::getStockSummary($restaurantId);

        // Движение за сегодня
        $today = Carbon::today();
        $todayMovements = StockMovement::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->selectRaw("type, SUM(total_cost) as total")
            ->groupBy('type')
            ->pluck('total', 'type');

        // Топ списаний за месяц
        $monthStart = Carbon::now()->startOfMonth();
        $topWriteOffs = StockMovement::where('restaurant_id', $restaurantId)
            ->where('type', 'write_off')
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('ingredient_id, SUM(ABS(quantity)) as total_qty, SUM(total_cost) as total_cost')
            ->groupBy('ingredient_id')
            ->orderByDesc('total_cost')
            ->limit(5)
            ->with('ingredient:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'today' => [
                    'income' => $todayMovements['income'] ?? 0,
                    'expense' => $todayMovements['expense'] ?? 0,
                    'write_off' => $todayMovements['write_off'] ?? 0,
                ],
                'top_write_offs' => $topWriteOffs,
            ],
        ]);
    }

    public function lowStockAlerts(Request $request): JsonResponse
    {
        $ingredients = Ingredient::with(['category', 'unit'])
            ->where('restaurant_id', $request->input('restaurant_id', 1))
            ->where('is_active', true)
            ->lowStock()
            ->orderBy('quantity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }
}
