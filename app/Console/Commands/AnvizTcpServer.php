<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnvizTcpServer extends Command
{
    protected $signature = 'anviz:server {--port=5010 : Порт для прослушивания}';
    protected $description = 'TCP сервер для приёма данных от Anviz устройств (режим Client)';

    protected $socket;
    protected $clients = [];
    protected AttendanceService $attendanceService;

    public function handle(AttendanceService $attendanceService): int
    {
        $this->attendanceService = $attendanceService;
        $port = $this->option('port');

        $this->info("Запуск Anviz TCP сервера на порту {$port}...");

        // Создаём сокет
        $this->socket = stream_socket_server(
            "tcp://0.0.0.0:{$port}",
            $errno,
            $errstr
        );

        if (!$this->socket) {
            $this->error("Не удалось создать сервер: {$errstr} ({$errno})");
            return 1;
        }

        stream_set_blocking($this->socket, false);

        $this->info("Сервер запущен. Ожидание подключений...");
        $this->info("Нажмите Ctrl+C для остановки.");

        while (true) {
            // Проверяем новые подключения
            $newClient = @stream_socket_accept($this->socket, 0);
            if ($newClient) {
                $clientName = stream_socket_get_name($newClient, true);
                $this->info("[{$this->timestamp()}] Новое подключение: {$clientName}");
                stream_set_blocking($newClient, false);
                $this->clients[] = $newClient;
            }

            // Обрабатываем данные от клиентов
            foreach ($this->clients as $key => $client) {
                $data = @fread($client, 4096);

                if ($data === false || $data === '') {
                    // Проверяем, закрыто ли соединение
                    if (feof($client)) {
                        $this->warn("[{$this->timestamp()}] Клиент отключился");
                        fclose($client);
                        unset($this->clients[$key]);
                    }
                    continue;
                }

                $this->processData($client, $data);
            }

            // Небольшая пауза чтобы не грузить CPU
            usleep(50000); // 50ms
        }

        return 0;
    }

    protected function processData($client, string $data): void
    {
        $hex = bin2hex($data);
        $clientName = stream_socket_get_name($client, true);

        $this->line("[{$this->timestamp()}] Получено от {$clientName}: {$hex}");
        Log::info('Anviz TCP received', ['client' => $clientName, 'hex' => $hex, 'length' => strlen($data)]);

        // Проверяем что это Anviz пакет (начинается с 0xA5)
        if (strlen($data) < 10 || ord($data[0]) !== 0xA5) {
            $this->warn("  Неизвестный формат пакета");
            return;
        }

        // Парсим заголовок пакета
        // Формат Anviz: STX(1) + DeviceCode(4) + CMD(1) + Reserved(2) + [LEN(1) + DATA(N)] + CRC(2)
        $deviceCode = unpack('N', substr($data, 1, 4))[1]; // Big-endian (network order)
        $cmd = ord($data[5]);

        // Определяем длину данных в зависимости от команды
        // Команды 0xDF (realtime), 0x40 (records), 0x7E имеют байт длины на позиции 8
        $dataLen = 0;
        $payload = '';

        if ($cmd == 0xDF || $cmd == 0x40 || $cmd == 0x42 || $cmd == 0x7E) {
            if (strlen($data) >= 9) {
                $dataLen = ord($data[8]);
                $payload = substr($data, 9, $dataLen);
            }
        }

        $this->info("  Device Code: {$deviceCode}, CMD: 0x" . dechex($cmd) . ", Data Len: {$dataLen}");

        // Находим устройство по device_code
        $device = AttendanceDevice::where('type', 'anviz')
            ->whereJsonContains('settings->device_code', $deviceCode)
            ->orWhere(function($q) use ($deviceCode) {
                $q->where('type', 'anviz')
                  ->whereRaw("JSON_EXTRACT(settings, '$.device_code') = ?", [$deviceCode]);
            })
            ->first();

        if (!$device) {
            // Попробуем найти по IP
            $clientIp = explode(':', $clientName)[0];
            $device = AttendanceDevice::where('type', 'anviz')
                ->where('ip_address', $clientIp)
                ->first();
        }

        if ($device) {
            $this->info("  Устройство: {$device->name}");
            $device->markHeartbeat();
        } else {
            $this->warn("  Устройство не найдено (code: {$deviceCode})");
        }

        // Обрабатываем команду
        $response = $this->handleCommand($device, $cmd, $payload, $deviceCode);

        if ($response) {
            fwrite($client, $response);
            $this->info("  Отправлен ответ: " . bin2hex($response));
        }
    }

    protected function handleCommand(?AttendanceDevice $device, int $cmd, string $payload, int $deviceCode): ?string
    {
        switch ($cmd) {
            case 0xDF: // Real-time record (CMD_RECORD_REALTIME)
            case 0x7E: // Real-time record push (событие в реальном времени)
                return $this->handleRealtimeRecord($device, $payload, $deviceCode, $cmd);

            case 0x40: // Upload records (загрузка записей)
            case 0x42: // New records
                return $this->handleRecords($device, $payload, $deviceCode);

            case 0x00: // Heartbeat / Connection test
            case 0x01: // Device connect
            case 0x7F: // Heartbeat (0x7F)
                $this->info("  Heartbeat/Connect получен");
                return $this->buildResponse($deviceCode, $cmd, chr(0x00)); // ACK

            default:
                $this->info("  Команда 0x" . dechex($cmd) . " - пропущена");
                return $this->buildResponse($deviceCode, $cmd, chr(0x00)); // ACK
        }
    }

    protected function handleRealtimeRecord(?AttendanceDevice $device, string $payload, int $deviceCode, int $cmd = 0xDF): ?string
    {
        $this->info("  === REAL-TIME RECORD ===");

        if (strlen($payload) < 14) {
            $this->warn("  Payload слишком короткий: " . strlen($payload) . " байт");
            $this->warn("  Payload hex: " . bin2hex($payload));
            return $this->buildResponse($deviceCode, $cmd, chr(0x00));
        }

        // Парсим запись
        // User ID (5 байт) + Timestamp (4 байта) + Backup code (1) + Type (1) + Work code (3)
        $userId = $this->bytesToInt(substr($payload, 0, 5));
        $timestamp = unpack('N', substr($payload, 5, 4))[1];
        // Anviz timestamp = секунды с 2000-01-02 00:00:00 в локальном времени устройства
        // Сохраняем как есть - устройство уже отправляет правильное локальное время
        $dateTime = Carbon::create(2000, 1, 2, 0, 0, 0)->addSeconds($timestamp);
        $backupCode = ord($payload[9]);
        $recordType = ord($payload[10]) & 0x0F; // Берём только нижние 4 бита (без real-time флага)

        $eventType = $recordType === 0 ? 'clock_in' : 'clock_out';
        $method = $this->getVerificationMethod($backupCode);

        $this->info("  User ID: {$userId}");
        $this->info("  Time: {$dateTime}");
        $this->info("  Type: {$eventType}");
        $this->info("  Method: {$method}");

        if ($device) {
            $this->saveAttendanceRecord($device, $userId, $dateTime, $eventType, $method);
        } else {
            $this->warn("  Устройство не найдено - запись не сохранена!");
        }

        return $this->buildResponse($deviceCode, $cmd, chr(0x00)); // ACK
    }

    protected function handleRecords(?AttendanceDevice $device, string $payload, int $deviceCode): ?string
    {
        if (strlen($payload) < 1) {
            return $this->buildResponse($deviceCode, 0x40, chr(0x00));
        }

        $count = ord($payload[0]);
        $this->info("  Получено записей: {$count}");

        $data = substr($payload, 1);
        $recordSize = 14;

        for ($i = 0; $i < $count && strlen($data) >= $recordSize; $i++) {
            $record = substr($data, $i * $recordSize, $recordSize);

            $userId = $this->bytesToInt(substr($record, 0, 5));
            $timestamp = unpack('N', substr($record, 5, 4))[1];
            $dateTime = Carbon::create(2000, 1, 2, 0, 0, 0)->addSeconds($timestamp);
            $backupCode = ord($record[9]);
            $recordType = ord($record[10]);

            $eventType = $recordType === 0 ? 'clock_in' : 'clock_out';
            $method = $this->getVerificationMethod($backupCode);

            $this->info("    [{$i}] User {$userId} at {$dateTime} ({$eventType})");

            if ($device) {
                $this->saveAttendanceRecord($device, $userId, $dateTime, $eventType, $method);
            }
        }

        return $this->buildResponse($deviceCode, 0x40, chr(0x00));
    }

    protected function saveAttendanceRecord(AttendanceDevice $device, int $deviceUserId, Carbon $eventTime, string $eventType, string $method): bool
    {
        try {
            $result = $this->attendanceService->processDeviceEvent(
                device: $device,
                eventType: $eventType,
                deviceUserId: (string) $deviceUserId,
                eventTime: $eventTime,
                rawData: [
                    'device_user_id' => $deviceUserId,
                    'method' => $method,
                ]
            );

            if ($result['success']) {
                $this->info("    ✓ Событие обработано: " . ($result['message'] ?? 'OK'));

                // Обновляем face_status если нужно
                if ($method === 'face') {
                    $pivot = DB::table('attendance_device_users')
                        ->where('device_id', $device->id)
                        ->where('device_user_id', (string) $deviceUserId)
                        ->first();

                    if ($pivot && $pivot->face_status !== 'enrolled') {
                        DB::table('attendance_device_users')
                            ->where('id', $pivot->id)
                            ->update([
                                'is_synced' => true,
                                'face_status' => 'enrolled',
                                'face_enrolled_at' => now(),
                                'sync_error' => null,
                            ]);
                        $this->info("    ✓ Face ID статус обновлён на 'enrolled'");
                    }
                }
                return true;
            } else {
                $this->warn("    × Ошибка: " . ($result['message'] ?? $result['error'] ?? 'unknown'));
                return false;
            }
        } catch (\Exception $e) {
            $this->error("    × Exception: " . $e->getMessage());
            Log::error('Anviz saveAttendanceRecord error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function buildResponse(int $deviceCode, int $cmd, string $data): string
    {
        // Формат ответа Anviz: STX(1) + DeviceCode(4) + CMD(1) + RET(1) + LEN(2) + DATA(N) + CRC(2)
        $packet = chr(0xA5);                          // STX
        $packet .= pack('N', $deviceCode);            // Device code (4 bytes, big-endian/network order)
        $packet .= chr($cmd | 0x80);                  // Command with ACK flag
        $packet .= chr(0x00);                         // Return code (success)
        $packet .= pack('n', strlen($data));          // Data length (2 bytes, big-endian)
        $packet .= $data;

        // Calculate CRC16
        $crc = $this->crc16($packet);
        $packet .= pack('n', $crc);

        return $packet;
    }

    protected function crc16(string $data): int
    {
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 1) {
                    $crc = ($crc >> 1) ^ 0x8408;
                } else {
                    $crc >>= 1;
                }
            }
        }

        return $crc ^ 0xFFFF;
    }

    protected function bytesToInt(string $bytes): int
    {
        $result = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $result = ($result << 8) | ord($bytes[$i]);
        }
        return $result;
    }

    protected function getVerificationMethod(int $code): string
    {
        return match($code) {
            0 => 'password',
            1 => 'fingerprint',
            2 => 'card',
            15, 16, 17, 18 => 'face',
            default => 'face',
        };
    }

    protected function timestamp(): string
    {
        return now()->format('H:i:s');
    }

    public function __destruct()
    {
        if ($this->socket) {
            fclose($this->socket);
        }
        foreach ($this->clients as $client) {
            fclose($client);
        }
    }
}
