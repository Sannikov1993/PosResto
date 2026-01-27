<?php

namespace App\Services;

/**
 * Сервис расчёта ETA (Estimated Time of Arrival)
 *
 * Рассчитывает примерное время доставки на основе:
 * - Расстояния между точками
 * - Типа транспорта курьера
 * - Городских условий (буфер на пробки, парковку)
 */
class EtaCalculationService
{
    private GeocodingService $geocoding;

    /**
     * Средние скорости по типам транспорта (км/ч)
     * Учитывают городские условия: светофоры, пробки, пешеходы
     */
    private const TRANSPORT_SPEEDS = [
        'car' => 25,      // Авто в городе с учётом пробок
        'scooter' => 20,  // Мотороллер/скутер
        'bike' => 15,     // Велосипед
        'foot' => 5,      // Пешком
    ];

    /**
     * Буферное время (мин) на:
     * - Поиск парковки
     * - Подъём в подъезд
     * - Поиск квартиры
     */
    private const BUFFER_MINUTES = 3;

    public function __construct(GeocodingService $geocoding)
    {
        $this->geocoding = $geocoding;
    }

    /**
     * Рассчитать ETA от текущей позиции до точки назначения
     *
     * @param float $fromLat Широта текущей позиции
     * @param float $fromLng Долгота текущей позиции
     * @param float $toLat Широта назначения
     * @param float $toLng Долгота назначения
     * @param string $transportType Тип транспорта: car, scooter, bike, foot
     * @return array ['minutes' => int, 'distance_km' => float, 'label' => string]
     */
    public function calculate(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        string $transportType = 'car'
    ): array {
        // Рассчитываем расстояние по прямой
        $distance = $this->geocoding->calculateDistance($fromLat, $fromLng, $toLat, $toLng);

        // Коэффициент извилистости дорог (прямое расстояние vs реальный путь)
        // В городе обычно 1.3-1.5
        $roadFactor = 1.35;
        $actualDistance = $distance * $roadFactor;

        // Получаем скорость для типа транспорта
        $speed = self::TRANSPORT_SPEEDS[$transportType] ?? self::TRANSPORT_SPEEDS['car'];

        // Время в пути (часы -> минуты)
        $travelMinutes = ($actualDistance / $speed) * 60;

        // Итоговое время с буфером
        $totalMinutes = max(1, round($travelMinutes + self::BUFFER_MINUTES));

        return [
            'minutes' => (int) $totalMinutes,
            'distance_km' => round($distance, 2),
            'actual_distance_km' => round($actualDistance, 2),
            'label' => $this->formatLabel($totalMinutes),
            'transport' => $transportType,
            'speed_kmh' => $speed,
        ];
    }

    /**
     * Рассчитать ETA для заказа
     *
     * @param \App\Models\Order $order
     * @param array|null $courierLocation ['lat' => float, 'lng' => float]
     * @return array|null
     */
    public function calculateForOrder($order, ?array $courierLocation = null): ?array
    {
        // Если нет координат доставки - невозможно рассчитать
        if (!$order->delivery_latitude || !$order->delivery_longitude) {
            return null;
        }

        // Определяем позицию курьера
        $fromLat = null;
        $fromLng = null;

        if ($courierLocation) {
            $fromLat = $courierLocation['lat'] ?? null;
            $fromLng = $courierLocation['lng'] ?? null;
        } elseif ($order->courier) {
            $location = $order->courier->courier_last_location;
            if (is_array($location)) {
                $fromLat = $location['lat'] ?? null;
                $fromLng = $location['lng'] ?? null;
            }
        }

        // Если нет позиции курьера - используем позицию ресторана
        if (!$fromLat || !$fromLng) {
            $fromLat = config('services.yandex.restaurant_lat');
            $fromLng = config('services.yandex.restaurant_lng');
        }

        if (!$fromLat || !$fromLng) {
            return null;
        }

        // Определяем тип транспорта курьера
        $transportType = 'car';
        if ($order->courier && method_exists($order->courier, 'getAttribute')) {
            // Проверяем есть ли связанный Courier
            $courierModel = \App\Models\Courier::where('user_id', $order->courier_id)->first();
            if ($courierModel) {
                $transportType = $courierModel->transport ?? 'car';
            }
        }

        return $this->calculate(
            $fromLat,
            $fromLng,
            $order->delivery_latitude,
            $order->delivery_longitude,
            $transportType
        );
    }

    /**
     * Форматировать ETA для отображения
     *
     * @param int $minutes
     * @return string
     */
    private function formatLabel(int $minutes): string
    {
        if ($minutes <= 1) {
            return 'Меньше минуты';
        }

        if ($minutes < 5) {
            return '~5 мин';
        }

        if ($minutes <= 60) {
            // Округляем до 5 минут
            $rounded = ceil($minutes / 5) * 5;
            return "~{$rounded} мин";
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return "~{$hours} ч";
        }

        // Округляем минуты до 15
        $roundedMins = ceil($mins / 15) * 15;
        if ($roundedMins >= 60) {
            $hours++;
            $roundedMins = 0;
        }

        if ($roundedMins === 0) {
            return "~{$hours} ч";
        }

        return "~{$hours} ч {$roundedMins} мин";
    }

    /**
     * Проверить, опаздывает ли курьер
     *
     * @param \App\Models\Order $order
     * @param array|null $courierLocation
     * @return array ['is_late' => bool, 'delay_minutes' => int|null]
     */
    public function checkDelay($order, ?array $courierLocation = null): array
    {
        $eta = $this->calculateForOrder($order, $courierLocation);

        if (!$eta) {
            return ['is_late' => false, 'delay_minutes' => null];
        }

        // Определяем ожидаемое время доставки
        $expectedDeliveryTime = $order->desired_delivery_time
            ?? $order->created_at->addMinutes($order->estimated_delivery_minutes ?? 45);

        // Рассчитываем предполагаемое время прибытия
        $estimatedArrival = now()->addMinutes($eta['minutes']);

        // Проверяем опоздание
        $isLate = $estimatedArrival->gt($expectedDeliveryTime);
        $delayMinutes = $isLate
            ? $estimatedArrival->diffInMinutes($expectedDeliveryTime)
            : null;

        return [
            'is_late' => $isLate,
            'delay_minutes' => $delayMinutes,
            'expected_at' => $expectedDeliveryTime->toIso8601String(),
            'estimated_arrival' => $estimatedArrival->toIso8601String(),
        ];
    }
}
