<?php

namespace App\Observers;

use App\Models\AttendanceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceEventObserver
{
    /**
     * Handle the AttendanceEvent "created" event.
     * Автоматически обновляет face_status когда приходит отметка от устройства.
     */
    public function created(AttendanceEvent $event): void
    {
        // Только для событий от устройства
        if ($event->source !== AttendanceEvent::SOURCE_DEVICE || !$event->device_id) {
            return;
        }

        // Находим связь пользователь-устройство
        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $event->device_id)
            ->where('user_id', $event->user_id)
            ->first();

        if (!$pivot) {
            Log::debug('AttendanceEventObserver: pivot not found', [
                'device_id' => $event->device_id,
                'user_id' => $event->user_id,
            ]);
            return;
        }

        // Определяем тип биометрии
        $method = $event->verification_method;
        $isFace = in_array($method, ['face', AttendanceEvent::METHOD_FACE]);
        $isFingerprint = in_array($method, ['fingerprint', AttendanceEvent::METHOD_FINGERPRINT]);

        $updates = [
            'is_synced' => true,
            'sync_error' => null,
        ];

        // Обновляем face_status
        if ($isFace && ($pivot->face_status ?? 'none') !== 'enrolled') {
            $updates['face_status'] = 'enrolled';
            $updates['face_enrolled_at'] = now();
            Log::info('AttendanceEventObserver: face_status updated to enrolled', [
                'user_id' => $event->user_id,
                'device_id' => $event->device_id,
            ]);
        }

        // Обновляем fingerprint_status
        if ($isFingerprint && ($pivot->fingerprint_status ?? 'none') !== 'enrolled') {
            $updates['fingerprint_status'] = 'enrolled';
            $updates['fingerprint_enrolled_at'] = now();
            Log::info('AttendanceEventObserver: fingerprint_status updated to enrolled', [
                'user_id' => $event->user_id,
                'device_id' => $event->device_id,
            ]);
        }

        // Обновляем запись
        if (count($updates) > 2) { // Есть изменения помимо is_synced и sync_error
            DB::table('attendance_device_users')
                ->where('id', $pivot->id)
                ->update($updates);
        } elseif (!$pivot->is_synced) {
            // Просто помечаем как синхронизированный
            DB::table('attendance_device_users')
                ->where('id', $pivot->id)
                ->update($updates);
        }
    }
}
