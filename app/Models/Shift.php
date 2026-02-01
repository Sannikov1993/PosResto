<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToRestaurant;

class Shift extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['duration_hours', 'status_label', 'time_range'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timeEntry()
    {
        return $this->hasOne(TimeEntry::class);
    }

    // Accessors
    public function getDurationHoursAttribute()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return round($start->diffInMinutes($end) / 60, 1);
    }

    public function getStatusLabelAttribute()
    {
        return [
            'scheduled' => 'Запланировано',
            'confirmed' => 'Подтверждено',
            'in_progress' => 'В работе',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено',
            'no_show' => 'Не явился',
        ][$this->status] ?? $this->status;
    }

    public function getTimeRangeAttribute()
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i');
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

    public function scopeForWeek($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        return $query->whereBetween('date', [
            $date->copy()->startOfWeek(),
            $date->copy()->endOfWeek(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    // Methods
    public function start()
    {
        $this->update(['status' => 'in_progress']);
        
        // Создаём запись учёта времени
        return TimeEntry::create([
            'restaurant_id' => $this->restaurant_id,
            'user_id' => $this->user_id,
            'shift_id' => $this->id,
            'date' => $this->date,
            'clock_in' => now(),
            'status' => 'active',
        ]);
    }

    public function complete()
    {
        $this->update(['status' => 'completed']);
        
        // Завершаем запись учёта времени
        if ($this->timeEntry && $this->timeEntry->status === 'active') {
            $this->timeEntry->clockOut();
        }
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}
