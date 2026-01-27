<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель истории заказа на доставку
 */
class DeliveryOrderHistory extends Model
{
    protected $table = 'delivery_order_history';

    protected $fillable = [
        'delivery_order_id', 'action', 'old_value', 'new_value', 'comment', 'user_id',
    ];

    const ACTIONS = [
        'created' => 'Создан',
        'status_changed' => 'Статус изменён',
        'courier_assigned' => 'Назначен курьер',
        'cancelled' => 'Отменён',
        'edited' => 'Изменён',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить описание действия
     */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Получить полное описание изменения
     */
    public function getDescriptionAttribute(): string
    {
        switch ($this->action) {
            case 'created':
                return 'Заказ создан';
            case 'status_changed':
                $oldLabel = DeliveryOrder::STATUSES[$this->old_value]['label'] ?? $this->old_value;
                $newLabel = DeliveryOrder::STATUSES[$this->new_value]['label'] ?? $this->new_value;
                return "Статус: {$oldLabel} → {$newLabel}";
            case 'courier_assigned':
                return "Назначен курьер: {$this->new_value}";
            case 'cancelled':
                return "Отменён" . ($this->new_value ? ": {$this->new_value}" : '');
            default:
                return $this->new_value ?? $this->action;
        }
    }
}
