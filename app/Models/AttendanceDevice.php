<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttendanceDevice extends Model
{
    // Типы устройств
    const TYPE_ANVIZ = 'anviz';
    const TYPE_ZKTECO = 'zkteco';
    const TYPE_HIKVISION = 'hikvision';
    const TYPE_GENERIC = 'generic';

    // Статусы
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_OFFLINE = 'offline';

    protected $fillable = [
        'restaurant_id',
        'name',
        'type',
        'model',
        'serial_number',
        'ip_address',
        'port',
        'api_key',
        'settings',
        'status',
        'last_heartbeat_at',
        'last_sync_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'port' => 'integer',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    // ==================== RELATIONSHIPS ====================

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'device_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attendance_device_users', 'device_id', 'user_id')
            ->withPivot([
                'device_user_id',
                'is_synced',
                'synced_at',
                'face_status',
                'face_enrolled_at',
                'face_templates_count',
                'fingerprint_status',
                'fingerprint_enrolled_at',
                'card_number',
                'sync_error',
            ])
            ->withTimestamps();
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ==================== METHODS ====================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnline(): bool
    {
        if (!$this->last_heartbeat_at) {
            return false;
        }
        // Считаем онлайн если heartbeat был в последние 5 минут
        return $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }

    public function markHeartbeat(): void
    {
        $this->update([
            'last_heartbeat_at' => now(),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function markOffline(): void
    {
        $this->update(['status' => self::STATUS_OFFLINE]);
    }

    public function markSynced(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Генерирует новый API ключ для устройства
     */
    public function regenerateApiKey(): string
    {
        $apiKey = bin2hex(random_bytes(32));
        $this->update(['api_key' => $apiKey]);
        return $apiKey;
    }

    /**
     * Проверяет API ключ
     */
    public function validateApiKey(string $key): bool
    {
        return hash_equals($this->api_key ?? '', $key);
    }

    /**
     * Получить настройку устройства
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Установить настройку устройства
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Синхронизировать пользователя с устройством
     */
    public function syncUser(User $user, ?string $deviceUserId = null, array $extraData = []): void
    {
        $isSynced = ($extraData['sync_status'] ?? 'synced') === 'synced';

        $pivotData = [
            'device_user_id' => $deviceUserId,
            'is_synced' => $isSynced,
            'synced_at' => $isSynced ? now() : null,
            'face_status' => $isSynced ? 'pending' : 'none',
            'sync_error' => $extraData['sync_error'] ?? null,
        ];

        // Добавляем дополнительные поля если переданы
        if (isset($extraData['face_status'])) {
            $pivotData['face_status'] = $extraData['face_status'];
        }
        if (isset($extraData['card_number'])) {
            $pivotData['card_number'] = $extraData['card_number'];
        }

        $this->users()->syncWithoutDetaching([
            $user->id => $pivotData,
        ]);
    }

    /**
     * Обновить статус биометрии пользователя
     */
    public function updateUserBiometricStatus(string $deviceUserId, array $data): bool
    {
        return \DB::table('attendance_device_users')
            ->where('device_id', $this->id)
            ->where('device_user_id', $deviceUserId)
            ->update($data) > 0;
    }

    /**
     * Получить список типов устройств
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ANVIZ => 'Anviz',
            self::TYPE_ZKTECO => 'ZKTeco',
            self::TYPE_HIKVISION => 'Hikvision',
            self::TYPE_GENERIC => 'Другое',
        ];
    }
}
