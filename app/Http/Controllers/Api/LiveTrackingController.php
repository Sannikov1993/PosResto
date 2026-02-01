<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TrackingToken;
use App\Models\CourierLocationLog;
use App\Models\RealtimeEvent;
use App\Models\Courier;
use App\Services\EtaCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Контроллер live-трекинга курьера
 *
 * Предоставляет:
 * - Публичный API для отслеживания заказа (по токену)
 * - SSE-стрим обновлений локации
 * - API обновления позиции курьера
 */
class LiveTrackingController extends Controller
{
    private EtaCalculationService $etaService;

    public function __construct(EtaCalculationService $etaService)
    {
        $this->etaService = $etaService;
    }

    // ==================== Публичные методы (по токену) ====================

    /**
     * Получить данные для отслеживания заказа
     *
     * GET /api/tracking/{token}/data
     */
    public function getTrackingData(string $token): JsonResponse
    {
        $trackingToken = TrackingToken::findByToken($token);

        if (!$trackingToken || !$trackingToken->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Недействительная ссылка для отслеживания',
            ], 403);
        }

        $order = Order::with(['courier', 'deliveryZone'])
            ->find($trackingToken->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Заказ не найден',
            ], 404);
        }

        // Получаем данные курьера и ETA
        $courierData = null;
        $eta = null;

        if ($order->courier_id && in_array($order->status, ['delivering', 'ready'])) {
            $courierLocation = $order->courier->courier_last_location;

            $courierData = [
                'name' => $order->courier->name,
                'phone' => $this->maskPhone($order->courier->phone),
                'location' => $courierLocation,
            ];

            // Рассчитываем ETA если есть координаты
            if ($courierLocation && $order->delivery_latitude && $order->delivery_longitude) {
                $eta = $this->etaService->calculateForOrder($order, $courierLocation);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $this->getStatusLabel($order->status),
                'status_color' => $this->getStatusColor($order->status),
                'delivery_address' => [
                    'lat' => $order->delivery_latitude,
                    'lng' => $order->delivery_longitude,
                    'formatted' => $order->delivery_address,
                ],
                'courier' => $courierData,
                'eta' => $eta,
                'restaurant' => [
                    'lat' => (float) config('services.yandex.restaurant_lat'),
                    'lng' => (float) config('services.yandex.restaurant_lng'),
                ],
                'is_completed' => in_array($order->status, ['completed', 'cancelled']),
                'is_cancelled' => $order->status === 'cancelled',
                'timestamps' => [
                    'created_at' => $order->created_at?->toIso8601String(),
                    'cooking_started_at' => $order->cooking_started_at?->toIso8601String(),
                    'ready_at' => $order->ready_at?->toIso8601String(),
                    'picked_up_at' => $order->picked_up_at?->toIso8601String(),
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * SSE-стрим обновлений локации курьера
     *
     * GET /api/tracking/{token}/stream
     */
    public function stream(string $token): StreamedResponse
    {
        $trackingToken = TrackingToken::findByToken($token);

        if (!$trackingToken || !$trackingToken->isValid()) {
            abort(403, 'Недействительная ссылка');
        }

        $orderId = $trackingToken->order_id;
        $channel = "tracking_{$orderId}";

        return new StreamedResponse(function () use ($orderId, $channel) {
            // Отключаем буферизацию
            if (ob_get_level()) {
                ob_end_clean();
            }

            set_time_limit(0);

            $lastEventId = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
            $lastHeartbeat = time();
            $maxRuntime = 55; // Секунд до переподключения
            $startTime = time();
            $pollInterval = 1000000; // 1 секунда в микросекундах

            // Начальное сообщение
            echo "event: connected\n";
            echo "data: " . json_encode(['channel' => $channel, 'time' => now()->toIso8601String()]) . "\n\n";
            flush();

            while (true) {
                // Проверяем таймаут
                if (time() - $startTime >= $maxRuntime) {
                    echo "event: reconnect\n";
                    echo "data: " . json_encode(['reason' => 'timeout']) . "\n\n";
                    flush();
                    break;
                }

                // Проверяем разрыв соединения
                if (connection_aborted()) {
                    break;
                }

                // Получаем новые события
                $events = RealtimeEvent::where('id', '>', $lastEventId)
                    ->where('channel', $channel)
                    ->orderBy('id')
                    ->get();

                foreach ($events as $event) {
                    echo "id: {$event->id}\n";
                    echo "event: {$event->event}\n";
                    echo "data: " . json_encode($event->data) . "\n\n";
                    $lastEventId = $event->id;
                    flush();
                }

                // Heartbeat каждые 15 секунд
                if (time() - $lastHeartbeat >= 15) {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode(['time' => now()->toIso8601String()]) . "\n\n";
                    flush();
                    $lastHeartbeat = time();
                }

                usleep($pollInterval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Polling-альтернатива для браузеров без SSE
     *
     * GET /api/tracking/{token}/poll
     */
    public function poll(Request $request, string $token): JsonResponse
    {
        $trackingToken = TrackingToken::findByToken($token);

        if (!$trackingToken || !$trackingToken->isValid()) {
            return response()->json(['error' => 'Недействительная ссылка'], 403);
        }

        $orderId = $trackingToken->order_id;
        $channel = "tracking_{$orderId}";
        $lastEventId = (int) $request->input('last_event_id', 0);

        $events = RealtimeEvent::where('id', '>', $lastEventId)
            ->where('channel', $channel)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'event' => $e->event,
                'data' => $e->data,
                'timestamp' => $e->created_at->toIso8601String(),
            ]),
            'last_event_id' => $events->isNotEmpty() ? $events->last()->id : $lastEventId,
        ]);
    }

    // ==================== Методы для курьера (авторизованные) ====================

    /**
     * Обновить позицию курьера
     *
     * POST /api/courier/location
     */
    public function updateCourierLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:10000',
            'speed' => 'nullable|numeric|min:0|max:500',
            'heading' => 'nullable|numeric|between:0,360',
        ]);

        $user = auth()->user();

        if (!$user || !$user->is_courier || !$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Доступ запрещён',
            ], 403);
        }

        // Обновляем последнюю известную позицию курьера
        $locationData = [
            'lat' => $validated['latitude'],
            'lng' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ];

        $user->update([
            'courier_last_location' => $locationData,
            'courier_last_seen' => now(),
        ]);

        // Находим активные заказы доставки для этого курьера
        $activeOrders = Order::where('courier_id', $user->id)
            ->where('type', 'delivery')
            ->where('status', 'delivering')
            ->get();

        foreach ($activeOrders as $order) {
            // Записываем в лог позиций
            CourierLocationLog::create([
                'order_id' => $order->id,
                'courier_id' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? null,
                'speed' => $validated['speed'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'recorded_at' => now(),
            ]);

            // Рассчитываем ETA
            $eta = null;
            if ($order->delivery_latitude && $order->delivery_longitude) {
                $eta = $this->etaService->calculateForOrder($order, $locationData);
            }

            // Создаём событие для SSE-стрима
            RealtimeEvent::create([
                'restaurant_id' => $order->restaurant_id,
                'channel' => "tracking_{$order->id}",
                'event' => 'courier_location',
                'data' => [
                    'order_id' => $order->id,
                    'location' => [
                        'lat' => $validated['latitude'],
                        'lng' => $validated['longitude'],
                        'accuracy' => $validated['accuracy'] ?? null,
                        'heading' => $validated['heading'] ?? null,
                    ],
                    'eta' => $eta,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'active_orders' => $activeOrders->count(),
        ]);
    }

    // ==================== Хелперы ====================

    /**
     * Маскировать телефон для приватности
     * +7 (999) 123-45-67 -> +7 (999) ***-**-67
     */
    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // Оставляем только цифры
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 10) {
            return $phone;
        }

        // Показываем первые 4 и последние 2 цифры
        $visible = substr($digits, 0, 4) . '***' . substr($digits, -2);

        return $visible;
    }

    /**
     * Получить label статуса на русском
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Заказ принят',
            'confirmed' => 'Подтверждён',
            'cooking' => 'Готовится',
            'ready' => 'Готов к отправке',
            'delivering' => 'Курьер в пути',
            'completed' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $status,
        };
    }

    /**
     * Получить цвет статуса
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'new', 'confirmed' => '#3B82F6',  // Синий
            'cooking' => '#F59E0B',            // Жёлтый
            'ready' => '#10B981',              // Зелёный
            'delivering' => '#8B5CF6',         // Фиолетовый
            'completed' => '#6B7280',          // Серый
            'cancelled' => '#EF4444',          // Красный
            default => '#6B7280',
        };
    }
}
