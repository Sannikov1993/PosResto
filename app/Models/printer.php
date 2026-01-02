<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'restaurant_id',
        'name',
        'type',
        'connection',
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
        ][$this->connection] ?? $this->connection;
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
        return self::where('restaurant_id', $restaurantId)
            ->where('type', $type)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    public static function getKitchenPrinters($restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('type', 'kitchen')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Отправка данных на принтер
     */
    public function send(string $data): array
    {
        // Декодируем base64
        $rawData = base64_decode($data);
        
        switch ($this->connection) {
            case 'network':
                return $this->sendToNetwork($rawData);
            case 'usb':
                return $this->sendToUsb($rawData);
            case 'file':
                return $this->sendToFile($rawData);
            default:
                return ['success' => false, 'message' => 'Неподдерживаемый тип подключения'];
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

            // Windows: COM1, COM2, etc
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
        if ($this->connection !== 'network') {
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
