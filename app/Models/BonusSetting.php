<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;

class BonusSetting extends Model
{
    use BelongsToTenant, BelongsToRestaurant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'is_enabled',
        'currency_name',
        'currency_symbol',
        'earn_rate',
        'min_order_for_earn',
        'earn_rounding',
        'spend_rate',
        'min_spend_amount',
        'bonus_to_ruble',
        'expiry_days',
        'notify_before_expiry',
        'notify_days_before',
        'registration_bonus',
        'birthday_bonus',
        'referral_bonus',
        'referral_friend_bonus',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'earn_rate' => 'decimal:2',
        'min_order_for_earn' => 'decimal:2',
        'earn_rounding' => 'integer',
        'spend_rate' => 'decimal:2',
        'min_spend_amount' => 'decimal:2',
        'bonus_to_ruble' => 'decimal:2',
        'expiry_days' => 'integer',
        'notify_before_expiry' => 'boolean',
        'notify_days_before' => 'integer',
        'registration_bonus' => 'decimal:2',
        'birthday_bonus' => 'decimal:2',
        'referral_bonus' => 'decimal:2',
        'referral_friend_bonus' => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Получить настройки для ресторана (с созданием дефолтных если нет)
     */
    public static function getForRestaurant(int $restaurantId): self
    {
        return self::firstOrCreate(
            ['restaurant_id' => $restaurantId],
            [
                'is_enabled' => true,
                'currency_name' => 'бонусов',
                'currency_symbol' => 'B',
                'earn_rate' => 5,
                'min_order_for_earn' => 0,
                'earn_rounding' => 1,
                'spend_rate' => 50,
                'min_spend_amount' => 100,
                'bonus_to_ruble' => 1,
                'expiry_days' => null,
                'notify_before_expiry' => true,
                'notify_days_before' => 7,
                'registration_bonus' => 0,
                'birthday_bonus' => 0,
                'referral_bonus' => 0,
                'referral_friend_bonus' => 0,
            ]
        );
    }

    /**
     * Получить эффективную ставку кэшбэка для клиента
     * Учитывает уровень лояльности если он есть и включён
     */
    public function getEffectiveEarnRate(?Customer $customer): float
    {
        // Базовая ставка из настроек бонусной системы
        $rate = $this->earn_rate;

        // Если есть клиент, проверяем его уровень лояльности
        if ($customer) {
            // Проверяем включены ли уровни лояльности
            $levelsEnabled = LoyaltySetting::get('levels_enabled', '1', $this->restaurant_id) !== '0';

            if ($levelsEnabled) {
                // Загружаем уровень если не загружен
                if (!$customer->relationLoaded('loyaltyLevel')) {
                    $customer->load('loyaltyLevel');
                }

                // Если у уровня есть свой кэшбэк - используем его
                if ($customer->loyaltyLevel && $customer->loyaltyLevel->cashback_percent > 0) {
                    $rate = (float) $customer->loyaltyLevel->cashback_percent;
                }
            }
        }

        return $rate;
    }

    /**
     * Рассчитать бонусы для начисления
     * @param float $orderTotal - сумма заказа
     * @param float|null $customRate - кастомная ставка (если null - используется earn_rate)
     */
    public function calculateEarnAmount(float $orderTotal, ?float $customRate = null): int
    {
        if (!$this->is_enabled) {
            return 0;
        }

        if ($orderTotal < $this->min_order_for_earn) {
            return 0;
        }

        $rate = $customRate ?? $this->earn_rate;
        $amount = $orderTotal * ($rate / 100);

        // Округление
        if ($this->earn_rounding > 1) {
            $amount = round($amount / $this->earn_rounding) * $this->earn_rounding;
        } else {
            $amount = round($amount);
        }

        return (int) $amount;
    }

    /**
     * Рассчитать максимум бонусов для списания
     */
    public function calculateMaxSpend(float $orderTotal, int $customerBalance): int
    {
        if (!$this->is_enabled) {
            return 0;
        }

        // Максимум по проценту от заказа
        $maxByPercent = $orderTotal * ($this->spend_rate / 100);

        // Минимальная сумма для списания
        if ($orderTotal < $this->min_spend_amount) {
            return 0;
        }

        // Возвращаем минимум из баланса клиента и лимита по заказу
        return (int) min($customerBalance, $maxByPercent);
    }
}
