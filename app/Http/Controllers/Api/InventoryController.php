<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientStock;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\IngredientPackaging;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Services\YandexVisionService;
use App\Services\UnitConversionService;

class InventoryController extends Controller
{
    // ==========================================
    // СКЛАДЫ
    // ==========================================

    public function warehouses(Request $request): JsonResponse
    {
        $warehouses = Warehouse::where('restaurant_id', $request->input('restaurant_id', 1))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|in:main,kitchen,bar,storage',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        // Если это первый склад или помечен как default - убираем флаг у остальных
        if ($validated['is_default'] ?? false) {
            Warehouse::where('restaurant_id', $restaurantId)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'main',
            'address' => $validated['address'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => Warehouse::where('restaurant_id', $restaurantId)->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Склад создан',
            'data' => $warehouse,
        ], 201);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'nullable|string|in:main,kitchen,bar,storage',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Warehouse::where('restaurant_id', $warehouse->restaurant_id)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_default' => false]);
        }

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Склад обновлён',
            'data' => $warehouse,
        ]);
    }

    public function warehouseTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Warehouse::getTypes(),
        ]);
    }

    public function destroyWarehouse(Warehouse $warehouse): JsonResponse
    {
        // Проверяем, есть ли остатки на складе
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить склад с остатками товаров',
            ], 422);
        }

        // Проверяем, является ли это единственным складом
        if (Warehouse::where('restaurant_id', $warehouse->restaurant_id)->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить единственный склад',
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Склад удалён',
        ]);
    }

    // ==========================================
    // ИНГРЕДИЕНТЫ
    // ==========================================

    public function ingredients(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');

        $query = Ingredient::with(['category', 'unit', 'stocks', 'packagings.unit'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

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

        // Пагинация: per_page по умолчанию 100, максимум 500
        $perPage = min($request->input('per_page', 100), 500);

        // Если запрошена пагинация через параметр page
        if ($request->has('page')) {
            $paginated = $query->orderBy('name')->paginate($perPage);

            // Добавляем остаток на выбранном складе
            if ($warehouseId) {
                $paginated->getCollection()->each(function ($ingredient) use ($warehouseId) {
                    $ingredient->warehouse_stock = $ingredient->getStockInWarehouse($warehouseId);
                });
            }

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

        // Без явной пагинации - ограничиваем лимитом для обратной совместимости
        $ingredients = $query->orderBy('name')->limit($perPage)->get();

        // Добавляем остаток на выбранном складе
        if ($warehouseId) {
            $ingredients->each(function ($ingredient) use ($warehouseId) {
                $ingredient->warehouse_stock = $ingredient->getStockInWarehouse($warehouseId);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }

    public function storeIngredient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'nullable|integer|exists:ingredient_categories,id',
            'unit_id' => 'required|integer|exists:units,id',
            'sku' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'shelf_life_days' => 'nullable|integer|min:0',
            'storage_conditions' => 'nullable|string',
            'is_semi_finished' => 'nullable|boolean',
            'track_stock' => 'nullable|boolean',
            'initial_stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            // Новые поля для конвертации
            'piece_weight' => 'nullable|numeric|min:0',
            'density' => 'nullable|numeric|min:0',
            'cold_loss_percent' => 'nullable|numeric|min:0|max:100',
            'hot_loss_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

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
            // Новые поля
            'piece_weight' => $validated['piece_weight'] ?? null,
            'density' => $validated['density'] ?? null,
            'cold_loss_percent' => $validated['cold_loss_percent'] ?? 0,
            'hot_loss_percent' => $validated['hot_loss_percent'] ?? 0,
        ]);

        // Если указан начальный остаток
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

    public function showIngredient(Ingredient $ingredient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ingredient->load(['category', 'unit', 'stocks.warehouse', 'recipes.dish', 'packagings.unit']),
        ]);
    }

    public function updateIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'category_id' => 'nullable|integer|exists:ingredient_categories,id',
            'unit_id' => 'sometimes|integer|exists:units,id',
            'sku' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'shelf_life_days' => 'nullable|integer|min:0',
            'storage_conditions' => 'nullable|string',
            'is_semi_finished' => 'nullable|boolean',
            'track_stock' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            // Новые поля для конвертации
            'piece_weight' => 'nullable|numeric|min:0',
            'density' => 'nullable|numeric|min:0',
            'cold_loss_percent' => 'nullable|numeric|min:0|max:100',
            'hot_loss_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $ingredient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ингредиент обновлён',
            'data' => $ingredient->fresh(['category', 'unit', 'stocks', 'packagings.unit']),
        ]);
    }

    public function destroyIngredient(Ingredient $ingredient): JsonResponse
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

    // ==========================================
    // КАТЕГОРИИ
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

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
        ]);

        $category = IngredientCategory::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '📦',
            'color' => $validated['color'] ?? '#6b7280',
            'sort_order' => IngredientCategory::where('restaurant_id', $request->input('restaurant_id', 1))->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Категория создана',
            'data' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, IngredientCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Категория обновлена',
            'data' => $category,
        ]);
    }

    public function destroyCategory(IngredientCategory $category): JsonResponse
    {
        if ($category->ingredients()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить категорию с ингредиентами',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Категория удалена',
        ]);
    }

    // ==========================================
    // ЕДИНИЦЫ ИЗМЕРЕНИЯ
    // ==========================================

    public function units(Request $request): JsonResponse
    {
        $query = Unit::query();

        if ($request->has('restaurant_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('restaurant_id', $request->input('restaurant_id'))
                  ->orWhere('is_system', true);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'short_name' => 'required|string|max:10',
            'type' => 'nullable|string|in:weight,volume,piece,length',
        ]);

        $unit = Unit::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'type' => $validated['type'] ?? 'piece',
            'is_system' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Единица измерения создана',
            'data' => $unit,
        ], 201);
    }

    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        if ($unit->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Системные единицы измерения нельзя редактировать',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'short_name' => 'sometimes|string|max:10',
            'type' => 'nullable|string|in:weight,volume,piece,length',
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Единица измерения обновлена',
            'data' => $unit,
        ]);
    }

    public function destroyUnit(Unit $unit): JsonResponse
    {
        if ($unit->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Системные единицы измерения нельзя удалять',
            ], 422);
        }

        if ($unit->ingredients()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Единица измерения используется в ингредиентах',
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Единица измерения удалена',
        ]);
    }

    // ==========================================
    // НАКЛАДНЫЕ
    // ==========================================

    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::with(['warehouse', 'supplier', 'user'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);

        if ($request->has('page')) {
            $paginated = $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate($perPage);

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

        // Обратная совместимость: без page параметра возвращаем с лимитом
        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:income,expense,transfer,write_off',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'target_warehouse_id' => 'nullable|integer|exists:warehouses,id|different:warehouse_id',
            'invoice_date' => 'nullable|date',
            'external_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        $invoice = Invoice::create([
            'restaurant_id' => $restaurantId,
            'warehouse_id' => $validated['warehouse_id'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'user_id' => $request->input('user_id', 1),
            'type' => $validated['type'],
            'number' => Invoice::generateNumber($validated['type']),
            'external_number' => $validated['external_number'] ?? null,
            'status' => 'draft',
            'target_warehouse_id' => $validated['target_warehouse_id'] ?? null,
            'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $ingredient = Ingredient::find($item['ingredient_id']);
            $costPrice = $item['cost_price'] ?? $ingredient->cost_price ?? 0;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'cost_price' => $costPrice,
                'total' => $item['quantity'] * $costPrice,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);
        }

        $invoice->recalculateTotal();

        return response()->json([
            'success' => true,
            'message' => 'Накладная создана',
            'data' => $invoice->load(['items.ingredient.unit', 'warehouse', 'supplier']),
        ], 201);
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items.ingredient.unit', 'warehouse', 'targetWarehouse', 'supplier', 'user']),
        ]);
    }

    public function completeInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Накладная уже проведена',
            ], 422);
        }

        $success = $invoice->complete($request->input('user_id'));

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось провести накладную',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная проведена',
            'data' => $invoice->fresh(),
        ]);
    }

    public function cancelInvoice(Invoice $invoice): JsonResponse
    {
        if (!$invoice->cancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить проведённую накладную',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная отменена',
        ]);
    }

    // ==========================================
    // БЫСТРЫЕ ОПЕРАЦИИ
    // ==========================================

    public function quickIncome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'cost_price' => 'nullable|numeric|min:0',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);

        if ($validated['cost_price'] ?? null) {
            $ingredient->update(['cost_price' => $validated['cost_price']]);
        }

        $movement = $ingredient->addStock(
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

    public function quickWriteOff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
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

    // ==========================================
    // ДВИЖЕНИЕ ТОВАРОВ
    // ==========================================

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['ingredient.unit', 'warehouse', 'user'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

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

        // Пагинация: per_page по умолчанию 100, максимум 500
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

        // Обратная совместимость: без page параметра возвращаем с лимитом
        $movements = $query->orderByDesc('movement_date')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    // ==========================================
    // РЕЦЕПТЫ (ТЕХКАРТЫ)
    // ==========================================

    public function dishRecipe(int $dishId): JsonResponse
    {
        $recipes = Recipe::with(['ingredient.unit'])
            ->where('dish_id', $dishId)
            ->orderBy('sort_order')
            ->get();

        $totalCost = $recipes->sum('ingredient_cost');

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $recipes,
                'total_cost' => $totalCost,
            ],
        ]);
    }

    public function saveDishRecipe(Request $request, int $dishId): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'array',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.gross_quantity' => 'nullable|numeric|min:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.is_optional' => 'nullable|boolean',
            'items.*.notes' => 'nullable|string',
        ]);

        // Удаляем старые записи
        Recipe::where('dish_id', $dishId)->delete();

        // Создаём новые
        $order = 0;
        foreach ($validated['items'] ?? [] as $item) {
            Recipe::create([
                'dish_id' => $dishId,
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'gross_quantity' => $item['gross_quantity'] ?? null,
                'waste_percent' => $item['waste_percent'] ?? 0,
                'is_optional' => $item['is_optional'] ?? false,
                'notes' => $item['notes'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Рецепт сохранён',
            'data' => Recipe::with(['ingredient.unit'])->where('dish_id', $dishId)->get(),
        ]);
    }

    // ==========================================
    // ПОСТАВЩИКИ
    // ==========================================

    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::where('restaurant_id', $request->input('restaurant_id', 1))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
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
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'inn' => 'nullable|string|max:20',
            'kpp' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string',
            'delivery_days' => 'nullable|integer|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            ...$validated,
            'is_active' => true,
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
            'name' => 'sometimes|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'inn' => 'nullable|string|max:20',
            'kpp' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string',
            'delivery_days' => 'nullable|integer|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
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

    public function destroySupplier(Supplier $supplier): JsonResponse
    {
        if ($supplier->invoices()->count() > 0) {
            // Вместо удаления деактивируем
            $supplier->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Поставщик деактивирован (есть связанные накладные)',
                'data' => $supplier,
            ]);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Поставщик удалён',
        ]);
    }

    // ==========================================
    // СТАТИСТИКА
    // ==========================================

    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $warehouseId = $request->input('warehouse_id');

        $summary = Ingredient::getStockSummary($restaurantId, $warehouseId);

        // Движение за сегодня
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

        // Склады
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
        $ingredients = Ingredient::with(['category', 'unit', 'stocks'])
            ->where('restaurant_id', $request->input('restaurant_id', 1))
            ->where('is_active', true)
            ->where('track_stock', true)
            ->get()
            ->filter(fn($i) => $i->is_low_stock);

        return response()->json([
            'success' => true,
            'data' => $ingredients->values(),
        ]);
    }

    // ==========================================
    // ИНВЕНТАРИЗАЦИИ
    // ==========================================

    public function inventoryChecks(Request $request): JsonResponse
    {
        $query = InventoryCheck::with(['warehouse', 'creator'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

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

    public function createInventoryCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'notes' => 'nullable|string',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $userId = $request->input('user_id', 1);

        // Проверяем что нет незавершённой инвентаризации для этого склада
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

        // Заполняем позиции из текущих остатков
        $check->populateFromStock();

        return response()->json([
            'success' => true,
            'message' => 'Инвентаризация создана',
            'data' => $check->load(['items.ingredient.unit', 'warehouse']),
        ], 201);
    }

    public function showInventoryCheck(InventoryCheck $inventoryCheck): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $inventoryCheck->load(['items.ingredient.unit', 'warehouse', 'creator', 'completer']),
        ]);
    }

    public function updateInventoryCheckItem(Request $request, InventoryCheck $inventoryCheck, $itemId): JsonResponse
    {
        if ($inventoryCheck->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя редактировать завершённую инвентаризацию',
            ], 422);
        }

        $item = $inventoryCheck->items()->findOrFail($itemId);

        $validated = $request->validate([
            'actual_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $item->actual_quantity = $validated['actual_quantity'];
        $item->notes = $validated['notes'] ?? $item->notes;
        $item->save();

        // Если это была первая позиция — переводим в in_progress
        if ($inventoryCheck->status === 'draft') {
            $inventoryCheck->start();
        }

        return response()->json([
            'success' => true,
            'message' => 'Позиция обновлена',
            'data' => $item->fresh('ingredient.unit'),
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

        // Проверяем все ли позиции заполнены
        $unfilled = $inventoryCheck->items()->whereNull('actual_quantity')->count();
        if ($unfilled > 0) {
            return response()->json([
                'success' => false,
                'message' => "Не заполнено позиций: {$unfilled}",
            ], 422);
        }

        $userId = $request->input('user_id', 1);
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

    public function cancelInventoryCheck(InventoryCheck $inventoryCheck): JsonResponse
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

    public function addInventoryCheckItem(Request $request, InventoryCheck $inventoryCheck): JsonResponse
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

        // Проверяем что ингредиент ещё не добавлен
        $exists = $inventoryCheck->items()->where('ingredient_id', $validated['ingredient_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ингредиент уже есть в инвентаризации',
            ], 422);
        }

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $stock = $ingredient->getStockInWarehouse($inventoryCheck->warehouse_id);

        $item = $inventoryCheck->items()->create([
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

    // ==========================================
    // POS ИНТЕГРАЦИЯ
    // ==========================================

    /**
     * Проверить доступность ингредиентов для блюда
     */
    public function checkDishAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'portions' => 'nullable|integer|min:1',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
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

    /**
     * Списать ингредиенты для заказа (вызывается при оплате)
     */
    public function deductForOrder(Request $request, \App\Models\Order $order): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $restaurantId = $order->restaurant_id ?? 1;
        $warehouseId = $validated['warehouse_id']
            ?? Warehouse::where('restaurant_id', $restaurantId)->where('is_default', true)->value('id')
            ?? Warehouse::where('restaurant_id', $restaurantId)->first()?->id;

        if (!$warehouseId) {
            return response()->json([
                'success' => false,
                'message' => 'Склад не найден',
            ], 422);
        }

        // Проверяем что заказ оплачен
        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Заказ не оплачен',
            ], 422);
        }

        // Проверяем что списание ещё не производилось
        if ($order->inventory_deducted) {
            return response()->json([
                'success' => true,
                'message' => 'Ингредиенты уже списаны',
            ]);
        }

        $userId = $request->input('user_id', 1);
        $deductedItems = [];

        // Списываем ингредиенты по каждой позиции заказа
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

        // Помечаем заказ как обработанный
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

    // ==========================================
    // РАСПОЗНАВАНИЕ НАКЛАДНЫХ ПО ФОТО
    // ==========================================

    /**
     * Распознать накладную по фото через Yandex Vision OCR
     */
    public function recognizeInvoice(Request $request, YandexVisionService $visionService): JsonResponse
    {
        // Проверяем настройки
        if (!$visionService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Yandex Vision API не настроен. Добавьте YANDEX_VISION_API_KEY и YANDEX_FOLDER_ID в .env',
            ], 422);
        }

        $validated = $request->validate([
            'image' => 'required|string', // base64
        ]);

        $result = $visionService->recognizeInvoice($validated['image']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Ошибка распознавания',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная распознана',
            'data' => [
                'items' => $result['items'],
                'items_count' => $result['items_count'],
                'raw_text' => $result['raw_text'] ?? null,
            ],
        ]);
    }

    /**
     * Проверить настройки Yandex Vision API
     */
    public function checkVisionConfig(YandexVisionService $visionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'configured' => $visionService->isConfigured(),
        ]);
    }

    // ==========================================
    // ФАСОВКИ (PACKAGINGS)
    // ==========================================

    /**
     * Получить фасовки ингредиента
     */
    public function ingredientPackagings(Ingredient $ingredient): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ingredient->packagings()->with('unit')->get(),
        ]);
    }

    /**
     * Создать фасовку для ингредиента
     */
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

        // Проверяем уникальность unit_id для этого ингредиента
        if ($ingredient->packagings()->where('unit_id', $validated['unit_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Фасовка с такой единицей измерения уже существует',
            ], 422);
        }

        $packaging = $ingredient->packagings()->create([
            'unit_id' => $validated['unit_id'],
            'quantity' => $validated['quantity'],
            'barcode' => $validated['barcode'] ?? null,
            'name' => $validated['name'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_purchase' => $validated['is_purchase'] ?? true,
        ]);

        // Если это default - убираем флаг у других
        if ($packaging->is_default) {
            $packaging->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Фасовка создана',
            'data' => $packaging->load('unit'),
        ], 201);
    }

    /**
     * Обновить фасовку
     */
    public function updatePackaging(Request $request, IngredientPackaging $packaging): JsonResponse
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

    /**
     * Удалить фасовку
     */
    public function destroyPackaging(IngredientPackaging $packaging): JsonResponse
    {
        $packaging->delete();

        return response()->json([
            'success' => true,
            'message' => 'Фасовка удалена',
        ]);
    }

    // ==========================================
    // КОНВЕРТАЦИЯ ЕДИНИЦ
    // ==========================================

    /**
     * Конвертировать количество между единицами
     */
    public function convertUnits(Request $request, UnitConversionService $conversionService): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric',
            'from_unit_id' => 'required|integer|exists:units,id',
            'to_unit_id' => 'required|integer|exists:units,id',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);
        $fromUnit = Unit::findOrFail($validated['from_unit_id']);
        $toUnit = Unit::findOrFail($validated['to_unit_id']);

        // Проверяем возможность конвертации
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

    /**
     * Рассчитать брутто/нетто
     */
    public function calculateBruttoNetto(Request $request, UnitConversionService $conversionService): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'direction' => 'required|in:to_net,to_gross',
            'processing_type' => 'nullable|in:none,cold,hot,both',
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);
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

    /**
     * Получить доступные единицы измерения для ингредиента
     */
    public function availableUnits(Ingredient $ingredient, UnitConversionService $conversionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $conversionService->getAvailableUnits($ingredient),
        ]);
    }

    /**
     * Получить рекомендуемые параметры для ингредиента
     */
    public function suggestParameters(Ingredient $ingredient, UnitConversionService $conversionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $conversionService->suggestParameters($ingredient),
        ]);
    }
}
