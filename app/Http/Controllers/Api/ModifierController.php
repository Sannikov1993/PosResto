<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModifierController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // Получить все группы модификаторов
    public function index(Request $request): JsonResponse
    {
        $query = Modifier::with(['options' => function ($q) {
            $q->orderBy('sort_order');
        }])
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->orderBy('sort_order');

        if ($request->has('is_global')) {
            $query->where('is_global', $request->boolean('is_global'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    // Получить одну группу
    public function show(Modifier $modifier): JsonResponse
    {
        $modifier->load(['options.ingredients', 'dishes']);
        return response()->json($modifier);
    }

    // Создать группу модификаторов
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'in:single,multiple',
            'is_required' => 'boolean',
            'min_selections' => 'integer|min:0',
            'max_selections' => 'integer|min:1',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'options' => 'array',
            'options.*.name' => 'required|string|max:100',
            'options.*.price' => 'numeric',
            'options.*.is_default' => 'boolean',
            'options.*.sort_order' => 'integer',
        ]);

        $modifier = Modifier::create([
            'restaurant_id' => $this->getRestaurantId($request),
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'single',
            'is_required' => $validated['is_required'] ?? false,
            'min_selections' => $validated['min_selections'] ?? 0,
            'max_selections' => $validated['max_selections'] ?? 10,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'is_global' => $validated['is_global'] ?? true,
        ]);

        // Создаём опции
        if (!empty($validated['options'])) {
            foreach ($validated['options'] as $index => $optionData) {
                $modifier->options()->create([
                    'name' => $optionData['name'],
                    'price' => $optionData['price'] ?? 0,
                    'is_default' => $optionData['is_default'] ?? false,
                    'sort_order' => $optionData['sort_order'] ?? $index,
                    'is_active' => true,
                ]);
            }
        }

        $modifier->load('options');
        return response()->json($modifier, 201);
    }

    // Обновить группу
    public function update(Request $request, Modifier $modifier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'type' => 'in:single,multiple',
            'is_required' => 'boolean',
            'min_selections' => 'integer|min:0',
            'max_selections' => 'integer|min:1',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'options' => 'array',
        ]);

        $modifier->update($validated);

        // Обновляем опции если переданы
        if (isset($validated['options'])) {
            $existingIds = [];

            foreach ($validated['options'] as $index => $optionData) {
                if (isset($optionData['id'])) {
                    // Обновляем существующую (ищем только среди опций этого модификатора)
                    $option = $modifier->options()->find($optionData['id']);
                    if ($option) {
                        $option->update([
                            'name' => $optionData['name'],
                            'price' => $optionData['price'] ?? 0,
                            'is_default' => $optionData['is_default'] ?? false,
                            'sort_order' => $optionData['sort_order'] ?? $index,
                            'is_active' => $optionData['is_active'] ?? true,
                        ]);
                        $existingIds[] = $option->id;
                    }
                } else {
                    // Создаём новую
                    $option = $modifier->options()->create([
                        'name' => $optionData['name'],
                        'price' => $optionData['price'] ?? 0,
                        'is_default' => $optionData['is_default'] ?? false,
                        'sort_order' => $optionData['sort_order'] ?? $index,
                        'is_active' => true,
                    ]);
                    $existingIds[] = $option->id;
                }
            }

            // Удаляем опции которых нет в списке
            $modifier->options()->whereNotIn('id', $existingIds)->delete();
        }

        $modifier->load('options');
        return response()->json($modifier);
    }

    // Удалить группу
    public function destroy(Modifier $modifier): JsonResponse
    {
        $modifier->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ================== ОПЦИИ ==================

    // Добавить опцию в группу
    public function storeOption(Request $request, Modifier $modifier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'numeric',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
            'ingredients.*.action' => 'in:add,replace,remove',
        ]);

        $option = $modifier->options()->create([
            'name' => $validated['name'],
            'price' => $validated['price'] ?? 0,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => $validated['sort_order'] ?? $modifier->options()->count(),
            'is_active' => true,
        ]);

        // Привязываем ингредиенты
        if (!empty($validated['ingredients'])) {
            foreach ($validated['ingredients'] as $ingData) {
                $option->ingredients()->attach($ingData['ingredient_id'], [
                    'quantity' => $ingData['quantity'],
                    'action' => $ingData['action'] ?? 'add',
                ]);
            }
        }

        $option->load('ingredients');
        return response()->json($option, 201);
    }

    // Обновить опцию
    public function updateOption(Request $request, ModifierOption $option): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'price' => 'numeric',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
            'ingredients.*.action' => 'in:add,replace,remove',
        ]);

        $option->update($validated);

        // Обновляем ингредиенты
        if (isset($validated['ingredients'])) {
            $syncData = [];
            foreach ($validated['ingredients'] as $ingData) {
                $syncData[$ingData['ingredient_id']] = [
                    'quantity' => $ingData['quantity'],
                    'action' => $ingData['action'] ?? 'add',
                ];
            }
            $option->ingredients()->sync($syncData);
        }

        $option->load('ingredients');
        return response()->json($option);
    }

    // Удалить опцию
    public function destroyOption(ModifierOption $option): JsonResponse
    {
        $option->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ================== ПРИВЯЗКА К БЛЮДАМ ==================

    // Привязать модификатор к блюду
    public function attachToDish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'modifier_id' => 'required|exists:modifiers,id',
            'sort_order' => 'integer',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $modifier = Modifier::forRestaurant($restaurantId)->findOrFail($validated['modifier_id']);
        // Также проверяем, что блюдо принадлежит этому ресторану
        \App\Models\Dish::forRestaurant($restaurantId)->findOrFail($validated['dish_id']);
        $modifier->dishes()->syncWithoutDetaching([
            $validated['dish_id'] => [
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => true,
            ]
        ]);

        return response()->json(['message' => 'Attached']);
    }

    // Отвязать модификатор от блюда
    public function detachFromDish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'modifier_id' => 'required|exists:modifiers,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $modifier = Modifier::forRestaurant($restaurantId)->findOrFail($validated['modifier_id']);
        $modifier->dishes()->detach($validated['dish_id']);

        return response()->json(['message' => 'Detached']);
    }

    // Получить модификаторы блюда
    public function dishModifiers(int $dishId): JsonResponse
    {
        $modifiers = Modifier::whereHas('dishes', function ($q) use ($dishId) {
            $q->where('dish_id', $dishId);
        })
            ->with(['options.ingredients'])
            ->orderBy('sort_order')
            ->get();

        return response()->json($modifiers);
    }

    // Сохранить модификаторы блюда (массовое обновление)
    public function saveDishModifiers(Request $request, int $dishId): JsonResponse
    {
        $validated = $request->validate([
            'modifier_ids' => 'array',
            'modifier_ids.*' => 'exists:modifiers,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $syncData = [];
        foreach ($validated['modifier_ids'] ?? [] as $index => $modifierId) {
            $syncData[$modifierId] = [
                'sort_order' => $index,
                'is_active' => true,
            ];
        }

        $dish = \App\Models\Dish::forRestaurant($restaurantId)->findOrFail($dishId);
        $dish->modifiers()->sync($syncData);

        return response()->json(['message' => 'Saved']);
    }
}
