<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Printer extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'name',
        'type',
        'kitchen_station_id',
        'connection_type',
        'ip_address',
        'port',
        'device_path',
        'paper_width',
        'chars_per_line',
        'encoding',
        'cut_paper',
        'open_drawer',
        'print_logo',
        'print_qr',
        'is_active',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'cut_paper' => 'boolean',
        'open_drawer' => 'boolean',
        'print_logo' => 'boolean',
        'print_qr' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    protected $appends = ['type_label', 'connection_label', 'status'];

    const TYPE_RECEIPT = 'receipt';
    const TYPE_KITCHEN = 'kitchen';
    const TYPE_BAR = 'bar';
    const TYPE_LABEL = 'label';

    const CONN_NETWORK = 'network';
    const CONN_USB = 'usb';
    const CONN_BLUETOOTH = 'bluetooth';
    const CONN_FILE = 'file';

    // Relationships
    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function kitchenStation()
    {
        return $this->belongsTo(KitchenStation::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'receipt' => 'Касса',
            'kitchen' => 'Кухня',
            'bar' => 'Бар',
            'label' => 'Этикетки',
        ][$this->type] ?? $this->type;
    }

    public function getConnectionLabelAttribute()
    {
        return [
            'network' => 'Сеть',
            'usb' => 'USB',
            'bluetooth' => 'Bluetooth',
            'file' => 'Файл',
        ][$this->connection_type] ?? $this->connection_type;
    }

    public function getStatusAttribute()
    {
        // В реальном приложении здесь проверка соединения
        return $this->is_active ? 'online' : 'offline';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Methods
    public static function getDefault($type = 'receipt', $restaurantId = 1)
    {
        // Сначала ищем принтер по умолчанию
        $printer = self::where('restaurant_id', $restaurantId)
            ->where('type', $type)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        // Если не нашли - берём любой активный принтер нужного типа
        if (!$printer) {
            $printer = self::where('restaurant_id', $restaurantId)
                ->where('type', $type)
                ->where('is_active', true)
                ->first();
        }

        return $printer;
    }

    public static function getKitchenPrinters($restaurantId = 1, $stationId = null)
    {
        $query = self::where('restaurant_id', $restaurantId)
            ->where('type', 'kitchen')
            ->where('is_active', true);

        if ($stationId) {
            $query->where('kitchen_station_id', $stationId);
        }

        return $query->get();
    }

    /**
     * Получить принтеры для бара
     */
    public static function getBarPrinters($restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('type', 'bar')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Получить принтер по цеху
     */
    public static function getByStation($stationId, $restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('kitchen_station_id', $stationId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Отправка данных на принтер
     */
    public function send(string $data): array
    {
        // Декодируем base64
        $rawData = base64_decode($data);

        Log::info('Printer send', [
            'printer_id' => $this->id,
            'connection' => $this->connection_type,
            'connection_type' => gettype($this->connection_type),
            'device_path' => $this->device_path,
            'data_length' => strlen($rawData)
        ]);

        switch ($this->connection_type) {
            case 'network':
                return $this->sendToNetwork($rawData);
            case 'usb':
                return $this->sendToUsb($rawData);
            case 'file':
                return $this->sendToFile($rawData);
            default:
                return [
                    'success' => false,
                    'message' => 'Неподдерживаемый тип подключения: ' . var_export($this->connection_type, true)
                ];
        }
    }

    private function sendToNetwork(string $data): array
    {
        try {
            $socket = @fsockopen($this->ip_address, $this->port, $errno, $errstr, 5);
            
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Ошибка подключения: $errstr ($errno)",
                ];
            }

            fwrite($socket, $data);
            fclose($socket);

            return ['success' => true, 'message' => 'Отправлено'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendToUsb(string $data): array
    {
        try {
            $path = $this->device_path;

            if (!$path) {
                return ['success' => false, 'message' => 'Не указан путь к устройству'];
            }

            // Определяем ОС
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            if ($isWindows) {
                return $this->sendToWindowsPrinter($data, $path);
            }

            // Linux: /dev/usb/lp0, /dev/ttyUSB0, etc
            $fp = @fopen($path, 'w');

            if (!$fp) {
                return ['success' => false, 'message' => 'Не удалось открыть устройство'];
            }

            fwrite($fp, $data);
            fclose($fp);

            return ['success' => true, 'message' => 'Отправлено'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Отправка на Windows принтер
     */
    /**
     * Валидация имени принтера (защита от command injection)
     */
    public static function isValidPrinterPath(string $path): bool
    {
        // Базовая валидация символов
        if (!preg_match('/^[a-zA-Z0-9_\-\.\\\\\/:() ]+$/', $path)) {
            return false;
        }

        // Защита от SSRF через UNC paths к нелокальным хостам
        // Разрешаем только \\localhost и \\127.0.0.1
        if (preg_match('/^\\\\\\\\(.+?)\\\\/', $path, $matches)) {
            $host = strtolower($matches[1]);
            $allowedHosts = ['localhost', '127.0.0.1'];
            if (!in_array($host, $allowedHosts)) {
                return false;
            }
        }

        return true;
    }

    private function sendToWindowsPrinter(string $data, string $printerPath): array
    {
        $debugLog = [];

        // Валидация имени принтера
        if (!self::isValidPrinterPath($printerPath)) {
            Log::warning('Printer: invalid printer path rejected', ['path' => $printerPath]);
            return [
                'success' => false,
                'message' => 'Недопустимые символы в имени принтера',
            ];
        }

        try {
            // Создаём временный файл с данными для печати
            $tempFile = storage_path('app/print_temp_' . uniqid() . '.bin');
            file_put_contents($tempFile, $data);
            $debugLog[] = "Temp file: $tempFile (" . strlen($data) . " bytes)";

            // Метод 1: Если это COM порт (COM1, COM2, etc)
            if (preg_match('/^COM\d+$/i', $printerPath)) {
                $debugLog[] = "Trying COM port: $printerPath";
                $fp = @fopen($printerPath, 'w');
                if ($fp) {
                    fwrite($fp, $data);
                    fclose($fp);
                    @unlink($tempFile);
                    return ['success' => true, 'message' => 'Отправлено на ' . $printerPath];
                }
                $debugLog[] = "COM port failed";
            }

            // Метод 2: Прямая запись через UNC путь к расшаренному принтеру
            $printerName = $printerPath;

            // Формируем разные варианты путей
            $pathsToTry = [];

            // Если путь уже содержит слеши - используем как есть
            if (str_contains($printerPath, '\\') || str_contains($printerPath, '/')) {
                $pathsToTry[] = $printerPath;
            } else {
                // Пробуем разные форматы UNC пути
                $pathsToTry[] = '\\\\localhost\\' . $printerPath;
                $pathsToTry[] = '\\\\127.0.0.1\\' . $printerPath;
                $pathsToTry[] = '\\\\' . gethostname() . '\\' . $printerPath;
            }

            foreach ($pathsToTry as $uncPath) {
                $debugLog[] = "Trying fopen to: $uncPath";

                // Пробуем открыть как файл
                $fp = @fopen($uncPath, 'wb');
                if ($fp) {
                    $written = fwrite($fp, $data);
                    fclose($fp);
                    @unlink($tempFile);
                    $debugLog[] = "Success via fopen! Written: $written bytes";
                    Log::info('Windows printer success via fopen', ['path' => $uncPath, 'bytes' => $written]);
                    return ['success' => true, 'message' => 'Отправлено на ' . $printerPath];
                }

                $error = error_get_last();
                $debugLog[] = "fopen failed: " . ($error['message'] ?? 'unknown error');
            }

            // Метод 3: Через copy /b на Windows принтер
            foreach ($pathsToTry as $uncPath) {
                $debugLog[] = "Trying copy /b to: $uncPath";

                $escapedPrinter = escapeshellarg($uncPath);
                $escapedFile = escapeshellarg($tempFile);

                $command = "copy /b {$escapedFile} {$escapedPrinter} 2>&1";
                $debugLog[] = "Command: $command";

                $output = [];
                $returnCode = -1;
                exec($command, $output, $returnCode);
                $outputStr = implode("\n", $output);
                $debugLog[] = "Result code: $returnCode, output: $outputStr";

                if ($returnCode === 0 || str_contains(mb_strtolower($outputStr), 'скопировано') || str_contains(strtolower($outputStr), 'copied')) {
                    @unlink($tempFile);
                    Log::info('Windows printer success via copy', ['path' => $uncPath]);
                    return ['success' => true, 'message' => 'Отправлено на ' . $printerPath];
                }
            }

            // Метод 4: Через PowerShell RAW printing через Windows Spooler API
            $debugLog[] = "Trying PowerShell RAW print for: $printerName";

            // Используем постоянный скрипт для RAW печати
            $psScriptFile = storage_path('app/print_raw.ps1');

            if (!file_exists($psScriptFile)) {
                $debugLog[] = "ERROR: PowerShell script not found at: $psScriptFile";
                @unlink($tempFile);
                return [
                    'success' => false,
                    'message' => 'Скрипт печати не найден. Обратитесь к администратору.',
                    'debug' => implode("\n", $debugLog)
                ];
            }

            $psCommand = 'powershell -ExecutionPolicy Bypass -File ' . escapeshellarg($psScriptFile) . ' -PrinterName ' . escapeshellarg($printerName) . ' -FilePath ' . escapeshellarg($tempFile) . ' 2>&1';
            $debugLog[] = "PS Command: $psCommand";

            $psOutput = shell_exec($psCommand);
            $debugLog[] = "PS Output: " . ($psOutput ?? 'null');

            @unlink($tempFile);

            if ($psOutput && (str_contains($psOutput, 'OK_RAW') || str_contains($psOutput, 'OK_PORT') || str_contains($psOutput, 'OK_SPOOLER'))) {
                Log::info('Windows printer success via PowerShell RAW', ['printer' => $printerName, 'output' => $psOutput]);
                return ['success' => true, 'message' => 'Напечатано через Windows RAW'];
            }

            // Логируем отладку
            Log::warning('Windows printer failed', ['debug' => $debugLog]);

            return [
                'success' => false,
                'message' => 'Не удалось отправить на принтер. Проверьте настройки общего доступа.',
                'debug' => implode("\n", $debugLog)
            ];

        } catch (\Exception $e) {
            @unlink($tempFile ?? '');
            Log::error('Windows printer exception', ['error' => $e->getMessage(), 'debug' => $debugLog]);
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage(), 'debug' => implode("\n", $debugLog)];
        }
    }

    private function sendToFile(string $data): array
    {
        try {
            $filename = storage_path('app/print_' . $this->id . '_' . time() . '.bin');
            file_put_contents($filename, $data);
            return ['success' => true, 'message' => 'Сохранено в файл: ' . $filename];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Проверка соединения
     */
    public function testConnection(): array
    {
        if ($this->connection_type !== 'network') {
            return ['success' => true, 'message' => 'Проверка доступна только для сетевых принтеров'];
        }

        try {
            $socket = @fsockopen($this->ip_address, $this->port, $errno, $errstr, 3);
            
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Принтер недоступен: $errstr",
                ];
            }

            fclose($socket);
            return ['success' => true, 'message' => 'Принтер доступен'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
