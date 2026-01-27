<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\AnvizTcpClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAttendanceRecords extends Command
{
    protected $signature = 'attendance:sync {--device= : ID устройства (опционально)}';
    protected $description = 'Синхронизация отметок с биометрических устройств';

    public function handle(): int
    {
        $deviceId = $this->option('device');

        $query = AttendanceDevice::where('type', 'anviz')
            ->where('status', 'active')
            ->whereNotNull('ip_address');

        if ($deviceId) {
            $query->where('id', $deviceId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->warn('Нет активных устройств для синхронизации');
            return 0;
        }

        foreach ($devices as $device) {
            $this->syncDevice($device);
        }

        return 0;
    }

    protected function syncDevice(AttendanceDevice $device): void
    {
        $this->info("Синхронизация: {$device->name} ({$device->ip_address})");

        $client = new AnvizTcpClient(
            $device->ip_address,
            $device->port ?? 5010,
            $device->settings['device_code'] ?? 1
        );

        try {
            if (!$client->connect()) {
                $this->error("  Не удалось подключиться");
                return;
            }

            // Обновляем heartbeat
            $device->markHeartbeat();

            // Получаем новые записи
            $records = $client->getNewRecords();
            $this->info("  Получено записей: " . count($records));

            if (empty($records)) {
                $client->disconnect();
                return;
            }

            // Обрабатываем записи
            $created = 0;
            $skipped = 0;

            foreach ($records as $record) {
                $result = $this->processRecord($device, $record);
                if ($result) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            $this->info("  Создано: {$created}, пропущено: {$skipped}");

            // Обновляем время синхронизации
            $device->markSynced();

            $client->disconnect();

        } catch (\Exception $e) {
            $this->error("  Ошибка: " . $e->getMessage());
            Log::error('SyncAttendanceRecords error', [
                'device' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function processRecord(AttendanceDevice $device, array $record): bool
    {
        $deviceUserId = $record['user_id'] ?? null;
        $eventTime = $record['datetime'] ?? null;
        $eventType = $record['type'] ?? 'clock_in';
        $method = $record['method'] ?? 'face';

        if (!$deviceUserId || !$eventTime) {
            return false;
        }

        // Находим пользователя PosLab по device_user_id
        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $device->id)
            ->where('device_user_id', (string) $deviceUserId)
            ->first();

        if (!$pivot) {
            Log::warning('SyncAttendanceRecords: Unknown user', [
                'device_id' => $device->id,
                'device_user_id' => $deviceUserId,
            ]);
            return false;
        }

        // Проверяем, не дубликат ли
        $exists = AttendanceEvent::where('device_id', $device->id)
            ->where('user_id', $pivot->user_id)
            ->where('event_time', $eventTime)
            ->exists();

        if ($exists) {
            return false;
        }

        // Создаём событие
        AttendanceEvent::create([
            'restaurant_id' => $device->restaurant_id,
            'user_id' => $pivot->user_id,
            'device_id' => $device->id,
            'event_type' => $eventType,
            'event_time' => $eventTime,
            'source' => AttendanceEvent::SOURCE_DEVICE,
            'verification_method' => $this->mapMethod($method),
            'raw_data' => $record,
        ]);

        // Если это первая отметка пользователя - обновляем face_status
        if ($pivot->face_status !== 'enrolled') {
            DB::table('attendance_device_users')
                ->where('id', $pivot->id)
                ->update([
                    'is_synced' => true,
                    'face_status' => 'enrolled',
                    'face_enrolled_at' => now(),
                    'sync_error' => null,
                ]);
        }

        return true;
    }

    protected function mapMethod(string $method): string
    {
        return match ($method) {
            'face', 'facial' => AttendanceEvent::METHOD_FACE,
            'fingerprint', 'finger' => AttendanceEvent::METHOD_FINGERPRINT,
            'card', 'rfid' => AttendanceEvent::METHOD_CARD,
            'password', 'pin' => AttendanceEvent::METHOD_PIN,
            default => AttendanceEvent::METHOD_FACE,
        };
    }
}
