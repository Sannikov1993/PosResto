<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;
use App\Services\PromotionService;

class Promotion extends Model
{
    use SoftDeletes, BelongsToTenant, BelongsToRestaurant;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($promotion) {
            if (empty($promotion->slug)) {
                $promotion->slug = Str::slug($promotion->name) . '-' . Str::random(6);
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'slug',
        'code',                      // Промокод для активации
        'description',
        'promo_text',
        'internal_notes',
        'image',
        'type',
        'reward_type',
        'activation_type',           // auto, manual, by_code
        'applies_to',
        'discount_value',
        'progressive_tiers',
        'max_discount',
        'min_order_amount',
        'min_items_count',
        'applicable_categories',
        'applicable_dishes',
        'requires_all_dishes',
        'excluded_dishes',
        'excluded_categories',
        'buy_quantity',
        'get_quantity',
        'gift_dish_id',
        'starts_at',
        'ends_at',
        'schedule',
        'conditions',
        'bonus_settings',
        'usage_limit',
        'usage_per_customer',
        'usage_count',
        'order_types',
        'payment_methods',
        'source_channels',
        'stackable',
        'auto_apply',
        'is_exclusive',
        'single_use_with_promotions',
        'priority',
        'is_active',
        'is_public',                 // Публичный промокод
        'is_automatic',
        'is_featured',
        'is_first_order_only',
        'is_birthday_only',
        'birthday_days_before',
        'birthday_days_after',
        'requires_promo_code',
        'loyalty_levels',
        'excluded_customers',
        'allowed_customer_ids',      // Персональные промокоды
        'zones',
        'tables_list',
        'sort_order',
    ];

    protected $casts = [
        'applicable_categories' => 'array',
        'applicable_dishes' => 'array',
        'requires_all_dishes' => 'boolean',
        'excluded_dishes' => 'array',
        'excluded_categories' => 'array',
        'progressive_tiers' => 'array',
        'schedule' => 'array',
        'conditions' => 'array',
        'bonus_settings' => 'array',
        'order_types' => 'array',
        'payment_methods' => 'array',
        'source_channels' => 'array',
        'loyalty_levels' => 'array',
        'excluded_customers' => 'array',
        'allowed_customer_ids' => 'array',
        'zones' => 'array',
        'tables_list' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'stackable' => 'boolean',
        'auto_apply' => 'boolean',
        'is_exclusive' => 'boolean',
        'single_use_with_promotions' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_automatic' => 'boolean',
        'is_featured' => 'boolean',
        'is_first_order_only' => 'boolean',
        'is_birthday_only' => 'boolean',
        'birthday_days_before' => 'integer',
        'birthday_days_after' => 'integer',
        'requires_promo_code' => 'boolean',
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'reward_type' => 'discount',
        'applies_to' => 'whole_order',
        'auto_apply' => true,
        'is_exclusive' => false,
        'stackable' => true,
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function giftDish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'gift_dish_id');
    }

    // ==================== ДЕЛЕГАТЫ К PromotionService ====================

    public function isCurrentlyActive(): bool
    {
        return app(PromotionService::class)->isCurrentlyActive($this);
    }

    protected function checkSchedule(?Carbon $now = null): bool
    {
        return app(PromotionService::class)->checkSchedule($this, $now);
    }

    public function isApplicableToOrder(array $context): bool
    {
        return app(PromotionService::class)->isApplicableToOrder($this, $context);
    }

    public function isWithinBirthdayRange($birthday): bool
    {
        return app(PromotionService::class)->isWithinBirthdayRange($this, $birthday);
    }

    public function getBirthdayRangeDescription(): ?string
    {
        return app(PromotionService::class)->getBirthdayRangeDescription($this);
    }

    public function calculateDiscount(array $orderItems, float $orderTotal, array $context = []): float
    {
        return app(PromotionService::class)->calculateDiscount($this, $orderItems, $orderTotal, $context);
    }

    public function getApplicableTotal(array $orderItems, ?float $orderTotal = null): float
    {
        return app(PromotionService::class)->getApplicableTotal($this, $orderItems, $orderTotal);
    }

    public function calculateProgressiveDiscount(float $orderTotal): float
    {
        return app(PromotionService::class)->calculateProgressiveDiscount($this, $orderTotal);
    }

    public function getProgressiveDiscountPercent(float $orderTotal): ?float
    {
        return app(PromotionService::class)->getProgressiveDiscountPercent($this, $orderTotal);
    }

    public function calculateBonusEarning(array $orderItems, float $orderTotal): float
    {
        return app(PromotionService::class)->calculateBonusEarning($this, $orderItems, $orderTotal);
    }

    public function getBonusActivationDelay(): int
    {
        return app(PromotionService::class)->getBonusActivationDelay($this);
    }

    public function getBonusExpiryDays(): ?int
    {
        return app(PromotionService::class)->getBonusExpiryDays($this);
    }

    // ==================== СПРАВОЧНИКИ ====================

    /**
     * Типы акций
     */
    public static function getTypes(): array
    {
        return [
            'discount_percent' => 'Скидка в %',
            'discount_fixed' => 'Фиксированная скидка',
            'progressive_discount' => 'Прогрессивная скидка',
            'buy_x_get_y' => 'Купи X получи Y',
            'free_delivery' => 'Бесплатная доставка',
            'gift' => 'Подарок к заказу',
            'combo' => 'Комбо-предложение',
            'happy_hour' => 'Счастливые часы',
            'first_order' => 'Первый заказ',
            'birthday' => 'День рождения',
            'bonus' => 'Начисление бонусов',
        ];
    }

    /**
     * Типы вознаграждений
     */
    public static function getRewardTypes(): array
    {
        return [
            'discount' => 'Скидка',
            'bonus' => 'Бонусы',
            'gift' => 'Подарок',
            'free_delivery' => 'Бесплатная доставка',
        ];
    }

    /**
     * К чему применяется
     */
    public static function getAppliesTo(): array
    {
        return [
            'whole_order' => 'На весь чек',
            'categories' => 'На категории',
            'dishes' => 'На блюда',
        ];
    }

    /**
     * Типы заказов
     */
    public static function getOrderTypes(): array
    {
        return [
            'dine_in' => 'В зале',
            'delivery' => 'Доставка',
            'pickup' => 'Самовывоз',
        ];
    }

    /**
     * Способы оплаты
     */
    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Наличные',
            'card' => 'Карта',
            'online' => 'Онлайн',
        ];
    }

    /**
     * Каналы продаж
     */
    public static function getSourceChannels(): array
    {
        return [
            'pos' => 'POS-терминал',
            'website' => 'Сайт',
            'app' => 'Приложение',
            'phone' => 'Телефон',
        ];
    }

    /**
     * Дни недели
     */
    public static function getDaysOfWeek(): array
    {
        return [
            0 => 'Воскресенье',
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Получить краткое описание условий
     */
    public function getConditionsSummary(): array
    {
        $summary = [];

        if ($this->min_order_amount) {
            $summary[] = "От " . number_format($this->min_order_amount, 0, '', ' ') . " ₽";
        }

        if ($this->order_types && count($this->order_types) < 3) {
            $types = array_map(fn($t) => self::getOrderTypes()[$t] ?? $t, $this->order_types);
            $summary[] = implode(', ', $types);
        }

        if ($this->is_first_order_only) {
            $summary[] = "Первый заказ";
        }

        if ($this->is_birthday_only) {
            $summary[] = $this->getBirthdayRangeDescription();
        }

        if ($this->schedule && !empty($this->schedule['time_from'])) {
            $summary[] = $this->schedule['time_from'] . '-' . $this->schedule['time_to'];
        }

        if ($this->requires_promo_code || $this->code) {
            $summary[] = "По промокоду";
        }

        return $summary;
    }

    // ==================== ПРОМОКОДЫ ====================

    /**
     * Связь с использованиями промокода
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    /**
     * Найти акцию по коду
     */
    public static function findByCode(string $code, int $restaurantId): ?self
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function checkCodeValidity(?int $customerId = null, float $orderTotal = 0): array
    {
        return app(PromotionService::class)->checkCodeValidity($this, $customerId, $orderTotal);
    }

    public function isApplicableToContext(array $context): array
    {
        return app(PromotionService::class)->isApplicableToContext($this, $context);
    }

    public function apply(?int $customerId, ?int $orderId, float $discountAmount): void
    {
        app(PromotionService::class)->applyUsage($this, $customerId, $orderId, $discountAmount);
    }

    public static function generateCode(int $length = 8): string
    {
        return PromotionService::generateCode($length);
    }

    /**
     * Scope: только акции с промокодом
     */
    public function scopeWithCode($query)
    {
        return $query->whereNotNull('code');
    }

    /**
     * Scope: только автоматические акции
     */
    public function scopeAutomatic($query)
    {
        return $query->where('activation_type', 'auto');
    }

    /**
     * Scope: публичные промокоды
     */
    public function scopePublicCodes($query)
    {
        return $query->where('is_public', true)->whereNotNull('code');
    }

    /**
     * Получить тип активации
     */
    public static function getActivationTypes(): array
    {
        return [
            'auto' => 'Автоматически',
            'manual' => 'Вручную (кассир)',
            'by_code' => 'По промокоду',
        ];
    }
}
