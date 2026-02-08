<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveRecipeRequest;
use App\Models\Dish;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
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

    public function saveDishRecipe(SaveRecipeRequest $request, int $dishId): JsonResponse
    {
        $validated = $request->validated();
        $dish = Dish::findOrFail($dishId);

        Recipe::where('dish_id', $dishId)->delete();

        $order = 0;
        foreach ($validated['items'] ?? [] as $item) {
            Recipe::create([
                'restaurant_id' => $dish->restaurant_id,
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
}
