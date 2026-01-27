<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StopList extends Model
{
    use HasFactory;

    protected $table = 'stop_list';

    protected $fillable = [
        'restaurant_id',
        'dish_id',
        'reason',
        'stopped_at',
        'resume_at',
        'stopped_by',
    ];

    protected $casts = [
        'stopped_at' => 'datetime',
        'resume_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function stoppedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by');
    }

    // ===== SCOPES =====

    /**
     * Только активные записи (бессрочные или resume_at ещё не наступило)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('resume_at')
              ->orWhere('resume_at', '>', now());
        });
    }

    /**
     * Фильтр по ресторану
     */
    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Истёкшие записи (resume_at уже прошло)
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('resume_at')
            ->where('resume_at', '<=', now());
    }

    /**
     * Записи, которые истекают скоро (в течение указанных минут)
     */
    public function scopeExpiringSoon($query, int $minutes = 60)
    {
        return $query->whereNotNull('resume_at')
            ->where('resume_at', '>', now())
            ->where('resume_at', '<=', now()->addMinutes($minutes));
    }

    /**
     * Бессрочные записи
     */
    public function scopeIndefinite($query)
    {
        return $query->whereNull('resume_at');
    }

    // ===== HELPERS =====

    /**
     * Проверяет, активна ли запись
     */
    public function isActive(): bool
    {
        return is_null($this->resume_at) || $this->resume_at->isFuture();
    }

    /**
     * Проверяет, истекла ли запись
     */
    public function isExpired(): bool
    {
        return !is_null($this->resume_at) && $this->resume_at->isPast();
    }

    /**
     * Проверяет, скоро ли истечёт запись
     */
    public function isExpiringSoon(int $minutes = 60): bool
    {
        if (is_null($this->resume_at)) {
            return false;
        }
        return $this->resume_at->isFuture() && $this->resume_at->diffInMinutes(now()) <= $minutes;
    }

    /**
     * Проверяет, находится ли блюдо в активном стоп-листе
     */
    public static function isDishStopped(int $dishId, int $restaurantId): bool
    {
        return static::where('restaurant_id', $restaurantId)
            ->where('dish_id', $dishId)
            ->active()
            ->exists();
    }

    /**
     * Получить ID всех блюд в стоп-листе для ресторана
     */
    public static function getStoppedDishIds(int $restaurantId): array
    {
        return static::where('restaurant_id', $restaurantId)
            ->active()
            ->pluck('dish_id')
            ->toArray();
    }
}
