<?php

namespace App\Http\Controllers;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Контроллер публичного трекинга заказов
 */
class TrackingController extends Controller
{
    /**
     * Страница трекинга заказа
     */
    public function show(string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->orWhere('order_number', '#' . $orderNumber)
            ->orWhere('daily_number', $orderNumber)
            ->orWhere('daily_number', '#' . $orderNumber)
            ->where('type', OrderType::DELIVERY->value)
            ->with(['items', 'courier', 'deliveryZone'])
            ->first();

        if (!$order) {
            abort(404, 'Заказ не найден');
        }

        return view('tracking.show', [
            'order' => $order,
            'statusSteps' => $this->getStatusSteps($order),
        ]);
    }

    /**
     * Форма поиска заказа
     */
    public function index(): View
    {
        return view('tracking.index');
    }

    /**
     * Поиск заказа по номеру или телефону
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $query = trim($validated['query']);

        // Поиск по номеру заказа
        $order = Order::where('type', OrderType::DELIVERY->value)
            ->where(function($q) use ($query) {
                $q->where('order_number', $query)
                  ->orWhere('order_number', '#' . $query)
                  ->orWhere('order_number', 'like', "%{$query}%")
                  ->orWhere('daily_number', $query)
                  ->orWhere('daily_number', '#' . $query)
                  ->orWhere('daily_number', 'like', "%{$query}%");
            })
            ->first();

        if ($order) {
            return response()->json([
                'success' => true,
                'redirect' => url("/track/{$order->order_number}"),
            ]);
        }

        // Поиск последнего заказа по телефону
        $phone = preg_replace('/\D/', '', $query);
        if (strlen($phone) >= 10) {
            $order = Order::where('type', OrderType::DELIVERY->value)
                ->where('phone', 'like', "%{$phone}%")
                ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
                ->orderByDesc('created_at')
                ->first();

            if ($order) {
                return response()->json([
                    'success' => true,
                    'redirect' => url("/track/{$order->order_number}"),
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Заказ не найден',
        ], 404);
    }

    /**
     * API: Получить статус заказа (для автообновления)
     */
    public function status(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->orWhere('order_number', '#' . $orderNumber)
            ->orWhere('daily_number', $orderNumber)
            ->where('type', OrderType::DELIVERY->value)
            ->with(['courier', 'deliveryZone'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Заказ не найден'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $this->getStatusLabel($order->status),
            'status_icon' => $this->getStatusIcon($order->status),
            'status_color' => $this->getStatusColor($order->status),
            'progress' => $this->getProgress($order->status),
            'courier' => $order->courier ? [
                'name' => $order->courier->name,
                'phone' => $order->courier->phone,
            ] : null,
            'eta' => $this->calculateEta($order),
            'timestamps' => [
                'created' => $order->created_at?->format('H:i'),
                'cooking_started' => $order->cooking_started_at?->format('H:i'),
                'ready' => $order->ready_at?->format('H:i'),
                'picked_up' => $order->picked_up_at?->format('H:i'),
                'delivered' => $order->delivered_at?->format('H:i'),
            ],
            'is_completed' => in_array($order->status, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value]),
        ]);
    }

    /**
     * Получить шаги статуса для отображения прогресса
     */
    private function getStatusSteps(Order $order): array
    {
        $steps = [
            [
                'key' => 'new',
                'label' => 'Принят',
                'icon' => 'clipboard-check',
                'time' => $order->created_at?->format('H:i'),
                'completed' => true,
            ],
            [
                'key' => 'cooking',
                'label' => 'Готовится',
                'icon' => 'fire',
                'time' => $order->cooking_started_at?->format('H:i'),
                'completed' => in_array($order->status, ['cooking', 'ready', 'delivering', 'completed']),
            ],
            [
                'key' => 'ready',
                'label' => 'Готов',
                'icon' => 'check-circle',
                'time' => $order->ready_at?->format('H:i'),
                'completed' => in_array($order->status, ['ready', 'delivering', 'completed']),
            ],
            [
                'key' => 'delivering',
                'label' => 'В пути',
                'icon' => 'truck',
                'time' => $order->picked_up_at?->format('H:i'),
                'completed' => in_array($order->status, ['delivering', 'completed']),
            ],
            [
                'key' => 'completed',
                'label' => 'Доставлен',
                'icon' => 'home',
                'time' => $order->delivered_at?->format('H:i'),
                'completed' => $order->status === 'completed',
            ],
        ];

        // Определяем текущий шаг
        $currentIndex = match($order->status) {
            'new', 'confirmed' => 0,
            'cooking' => 1,
            'ready' => 2,
            'delivering' => 3,
            'completed' => 4,
            'cancelled' => -1,
            default => 0,
        };

        foreach ($steps as $index => &$step) {
            $step['current'] = $index === $currentIndex;
            $step['active'] = $index <= $currentIndex;
        }

        return $steps;
    }

    /**
     * Рассчитать примерное время доставки
     */
    private function calculateEta(Order $order): ?string
    {
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return null;
        }

        $estimatedMinutes = $order->estimated_delivery_minutes ?? $order->deliveryZone?->estimated_time ?? 45;

        // Корректируем в зависимости от статуса
        $elapsed = $order->created_at?->diffInMinutes(now()) ?? 0;
        $remaining = max(0, $estimatedMinutes - $elapsed);

        if ($remaining <= 0) {
            return 'Скоро';
        }

        if ($remaining <= 5) {
            return '~5 мин';
        }

        return "~{$remaining} мин";
    }

    /**
     * Получить процент прогресса
     */
    private function getProgress(string $status): int
    {
        return match($status) {
            'new', 'confirmed' => 10,
            'cooking' => 35,
            'ready' => 60,
            'delivering' => 85,
            'completed' => 100,
            'cancelled' => 0,
            default => 0,
        };
    }

    /**
     * Получить метку статуса
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'new' => 'Заказ принят',
            'confirmed' => 'Заказ подтверждён',
            'cooking' => 'Готовится на кухне',
            'ready' => 'Готов к отправке',
            'delivering' => 'Курьер в пути',
            'completed' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $status,
        };
    }

    /**
     * Получить иконку статуса
     */
    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'new' => '📋',
            'confirmed' => '✓',
            'cooking' => '👨‍🍳',
            'ready' => '✅',
            'delivering' => '🚗',
            'completed' => '🎉',
            'cancelled' => '❌',
            default => '📦',
        };
    }

    /**
     * Получить цвет статуса
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'new' => '#3B82F6',
            'confirmed' => '#8B5CF6',
            'cooking' => '#F59E0B',
            'ready' => '#10B981',
            'delivering' => '#06B6D4',
            'completed' => '#6B7280',
            'cancelled' => '#EF4444',
            default => '#6B7280',
        };
    }

    /**
     * Страница live-трекинга с картой
     */
    public function showLive(string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->orWhere('order_number', '#' . $orderNumber)
            ->orWhere('daily_number', $orderNumber)
            ->orWhere('daily_number', '#' . $orderNumber)
            ->where('type', 'delivery')
            ->with(['items', 'courier', 'deliveryZone'])
            ->first();

        if (!$order) {
            abort(404, 'Заказ не найден');
        }

        // Генерируем или получаем токен для трекинга
        $trackingToken = TrackingToken::generateForOrder($order);

        return view('tracking.live', [
            'order' => $order,
            'trackingToken' => $trackingToken->token,
            'yandexApiKey' => config('services.yandex.js_api_key', ''),
        ]);
    }
}
