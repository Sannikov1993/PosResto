<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use ResolvesRestaurantId;

    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::where('restaurant_id', $this->getRestaurantId($request))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $warehouse,
        ]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);

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

    public function update(Request $request, Warehouse $warehouse): JsonResponse
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

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить склад с остатками товаров',
            ], 422);
        }

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

    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Warehouse::getTypes(),
        ]);
    }
}
