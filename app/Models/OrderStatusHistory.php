<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    protected $table = 'order_status_history';

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'status',
        'comment',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper: получить метку статуса
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'new' => 'Новый',
            'confirmed' => 'Подтверждён',
            'cooking' => 'Готовится',
            'ready' => 'Готов',
            'delivering' => 'Доставляется',
            'completed' => 'Завершён',
            'cancelled' => 'Отменён',
            default => $this->status,
        };
    }
}
