<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель зоны доставки
 */
class DeliveryZone extends Model
{
    protected $fillable = [
        'restaurant_id',
        'name',
        'min_distance',
        'max_distance',
        'delivery_fee',
        'free_delivery_from',
        'estimated_time',
        'color',
        'polygon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'min_distance' => 'decimal:2',
        'max_distance' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'free_delivery_from' => 'decimal:2',
        'estimated_time' => 'integer',
        'polygon' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_zone_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // Helpers

    /**
     * Получить стоимость доставки с учётом суммы заказа
     */
    public function getDeliveryFee(float $orderTotal): float
    {
        if ($this->free_delivery_from && $orderTotal >= $this->free_delivery_from) {
            return 0;
        }
        return (float) $this->delivery_fee;
    }

    /**
     * Получить описание зоны
     */
    public function getDescription(): string
    {
        $desc = "{$this->min_distance}-{$this->max_distance} км";
        if ($this->delivery_fee > 0) {
            $desc .= ", {$this->delivery_fee} ₽";
            if ($this->free_delivery_from) {
                $desc .= " (бесплатно от {$this->free_delivery_from} ₽)";
            }
        } else {
            $desc .= ", бесплатно";
        }
        return $desc;
    }

    /**
     * Определить зону по расстоянию
     */
    public static function getZoneByDistance(float $distance, int $restaurantId = 1): ?self
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('min_distance', '<=', $distance)
            ->where('max_distance', '>=', $distance)
            ->first();
    }
}
