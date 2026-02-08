<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ResolvesRestaurantId;

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::where('restaurant_id', $this->getRestaurantId($request))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    public function store(Request $request): JsonResponse
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
            'restaurant_id' => $this->getRestaurantId($request),
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Поставщик добавлен',
            'data' => $supplier,
        ], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
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

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->invoices()->count() > 0) {
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
}
