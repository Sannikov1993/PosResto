<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TimeHelper;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\AnvizService;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceWebhookController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AnvizService $anvizService,
    ) {}

    /**
     * Универсальный webhook для устройств биометрии
     * POST /api/attendance/webhook/{type}
     *
     * @param string $type Тип устройства (anviz, zkteco, hikvision, generic)
     */
    public function handle(Request $request, string $type): JsonResponse
    {
        Log::info("Attendance webhook [{$type}]", [
            'data' => $request->except(['api_key', 'password', 'token']),
            'ip' => $request->ip(),
        ]);

        // Получаем API ключ из заголовка
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        // Проверяем тип устройства
        if (!in_array($type, ['anviz', 'zkteco', 'hikvision', 'generic'])) {
            Log::warning("Attendance webhook: unknown type [{$type}]", ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'unknown_type'], 400);
        }

        // Получаем serial number для валидации устройства и API ключа
        $data = $request->all();
        $serialNumber = $this->extractSerialNumber($data, $type);

        if (!$serialNumber) {
            Log::warning("Attendance webhook: missing serial number", ['type' => $type, 'ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'missing_serial'], 400);
        }

        // Находим устройство
        $device = AttendanceDevice::where('serial_number', $serialNumber)->first();

        if (!$device) {
            Log::warning("Attendance webhook: device not found", ['serial' => $serialNumber, 'ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'device_not_found'], 404);
        }

        // ОБЯЗАТЕЛЬНАЯ валидация API ключа для всех типов устройств
        if (!$apiKey) {
            Log::warning("Attendance webhook: missing API key", ['device_id' => $device->id, 'ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'missing_api_key'], 401);
        }

        if (!$device->validateApiKey($apiKey)) {
            Log::warning("Attendance webhook: invalid API key", ['device_id' => $device->id, 'ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'invalid_api_key'], 401);
        }

        // Помечаем heartbeat
        $device->markHeartbeat();

        // Обрабатываем по типу устройства
        $result = match($type) {
            'anviz' => $this->anvizService->handleWebhook($data, $apiKey, $device),
            'zkteco' => $this->handleZkteco($data, $device),
            'hikvision' => $this->handleHikvision($data, $device),
            'generic' => $this->handleGeneric($data, $device),
        };

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Извлечь серийный номер из данных в зависимости от типа устройства
     */
    protected function extractSerialNumber(array $data, string $type): ?string
    {
        return match($type) {
            'anviz' => $data['device_sn'] ?? $data['sn'] ?? $data['serial'] ?? $data['serial_number'] ?? null,
            'zkteco' => $data['sn'] ?? $data['serial_number'] ?? null,
            'hikvision' => $data['deviceSerialNo'] ?? $data['serialNumber'] ?? null,
            default => $data['serial_number'] ?? $data['sn'] ?? $data['device_id'] ?? null,
        };
    }

    /**
     * Heartbeat от устройства
     * POST /api/attendance/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $serialNumber = $request->input('serial_number') ?? $request->input('sn');
        $type = $request->input('type', 'generic');

        if (!$serialNumber) {
            return response()->json([
                'success' => false,
                'error' => 'missing_serial',
            ], 400);
        }

        $device = AttendanceDevice::where('serial_number', $serialNumber)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'error' => 'device_not_found',
            ], 404);
        }

        $device->markHeartbeat();

        return response()->json([
            'success' => true,
            'device_id' => $device->id,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Обработчик для ZKTeco
     */
    protected function handleZkteco(array $data, AttendanceDevice $device): array
    {
        // Преобразуем ZKTeco формат
        $eventType = ($data['punch'] ?? $data['status'] ?? 0) == 0
            ? 'clock_in'
            : 'clock_out';

        return $this->attendanceService->processDeviceEvent(
            device: $device,
            eventType: $eventType,
            deviceUserId: (string) ($data['user_id'] ?? $data['pin'] ?? ''),
            eventTime: isset($data['timestamp'])
                ? TimeHelper::parse($data['timestamp'], $device->restaurant_id)
                : TimeHelper::now($device->restaurant_id),
            rawData: $data,
        );
    }

    /**
     * Обработчик для Hikvision
     */
    protected function handleHikvision(array $data, AttendanceDevice $device): array
    {
        // Hikvision события доступа (ISAPI формат)
        $accessControlEvent = $data['AccessControlEvent'] ?? $data;

        return $this->attendanceService->processDeviceEvent(
            device: $device,
            eventType: 'clock_in', // Hikvision обычно не различает вход/выход
            deviceUserId: (string) ($accessControlEvent['employeeNoString'] ?? ''),
            eventTime: isset($accessControlEvent['time'])
                ? TimeHelper::parse($accessControlEvent['time'], $device->restaurant_id)
                : TimeHelper::now($device->restaurant_id),
            rawData: $data,
        );
    }

    /**
     * Универсальный обработчик
     */
    protected function handleGeneric(array $data, AttendanceDevice $device): array
    {
        // Универсальный формат
        $eventType = match(strtolower($data['event_type'] ?? $data['type'] ?? 'in')) {
            'in', 'clock_in', 'checkin', '0', '1' => 'clock_in',
            'out', 'clock_out', 'checkout', '2' => 'clock_out',
            default => 'clock_in',
        };

        return $this->attendanceService->processDeviceEvent(
            device: $device,
            eventType: $eventType,
            deviceUserId: (string) ($data['user_id'] ?? $data['employee_id'] ?? ''),
            eventTime: isset($data['event_time'])
                ? TimeHelper::parse($data['event_time'], $device->restaurant_id)
                : TimeHelper::now($device->restaurant_id),
            rawData: $data,
        );
    }
}
