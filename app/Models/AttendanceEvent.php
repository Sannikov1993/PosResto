<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToRestaurant;

class AttendanceEvent extends Model
{
    use BelongsToRestaurant;
    // Типы событий
    const TYPE_CLOCK_IN = 'clock_in';
    const TYPE_CLOCK_OUT = 'clock_out';

    // Источники
    const SOURCE_DEVICE = 'device';
    const SOURCE_QR_CODE = 'qr_code';
    const SOURCE_MANUAL = 'manual';
    const SOURCE_API = 'api';

    // Методы верификации
    const METHOD_FACE = 'face';
    const METHOD_FINGERPRINT = 'fingerprint';
    const METHOD_CARD = 'card';
    const METHOD_QR = 'qr';
    const METHOD_PIN = 'pin';
    const METHOD_MANUAL = 'manual';

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'device_id',
        'work_session_id',
        'event_type',
        'source',
        'device_event_id',
        'confidence',
        'verification_method',
        'latitude',
        'longitude',
        'ip_address',
        'user_agent',
        'raw_data',
        'event_time',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'raw_data' => 'array',
        'event_time' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::observe(\App\Observers\AttendanceEventObserver::class);
    }

    // ==================== RELATIONSHIPS ====================

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    // ==================== SCOPES ====================

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeClockIn($query)
    {
        return $query->where('event_type', self::TYPE_CLOCK_IN);
    }

    public function scopeClockOut($query)
    {
        return $query->where('event_type', self::TYPE_CLOCK_OUT);
    }

    public function scopeFromDevice($query)
    {
        return $query->where('source', self::SOURCE_DEVICE);
    }

    public function scopeFromQr($query)
    {
        return $query->where('source', self::SOURCE_QR_CODE);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('event_time', today());
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('event_time', $date);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_time', [$startDate, $endDate]);
    }

    // ==================== ACCESSORS ====================

    public function getIsClockInAttribute(): bool
    {
        return $this->event_type === self::TYPE_CLOCK_IN;
    }

    public function getIsClockOutAttribute(): bool
    {
        return $this->event_type === self::TYPE_CLOCK_OUT;
    }

    public function getSourceLabelAttribute(): string
    {
        return match($this->source) {
            self::SOURCE_DEVICE => 'Терминал',
            self::SOURCE_QR_CODE => 'QR-код',
            self::SOURCE_MANUAL => 'Вручную',
            self::SOURCE_API => 'API',
            default => $this->source,
        };
    }

    public function getMethodLabelAttribute(): string
    {
        return match($this->verification_method) {
            self::METHOD_FACE => 'Лицо',
            self::METHOD_FINGERPRINT => 'Отпечаток',
            self::METHOD_CARD => 'Карта',
            self::METHOD_QR => 'QR-код',
            self::METHOD_PIN => 'PIN-код',
            self::METHOD_MANUAL => 'Вручную',
            default => $this->verification_method ?? '-',
        };
    }

    // ==================== METHODS ====================

    public function isClockIn(): bool
    {
        return $this->event_type === self::TYPE_CLOCK_IN;
    }

    public function isClockOut(): bool
    {
        return $this->event_type === self::TYPE_CLOCK_OUT;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Рассчитать расстояние до точки в метрах
     */
    public function distanceTo(float $lat, float $lng): ?float
    {
        if (!$this->hasLocation()) {
            return null;
        }

        // Формула Хаверсина
        $earthRadius = 6371000; // метры

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
             cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Создать событие clock in
     */
    public static function createClockIn(array $data): self
    {
        $data['event_type'] = self::TYPE_CLOCK_IN;
        return self::create($data);
    }

    /**
     * Создать событие clock out
     */
    public static function createClockOut(array $data): self
    {
        $data['event_type'] = self::TYPE_CLOCK_OUT;
        return self::create($data);
    }
}
