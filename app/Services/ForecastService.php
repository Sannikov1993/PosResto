<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ForecastService
{
    // Российские праздники (день-месяц)
    private array $holidays = [
        '01-01', '02-01', '03-01', '04-01', '05-01', '06-01', '07-01', '08-01', // Новогодние
        '23-02', // День защитника Отечества
        '08-03', // Международный женский день
        '01-05', // Праздник Весны и Труда
        '09-05', // День Победы
        '12-06', // День России
        '04-11', // День народного единства
    ];

    // Сезонные коэффициенты по месяцам (относительно среднего)
    private array $seasonality = [
        1 => 0.85,  // Январь - после праздников спад
        2 => 0.90,
        3 => 0.95,
        4 => 1.00,
        5 => 1.05,  // Весна - рост
        6 => 1.10,
        7 => 0.95,  // Лето - отпуска
        8 => 0.95,
        9 => 1.05,  // Осень - рост
        10 => 1.10,
        11 => 1.05,
        12 => 1.20,  // Декабрь - праздники
    ];

    /**
     * Улучшенный прогноз продаж
     */
    public function forecast(int $restaurantId, int $days = 7): array
    {
        return Cache::remember("forecast:{$restaurantId}:{$days}", 1800, function () use ($restaurantId, $days) {
            return $this->doForecast($restaurantId, $days);
        });
    }

    private function doForecast(int $restaurantId, int $days = 7): array
    {
        $now = Carbon::now();
        $weeksBack = 12; // Берём больше данных для точности
        $dateFrom = $now->copy()->subWeeks($weeksBack)->startOfDay();

        // Получаем исторические данные
        $dailySales = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
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
            return ['forecast' => [], 'historical' => [], 'confidence' => 'none'];
        }

        // Взвешенное среднее по дням недели (недавние данные важнее)
        $weightedAvg = $this->calculateWeightedAverages($dailySales);

        // Тренд с учётом экспоненциального сглаживания
        $trend = $this->calculateTrend($dailySales);

        // Генерируем прогноз
        $forecast = [];
        $today = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->addDays($i);
            $dow = $date->dayOfWeek + 1; // MySQL: 1=Sunday

            $baseRevenue = $weightedAvg[$dow]['revenue'] ?? 0;
            $baseOrders = $weightedAvg[$dow]['orders'] ?? 0;
            $dataPoints = $weightedAvg[$dow]['count'] ?? 0;

            // Применяем модификаторы
            $seasonalFactor = $this->seasonality[$date->month] ?? 1.0;
            $holidayFactor = $this->getHolidayFactor($date);
            $trendFactor = 1 + ($trend * $i * 0.01); // Постепенное применение тренда

            // Ограничиваем факторы разумными пределами
            $trendFactor = max(0.7, min(1.3, $trendFactor));
            $totalFactor = $seasonalFactor * $holidayFactor * $trendFactor;

            $predictedRevenue = $baseRevenue * $totalFactor;
            $predictedOrders = $baseOrders * $totalFactor;

            // Расчёт доверительного интервала
            $stdDev = $weightedAvg[$dow]['std_dev'] ?? ($baseRevenue * 0.2);
            $confidence = $this->calculateConfidence($dataPoints, $stdDev, $baseRevenue);

            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $this->getDayName($date->dayOfWeek),
                'day_full' => $this->getDayNameFull($date->dayOfWeek),
                'predicted_revenue' => round($predictedRevenue, 2),
                'predicted_orders' => round($predictedOrders),
                'confidence' => $confidence['level'],
                'confidence_percent' => $confidence['percent'],
                'revenue_min' => round($predictedRevenue - $stdDev * 1.5, 2),
                'revenue_max' => round($predictedRevenue + $stdDev * 1.5, 2),
                'is_holiday' => $this->isHoliday($date),
                'is_weekend' => $date->isWeekend(),
                'factors' => [
                    'seasonal' => round($seasonalFactor, 2),
                    'holiday' => round($holidayFactor, 2),
                    'trend' => round($trendFactor, 2),
                ],
            ];
        }

        // Исторические данные для графика
        $historical = $dailySales->map(fn($day) => [
            'date' => $day->date,
            'revenue' => round($day->revenue, 2),
            'orders' => $day->orders_count,
        ])->values();

        return [
            'forecast' => $forecast,
            'historical' => $historical,
            'avg_by_day' => $weightedAvg,
            'trend_percent' => round($trend, 2),
            'seasonality' => $this->seasonality,
            'data_quality' => $this->assessDataQuality($dailySales),
        ];
    }

    /**
     * Прогноз по категориям меню
     */
    public function forecastByCategory(int $restaurantId, int $days = 7): array
    {
        return Cache::remember("forecast_category:{$restaurantId}:{$days}", 1800, function () use ($restaurantId, $days) {
            return $this->doForecastByCategory($restaurantId, $days);
        });
    }

    private function doForecastByCategory(int $restaurantId, int $days = 7): array
    {
        $now = Carbon::now();
        $weeksBack = 8;
        $dateFrom = $now->copy()->subWeeks($weeksBack);

        // Продажи по категориям
        $categorySales = OrderItem::select(
            'dishes.category_id',
            DB::raw('DAYOFWEEK(orders.created_at) as day_of_week'),
            DB::raw('SUM(order_items.quantity) as total_qty'),
            DB::raw('SUM(order_items.total) as total_revenue'),
            DB::raw('COUNT(DISTINCT DATE(orders.created_at)) as days_count')
        )
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', $dateFrom)
            ->groupBy('dishes.category_id', 'day_of_week')
            ->get();

        // Группируем по категориям
        $categories = Category::where('restaurant_id', $restaurantId)->get()->keyBy('id');
        $categoryForecasts = [];

        foreach ($categories as $categoryId => $category) {
            $catData = $categorySales->where('category_id', $categoryId);

            if ($catData->isEmpty()) {
                continue;
            }

            // Среднее по дням недели
            $avgByDay = [];
            foreach ($catData as $row) {
                $dow = $row->day_of_week;
                $avgByDay[$dow] = [
                    'qty' => $row->days_count > 0 ? round($row->total_qty / $row->days_count, 1) : 0,
                    'revenue' => $row->days_count > 0 ? round($row->total_revenue / $row->days_count, 2) : 0,
                ];
            }

            // Прогноз на N дней
            $forecast = [];
            $today = Carbon::today();

            for ($i = 0; $i < $days; $i++) {
                $date = $today->copy()->addDays($i);
                $dow = $date->dayOfWeek + 1;

                $forecast[] = [
                    'date' => $date->format('Y-m-d'),
                    'predicted_qty' => $avgByDay[$dow]['qty'] ?? 0,
                    'predicted_revenue' => $avgByDay[$dow]['revenue'] ?? 0,
                ];
            }

            $categoryForecasts[] = [
                'category_id' => $categoryId,
                'category_name' => $category->name,
                'icon' => $category->icon,
                'total_forecast_revenue' => round(collect($forecast)->sum('predicted_revenue'), 2),
                'total_forecast_qty' => round(collect($forecast)->sum('predicted_qty')),
                'daily_forecast' => $forecast,
            ];
        }

        // Сортируем по прогнозируемой выручке
        usort($categoryForecasts, fn($a, $b) => $b['total_forecast_revenue'] <=> $a['total_forecast_revenue']);

        return [
            'categories' => $categoryForecasts,
            'period_days' => $days,
        ];
    }

    /**
     * Прогноз необходимых ингредиентов
     */
    public function forecastIngredients(int $restaurantId, int $days = 7): array
    {
        return Cache::remember("forecast_ingredients:{$restaurantId}:{$days}", 1800, function () use ($restaurantId, $days) {
            return $this->doForecastIngredients($restaurantId, $days);
        });
    }

    private function doForecastIngredients(int $restaurantId, int $days = 7): array
    {
        // Получаем прогноз по категориям
        $categoryForecast = $this->forecastByCategory($restaurantId, $days);

        // Получаем рецепты и связи блюд с ингредиентами
        $recipes = DB::table('recipes')
            ->join('dishes', 'recipes.dish_id', '=', 'dishes.id')
            ->join('ingredients', 'recipes.ingredient_id', '=', 'ingredients.id')
            ->where('dishes.restaurant_id', $restaurantId)
            ->select(
                'recipes.dish_id',
                'recipes.ingredient_id',
                'recipes.quantity as recipe_qty',
                'ingredients.name as ingredient_name',
                'ingredients.unit',
                'dishes.category_id'
            )
            ->get();

        if ($recipes->isEmpty()) {
            return ['ingredients' => [], 'message' => 'Рецепты не заполнены'];
        }

        // Исторические продажи по блюдам
        $now = Carbon::now();
        $weeksBack = 4;
        $dishSales = OrderItem::select(
            'dish_id',
            DB::raw('AVG(quantity) as avg_qty_per_order'),
            DB::raw('COUNT(*) / ' . ($weeksBack * 7) . ' as orders_per_day')
        )
            ->whereHas('order', function ($q) use ($restaurantId, $now, $weeksBack) {
                $q->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $now->copy()->subWeeks($weeksBack));
            })
            ->groupBy('dish_id')
            ->get()
            ->keyBy('dish_id');

        // Расчёт потребности в ингредиентах
        $ingredientNeeds = [];

        foreach ($recipes as $recipe) {
            $dishStats = $dishSales->get($recipe->dish_id);
            if (!$dishStats) {
                continue;
            }

            // Прогноз количества блюд на период
            $predictedDishQty = $dishStats->orders_per_day * $dishStats->avg_qty_per_order * $days;

            // Потребность в ингредиенте
            $ingredientQty = $predictedDishQty * $recipe->recipe_qty;

            $ingredientId = $recipe->ingredient_id;
            if (!isset($ingredientNeeds[$ingredientId])) {
                $ingredientNeeds[$ingredientId] = [
                    'ingredient_id' => $ingredientId,
                    'name' => $recipe->ingredient_name,
                    'unit' => $recipe->unit,
                    'predicted_qty' => 0,
                    'dishes' => [],
                ];
            }

            $ingredientNeeds[$ingredientId]['predicted_qty'] += $ingredientQty;
            $ingredientNeeds[$ingredientId]['dishes'][] = $recipe->dish_id;
        }

        // Округляем и сортируем
        $ingredients = array_values($ingredientNeeds);
        foreach ($ingredients as &$ing) {
            $ing['predicted_qty'] = round($ing['predicted_qty'], 2);
            $ing['dishes_count'] = count(array_unique($ing['dishes']));
            unset($ing['dishes']);
        }

        usort($ingredients, fn($a, $b) => $b['predicted_qty'] <=> $a['predicted_qty']);

        return [
            'ingredients' => $ingredients,
            'period_days' => $days,
            'note' => 'Прогноз на основе средних продаж за 4 недели',
        ];
    }

    /**
     * Прогноз потребности в персонале
     */
    public function forecastStaff(int $restaurantId, int $days = 7): array
    {
        $forecast = $this->forecast($restaurantId, $days);
        $staffNeeds = [];

        // Примерные нормативы
        $ordersPerWaiter = 15;   // Заказов на 1 официанта в смену
        $ordersPerCook = 20;     // Заказов на 1 повара в смену

        foreach ($forecast['forecast'] as $day) {
            $orders = $day['predicted_orders'];

            $staffNeeds[] = [
                'date' => $day['date'],
                'day_name' => $day['day_name'],
                'predicted_orders' => $orders,
                'waiters_needed' => max(1, ceil($orders / $ordersPerWaiter)),
                'cooks_needed' => max(1, ceil($orders / $ordersPerCook)),
                'is_weekend' => $day['is_weekend'],
                'is_holiday' => $day['is_holiday'],
            ];
        }

        return [
            'staff_forecast' => $staffNeeds,
            'assumptions' => [
                'orders_per_waiter' => $ordersPerWaiter,
                'orders_per_cook' => $ordersPerCook,
            ],
        ];
    }

    private function calculateWeightedAverages($dailySales): array
    {
        $weighted = [];
        $now = Carbon::now();

        foreach ($dailySales as $day) {
            $dow = $day->day_of_week;
            $daysAgo = Carbon::parse($day->date)->diffInDays($now);

            // Экспоненциальное затухание веса
            $weight = exp(-$daysAgo / 30); // Полупериод ~30 дней

            if (!isset($weighted[$dow])) {
                $weighted[$dow] = [
                    'revenue_sum' => 0,
                    'orders_sum' => 0,
                    'weight_sum' => 0,
                    'values' => [],
                    'count' => 0,
                ];
            }

            $weighted[$dow]['revenue_sum'] += $day->revenue * $weight;
            $weighted[$dow]['orders_sum'] += $day->orders_count * $weight;
            $weighted[$dow]['weight_sum'] += $weight;
            $weighted[$dow]['values'][] = $day->revenue;
            $weighted[$dow]['count']++;
        }

        $result = [];
        foreach ($weighted as $dow => $data) {
            if ($data['weight_sum'] > 0) {
                $avgRevenue = $data['revenue_sum'] / $data['weight_sum'];
                $avgOrders = $data['orders_sum'] / $data['weight_sum'];

                // Стандартное отклонение
                $variance = 0;
                foreach ($data['values'] as $val) {
                    $variance += pow($val - $avgRevenue, 2);
                }
                $stdDev = $data['count'] > 1 ? sqrt($variance / $data['count']) : $avgRevenue * 0.2;

                $result[$dow] = [
                    'revenue' => round($avgRevenue, 2),
                    'orders' => round($avgOrders, 1),
                    'count' => $data['count'],
                    'std_dev' => round($stdDev, 2),
                ];
            }
        }

        return $result;
    }

    private function calculateTrend($dailySales): float
    {
        $n = $dailySales->count();
        if ($n < 7) {
            return 0;
        }

        // Сравниваем средние: последние 2 недели vs предыдущие 2 недели
        $sorted = $dailySales->sortByDesc('date')->values();

        $recent = $sorted->take(14)->avg('revenue');
        $previous = $sorted->skip(14)->take(14)->avg('revenue');

        if ($previous > 0) {
            return (($recent - $previous) / $previous) * 100;
        }

        return 0;
    }

    private function getHolidayFactor(Carbon $date): float
    {
        $dayMonth = $date->format('d-m');

        // День праздника
        if (in_array($dayMonth, $this->holidays)) {
            return 1.3; // +30% в праздник
        }

        // День перед праздником
        $tomorrow = $date->copy()->addDay()->format('d-m');
        if (in_array($tomorrow, $this->holidays)) {
            return 1.15;
        }

        // Новогодние праздники (особый период)
        if ($date->month == 12 && $date->day >= 25) {
            return 1.25;
        }

        return 1.0;
    }

    private function isHoliday(Carbon $date): bool
    {
        return in_array($date->format('d-m'), $this->holidays);
    }

    private function calculateConfidence(int $dataPoints, float $stdDev, float $mean): array
    {
        // Коэффициент вариации
        $cv = $mean > 0 ? ($stdDev / $mean) * 100 : 100;

        if ($dataPoints >= 8 && $cv < 20) {
            return ['level' => 'high', 'percent' => 90];
        } elseif ($dataPoints >= 4 && $cv < 40) {
            return ['level' => 'medium', 'percent' => 70];
        } else {
            return ['level' => 'low', 'percent' => 50];
        }
    }

    private function assessDataQuality($dailySales): array
    {
        $count = $dailySales->count();
        $daysWithData = $dailySales->unique('date')->count();

        return [
            'total_records' => $count,
            'days_with_data' => $daysWithData,
            'quality' => match (true) {
                $daysWithData >= 60 => 'excellent',
                $daysWithData >= 30 => 'good',
                $daysWithData >= 14 => 'fair',
                default => 'poor',
            },
            'recommendation' => $daysWithData < 30
                ? 'Для более точного прогноза требуется минимум 30 дней данных'
                : null,
        ];
    }

    private function getDayName(int $dayOfWeek): string
    {
        return ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'][$dayOfWeek] ?? '';
    }

    private function getDayNameFull(int $dayOfWeek): string
    {
        return [
            'Воскресенье', 'Понедельник', 'Вторник', 'Среда',
            'Четверг', 'Пятница', 'Суббота'
        ][$dayOfWeek] ?? '';
    }
}
