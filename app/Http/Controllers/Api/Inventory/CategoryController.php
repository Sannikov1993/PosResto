<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Models\IngredientCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ResolvesRestaurantId;

    public function index(Request $request): JsonResponse
    {
        $categories = IngredientCategory::where('restaurant_id', $this->getRestaurantId($request))
            ->withCount('ingredients')
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $category = IngredientCategory::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '📦',
            'color' => $validated['color'] ?? '#6b7280',
            'sort_order' => IngredientCategory::where('restaurant_id', $restaurantId)->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Категория создана',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, IngredientCategory $category): JsonResponse
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

    public function destroy(IngredientCategory $category): JsonResponse
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
}
