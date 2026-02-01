<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KitchenDevice;
use App\Models\KitchenStation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KitchenDeviceController extends Controller
{
    /**
     * Регистрация нового устройства (вызывается с планшета при первом запуске)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:64',
            'name' => 'nullable|string|max:100',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Проверяем, не зарегистрировано ли уже
        $device = KitchenDevice::where('device_id', $validated['device_id'])->first();

        if ($device) {
            // Устройство уже существует - обновляем last_seen и возвращаем данные
            $device->update([
                'last_seen_at' => now(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Устройство уже зарегистрировано',
                'data' => $this->formatDeviceResponse($device),
            ]);
        }

        // Создаём новое устройство
        $device = KitchenDevice::create([
            'restaurant_id' => $restaurantId,
            'device_id' => $validated['device_id'],
            'name' => $validated['name'] ?? 'Новое устройство',
            'status' => KitchenDevice::STATUS_PENDING,
            'last_seen_at' => now(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Устройство зарегистрировано. Ожидает настройки в админке.',
            'data' => $this->formatDeviceResponse($device),
        ], 201);
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

        $device = KitchenDevice::with('kitchenStation')
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство не найдено',
                'status' => 'not_registered',
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

        if ($device->status === KitchenDevice::STATUS_PENDING || !$device->kitchen_station_id) {
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
            ->orderByDesc('last_seen_at')
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

        // Если назначили станцию - автоматически активируем
        if (isset($validated['kitchen_station_id']) && $validated['kitchen_station_id'] && $kitchenDevice->status === KitchenDevice::STATUS_PENDING) {
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
     * Сменить станцию по PIN (вызывается с планшета)
     */
    public function changeStation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'pin' => 'required|string',
            'kitchen_station_id' => 'required|exists:kitchen_stations,id',
        ]);

        $device = KitchenDevice::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Устройство не найдено',
            ], 404);
        }

        // Проверяем PIN (если установлен на устройстве или глобальный)
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
     */
    protected function formatDeviceResponse(KitchenDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'name' => $device->name,
            'status' => $device->status,
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
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        if ($request->has('restaurant_id') && $user) {
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

        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        abort(401, 'Требуется авторизация');
    }
}
