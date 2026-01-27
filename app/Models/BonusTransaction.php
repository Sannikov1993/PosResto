<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'expires_at' => 'datetime',
    ];

    protected $appends = ['type_label', 'type_icon', 'formatted_amount'];

    const TYPE_EARN = 'earn';
    const TYPE_SPEND = 'spend';
    const TYPE_EXPIRE = 'expire';
    const TYPE_MANUAL = 'manual';
    const TYPE_BIRTHDAY = 'birthday';
    const TYPE_PROMO = 'promo';
    const TYPE_REFERRAL = 'referral';
    const TYPE_REGISTRATION = 'registration';
    const TYPE_REFUND = 'refund';

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public function getTypeIconAttribute(): string
    {
        return [
            'earn' => '➕',
            'spend' => '➖',
            'expire' => '🔥',
            'manual' => '✏️',
            'birthday' => '🎂',
            'promo' => '🎁',
            'referral' => '👥',
            'registration' => '🎉',
            'refund' => '↩️',
        ][$this->type] ?? '💰';
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount >= 0 ? '+' : '';
        return $prefix . number_format($this->amount, 0);
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeEarnings($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeSpendings($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    // Static
    public static function getTypes(): array
    {
        return [
            'earn' => 'Начисление за заказ',
            'spend' => 'Списание',
            'expire' => 'Сгорание',
            'manual' => 'Ручная операция',
            'birthday' => 'Бонус ко дню рождения',
            'promo' => 'Промокод',
            'referral' => 'За реферала',
            'registration' => 'За регистрацию',
            'refund' => 'Возврат',
        ];
    }

    /**
     * @deprecated Используйте BonusService для работы с бонусами
     */
    public static function createTransaction(
        int $customerId,
        string $type,
        float $amount,
        ?int $orderId = null,
        ?string $description = null,
        int $restaurantId = 1,
        ?int $createdBy = null
    ): self {
        $customer = Customer::findOrFail($customerId);

        $currentBalance = $customer->bonus_balance ?? 0;
        $newBalance = $currentBalance + $amount;

        // Prevent negative balance
        if ($newBalance < 0) {
            $newBalance = 0;
            $amount = -$currentBalance;
        }

        $customer->update([
            'bonus_balance' => (int) $newBalance
        ]);

        return self::create([
            'restaurant_id' => $restaurantId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $description,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @deprecated Используйте BonusService::earnForOrder()
     */
    public static function earnFromOrder(Customer $customer, Order $order, float $earnRate, float $multiplier = 1): self
    {
        $baseBonusAmount = round($order->total * ($earnRate / 100));
        $bonusAmount = $multiplier > 1 ? round($baseBonusAmount * $multiplier) : $baseBonusAmount;

        $description = "Начисление {$earnRate}% от заказа #{$order->id}";
        if ($multiplier > 1) {
            $description .= " (x{$multiplier} по промокоду)";
        }

        return self::createTransaction(
            $customer->id,
            self::TYPE_EARN,
            $bonusAmount,
            $order->id,
            $description,
            $order->restaurant_id
        );
    }

    /**
     * @deprecated Используйте BonusService::spendForOrder()
     */
    public static function spendOnOrder(Customer $customer, Order $order, float $amount): self
    {
        return self::createTransaction(
            $customer->id,
            self::TYPE_SPEND,
            -abs($amount),
            $order->id,
            "Списание на заказ #{$order->id}",
            $order->restaurant_id
        );
    }

    /**
     * @deprecated Используйте BonusService::refundForOrder()
     */
    public static function refundFromOrder(Customer $customer, Order $order, float $amount): self
    {
        return self::createTransaction(
            $customer->id,
            self::TYPE_REFUND,
            abs($amount),
            $order->id,
            "Возврат бонусов за отменённый заказ #{$order->id}",
            $order->restaurant_id
        );
    }

    /**
     * @deprecated Используйте BonusService::awardRegistrationBonus()
     */
    public static function awardRegistrationBonus(Customer $customer, float $amount, int $restaurantId = 1): self
    {
        return self::createTransaction(
            $customer->id,
            self::TYPE_REGISTRATION,
            $amount,
            null,
            "Бонус за регистрацию",
            $restaurantId
        );
    }

    /**
     * @deprecated Используйте BonusService::awardBirthdayBonus()
     */
    public static function awardBirthdayBonus(Customer $customer, float $amount, int $restaurantId = 1): self
    {
        return self::createTransaction(
            $customer->id,
            self::TYPE_BIRTHDAY,
            $amount,
            null,
            "Бонус ко дню рождения",
            $restaurantId
        );
    }

    /**
     * @deprecated Используйте BonusService::awardReferralBonus()
     */
    public static function awardReferralBonus(Customer $customer, Customer $referredCustomer, float $amount, int $restaurantId = 1): self
    {
        return self::createTransaction(
            $customer->id,
            self::TYPE_REFERRAL,
            $amount,
            null,
            "Бонус за приглашение друга {$referredCustomer->name}",
            $restaurantId
        );
    }
}
