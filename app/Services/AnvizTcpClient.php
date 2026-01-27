<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * TCP клиент для прямого подключения к устройствам Anviz
 * Протокол: Anviz SDK на порту 5010
 *
 * @see https://github.com/murat11/phanviz
 * @see https://github.com/AnvizJacobs/AnvizCloudKit
 */
class AnvizTcpClient
{
    // Команды протокола Anviz
    const CMD_GET_DEVICE_INFO = 0x30;      // Получить информацию об устройстве
    const CMD_GET_DEVICE_SN = 0x32;        // Получить серийный номер
    const CMD_GET_DATETIME = 0x38;         // Получить время устройства
    const CMD_SET_DATETIME = 0x39;         // Установить время
    const CMD_GET_RECORD_INFO = 0x3C;      // Информация о записях
    const CMD_GET_ALL_RECORDS = 0x40;      // Все записи
    const CMD_GET_NEW_RECORDS = 0x42;      // Новые записи
    const CMD_CLEAR_NEW_RECORDS = 0x4E;    // Очистить флаг новых записей
    const CMD_GET_STAFF_INFO = 0x72;       // Информация о сотрудниках
    const CMD_GET_ALL_STAFF = 0x74;        // Все сотрудники
    const CMD_UPLOAD_STAFF = 0x76;         // Загрузить сотрудника на устройство
    const CMD_DELETE_STAFF = 0x4C;         // Удалить сотрудника с устройства
    const CMD_DELETE_ALL_STAFF = 0x4D;     // Удалить всех сотрудников

    const RESPONSE_SUCCESS = 0x00;
    const RESPONSE_FAIL = 0x01;

    protected string $ip;
    protected int $port;
    protected int $deviceCode;
    protected $socket = null;
    protected int $timeout = 5;

    public function __construct(string $ip, int $port = 5010, int $deviceCode = 1)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->deviceCode = $deviceCode;
    }

    /**
     * Подключиться к устройству
     */
    public function connect(): bool
    {
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            "tcp://{$this->ip}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );

        if (!$this->socket) {
            Log::error('AnvizTcpClient: Failed to connect', [
                'ip' => $this->ip,
                'port' => $this->port,
                'errno' => $errno,
                'error' => $errstr,
            ]);
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        Log::info('AnvizTcpClient: Connected', ['ip' => $this->ip, 'port' => $this->port]);
        return true;
    }

    /**
     * Отключиться
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Получить информацию об устройстве
     */
    public function getDeviceInfo(): ?array
    {
        $response = $this->sendCommand(self::CMD_GET_DEVICE_INFO);

        if (!$response) {
            return null;
        }

        // Парсим ответ (зависит от версии прошивки)
        return [
            'raw' => bin2hex($response),
            'connected' => true,
        ];
    }

    /**
     * Получить серийный номер устройства
     */
    public function getSerialNumber(): ?string
    {
        $response = $this->sendCommand(self::CMD_GET_DEVICE_SN);

        if (!$response || strlen($response) < 10) {
            return null;
        }

        // Серийный номер в ASCII после заголовка
        $data = substr($response, 10);
        return trim($data);
    }

    /**
     * Получить информацию о записях
     */
    public function getRecordInfo(): ?array
    {
        $response = $this->sendCommand(self::CMD_GET_RECORD_INFO);

        if (!$response || strlen($response) < 18) {
            return null;
        }

        $data = substr($response, 10);

        return [
            'user_count' => $this->bytesToInt(substr($data, 0, 3)),
            'fp_count' => $this->bytesToInt(substr($data, 3, 3)),
            'record_count' => $this->bytesToInt(substr($data, 6, 3)),
            'new_record_count' => isset($data[9]) ? $this->bytesToInt(substr($data, 9, 3)) : 0,
        ];
    }

    /**
     * Получить новые записи посещаемости
     */
    public function getNewRecords(): array
    {
        $records = [];

        // Запрашиваем информацию о количестве записей
        $info = $this->getRecordInfo();
        Log::info('AnvizTcpClient: Record info', $info ?? ['error' => 'no info']);

        // Запрашиваем новые записи
        $response = $this->sendCommand(self::CMD_GET_NEW_RECORDS, pack('C', 25)); // Запросить 25 записей

        if (!$response || strlen($response) < 12) {
            return $records;
        }

        // Парсим записи
        $count = ord($response[10]);
        $data = substr($response, 11);

        for ($i = 0; $i < $count && strlen($data) >= 14; $i++) {
            $record = substr($data, $i * 14, 14);
            $records[] = $this->parseRecord($record);
        }

        return $records;
    }

    /**
     * Получить все записи посещаемости
     */
    public function getAllRecords(int $limit = 100): array
    {
        $records = [];

        // Формируем запрос с начальной позиции
        $payload = pack('C', 0) . pack('C', $limit); // Позиция 0, количество записей

        $response = $this->sendCommand(self::CMD_GET_ALL_RECORDS, $payload);

        if (!$response || strlen($response) < 12) {
            return $records;
        }

        // Парсим записи
        $count = ord($response[10]);
        $data = substr($response, 11);

        for ($i = 0; $i < $count && strlen($data) >= 14; $i++) {
            $record = substr($data, $i * 14, 14);
            $records[] = $this->parseRecord($record);
        }

        return $records;
    }

    /**
     * Парсинг записи посещаемости
     * Формат: 5 байт User ID + 4 байта timestamp + 1 байт backup code + 1 байт type + 3 байта work code
     */
    protected function parseRecord(string $data): array
    {
        if (strlen($data) < 14) {
            return ['error' => 'invalid_record'];
        }

        // User ID (5 байт, big-endian)
        $userId = $this->bytesToInt(substr($data, 0, 5));

        // Timestamp (4 байта) - секунды с 2000-01-02 00:00:00
        // Устройство отправляет время в локальной таймзоне
        $timestamp = unpack('N', substr($data, 5, 4))[1];
        $dateTime = \Carbon\Carbon::create(2000, 1, 2, 0, 0, 0, 'Asia/Yekaterinburg')->addSeconds($timestamp);

        // Backup code (1 байт) - метод верификации
        $backupCode = ord($data[9]);

        // Record type (1 байт) - 0=in, 1=out
        $recordType = ord($data[10]);

        // Work code (3 байта)
        $workCode = $this->bytesToInt(substr($data, 11, 3));

        return [
            'user_id' => $userId,
            'datetime' => $dateTime->toDateTimeString(),
            'timestamp' => $dateTime->timestamp,
            'backup_code' => $backupCode,
            'type' => $recordType === 0 ? 'clock_in' : 'clock_out',
            'work_code' => $workCode,
            'method' => $this->getVerificationMethod($backupCode),
        ];
    }

    /**
     * Отправить команду устройству
     */
    protected function sendCommand(int $cmd, string $data = ''): ?string
    {
        if (!$this->socket) {
            if (!$this->connect()) {
                return null;
            }
        }

        $packet = $this->buildPacket($cmd, $data);

        Log::debug('AnvizTcpClient: Sending', ['cmd' => dechex($cmd), 'packet' => bin2hex($packet)]);

        $sent = @fwrite($this->socket, $packet);

        if ($sent === false) {
            Log::error('AnvizTcpClient: Send failed');
            return null;
        }

        // Читаем ответ
        $response = '';

        // Ждём данные с таймаутом
        while (!feof($this->socket)) {
            $chunk = @fread($this->socket, 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;

            // Проверяем метаданные стрима на таймаут
            $info = stream_get_meta_data($this->socket);
            if ($info['timed_out']) {
                break;
            }

            // Если получили меньше 1024 байт - значит это конец
            if (strlen($chunk) < 1024) {
                break;
            }
        }

        Log::debug('AnvizTcpClient: Response', ['length' => strlen($response), 'data' => bin2hex($response)]);

        return $response;
    }

    /**
     * Собрать пакет для отправки
     * Формат: STX(1) + CH(1) + DeviceCode(4) + CMD(1) + LEN(2) + DATA(N) + CRC(2)
     */
    protected function buildPacket(int $cmd, string $data = ''): string
    {
        $stx = 0xA5; // Start byte
        $ch = 0x00;  // Channel

        // Device code (4 bytes, little-endian)
        $deviceCode = pack('V', $this->deviceCode);

        // Data length (2 bytes, big-endian)
        $len = pack('n', strlen($data));

        // Формируем пакет без CRC
        $packet = chr($stx) . chr($ch) . $deviceCode . chr($cmd) . $len . $data;

        // Вычисляем CRC16 (CCITT)
        $crc = $this->crc16($packet);
        $packet .= pack('n', $crc);

        return $packet;
    }

    /**
     * CRC16-CCITT
     */
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

    /**
     * Конвертировать байты в int
     */
    protected function bytesToInt(string $bytes): int
    {
        $result = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $result = ($result << 8) | ord($bytes[$i]);
        }
        return $result;
    }

    /**
     * Получить метод верификации по коду
     */
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

    // ==================== USER MANAGEMENT ====================

    /**
     * Получить список всех сотрудников с устройства
     * Формат записи: 5 байт ID + 10 байт пароль + 10 байт карта + 40 байт имя + доп. данные
     */
    public function getAllUsers(): array
    {
        $users = [];

        // Запрашиваем информацию о количестве пользователей
        $info = $this->getRecordInfo();
        $userCount = $info['user_count'] ?? 0;

        if ($userCount === 0) {
            return $users;
        }

        // Запрашиваем пользователей порциями по 12
        $offset = 0;
        $batchSize = 12;

        while ($offset < $userCount) {
            $payload = pack('N', $offset) . chr(min($batchSize, $userCount - $offset));
            $response = $this->sendCommand(self::CMD_GET_ALL_STAFF, $payload);

            if (!$response || strlen($response) < 12) {
                break;
            }

            $count = ord($response[10]);
            $data = substr($response, 11);

            // Парсим пользователей
            $recordSize = 65; // Минимальный размер записи пользователя
            for ($i = 0; $i < $count && strlen($data) >= $recordSize; $i++) {
                $record = substr($data, 0, $recordSize);
                $data = substr($data, $recordSize);

                $user = $this->parseUserRecord($record);
                if ($user) {
                    $users[] = $user;
                }
            }

            $offset += $count;
            if ($count < $batchSize) {
                break;
            }
        }

        return $users;
    }

    /**
     * Парсинг записи пользователя
     */
    protected function parseUserRecord(string $data): ?array
    {
        if (strlen($data) < 65) {
            return null;
        }

        // User ID (5 байт)
        $userId = $this->bytesToInt(substr($data, 0, 5));

        // Password (10 байт, ASCII)
        $password = rtrim(substr($data, 5, 10), "\x00");

        // Card ID (10 байт)
        $cardId = rtrim(substr($data, 15, 10), "\x00");

        // Name (40 байт, может быть UTF-8 или GB2312)
        $nameRaw = substr($data, 25, 40);
        $name = rtrim($nameRaw, "\x00");
        // Пробуем декодировать как UTF-8, если не получается - как GB2312
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = @iconv('GB2312', 'UTF-8//IGNORE', $name);
        }
        $name = trim($name);

        return [
            'user_id' => $userId,
            'password' => $password,
            'card_id' => $cardId,
            'name' => $name,
            'has_biometric' => strlen($data) > 65, // Если есть доп. данные - есть биометрия
        ];
    }

    /**
     * Добавить пользователя на устройство
     *
     * @param int $userId ID пользователя (1-65535)
     * @param string $name Имя (до 20 символов)
     * @param string $password Пароль (до 8 цифр)
     * @param string $cardId ID карты (до 10 символов)
     */
    public function addUser(int $userId, string $name, string $password = '', string $cardId = ''): bool
    {
        // User ID (5 байт, big-endian)
        $data = str_pad(pack('N', $userId), 5, "\x00", STR_PAD_LEFT);

        // Password (10 байт)
        $data .= str_pad(substr($password, 0, 10), 10, "\x00");

        // Card ID (10 байт)
        $data .= str_pad(substr($cardId, 0, 10), 10, "\x00");

        // Name (40 байт) - конвертируем в UTF-8
        $nameUtf8 = mb_substr($name, 0, 20, 'UTF-8');
        $data .= str_pad($nameUtf8, 40, "\x00");

        // Department (1 байт)
        $data .= chr(0);

        // Group (1 байт)
        $data .= chr(0);

        // Attendance mode (1 байт)
        $data .= chr(0);

        // Extended password flag (1 байт)
        $data .= chr(0);

        // Keep (4 байта)
        $data .= str_repeat("\x00", 4);

        // Special info (20 байт)
        $data .= str_repeat("\x00", 20);

        // Количество записей (1 запись)
        $payload = chr(1) . $data;

        $response = $this->sendCommand(self::CMD_UPLOAD_STAFF, $payload);

        if (!$response) {
            return false;
        }

        // Проверяем статус ответа
        $status = ord($response[9] ?? chr(1));
        return $status === self::RESPONSE_SUCCESS;
    }

    /**
     * Удалить пользователя с устройства
     */
    public function deleteUser(int $userId): bool
    {
        // User ID (5 байт, big-endian)
        $data = chr(1); // Количество пользователей для удаления
        $data .= str_pad(pack('N', $userId), 5, "\x00", STR_PAD_LEFT);

        $response = $this->sendCommand(self::CMD_DELETE_STAFF, $data);

        if (!$response) {
            return false;
        }

        $status = ord($response[9] ?? chr(1));
        return $status === self::RESPONSE_SUCCESS;
    }

    /**
     * Получить информацию о конкретном пользователе
     */
    public function getUser(int $userId): ?array
    {
        // Ищем пользователя в списке
        $users = $this->getAllUsers();
        foreach ($users as $user) {
            if ($user['user_id'] === $userId) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Проверить существует ли пользователь на устройстве
     */
    public function userExists(int $userId): bool
    {
        return $this->getUser($userId) !== null;
    }

    /**
     * Тест подключения
     */
    public function testConnection(): array
    {
        try {
            if (!$this->connect()) {
                return ['success' => false, 'error' => 'connection_failed'];
            }

            $info = $this->getRecordInfo();
            $this->disconnect();

            if ($info) {
                return [
                    'success' => true,
                    'device_info' => $info,
                ];
            }

            return ['success' => true, 'message' => 'Connected but no info available'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
