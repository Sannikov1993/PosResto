<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'color',
        'is_active',
    ];

    protected $casts = [
        'break_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['duration_hours'];

    // ==================== RELATIONSHIPS ====================

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // ==================== SCOPES ====================

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return round(abs($end->diffInMinutes($start)) / 60, 2);
    }

    // ==================== METHODS ====================

    /**
     * Get formatted time range
     */
    public function getTimeRange(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i');
    }

    /**
     * Create schedule from template
     */
    public function createSchedule(int $userId, $date, ?int $createdBy = null): StaffSchedule
    {
        return StaffSchedule::create([
            'restaurant_id' => $this->restaurant_id,
            'user_id' => $userId,
            'date' => $date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'status' => StaffSchedule::STATUS_DRAFT,
            'created_by' => $createdBy,
        ]);
    }

    // ==================== STATIC METHODS ====================

    /**
     * Get default templates for new restaurant
     */
    public static function createDefaults(int $restaurantId): void
    {
        $defaults = [
            [
                'name' => 'Утренняя смена',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 30,
                'color' => '#f59e0b',
            ],
            [
                'name' => 'Дневная смена',
                'start_time' => '12:00',
                'end_time' => '20:00',
                'break_minutes' => 30,
                'color' => '#10b981',
            ],
            [
                'name' => 'Вечерняя смена',
                'start_time' => '16:00',
                'end_time' => '00:00',
                'break_minutes' => 30,
                'color' => '#6366f1',
            ],
            [
                'name' => 'Полный день',
                'start_time' => '10:00',
                'end_time' => '22:00',
                'break_minutes' => 60,
                'color' => '#f97316',
            ],
        ];

        foreach ($defaults as $template) {
            static::create(array_merge($template, ['restaurant_id' => $restaurantId]));
        }
    }
}
