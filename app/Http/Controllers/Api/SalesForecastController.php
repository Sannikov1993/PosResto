<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\ForecastService;
use App\Domain\Order\Enums\OrderStatus;

class SalesForecastController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // ПРОГНОЗ ПРОДАЖ
    // ==========================================

    public function salesForecast(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $forecastDays = $request->input('days', 7);

        // Берём данные за последние 8 недель для анализа паттернов
        $weeksBack = 8;
        $dateFrom = TimeHelper::now($restaurantId)->subWeeks($weeksBack)->startOfDay();

        // Получаем дневные продажи
        $dailySales = Order::where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('created_at', '>=', $dateFrom)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date', 'day_of_week')
            ->orderBy('date')
            ->get();

        if ($dailySales->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['forecast' => [], 'historical' => []],
            ]);
        }

        // Средние по дням недели
        $avgByDayOfWeek = [];
        $countByDayOfWeek = [];

        foreach ($dailySales as $day) {
            $dow = $day->day_of_week;
            if (!isset($avgByDayOfWeek[$dow])) {
                $avgByDayOfWeek[$dow] = ['revenue' => 0, 'orders' => 0];
                $countByDayOfWeek[$dow] = 0;
            }
            $avgByDayOfWeek[$dow]['revenue'] += $day->revenue;
            $avgByDayOfWeek[$dow]['orders'] += $day->orders_count;
            $countByDayOfWeek[$dow]++;
        }

        foreach ($avgByDayOfWeek as $dow => $data) {
            $count = $countByDayOfWeek[$dow];
            $avgByDayOfWeek[$dow]['revenue'] = round($data['revenue'] / $count, 2);
            $avgByDayOfWeek[$dow]['orders'] = round($data['orders'] / $count, 1);
        }

        // Тренд (простая линейная регрессия)
        $n = $dailySales->count();
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;

        foreach ($dailySales->values() as $i => $day) {
            $x = $i;
            $y = $day->revenue;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = 0;
        if ($n > 1 && ($n * $sumX2 - $sumX * $sumX) != 0) {
            $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        }

        // Генерируем прогноз
        $forecast = [];
        $today = TimeHelper::today($restaurantId);

        for ($i = 0; $i < $forecastDays; $i++) {
            $date = $today->copy()->addDays($i);
            $dow = $date->dayOfWeek + 1; // Carbon: 0=Sunday, MySQL DAYOFWEEK: 1=Sunday

            $baseRevenue = $avgByDayOfWeek[$dow]['revenue'] ?? 0;
            $baseOrders = $avgByDayOfWeek[$dow]['orders'] ?? 0;

            // Применяем тренд
            $trendFactor = 1 + ($slope * $i / max(1, $baseRevenue)) * 0.1;
            $trendFactor = max(0.8, min(1.2, $trendFactor)); // Ограничиваем ±20%

            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $this->getDayName($date->dayOfWeek),
                'predicted_revenue' => round($baseRevenue * $trendFactor, 2),
                'predicted_orders' => round($baseOrders * $trendFactor),
                'confidence' => $countByDayOfWeek[$dow] ?? 0 >= 4 ? 'high' : 'low',
            ];
        }

        // Исторические данные для графика
        $historical = $dailySales->map(function ($day) {
            return [
                'date' => $day->date,
                'revenue' => $day->revenue,
                'orders' => $day->orders_count,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'forecast' => $forecast,
                'historical' => $historical,
                'avg_by_day' => $avgByDayOfWeek,
                'trend_slope' => round($slope, 2),
            ],
        ]);
    }

    // ==========================================
    // УЛУЧШЕННЫЙ ПРОГНОЗ
    // ==========================================

    public function enhancedForecast(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecast($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastByCategory(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastByCategory($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastIngredients(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastIngredients($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function forecastStaff(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $days = $request->input("days", 7);

        $service = new ForecastService();
        $data = $service->forecastStaff($restaurantId, $days);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    private function getDayName($dayOfWeek): string
    {
        $days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
        return $days[$dayOfWeek] ?? '';
    }
}
