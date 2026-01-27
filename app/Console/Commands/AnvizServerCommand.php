<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnvizServerCommand extends Command
{
    protected $signature = 'anviz:server {--port=5010 : TCP port to listen on}';
    protected $description = 'Start TCP server to receive events from Anviz devices in Client mode';

    // Anviz protocol commands
    const CMD_CONNECT = 0x01;           // Device connection
    const CMD_HEARTBEAT = 0x7F;         // Heartbeat / keep-alive
    const CMD_RECORD_REALTIME = 0xDF;   // Real-time attendance record
    const CMD_RECORD_UPLOAD = 0x40;     // Record upload
    const CMD_GET_DEVICE_INFO = 0x30;
    const CMD_GET_RECORD_INFO = 0x3C;

    protected $server;
    protected $clients = [];
    protected AttendanceService $attendanceService;

    public function handle(AttendanceService $attendanceService): int
    {
        $this->attendanceService = $attendanceService;
        $port = $this->option('port');

        $this->info("Starting Anviz TCP Server on port {$port}...");

        // Создаём TCP сервер
        $this->server = stream_socket_server(
            "tcp://0.0.0.0:{$port}",
            $errno,
            $errstr
        );

        if (!$this->server) {
            $this->error("Failed to start server: {$errstr} ({$errno})");
            return 1;
        }

        stream_set_blocking($this->server, false);

        $this->info("✓ Server listening on 0.0.0.0:{$port}");
        $this->info("Waiting for Anviz devices to connect...");
        $this->newLine();

        // Основной цикл
        while (true) {
            $this->tick();
            usleep(100000); // 100ms
        }

        return 0;
    }

    protected function tick(): void
    {
        // Принимаем новые подключения
        $client = @stream_socket_accept($this->server, 0);
        if ($client) {
            $peername = stream_socket_get_name($client, true);
            $this->info("[{$this->timestamp()}] New connection from {$peername}");
            stream_set_blocking($client, false);
            $this->clients[] = [
                'socket' => $client,
                'address' => $peername,
                'buffer' => '',
                'device_id' => null,
                'last_activity' => time(),
            ];
        }

        // Обрабатываем данные от клиентов
        foreach ($this->clients as $key => &$clientData) {
            $data = @fread($clientData['socket'], 4096);

            if ($data === false || (strlen($data) === 0 && feof($clientData['socket']))) {
                // Клиент отключился
                $this->warn("[{$this->timestamp()}] Client {$clientData['address']} disconnected");
                fclose($clientData['socket']);
                unset($this->clients[$key]);
                continue;
            }

            if (strlen($data) > 0) {
                $clientData['buffer'] .= $data;
                $clientData['last_activity'] = time();

                $this->line("[{$this->timestamp()}] Received " . strlen($data) . " bytes from {$clientData['address']}");
                $this->line("  Data: " . bin2hex($data));

                // Обрабатываем пакеты
                $this->processBuffer($clientData);
            }

            // Таймаут неактивных клиентов (5 минут)
            if (time() - $clientData['last_activity'] > 300) {
                $this->warn("[{$this->timestamp()}] Client {$clientData['address']} timed out");
                fclose($clientData['socket']);
                unset($this->clients[$key]);
            }
        }

        $this->clients = array_values($this->clients); // Перенумеровываем
    }

    protected function processBuffer(array &$clientData): void
    {
        $buffer = $clientData['buffer'];

        // Ищем начало пакета Anviz (0xA5)
        while (strlen($buffer) > 0) {
            // Находим стартовый байт
            $start = strpos($buffer, chr(0xA5));
            if ($start === false) {
                $buffer = '';
                break;
            }

            if ($start > 0) {
                $buffer = substr($buffer, $start);
            }

            // Минимальная длина пакета: STX(1) + DeviceCode(4) + CMD(1) + Reserved(2) + CRC(2) = 10
            if (strlen($buffer) < 10) {
                break;
            }

            // Парсим заголовок
            $deviceCode = unpack('N', substr($buffer, 1, 4))[1]; // Big-endian 4 bytes (network order)
            $cmd = ord($buffer[5]);

            // Определяем формат по команде
            // Heartbeat (0x7F): STX(1) + Device(4) + CMD(1) + Reserved(2) + CRC(2) = 10 bytes, no data
            // Record (0xDF): STX(1) + Device(4) + CMD(1) + Reserved(2) + LEN(1) + DATA(N) + CRC(2)

            $len = 0;
            $headerLen = 8; // По умолчанию без LEN байта

            if ($cmd == self::CMD_RECORD_REALTIME || $cmd == self::CMD_RECORD_UPLOAD) {
                // Команды с данными имеют LEN байт
                if (strlen($buffer) < 11) {
                    break;
                }
                $len = ord($buffer[8]);
                $headerLen = 9;
            }

            $totalLen = $headerLen + $len + 2; // Header + Data + CRC(2)

            if (strlen($buffer) < $totalLen) {
                break;
            }

            // Извлекаем пакет
            $packet = substr($buffer, 0, $totalLen);
            $buffer = substr($buffer, $totalLen);

            $data = ($len > 0) ? substr($packet, $headerLen, $len) : '';

            $this->info("[{$this->timestamp()}] Packet: CMD=0x" . dechex($cmd) . ", Device={$deviceCode}, Len={$len}");
            if ($len > 0) {
                $this->line("  RawData: " . bin2hex($data));
            }

            // Обрабатываем команду
            $this->handleCommand($clientData, $deviceCode, $cmd, 0, 0, $data);
        }

        $clientData['buffer'] = $buffer;
    }

    protected function handleCommand(array &$clientData, int $deviceCode, int $cmd, int $ack, int $ret, string $data): void
    {
        switch ($cmd) {
            case self::CMD_CONNECT:
            case 0x00:
                $this->handleConnect($clientData, $deviceCode, $data);
                break;

            case self::CMD_HEARTBEAT:
                $this->handleHeartbeat($clientData, $deviceCode);
                break;

            case self::CMD_RECORD_REALTIME:
            case self::CMD_RECORD_UPLOAD:
                $this->handleRealtimeRecord($clientData, $deviceCode, $data);
                break;

            default:
                $this->line("  Unknown command: 0x" . dechex($cmd));
                if (strlen($data) > 0) {
                    $this->line("  Data: " . bin2hex($data));
                }
                // Отправляем ACK на неизвестные команды тоже
                $this->sendAck($clientData['socket'], $deviceCode, $cmd);
        }
    }

    protected function handleHeartbeat(array &$clientData, int $deviceCode): void
    {
        $this->line("  → Heartbeat from device {$deviceCode}");

        // Обновляем heartbeat в БД
        $device = AttendanceDevice::where('type', 'anviz')
            ->where(function($q) use ($deviceCode) {
                $q->where('serial_number', (string)$deviceCode)
                  ->orWhere('settings->device_code', $deviceCode);
            })
            ->first();

        if ($device) {
            $device->markHeartbeat();
        }

        // Отправляем ACK
        $this->sendAck($clientData['socket'], $deviceCode, self::CMD_HEARTBEAT);
    }

    protected function handleConnect(array &$clientData, int $deviceCode, string $data): void
    {
        $this->info("  → Device connected: code={$deviceCode}");

        // Сохраняем device code
        $clientData['device_id'] = $deviceCode;

        // Ищем устройство в БД по серийному номеру или device code
        $device = AttendanceDevice::where('type', 'anviz')
            ->where(function($q) use ($deviceCode) {
                $q->where('serial_number', (string)$deviceCode)
                  ->orWhere('settings->device_code', $deviceCode);
            })
            ->first();

        if ($device) {
            $device->markHeartbeat();
            $this->info("  → Matched device: {$device->name} (ID: {$device->id})");
        } else {
            $this->warn("  → Unknown device code: {$deviceCode}");
        }

        // Отправляем ACK
        $this->sendAck($clientData['socket'], $deviceCode, 0x00);
    }

    protected function handleRealtimeRecord(array &$clientData, int $deviceCode, string $data): void
    {
        $this->info("  → Real-time attendance record!");

        if (strlen($data) < 14) {
            $this->warn("  → Invalid record data length: " . strlen($data));
            return;
        }

        // Парсим запись
        // User ID (5 байт, big-endian)
        $userId = 0;
        for ($i = 0; $i < 5; $i++) {
            $userId = ($userId << 8) | ord($data[$i]);
        }

        // Timestamp (4 байта) - секунды с 2000-01-02 00:00:00
        // Устройство отправляет время в локальной таймзоне
        $timestamp = unpack('N', substr($data, 5, 4))[1];
        $dateTime = Carbon::create(2000, 1, 2, 0, 0, 0, 'Asia/Yekaterinburg')->addSeconds($timestamp);

        // Backup code (1 байт) - метод верификации
        $backupCode = ord($data[9]);

        // Record type (1 байт)
        // Бит 7 (0x80) = real-time флаг, биты 0-3 = тип записи (0=IN, 1=OUT)
        $statusByte = ord($data[10]);
        $recordType = $statusByte & 0x0F; // Берём только нижние 4 бита

        // Work code (3 байта)
        $workCode = 0;
        for ($i = 11; $i < 14 && $i < strlen($data); $i++) {
            $workCode = ($workCode << 8) | ord($data[$i]);
        }

        $eventType = ($recordType == 0) ? 'clock_in' : 'clock_out';
        $method = $this->getVerificationMethod($backupCode);

        $this->info("  → User ID: {$userId}");
        $this->info("  → Time: {$dateTime->toDateTimeString()}");
        $this->info("  → Type: {$eventType}");
        $this->info("  → Method: {$method}");

        // Находим устройство
        $device = AttendanceDevice::where('type', 'anviz')
            ->where(function($q) use ($deviceCode) {
                $q->where('serial_number', (string)$deviceCode)
                  ->orWhere('settings->device_code', $deviceCode);
            })
            ->first();

        if (!$device) {
            $this->error("  → Device not found in database!");
            $this->sendAck($clientData['socket'], $deviceCode, self::CMD_RECORD_REALTIME);
            return;
        }

        // Обрабатываем событие
        try {
            $result = $this->attendanceService->processDeviceEvent(
                device: $device,
                eventType: $eventType,
                deviceUserId: (string)$userId,
                eventTime: $dateTime,
                rawData: [
                    'device_code' => $deviceCode,
                    'backup_code' => $backupCode,
                    'work_code' => $workCode,
                    'method' => $method,
                ]
            );

            if ($result['success']) {
                $this->info("  ✓ Event processed successfully!");
            } else {
                $this->warn("  × Event processing failed: " . ($result['message'] ?? $result['error'] ?? 'unknown'));
            }
        } catch (\Exception $e) {
            $this->error("  × Exception: " . $e->getMessage());
            Log::error('Anviz event processing error', ['error' => $e->getMessage()]);
        }

        // Отправляем ACK
        $this->sendAck($clientData['socket'], $deviceCode, self::CMD_RECORD_REALTIME);
    }

    protected function sendAck($socket, int $deviceCode, int $cmd): void
    {
        // Формируем ACK пакет (упрощённый формат)
        // STX(1) + DeviceCode(4) + CMD(1) + RET(1) + LEN(2) + CRC(2)
        $packet = chr(0xA5);                          // STX
        $packet .= pack('N', $deviceCode);            // Device code (big-endian, network order)
        $packet .= chr($cmd | 0x80);                  // Command with ACK flag
        $packet .= chr(0x00);                         // Return code (success)
        $packet .= pack('n', 0);                      // Data length = 0

        // CRC16
        $crc = $this->crc16($packet);
        $packet .= pack('n', $crc);

        $this->line("  ← Sending ACK: " . bin2hex($packet));
        @fwrite($socket, $packet);
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

    protected function getVerificationMethod(int $code): string
    {
        return match($code) {
            0 => 'password',
            1 => 'fingerprint',
            2 => 'card',
            3 => 'password+fingerprint',
            4 => 'password+card',
            5 => 'fingerprint+card',
            6 => 'all',
            15, 16, 17, 18 => 'face',
            default => 'unknown',
        };
    }

    protected function timestamp(): string
    {
        return now()->format('H:i:s');
    }
}
