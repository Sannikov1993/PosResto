<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\LegalEntity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CashRegisterController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список кассовых аппаратов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $legalEntityId = $request->input('legal_entity_id');

        $query = CashRegister::where('restaurant_id', $restaurantId)
            ->with('legalEntity:id,name,short_name,type');

        if ($legalEntityId) {
            $query->where('legal_entity_id', $legalEntityId);
        }

        $registers = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $registers,
        ]);
    }

    /**
     * Создать кассовый аппарат
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'legal_entity_id' => 'required|integer|exists:legal_entities,id',
            'name' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:20',
            'fn_number' => 'nullable|string|max:20',
            'fn_expires_at' => 'nullable|date',
            'ofd_name' => 'nullable|string|max:255',
            'ofd_inn' => 'nullable|string|max:12',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Устанавливаем restaurant_id если не передан
        if (empty($validated['restaurant_id'])) {
            $validated['restaurant_id'] = $this->getRestaurantId($request);
        }

        // Если это первая касса для юрлица - делаем её по умолчанию
        $existingCount = CashRegister::where('legal_entity_id', $validated['legal_entity_id'])->count();
        if ($existingCount === 0) {
            $validated['is_default'] = true;
        }

        $register = CashRegister::create($validated);

        // Если указано is_default, снимаем флаг с других
        if ($register->is_default) {
            $register->makeDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Касса создана',
            'data' => $register->load('legalEntity:id,name,short_name,type'),
        ], 201);
    }

    /**
     * Получить кассовый аппарат
     */
    public function show(CashRegister $cashRegister): JsonResponse
    {
        $cashRegister->load('legalEntity');

        return response()->json([
            'success' => true,
            'data' => $cashRegister,
        ]);
    }

    /**
     * Обновить кассовый аппарат
     */
    public function update(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $validated = $request->validate([
            'legal_entity_id' => 'sometimes|integer|exists:legal_entities,id',
            'name' => 'sometimes|string|max:100',
            'serial_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:20',
            'fn_number' => 'nullable|string|max:20',
            'fn_expires_at' => 'nullable|date',
            'ofd_name' => 'nullable|string|max:255',
            'ofd_inn' => 'nullable|string|max:12',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $cashRegister->update($validated);

        // Если указано is_default, снимаем флаг с других
        if (isset($validated['is_default']) && $validated['is_default']) {
            $cashRegister->makeDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Касса обновлена',
            'data' => $cashRegister->fresh('legalEntity:id,name,short_name,type'),
        ]);
    }

    /**
     * Удалить кассовый аппарат
     */
    public function destroy(CashRegister $cashRegister): JsonResponse
    {
        // Проверяем, нет ли открытых смен на этой кассе
        $openShifts = $cashRegister->cashShifts()->where('status', 'open')->count();
        if ($openShifts > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить кассу с открытой сменой',
            ], 422);
        }

        // Если это касса по умолчанию - назначаем другую
        if ($cashRegister->is_default) {
            $another = CashRegister::where('legal_entity_id', $cashRegister->legal_entity_id)
                ->where('id', '!=', $cashRegister->id)
                ->where('is_active', true)
                ->first();

            if ($another) {
                $another->makeDefault();
            }
        }

        $cashRegister->delete();

        return response()->json([
            'success' => true,
            'message' => 'Касса удалена',
        ]);
    }

    /**
     * Сделать кассу по умолчанию для юрлица
     */
    public function makeDefault(CashRegister $cashRegister): JsonResponse
    {
        $cashRegister->makeDefault();

        return response()->json([
            'success' => true,
            'message' => 'Касса установлена по умолчанию',
            'data' => $cashRegister,
        ]);
    }
}
