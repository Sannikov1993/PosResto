<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BonusTransaction extends Model
{
    protected $fillable = [
        'restaurant_id',
        'customer_id',
        'order_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'expires_at' => 'date',
    ];

    protected $appends = ['type_label', 'type_icon'];

    const TYPE_EARN = 'earn';
    const TYPE_SPEND = 'spend';
    const TYPE_EXPIRE = 'expire';
    const TYPE_MANUAL = 'manual';
    const TYPE_BIRTHDAY = 'birthday';
    const TYPE_PROMO = 'promo';

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'earn' => 'Начисление',
            'spend' => 'Списание',
            'expire' => 'Сгорание',
            'manual' => 'Ручная операция',
            'birthday' => 'Бонус ко дню рождения',
            'promo' => 'Промокод',
        ][$this->type] ?? $this->type;
    }

    public function getTypeIconAttribute()
    {
        return [
            'earn' => '➕',
            'spend' => '➖',
            'expire' => '🔥',
            'manual' => '✏️',
            'birthday' => '🎂',
            'promo' => '🎁',
        ][$this->type] ?? '💰';
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeEarnings($query)
    {
        return $query->whereIn('type', ['earn', 'manual', 'birthday', 'promo'])
            ->where('amount', '>', 0);
    }

    public function scopeSpendings($query)
    {
        return $query->whereIn('type', ['spend', 'expire'])
            ->where('amount', '<', 0);
    }
}