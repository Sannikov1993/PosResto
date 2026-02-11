<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PosSettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Получить настройки POS-терминала
     */
    public function posSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::withoutGlobalScope('tenant')->find($restaurantId);

        $defaults = [
            'theme' => 'dark',
            'fontSize' => 'medium',
            'tileSize' => 'standard',
            'showDishPhotos' => true,
            'showCalories' => false,
            'floorScale' => 100,
            'enableAnimations' => true,
            'autoOpenShift' => false,
            'confirmCloseShift' => true,
            'roundingMode' => 'none',
            'defaultPaymentMethod' => 'cash',
            'autoPrintPrecheck' => false,
            'requireCancelComment' => true,
            'autoPrintReceipt' => false,
            'autoPrintKitchen' => true,
            'receiptCopies' => 1,
            'kitchenCopies' => 1,
            'defaultPrinter' => null,
            'paperWidth' => 80,
            'printLogo' => true,
            'receiptFooter' => 'Спасибо за визит!',
            'soundNewOrder' => true,
            'soundOrderReady' => true,
            'soundWaiterCall' => true,
            'soundVolume' => 70,
            'enableVibration' => true,
            'quickDishes' => [],
            'quickDiscounts' => [5, 10, 15, 20],
            'showChangeCalculator' => true,
            'minDeliveryAmount' => 500,
            'autoAssignCourier' => false,
            'showDeliveryMap' => true,
            'defaultDeliveryRadius' => 5,
            'autoLogoutMinutes' => 30,
            'requirePinForCancel' => true,
            'requirePinForDiscount' => false,
            'requirePinForRefund' => true,
            'screenLockEnabled' => false,
            'menuRefreshInterval' => 5,
            'offlineModeEnabled' => false,
            'syncInterval' => 15,
            'cacheImages' => true,
        ];

        $settings = $restaurant?->getSetting('pos', []) ?? [];

        return response()->json([
            'success' => true,
            'data' => array_merge($defaults, $settings),
        ]);
    }

    /**
     * Сохранить настройки POS-терминала
     */
    public function updatePosSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_print_receipt' => 'sometimes|boolean',
            'auto_print_kitchen' => 'sometimes|boolean',
            'default_order_type' => 'sometimes|string|in:dine_in,takeaway,delivery',
            'show_order_number' => 'sometimes|boolean',
            'require_customer' => 'sometimes|boolean',
            'allow_discount' => 'sometimes|boolean',
            'max_discount_percent' => 'sometimes|integer|min:0|max:100',
            'default_payment_method' => 'sometimes|string|in:cash,card,mixed',
            'tips_enabled' => 'sometimes|boolean',
            'tips_options' => 'sometimes|array',
            'tips_options.*' => 'integer|min:0|max:100',
            'quick_amounts' => 'sometimes|array',
            'quick_amounts.*' => 'numeric|min:0',
            'lock_screen_enabled' => 'sometimes|boolean',
            'lock_screen_timeout' => 'sometimes|integer|min:0|max:3600',
            'sound_enabled' => 'sometimes|boolean',
            'theme' => 'sometimes|string|in:light,dark,auto',
            'language' => 'sometimes|string|in:ru,en',
            'currency' => 'sometimes|string|max:3',
            'currency_symbol' => 'sometimes|string|max:5',
            'tax_included' => 'sometimes|boolean',
            'tax_rate' => 'sometimes|numeric|min:0|max:100',
            'service_charge_enabled' => 'sometimes|boolean',
            'service_charge_percent' => 'sometimes|numeric|min:0|max:100',
            'table_required' => 'sometimes|boolean',
            'waiter_required' => 'sometimes|boolean',
            'guest_count_required' => 'sometimes|boolean',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::withoutGlobalScope('tenant')->find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $currentSettings = $restaurant->getSetting('pos', []);
        $newSettings = array_merge($currentSettings, $validated);

        $restaurant->setSetting('pos', $newSettings);

        return response()->json([
            'success' => true,
            'message' => 'Настройки POS сохранены',
            'data' => $newSettings,
        ]);
    }
}
