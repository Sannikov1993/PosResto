<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrintSettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Получить настройки печати по умолчанию
     */
    public function printSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        $defaults = [
            // Автопечать
            'auto_print_receipt' => false,
            'auto_print_kitchen' => true,
            'auto_print_new_items' => true,
            'receipt_copies' => 1,
            'kitchen_copies' => 1,

            // Шапка чека
            'receipt_header_name' => '',
            'receipt_header_address' => '',
            'receipt_header_phone' => '',
            'receipt_header_inn' => '',

            // Настройки печати
            'print_logo' => false,
            'print_qr' => false,
            'qr_url' => '',
            'qr_text' => 'Сканируйте для отзыва',

            // Отображение на чеке гостя
            'show_waiter' => true,
            'show_table' => true,
            'show_guests_count' => false,
            'show_order_number' => true,
            'show_order_time' => true,
            'show_payment_method' => true,

            // Футер чека
            'receipt_footer_line1' => 'Спасибо за визит!',
            'receipt_footer_line2' => 'Ждем вас снова!',

            // Футер доставки
            'delivery_footer_line1' => 'Спасибо за заказ!',
            'delivery_footer_line2' => 'Приятного аппетита!',

            // Отображение на чеке доставки
            'delivery_show_customer' => true,
            'delivery_show_phone' => true,
            'delivery_show_address' => true,
            'delivery_show_entrance' => true,
            'delivery_show_intercom' => true,
            'delivery_show_courier' => true,
            'delivery_show_comment' => true,

            // Кухня
            'kitchen_beep' => true,
            'kitchen_large_font' => true,
            'kitchen_bold_items' => true,
            'kitchen_header_text' => 'НОВЫЙ ЗАКАЗ',
            'kitchen_show_table' => true,
            'kitchen_show_waiter' => true,
            'kitchen_show_order_number' => true,
            'kitchen_show_time' => true,
            'kitchen_show_order_type' => true,
            'kitchen_show_modifiers' => true,
            'kitchen_show_comments' => true,

            // Пречек
            'precheck_title' => 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ',
            'precheck_subtitle' => '(не является фискальным документом)',
            'precheck_show_table' => true,
            'precheck_show_waiter' => true,
            'precheck_show_date' => true,
            'precheck_show_guests' => false,
            'precheck_footer' => 'Приятного аппетита!',
        ];

        $settings = $restaurant?->getSetting('print', []) ?? [];

        return response()->json([
            'success' => true,
            'data' => array_merge($defaults, $settings),
        ]);
    }

    /**
     * Обновить настройки печати
     */
    public function updatePrintSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Автопечать
            'auto_print_receipt' => 'nullable|boolean',
            'auto_print_kitchen' => 'nullable|boolean',
            'auto_print_new_items' => 'nullable|boolean',
            'receipt_copies' => 'nullable|integer|min:1|max:5',
            'kitchen_copies' => 'nullable|integer|min:1|max:5',

            // Шапка чека
            'receipt_header_name' => 'nullable|string|max:100',
            'receipt_header_address' => 'nullable|string|max:200',
            'receipt_header_phone' => 'nullable|string|max:50',
            'receipt_header_inn' => 'nullable|string|max:20',

            // Настройки печати
            'print_logo' => 'nullable|boolean',
            'print_qr' => 'nullable|boolean',
            'qr_url' => 'nullable|string|max:200',
            'qr_text' => 'nullable|string|max:100',

            // Отображение на чеке гостя
            'show_waiter' => 'nullable|boolean',
            'show_table' => 'nullable|boolean',
            'show_guests_count' => 'nullable|boolean',
            'show_order_number' => 'nullable|boolean',
            'show_order_time' => 'nullable|boolean',
            'show_payment_method' => 'nullable|boolean',

            // Футер чека
            'receipt_footer_line1' => 'nullable|string|max:100',
            'receipt_footer_line2' => 'nullable|string|max:100',

            // Футер доставки
            'delivery_footer_line1' => 'nullable|string|max:100',
            'delivery_footer_line2' => 'nullable|string|max:100',

            // Отображение на чеке доставки
            'delivery_show_customer' => 'nullable|boolean',
            'delivery_show_phone' => 'nullable|boolean',
            'delivery_show_address' => 'nullable|boolean',
            'delivery_show_entrance' => 'nullable|boolean',
            'delivery_show_intercom' => 'nullable|boolean',
            'delivery_show_courier' => 'nullable|boolean',
            'delivery_show_comment' => 'nullable|boolean',

            // Кухня
            'kitchen_beep' => 'nullable|boolean',
            'kitchen_large_font' => 'nullable|boolean',
            'kitchen_bold_items' => 'nullable|boolean',
            'kitchen_header_text' => 'nullable|string|max:50',
            'kitchen_show_table' => 'nullable|boolean',
            'kitchen_show_waiter' => 'nullable|boolean',
            'kitchen_show_order_number' => 'nullable|boolean',
            'kitchen_show_time' => 'nullable|boolean',
            'kitchen_show_order_type' => 'nullable|boolean',
            'kitchen_show_modifiers' => 'nullable|boolean',
            'kitchen_show_comments' => 'nullable|boolean',

            // Пречек
            'precheck_title' => 'nullable|string|max:100',
            'precheck_subtitle' => 'nullable|string|max:100',
            'precheck_show_table' => 'nullable|boolean',
            'precheck_show_waiter' => 'nullable|boolean',
            'precheck_show_date' => 'nullable|boolean',
            'precheck_show_guests' => 'nullable|boolean',
            'precheck_footer' => 'nullable|string|max:100',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        // Убираем null значения, чтобы они не перезаписывали существующие настройки
        $validated = array_filter($validated, fn($value) => $value !== null);

        $currentSettings = $restaurant->getSetting('print', []);
        $newSettings = array_merge($currentSettings, $validated);

        $restaurant->setSetting('print', $newSettings);

        return response()->json([
            'success' => true,
            'message' => 'Настройки печати сохранены',
            'data' => $newSettings,
        ]);
    }
}
