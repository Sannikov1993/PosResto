<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель лога позиций курьера
 *
 * Хранит историю перемещений курьера для каждого заказа.
 * Данные автоматически очищаются после завершения доставки.
 */
class CourierLocationLog extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'courier_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    // ==================== Связи ====================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    // ==================== Скоупы ====================

    /**
     * Записи за последние N минут
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Записи для конкретного заказа
     */
    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    // ==================== Методы ====================

    /**
     * Очистка старых логов
     *
     * Удаляет записи для завершённых/отменённых заказов старше 1 часа
     *
     * @return int Количество удалённых записей
     */
    public static function cleanup(): int
    {
        return self::whereHas('order', function ($query) {
            $query->whereIn('status', ['completed', 'cancelled']);
        })
        ->where('created_at', '<', now()->subHour())
        ->delete();
    }

    /**
     * Получить последнюю позицию курьера для заказа
     */
    public static function getLastPosition(int $orderId): ?self
    {
        return self::where('order_id', $orderId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Получить маршрут курьера для заказа
     *
     * @param int $orderId
     * @param int $limit Максимальное количество точек
     * @return \Illuminate\Support\Collection
     */
    public static function getRoute(int $orderId, int $limit = 100)
    {
        return self::where('order_id', $orderId)
            ->orderBy('recorded_at')
            ->limit($limit)
            ->get(['latitude', 'longitude', 'recorded_at']);
    }
}
