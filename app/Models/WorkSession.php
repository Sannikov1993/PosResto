<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WorkSession extends Model
{
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'clock_in',
        'clock_out',
        'hours_worked',
        'break_minutes',
        'notes',
        'clock_in_ip',
        'clock_out_ip',
        'clock_in_verified_by',
        'clock_out_verified_by',
        'status',
        'is_manual', // Флаг: смена создана/отредактирована вручную (биометрия не меняет)
        'corrected_by',
        'correction_reason',
        'original_clock_in',
        'original_clock_out',
        'unclosed_reminder_sent_at',
        'clock_in_event_id',
        'clock_out_event_id',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'original_clock_in' => 'datetime',
        'original_clock_out' => 'datetime',
        'hours_worked' => 'decimal:2',
        'break_minutes' => 'decimal:2',
        'unclosed_reminder_sent_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    protected $appends = ['duration_formatted', 'date', 'is_active'];

    // Статусы
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CORRECTED = 'corrected';
    const STATUS_AUTO_CLOSED = 'auto_closed'; // Автозакрыто (сотрудник забыл уйти, часы = 0)

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function clockInEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'clock_in_event_id');
    }

    public function clockOutEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'clock_out_event_id');
    }

    // Accessors
    public function getDurationFormattedAttribute(): string
    {
        if (!$this->hours_worked) {
            if ($this->clock_in && !$this->clock_out) {
                $hours = $this->clock_in->diffInMinutes(now()) / 60;
                return $this->formatHours($hours) . ' (в работе)';
            }
            return '-';
        }
        return $this->formatHours($this->hours_worked);
    }

    public function getDateAttribute(): ?string
    {
        return $this->clock_in?->format('Y-m-d');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->clock_in && !$this->clock_out;
    }

    // Helpers
    private function formatHours(float $hours): string
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return sprintf('%d ч %02d мин', $h, $m);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('clock_out')->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('clock_out');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('clock_in', [$startDate, $endDate]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('clock_in', $date);
    }

    // Methods
    public static function clockIn(int $userId, int $restaurantId, ?string $ip = null): self
    {
        // Закрываем незакрытые смены
        self::where('user_id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->whereNull('clock_out')
            ->update([
                'clock_out' => now(),
                'status' => self::STATUS_COMPLETED
            ]);

        return self::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $userId,
            'clock_in' => now(),
            'clock_in_ip' => $ip,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function clockOut(?string $ip = null): self
    {
        $clockOut = now();
        $hoursWorked = $this->clock_in->diffInMinutes($clockOut) / 60;
        $hoursWorked = max(0, $hoursWorked - ($this->break_minutes / 60));

        $this->update([
            'clock_out' => $clockOut,
            'clock_out_ip' => $ip,
            'hours_worked' => round($hoursWorked, 2),
            'status' => self::STATUS_COMPLETED,
        ]);

        return $this;
    }

    public function correct(Carbon $newClockIn, ?Carbon $newClockOut, int $correctorId, string $reason): self
    {
        // Сохраняем оригинальные значения
        if (!$this->original_clock_in) {
            $this->original_clock_in = $this->clock_in;
            $this->original_clock_out = $this->clock_out;
        }

        $hoursWorked = null;
        if ($newClockOut) {
            $hoursWorked = $newClockIn->diffInMinutes($newClockOut) / 60;
            $hoursWorked = max(0, $hoursWorked - ($this->break_minutes / 60));
        }

        $this->update([
            'clock_in' => $newClockIn,
            'clock_out' => $newClockOut,
            'hours_worked' => $hoursWorked ? round($hoursWorked, 2) : null,
            'status' => $newClockOut ? self::STATUS_CORRECTED : self::STATUS_ACTIVE,
            'corrected_by' => $correctorId,
            'correction_reason' => $reason,
        ]);

        return $this;
    }

    // Статистика
    public static function getStatsForPeriod(int $userId, $startDate, $endDate): array
    {
        $sessions = self::where('user_id', $userId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->whereNotNull('clock_out')
            ->get();

        $totalHours = $sessions->sum('hours_worked');
        $daysWorked = $sessions->pluck('date')->unique()->count();
        $avgHoursPerDay = $daysWorked > 0 ? $totalHours / $daysWorked : 0;

        return [
            'total_hours' => round($totalHours, 2),
            'days_worked' => $daysWorked,
            'sessions_count' => $sessions->count(),
            'avg_hours_per_day' => round($avgHoursPerDay, 2),
        ];
    }

    public static function getActiveSession(int $userId, int $restaurantId): ?self
    {
        return self::where('user_id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->whereNull('clock_out')
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }
}
