<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DeliveryZone;
use App\Models\Restaurant;

/**
 * Сервис геокодирования через Яндекс Geocoder API
 */
class GeocodingService
{
    private string $apiKey;
    private string $geocodeUrl = 'https://geocode-maps.yandex.ru/1.x/';
    private string $suggestUrl = 'https://suggest-maps.yandex.ru/v1/suggest';
    private ?string $city;
    private ?float $restaurantLat;
    private ?float $restaurantLng;
    private ?int $restaurantId;

    public function __construct(?int $restaurantId = null)
    {
        $this->restaurantId = $restaurantId ?? auth()->user()?->restaurant_id;

        // Пробуем загрузить настройки из базы данных (Cache)
        $settings = $this->getYandexSettings();

        $this->apiKey = $settings['api_key'] ?? config('services.yandex.geocoder_key', '');
        $this->city = $settings['city'] ?? config('services.yandex.city', 'Москва');
        $this->restaurantLat = $settings['restaurant_lat'] ?? config('services.yandex.restaurant_lat');
        $this->restaurantLng = $settings['restaurant_lng'] ?? config('services.yandex.restaurant_lng');
    }

    /**
     * Получить настройки Yandex из базы данных или config
     */
    private function getYandexSettings(): array
    {
        if (!$this->restaurantId) {
            return [];
        }

        // Читаем из БД (поле settings ресторана)
        $restaurant = Restaurant::find($this->restaurantId);
        $settings = $restaurant?->getSetting('yandex', []) ?? [];

        // Если интеграция выключена в настройках - возвращаем пустой массив
        if (!empty($settings) && empty($settings['enabled'])) {
            return [];
        }

        return $settings;
    }

    /**
     * Получить координаты ресторана
     */
    public function getRestaurantCoordinates(): array
    {
        return [
            'lat' => $this->restaurantLat,
            'lng' => $this->restaurantLng,
        ];
    }

    /**
     * Геокодирование адреса - получение координат по адресу
     *
     * @param string $address Адрес для геокодирования
     * @return array|null ['lat' => float, 'lng' => float, 'formatted_address' => string]
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('GeocodingService: API ключ Яндекс не настроен');
            return null;
        }

        // Проверяем кэш
        $cacheKey = 'geocode_' . md5($address);
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Добавляем город к запросу если не указан
        $query = $address;
        if ($this->city && stripos($address, $this->city) === false) {
            $query = $this->city . ', ' . $address;
        }

        try {
            $response = Http::timeout(10)->get($this->geocodeUrl, [
                'apikey' => $this->apiKey,
                'geocode' => $query,
                'format' => 'json',
                'results' => 1,
            ]);

            if (!$response->successful()) {
                Log::error('GeocodingService: Ошибка запроса Яндекс', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $featureMember = $data['response']['GeoObjectCollection']['featureMember'] ?? [];

            if (empty($featureMember)) {
                Log::info('GeocodingService: Адрес не найден', ['address' => $address]);
                return null;
            }

            $geoObject = $featureMember[0]['GeoObject'] ?? null;
            if (!$geoObject) {
                return null;
            }

            // Координаты в Яндексе в формате "долгота широта"
            $pos = $geoObject['Point']['pos'] ?? null;
            if (!$pos) {
                return null;
            }

            [$lng, $lat] = explode(' ', $pos);

            $result = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'formatted_address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'] ?? $address,
            ];

            // Кэшируем на 24 часа
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;

        } catch (\Exception $e) {
            Log::error('GeocodingService: Исключение', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);
            return null;
        }
    }

    /**
     * Получить подсказки адресов (автокомплит)
     *
     * @param string $query Начало адреса
     * @param int $limit Количество результатов
     * @return array
     */
    public function suggest(string $query, int $limit = 5): array
    {
        if (empty($this->apiKey) || strlen($query) < 3) {
            return [];
        }

        // Добавляем город к запросу
        $fullQuery = $query;
        if ($this->city && stripos($query, $this->city) === false) {
            $fullQuery = $this->city . ', ' . $query;
        }

        try {
            $response = Http::timeout(5)->get($this->suggestUrl, [
                'apikey' => $this->apiKey,
                'text' => $fullQuery,
                'types' => 'house,street',
                'results' => $limit,
                'lang' => 'ru',
            ]);

            if (!$response->successful()) {
                // Fallback: используем геокодер для подсказок
                return $this->suggestViaGeocode($fullQuery, $limit);
            }

            $data = $response->json();
            $suggestions = [];

            foreach ($data['results'] ?? [] as $item) {
                $suggestion = [
                    'address' => $item['title']['text'] ?? '',
                    'subtitle' => $item['subtitle']['text'] ?? '',
                    'lat' => null,
                    'lng' => null,
                ];

                // Если есть координаты
                if (isset($item['center'])) {
                    $suggestion['lat'] = $item['center'][1] ?? null;
                    $suggestion['lng'] = $item['center'][0] ?? null;
                }

                $suggestions[] = $suggestion;
            }

            return $suggestions;

        } catch (\Exception $e) {
            Log::error('GeocodingService: Ошибка подсказок', ['message' => $e->getMessage()]);
            // Fallback
            return $this->suggestViaGeocode($fullQuery, $limit);
        }
    }

    /**
     * Подсказки через геокодер (fallback)
     */
    private function suggestViaGeocode(string $query, int $limit = 5): array
    {
        try {
            $response = Http::timeout(5)->get($this->geocodeUrl, [
                'apikey' => $this->apiKey,
                'geocode' => $query,
                'format' => 'json',
                'results' => $limit,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            $suggestions = [];

            foreach ($data['response']['GeoObjectCollection']['featureMember'] ?? [] as $item) {
                $geoObject = $item['GeoObject'] ?? null;
                if (!$geoObject) continue;

                $pos = $geoObject['Point']['pos'] ?? null;
                $lat = null;
                $lng = null;

                if ($pos) {
                    [$lng, $lat] = explode(' ', $pos);
                }

                $suggestions[] = [
                    'address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'] ?? $geoObject['name'] ?? '',
                    'subtitle' => $geoObject['description'] ?? '',
                    'lat' => $lat ? (float) $lat : null,
                    'lng' => $lng ? (float) $lng : null,
                ];
            }

            return $suggestions;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Определить зону доставки по координатам
     *
     * @param float $lat Широта
     * @param float $lng Долгота
     * @param int $restaurantId ID ресторана
     * @return DeliveryZone|null
     */
    public function detectZone(float $lat, float $lng, ?int $restaurantId = null): ?DeliveryZone
    {
        $restaurantId = $restaurantId ?? $this->restaurantId;
        if (!$restaurantId) {
            return null;
        }

        $zones = DeliveryZone::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($zones as $zone) {
            if ($this->isPointInZone($lat, $lng, $zone)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Проверить, находится ли точка в зоне доставки
     *
     * @param float $lat Широта точки
     * @param float $lng Долгота точки
     * @param DeliveryZone $zone Зона доставки
     * @return bool
     */
    public function isPointInZone(float $lat, float $lng, DeliveryZone $zone): bool
    {
        // Если есть полигон - проверяем по нему
        if (!empty($zone->polygon)) {
            return $this->isPointInPolygon($lat, $lng, $zone->polygon);
        }

        // Иначе проверяем по расстоянию от точки ресторана
        if ($this->restaurantLat && $this->restaurantLng) {
            $distance = $this->calculateDistance($this->restaurantLat, $this->restaurantLng, $lat, $lng);
            return $distance >= $zone->min_distance && $distance <= $zone->max_distance;
        }

        return false;
    }

    /**
     * Проверка попадания точки в полигон (алгоритм Ray Casting)
     *
     * @param float $lat Широта
     * @param float $lng Долгота
     * @param array $polygon Массив точек полигона [[lat, lng], ...]
     * @return bool
     */
    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            // Поддержка разных форматов полигона
            $xi = $polygon[$i]['lat'] ?? $polygon[$i][0] ?? 0;
            $yi = $polygon[$i]['lng'] ?? $polygon[$i][1] ?? 0;
            $xj = $polygon[$j]['lat'] ?? $polygon[$j][0] ?? 0;
            $yj = $polygon[$j]['lng'] ?? $polygon[$j][1] ?? 0;

            if ((($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Рассчитать расстояние между двумя точками (формула Haversine)
     *
     * @param float $lat1 Широта точки 1
     * @param float $lng1 Долгота точки 1
     * @param float $lat2 Широта точки 2
     * @param float $lng2 Долгота точки 2
     * @return float Расстояние в километрах
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Радиус Земли в км

        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);

        $a = sin($latDiff / 2) * sin($latDiff / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDiff / 2) * sin($lngDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Полное геокодирование с определением зоны
     *
     * @param string $address Адрес
     * @param int $restaurantId ID ресторана
     * @return array
     */
    public function geocodeWithZone(string $address, ?int $restaurantId = null): array
    {
        $restaurantId = $restaurantId ?? $this->restaurantId;

        $result = [
            'success' => false,
            'coordinates' => null,
            'zone' => null,
            'delivery_cost' => 0,
            'delivery_time' => null,
            'distance' => null,
            'formatted_address' => null,
            'error' => null,
        ];

        // Геокодируем адрес
        $coords = $this->geocode($address);

        if (!$coords) {
            $result['error'] = 'Не удалось определить координаты адреса';
            return $result;
        }

        $result['coordinates'] = $coords;
        $result['formatted_address'] = $coords['formatted_address'];

        // Рассчитываем расстояние от ресторана
        if ($this->restaurantLat && $this->restaurantLng) {
            $result['distance'] = $this->calculateDistance(
                $this->restaurantLat,
                $this->restaurantLng,
                $coords['lat'],
                $coords['lng']
            );
        }

        // Определяем зону
        $zone = $this->detectZone($coords['lat'], $coords['lng'], $restaurantId);

        if ($zone) {
            $result['success'] = true;
            $result['zone'] = $zone;
            $result['delivery_cost'] = $zone->delivery_fee;
            $result['delivery_time'] = $zone->estimated_time;
        } else {
            $result['error'] = 'Адрес находится за пределами зоны доставки';
        }

        return $result;
    }
}
