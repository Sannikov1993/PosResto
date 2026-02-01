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
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
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
}
