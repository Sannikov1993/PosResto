<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class Restaurant extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'logo',
        'address',
        'phone',
        'email',
        'settings',
        'is_active',
        'is_main',
        'latitude',
        'longitude',
        'attendance_mode',
        'attendance_early_minutes',
        'attendance_late_minutes',
        'device_registration_code',
        'device_registration_code_expires_at',
        // Telegram Guest Bot (white-label per restaurant)
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_bot_id',
        'telegram_webhook_secret',
        'telegram_bot_active',
        'telegram_bot_verified_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'device_registration_code_expires_at' => 'datetime',
        'telegram_bot_active' => 'boolean',
        'telegram_bot_verified_at' => 'datetime',
        // Шифрование секретов (Laravel encrypted cast)
        'telegram_bot_token' => 'encrypted',
        'telegram_webhook_secret' => 'encrypted',
    ];

    /**
     * Скрываем токен бота из сериализации
     */
    protected $hidden = [
        'telegram_bot_token',
        'telegram_webhook_secret',
    ];

    // ===== RELATIONSHIPS =====

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function kitchenStations(): HasMany
    {
        return $this->hasMany(KitchenStation::class);
    }

    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class)->orderBy('sort_order');
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class)->orderBy('sort_order');
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function attendanceDevices(): HasMany
    {
        return $this->hasMany(AttendanceDevice::class);
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function attendanceQrCodes(): HasMany
    {
        return $this->hasMany(AttendanceQrCode::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ===== HELPERS =====

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    // ===== WORKING HOURS =====

    /**
     * Дни недели для маппинга Carbon dayOfWeek -> ключ настроек
     */
    private const DAY_KEYS = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    /**
     * Получить время закрытия для конкретной даты
     */
    public function getClosingTimeForDate(\Carbon\Carbon $date): ?string
    {
        $dayKey = self::DAY_KEYS[$date->dayOfWeek];
        $daySettings = $this->getSetting("working_hours.{$dayKey}");

        if (!$daySettings || !($daySettings['enabled'] ?? false)) {
            return null; // Выходной день
        }

        return $daySettings['close'] ?? '23:00';
    }

    /**
     * Получить время открытия для конкретной даты
     */
    public function getOpeningTimeForDate(\Carbon\Carbon $date): ?string
    {
        $dayKey = self::DAY_KEYS[$date->dayOfWeek];
        $daySettings = $this->getSetting("working_hours.{$dayKey}");

        if (!$daySettings || !($daySettings['enabled'] ?? false)) {
            return null;
        }

        return $daySettings['open'] ?? '10:00';
    }

    /**
     * Буфер после закрытия для автозакрытия смен (по умолчанию 4 часа)
     */
    public function getAutoCloseBufferHours(): int
    {
        return (int) $this->getSetting('attendance.auto_close_buffer_hours', 4);
    }

    // ===== DEVICE REGISTRATION CODE =====

    /**
     * Генерирует новый 6-значный код регистрации устройств
     * Код действует 10 минут
     */
    public function generateDeviceRegistrationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'device_registration_code' => $code,
            'device_registration_code_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Проверяет, действителен ли текущий код регистрации
     */
    public function hasValidRegistrationCode(): bool
    {
        return $this->device_registration_code
            && $this->device_registration_code_expires_at
            && $this->device_registration_code_expires_at->isFuture();
    }

    /**
     * Проверяет, совпадает ли введённый код с текущим кодом регистрации
     */
    public function validateRegistrationCode(string $code): bool
    {
        if (!$this->hasValidRegistrationCode()) {
            return false;
        }

        return $this->device_registration_code === $code;
    }

    /**
     * Получить текущий код или null если истёк
     */
    public function getActiveRegistrationCode(): ?array
    {
        if (!$this->hasValidRegistrationCode()) {
            return null;
        }

        return [
            'code' => $this->device_registration_code,
            'expires_at' => $this->device_registration_code_expires_at->toIso8601String(),
            'expires_in_seconds' => $this->device_registration_code_expires_at->diffInSeconds(now()),
        ];
    }

    // ===== TELEGRAM BOT (WHITE-LABEL) =====

    /**
     * Проверяет, настроен ли и активен ли Telegram бот
     */
    public function hasTelegramBot(): bool
    {
        return $this->telegram_bot_active
            && !empty($this->telegram_bot_token)
            && !empty($this->telegram_bot_username);
    }

    /**
     * Проверяет, настроен ли бот (но может быть не верифицирован)
     */
    public function hasTelegramBotConfigured(): bool
    {
        return !empty($this->telegram_bot_token);
    }

    /**
     * Получить данные бота для отображения в UI
     */
    public function getTelegramBotInfo(): ?array
    {
        if (!$this->hasTelegramBotConfigured()) {
            return null;
        }

        return [
            'username' => $this->telegram_bot_username,
            'is_active' => $this->telegram_bot_active,
            'verified_at' => $this->telegram_bot_verified_at?->toIso8601String(),
        ];
    }

    /**
     * Генерирует секрет для webhook'а (используется для верификации запросов от Telegram)
     */
    public function generateTelegramWebhookSecret(): string
    {
        $secret = \Illuminate\Support\Str::random(64);
        $this->update(['telegram_webhook_secret' => $secret]);
        return $secret;
    }

    /**
     * Scope: рестораны с активным Telegram ботом
     */
    public function scopeWithTelegramBot($query)
    {
        return $query->where('telegram_bot_active', true)
            ->whereNotNull('telegram_bot_token')
            ->whereNotNull('telegram_bot_username');
    }

    /**
     * Find restaurant by bot_id (for webhook routing)
     */
    public static function findByTelegramBotId(string $botId): ?static
    {
        return static::where('telegram_bot_id', $botId)
            ->where('telegram_bot_active', true)
            ->first();
    }
}
