<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChurnAnalysisService
{
    // Настройки по умолчанию
    private int $churnThresholdDays = 60;      // Дней без заказа = отток
    private int $atRiskThresholdDays = 30;     // Дней без заказа = в зоне риска
    private int $activeThresholdDays = 14;     // Активный клиент

    // Веса факторов риска
    private array $riskWeights = [
        'days_since_order' => 0.40,
        'frequency_decline' => 0.25,
        'avg_check_decline' => 0.20,
        'negative_reviews' => 0.15,
    ];

    /**
     * Полный анализ оттока клиентов
     */
    public function analyze(int $restaurantId, int $lookbackDays = 180): array
    {
        $now = Carbon::now();
        $lookbackDate = $now->copy()->subDays($lookbackDays);

        // Получаем всех клиентов с хотя бы одним заказом
        $customers = Customer::where('restaurant_id', $restaurantId)
            ->where('is_blacklisted', false)
            ->where('total_orders', '>', 0)
            ->get();

        // Pre-fetch first order dates для всех клиентов одним запросом
        $customerIds = $customers->pluck('id');
        $firstOrderDates = Order::forRestaurant($restaurantId)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->select('customer_id', DB::raw('MIN(created_at) as first_order_at'))
            ->groupBy('customer_id')
            ->pluck('first_order_at', 'customer_id');

        // Pre-fetch frequency/avgCheck decline data в batch
        $mid = $now->copy()->subDays(60);
        $start = $now->copy()->subDays(120);

        $recentOrderStats = Order::forRestaurant($restaurantId)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $mid)
            ->select('customer_id', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(total) as avg_total'))
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $previousOrderStats = Order::forRestaurant($restaurantId)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $mid])
            ->select('customer_id', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(total) as avg_total'))
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $summary = [
            'total_customers' => 0,
            'active_customers' => 0,
            'at_risk_customers' => 0,
            'churned_customers' => 0,
            'new_customers' => 0,
        ];

        $atRisk = [];
        $churnedRecently = [];

        foreach ($customers as $customer) {
            $summary['total_customers']++;

            // Дней с последнего заказа
            $daysSinceOrder = $customer->last_order_at
                ? $now->diffInDays(Carbon::parse($customer->last_order_at))
                : 999;

            // Первый заказ за последние 30 дней = новый клиент
            $firstOrderDate = $firstOrderDates->get($customer->id);
            $isNewCustomer = $firstOrderDate && Carbon::parse($firstOrderDate)->diffInDays($now) <= 30;

            if ($isNewCustomer) {
                $summary['new_customers']++;
            }

            // Классификация по активности
            if ($daysSinceOrder <= $this->activeThresholdDays) {
                $summary['active_customers']++;
            } elseif ($daysSinceOrder <= $this->atRiskThresholdDays) {
                $summary['active_customers']++;
            } elseif ($daysSinceOrder <= $this->churnThresholdDays) {
                $summary['at_risk_customers']++;

                // Расчёт вероятности оттока (с pre-fetched данными)
                $churnData = $this->calculateChurnProbability(
                    $customer, $restaurantId, $recentOrderStats, $previousOrderStats
                );
                $atRisk[] = $churnData;
            } else {
                $summary['churned_customers']++;

                // Недавно ушедшие (за последние 30 дней перешли порог)
                if ($daysSinceOrder <= $this->churnThresholdDays + 30) {
                    $churnedRecently[] = [
                        'id' => $customer->id,
                        'name' => $customer->name ?: $customer->phone,
                        'phone' => $customer->phone,
                        'last_order_days' => $daysSinceOrder,
                        'total_orders' => $customer->total_orders,
                        'total_spent' => round($customer->total_spent, 2),
                        'churned_at' => $customer->last_order_at
                            ? Carbon::parse($customer->last_order_at)->addDays($this->churnThresholdDays)->format('Y-m-d')
                            : null,
                    ];
                }
            }
        }

        // Расчёт метрик
        $total = $summary['total_customers'];
        $summary['churn_rate'] = $total > 0
            ? round(($summary['churned_customers'] / $total) * 100, 1)
            : 0;
        $summary['retention_rate'] = 100 - $summary['churn_rate'];
        $summary['at_risk_rate'] = $total > 0
            ? round(($summary['at_risk_customers'] / $total) * 100, 1)
            : 0;

        // Сортируем по вероятности оттока (высокая первая)
        usort($atRisk, fn($a, $b) => $b['churn_probability'] <=> $a['churn_probability']);

        // Тренд оттока за последние 6 месяцев
        $trend = $this->calculateChurnTrend($restaurantId, 6);

        return [
            'summary' => $summary,
            'at_risk' => array_slice($atRisk, 0, 50), // Топ-50
            'churned_recently' => array_slice($churnedRecently, 0, 20),
            'trend' => $trend,
            'thresholds' => [
                'active_days' => $this->activeThresholdDays,
                'at_risk_days' => $this->atRiskThresholdDays,
                'churn_days' => $this->churnThresholdDays,
            ],
        ];
    }

    /**
     * Рассчитывает вероятность оттока для клиента
     */
    public function calculateChurnProbability(
        Customer $customer,
        int $restaurantId,
        ?\Illuminate\Support\Collection $recentOrderStats = null,
        ?\Illuminate\Support\Collection $previousOrderStats = null
    ): array
    {
        $now = Carbon::now();
        $riskFactors = [];
        $riskScore = 0;

        // 1. Дни с последнего заказа (основной фактор)
        $daysSinceOrder = $customer->last_order_at
            ? $now->diffInDays(Carbon::parse($customer->last_order_at))
            : 999;

        $daysRisk = min(100, ($daysSinceOrder / $this->churnThresholdDays) * 100);
        $riskScore += $daysRisk * $this->riskWeights['days_since_order'];

        if ($daysSinceOrder > $this->atRiskThresholdDays) {
            $riskFactors[] = "Давно не был ({$daysSinceOrder} дней)";
        }

        // 2. Снижение частоты заказов (используем pre-fetched данные если есть)
        if ($recentOrderStats && $previousOrderStats) {
            $recent = $recentOrderStats->get($customer->id);
            $previous = $previousOrderStats->get($customer->id);
            $prevCount = $previous->cnt ?? 0;
            $recentCount = $recent->cnt ?? 0;
            $frequencyDecline = $prevCount > 0
                ? max(0, round((($prevCount - $recentCount) / $prevCount) * 100, 1))
                : 0;
        } else {
            $frequencyDecline = $this->calculateFrequencyDecline($customer->id, $restaurantId);
        }
        if ($frequencyDecline > 0) {
            $riskScore += min(100, $frequencyDecline * 2) * $this->riskWeights['frequency_decline'];
            if ($frequencyDecline > 30) {
                $riskFactors[] = "Частота снизилась на {$frequencyDecline}%";
            }
        }

        // 3. Снижение среднего чека (используем pre-fetched данные если есть)
        if ($recentOrderStats && $previousOrderStats) {
            $recent = $recentOrderStats->get($customer->id);
            $previous = $previousOrderStats->get($customer->id);
            $prevAvg = $previous->avg_total ?? 0;
            $recentAvg = $recent->avg_total ?? 0;
            $avgCheckDecline = $prevAvg > 0
                ? max(0, round((($prevAvg - $recentAvg) / $prevAvg) * 100, 1))
                : 0;
        } else {
            $avgCheckDecline = $this->calculateAvgCheckDecline($customer->id, $restaurantId);
        }
        if ($avgCheckDecline > 0) {
            $riskScore += min(100, $avgCheckDecline * 2) * $this->riskWeights['avg_check_decline'];
            if ($avgCheckDecline > 20) {
                $riskFactors[] = "Средний чек упал на {$avgCheckDecline}%";
            }
        }

        // 4. Негативные отзывы
        $negativeReviews = $this->getRecentNegativeReviews($customer->id, $restaurantId);
        if ($negativeReviews > 0) {
            $riskScore += min(100, $negativeReviews * 50) * $this->riskWeights['negative_reviews'];
            $riskFactors[] = "Негативные отзывы ({$negativeReviews})";
        }

        // Ограничиваем 0-100
        $churnProbability = min(100, max(0, round($riskScore)));

        // Рекомендация
        $recommendation = $this->getRecommendation($churnProbability, $customer);

        return [
            'id' => $customer->id,
            'name' => $customer->name ?: $customer->phone,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'last_order_days' => $daysSinceOrder,
            'total_orders' => $customer->total_orders,
            'total_spent' => round($customer->total_spent, 2),
            'churn_probability' => $churnProbability,
            'risk_level' => $this->getRiskLevel($churnProbability),
            'risk_factors' => $riskFactors,
            'recommended_action' => $recommendation,
            'clv' => $this->calculateCLV($customer),
        ];
    }

    /**
     * Алерты о клиентах в зоне риска
     */
    public function getAlerts(int $restaurantId): array
    {
        $analysis = $this->analyze($restaurantId);
        $atRisk = $analysis['at_risk'];

        $alerts = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        foreach ($atRisk as $customer) {
            $alert = [
                'customer_id' => $customer['id'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'message' => $this->generateAlertMessage($customer),
                'action' => $customer['recommended_action'],
                'churn_probability' => $customer['churn_probability'],
                'clv' => $customer['clv'],
            ];

            // Критические: высокий CLV + высокая вероятность оттока
            if ($customer['clv'] >= 20000 && $customer['churn_probability'] >= 60) {
                $alerts['critical'][] = $alert;
            }
            // Предупреждения: средний риск
            elseif ($customer['churn_probability'] >= 50) {
                $alerts['warning'][] = $alert;
            }
            // Информационные: начальный риск
            else {
                $alerts['info'][] = $alert;
            }
        }

        return [
            'critical' => array_slice($alerts['critical'], 0, 10),
            'warning' => array_slice($alerts['warning'], 0, 15),
            'info' => array_slice($alerts['info'], 0, 20),
            'total_alerts' => count($atRisk),
        ];
    }

    /**
     * Тренд оттока за N месяцев
     */
    public function calculateChurnTrend(int $restaurantId, int $months = 6): array
    {
        $trend = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
            $monthLabel = $monthStart->format('Y-m');

            // Активные на начало месяца (имели заказ за 60 дней до начала месяца)
            $activeAtStart = Customer::where('restaurant_id', $restaurantId)
                ->where('is_blacklisted', false)
                ->whereHas('orders', function ($q) use ($monthStart) {
                    $threshold = $monthStart->copy()->subDays($this->churnThresholdDays);
                    $q->where('status', 'completed')
                        ->where('created_at', '>=', $threshold)
                        ->where('created_at', '<', $monthStart);
                })
                ->count();

            // Ушли в этом месяце (последний заказ был 60+ дней назад от конца месяца)
            $churnedInMonth = 0;
            if ($activeAtStart > 0) {
                $churnThreshold = $monthEnd->copy()->subDays($this->churnThresholdDays);

                $churnedInMonth = Customer::where('restaurant_id', $restaurantId)
                    ->where('is_blacklisted', false)
                    ->where('last_order_at', '>=', $churnThreshold->copy()->subDays(30))
                    ->where('last_order_at', '<', $churnThreshold)
                    ->whereDoesntHave('orders', function ($q) use ($churnThreshold, $monthEnd) {
                        $q->where('status', 'completed')
                            ->whereBetween('created_at', [$churnThreshold, $monthEnd]);
                    })
                    ->count();
            }

            $churnRate = $activeAtStart > 0
                ? round(($churnedInMonth / $activeAtStart) * 100, 1)
                : 0;

            $trend[] = [
                'month' => $monthLabel,
                'month_name' => $this->getMonthName($monthStart->month),
                'active_start' => $activeAtStart,
                'churned' => $churnedInMonth,
                'churn_rate' => $churnRate,
                'retention_rate' => 100 - $churnRate,
            ];
        }

        return $trend;
    }

    /**
     * Customer Lifetime Value
     */
    public function calculateCLV(Customer $customer): float
    {
        if ($customer->total_orders == 0) {
            return 0;
        }

        // Простая формула: средний чек * частота * ожидаемый срок
        $avgCheck = $customer->total_spent / $customer->total_orders;

        // Частота в месяц (используем restaurant_id клиента для изоляции)
        $firstOrder = Order::forRestaurant($customer->restaurant_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->min('created_at');

        if (!$firstOrder) {
            return $avgCheck;
        }

        $monthsActive = max(1, Carbon::parse($firstOrder)->diffInMonths(Carbon::now()));
        $ordersPerMonth = $customer->total_orders / $monthsActive;

        // Ожидаемый срок: 12 месяцев для ресторана
        $expectedLifespan = 12;

        return round($avgCheck * $ordersPerMonth * $expectedLifespan, 2);
    }

    private function calculateFrequencyDecline(int $customerId, int $restaurantId): float
    {
        // Сравниваем частоту заказов: последние 60 дней vs предыдущие 60 дней
        $now = Carbon::now();
        $mid = $now->copy()->subDays(60);
        $start = $now->copy()->subDays(120);

        $recentOrders = Order::forRestaurant($restaurantId)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $mid)
            ->count();

        $previousOrders = Order::forRestaurant($restaurantId)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $mid])
            ->count();

        if ($previousOrders == 0) {
            return 0;
        }

        $decline = (($previousOrders - $recentOrders) / $previousOrders) * 100;
        return max(0, round($decline, 1));
    }

    private function calculateAvgCheckDecline(int $customerId, int $restaurantId): float
    {
        $now = Carbon::now();
        $mid = $now->copy()->subDays(60);
        $start = $now->copy()->subDays(120);

        $recentAvg = Order::forRestaurant($restaurantId)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $mid)
            ->avg('total') ?? 0;

        $previousAvg = Order::forRestaurant($restaurantId)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $mid])
            ->avg('total') ?? 0;

        if ($previousAvg == 0) {
            return 0;
        }

        $decline = (($previousAvg - $recentAvg) / $previousAvg) * 100;
        return max(0, round($decline, 1));
    }

    private function getRecentNegativeReviews(int $customerId, int $restaurantId): int
    {
        if (!class_exists(Review::class)) {
            return 0;
        }

        return Review::forRestaurant($restaurantId)
            ->where('customer_id', $customerId)
            ->where('rating', '<=', 2)
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->count();
    }

    private function getRiskLevel(int $probability): string
    {
        return match (true) {
            $probability >= 70 => 'high',
            $probability >= 40 => 'medium',
            default => 'low',
        };
    }

    private function getRecommendation(int $probability, Customer $customer): string
    {
        $clv = $this->calculateCLV($customer);

        // Высокий CLV + высокий риск
        if ($clv >= 30000 && $probability >= 60) {
            return 'Персональный звонок от менеджера';
        }

        return match (true) {
            $probability >= 80 => 'Агрессивная скидка -30%',
            $probability >= 60 => 'Персональное предложение -20%',
            $probability >= 40 => 'Email с акцией -15%',
            $probability >= 20 => 'Push-напоминание о бонусах',
            default => 'Мониторинг активности',
        };
    }

    private function generateAlertMessage(array $customer): string
    {
        $clvFormatted = number_format($customer['clv'], 0, ',', ' ');
        $days = $customer['last_order_days'];
        $prob = $customer['churn_probability'];

        if ($customer['clv'] >= 30000) {
            return "VIP-клиент с LTV {$clvFormatted}₽ не был {$days} дней. Риск ухода: {$prob}%";
        }

        if ($prob >= 70) {
            return "Клиент с высоким риском ухода ({$prob}%). Не был {$days} дней";
        }

        return "Клиент не был {$days} дней. Вероятность оттока: {$prob}%";
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Янв', 2 => 'Фев', 3 => 'Мар', 4 => 'Апр',
            5 => 'Май', 6 => 'Июн', 7 => 'Июл', 8 => 'Авг',
            9 => 'Сен', 10 => 'Окт', 11 => 'Ноя', 12 => 'Дек',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Обновить пороговые значения
     */
    public function setThresholds(int $active, int $atRisk, int $churn): void
    {
        $this->activeThresholdDays = $active;
        $this->atRiskThresholdDays = $atRisk;
        $this->churnThresholdDays = $churn;
    }
}
