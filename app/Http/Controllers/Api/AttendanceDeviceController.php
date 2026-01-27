<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\AttendanceQrCode;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AnvizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceDeviceController extends Controller
{
    public function __construct(
        protected AnvizService $anvizService,
    ) {}

    /**
     * Получить список устройств ресторана
     * GET /api/backoffice/attendance/devices
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $devices = AttendanceDevice::forRestaurant($restaurantId)
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn ($device) => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'type_label' => AttendanceDevice::getTypes()[$device->type] ?? $device->type,
                'model' => $device->model,
                'serial_number' => $device->serial_number,
                'ip_address' => $device->ip_address,
                'status' => $device->status,
                'is_online' => $device->isOnline(),
                'users_count' => $device->users_count,
                'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Создать устройство
     * POST /api/backoffice/attendance/devices
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:anviz,zkteco,hikvision,generic',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'required|string|max:100|unique:attendance_devices',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'settings' => 'nullable|array',
        ]);

        $restaurantId = Auth::user()->restaurant_id;

        $device = AttendanceDevice::create([
            'restaurant_id' => $restaurantId,
            ...$validated,
            'api_key' => bin2hex(random_bytes(32)),
            'status' => AttendanceDevice::STATUS_ACTIVE,
        ]);

        return response()->json([
            'success' => true,
            'data' => $device,
            'api_key' => $device->api_key, // Показываем только при создании
            'webhook_url' => url("/api/attendance/webhook/{$device->type}"),
        ], 201);
    }

    /**
     * Получить устройство
     * GET /api/backoffice/attendance/devices/{id}
     */
    public function show(int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $device->load('users:id,name,role');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'type_label' => AttendanceDevice::getTypes()[$device->type] ?? $device->type,
                'model' => $device->model,
                'serial_number' => $device->serial_number,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'settings' => $device->settings,
                'status' => $device->status,
                'is_online' => $device->isOnline(),
                'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                'users' => $device->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role,
                    'device_user_id' => $u->pivot->device_user_id,
                    'is_synced' => $u->pivot->is_synced,
                    'synced_at' => $u->pivot->synced_at,
                    'face_status' => $u->pivot->face_status ?? 'none',
                    'face_enrolled_at' => $u->pivot->face_enrolled_at,
                    'face_templates_count' => $u->pivot->face_templates_count ?? 0,
                    'fingerprint_status' => $u->pivot->fingerprint_status ?? 'none',
                    'fingerprint_enrolled_at' => $u->pivot->fingerprint_enrolled_at,
                    'card_number' => $u->pivot->card_number,
                    'sync_error' => $u->pivot->sync_error,
                    'has_biometric' => in_array($u->pivot->face_status, ['enrolled']) || in_array($u->pivot->fingerprint_status, ['enrolled']),
                    'needs_enrollment' => $u->pivot->is_synced && !in_array($u->pivot->face_status, ['enrolled']) && !in_array($u->pivot->fingerprint_status, ['enrolled']),
                ]),
                'webhook_url' => url("/api/attendance/webhook/{$device->type}"),
            ],
        ]);
    }

    /**
     * Обновить устройство
     * PUT /api/backoffice/attendance/devices/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => "sometimes|string|max:100|unique:attendance_devices,serial_number,{$id}",
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'settings' => 'nullable|array',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $device->update($validated);

        return response()->json([
            'success' => true,
            'data' => $device->fresh(),
        ]);
    }

    /**
     * Удалить устройство
     * DELETE /api/backoffice/attendance/devices/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $device->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Перегенерировать API ключ
     * POST /api/backoffice/attendance/devices/{id}/regenerate-key
     */
    public function regenerateKey(int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $apiKey = $device->regenerateApiKey();

        return response()->json([
            'success' => true,
            'api_key' => $apiKey,
        ]);
    }

    /**
     * Синхронизировать пользователей с устройством
     * POST /api/backoffice/attendance/devices/{id}/sync-users
     */
    public function syncUsers(Request $request, int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $results = [];
        $users = User::whereIn('id', $request->input('user_ids'))->get();

        foreach ($users as $user) {
            if ($device->type === AttendanceDevice::TYPE_ANVIZ) {
                $result = $this->anvizService->syncUserToDevice($device, $user);
            } else {
                // Для других типов просто добавляем связь
                $device->syncUser($user, (string) $user->id);
                $result = ['success' => true, 'user_id' => $user->id];
            }

            $results[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                ...$result,
            ];
        }

        $device->markSynced();

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Проверить подключение к устройству
     * POST /api/backoffice/attendance/devices/{id}/test-connection
     */
    public function testConnection(int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if ($device->type === AttendanceDevice::TYPE_ANVIZ) {
            $result = $this->anvizService->getDeviceInfo($device);
        } else {
            // Для других типов просто проверяем доступность IP
            if (!$device->ip_address) {
                $result = ['success' => false, 'error' => 'no_ip'];
            } else {
                $connection = @fsockopen($device->ip_address, $device->port ?? 80, $errno, $errstr, 5);
                $result = $connection
                    ? ['success' => true, 'message' => 'Соединение установлено']
                    : ['success' => false, 'error' => 'connection_failed'];
                if ($connection) {
                    fclose($connection);
                }
            }
        }

        return response()->json($result);
    }

    // ==================== ДОСТУП ПОЛЬЗОВАТЕЛЯ К УСТРОЙСТВАМ ====================

    /**
     * Получить список устройств с доступом для конкретного пользователя
     * GET /api/backoffice/attendance/users/{userId}/devices
     */
    public function getUserDevices(int $userId): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        // Проверяем что пользователь принадлежит ресторану
        $user = User::where('id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        // Все активные устройства ресторана
        $devices = AttendanceDevice::forRestaurant($restaurantId)
            ->active()
            ->get();

        // Связи пользователя с устройствами
        $userDevices = \DB::table('attendance_device_users')
            ->where('user_id', $userId)
            ->whereIn('device_id', $devices->pluck('id'))
            ->get()
            ->keyBy('device_id');

        $result = $devices->map(function ($device) use ($userDevices) {
            $pivot = $userDevices->get($device->id);

            return [
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => $device->type,
                    'type_label' => AttendanceDevice::getTypes()[$device->type] ?? $device->type,
                    'status' => $device->status,
                    'is_online' => $device->isOnline(),
                ],
                'access' => $pivot ? [
                    'granted' => true,
                    'device_user_id' => $pivot->device_user_id,
                    'is_synced' => (bool) $pivot->is_synced,
                    'synced_at' => $pivot->synced_at,
                    'face_status' => $pivot->face_status ?? 'none',
                    'face_enrolled_at' => $pivot->face_enrolled_at,
                    'face_templates_count' => $pivot->face_templates_count ?? 0,
                    'fingerprint_status' => $pivot->fingerprint_status ?? 'none',
                    'fingerprint_enrolled_at' => $pivot->fingerprint_enrolled_at,
                    'card_number' => $pivot->card_number,
                    'sync_error' => $pivot->sync_error,
                    'has_biometric' => in_array($pivot->face_status ?? 'none', ['enrolled']) ||
                                      in_array($pivot->fingerprint_status ?? 'none', ['enrolled']),
                    'needs_enrollment' => $pivot->is_synced &&
                                         !in_array($pivot->face_status ?? 'none', ['enrolled']) &&
                                         !in_array($pivot->fingerprint_status ?? 'none', ['enrolled']),
                ] : [
                    'granted' => false,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
                'devices' => $result,
            ],
        ]);
    }

    /**
     * Сводный биометрический статус пользователя
     * GET /api/backoffice/attendance/users/{userId}/biometric-status
     */
    public function getUserBiometricStatus(int $userId): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $user = User::where('id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        $totalDevices = AttendanceDevice::forRestaurant($restaurantId)->active()->count();

        $userDevices = \DB::table('attendance_device_users as adu')
            ->join('attendance_devices as ad', 'ad.id', '=', 'adu.device_id')
            ->where('adu.user_id', $userId)
            ->where('ad.restaurant_id', $restaurantId)
            ->where('ad.status', 'active')
            ->select('adu.*', 'ad.name as device_name')
            ->get();

        $stats = [
            'total_devices' => $totalDevices,
            'devices_with_access' => $userDevices->count(),
            'devices_synced' => $userDevices->where('is_synced', true)->count(),
            'devices_pending' => $userDevices->where('is_synced', false)->count(),
            'devices_with_error' => $userDevices->whereNotNull('sync_error')->count(),
            'face_enrolled' => $userDevices->where('face_status', 'enrolled')->count(),
            'fingerprint_enrolled' => $userDevices->where('fingerprint_status', 'enrolled')->count(),
            'needs_enrollment' => $userDevices->filter(function ($d) {
                return $d->is_synced &&
                       !in_array($d->face_status ?? 'none', ['enrolled']) &&
                       !in_array($d->fingerprint_status ?? 'none', ['enrolled']);
            })->count(),
        ];

        // Определяем общий статус
        $overallStatus = 'none';
        if ($userDevices->count() > 0) {
            if ($stats['devices_with_error'] > 0) {
                $overallStatus = 'error';
            } elseif ($stats['devices_pending'] > 0) {
                $overallStatus = 'pending';
            } elseif ($stats['needs_enrollment'] > 0) {
                $overallStatus = 'needs_enrollment';
            } elseif ($stats['face_enrolled'] > 0 || $stats['fingerprint_enrolled'] > 0) {
                $overallStatus = 'enrolled';
            } else {
                $overallStatus = 'synced';
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'overall_status' => $overallStatus,
                'stats' => $stats,
                'devices' => $userDevices->map(fn ($d) => [
                    'device_id' => $d->device_id,
                    'device_name' => $d->device_name,
                    'device_user_id' => $d->device_user_id,
                    'is_synced' => (bool) $d->is_synced,
                    'face_status' => $d->face_status ?? 'none',
                    'face_enrolled_at' => $d->face_enrolled_at,
                    'fingerprint_status' => $d->fingerprint_status ?? 'none',
                    'sync_error' => $d->sync_error,
                ]),
            ],
        ]);
    }

    // ==================== НАСТРОЙКИ РЕСТОРАНА ====================

    /**
     * Получить настройки учёта времени ресторана
     * GET /api/backoffice/attendance/settings
     */
    public function getSettings(): JsonResponse
    {
        $restaurant = Restaurant::find(Auth::user()->restaurant_id);

        $qrCode = AttendanceQrCode::forRestaurant($restaurant->id)->active()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'attendance_mode' => $restaurant->attendance_mode,
                'attendance_early_minutes' => $restaurant->attendance_early_minutes,
                'attendance_late_minutes' => $restaurant->attendance_late_minutes,
                'latitude' => $restaurant->latitude,
                'longitude' => $restaurant->longitude,
                'qr_code' => $qrCode ? [
                    'id' => $qrCode->id,
                    'type' => $qrCode->type,
                    'require_geolocation' => $qrCode->require_geolocation,
                    'max_distance_meters' => $qrCode->max_distance_meters,
                    'refresh_interval_minutes' => $qrCode->refresh_interval_minutes,
                    'is_active' => $qrCode->is_active,
                ] : null,
                'modes' => [
                    ['value' => 'disabled', 'label' => 'Отключён (свободный режим)'],
                    ['value' => 'device_only', 'label' => 'Только терминал'],
                    ['value' => 'qr_only', 'label' => 'Только QR-код'],
                    ['value' => 'device_or_qr', 'label' => 'Терминал или QR-код'],
                ],
            ],
        ]);
    }

    /**
     * Обновить настройки учёта времени
     * PUT /api/backoffice/attendance/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_mode' => 'sometimes|string|in:disabled,device_only,qr_only,device_or_qr',
            'attendance_early_minutes' => 'sometimes|integer|min:0|max:120',
            'attendance_late_minutes' => 'sometimes|integer|min:0|max:480',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $restaurant = Restaurant::find(Auth::user()->restaurant_id);
        $restaurant->update($validated);

        // Создаём QR-код если включён QR режим
        if (in_array($validated['attendance_mode'] ?? $restaurant->attendance_mode, ['qr_only', 'device_or_qr'])) {
            AttendanceQrCode::getOrCreateForRestaurant($restaurant->id);
        }

        return response()->json([
            'success' => true,
            'data' => $restaurant->fresh(),
        ]);
    }

    /**
     * Настроить QR-код
     * PUT /api/backoffice/attendance/qr-settings
     */
    public function updateQrSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:static,dynamic',
            'require_geolocation' => 'sometimes|boolean',
            'max_distance_meters' => 'sometimes|integer|min:10|max:1000',
            'refresh_interval_minutes' => 'sometimes|integer|min:1|max:60',
        ]);

        $restaurantId = Auth::user()->restaurant_id;
        $qrCode = AttendanceQrCode::forRestaurant($restaurantId)->active()->first();

        if (!$qrCode) {
            $qrCode = AttendanceQrCode::createForRestaurant($restaurantId, $validated['type'] ?? 'dynamic', $validated);
        } else {
            $qrCode->update($validated);
            if (isset($validated['type']) && $validated['type'] === 'dynamic' && $qrCode->type !== 'dynamic') {
                $qrCode->refresh();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $qrCode->fresh(),
        ]);
    }

    // ==================== СОБЫТИЯ ====================

    /**
     * Получить события учёта времени
     * GET /api/backoffice/attendance/events
     */
    public function events(Request $request): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $query = AttendanceEvent::forRestaurant($restaurantId)
            ->with(['user:id,name,role', 'device:id,name,type'])
            ->orderBy('event_time', 'desc');

        if ($request->has('user_id')) {
            $query->forUser($request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->forDate($request->input('date'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forPeriod($request->input('start_date'), $request->input('end_date'));
        }

        $events = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Получить событие
     * GET /api/backoffice/attendance/events/{id}
     */
    public function showEvent(int $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (!$event) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $event->load(['user:id,name,role,avatar', 'device:id,name,type', 'workSession']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_time' => $event->event_time->toIso8601String(),
                'source' => $event->source,
                'verification_method' => $event->verification_method,
                'confidence' => $event->confidence,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'ip_address' => $event->ip_address,
                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'role' => $event->user->role,
                    'avatar' => $event->user->avatar,
                ] : null,
                'device' => $event->device ? [
                    'id' => $event->device->id,
                    'name' => $event->device->name,
                    'type' => $event->device->type,
                ] : null,
                'work_session' => $event->workSession ? [
                    'id' => $event->workSession->id,
                    'clock_in' => $event->workSession->clock_in?->toIso8601String(),
                    'clock_out' => $event->workSession->clock_out?->toIso8601String(),
                    'status' => $event->workSession->status,
                ] : null,
                'created_at' => $event->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Создать событие вручную (для ручных корректировок)
     * POST /api/backoffice/attendance/events
     */
    public function createEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'event_type' => 'required|string|in:clock_in,clock_out',
            'event_time' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $restaurantId = Auth::user()->restaurant_id;

        // Проверяем что пользователь принадлежит ресторану
        $user = User::where('id', $validated['user_id'])
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'user_not_found',
                'message' => 'Сотрудник не найден',
            ], 404);
        }

        $event = AttendanceEvent::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'event_type' => $validated['event_type'],
            'event_time' => $validated['event_time'],
            'source' => AttendanceEvent::SOURCE_MANUAL,
            'verification_method' => AttendanceEvent::METHOD_MANUAL,
            'raw_data' => [
                'created_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $event,
        ], 201);
    }

    /**
     * Обновить событие (корректировка времени)
     * PUT /api/backoffice/attendance/events/{id}
     */
    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (!$event) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'event_time' => 'sometimes|date',
            'event_type' => 'sometimes|string|in:clock_in,clock_out',
            'notes' => 'nullable|string|max:500',
        ]);

        // Сохраняем историю изменений
        $rawData = $event->raw_data ?? [];
        $rawData['corrections'] = $rawData['corrections'] ?? [];
        $rawData['corrections'][] = [
            'corrected_by' => Auth::id(),
            'corrected_at' => now()->toIso8601String(),
            'old_event_time' => $event->event_time->toIso8601String(),
            'old_event_type' => $event->event_type,
            'notes' => $validated['notes'] ?? null,
        ];

        $updateData = array_filter([
            'event_time' => $validated['event_time'] ?? null,
            'event_type' => $validated['event_type'] ?? null,
        ]);
        $updateData['raw_data'] = $rawData;

        $event->update($updateData);

        return response()->json([
            'success' => true,
            'data' => $event->fresh(),
        ]);
    }

    /**
     * Удалить событие
     * DELETE /api/backoffice/attendance/events/{id}
     */
    public function deleteEvent(int $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (!$event) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        // Нельзя удалять события от устройств
        if ($event->source === AttendanceEvent::SOURCE_DEVICE) {
            return response()->json([
                'success' => false,
                'error' => 'cannot_delete_device_event',
                'message' => 'Нельзя удалить событие от устройства. Используйте корректировку времени.',
            ], 400);
        }

        $event->delete();

        return response()->json(['success' => true]);
    }

    // ==================== DEVICE USER SYNC ====================

    /**
     * Получить список пользователей с устройства
     * GET /api/backoffice/attendance/devices/{id}/device-users
     */
    public function getDeviceUsers(int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if ($device->type !== AttendanceDevice::TYPE_ANVIZ) {
            return response()->json([
                'success' => false,
                'error' => 'unsupported_device_type',
                'message' => 'Данный тип устройства не поддерживает синхронизацию',
            ], 400);
        }

        $result = $this->anvizService->getDeviceUsers($device);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        // Добавляем информацию о связанных пользователях PosLab
        $linkedUsers = $device->users()->get()->keyBy('pivot.device_user_id');

        $deviceUsers = collect($result['users'])->map(function ($user) use ($linkedUsers) {
            $linkedUser = $linkedUsers->get((string)$user['user_id']);
            return [
                ...$user,
                'poslab_user' => $linkedUser ? [
                    'id' => $linkedUser->id,
                    'name' => $linkedUser->name,
                    'role' => $linkedUser->role,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $deviceUsers,
            'device_info' => $result['device_info'] ?? null,
        ]);
    }

    /**
     * Добавить пользователя на устройство
     * POST /api/backoffice/attendance/devices/{id}/device-users
     */
    public function addDeviceUser(Request $request, int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'device_user_id' => 'nullable|integer|min:1|max:65535',
        ]);

        // Проверяем что пользователь принадлежит ресторану
        $user = User::where('id', $validated['user_id'])
            ->where('restaurant_id', Auth::user()->restaurant_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'user_not_found',
            ], 404);
        }

        // Если device_user_id не указан, генерируем следующий свободный
        $deviceUserId = $validated['device_user_id'] ?? $this->getNextDeviceUserId($device);

        $syncStatus = 'synced';
        $syncMessage = 'Пользователь добавлен на устройство';
        $syncError = null;
        $warning = null;

        if ($device->type === AttendanceDevice::TYPE_ANVIZ) {
            $result = $this->anvizService->addUserToDevice($device, $user, $deviceUserId);

            if (!$result['success']) {
                // Устройство не поддерживает автоматическую синхронизацию - требуется ручная регистрация
                $syncStatus = 'pending';
                $syncError = 'Требуется ручная регистрация на устройстве';
                $warning = "Добавьте пользователя на устройстве вручную с ID: {$deviceUserId}";
                $syncMessage = 'Требуется ручная регистрация';
            }
        }

        // Создаём связь в БД
        $device->syncUser($user, (string)$deviceUserId, [
            'sync_status' => $syncStatus,
            'sync_error' => $syncError,
        ]);

        $response = [
            'success' => true,
            'message' => $syncMessage,
            'device_user_id' => $deviceUserId,
            'sync_status' => $syncStatus,
        ];

        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response);
    }

    /**
     * Удалить пользователя с устройства
     * DELETE /api/backoffice/attendance/devices/{id}/device-users/{deviceUserId}
     */
    public function removeDeviceUser(int $id, int $deviceUserId): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $warning = null;

        // Пробуем удалить с устройства (но не блокируем если не получилось)
        if ($device->type === AttendanceDevice::TYPE_ANVIZ) {
            $result = $this->anvizService->removeUserFromDeviceTcp($device, $deviceUserId);

            if (!$result['success']) {
                // Устройство не ответило — удаляем только из базы
                $warning = 'Не удалось удалить с устройства (удалите вручную). Доступ в системе отозван.';
            }
        }

        // Всегда удаляем связь из БД
        $device->users()->wherePivot('device_user_id', (string)$deviceUserId)->detach();

        $response = [
            'success' => true,
            'message' => 'Доступ отозван',
        ];

        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response);
    }

    /**
     * Обновить device_user_id для существующей связи
     * PATCH /api/backoffice/attendance/devices/{id}/device-users/{deviceUserId}
     */
    public function updateDeviceUser(Request $request, int $id, int $deviceUserId): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'device_user_id' => 'required|integer|min:1|max:65535',
        ]);

        $newDeviceUserId = $validated['device_user_id'];

        // Находим существующую связь
        $pivot = \DB::table('attendance_device_users')
            ->where('device_id', $device->id)
            ->where('device_user_id', (string) $deviceUserId)
            ->first();

        if (!$pivot) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Связь не найдена',
            ], 404);
        }

        // Проверяем что новый ID не занят
        $exists = \DB::table('attendance_device_users')
            ->where('device_id', $device->id)
            ->where('device_user_id', (string) $newDeviceUserId)
            ->where('id', '!=', $pivot->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => 'duplicate_id',
                'message' => "ID {$newDeviceUserId} уже используется другим сотрудником",
            ], 400);
        }

        // Обновляем device_user_id
        \DB::table('attendance_device_users')
            ->where('id', $pivot->id)
            ->update([
                'device_user_id' => (string) $newDeviceUserId,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'ID обновлён',
            'device_user_id' => $newDeviceUserId,
        ]);
    }

    /**
     * Связать пользователя устройства с пользователем PosLab
     * POST /api/backoffice/attendance/devices/{id}/link-user
     */
    public function linkDeviceUser(Request $request, int $id): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'device_user_id' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::where('id', $validated['user_id'])
            ->where('restaurant_id', Auth::user()->restaurant_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'user_not_found',
            ], 404);
        }

        $device->syncUser($user, $validated['device_user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Пользователь связан',
        ]);
    }

    /**
     * Отвязать пользователя устройства
     * DELETE /api/backoffice/attendance/devices/{id}/unlink-user/{deviceUserId}
     */
    public function unlinkDeviceUser(int $id, string $deviceUserId): JsonResponse
    {
        $device = $this->findDevice($id);

        if (!$device) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $device->users()->wherePivot('device_user_id', $deviceUserId)->detach();

        return response()->json([
            'success' => true,
            'message' => 'Связь удалена',
        ]);
    }

    /**
     * Получить следующий свободный ID для устройства
     */
    protected function getNextDeviceUserId(AttendanceDevice $device): int
    {
        $maxId = $device->users()
            ->get()
            ->pluck('pivot.device_user_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int)$id)
            ->max() ?? 0;

        return $maxId + 1;
    }

    // ==================== HELPERS ====================

    protected function findEvent(int $id): ?AttendanceEvent
    {
        return AttendanceEvent::where('id', $id)
            ->where('restaurant_id', Auth::user()->restaurant_id)
            ->first();
    }

    protected function findDevice(int $id): ?AttendanceDevice
    {
        return AttendanceDevice::where('id', $id)
            ->where('restaurant_id', Auth::user()->restaurant_id)
            ->first();
    }
}
