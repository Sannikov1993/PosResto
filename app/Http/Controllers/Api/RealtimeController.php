<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RealtimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    /**
     * Server-Sent Events stream
     * Клиент подключается и получает события в реальном времени
     */
    public function stream(Request $request): StreamedResponse
    {
        $lastEventId = (int) $request->header('Last-Event-ID', $request->input('last_id', 0));
        $channels = $request->input('channels', []);
        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }
        $restaurantId = $request->input('restaurant_id', 1);

        return new StreamedResponse(function () use ($lastEventId, $channels, $restaurantId) {
            // Отключаем буферизацию
            if (ob_get_level()) ob_end_clean();
            
            // Устанавливаем максимальное время выполнения
            set_time_limit(0);
            
            $lastId = $lastEventId;
            $heartbeatInterval = 15; // секунд
            $lastHeartbeat = time();
            $maxRuntime = 55; // секунд (чуть меньше минуты для перезапуска)
            $startTime = time();

            while (true) {
                // Проверяем таймаут
                if (time() - $startTime >= $maxRuntime) {
                    echo "event: reconnect\n";
                    echo "data: {\"reason\": \"timeout\"}\n\n";
                    flush();
                    break;
                }

                // Проверяем отключение клиента
                if (connection_aborted()) {
                    break;
                }

                // Получаем новые события
                $events = RealtimeEvent::getAfter($lastId, $channels, $restaurantId);

                foreach ($events as $event) {
                    echo "id: {$event->id}\n";
                    echo "event: {$event->event}\n";
                    echo "data: " . json_encode([
                        'id' => $event->id,
                        'channel' => $event->channel,
                        'event' => $event->event,
                        'data' => $event->data,
                        'timestamp' => $event->created_at->toIso8601String(),
                    ]) . "\n\n";
                    
                    $lastId = $event->id;
                }

                // Heartbeat для поддержания соединения
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode(['time' => now()->toIso8601String()]) . "\n\n";
                    $lastHeartbeat = time();
                }

                flush();
                
                // Небольшая пауза чтобы не грузить сервер
                usleep(500000); // 0.5 секунды
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // для nginx
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Long Polling альтернатива для SSE
     * Для браузеров/клиентов которые не поддерживают SSE
     */
    public function poll(Request $request): JsonResponse
    {
        $lastEventId = (int) $request->input('last_id', 0);
        $channels = $request->input('channels', []);
        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }
        $restaurantId = $request->input('restaurant_id', 1);
        $timeout = min((int) $request->input('timeout', 20), 30); // макс 30 сек

        $startTime = time();
        $events = collect();

        // Ждём события или таймаут
        while (time() - $startTime < $timeout) {
            $events = RealtimeEvent::getAfter($lastEventId, $channels, $restaurantId);
            
            if ($events->isNotEmpty()) {
                break;
            }
            
            usleep(500000); // 0.5 секунды
        }

        $lastId = $events->isNotEmpty() ? $events->last()->id : $lastEventId;

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events->map(fn($e) => [
                    'id' => $e->id,
                    'channel' => $e->channel,
                    'event' => $e->event,
                    'data' => $e->data,
                    'timestamp' => $e->created_at->toIso8601String(),
                ]),
                'last_id' => $lastId,
            ],
        ]);
    }

    /**
     * Получить последние события (без ожидания)
     */
    public function recent(Request $request): JsonResponse
    {
        $channels = $request->input('channels', []);
        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }
        $restaurantId = $request->input('restaurant_id', 1);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = RealtimeEvent::where('restaurant_id', $restaurantId)
            ->orderByDesc('id')
            ->limit($limit);

        if (!empty($channels)) {
            $query->whereIn('channel', $channels);
        }

        $events = $query->get()->reverse()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events->map(fn($e) => [
                    'id' => $e->id,
                    'channel' => $e->channel,
                    'event' => $e->event,
                    'data' => $e->data,
                    'timestamp' => $e->created_at->toIso8601String(),
                ]),
                'last_id' => $events->last()?->id ?? 0,
            ],
        ]);
    }

    /**
     * Отправить кастомное событие (для тестирования или уведомлений)
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|max:50',
            'event' => 'required|string|max:50',
            'data' => 'nullable|array',
        ]);

        $event = RealtimeEvent::dispatch(
            $validated['channel'],
            $validated['event'],
            $validated['data'] ?? [],
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Событие отправлено',
            'data' => [
                'id' => $event->id,
                'channel' => $event->channel,
                'event' => $event->event,
            ],
        ]);
    }

    /**
     * Получить текущий статус / последний ID
     */
    public function status(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        
        $lastEvent = RealtimeEvent::where('restaurant_id', $restaurantId)
            ->orderByDesc('id')
            ->first();

        $stats = RealtimeEvent::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', now()->subHour())
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        return response()->json([
            'success' => true,
            'data' => [
                'last_id' => $lastEvent?->id ?? 0,
                'last_event_at' => $lastEvent?->created_at?->toIso8601String(),
                'events_last_hour' => $stats,
            ],
        ]);
    }

    /**
     * Очистка старых событий
     */
    public function cleanup(): JsonResponse
    {
        $deleted = RealtimeEvent::cleanup();

        return response()->json([
            'success' => true,
            'message' => "Удалено {$deleted} старых событий",
        ]);
    }
}
