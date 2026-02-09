<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RFMAnalysisService
{
    // Пороги для R (дни с последнего заказа)
    private array $recencyThresholds = [
        5 => 14,   // 0-14 дней = 5
        4 => 30,   // 15-30 дней = 4
        3 => 60,   // 31-60 дней = 3
        2 => 90,   // 61-90 дней = 2
        1 => PHP_INT_MAX, // >90 дней = 1
    ];

    // Пороги для F (количество заказов)
    private array $frequencyThresholds = [
        5 => 20,   // >20 заказов = 5
        4 => 10,   // 10-20 = 4
        3 => 5,    // 5-9 = 3
        2 => 2,    // 2-4 = 2
        1 => 1,    // 1 заказ = 1
    ];

    // Пороги для M (сумма покупок)
    private array $monetaryThresholds = [
        5 => 50000,  // >50k = 5
        4 => 20000,  // 20-50k = 4
        3 => 10000,  // 10-20k = 3
        2 => 5000,   // 5-10k = 2
        1 => 0,      // <5k = 1
    ];

    // Определение сегментов по RFM-коду
    private array $segments = [
        // Champions - лучшие клиенты
        '555' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '554' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '545' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '544' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '455' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '454' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],
        '445' => ['name' => 'Champions', 'color' => '#22c55e', 'action' => 'VIP-программа'],

        // Loyal - постоянные клиенты
        '543' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '534' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '443' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '444' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '435' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '434' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '433' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],
        '533' => ['name' => 'Loyal', 'color' => '#3b82f6', 'action' => 'Персональные предложения'],

        // Potential Loyalist - перспективные
        '553' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '552' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '551' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '542' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '541' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '532' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '531' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '452' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '451' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '442' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],
        '441' => ['name' => 'Potential Loyalist', 'color' => '#8b5cf6', 'action' => 'Программа вовлечения'],

        // New Customers - новые
        '512' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '511' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '412' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '411' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '311' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '312' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],
        '321' => ['name' => 'New Customers', 'color' => '#06b6d4', 'action' => 'Welcome-бонус'],

        // Promising - многообещающие
        '525' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '524' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '523' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '522' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '521' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '425' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '424' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '423' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '422' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '421' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '325' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '324' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '323' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],
        '322' => ['name' => 'Promising', 'color' => '#10b981', 'action' => 'Акция на повторный визит'],

        // Need Attention - требуют внимания
        '535' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '432' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '431' => ['name' => 'Need Attention', 'action' => 'Спецпредложение', 'color' => '#f59e0b'],
        '343' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '342' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '341' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '335' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '334' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '333' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '344' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],
        '345' => ['name' => 'Need Attention', 'color' => '#f59e0b', 'action' => 'Спецпредложение'],

        // About to Sleep - засыпающие
        '332' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '331' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '313' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '314' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '315' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '243' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '244' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '245' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '234' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '235' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],
        '233' => ['name' => 'About to Sleep', 'color' => '#f97316', 'action' => 'Срочная реактивация'],

        // At Risk - в зоне риска (были активны, но пропали)
        '155' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '154' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '153' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '145' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '144' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '143' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '135' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '134' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '133' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '253' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '254' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],
        '255' => ['name' => 'At Risk', 'color' => '#ef4444', 'action' => 'Win-back кампания'],

        // Can't Lose Them - нельзя потерять (были VIP)
        '152' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '151' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '142' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '141' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '132' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '131' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '252' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '251' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '242' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],
        '241' => ['name' => "Can't Lose", 'color' => '#dc2626', 'action' => 'Персональный звонок'],

        // Hibernating - спящие
        '232' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '231' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '223' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '222' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '221' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '213' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '212' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],
        '211' => ['name' => 'Hibernating', 'color' => '#9ca3af', 'action' => 'Email-кампания'],

        // Lost - потерянные
        '125' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '124' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '123' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '122' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '121' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '115' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '114' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '113' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '112' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
        '111' => ['name' => 'Lost', 'color' => '#6b7280', 'action' => 'Последняя попытка'],
    ];

    /**
     * Рассчитывает RFM-анализ для всех клиентов
     */
    public function analyze(int $restaurantId, int $periodDays = 90): array
    {
        $dateFrom = Carbon::now()->subDays($periodDays);

        // Получаем клиентов с их заказами за период
        $customers = Customer::where('restaurant_id', $restaurantId)
            ->where('is_blacklisted', false)
            ->where('total_orders', '>', 0)
            ->get();

        // Pre-fetch order stats для всех клиентов одним запросом (вместо N+1)
        $customerIds = $customers->pluck('id');
        $orderStatsMap = Order::forRestaurant($restaurantId)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $dateFrom)
            ->select(
                'customer_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_at')
            )
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $results = [];
        $segmentsSummary = [];

        foreach ($customers as $customer) {
            $orderStats = $orderStatsMap->get($customer->id);

            // Если нет заказов за период - используем общую статистику
            $frequency = $orderStats->orders_count ?? 0;
            $monetary = $orderStats->total_spent ?? 0;
            $lastOrderAt = ($orderStats->last_order_at ?? null)
                ? Carbon::parse($orderStats->last_order_at)
                : ($customer->last_order_at ? Carbon::parse($customer->last_order_at) : null);

            // Расчёт дней с последнего заказа
            $recencyDays = $lastOrderAt
                ? Carbon::now()->diffInDays($lastOrderAt)
                : 999;

            // Расчёт RFM-оценок
            $rScore = $this->calculateRecencyScore($recencyDays);
            $fScore = $this->calculateFrequencyScore($frequency);
            $mScore = $this->calculateMonetaryScore($monetary);

            $rfmScore = "{$rScore}{$fScore}{$mScore}";
            $segment = $this->getSegment($rfmScore);

            $customerData = [
                'id' => $customer->id,
                'name' => $customer->name ?: $customer->phone,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'recency_days' => $recencyDays,
                'frequency' => $frequency,
                'monetary' => round($monetary, 2),
                'r_score' => $rScore,
                'f_score' => $fScore,
                'm_score' => $mScore,
                'rfm_score' => $rfmScore,
                'segment' => $segment['name'],
                'segment_color' => $segment['color'],
                'action' => $segment['action'],
            ];

            $results[] = $customerData;

            // Подсчёт по сегментам
            $segmentName = $segment['name'];
            if (!isset($segmentsSummary[$segmentName])) {
                $segmentsSummary[$segmentName] = [
                    'count' => 0,
                    'revenue' => 0,
                    'color' => $segment['color'],
                ];
            }
            $segmentsSummary[$segmentName]['count']++;
            $segmentsSummary[$segmentName]['revenue'] += $monetary;
        }

        // Сортируем по RFM-оценке (сначала лучшие)
        usort($results, function ($a, $b) {
            return $b['rfm_score'] <=> $a['rfm_score'];
        });

        // Расчёт процентов для сегментов
        $totalCustomers = count($results);
        foreach ($segmentsSummary as $name => &$data) {
            $data['percent'] = $totalCustomers > 0
                ? round(($data['count'] / $totalCustomers) * 100, 1)
                : 0;
        }

        // Расчёт распределения по R, F, M
        $distribution = $this->calculateDistribution($results);

        return [
            'customers' => $results,
            'segments_summary' => $segmentsSummary,
            'distribution' => $distribution,
            'period_days' => $periodDays,
            'total_customers' => $totalCustomers,
        ];
    }

    /**
     * Получает RFM для одного клиента
     *
     * @param int $customerId ID клиента
     * @param int $restaurantId ID ресторана (для проверки доступа)
     * @param int $periodDays Период анализа в днях
     */
    public function getCustomerRFM(int $customerId, int $restaurantId, int $periodDays = 90): ?array
    {
        // Ищем клиента только в рамках указанного ресторана
        $customer = Customer::forRestaurant($restaurantId)->find($customerId);
        if (!$customer) {
            return null;
        }

        $dateFrom = Carbon::now()->subDays($periodDays);

        // Запрос заказов только для этого ресторана
        $orderStats = Order::forRestaurant($restaurantId)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $dateFrom)
            ->select(
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_at')
            )
            ->first();

        $frequency = $orderStats->orders_count ?: 0;
        $monetary = $orderStats->total_spent ?: 0;
        $lastOrderAt = $orderStats->last_order_at
            ? Carbon::parse($orderStats->last_order_at)
            : ($customer->last_order_at ? Carbon::parse($customer->last_order_at) : null);

        $recencyDays = $lastOrderAt
            ? Carbon::now()->diffInDays($lastOrderAt)
            : 999;

        $rScore = $this->calculateRecencyScore($recencyDays);
        $fScore = $this->calculateFrequencyScore($frequency);
        $mScore = $this->calculateMonetaryScore($monetary);

        $rfmScore = "{$rScore}{$fScore}{$mScore}";
        $segment = $this->getSegment($rfmScore);

        return [
            'customer_id' => $customer->id,
            'name' => $customer->name ?: $customer->phone,
            'recency_days' => $recencyDays,
            'frequency' => $frequency,
            'monetary' => round($monetary, 2),
            'r_score' => $rScore,
            'f_score' => $fScore,
            'm_score' => $mScore,
            'rfm_score' => $rfmScore,
            'segment' => $segment['name'],
            'segment_color' => $segment['color'],
            'action' => $segment['action'],
            'period_days' => $periodDays,
        ];
    }

    /**
     * Возвращает только сводку по сегментам
     */
    public function getSegmentsSummary(int $restaurantId, int $periodDays = 90): array
    {
        $analysis = $this->analyze($restaurantId, $periodDays);

        return [
            'segments' => $analysis['segments_summary'],
            'total_customers' => $analysis['total_customers'],
            'period_days' => $periodDays,
        ];
    }

    private function calculateRecencyScore(int $days): int
    {
        foreach ($this->recencyThresholds as $score => $threshold) {
            if ($days <= $threshold) {
                return $score;
            }
        }
        return 1;
    }

    private function calculateFrequencyScore(int $orders): int
    {
        if ($orders >= $this->frequencyThresholds[5]) return 5;
        if ($orders >= $this->frequencyThresholds[4]) return 4;
        if ($orders >= $this->frequencyThresholds[3]) return 3;
        if ($orders >= $this->frequencyThresholds[2]) return 2;
        return 1;
    }

    private function calculateMonetaryScore(float $amount): int
    {
        if ($amount >= $this->monetaryThresholds[5]) return 5;
        if ($amount >= $this->monetaryThresholds[4]) return 4;
        if ($amount >= $this->monetaryThresholds[3]) return 3;
        if ($amount >= $this->monetaryThresholds[2]) return 2;
        return 1;
    }

    private function getSegment(string $rfmScore): array
    {
        return $this->segments[$rfmScore] ?? [
            'name' => 'Other',
            'color' => '#9ca3af',
            'action' => 'Анализ',
        ];
    }

    private function calculateDistribution(array $customers): array
    {
        $distribution = [
            'r' => array_fill(1, 5, 0),
            'f' => array_fill(1, 5, 0),
            'm' => array_fill(1, 5, 0),
        ];

        foreach ($customers as $customer) {
            $distribution['r'][$customer['r_score']]++;
            $distribution['f'][$customer['f_score']]++;
            $distribution['m'][$customer['m_score']]++;
        }

        return $distribution;
    }

    /**
     * Возвращает описание сегментов
     */
    public function getSegmentDescriptions(): array
    {
        return [
            'Champions' => [
                'description' => 'Лучшие клиенты. Покупают часто и много',
                'color' => '#22c55e',
                'actions' => ['VIP-программа', 'Эксклюзивные предложения', 'Приоритетное обслуживание'],
            ],
            'Loyal' => [
                'description' => 'Постоянные клиенты с хорошей частотой визитов',
                'color' => '#3b82f6',
                'actions' => ['Upsell', 'Персональные рекомендации', 'Бонусы за лояльность'],
            ],
            'Potential Loyalist' => [
                'description' => 'Новые клиенты с высоким потенциалом',
                'color' => '#8b5cf6',
                'actions' => ['Программа вовлечения', 'Пробные предложения', 'Обратная связь'],
            ],
            'New Customers' => [
                'description' => 'Недавно совершили первую покупку',
                'color' => '#06b6d4',
                'actions' => ['Welcome-бонус', 'Знакомство с ассортиментом', 'Скидка на второй заказ'],
            ],
            'Promising' => [
                'description' => 'Перспективные клиенты, требуют внимания',
                'color' => '#10b981',
                'actions' => ['Акции на повторный визит', 'Напоминания', 'Специальные условия'],
            ],
            'Need Attention' => [
                'description' => 'Активность снижается, требуют внимания',
                'color' => '#f59e0b',
                'actions' => ['Спецпредложение', 'Опрос удовлетворённости', 'Персональная скидка'],
            ],
            'About to Sleep' => [
                'description' => 'Скоро могут уйти, активность падает',
                'color' => '#f97316',
                'actions' => ['Срочная реактивация', 'Ограниченное предложение', 'Напоминание о бонусах'],
            ],
            'At Risk' => [
                'description' => 'Были активны, но давно не возвращались',
                'color' => '#ef4444',
                'actions' => ['Win-back кампания', 'Большая скидка', 'Персональное обращение'],
            ],
            "Can't Lose" => [
                'description' => 'Ценные клиенты, которых мы теряем',
                'color' => '#dc2626',
                'actions' => ['Персональный звонок', 'VIP-предложение', 'Выяснение причин ухода'],
            ],
            'Hibernating' => [
                'description' => 'Неактивны длительное время',
                'color' => '#9ca3af',
                'actions' => ['Email-кампания', 'Напоминание о нас', 'Акция возвращения'],
            ],
            'Lost' => [
                'description' => 'Потерянные клиенты',
                'color' => '#6b7280',
                'actions' => ['Последняя попытка', 'Опрос причин ухода', 'Архивирование'],
            ],
        ];
    }
}
