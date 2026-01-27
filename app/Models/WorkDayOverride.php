<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDayOverride extends Model
{
    protected $fillable = [
        'user_id',
        'restaurant_id',
        'date',
        'type',
        'start_time',
        'end_time',
        'hours',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
    ];

    // Типы дней
    const TYPE_SHIFT = 'shift';           // Полная смена
    const TYPE_DAY_OFF = 'day_off';       // Выходной
    const TYPE_VACATION = 'vacation';     // Отпуск
    const TYPE_SICK_LEAVE = 'sick_leave'; // Больничный
    const TYPE_ABSENCE = 'absence';       // Прогул

    // Типы с часами (считаются в табель)
    const TYPES_WITH_HOURS = [
        self::TYPE_SHIFT,
        self::TYPE_VACATION,
        self::TYPE_SICK_LEAVE,
    ];

    // Типы без часов
    const TYPES_WITHOUT_HOURS = [
        self::TYPE_DAY_OFF,
        self::TYPE_ABSENCE,
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helpers
    public static function getTypeLabel(string $type): string
    {
        return match($type) {
            self::TYPE_SHIFT => 'Смена',
            self::TYPE_DAY_OFF => 'Выходной',
            self::TYPE_VACATION => 'Отпуск',
            self::TYPE_SICK_LEAVE => 'Больничный',
            self::TYPE_ABSENCE => 'Прогул',
            default => $type,
        };
    }

    public static function getTypeColor(string $type): string
    {
        return match($type) {
            self::TYPE_SHIFT => 'blue',
            self::TYPE_DAY_OFF => 'gray',
            self::TYPE_VACATION => 'green',
            self::TYPE_SICK_LEAVE => 'yellow',
            self::TYPE_ABSENCE => 'red',
            default => 'gray',
        };
    }

    public static function getAllTypes(): array
    {
        return [
            ['value' => self::TYPE_SHIFT, 'label' => 'Смена', 'color' => 'blue', 'has_hours' => true],
            ['value' => self::TYPE_DAY_OFF, 'label' => 'Выходной', 'color' => 'gray', 'has_hours' => false],
            ['value' => self::TYPE_VACATION, 'label' => 'Отпуск', 'color' => 'green', 'has_hours' => true],
            ['value' => self::TYPE_SICK_LEAVE, 'label' => 'Больничный', 'color' => 'yellow', 'has_hours' => true],
            ['value' => self::TYPE_ABSENCE, 'label' => 'Прогул', 'color' => 'red', 'has_hours' => false],
        ];
    }

    public function hasHours(): bool
    {
        return in_array($this->type, self::TYPES_WITH_HOURS);
    }

    public function getFormattedHours(): string
    {
        $h = floor($this->hours);
        $m = round(($this->hours - $h) * 60);
        return sprintf('%d:%02d', $h, $m);
    }
}
