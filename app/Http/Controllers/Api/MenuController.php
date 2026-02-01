<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Modifier;
use App\Services\PriceListService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    /**
     * Получить всё меню (категории с блюдами)
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $priceListId = $request->input('price_list_id');

        $categories = Category::with(['dishes' => function ($query) {
                $query->where('is_available', true)->orderBy('sort_order');
            }])
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($priceListId) {
            $priceListService = new PriceListService();
            $categories->each(function ($category) use ($priceListService, $priceListId) {
                if ($category->dishes) {
                    $priceListService->resolvePrices($category->dishes, (int) $priceListId);
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Получить список категорий
     */
    public function categories(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $categories = Category::with('legalEntity:id,name,short_name,type')
            ->withCount(['dishes' => function ($query) {
                $query->where('is_available', true);
            }])
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Создать категорию
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
            'parent_id' => 'nullable|exists:categories,id',
            'legal_entity_id' => 'nullable|exists:legal_entities,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $slug = $this->generateUniqueSlug(Category::class, $validated['name'], $restaurantId);

        $category = Category::create([
            'restaurant_id' => $restaurantId,
            'legal_entity_id' => $validated['legal_entity_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? '#6366F1',
            'sort_order' => $validated['sort_order'] ?? 0,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Категория создана',
            'data' => $category,
        ], 201);
    }

    /**
     * Обновить категорию
     */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'legal_entity_id' => 'nullable|exists:legal_entities,id',
        ]);

        // Генерируем уникальный slug только если имя изменилось
        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = $this->generateUniqueSlug(Category::class, $validated['name'], $category->restaurant_id, $category->id);
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Категория обновлена',
            'data' => $category,
        ]);
    }

    /**
     * Удалить категорию
     */
    public function destroyCategory(Category $category): JsonResponse
    {
        // Перенести блюда в "Без категории" или удалить
        $category->dishes()->update(['category_id' => null]);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Категория удалена',
        ]);
    }

    /**
     * Получить список блюд
     */
    public function dishes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $includeVariants = $request->boolean('include_variants', false);

        $relations = ['category', 'modifiers.options', 'kitchenStation', 'variants'];
        if ($includeVariants) {
            $relations[] = 'parent'; // Load parent for variants
        }

        $query = Dish::with($relations)
            ->where('restaurant_id', $restaurantId);

        // По умолчанию возвращаем только верхний уровень (simple + parent)
        // Варианты грузятся через связь variants
        if (!$includeVariants) {
            $query->topLevel();
        }

        // Фильтр по категории
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Только доступные
        if ($request->has('available')) {
            if ($request->boolean('available')) {
                $query->where('is_available', true);
            }
        }

        // Поиск
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Популярные
        if ($request->boolean('popular')) {
            $query->where('is_popular', true);
        }

        // Фильтр по типу
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        $dishes = $query->orderBy('sort_order')->get();

        $priceListId = $request->input('price_list_id');

        // Добавляем минимальную цену для parent
        $dishes->transform(function ($dish) {
            if ($dish->isParent()) {
                $dish->min_price = $dish->getMinPrice();
            }
            return $dish;
        });

        // Подставляем цены из прайс-листа
        if ($priceListId) {
            $priceListService = new PriceListService();
            $priceListService->resolvePrices($dishes, (int) $priceListId);

            // Также резолвим цены для вариантов внутри parent-блюд
            $allVariants = collect();
            $dishes->each(function ($dish) use ($allVariants) {
                if ($dish->isParent() && $dish->variants) {
                    foreach ($dish->variants as $variant) {
                        $allVariants->push($variant);
                    }
                }
            });
            if ($allVariants->isNotEmpty()) {
                $priceListService->resolvePrices($allVariants, (int) $priceListId);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $dishes,
        ]);
    }

    /**
     * Получить блюдо по ID
     */
    public function showDish(Dish $dish): JsonResponse
    {
        $dish->load(['category', 'modifiers.options', 'kitchenStation', 'variants', 'parent']);

        // Добавляем минимальную цену для parent
        if ($dish->isParent()) {
            $dish->min_price = $dish->getMinPrice();
        }

        return response()->json([
            'success' => true,
            'data' => $dish,
        ]);
    }

    /**
     * Создать блюдо
     */
    public function storeDish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'product_type' => 'nullable|in:simple,parent,variant',
            'parent_id' => 'nullable|exists:dishes,id',
            'name' => 'required|string|max:255',
            'variant_name' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'cooking_time' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:50',
            'api_external_id' => 'nullable|string|max:100',
            'is_available' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_vegetarian' => 'nullable|boolean',
            'modifier_ids' => 'nullable|array',
            'modifier_ids.*' => 'exists:modifiers,id',
            'variant_sort' => 'nullable|integer',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $slug = $this->generateUniqueSlug(Dish::class, $validated['name'], $restaurantId);

        // Определяем тип товара
        $productType = $validated['product_type'] ?? 'simple';

        // Если указан parent_id, это вариант
        if (!empty($validated['parent_id'])) {
            $productType = 'variant';
        }

        // Для parent товаров цена может быть null (берётся мин. из вариантов)
        $price = $validated['price'] ?? ($productType === 'parent' ? null : 0);

        $dish = Dish::create([
            'restaurant_id' => $restaurantId,
            'category_id' => $validated['category_id'] ?? null,
            'kitchen_station_id' => $validated['kitchen_station_id'] ?? null,
            'product_type' => $productType,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'variant_name' => $validated['variant_name'] ?? null,
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price' => $price,
            'old_price' => $validated['old_price'] ?? null,
            'cost_price' => $validated['cost_price'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'calories' => $validated['calories'] ?? null,
            'cooking_time' => $validated['cooking_time'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'api_external_id' => $validated['api_external_id'] ?? null,
            'is_available' => $validated['is_available'] ?? true,
            'is_popular' => $validated['is_popular'] ?? false,
            'is_new' => $validated['is_new'] ?? false,
            'is_spicy' => $validated['is_spicy'] ?? false,
            'is_vegetarian' => $validated['is_vegetarian'] ?? false,
            'variant_sort' => $validated['variant_sort'] ?? 0,
        ]);

        // Привязать модификаторы
        if (!empty($validated['modifier_ids'])) {
            $dish->modifiers()->sync($validated['modifier_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Блюдо создано',
            'data' => $dish->load(['category', 'modifiers', 'kitchenStation', 'variants', 'parent']),
        ], 201);
    }

    /**
     * Обновить блюдо
     */
    public function updateDish(Request $request, Dish $dish): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'product_type' => 'nullable|in:simple,parent,variant',
            'parent_id' => 'nullable|exists:dishes,id',
            'name' => 'sometimes|string|max:255',
            'variant_name' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'cooking_time' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:50',
            'api_external_id' => 'nullable|string|max:100',
            'is_available' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_vegetarian' => 'nullable|boolean',
            'modifier_ids' => 'nullable|array',
            'variant_sort' => 'nullable|integer',
        ]);

        // Генерируем уникальный slug только если имя изменилось
        if (isset($validated['name']) && $validated['name'] !== $dish->name) {
            $baseName = $validated['name'];
            // Для вариантов добавляем variant_name к slug
            if ($dish->product_type === 'variant' && $dish->variant_name) {
                $baseName = $validated['name'] . ' ' . $dish->variant_name;
            }
            $validated['slug'] = $this->generateUniqueSlug(Dish::class, $baseName, $dish->restaurant_id, $dish->id);
        }

        // Убираем modifier_ids из validated для update
        $modifierIds = $validated['modifier_ids'] ?? null;
        unset($validated['modifier_ids']);

        $dish->update($validated);

        // Обновить модификаторы
        if ($modifierIds !== null) {
            $dish->modifiers()->sync($modifierIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Блюдо обновлено',
            'data' => $dish->fresh(['category', 'modifiers', 'kitchenStation', 'variants', 'parent']),
        ]);
    }

    /**
     * Удалить блюдо
     */
    public function destroyDish(Dish $dish): JsonResponse
    {
        $dish->delete();

        return response()->json([
            'success' => true,
            'message' => 'Блюдо удалено',
        ]);
    }

    /**
     * Быстрое изменение доступности блюда
     */
    public function toggleAvailability(Dish $dish): JsonResponse
    {
        $dish->update(['is_available' => !$dish->is_available]);

        return response()->json([
            'success' => true,
            'message' => $dish->is_available ? 'Блюдо доступно' : 'Блюдо недоступно',
            'data' => $dish,
        ]);
    }

    /**
     * Получить модификаторы
     */
    public function modifiers(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $modifiers = Modifier::with('options')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modifiers,
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

        // Для публичных эндпоинтов меню (гостевое меню) без авторизации
        // возвращаем 1 как fallback, но это должно контролироваться роутами
        return 1;
    }

    /**
     * Генерировать уникальный slug
     */
    protected function generateUniqueSlug(string $model, string $name, int $restaurantId, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);

        // Если slug пустой (например, только кириллица без транслитерации)
        if (empty($baseSlug)) {
            $baseSlug = 'item-' . time();
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($model::where('restaurant_id', $restaurantId)
            ->where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
