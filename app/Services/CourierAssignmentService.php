<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Сервис умного назначения курьеров
 *
 * Учитывает:
 * - Расстояние до адреса доставки
 * - Текущую загруженность курьера
 * - Тип транспорта
 * - Время с последнего обновления геолокации
 */
class CourierAssignmentService
{
    // Веса для скоринга
    const WEIGHT_DISTANCE = 0.4;      // 40% - расстояние
    const WEIGHT_WORKLOAD = 0.3;      // 30% - загруженность
    const WEIGHT_TRANSPORT = 0.15;    // 15% - тип транспорта
    const WEIGHT_FRESHNESS = 0.15;   // 15% - свежесть геолокации

    // Максимальное количество активных заказов
    const MAX_ACTIVE_ORDERS = 3;

    // Скорость транспорта (км/ч) для расчёта времени
    const TRANSPORT_SPEED = [
        'car' => 30,
        'scooter' => 25,
        'bike' => 15,
        'foot' => 5,
    ];

    private GeocodingService $geocoding;
    private ?int $restaurantId = null;

    public function __construct(GeocodingService $geocoding)
    {
        $this->geocoding = $geocoding;
    }

    /**
     * Установить контекст ресторана для запросов
     */
    public function forRestaurant(int $restaurantId): self
    {
        $this->restaurantId = $restaurantId;
        return $this;
    }

    /**
     * Найти лучшего курьера для заказа
     *
     * @param Order $order Заказ на доставку
     * @param bool $includeScores Включить детальные оценки в результат
     * @return array|null ['courier' => Courier, 'score' => float, 'eta' => int, 'reason' => string]
     */
    public function findBestCourier(Order $order, bool $includeScores = false): ?array
    {
        // Устанавливаем контекст ресторана из заказа
        $this->restaurantId = $order->restaurant_id;

        // Получаем координаты доставки
        $deliveryLat = $order->delivery_latitude;
        $deliveryLng = $order->delivery_longitude;

        // Если нет координат - пробуем геокодировать
        if (!$deliveryLat || !$deliveryLng) {
            $geocoded = $this->geocoding->geocode($order->delivery_address);
            if ($geocoded) {
                $deliveryLat = $geocoded['lat'];
                $deliveryLng = $geocoded['lng'];
            } else {
                Log::warning('CourierAssignment: Не удалось геокодировать адрес', [
                    'order_id' => $order->id,
                    'address' => $order->delivery_address,
                ]);
                return null;
            }
        }

        // Получаем доступных курьеров (только для этого ресторана)
        $couriers = $this->getAvailableCouriers();

        if ($couriers->isEmpty()) {
            return null;
        }

        // Оцениваем каждого курьера
        $scored = $couriers->map(function ($courier) use ($deliveryLat, $deliveryLng) {
            return $this->scoreCourier($courier, $deliveryLat, $deliveryLng);
        })->sortByDesc('total_score');

        $best = $scored->first();

        if (!$best || $best['total_score'] <= 0) {
            return null;
        }

        $result = [
            'courier' => $best['courier'],
            'score' => round($best['total_score'], 2),
            'eta' => $best['eta_minutes'],
            'distance' => round($best['distance'], 1),
            'reason' => $this->formatReason($best),
        ];

        if ($includeScores) {
            $result['all_couriers'] = $scored->values()->toArray();
        }

        Log::info('CourierAssignment: Рекомендация', [
            'order_id' => $order->id ?? null,
            'courier_id' => $best['courier']->id,
            'courier_name' => $best['courier']->name,
            'score' => $result['score'],
            'eta' => $result['eta'],
        ]);

        return $result;
    }

    /**
     * Получить список доступных курьеров с рейтингом для заказа
     */
    public function getRankedCouriers(Order $order): Collection
    {
        // Устанавливаем контекст ресторана из заказа
        $this->restaurantId = $order->restaurant_id;

        $deliveryLat = $order->delivery_latitude;
        $deliveryLng = $order->delivery_longitude;

        if (!$deliveryLat || !$deliveryLng) {
            $geocoded = $this->geocoding->geocode($order->delivery_address);
            if ($geocoded) {
                $deliveryLat = $geocoded['lat'];
                $deliveryLng = $geocoded['lng'];
            }
        }

        $couriers = $this->getAvailableCouriers();

        if (!$deliveryLat || !$deliveryLng) {
            // Без координат - просто по загруженности
            return $couriers->map(function ($courier) {
                $activeOrders = $this->getActiveOrdersCount($courier);
                return [
                    'courier' => $courier,
                    'active_orders' => $activeOrders,
                    'score' => max(0, 100 - $activeOrders * 30),
                    'eta' => null,
                    'distance' => null,
                    'recommended' => $activeOrders === 0,
                ];
            })->sortByDesc('score')->values();
        }

        return $couriers->map(function ($courier) use ($deliveryLat, $deliveryLng) {
            $scored = $this->scoreCourier($courier, $deliveryLat, $deliveryLng);
            return [
                'courier' => $scored['courier'],
                'active_orders' => $scored['active_orders'],
                'score' => round($scored['total_score']),
                'eta' => $scored['eta_minutes'],
                'distance' => round($scored['distance'], 1),
                'recommended' => $scored === $this->getAvailableCouriers()->first(),
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Получить доступных курьеров для текущего ресторана
     */
    private function getAvailableCouriers(): Collection
    {
        $query = Courier::query()
            ->where('is_active', true)
            ->whereIn('status', [Courier::STATUS_AVAILABLE, Courier::STATUS_BUSY]);

        // Фильтруем по ресторану если контекст установлен
        if ($this->restaurantId) {
            $query->where('restaurant_id', $this->restaurantId);
        }

        return $query
            ->withCount(['activeOrders'])
            ->having('active_orders_count', '<', self::MAX_ACTIVE_ORDERS)
            ->get();
    }

    /**
     * Оценить курьера для доставки
     */
    private function scoreCourier(Courier $courier, float $deliveryLat, float $deliveryLng): array
    {
        // 1. Расстояние от курьера до точки доставки
        $distance = $this->calculateDistance($courier, $deliveryLat, $deliveryLng);
        $distanceScore = $this->scoreDistance($distance);

        // 2. Загруженность (количество активных заказов)
        $activeOrders = $this->getActiveOrdersCount($courier);
        $workloadScore = $this->scoreWorkload($activeOrders);

        // 3. Тип транспорта (для дальних доставок лучше авто)
        $transportScore = $this->scoreTransport($courier->transport, $distance);

        // 4. Свежесть геолокации
        $freshnessScore = $this->scoreFreshness($courier->last_location_at);

        // Итоговый скор
        $totalScore =
            $distanceScore * self::WEIGHT_DISTANCE +
            $workloadScore * self::WEIGHT_WORKLOAD +
            $transportScore * self::WEIGHT_TRANSPORT +
            $freshnessScore * self::WEIGHT_FRESHNESS;

        // Штраф если курьер уже занят
        if ($courier->status === Courier::STATUS_BUSY) {
            $totalScore *= 0.7;
        }

        // Рассчитываем ETA
        $speed = self::TRANSPORT_SPEED[$courier->transport] ?? 20;
        $etaMinutes = (int) ceil(($distance / $speed) * 60);

        // Добавляем время на текущие заказы
        if ($activeOrders > 0) {
            $etaMinutes += $courier->estimated_free_time;
        }

        return [
            'courier' => $courier,
            'distance' => $distance,
            'active_orders' => $activeOrders,
            'eta_minutes' => $etaMinutes,
            'scores' => [
                'distance' => round($distanceScore),
                'workload' => round($workloadScore),
                'transport' => round($transportScore),
                'freshness' => round($freshnessScore),
            ],
            'total_score' => $totalScore,
        ];
    }

    /**
     * Рассчитать расстояние от курьера до точки доставки
     */
    private function calculateDistance(Courier $courier, float $deliveryLat, float $deliveryLng): float
    {
        // Если есть геолокация курьера - используем её
        if ($courier->current_lat && $courier->current_lng) {
            return $this->geocoding->calculateDistance(
                $courier->current_lat,
                $courier->current_lng,
                $deliveryLat,
                $deliveryLng
            );
        }

        // Иначе считаем от ресторана
        $restaurantLat = config('services.yandex.restaurant_lat');
        $restaurantLng = config('services.yandex.restaurant_lng');

        if ($restaurantLat && $restaurantLng) {
            return $this->geocoding->calculateDistance(
                $restaurantLat,
                $restaurantLng,
                $deliveryLat,
                $deliveryLng
            );
        }

        return 5.0; // Дефолт 5 км если нет данных
    }

    /**
     * Получить количество активных заказов курьера
     */
    private function getActiveOrdersCount(Courier $courier): int
    {
        // Используем предзагруженный счётчик или считаем
        if (isset($courier->active_orders_count)) {
            return $courier->active_orders_count;
        }

        // Считаем заказы из Order модели (по user_id курьера)
        $query = Order::where('courier_id', $courier->user_id)
            ->where('type', 'delivery')
            ->whereIn('status', ['delivering']);

        // Фильтруем по ресторану если контекст установлен
        if ($this->restaurantId) {
            $query->where('restaurant_id', $this->restaurantId);
        }

        return $query->count();
    }

    /**
     * Оценка по расстоянию (0-100)
     * Чем ближе - тем лучше
     */
    private function scoreDistance(float $distance): float
    {
        if ($distance <= 1) return 100;
        if ($distance <= 2) return 90;
        if ($distance <= 3) return 75;
        if ($distance <= 5) return 60;
        if ($distance <= 7) return 40;
        if ($distance <= 10) return 20;
        return max(0, 10 - $distance);
    }

    /**
     * Оценка по загруженности (0-100)
     * Чем меньше заказов - тем лучше
     */
    private function scoreWorkload(int $activeOrders): float
    {
        return match($activeOrders) {
            0 => 100,
            1 => 60,
            2 => 30,
            default => 0,
        };
    }

    /**
     * Оценка по типу транспорта (0-100)
     * Зависит от расстояния
     */
    private function scoreTransport(string $transport, float $distance): float
    {
        // Для близких доставок (до 2 км) - любой транспорт хорош
        if ($distance <= 2) {
            return match($transport) {
                'scooter' => 100,
                'bike' => 95,
                'car' => 80,
                'foot' => 70,
                default => 50,
            };
        }

        // Для средних (2-5 км)
        if ($distance <= 5) {
            return match($transport) {
                'car' => 100,
                'scooter' => 95,
                'bike' => 60,
                'foot' => 20,
                default => 50,
            };
        }

        // Для дальних (> 5 км)
        return match($transport) {
            'car' => 100,
            'scooter' => 70,
            'bike' => 30,
            'foot' => 0,
            default => 30,
        };
    }

    /**
     * Оценка свежести геолокации (0-100)
     */
    private function scoreFreshness(?\DateTime $lastLocationAt): float
    {
        if (!$lastLocationAt) {
            return 30; // Нет данных - низкий скор
        }

        $minutesAgo = now()->diffInMinutes($lastLocationAt);

        if ($minutesAgo <= 5) return 100;
        if ($minutesAgo <= 15) return 80;
        if ($minutesAgo <= 30) return 60;
        if ($minutesAgo <= 60) return 40;
        return 20;
    }

    /**
     * Форматировать причину рекомендации
     */
    private function formatReason(array $scored): string
    {
        $reasons = [];

        if ($scored['distance'] <= 2) {
            $reasons[] = 'ближайший';
        }

        if ($scored['active_orders'] === 0) {
            $reasons[] = 'свободен';
        } elseif ($scored['active_orders'] === 1) {
            $reasons[] = '1 заказ';
        }

        if ($scored['courier']->transport === 'car' && $scored['distance'] > 3) {
            $reasons[] = 'на авто';
        }

        if (empty($reasons)) {
            $reasons[] = 'лучший по рейтингу';
        }

        return implode(', ', $reasons);
    }

    /**
     * Автоназначение курьера для заказа
     */
    public function autoAssign(Order $order): bool
    {
        $result = $this->findBestCourier($order);

        if (!$result) {
            Log::warning('CourierAssignment: Нет доступных курьеров для автоназначения', [
                'order_id' => $order->id,
            ]);
            return false;
        }

        $courier = $result['courier'];

        $order->update([
            'courier_id' => $courier->user_id,
        ]);

        Log::info('CourierAssignment: Курьер автоназначен', [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'courier_name' => $courier->name,
        ]);

        return true;
    }
}
