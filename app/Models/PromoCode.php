<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'promotion_id',
        'code',
        'name',
        'description',
        'internal_notes',
        'type',
        'applies_to',
        'value',
        'max_discount',
        'min_order_amount',
        'applicable_categories',
        'applicable_dishes',
        'excluded_dishes',
        'excluded_categories',
        'order_types',
        'payment_methods',
        'source_channels',
        'usage_limit',
        'usage_per_customer',
        'usage_count',
        'first_order_only',
        'is_birthday_only',
        'birthday_days_before',
        'birthday_days_after',
        'loyalty_levels',
        'zones',
        'tables_list',
        'schedule',
        'stackable',
        'priority',
        'is_exclusive',
        'single_use_with_promotions',
        'gift_dish_id',
        'bonus_settings',
        'allowed_customer_ids',
        'starts_at',
        'expires_at',
        'is_active',
        'is_public',
        'is_automatic',
    ];

    protected $casts = [
        'allowed_customer_ids' => 'array',
        'applicable_categories' => 'array',
        'applicable_dishes' => 'array',
        'excluded_dishes' => 'array',
        'excluded_categories' => 'array',
        'order_types' => 'array',
        'payment_methods' => 'array',
        'source_channels' => 'array',
        'loyalty_levels' => 'array',
        'zones' => 'array',
        'tables_list' => 'array',
        'schedule' => 'array',
        'bonus_settings' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'first_order_only' => 'boolean',
        'is_birthday_only' => 'boolean',
        'stackable' => 'boolean',
        'is_exclusive' => 'boolean',
        'single_use_with_promotions' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_automatic' => 'boolean',
        'birthday_days_before' => 'integer',
        'birthday_days_after' => 'integer',
        'priority' => 'integer',
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    protected $appends = ['type_label', 'is_valid', 'formatted_value', 'status', 'frontend_type'];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function giftDish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'gift_dish_id');
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->frontend_type] ?? $this->type;
    }

    /**
     * Возвращает тип промокода в формате фронтенда.
     * БД хранит: percent, fixed, bonus
     * Фронтенд ожидает: discount_percent, discount_fixed, gift, free_delivery, etc.
     */
    public function getFrontendTypeAttribute(): string
    {
        // Если есть gift_dish_id — это подарок
        if ($this->gift_dish_id) {
            return 'gift';
        }

        // Если сохранён оригинальный тип в bonus_settings - используем его
        if (!empty($this->bonus_settings['frontend_type'])) {
            return $this->bonus_settings['frontend_type'];
        }

        // Маппинг из БД формата в фронтенд формат
        $typeMap = [
            'percent' => 'discount_percent',
            'fixed' => 'discount_fixed',
            'bonus' => 'bonus_add',
        ];

        return $typeMap[$this->type] ?? $this->type;
    }

    public function getIsValidAttribute(): bool
    {
        return $this->checkValidity()['valid'];
    }

    public function getFormattedValueAttribute(): string
    {
        // Убираем лишние нули после запятой
        $formattedValue = rtrim(rtrim(number_format($this->value, 2, '.', ''), '0'), '.');

        switch ($this->type) {
            case 'percent': // Для совместимости с CHECK constraint в БД
            case 'discount_percent':
            case 'bonus_multiply':
                return $formattedValue . '%';
            default:
                return number_format($this->value, 0) . ' ₽';
        }
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) return 'inactive';

        $now = Carbon::now();
        if ($this->starts_at && $now->lt($this->starts_at)) return 'scheduled';
        if ($this->expires_at && $now->gt($this->expires_at)) return 'expired';
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return 'exhausted';

        return 'active';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $now = Carbon::now();
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // Methods
    public static function findByCode(string $code, int $restaurantId = 1): ?self
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function checkValidity(?int $customerId = null, float $orderTotal = 0): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'error' => 'Промокод неактивен'];
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return ['valid' => false, 'error' => 'Промокод ещё не активен'];
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return ['valid' => false, 'error' => 'Срок действия промокода истёк'];
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return ['valid' => false, 'error' => 'Лимит использования промокода исчерпан'];
        }

        if ($orderTotal > 0 && $this->min_order_amount && $orderTotal < $this->min_order_amount) {
            return ['valid' => false, 'error' => "Минимальная сумма заказа: " . number_format($this->min_order_amount, 0) . " ₽"];
        }

        if ($customerId) {
            // Персональный промокод
            if ($this->allowed_customer_ids && !in_array($customerId, $this->allowed_customer_ids)) {
                return ['valid' => false, 'error' => 'Промокод недоступен для вашего аккаунта'];
            }

            // Лимит на клиента
            if ($this->usage_per_customer) {
                $customerUsages = $this->usages()->where('customer_id', $customerId)->count();
                if ($customerUsages >= $this->usage_per_customer) {
                    return ['valid' => false, 'error' => 'Вы уже использовали этот промокод'];
                }
            }

            // Только для первого заказа
            if ($this->first_order_only) {
                $customer = Customer::find($customerId);
                if ($customer && $customer->total_orders > 0) {
                    return ['valid' => false, 'error' => 'Промокод только для первого заказа'];
                }
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Рассчитать скидку по промокоду
     *
     * @param float $orderTotal Полная сумма заказа (fallback)
     * @param array $orderItems Товары заказа (для расчёта по категориям/товарам)
     */
    public function calculateDiscount(float $orderTotal, array $orderItems = []): float
    {
        // Если это подарок (gift) - скидка не применяется, только товар добавляется
        if ($this->gift_dish_id) {
            return 0;
        }

        // Рассчитываем сумму к которой применяется скидка
        $applicableTotal = $this->getApplicableTotal($orderItems, $orderTotal);

        if ($applicableTotal <= 0) {
            return 0;
        }

        switch ($this->type) {
            case 'discount_percent':
            case 'percent': // Для совместимости с CHECK constraint в БД
                $discount = $applicableTotal * ($this->value / 100);
                if ($this->max_discount && $discount > $this->max_discount) {
                    $discount = $this->max_discount;
                }
                return round($discount, 2);

            case 'discount_fixed':
            case 'fixed': // Для совместимости с CHECK constraint в БД
                return min($this->value, $applicableTotal);

            default:
                return 0;
        }
    }

    /**
     * Рассчитать сумму к которой применяется скидка
     */
    protected function getApplicableTotal(array $orderItems, float $orderTotal): float
    {
        // Для whole_order без исключений можно использовать полную сумму
        $hasExclusions = !empty($this->excluded_dishes) || !empty($this->excluded_categories);
        $appliesTo = $this->applies_to ?? 'whole_order';

        if ($appliesTo === 'whole_order' && empty($orderItems) && !$hasExclusions) {
            return $orderTotal;
        }

        // Если нет товаров - возвращаем полную сумму для whole_order
        if (empty($orderItems)) {
            if ($appliesTo === 'whole_order') {
                return $orderTotal;
            }
            return 0;
        }

        $total = 0;

        foreach ($orderItems as $item) {
            $dishId = $item['dish_id'] ?? $item['id'] ?? null;
            $categoryId = $item['category_id'] ?? null;

            // Проверка исключений по товарам
            if (!empty($this->excluded_dishes) && in_array($dishId, $this->excluded_dishes)) {
                continue;
            }

            // Проверка исключений по категориям
            if (!empty($this->excluded_categories) && in_array($categoryId, $this->excluded_categories)) {
                continue;
            }

            // Проверка применимости
            $applicable = false;

            switch ($appliesTo) {
                case 'whole_order':
                    $applicable = true;
                    break;

                case 'dishes':
                    if (!empty($this->applicable_dishes)) {
                        $applicable = in_array($dishId, $this->applicable_dishes);
                    }
                    break;

                case 'categories':
                    if (!empty($this->applicable_categories)) {
                        $applicable = in_array($categoryId, $this->applicable_categories);
                    }
                    break;

                default:
                    $applicable = true;
            }

            if ($applicable) {
                $price = $item['price'] ?? 0;
                $quantity = $item['quantity'] ?? 1;
                $total += $price * $quantity;
            }
        }

        return $total;
    }

    public function apply(?int $customerId, ?int $orderId, float $discountAmount): void
    {
        PromoCodeUsage::create([
            'promo_code_id' => $this->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
        ]);

        $this->increment('usage_count');
    }

    public static function getTypes(): array
    {
        return [
            'discount_percent' => 'Скидка в %',
            'discount_fixed' => 'Фиксированная скидка',
            'free_delivery' => 'Бесплатная доставка',
            'gift' => 'Подарок',
            'bonus_multiply' => 'Множитель бонусов',
            'bonus_add' => 'Бонусы за заказ',
        ];
    }

    public static function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    // ==================== ПРОВЕРКИ УСЛОВИЙ ====================

    /**
     * Полная проверка применимости промокода к контексту заказа
     */
    public function isApplicableToContext(array $context): array
    {
        $result = $this->checkValidity(
            $context['customer_id'] ?? null,
            $context['order_total'] ?? 0
        );

        if (!$result['valid']) {
            return $result;
        }

        // Проверка типа заказа (В зале / Доставка / Самовывоз)
        if (!$this->checkOrderType($context['order_type'] ?? null)) {
            return ['valid' => false, 'error' => 'Промокод не действует для этого типа заказа'];
        }

        // Проверка дня рождения
        if ($this->is_birthday_only) {
            if (empty($context['customer_id'])) {
                return ['valid' => false, 'error' => 'Для применения этого промокода необходимо привязать клиента'];
            }
            $customerBirthday = $context['customer_birthday'] ?? null;
            if (!$customerBirthday) {
                return ['valid' => false, 'error' => 'У клиента не указана дата рождения'];
            }
            if (!$this->isWithinBirthdayRange($customerBirthday)) {
                return ['valid' => false, 'error' => 'Промокод действует только в период дня рождения'];
            }
        }

        // Проверка уровня лояльности
        if (!$this->checkLoyaltyLevel($context['customer_loyalty_level'] ?? null)) {
            return ['valid' => false, 'error' => 'Промокод недоступен для вашего уровня лояльности'];
        }

        // Проверка расписания (дни недели и время)
        if (!$this->checkSchedule()) {
            return ['valid' => false, 'error' => 'Промокод сейчас не действует'];
        }

        return ['valid' => true, 'error' => null];
    }

    protected function checkOrderType(?string $orderType): bool
    {
        if (empty($this->order_types)) return true;
        if (!$orderType) return true;
        return in_array($orderType, $this->order_types);
    }

    protected function checkLoyaltyLevel(?int $loyaltyLevel): bool
    {
        if (empty($this->loyalty_levels)) return true;
        if (!$loyaltyLevel) return true;
        return in_array($loyaltyLevel, $this->loyalty_levels);
    }

    protected function checkSchedule(): bool
    {
        if (empty($this->schedule)) return true;

        // Используем часовой пояс из настроек ресторана
        $now = \App\Helpers\TimeHelper::now($this->restaurant_id ?? 1);
        $dayOfWeek = $now->dayOfWeek; // 0=воскресенье, 1=понедельник... 6=суббота

        // Проверка дня недели (days хранит индексы: 0=Вс, 1=Пн... 6=Сб)
        if (!empty($this->schedule['days'])) {
            // Приводим к int для корректного сравнения
            $scheduleDays = array_map('intval', $this->schedule['days']);
            if (!in_array($dayOfWeek, $scheduleDays, true)) {
                return false;
            }
        }

        // Проверка времени
        if (!empty($this->schedule['time_from']) && !empty($this->schedule['time_to'])) {
            $timezone = \App\Helpers\TimeHelper::getTimezone($this->restaurant_id ?? 1);
            $timeFrom = Carbon::parse($this->schedule['time_from'], $timezone);
            $timeTo = Carbon::parse($this->schedule['time_to'], $timezone);
            $currentTime = Carbon::parse($now->format('H:i'), $timezone);

            if (!$currentTime->between($timeFrom, $timeTo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверка попадания в диапазон дня рождения
     */
    public function isWithinBirthdayRange($birthday): bool
    {
        if (!$birthday) return false;

        $timezone = \App\Helpers\TimeHelper::getTimezone($this->restaurant_id ?? 1);
        $birthday = $birthday instanceof Carbon ? $birthday : Carbon::parse($birthday, $timezone);
        $today = \App\Helpers\TimeHelper::today($this->restaurant_id ?? 1);

        $birthdayThisYear = $birthday->copy()->year($today->year);
        $daysBefore = $this->birthday_days_before ?? 0;
        $daysAfter = $this->birthday_days_after ?? 0;

        $rangeStart = $birthdayThisYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEnd = $birthdayThisYear->copy()->addDays($daysAfter)->endOfDay();

        if ($today->between($rangeStart, $rangeEnd)) {
            return true;
        }

        // Проверка прошлого года
        $birthdayLastYear = $birthday->copy()->year($today->year - 1);
        $rangeStartLastYear = $birthdayLastYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEndLastYear = $birthdayLastYear->copy()->addDays($daysAfter)->endOfDay();

        if ($today->between($rangeStartLastYear, $rangeEndLastYear)) {
            return true;
        }

        // Проверка следующего года
        $birthdayNextYear = $birthday->copy()->year($today->year + 1);
        $rangeStartNextYear = $birthdayNextYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEndNextYear = $birthdayNextYear->copy()->addDays($daysAfter)->endOfDay();

        return $today->between($rangeStartNextYear, $rangeEndNextYear);
    }

    /**
     * Получить описание диапазона дня рождения
     */
    public function getBirthdayRangeDescription(): ?string
    {
        if (!$this->is_birthday_only) return null;

        $before = $this->birthday_days_before ?? 0;
        $after = $this->birthday_days_after ?? 0;

        if ($before == 0 && $after == 0) {
            return 'Только в день рождения';
        }

        $parts = [];
        if ($before > 0) {
            $parts[] = "{$before} дн. до";
        }
        $parts[] = "ДР";
        if ($after > 0) {
            $parts[] = "{$after} дн. после";
        }

        return implode(' ', $parts);
    }

    /**
     * Получить описание условий
     */
    public function getConditionsSummary(): array
    {
        $summary = [];

        if ($this->min_order_amount > 0) {
            $summary[] = "от " . number_format($this->min_order_amount, 0) . " ₽";
        }

        if (!empty($this->order_types)) {
            $labels = ['dine_in' => 'В зале', 'delivery' => 'Доставка', 'pickup' => 'Самовывоз'];
            $types = array_map(fn($t) => $labels[$t] ?? $t, $this->order_types);
            $summary[] = implode(', ', $types);
        }

        if ($this->first_order_only) {
            $summary[] = "Первый заказ";
        }

        if ($this->is_birthday_only) {
            $summary[] = $this->getBirthdayRangeDescription();
        }

        if (!empty($this->schedule['time_from'])) {
            $summary[] = $this->schedule['time_from'] . '-' . $this->schedule['time_to'];
        }

        return $summary;
    }

    // ==================== СТАТИЧЕСКИЕ СПРАВОЧНИКИ ====================

    public static function getAppliesTo(): array
    {
        return [
            'whole_order' => 'На весь заказ',
            'categories' => 'На категории',
            'dishes' => 'На блюда',
        ];
    }

    public static function getOrderTypes(): array
    {
        return [
            'dine_in' => 'В зале',
            'delivery' => 'Доставка',
            'pickup' => 'Самовывоз',
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Наличные',
            'card' => 'Карта',
            'online' => 'Онлайн',
            'bonus' => 'Бонусы',
        ];
    }

    public static function getSourceChannels(): array
    {
        return [
            'pos' => 'POS-терминал',
            'website' => 'Сайт',
            'app' => 'Приложение',
            'aggregator' => 'Агрегатор',
            'phone' => 'Телефон',
        ];
    }
}
