<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PriceListController extends Controller
{
    /**
     * Список прайс-листов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $priceLists = PriceList::withCount('items')
            ->where('restaurant_id', $restaurantId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $priceLists,
        ]);
    }

    /**
     * Создать прайс-лист
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Если ставим по умолчанию — снимаем у остальных
        if (!empty($validated['is_default'])) {
            PriceList::where('restaurant_id', $restaurantId)
                ->update(['is_default' => false]);
        }

        $priceList = PriceList::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Прайс-лист создан',
            'data' => $priceList->loadCount('items'),
        ], 201);
    }

    /**
     * Получить прайс-лист с позициями
     */
    public function show(PriceList $priceList): JsonResponse
    {
        $priceList->load('items.dish');
        $priceList->loadCount('items');

        return response()->json([
            'success' => true,
            'data' => $priceList,
        ]);
    }

    /**
     * Обновить прайс-лист
     */
    public function update(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Если ставим по умолчанию — снимаем у остальных
        if (!empty($validated['is_default'])) {
            PriceList::where('restaurant_id', $priceList->restaurant_id)
                ->where('id', '!=', $priceList->id)
                ->update(['is_default' => false]);
        }

        $priceList->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Прайс-лист обновлён',
            'data' => $priceList->loadCount('items'),
        ]);
    }

    /**
     * Удалить прайс-лист (soft delete)
     */
    public function destroy(PriceList $priceList): JsonResponse
    {
        $priceList->delete();

        return response()->json([
            'success' => true,
            'message' => 'Прайс-лист удалён',
        ]);
    }

    /**
     * Включить/выключить прайс-лист
     */
    public function toggle(PriceList $priceList): JsonResponse
    {
        $priceList->update(['is_active' => !$priceList->is_active]);

        return response()->json([
            'success' => true,
            'message' => $priceList->is_active ? 'Прайс-лист активирован' : 'Прайс-лист деактивирован',
            'data' => $priceList,
        ]);
    }

    /**
     * Сделать прайс-лист по умолчанию
     */
    public function setDefault(PriceList $priceList): JsonResponse
    {
        PriceList::where('restaurant_id', $priceList->restaurant_id)
            ->update(['is_default' => false]);

        $priceList->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Прайс-лист установлен по умолчанию',
            'data' => $priceList,
        ]);
    }

    /**
     * Получить позиции прайс-листа
     */
    public function items(PriceList $priceList): JsonResponse
    {
        $items = $priceList->items()->with('dish')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Сохранить позиции прайс-листа (bulk)
     */
    public function saveItems(Request $request, PriceList $priceList): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.dish_id' => 'required|exists:dishes,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            PriceListItem::updateOrCreate(
                [
                    'price_list_id' => $priceList->id,
                    'dish_id' => $item['dish_id'],
                ],
                [
                    'price' => $item['price'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Позиции сохранены',
            'data' => $priceList->items()->with('dish')->get(),
        ]);
    }

    /**
     * Удалить позицию из прайс-листа
     */
    public function removeItem(PriceList $priceList, int $dishId): JsonResponse
    {
        $priceList->items()
            ->where('dish_id', $dishId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Позиция удалена из прайс-листа',
        ]);
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        if ($request->has('restaurant_id') && $user) {
            if ($user->isSuperAdmin()) {
                return (int) $request->restaurant_id;
            }
            $restaurant = \App\Models\Restaurant::where('id', $request->restaurant_id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
            if ($restaurant) {
                return $restaurant->id;
            }
        }

        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        abort(401, 'Требуется авторизация');
    }
}
