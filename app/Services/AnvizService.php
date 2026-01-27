<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Сервис интеграции с Anviz Facepass 7 и другими устройствами Anviz
 *
 * Anviz поддерживает несколько способов интеграции:
 * 1. CrossChex Cloud API - облачный сервис Anviz
 * 2. CrossChex Standard - локальное ПО
 * 3. Прямое HTTP API устройства
 * 4. Webhook (устройство отправляет события на наш сервер)
 */
class AnvizService
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Обработать webhook от устройства Anviz
     *
     * Формат данных зависит от настройки устройства.
     * Типичный формат события:
     * {
     *   "device_sn": "ABC123",
     *   "user_id": "1",
     *   "event_time": "2024-01-24 10:00:00",
     *   "event_type": 1, // 1 = вход, 2 = выход
     *   "verify_mode": 15, // 15 = face
     *   "confidence": 98.5,
     *   "event_id": "12345"
     * }
     *
     * @param array $data Данные webhook
     * @param string|null $apiKey API ключ (валидация уже выполнена в контроллере)
     * @param AttendanceDevice|null $device Устройство (если уже найдено в контроллере)
     */
    public function handleWebhook(array $data, ?string $apiKey = null, ?AttendanceDevice $device = null): array
    {
        Log::info('Anviz webhook processing', ['device_id' => $device?->id]);

        // Если устройство не передано, ищем по серийному номеру (для обратной совместимости)
        if (!$device) {
            $serialNumber = $data['device_sn'] ?? $data['sn'] ?? $data['serial'] ?? null;

            if (!$serialNumber) {
                return [
                    'success' => false,
                    'error' => 'missing_serial',
                    'message' => 'Серийный номер устройства не указан',
                ];
            }

            $device = AttendanceDevice::where('serial_number', $serialNumber)
                ->where('type', AttendanceDevice::TYPE_ANVIZ)
                ->first();

            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'device_not_found',
                    'message' => "Устройство {$serialNumber} не зарегистрировано",
                ];
            }

            // Проверяем API ключ только если устройство не было передано
            if ($apiKey && !$device->validateApiKey($apiKey)) {
                return [
                    'success' => false,
                    'error' => 'invalid_api_key',
                    'message' => 'Неверный API ключ',
                ];
            }

            $device->markHeartbeat();
        }

        // Определяем тип события (enrollment или attendance)
        $eventCategory = $this->detectEventCategory($data);

        if ($eventCategory === 'enrollment') {
            return $this->handleEnrollment($data, $device);
        }

        // Парсим данные события attendance
        $eventType = $this->parseEventType($data);
        $eventTime = $this->parseEventTime($data);
        $deviceUserId = $data['user_id'] ?? $data['employee_id'] ?? $data['uid'] ?? null;

        if (!$deviceUserId) {
            return [
                'success' => false,
                'error' => 'missing_user_id',
                'message' => 'ID пользователя не указан',
            ];
        }

        // Обрабатываем событие attendance
        return $this->attendanceService->processDeviceEvent(
            device: $device,
            eventType: $eventType,
            deviceUserId: (string) $deviceUserId,
            eventTime: $eventTime,
            rawData: [
                'event_id' => $data['event_id'] ?? $data['record_id'] ?? null,
                'method' => $this->parseVerifyMode($data),
                'confidence' => $data['confidence'] ?? $data['score'] ?? null,
                'raw' => $data,
            ]
        );
    }

    /**
     * Обработать webhook события enrollment (регистрация биометрии)
     *
     * Формат данных:
     * {
     *   "device_sn": "ABC123",
     *   "event": "enrollment",
     *   "user_id": "5",
     *   "enroll_type": "face", // face, fingerprint, card
     *   "success": true,
     *   "templates_count": 1,
     *   "face_count": 1
     * }
     */
    public function handleEnrollment(array $data, AttendanceDevice $device): array
    {
        Log::info('Anviz enrollment processing', ['device_id' => $device->id, 'data' => $data]);

        // Извлекаем данные
        $deviceUserId = $data['user_id'] ?? $data['employee_id'] ?? $data['uid'] ?? null;
        $enrollType = strtolower($data['enroll_type'] ?? $data['type'] ?? 'face');
        $success = $data['success'] ?? $data['result'] ?? true;
        $templatesCount = $data['templates_count'] ?? $data['face_count'] ?? 1;

        if (!$deviceUserId) {
            return [
                'success' => false,
                'error' => 'missing_user_id',
                'message' => 'ID пользователя не указан',
            ];
        }

        // Находим связь пользователя с устройством
        $pivotRecord = \DB::table('attendance_device_users')
            ->where('device_id', $device->id)
            ->where('device_user_id', (string) $deviceUserId)
            ->first();

        if (!$pivotRecord) {
            Log::warning("Anviz enrollment: user not found", [
                'device_id' => $device->id,
                'device_user_id' => $deviceUserId,
            ]);

            return [
                'success' => false,
                'error' => 'user_not_found',
                'message' => "Пользователь {$deviceUserId} не найден на устройстве",
            ];
        }

        // Обновляем статус в зависимости от типа биометрии
        $updateData = [];

        if (in_array($enrollType, ['face', 'facial', '2'])) {
            if ($success) {
                $updateData['face_status'] = 'enrolled';
                $updateData['face_enrolled_at'] = now();
                $updateData['face_templates_count'] = $templatesCount;
                Log::info("Face enrolled for user {$pivotRecord->user_id} on device {$device->id}");
            } else {
                $updateData['face_status'] = 'failed';
                Log::warning("Face enrollment failed for user {$pivotRecord->user_id}");
            }
        } elseif (in_array($enrollType, ['fingerprint', 'finger', 'fp', '1'])) {
            if ($success) {
                $updateData['fingerprint_status'] = 'enrolled';
                $updateData['fingerprint_enrolled_at'] = now();
                Log::info("Fingerprint enrolled for user {$pivotRecord->user_id} on device {$device->id}");
            } else {
                $updateData['fingerprint_status'] = 'failed';
                Log::warning("Fingerprint enrollment failed for user {$pivotRecord->user_id}");
            }
        } elseif (in_array($enrollType, ['card', 'rfid', '4'])) {
            $cardNumber = $data['card_number'] ?? $data['card_no'] ?? null;
            if ($cardNumber) {
                $updateData['card_number'] = $cardNumber;
                Log::info("Card registered for user {$pivotRecord->user_id}: {$cardNumber}");
            }
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'error' => 'unknown_enroll_type',
                'message' => "Неизвестный тип регистрации: {$enrollType}",
            ];
        }

        // Обновляем запись
        \DB::table('attendance_device_users')
            ->where('id', $pivotRecord->id)
            ->update($updateData);

        return [
            'success' => true,
            'message' => 'Биометрия зарегистрирована',
            'user_id' => $pivotRecord->user_id,
            'device_user_id' => $deviceUserId,
            'enroll_type' => $enrollType,
        ];
    }

    /**
     * Обработать heartbeat от устройства
     */
    public function handleHeartbeat(string $serialNumber): array
    {
        $device = AttendanceDevice::where('serial_number', $serialNumber)
            ->where('type', AttendanceDevice::TYPE_ANVIZ)
            ->first();

        if (!$device) {
            return ['success' => false, 'error' => 'device_not_found'];
        }

        $device->markHeartbeat();

        return ['success' => true, 'device_id' => $device->id];
    }

    /**
     * Синхронизировать пользователя с устройством Anviz
     *
     * Отправляет данные пользователя на устройство через HTTP API
     */
    public function syncUserToDevice(AttendanceDevice $device, User $user): array
    {
        if (!$device->ip_address) {
            return [
                'success' => false,
                'error' => 'no_ip',
                'message' => 'IP адрес устройства не настроен',
            ];
        }

        // Формируем URL устройства
        $baseUrl = "http://{$device->ip_address}";
        if ($device->port) {
            $baseUrl .= ":{$device->port}";
        }

        try {
            // Anviz HTTP API для добавления пользователя
            // Формат может отличаться в зависимости от прошивки
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/user/add", [
                    'user_id' => (string) $user->id,
                    'name' => $user->name,
                    'card_no' => $user->card_number ?? '',
                    'role' => 0, // 0 = обычный пользователь
                ]);

            if ($response->successful()) {
                // Обновляем связь пользователя с устройством
                $device->syncUser($user, (string) $user->id);

                return [
                    'success' => true,
                    'message' => "Пользователь {$user->name} добавлен на устройство",
                ];
            }

            return [
                'success' => false,
                'error' => 'api_error',
                'message' => 'Ошибка API устройства: ' . $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('Anviz sync error', [
                'device' => $device->id,
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'connection_error',
                'message' => 'Не удалось подключиться к устройству: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Удалить пользователя с устройства
     */
    public function removeUserFromDevice(AttendanceDevice $device, User $user): array
    {
        if (!$device->ip_address) {
            return ['success' => false, 'error' => 'no_ip'];
        }

        $baseUrl = "http://{$device->ip_address}";
        if ($device->port) {
            $baseUrl .= ":{$device->port}";
        }

        try {
            $response = Http::timeout(10)
                ->delete("{$baseUrl}/api/user/{$user->id}");

            if ($response->successful()) {
                // Удаляем связь
                $device->users()->detach($user->id);

                return ['success' => true];
            }

            return ['success' => false, 'error' => 'api_error'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получить информацию об устройстве
     */
    public function getDeviceInfo(AttendanceDevice $device): array
    {
        if (!$device->ip_address) {
            return ['success' => false, 'error' => 'no_ip'];
        }

        $baseUrl = "http://{$device->ip_address}";
        if ($device->port) {
            $baseUrl .= ":{$device->port}";
        }

        try {
            $response = Http::timeout(5)->get("{$baseUrl}/api/device/info");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return ['success' => false, 'error' => 'api_error'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'offline'];
        }
    }

    /**
     * Загрузить события с устройства за период
     */
    public function fetchEvents(AttendanceDevice $device, Carbon $from, Carbon $to): array
    {
        if (!$device->ip_address) {
            return ['success' => false, 'events' => []];
        }

        $baseUrl = "http://{$device->ip_address}";
        if ($device->port) {
            $baseUrl .= ":{$device->port}";
        }

        try {
            $response = Http::timeout(30)->get("{$baseUrl}/api/records", [
                'start_time' => $from->format('Y-m-d H:i:s'),
                'end_time' => $to->format('Y-m-d H:i:s'),
            ]);

            if ($response->successful()) {
                $events = $response->json('records') ?? $response->json('data') ?? [];

                return [
                    'success' => true,
                    'events' => $events,
                ];
            }

            return ['success' => false, 'events' => []];

        } catch (\Exception $e) {
            return ['success' => false, 'events' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Определить категорию события: enrollment или attendance
     */
    protected function detectEventCategory(array $data): string
    {
        // Проверяем явное указание типа
        $event = strtolower($data['event'] ?? $data['event_type'] ?? '');

        if (str_contains($event, 'enroll') || str_contains($event, 'register')) {
            return 'enrollment';
        }

        // Проверяем наличие enrollment-специфичных полей
        if (isset($data['enroll_type']) || isset($data['templates_count']) || isset($data['face_count'])) {
            return 'enrollment';
        }

        // По умолчанию - событие посещаемости
        return 'attendance';
    }

    /**
     * Парсинг типа события
     */
    protected function parseEventType(array $data): string
    {
        $type = $data['event_type'] ?? $data['type'] ?? $data['in_out'] ?? 1;

        // Anviz: 1 = вход, 2 = выход
        // Некоторые модели: 0 = вход, 1 = выход
        if (is_numeric($type)) {
            return $type == 1 || $type == 0
                ? AttendanceEvent::TYPE_CLOCK_IN
                : AttendanceEvent::TYPE_CLOCK_OUT;
        }

        return strtolower($type) === 'in' || strtolower($type) === 'clock_in'
            ? AttendanceEvent::TYPE_CLOCK_IN
            : AttendanceEvent::TYPE_CLOCK_OUT;
    }

    /**
     * Парсинг времени события
     */
    protected function parseEventTime(array $data): Carbon
    {
        $time = $data['event_time'] ?? $data['time'] ?? $data['record_time'] ?? $data['timestamp'] ?? null;

        if (!$time) {
            return now();
        }

        if (is_numeric($time)) {
            return Carbon::createFromTimestamp($time);
        }

        return Carbon::parse($time);
    }

    /**
     * Парсинг метода верификации
     */
    protected function parseVerifyMode(array $data): string
    {
        $mode = $data['verify_mode'] ?? $data['mode'] ?? $data['method'] ?? null;

        // Anviz verify modes:
        // 1 = пароль, 2 = отпечаток, 4 = карта, 8 = face
        // Комбинации: 15 = face (8) + card (4) + finger (2) + pwd (1)
        if (is_numeric($mode)) {
            if ($mode >= 8) return AttendanceEvent::METHOD_FACE;
            if ($mode >= 4) return AttendanceEvent::METHOD_CARD;
            if ($mode >= 2) return AttendanceEvent::METHOD_FINGERPRINT;
            return AttendanceEvent::METHOD_PIN;
        }

        return match(strtolower($mode ?? '')) {
            'face', 'facial' => AttendanceEvent::METHOD_FACE,
            'finger', 'fingerprint', 'fp' => AttendanceEvent::METHOD_FINGERPRINT,
            'card', 'rfid', 'nfc' => AttendanceEvent::METHOD_CARD,
            default => AttendanceEvent::METHOD_FACE,
        };
    }

    // ==================== TCP CLIENT METHODS ====================

    /**
     * Создать TCP клиент для устройства
     */
    protected function createTcpClient(AttendanceDevice $device): ?AnvizTcpClient
    {
        if (!$device->ip_address) {
            return null;
        }

        $deviceCode = $device->settings['device_code'] ?? 1;
        $port = $device->port ?? 5010;

        return new AnvizTcpClient($device->ip_address, $port, $deviceCode);
    }

    /**
     * Получить список пользователей с устройства через TCP
     */
    public function getDeviceUsers(AttendanceDevice $device): array
    {
        $client = $this->createTcpClient($device);

        if (!$client) {
            return ['success' => false, 'error' => 'no_ip', 'message' => 'IP адрес не настроен'];
        }

        try {
            if (!$client->connect()) {
                return ['success' => false, 'error' => 'connection_failed', 'message' => 'Не удалось подключиться'];
            }

            $info = $client->getRecordInfo();
            $users = $client->getAllUsers();
            $client->disconnect();

            return [
                'success' => true,
                'users' => $users,
                'device_info' => $info,
            ];

        } catch (\Exception $e) {
            Log::error('AnvizService: getDeviceUsers failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * Добавить пользователя на устройство через TCP
     */
    public function addUserToDevice(AttendanceDevice $device, User $user, int $deviceUserId): array
    {
        $client = $this->createTcpClient($device);

        if (!$client) {
            return ['success' => false, 'error' => 'no_ip', 'message' => 'IP адрес не настроен'];
        }

        try {
            if (!$client->connect()) {
                return ['success' => false, 'error' => 'connection_failed', 'message' => 'Не удалось подключиться'];
            }

            $success = $client->addUser($deviceUserId, $user->name);
            $client->disconnect();

            if ($success) {
                return [
                    'success' => true,
                    'message' => "Пользователь {$user->name} добавлен (ID: {$deviceUserId})",
                    'device_user_id' => $deviceUserId,
                ];
            }

            return ['success' => false, 'error' => 'add_failed', 'message' => 'Устройство отклонило запрос'];

        } catch (\Exception $e) {
            Log::error('AnvizService: addUserToDevice failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * Удалить пользователя с устройства через TCP
     */
    public function removeUserFromDeviceTcp(AttendanceDevice $device, int $deviceUserId): array
    {
        $client = $this->createTcpClient($device);

        if (!$client) {
            return ['success' => false, 'error' => 'no_ip', 'message' => 'IP адрес не настроен'];
        }

        try {
            if (!$client->connect()) {
                return ['success' => false, 'error' => 'connection_failed', 'message' => 'Не удалось подключиться'];
            }

            $success = $client->deleteUser($deviceUserId);
            $client->disconnect();

            if ($success) {
                return ['success' => true, 'message' => "Пользователь {$deviceUserId} удалён"];
            }

            return ['success' => false, 'error' => 'delete_failed', 'message' => 'Устройство отклонило запрос'];

        } catch (\Exception $e) {
            Log::error('AnvizService: removeUserFromDevice failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * Проверить подключение к устройству через TCP
     */
    public function testTcpConnection(AttendanceDevice $device): array
    {
        $client = $this->createTcpClient($device);

        if (!$client) {
            return ['success' => false, 'error' => 'no_ip'];
        }

        return $client->testConnection();
    }

    /**
     * Настроить webhook на устройстве
     */
    public function configureWebhook(AttendanceDevice $device, string $webhookUrl): array
    {
        if (!$device->ip_address) {
            return ['success' => false, 'error' => 'no_ip'];
        }

        $baseUrl = "http://{$device->ip_address}";
        if ($device->port) {
            $baseUrl .= ":{$device->port}";
        }

        try {
            $response = Http::timeout(10)->post("{$baseUrl}/api/config/webhook", [
                'url' => $webhookUrl,
                'enabled' => true,
                'events' => ['record'], // события прохода
            ]);

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
