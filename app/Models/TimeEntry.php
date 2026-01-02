<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TimeEntry extends Model
{
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'shift_id',
        'date',
        'clock_in',
        'clock_out',
        'break_minutes',
        'worked_minutes',
        'status',
        'notes',
        'clock_in_method',
        'clock_out_method',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    protected $appends = ['worked_hours', 'is_active', 'formatted_clock_in', 'formatted_clock_out'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    // Accessors
    public function getWorkedHoursAttribute()
    {
        if ($this->worked_minutes) {
            return round($this->worked_minutes / 60, 1);
        }
        if ($this->clock_in && $this->clock_out) {
            $minutes = $this->clock_in->diffInMinutes($this->clock_out) - $this->break_minutes;
            return round($minutes / 60, 1);
        }
        if ($this->clock_in && $this->status === 'active') {
            $minutes = $this->clock_in->diffInMinutes(now()) - $this->break_minutes;
            return round($minutes / 60, 1);
        }
        return 0;
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active' && $this->clock_in && !$this->clock_out;
    }

    public function getFormattedClockInAttribute()
    {
        return $this->clock_in ? $this->clock_in->format('H:i') : null;
    }

    public function getFormattedClockOutAttribute()
    {
        return $this->clock_out ? $this->clock_out->format('H:i') : null;
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    // Methods
    public static function clockIn($userId, $method = 'manual', $restaurantId = 1)
    {
        // Проверяем, нет ли уже активной записи
        $active = self::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        
        if ($active) {
            return ['error' => 'Уже есть активная смена'];
        }

        return self::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $userId,
            'date' => Carbon::today(),
            'clock_in' => now(),
            'status' => 'active',
            'clock_in_method' => $method,
        ]);
    }

    public function clockOut($method = 'manual')
    {
        if ($this->status !== 'active') {
            return false;
        }

        $clockOut = now();
        $workedMinutes = $this->clock_in->diffInMinutes($clockOut) - $this->break_minutes;

        $this->update([
            'clock_out' => $clockOut,
            'worked_minutes' => max(0, $workedMinutes),
            'status' => 'completed',
            'clock_out_method' => $method,
        ]);

        // Обновляем связанную смену
        if ($this->shift_id) {
            $this->shift->update(['status' => 'completed']);
        }

        return true;
    }

    public function addBreak($minutes)
    {
        $this->increment('break_minutes', $minutes);
    }

    // Statistics
    public static function getMonthlyStats($userId, $month = null, $year = null)
    {
        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;

        $entries = self::where('user_id', $userId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('status', 'completed')
            ->get();

        return [
            'days_worked' => $entries->count(),
            'total_hours' => round($entries->sum('worked_minutes') / 60, 1),
            'avg_hours_per_day' => $entries->count() > 0 
                ? round($entries->sum('worked_minutes') / 60 / $entries->count(), 1) 
                : 0,
        ];
    }
}
