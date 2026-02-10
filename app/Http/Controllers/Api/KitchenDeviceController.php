<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KitchenDevice;
use App\Models\KitchenStation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\BroadcastsEvents;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;

class KitchenDeviceController extends Controller
{
    use BroadcastsEvents;
    /**
     * Создать новое устройство (из админки)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'status' => 'sometimes|in:pending,active,disabled',
            'pin' => 'nullable|string|max:6',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $device = KitchenDevice::create([
            'restaurant_id' => $restaurantId,
            'name' => $validated['name'],
            'kitchen_station_id' => $validated['kitchen_station_id'] ?? null,
            'status' => $validated['status'] ?? KitchenDevice::STATUS_ACTIVE, // Сразу активное
            'pin' => $validated['pin'] ?? null,
        ]);

        // Сразу генерируем код привязки
        $device->generateLinkingCode();

        return response()->json([
            'success' => true,
            'message' => 'Устройство создано',
            'data' => $this->formatDeviceResponse($device->fresh('kitchenStation')),
        ], 201);
    }

    /**
     * Сгенерировать новый код привязки (из админки)
     */
    public function regenerateLinkingCode(KitchenDevice $kitchenDevice): JsonResponse
    {
        $code = $kitchenDevice->generateLinkingCode();

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $code,
                'expires_at' => $kitchenDevice->linking_code_expires_at->toIso8601String(),
                'expires_in_seconds' => 600,
            ],
        ]);
    }

    /**
     * Привязать физическое устройство по коду (вызывается с планшета)
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:64',
            'linking_code' => 'required|string|size:6',
        ]);

        // Проверяем, не привязано ли уже это физическое устройство
        // Используем withoutGlobalScopes т.к. это операция привязки ДО установки контекста
        $existingDevice = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $validated['device_id'])
            ->first();
        if ($existingDevice) {
            // Уже привязано - обновляем last_seen и возвращаем данные
            $existingDevice->update([
                'last_seen_at' => now(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Устройство уже привязано',
                'data' => $this->formatDeviceResponse($existingDevice->load('kitchenStation')),
            ]);
        }

        // Ищем устройство по коду привязки
        // Используем withoutGlobalScopes т.к. это операция привязки ДО установки контекста
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('linking_code', $validated['linking_code'])
            ->where('linking_code_expires_at', '>', now())
            ->whereNull('device_id')
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный или просроченный код',
                'error_code' => 'invalid_code',
            ], 400);
        }

        // Привязываем устройство
        $device->linkDevice(
            $validated['device_id'],
            $request->userAgent(),
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Устройство привязано',
            'data' => $this->formatDeviceResponse($device->load('kitchenStation')),
        ]);
    }

    /**
     * Получить настройки станции для устройства (вызывается с планшета)
     */
    public function myStation(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'device_id не указан',
            ], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        // Загружаем restaurant для получения timezone (изоляция: timezone берётся из ресторана устройства)
        $device = KitchenDevice::withoutGlobalScopes()
            ->with(['kitchenStation', 'restaurant'])
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство не найдено',
                'status' => 'not_linked',
            ], 404);
        }

        // Обновляем last_seen
        $device->update([
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        if ($device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство отключено',
                'status' => 'disabled',
            ], 403);
        }

        // status = pending означает что устройство ещё не настроено
        // kitchen_station_id = null означает "все цеха" — это валидная настройка
        if ($device->status === KitchenDevice::STATUS_PENDING) {
            return response()->json([
                'success' => true,
                'message' => 'Устройство ожидает настройки',
                'status' => 'pending',
                'data' => $this->formatDeviceResponse($device),
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 'configured',
            'data' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Список всех устройств (для админки)
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $devices = KitchenDevice::with('kitchenStation')
            ->where('restaurant_id', $restaurantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => $this->formatDeviceResponse($d));

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Обновить настройки устройства (из админки)
     */
    public function update(Request $request, KitchenDevice $kitchenDevice): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'status' => 'sometimes|in:pending,active,disabled',
            'pin' => 'nullable|string|max:6',
            'settings' => 'nullable|array',
        ]);

        $kitchenDevice->update($validated);

        // Если устройство в pending и его настроили — активируем
        if ($kitchenDevice->status === KitchenDevice::STATUS_PENDING) {
            $kitchenDevice->update(['status' => KitchenDevice::STATUS_ACTIVE]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Устройство обновлено',
            'data' => $this->formatDeviceResponse($kitchenDevice->fresh('kitchenStation')),
        ]);
    }

    /**
     * Удалить устройство
     */
    public function destroy(KitchenDevice $kitchenDevice): JsonResponse
    {
        $kitchenDevice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Устройство удалено',
        ]);
    }

    /**
     * Отвязать физическое устройство (сбросить привязку)
     */
    public function unlink(KitchenDevice $kitchenDevice): JsonResponse
    {
        $kitchenDevice->update([
            'device_id' => null,
            'last_seen_at' => null,
            'user_agent' => null,
            'ip_address' => null,
        ]);

        // Генерируем новый код привязки
        $kitchenDevice->generateLinkingCode();

        return response()->json([
            'success' => true,
            'message' => 'Устройство отвязано',
            'data' => $this->formatDeviceResponse($kitchenDevice->fresh('kitchenStation')),
        ]);
    }

    /**
     * Сменить станцию по PIN (вызывается с планшета)
     */
    public function changeStation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'pin' => 'required|string',
            'kitchen_station_id' => 'required|exists:kitchen_stations,id',
        ]);

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $validated['device_id'])
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство не найдено',
            ], 404);
        }

        // Проверяем PIN
        if ($device->pin && $device->pin !== $validated['pin']) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный PIN',
            ], 403);
        }

        $device->update([
            'kitchen_station_id' => $validated['kitchen_station_id'],
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Станция изменена',
            'data' => $this->formatDeviceResponse($device->fresh('kitchenStation')),
        ]);
    }

    /**
     * Форматирование ответа устройства
     *
     * Изоляция данных: timezone берётся из ресторана, к которому привязано устройство.
     * Устройство не может запросить данные чужого ресторана.
     */
    protected function formatDeviceResponse(KitchenDevice $device): array
    {
        // Timezone из настроек ресторана (изолированно - только свой ресторан)
        $timezone = $device->restaurant?->settings['timezone'] ?? config('app.timezone', 'UTC');

        $response = [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'restaurant_id' => $device->restaurant_id,
            'name' => $device->name,
            'status' => $device->status,
            'is_linked' => $device->isLinked(),
            'timezone' => $timezone,
            'kitchen_station_id' => $device->kitchen_station_id,
            'kitchen_station' => $device->kitchenStation ? [
                'id' => $device->kitchenStation->id,
                'name' => $device->kitchenStation->name,
                'slug' => $device->kitchenStation->slug,
                'icon' => $device->kitchenStation->icon,
                'color' => $device->kitchenStation->color,
            ] : null,
            'has_pin' => !empty($device->pin),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'ip_address' => $device->ip_address,
            'created_at' => $device->created_at->toIso8601String(),
        ];

        // Добавляем информацию о коде привязки (только для админки, если устройство не привязано)
        if (!$device->isLinked()) {
            $response['linking_code'] = $device->hasValidLinkingCode() ? [
                'code' => $device->linking_code,
                'expires_at' => $device->linking_code_expires_at->toIso8601String(),
                'expires_in_seconds' => $device->linking_code_expires_at->diffInSeconds(now()),
            ] : null;
        }

        return $response;
    }

    /**
     * Получить заказы для устройства кухни (без авторизации пользователя)
     * Использует device_id для определения ресторана
     */
    public function orders(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'device_id не указан',
            ], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->with('kitchenStation')
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство не найдено',
            ], 404);
        }

        if ($device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство отключено',
            ], 403);
        }

        // Обновляем last_seen
        $device->update([
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        $restaurantId = $device->restaurant_id;
        $stationSlug = $request->input('station') ?? $device->kitchenStation?->slug;

        // Get today's date in restaurant's timezone if not specified
        $date = $request->input('date')
            ?? \App\Helpers\TimeHelper::today($restaurantId)->format('Y-m-d');

        // Build query using centralized forDate scope
        // This properly handles timezone conversion to UTC for database queries
        $query = \App\Models\Order::with(['items.dish', 'table', 'waiter'])
            ->where('restaurant_id', $restaurantId)
            ->forDate($date, $restaurantId, true); // true = include active orders for today

        // Фильтрация по цеху кухни
        if ($stationSlug) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $restaurantId)
                ->first();

            if ($station) {
                $query->whereHas('items', function ($q) use ($station) {
                    $q->whereHas('dish', function ($dq) use ($station) {
                        $dq->where('kitchen_station_id', $station->id)
                            ->orWhereNull('kitchen_station_id');
                    });
                });
            }
        }

        $orders = $query->orderByDesc('created_at')->limit(500)->get();

        // Пост-обработка: фильтрация items по станции
        if ($stationSlug) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $restaurantId)
                ->first();

            if ($station) {
                $orders = $orders->map(function ($order) use ($station) {
                    $filteredItems = $order->items->filter(function ($item) use ($station) {
                        $dish = $item->dish;
                        if (!$dish) return true;
                        return $dish->kitchen_station_id === $station->id
                            || $dish->kitchen_station_id === null;
                    })->values();

                    $order->setRelation('items', $filteredItems);
                    return $order;
                })->filter(function ($order) {
                    return $order->items->count() > 0;
                })->values();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Обновить статус заказа (для устройства кухни)
     */
    public function updateOrderStatus(Request $request, \App\Models\Order $order): JsonResponse
    {
        // Проверяем device_id
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        if (!$deviceId) {
            return response()->json(['success' => false, 'message' => 'device_id не указан'], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $deviceId)
            ->first();
        if (!$device || $device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json(['success' => false, 'message' => 'Устройство не найдено или отключено'], 403);
        }

        // Проверяем что заказ принадлежит тому же ресторану
        if ($order->restaurant_id !== $device->restaurant_id) {
            return response()->json(['success' => false, 'message' => 'Заказ не найден'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:cooking,ready,return_to_new,return_to_cooking',
            'station' => 'nullable|string',
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $order->status;

        // Получаем ID станции если передан slug
        $stationId = null;
        if (!empty($validated['station'])) {
            $station = KitchenStation::where('slug', $validated['station'])->first();
            $stationId = $station?->id;
        }

        // Обновляем статусы позиций
        switch ($newStatus) {
            case 'cooking':
                // Повар взял заказ в работу
                $pendingItemsQuery = $order->items()->where('status', 'pending');
                if ($stationId) {
                    $pendingItemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $pendingItemsQuery->update(['status' => 'cooking']);

                $itemsQuery = $order->items()->where('status', 'cooking')->whereNull('cooking_started_at');
                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $itemsQuery->update(['cooking_started_at' => now()]);

                if ($order->status !== OrderStatus::COOKING->value) {
                    $order->update(['status' => OrderStatus::COOKING->value]);
                }
                break;

            case 'ready':
                $itemsQuery = $order->items()->where('status', 'cooking');
                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $itemsQuery->update(['status' => 'ready', 'cooking_finished_at' => now()]);

                $hasCookingItems = $order->items()->where('status', 'cooking')->exists();
                if (!$hasCookingItems) {
                    $order->update(['status' => OrderStatus::READY->value]);
                }
                break;

            case 'return_to_new':
                $itemsQuery = $order->items()->where('status', 'cooking')->whereNotNull('cooking_started_at');
                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $itemsQuery->update(['cooking_started_at' => null]);

                $hasStartedItems = $order->items()->where('status', 'cooking')->whereNotNull('cooking_started_at')->exists();
                if (!$hasStartedItems && $order->status === OrderStatus::COOKING->value) {
                    $order->update(['status' => OrderStatus::CONFIRMED->value]);
                }
                $newStatus = OrderStatus::CONFIRMED->value;
                break;

            case 'return_to_cooking':
                $itemsQuery = $order->items()->where('status', 'ready');
                if ($stationId) {
                    $itemsQuery->whereHas('dish', fn($q) => $q->where('kitchen_station_id', $stationId)->orWhereNull('kitchen_station_id'));
                }
                $itemsQuery->update(['status' => 'cooking', 'cooking_finished_at' => null]);

                $order->update(['status' => OrderStatus::COOKING->value]);
                $newStatus = OrderStatus::COOKING->value;
                break;
        }

        // Обновляем delivery_status для delivery, pickup и preorder заказов
        if (in_array($order->type, [OrderType::DELIVERY->value, OrderType::PICKUP->value, OrderType::PREORDER->value])) {
            $map = ['cooking' => 'preparing', 'ready' => 'ready'];
            if (isset($map[$newStatus])) {
                $order->update(['delivery_status' => $map[$newStatus]]);
            }
        }

        $freshOrder = $order->fresh();
        $freshOrder->load('table');
        $this->broadcastOrderStatusChanged($freshOrder, $oldStatus, $newStatus);

        return response()->json([
            'success' => true,
            'message' => 'Статус обновлён',
            'data' => $freshOrder->load(['items.dish', 'table']),
        ]);
    }

    /**
     * Обновить статус позиции заказа (для устройства кухни)
     */
    public function updateItemStatus(Request $request, \App\Models\OrderItem $item): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        if (!$deviceId) {
            return response()->json(['success' => false, 'message' => 'device_id не указан'], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $deviceId)
            ->first();
        if (!$device || $device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json(['success' => false, 'message' => 'Устройство не найдено'], 403);
        }

        $order = $item->order;
        if ($order->restaurant_id !== $device->restaurant_id) {
            return response()->json(['success' => false, 'message' => 'Позиция не найдена'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:cooking,ready',
        ]);

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'cooking' && !$item->cooking_started_at) {
            $updateData['cooking_started_at'] = now();
        } elseif ($validated['status'] === 'ready') {
            $updateData['cooking_finished_at'] = now();
        }

        $item->update($updateData);

        // Обновляем статус заказа если нужно
        if ($validated['status'] === 'ready') {
            $hasCookingItems = $order->items()->where('status', 'cooking')->exists();
            if (!$hasCookingItems) {
                $order->update(['status' => OrderStatus::READY->value]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Статус позиции обновлён',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Количество заказов по датам (для календаря кухни)
     */
    public function countByDates(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        if (!$deviceId) {
            return response()->json(['success' => false, 'message' => 'device_id не указан'], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->with('kitchenStation')
            ->where('device_id', $deviceId)
            ->first();
        if (!$device || $device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json(['success' => false, 'message' => 'Устройство не найдено'], 403);
        }

        $restaurantId = $device->restaurant_id;
        $stationSlug = $request->input('station') ?? $device->kitchenStation?->slug;
        $today = \Carbon\Carbon::today();
        $startDate = \Carbon\Carbon::parse($request->input('start_date', $today->copy()->subDays(7)));
        $endDate = \Carbon\Carbon::parse($request->input('end_date', $today->copy()->addDays(30)));

        // Получаем станцию если указана
        $stationId = null;
        if ($stationSlug) {
            $station = KitchenStation::where('slug', $stationSlug)
                ->where('restaurant_id', $restaurantId)
                ->first();
            $stationId = $station?->id;
        }

        // Базовый запрос — все заказы, видимые на кухне (не отменённые)
        $query = \App\Models\Order::where('restaurant_id', $restaurantId)
            ->whereNotIn('status', [OrderStatus::CANCELLED->value]);

        // Фильтрация по станции
        if ($stationId) {
            $query->whereHas('items', function ($q) use ($stationId) {
                $q->whereHas('dish', function ($dq) use ($stationId) {
                    $dq->where('kitchen_station_id', $stationId)
                       ->orWhereNull('kitchen_station_id');
                });
            });
        }

        // Получаем заказы в диапазоне дат
        $orders = $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sq) use ($startDate, $endDate) {
                    $sq->whereNotNull('scheduled_at')
                       ->where('is_asap', false)
                       ->whereBetween('scheduled_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                });
                $q->orWhere(function ($sq) use ($startDate, $endDate) {
                    $sq->where(function ($asap) {
                           $asap->whereNull('scheduled_at')
                                ->orWhere('is_asap', true);
                       })
                       ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                });
            })
            ->get(['id', 'scheduled_at', 'created_at', 'is_asap']);

        // Группируем по датам
        $counts = [];
        foreach ($orders as $order) {
            if ($order->scheduled_at && !$order->is_asap) {
                $date = \Carbon\Carbon::parse($order->scheduled_at)->format('Y-m-d');
            } else {
                $date = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');
            }

            if (!isset($counts[$date])) {
                $counts[$date] = 0;
            }
            $counts[$date]++;
        }

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Вызвать официанта (для устройства кухни)
     */
    public function callWaiter(Request $request, \App\Models\Order $order): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        if (!$deviceId) {
            return response()->json(['success' => false, 'message' => 'device_id не указан'], 400);
        }

        // Используем withoutGlobalScopes т.к. устройство определяет свой контекст
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $deviceId)
            ->first();
        if (!$device || $device->status === KitchenDevice::STATUS_DISABLED) {
            return response()->json(['success' => false, 'message' => 'Устройство не найдено'], 403);
        }

        if ($order->restaurant_id !== $device->restaurant_id) {
            return response()->json(['success' => false, 'message' => 'Заказ не найден'], 404);
        }

        $order->loadMissing(['waiter', 'table']);

        \App\Models\RealtimeEvent::create([
            'channel' => 'pos',
            'event' => 'waiter_call',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'waiter_id' => $order->user_id,
                'waiter_name' => $order->waiter?->name ?? 'Не назначен',
                'table_id' => $order->table_id,
                'table_name' => $order->table?->name ?? $order->table?->number,
                'message' => "Заказ #{$order->order_number} готов к выдаче!",
                'called_at' => now()->toISOString(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Официант вызван',
            'data' => [
                'waiter_name' => $order->waiter?->name ?? 'Официант',
            ],
        ]);
    }

    /**
     * Авторизация каналов broadcasting для кухонного устройства (без user-токена)
     *
     * Кухонные планшеты не имеют Bearer-токена, поэтому стандартный /broadcasting/auth
     * (auth:sanctum) для них не работает. Этот endpoint авторизует каналы по device_id.
     */
    public function broadcastingAuth(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        if (!$deviceId || !$socketId || !$channelName) {
            return response()->json(['message' => 'Отсутствуют обязательные параметры'], 400);
        }

        // Находим устройство
        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $deviceId)
            ->where('status', KitchenDevice::STATUS_ACTIVE)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Устройство не найдено или отключено'], 403);
        }

        // Проверяем, что канал принадлежит ресторану этого устройства
        $restaurantId = $device->restaurant_id;
        $expectedPrefix = "private-restaurant.{$restaurantId}";

        if (!str_starts_with($channelName, $expectedPrefix)) {
            return response()->json(['message' => 'Доступ к каналу запрещён'], 403);
        }

        // Генерируем Pusher auth signature
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');

        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $secret);

        return response()->json([
            'auth' => "{$key}:{$signature}",
        ]);
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Требуется авторизация');
        }

        if ($request->has('restaurant_id')) {
            if ($user->isSuperAdmin()) {
                return (int) $request->restaurant_id;
            }
            $restaurant = \App\Models\Restaurant::where('id', $request->restaurant_id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
            if ($restaurant) {
                return $restaurant->id;
            }
        }

        if ($user->restaurant_id) {
            return $user->restaurant_id;
        }

        abort(400, 'restaurant_id не указан');
    }
}
