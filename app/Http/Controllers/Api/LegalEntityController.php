<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Models\CashRegister;
use App\Services\LegalEntityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class LegalEntityController extends Controller
{
    use Traits\ResolvesRestaurantId;

    protected LegalEntityService $legalEntityService;

    public function __construct(LegalEntityService $legalEntityService)
    {
        $this->legalEntityService = $legalEntityService;
    }

    /**
     * Список юридических лиц
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $entities = LegalEntity::where('restaurant_id', $restaurantId)
            ->withCount(['categories', 'cashRegisters'])
            ->with(['cashRegisters' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entities,
        ]);
    }

    /**
     * Создать юридическое лицо
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'type' => ['required', Rule::in(['llc', 'ie'])],
            'inn' => 'required|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'ogrn' => 'nullable|string|max:15',
            'legal_address' => 'nullable|string',
            'actual_address' => 'nullable|string',
            'director_name' => 'nullable|string|max:255',
            'director_position' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_bik' => 'nullable|string|max:9',
            'bank_account' => 'nullable|string|max:20',
            'bank_corr_account' => 'nullable|string|max:20',
            'taxation_system' => ['nullable', Rule::in(['osn', 'usn_income', 'usn_income_expense', 'patent'])],
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'has_alcohol_license' => 'nullable|boolean',
            'alcohol_license_number' => 'nullable|string|max:100',
            'alcohol_license_expires_at' => 'nullable|date',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Устанавливаем restaurant_id если не передан
        $restaurantId = $validated['restaurant_id'] ?? $this->getRestaurantId($request);
        $validated['restaurant_id'] = $restaurantId;

        // Устанавливаем tenant_id из ресторана
        $restaurant = \App\Models\Restaurant::find($restaurantId);
        $validated['tenant_id'] = $restaurant?->tenant_id;

        // Если это первое юрлицо - делаем его по умолчанию
        $existingCount = LegalEntity::where('restaurant_id', $restaurantId)->count();
        if ($existingCount === 0) {
            $validated['is_default'] = true;
        }

        $entity = LegalEntity::create($validated);

        // Если указано is_default, снимаем флаг с других
        if ($entity->is_default) {
            $entity->makeDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Юридическое лицо создано',
            'data' => $entity->load('cashRegisters'),
        ], 201);
    }

    /**
     * Получить юридическое лицо
     */
    public function show(LegalEntity $legalEntity): JsonResponse
    {
        $legalEntity->load(['cashRegisters', 'categories']);
        $legalEntity->loadCount(['categories', 'cashRegisters']);

        return response()->json([
            'success' => true,
            'data' => $legalEntity,
        ]);
    }

    /**
     * Обновить юридическое лицо
     */
    public function update(Request $request, LegalEntity $legalEntity): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'type' => ['sometimes', Rule::in(['llc', 'ie'])],
            'inn' => 'sometimes|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'ogrn' => 'nullable|string|max:15',
            'legal_address' => 'nullable|string',
            'actual_address' => 'nullable|string',
            'director_name' => 'nullable|string|max:255',
            'director_position' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_bik' => 'nullable|string|max:9',
            'bank_account' => 'nullable|string|max:20',
            'bank_corr_account' => 'nullable|string|max:20',
            'taxation_system' => ['nullable', Rule::in(['osn', 'usn_income', 'usn_income_expense', 'patent'])],
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'has_alcohol_license' => 'nullable|boolean',
            'alcohol_license_number' => 'nullable|string|max:100',
            'alcohol_license_expires_at' => 'nullable|date',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $legalEntity->update($validated);

        // Если указано is_default, снимаем флаг с других
        if (isset($validated['is_default']) && $validated['is_default']) {
            $legalEntity->makeDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Юридическое лицо обновлено',
            'data' => $legalEntity->fresh(['cashRegisters']),
        ]);
    }

    /**
     * Удалить юридическое лицо (soft delete)
     */
    public function destroy(LegalEntity $legalEntity): JsonResponse
    {
        // Проверяем, нет ли привязанных категорий
        $categoriesCount = $legalEntity->categories()->count();
        if ($categoriesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Нельзя удалить: к юрлицу привязано {$categoriesCount} категорий",
            ], 422);
        }

        // Если это юрлицо по умолчанию - назначаем другое
        if ($legalEntity->is_default) {
            $another = LegalEntity::where('restaurant_id', $legalEntity->restaurant_id)
                ->where('id', '!=', $legalEntity->id)
                ->where('is_active', true)
                ->first();

            if ($another) {
                $another->makeDefault();
            }
        }

        $legalEntity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Юридическое лицо удалено',
        ]);
    }

    /**
     * Сделать юрлицо по умолчанию
     */
    public function makeDefault(LegalEntity $legalEntity): JsonResponse
    {
        $legalEntity->makeDefault();

        return response()->json([
            'success' => true,
            'message' => 'Юридическое лицо установлено по умолчанию',
            'data' => $legalEntity,
        ]);
    }

    /**
     * Справочники (типы, системы налогообложения, ставки НДС)
     */
    public function dictionaries(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'types' => LegalEntity::getTypeLabels(),
                'taxation_systems' => LegalEntity::getTaxationLabels(),
                'vat_rates' => LegalEntity::getVatRates(),
            ],
        ]);
    }
}
