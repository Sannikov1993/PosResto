<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Modifier;
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

        $categories = Category::with(['dishes' => function ($query) {
                $query->where('is_available', true)->orderBy('sort_order');
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
     * Получить список категорий
     */
    public function categories(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $categories = Category::withCount(['dishes' => function ($query) {
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
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $category = Category::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
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
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
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

        $query = Dish::with(['category', 'modifiers.options'])
            ->where('restaurant_id', $restaurantId);

        // Фильтр по категории
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Только доступные
        if ($request->boolean('available', true)) {
            $query->where('is_available', true);
        }

        // Поиск
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Популярные
        if ($request->boolean('popular')) {
            $query->where('is_popular', true);
        }

        $dishes = $query->orderBy('sort_order')->get();

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
        $dish->load(['category', 'modifiers.options']);

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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'cooking_time' => 'nullable|integer|min:0',
            'is_available' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_vegetarian' => 'nullable|boolean',
            'modifier_ids' => 'nullable|array',
            'modifier_ids.*' => 'exists:modifiers,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $dish = Dish::create([
            'restaurant_id' => $restaurantId,
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'old_price' => $validated['old_price'] ?? null,
            'cost_price' => $validated['cost_price'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'calories' => $validated['calories'] ?? null,
            'cooking_time' => $validated['cooking_time'] ?? null,
            'is_available' => $validated['is_available'] ?? true,
            'is_popular' => $validated['is_popular'] ?? false,
            'is_new' => $validated['is_new'] ?? false,
            'is_spicy' => $validated['is_spicy'] ?? false,
            'is_vegetarian' => $validated['is_vegetarian'] ?? false,
        ]);

        // Привязать модификаторы
        if (!empty($validated['modifier_ids'])) {
            $dish->modifiers()->sync($validated['modifier_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Блюдо создано',
            'data' => $dish->load(['category', 'modifiers']),
        ], 201);
    }

    /**
     * Обновить блюдо
     */
    public function updateDish(Request $request, Dish $dish): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'cooking_time' => 'nullable|integer|min:0',
            'is_available' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_vegetarian' => 'nullable|boolean',
            'modifier_ids' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
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
            'data' => $dish->fresh(['category', 'modifiers']),
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
