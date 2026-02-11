<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiscountSettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Получить настройки ручных скидок (для POS)
     */
    public function manualDiscountSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        $defaults = [
            'preset_percentages' => [5, 10, 15, 20],
            'max_discount_without_pin' => 20,
            'allow_custom_percent' => true,
            'allow_fixed_amount' => true,
            'require_reason' => false,
            'reasons' => [
                ['id' => 'birthday', 'label' => 'День рождения'],
                ['id' => 'regular', 'label' => 'Постоянный клиент'],
                ['id' => 'complaint', 'label' => 'Жалоба/компенсация'],
                ['id' => 'manager', 'label' => 'Скидка менеджера'],
                ['id' => 'staff', 'label' => 'Сотрудник'],
                ['id' => 'promo', 'label' => 'Акция ресторана'],
                ['id' => 'other', 'label' => 'Другое'],
            ],
        ];

        $settings = $restaurant?->getSetting('manual_discounts', []) ?? [];

        // Ensure all default fields exist
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Сохранить настройки ручных скидок (для бекофиса)
     */
    public function updateManualDiscountSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset_percentages' => 'nullable|array',
            'preset_percentages.*' => 'integer|min:1|max:100',
            'max_discount_without_pin' => 'nullable|integer|min:0|max:100',
            'allow_custom_percent' => 'nullable|boolean',
            'allow_fixed_amount' => 'nullable|boolean',
            'require_reason' => 'nullable|boolean',
            'reasons' => 'nullable|array',
            'reasons.*.id' => 'required|string|max:50',
            'reasons.*.label' => 'required|string|max:100',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $currentSettings = $restaurant->getSetting('manual_discounts', []);
        $newSettings = array_merge($currentSettings, $validated);

        $restaurant->setSetting('manual_discounts', $newSettings);

        return response()->json([
            'success' => true,
            'message' => 'Настройки скидок сохранены',
            'data' => $newSettings,
        ]);
    }
}
