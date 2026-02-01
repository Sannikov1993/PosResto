<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToRestaurant;

class StaffSchedule extends Model
{
    use HasFactory, BelongsToRestaurant;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'date',
        'start_time',
        'end_time',
        'break_minutes',
        'position',
        'notes',
        'status',
        'published_at',
        'created_by',
        'reminder_24h_sent_at',
        'reminder_1h_sent_at',
        'clock_in_reminder_sent_at',
        'clock_out_reminder_sent_at',
    ];

    protected $casts = [
        'date' => 'date',
        'published_at' => 'datetime',
        'break_minutes' => 'integer',
        'reminder_24h_sent_at' => 'datetime',
        'reminder_1h_sent_at' => 'datetime',
        'clock_in_reminder_sent_at' => 'datetime',
        'clock_out_reminder_sent_at' => 'datetime',
    ];

    protected $appends = ['duration_hours', 'work_hours'];

    // ==================== RELATIONSHIPS ====================

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForWeek($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();
        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();

        return $query->whereBetween('date', [$startOfWeek, $endOfWeek]);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get total duration in hours (including break)
     */
    public function getDurationHoursAttribute(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Handle overnight shifts
        if ($end->lt($start)) {
            $end->addDay();
        }

        // Use abs() for safety with diffInMinutes which may return signed value
        return round(abs($end->diffInMinutes($start)) / 60, 2);
    }

    /**
     * Get work hours (excluding break)
     */
    public function getWorkHoursAttribute(): float
    {
        $totalMinutes = $this->duration_hours * 60;
        $workMinutes = $totalMinutes - $this->break_minutes;

        return round($workMinutes / 60, 2);
    }

    // ==================== METHODS ====================

    /**
     * Check if schedule is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if schedule is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Publish this schedule
     */
    public function publish(): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * Unpublish this schedule (back to draft)
     */
    public function unpublish(): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * Check if shift overlaps with another shift for the same user
     */
    public function overlapsWithExisting(): bool
    {
        return static::where('user_id', $this->user_id)
            ->where('date', $this->date)
            ->where('id', '!=', $this->id ?? 0)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('start_time', '<', $this->end_time)
                      ->where('end_time', '>', $this->start_time);
                });
            })
            ->exists();
    }

    /**
     * Get formatted time range
     */
    public function getTimeRange(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i');
    }

    // ==================== STATIC METHODS ====================

    /**
     * Get week schedule for restaurant
     */
    public static function getWeekSchedule(int $restaurantId, $weekStart = null): array
    {
        $weekStart = $weekStart ? Carbon::parse($weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $schedules = static::forRestaurant($restaurantId)
            ->forDateRange($weekStart, $weekEnd)
            ->with(['user:id,name,role,avatar'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Group by date
        $byDate = [];
        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $byDate[$dateKey] = $schedules->where('date', $date->format('Y-m-d'))->values();
        }

        return $byDate;
    }

    /**
     * Copy schedules from one week to another
     */
    public static function copyWeek(int $restaurantId, $fromWeekStart, $toWeekStart, int $createdBy): int
    {
        $fromStart = Carbon::parse($fromWeekStart)->startOfWeek();
        $fromEnd = $fromStart->copy()->endOfWeek();
        $toStart = Carbon::parse($toWeekStart)->startOfWeek();

        $schedules = static::forRestaurant($restaurantId)
            ->forDateRange($fromStart, $fromEnd)
            ->get();

        $count = 0;
        foreach ($schedules as $schedule) {
            // Get day of week (1=Mon to 7=Sun in ISO), then calculate offset from Monday
            $scheduleDate = Carbon::parse($schedule->date);
            $dayOfWeek = $scheduleDate->dayOfWeekIso; // 1=Monday, 2=Tuesday, etc.
            $newDate = $toStart->copy()->startOfWeek()->addDays($dayOfWeek - 1);

            // Check if schedule already exists
            $exists = static::where('restaurant_id', $restaurantId)
                ->where('user_id', $schedule->user_id)
                ->where('date', $newDate)
                ->where('start_time', $schedule->start_time)
                ->exists();

            if (!$exists) {
                static::create([
                    'restaurant_id' => $restaurantId,
                    'user_id' => $schedule->user_id,
                    'date' => $newDate,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'break_minutes' => $schedule->break_minutes,
                    'position' => $schedule->position,
                    'notes' => null,
                    'status' => self::STATUS_DRAFT,
                    'created_by' => $createdBy,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Publish all draft schedules for a week
     */
    public static function publishWeek(int $restaurantId, $weekStart): int
    {
        $start = Carbon::parse($weekStart)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        return static::forRestaurant($restaurantId)
            ->forDateRange($start, $end)
            ->draft()
            ->update([
                'status' => self::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
    }

    /**
     * Get user's upcoming schedules
     */
    public static function getUpcomingForUser(int $userId, int $limit = 5)
    {
        return static::forUser($userId)
            ->published()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }
}
