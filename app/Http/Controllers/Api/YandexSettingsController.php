<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class YandexSettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Получить настройки Yandex Карт
     */
    public function yandexSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        $defaults = [
            'enabled' => false,
            'api_key' => '',
            'city' => '',
            'restaurant_address' => '',
            'restaurant_lat' => '',
            'restaurant_lng' => '',
        ];

        $settings = $restaurant?->getSetting('yandex', $defaults) ?? $defaults;

        // Маскируем API ключ для безопасности (показываем только последние 8 символов)
        if (!empty($settings['api_key'])) {
            $settings['api_key'] = str_repeat('*', 28) . substr($settings['api_key'], -8);
        }

        return response()->json($settings);
    }

    /**
     * Сохранить настройки Yandex Карт
     */
    public function updateYandexSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'api_key' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'restaurant_address' => 'nullable|string|max:500',
            'restaurant_lat' => 'required|numeric',
            'restaurant_lng' => 'required|numeric',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        // Получаем текущие настройки
        $currentSettings = $restaurant->getSetting('yandex', []);

        // Если ключ замаскирован (начинается с *), используем старый ключ
        if (str_starts_with($validated['api_key'], '*') && !empty($currentSettings['api_key'])) {
            $validated['api_key'] = $currentSettings['api_key'];
        }

        $restaurant->setSetting('yandex', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Настройки Яндекс Карт сохранены',
        ]);
    }

    /**
     * Тест подключения к Yandex Geocoder
     */
    public function testYandexConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');

        // Если ключ замаскирован, берём из БД
        if (str_starts_with($apiKey, '*')) {
            $restaurantId = $this->getRestaurantId($request);
            $restaurant = Restaurant::find($restaurantId);
            $settings = $restaurant?->getSetting('yandex', []) ?? [];
            $apiKey = $settings['api_key'] ?? '';
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'API ключ не указан',
            ]);
        }

        try {
            // Делаем тестовый запрос к геокодеру
            $testAddress = 'Москва, Красная площадь';
            $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
                'apikey' => $apiKey,
                'geocode' => $testAddress,
                'format' => 'json',
                'results' => 1,
            ]);

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (isset($data['response']['GeoObjectCollection'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Подключение успешно',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Неверный ответ от геокодера',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? 'Ошибка подключения: ' . $e->getMessage() : 'Ошибка подключения к геокодеру',
            ]);
        }
    }

    /**
     * Геокодирование адреса ресторана
     */
    public function geocodeRestaurantAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
            'api_key' => 'required|string',
        ]);

        $apiKey = $validated['api_key'];

        // Если ключ замаскирован, берём из БД
        if (str_starts_with($apiKey, '*')) {
            $restaurantId = $this->getRestaurantId($request);
            $restaurant = Restaurant::find($restaurantId);
            $settings = $restaurant?->getSetting('yandex', []) ?? [];
            $apiKey = $settings['api_key'] ?? '';
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'API ключ не указан',
            ]);
        }

        try {
            $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
                'apikey' => $apiKey,
                'geocode' => $validated['address'],
                'format' => 'json',
                'results' => 1,
            ]);

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            $featureMember = $data['response']['GeoObjectCollection']['featureMember'] ?? [];

            if (empty($featureMember)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Адрес не найден',
                ]);
            }

            $geoObject = $featureMember[0]['GeoObject'] ?? null;
            if (!$geoObject) {
                return response()->json([
                    'success' => false,
                    'error' => 'Не удалось получить данные адреса',
                ]);
            }

            // Координаты в Яндексе в формате "долгота широта"
            $pos = $geoObject['Point']['pos'] ?? null;
            if (!$pos) {
                return response()->json([
                    'success' => false,
                    'error' => 'Координаты не найдены',
                ]);
            }

            [$lng, $lat] = explode(' ', $pos);

            return response()->json([
                'success' => true,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'formatted_address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'] ?? $validated['address'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? 'Ошибка геокодирования: ' . $e->getMessage() : 'Ошибка геокодирования адреса',
            ]);
        }
    }
}
