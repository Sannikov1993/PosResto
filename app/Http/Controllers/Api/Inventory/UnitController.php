<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ResolvesRestaurantId;

    public function index(Request $request): JsonResponse
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'short_name' => 'required|string|max:10',
            'type' => 'nullable|string|in:weight,volume,piece,length',
        ]);

        $unit = Unit::create([
            'restaurant_id' => $this->getRestaurantId($request),
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

    public function update(Request $request, Unit $unit): JsonResponse
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

    public function destroy(Unit $unit): JsonResponse
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
}
