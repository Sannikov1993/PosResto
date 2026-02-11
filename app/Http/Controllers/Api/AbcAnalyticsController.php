<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Domain\Order\Enums\OrderStatus;

class AbcAnalyticsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // ABC-АНАЛИЗ МЕНЮ
    // ==========================================

    public function abcAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 30); // дней
        $metric = $request->input('metric', 'revenue'); // revenue или quantity

        $dateFrom = TimeHelper::now($restaurantId)->subDays($period);

        // Получаем продажи по блюдам
        $salesData = OrderItem::select(
                'dish_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as orders_count')
            )
            ->whereHas('order', function ($q) use ($restaurantId, $dateFrom) {
                $q->where('restaurant_id', $restaurantId)
                  ->where('status', OrderStatus::COMPLETED->value)
                  ->where('created_at', '>=', $dateFrom);
            })
            ->groupBy('dish_id')
            ->with('dish:id,name,category_id,price')
            ->get();

        if ($salesData->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['items' => [], 'summary' => null],
            ]);
        }

        // Считаем общие суммы
        $totalRevenue = $salesData->sum('total_revenue');
        $totalQty = $salesData->sum('total_qty');

        // Сортируем по выбранной метрике
        $sorted = $salesData->sortByDesc($metric === 'revenue' ? 'total_revenue' : 'total_qty')->values();

        // ABC классификация
        $cumulative = 0;
        $items = [];

        foreach ($sorted as $item) {
            $value = $metric === 'revenue' ? $item->total_revenue : $item->total_qty;
            $total = $metric === 'revenue' ? $totalRevenue : $totalQty;
            $percent = $total > 0 ? ($value / $total) * 100 : 0;
            $cumulative += $percent;

            // Определяем категорию ABC
            if ($cumulative <= 80) {
                $category = 'A';
            } elseif ($cumulative <= 95) {
                $category = 'B';
            } else {
                $category = 'C';
            }

            $items[] = [
                'dish_id' => $item->dish_id,
                'dish_name' => $item->dish?->name ?? 'Удалено',
                'category_name' => $item->dish?->category?->name ?? '',
                'price' => $item->dish?->price ?? 0,
                'quantity' => $item->total_qty,
                'revenue' => $item->total_revenue,
                'orders_count' => $item->orders_count,
                'percent' => round($percent, 2),
                'cumulative_percent' => round($cumulative, 2),
                'abc_category' => $category,
            ];
        }

        // Статистика по категориям
        $summary = [
            'A' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
            'B' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
            'C' => ['count' => 0, 'revenue' => 0, 'quantity' => 0],
        ];

        foreach ($items as $item) {
            $cat = $item['abc_category'];
            $summary[$cat]['count']++;
            $summary[$cat]['revenue'] += $item['revenue'];
            $summary[$cat]['quantity'] += $item['quantity'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => $summary,
                'total_revenue' => $totalRevenue,
                'total_quantity' => $totalQty,
                'period_days' => $period,
                'metric' => $metric,
            ],
        ]);
    }
}
