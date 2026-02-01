<?php

namespace App\Services;

use App\Helpers\TimeHelper;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\AttendanceQrCode;
use App\Models\Restaurant;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Обработать событие от устройства биометрии
     */
    public function processDeviceEvent(
        AttendanceDevice $device,
        string $eventType,
        string $deviceUserId,
        Carbon $eventTime,
        array $rawData = []
    ): array {
        // Находим пользователя по device_user_id
        $user = $device->users()
            ->wherePivot('device_user_id', $deviceUserId)
            ->first();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'user_not_found',
                'message' => "Пользователь с ID {$deviceUserId} не найден на устройстве",
            ];
        }

        // Обновляем face_status если это отметка по Face ID
        $method = $rawData['method'] ?? 'face';
        if (in_array($method, ['face', AttendanceEvent::METHOD_FACE])) {
            $this->updateBiometricStatus($device->id, $deviceUserId, 'face');
        } elseif (in_array($method, ['fingerprint', AttendanceEvent::METHOD_FINGERPRINT])) {
            $this->updateBiometricStatus($device->id, $deviceUserId, 'fingerprint');
        }

        // Автоматическое определение типа события (приход/уход)
        // Первое сканирование за день = приход, далее чередуется
        $eventType = $this->determineEventType($user, $device->restaurant_id, $eventTime);

        return $this->processAttendance(
            user: $user,
            restaurantId: $device->restaurant_id,
            eventType: $eventType,
            source: AttendanceEvent::SOURCE_DEVICE,
            eventTime: $eventTime,
            deviceId: $device->id,
            deviceEventId: $rawData['event_id'] ?? null,
            verificationMethod: $rawData['method'] ?? AttendanceEvent::METHOD_FACE,
            confidence: $rawData['confidence'] ?? null,
            rawData: $rawData,
        );
    }

    /**
     * Обновить статус биометрии при получении отметки
     */
    protected function updateBiometricStatus(int $deviceId, string $deviceUserId, string $type): void
    {
        $field = $type === 'face' ? 'face_status' : 'fingerprint_status';
        $enrolledAtField = $type === 'face' ? 'face_enrolled_at' : 'fingerprint_enrolled_at';

        $pivot = DB::table('attendance_device_users')
            ->where('device_id', $deviceId)
            ->where('device_user_id', $deviceUserId)
            ->first();

        Log::debug('updateBiometricStatus', [
            'device_id' => $deviceId,
            'device_user_id' => $deviceUserId,
            'type' => $type,
            'found' => (bool) $pivot,
            'current_status' => $pivot?->$field ?? 'not_found',
        ]);

        if ($pivot && ($pivot->$field ?? 'none') !== 'enrolled') {
            DB::table('attendance_device_users')
                ->where('id', $pivot->id)
                ->update([
                    'is_synced' => true,
                    $field => 'enrolled',
                    $enrolledAtField => now(),
                    'sync_error' => null,
                ]);
            Log::info('Biometric status updated to enrolled', ['pivot_id' => $pivot->id, 'type' => $type]);
        }
    }

    /**
     * Автоматическое определение типа события (приход/уход)
     * Логика: если есть активная смена (clock_in без clock_out) — это уход, иначе приход
     * Смены с is_manual=true игнорируются (биометрия их не закрывает)
     */
    protected function determineEventType(User $user, int $restaurantId, Carbon $eventTime): string
    {
        // Проверяем есть ли активная смена (открытая, но не закрытая)
        // Игнорируем смены с is_manual=true - они управляются только вручную
        $activeSession = WorkSession::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->where(function ($q) {
                $q->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->first();

        if ($activeSession) {
            // Есть активная смена — закрываем её
            return AttendanceEvent::TYPE_CLOCK_OUT;
        }

        // Нет активной смены — открываем новую
        return AttendanceEvent::TYPE_CLOCK_IN;
    }

    /**
     * Обработать событие от QR-кода
     */
    public function processQrEvent(
        User $user,
        string $qrToken,
        string $eventType,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        // Находим QR-код
        $qrCode = AttendanceQrCode::findByToken($qrToken);

        if (!$qrCode) {
            return [
                'success' => false,
                'error' => 'invalid_qr',
                'message' => 'Недействительный QR-код',
            ];
        }

        if (!$qrCode->validateToken($qrToken)) {
            return [
                'success' => false,
                'error' => 'expired_qr',
                'message' => 'QR-код истёк. Обновите страницу и отсканируйте снова.',
            ];
        }

        $restaurant = $qrCode->restaurant;

        // Проверяем геолокацию
        if ($qrCode->require_geolocation) {
            if ($latitude === null || $longitude === null) {
                return [
                    'success' => false,
                    'error' => 'geolocation_required',
                    'message' => 'Требуется разрешить доступ к геолокации',
                ];
            }

            if ($restaurant->latitude && $restaurant->longitude) {
                $distance = $this->calculateDistance(
                    $latitude, $longitude,
                    $restaurant->latitude, $restaurant->longitude
                );

                if ($distance > $qrCode->max_distance_meters) {
                    return [
                        'success' => false,
                        'error' => 'too_far',
                        'message' => "Вы находитесь слишком далеко от ресторана ({$distance}м)",
                    ];
                }
            }
        }

        return $this->processAttendance(
            user: $user,
            restaurantId: $restaurant->id,
            eventType: $eventType,
            source: AttendanceEvent::SOURCE_QR_CODE,
            eventTime: TimeHelper::now($restaurant->id),
            latitude: $latitude,
            longitude: $longitude,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            verificationMethod: AttendanceEvent::METHOD_QR,
        );
    }

    /**
     * Основная логика обработки прихода/ухода
     */
    public function processAttendance(
        User $user,
        int $restaurantId,
        string $eventType,
        string $source,
        Carbon $eventTime,
        ?int $deviceId = null,
        ?string $deviceEventId = null,
        ?string $verificationMethod = null,
        ?float $confidence = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $rawData = null,
    ): array {
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return [
                'success' => false,
                'error' => 'restaurant_not_found',
                'message' => 'Ресторан не найден',
            ];
        }

        // Проверяем режим учета времени
        $validation = $this->validateAttendanceMode($restaurant, $source);
        if (!$validation['allowed']) {
            return [
                'success' => false,
                'error' => 'mode_not_allowed',
                'message' => $validation['message'],
            ];
        }

        // Проверяем расписание
        $scheduleValidation = $this->validateSchedule($user, $restaurant, $eventType, $eventTime);
        if (!$scheduleValidation['allowed']) {
            return [
                'success' => false,
                'error' => $scheduleValidation['error'],
                'message' => $scheduleValidation['message'],
            ];
        }

        return DB::transaction(function () use (
            $user, $restaurantId, $eventType, $source, $eventTime,
            $deviceId, $deviceEventId, $verificationMethod, $confidence,
            $latitude, $longitude, $ipAddress, $userAgent, $rawData,
            $scheduleValidation
        ) {
            // Дедупликация событий от устройства по device_event_id
            if ($deviceEventId) {
                $existingEvent = AttendanceEvent::where('device_id', $deviceId)
                    ->where('device_event_id', $deviceEventId)
                    ->first();

                if ($existingEvent) {
                    return [
                        'success' => true,
                        'event' => $existingEvent,
                        'session' => $existingEvent->workSession,
                        'message' => 'Событие уже обработано',
                        'duplicate' => true,
                    ];
                }
            }

            // Создаём событие
            $event = AttendanceEvent::create([
                'restaurant_id' => $restaurantId,
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'event_type' => $eventType,
                'source' => $source,
                'device_event_id' => $deviceEventId,
                'verification_method' => $verificationMethod,
                'confidence' => $confidence,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'raw_data' => $rawData,
                'event_time' => $eventTime,
            ]);

            // Обрабатываем clock in/out
            if ($eventType === AttendanceEvent::TYPE_CLOCK_IN) {
                $session = $this->handleClockIn($user, $restaurantId, $event, $ipAddress);
            } else {
                $session = $this->handleClockOut($user, $restaurantId, $event, $ipAddress);
            }

            // Связываем событие с сессией
            if ($session) {
                $event->update(['work_session_id' => $session->id]);
            }

            return [
                'success' => true,
                'event' => $event,
                'session' => $session,
                'schedule' => $scheduleValidation['schedule'] ?? null,
                'message' => $eventType === AttendanceEvent::TYPE_CLOCK_IN
                    ? 'Приход зафиксирован'
                    : 'Уход зафиксирован',
            ];
        });
    }

    /**
     * Проверить режим учета времени
     */
    protected function validateAttendanceMode(Restaurant $restaurant, string $source): array
    {
        $mode = $restaurant->attendance_mode;

        if ($mode === 'disabled') {
            return ['allowed' => true, 'message' => null];
        }

        $allowed = match($mode) {
            'device_only' => $source === AttendanceEvent::SOURCE_DEVICE,
            'qr_only' => $source === AttendanceEvent::SOURCE_QR_CODE,
            'device_or_qr' => in_array($source, [AttendanceEvent::SOURCE_DEVICE, AttendanceEvent::SOURCE_QR_CODE]),
            default => true,
        };

        if (!$allowed) {
            $modeLabels = [
                'device_only' => 'Используйте терминал в ресторане',
                'qr_only' => 'Отсканируйте QR-код в ресторане',
                'device_or_qr' => 'Используйте терминал или QR-код в ресторане',
            ];

            return [
                'allowed' => false,
                'message' => $modeLabels[$mode] ?? 'Метод отметки не разрешён',
            ];
        }

        return ['allowed' => true, 'message' => null];
    }

    /**
     * Проверить расписание
     */
    protected function validateSchedule(
        User $user,
        Restaurant $restaurant,
        string $eventType,
        Carbon $eventTime
    ): array {
        // Если режим disabled - пропускаем проверку расписания
        if ($restaurant->attendance_mode === 'disabled') {
            return ['allowed' => true, 'schedule' => null];
        }

        // Находим смену на сегодня
        $schedule = StaffSchedule::where('user_id', $user->id)
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('date', $eventTime->toDateString())
            ->published()
            ->first();

        if (!$schedule) {
            return [
                'allowed' => false,
                'error' => 'no_schedule',
                'message' => 'У вас нет запланированной смены на сегодня',
            ];
        }

        // Время начала и конца смены (в таймзоне ресторана)
        $tz = TimeHelper::getTimezone($restaurant->id);
        $shiftStart = Carbon::parse($schedule->date->format('Y-m-d') . ' ' . $schedule->start_time, $tz);
        $shiftEnd = Carbon::parse($schedule->date->format('Y-m-d') . ' ' . $schedule->end_time, $tz);

        // Ночная смена
        if ($shiftEnd->lt($shiftStart)) {
            $shiftEnd->addDay();
        }

        if ($eventType === AttendanceEvent::TYPE_CLOCK_IN) {
            // Проверяем что не слишком рано
            $earliestTime = $shiftStart->copy()->subMinutes($restaurant->attendance_early_minutes);
            if ($eventTime->lt($earliestTime)) {
                return [
                    'allowed' => false,
                    'error' => 'too_early',
                    'message' => "Слишком рано. Смена начинается в {$shiftStart->format('H:i')}",
                ];
            }

            // Проверяем что не слишком поздно
            $latestTime = $shiftStart->copy()->addMinutes($restaurant->attendance_late_minutes);
            if ($eventTime->gt($latestTime)) {
                return [
                    'allowed' => false,
                    'error' => 'too_late',
                    'message' => "Слишком поздно для отметки прихода. Обратитесь к менеджеру.",
                ];
            }
        }

        return [
            'allowed' => true,
            'schedule' => $schedule,
        ];
    }

    /**
     * Обработать clock in
     * Смены с is_manual=true НЕ закрываются автоматически
     */
    protected function handleClockIn(
        User $user,
        int $restaurantId,
        AttendanceEvent $event,
        ?string $ip = null
    ): WorkSession {
        // Получаем ресторан для режима работы
        $restaurant = Restaurant::find($restaurantId);

        // Блокируем незакрытые сессии для предотвращения race condition
        // Исключаем мануальные сессии - они управляются только вручную
        $existingSessions = WorkSession::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->whereNull('clock_out')
            ->where(function ($q) {
                $q->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->lockForUpdate()
            ->get();

        // Закрываем незакрытые сессии - часы = 0, админ проставит вручную
        foreach ($existingSessions as $session) {
            $session->update([
                'clock_out' => TimeHelper::now($restaurantId),
                'hours_worked' => 0, // Часы = 0, админ проставит вручную
                'status' => WorkSession::STATUS_AUTO_CLOSED,
                'notes' => ($session->notes ? $session->notes . '; ' : '') . 'Автозакрыто (сотрудник забыл отметить уход)',
            ]);
        }

        // Создаём новую сессию
        return WorkSession::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $user->id,
            'clock_in' => $event->event_time,
            'clock_in_ip' => $ip,
            'clock_in_event_id' => $event->id,
            'status' => WorkSession::STATUS_ACTIVE,
        ]);
    }

    /**
     * Обработать clock out
     * Смены с is_manual=true не закрываются биометрией
     */
    protected function handleClockOut(
        User $user,
        int $restaurantId,
        AttendanceEvent $event,
        ?string $ip = null
    ): ?WorkSession {
        // Находим активную сессию (только не-мануальные)
        $session = WorkSession::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->whereNull('clock_out')
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->first();

        if (!$session) {
            return null;
        }

        $hoursWorked = $session->clock_in->diffInMinutes($event->event_time) / 60;
        $hoursWorked = max(0, $hoursWorked - ($session->break_minutes / 60));

        $session->update([
            'clock_out' => $event->event_time,
            'clock_out_ip' => $ip,
            'clock_out_event_id' => $event->id,
            'hours_worked' => round($hoursWorked, 2),
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        return $session;
    }

    /**
     * Вычислить расстояние между двумя точками (в метрах)
     */
    protected function calculateDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        $earthRadius = 6371000; // метры

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2 +
             cos($lat1Rad) * cos($lat2Rad) * sin($lonDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c);
    }

    /**
     * Получить статус посещаемости пользователя
     */
    public function getUserAttendanceStatus(User $user, int $restaurantId): array
    {
        $activeSession = WorkSession::getActiveSession($user->id, $restaurantId);

        $todaySchedule = StaffSchedule::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->whereDate('date', today())
            ->published()
            ->first();

        $todayEvents = AttendanceEvent::forUser($user->id)
            ->forRestaurant($restaurantId)
            ->today()
            ->orderBy('event_time', 'desc')
            ->get();

        // Сессии за сегодня
        $todaySessions = WorkSession::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->whereDate('clock_in', today())
            ->orderBy('clock_in', 'asc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'clock_in' => $s->clock_in?->toIso8601String(),
                'clock_out' => $s->clock_out?->toIso8601String(),
                'hours_worked' => $s->hours_worked,
                'status' => $s->status,
            ]);

        // Проверяем требуется ли геолокация
        $qrCode = AttendanceQrCode::forRestaurant($restaurantId)->active()->first();

        return [
            'is_clocked_in' => $activeSession !== null,
            'active_session' => $activeSession,
            'today_schedule' => $todaySchedule,
            'today_events' => $todayEvents,
            'today_sessions' => $todaySessions,
            'can_clock_in' => $activeSession === null,
            'can_clock_out' => $activeSession !== null,
            'require_geolocation' => $qrCode?->require_geolocation ?? false,
        ];
    }
}
