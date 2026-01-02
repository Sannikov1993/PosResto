<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PromoCode extends Model
{
    protected $fillable = [
        'restaurant_id',
        'code',
        'name',
        'type',
        'value',
        'min_order',
        'max_discount',
        'usage_limit',
        'usage_count',
        'per_customer_limit',
        'valid_from',
        'valid_until',
        'first_order_only',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'first_order_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['type_label', 'is_valid', 'formatted_value'];

    const TYPE_PERCENT = 'percent';
    const TYPE_FIXED = 'fixed';
    const TYPE_BONUS = 'bonus';

    // Relationships
    public function usages()
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'percent' => 'Процент',
            'fixed' => 'Фиксированная',
            'bonus' => 'Бонусы',
        ][$this->type] ?? $this->type;
    }

    public function getIsValidAttribute()
    {
        if (!$this->is_active) return false;
        
        $today = Carbon::today();
        if ($this->valid_from && $today->lt($this->valid_from)) return false;
        if ($this->valid_until && $today->gt($this->valid_until)) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        
        return true;
    }

    public function getFormattedValueAttribute()
    {
        if ($this->type === 'percent') return $this->value . '%';
        return number_format($this->value, 0) . '₽';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $today = Carbon::today();
        return $query->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    // Methods
    public static function findByCode($code, $restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function validate($customerId = null, $orderTotal = 0)
    {
        $errors = [];

        if (!$this->is_active) {
            $errors[] = 'Промокод неактивен';
        }

        $today = Carbon::today();
        if ($this->valid_from && $today->lt($this->valid_from)) {
            $errors[] = 'Промокод ещё не действует';
        }

        if ($this->valid_until && $today->gt($this->valid_until)) {
            $errors[] = 'Срок действия промокода истёк';
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            $errors[] = 'Лимит использования исчерпан';
        }

        if ($orderTotal < $this->min_order) {
            $errors[] = "Минимальная сумма заказа: {$this->min_order}₽";
        }

        if ($customerId && $this->per_customer_limit) {
            $customerUsages = $this->usages()->where('customer_id', $customerId)->count();
            if ($customerUsages >= $this->per_customer_limit) {
                $errors[] = 'Вы уже использовали этот промокод максимальное количество раз';
            }
        }

        if ($customerId && $this->first_order_only) {
            $hasOrders = \App\Models\Order::where('customer_id', $customerId)
                ->where('status', 'completed')
                ->exists();
            if ($hasOrders) {
                $errors[] = 'Промокод только для первого заказа';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function calculateDiscount($orderTotal)
    {
        if ($this->type === 'percent') {
            $discount = $orderTotal * $this->value / 100;
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
            return round($discount, 2);
        }

        if ($this->type === 'fixed') {
            return min($this->value, $orderTotal);
        }

        // bonus type - возвращает количество бонусов
        return $this->value;
    }

    public function use($customerId, $orderId, $discountAmount)
    {
        PromoCodeUsage::create([
            'promo_code_id' => $this->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
        ]);

        $this->increment('usage_count');
    }
}
