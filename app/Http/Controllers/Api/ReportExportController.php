<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\RFMAnalysisService;
use App\Services\ChurnAnalysisService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;

class ReportExportController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // ЭКСПОРТ ПРОДАЖ В EXCEL (CSV)
    // ==========================================

    public function exportSales(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $from = $request->input('from', TimeHelper::startOfMonth($restaurantId)->format('Y-m-d'));
        $to = $request->input('to', TimeHelper::now($restaurantId)->format('Y-m-d'));

        $filename = "sales_{$from}_{$to}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        // Используем cursor() для streaming CSV без загрузки всех заказов в память (OOM prevention)
        $callback = function () use ($restaurantId, $from, $to) {
            $file = fopen('php://output', 'w');

            // BOM для корректного отображения в Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Заголовки
            fputcsv($file, [
                '№ Заказа',
                'Дата',
                'Время',
                'Тип',
                'Стол',
                'Официант',
                'Клиент',
                'Кол-во позиций',
                'Сумма',
                'Способ оплаты',
            ], ';');

            $query = Order::with(['items.dish', 'table', 'waiter', 'customer'])
                ->where('restaurant_id', $restaurantId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
                ->orderBy('created_at');

            foreach ($query->cursor() as $order) {
                fputcsv($file, [
                    $order->order_number,
                    Carbon::parse($order->created_at)->format('d.m.Y'),
                    Carbon::parse($order->created_at)->format('H:i'),
                    $order->type === OrderType::DINE_IN->value ? 'В зале' : ($order->type === OrderType::DELIVERY->value ? 'Доставка' : 'Самовывоз'),
                    $order->table?->number ?? '-',
                    $order->waiter?->name ?? '-',
                    $order->customer?->name ?? 'Гость',
                    $order->items->sum('quantity'),
                    number_format($order->total, 2, ',', ' '),
                    $order->payment_method === 'cash' ? 'Наличные' : 'Карта',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // ЭКСПОРТ ABC-АНАЛИЗА
    // ==========================================

    public function exportAbc(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input('period', 30);

        // Получаем данные ABC через AbcAnalyticsController
        $abcController = app(AbcAnalyticsController::class);
        $response = $abcController->abcAnalysis($request);
        $data = json_decode($response->getContent(), true)['data'];

        $filename = "abc_analysis_{$period}d.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Блюдо',
                'Категория',
                'Цена',
                'Продано шт.',
                'Выручка',
                '% от общей',
                'Накопленный %',
                'ABC',
            ], ';');

            foreach ($data['items'] as $item) {
                fputcsv($file, [
                    $item['dish_name'],
                    $item['category_name'],
                    number_format($item['price'], 2, ',', ' '),
                    $item['quantity'],
                    number_format($item['revenue'], 2, ',', ' '),
                    $item['percent'] . '%',
                    $item['cumulative_percent'] . '%',
                    $item['abc_category'],
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // ЭКСПОРТ RFM-АНАЛИЗА
    // ==========================================

    public function exportRfm(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->analyze($restaurantId, $period);

        $filename = "rfm_analysis_{$period}d.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen("php://output", "w");
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                "Клиент",
                "Телефон",
                "Дней без визита",
                "Заказов",
                "Сумма",
                "R",
                "F",
                "M",
                "RFM",
                "Сегмент",
                "Рекомендация",
            ], ";");

            foreach ($data["customers"] as $customer) {
                fputcsv($file, [
                    $customer["name"],
                    $customer["phone"],
                    $customer["recency_days"],
                    $customer["frequency"],
                    number_format($customer["monetary"], 2, ",", " "),
                    $customer["r_score"],
                    $customer["f_score"],
                    $customer["m_score"],
                    $customer["rfm_score"],
                    $customer["segment"],
                    $customer["action"],
                ], ";");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // ЭКСПОРТ АНАЛИЗА ОТТОКА
    // ==========================================

    public function exportChurn(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);

        $service = new ChurnAnalysisService();
        $data = $service->analyze($restaurantId);

        $filename = "churn_analysis.csv";

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen("php://output", "w");
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                "Клиент",
                "Телефон",
                "Дней без визита",
                "Всего заказов",
                "Сумма покупок",
                "Вероятность оттока",
                "Уровень риска",
                "CLV",
                "Рекомендация",
            ], ";");

            foreach ($data["at_risk"] as $customer) {
                fputcsv($file, [
                    $customer["name"],
                    $customer["phone"],
                    $customer["last_order_days"],
                    $customer["total_orders"],
                    number_format($customer["total_spent"], 2, ",", " "),
                    $customer["churn_probability"] . "%",
                    $customer["risk_level"],
                    number_format($customer["clv"], 2, ",", " "),
                    $customer["recommended_action"],
                ], ";");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
