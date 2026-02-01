<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Traits\BelongsToTenant;

class Promotion extends Model
{
    use SoftDeletes, BelongsToTenant;

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

    // ==================== ПРОВЕРКИ АКТИВНОСТИ ====================

    /**
     * Проверка базовой активности акции (без контекста заказа)
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) return false;

        $now = \App\Helpers\TimeHelper::now($this->restaurant_id ?? 1);

        // Проверка дат
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;

        // Проверка лимита использований
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;

        // Проверка расписания
        if (!$this->checkSchedule($now)) return false;

        return true;
    }

    /**
     * Проверка расписания (дни недели, время)
     * Использует TimeHelper для правильного часового пояса ресторана
     */
    protected function checkSchedule(?Carbon $now = null): bool
    {
        if (empty($this->schedule)) return true;

        // Используем часовой пояс из настроек ресторана
        $now = $now ?? \App\Helpers\TimeHelper::now($this->restaurant_id ?? 1);
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
     * Полная проверка применимости акции к заказу
     */
    public function isApplicableToOrder(array $context): bool
    {
        if (!$this->isCurrentlyActive()) {
            \Log::debug("Promo {$this->name}: failed isCurrentlyActive");
            return false;
        }

        // Контекст: order_type, payment_method, source_channel, customer_id,
        // customer_loyalty_level, is_first_order, is_birthday, zone_id, table_id, order_total, items

        // Проверка типа заказа
        if (!$this->checkOrderType($context['order_type'] ?? null)) {
            \Log::debug("Promo {$this->name}: failed checkOrderType", ['order_type' => $context['order_type'] ?? null, 'promo_order_types' => $this->order_types]);
            return false;
        }

        // Проверка способа оплаты
        if (!$this->checkPaymentMethod($context['payment_method'] ?? null)) {
            \Log::debug("Promo {$this->name}: failed checkPaymentMethod");
            return false;
        }

        // Проверка канала продаж
        if (!$this->checkSourceChannel($context['source_channel'] ?? 'pos')) {
            \Log::debug("Promo {$this->name}: failed checkSourceChannel");
            return false;
        }

        // Проверка первого заказа
        if ($this->is_first_order_only && !($context['is_first_order'] ?? false)) {
            \Log::debug("Promo {$this->name}: failed is_first_order_only check");
            return false;
        }

        // Проверка дня рождения (с учётом диапазона)
        if ($this->is_birthday_only) {
            $customerBirthday = $context['customer_birthday'] ?? null;
            if (!$this->isWithinBirthdayRange($customerBirthday)) {
                \Log::debug("Promo {$this->name}: failed birthday check", ['birthday' => $customerBirthday]);
                return false;
            }
        }

        // Проверка уровня лояльности
        if (!$this->checkLoyaltyLevel($context['customer_loyalty_level'] ?? null)) {
            \Log::debug("Promo {$this->name}: failed checkLoyaltyLevel", ['level' => $context['customer_loyalty_level'] ?? null, 'promo_levels' => $this->loyalty_levels]);
            return false;
        }

        // Проверка исключенных клиентов
        if ($this->isCustomerExcluded($context['customer_id'] ?? null)) {
            \Log::debug("Promo {$this->name}: customer excluded");
            return false;
        }

        // Проверка зоны/зала
        if (!$this->checkZone($context['zone_id'] ?? null)) {
            \Log::debug("Promo {$this->name}: failed checkZone");
            return false;
        }

        // Проверка стола
        if (!$this->checkTable($context['table_id'] ?? null)) {
            \Log::debug("Promo {$this->name}: failed checkTable");
            return false;
        }

        // Проверка минимальной суммы
        $orderTotal = $context['order_total'] ?? 0;
        if ($this->min_order_amount > 0 && $orderTotal < $this->min_order_amount) {
            \Log::debug("Promo {$this->name}: failed min_order_amount", ['total' => $orderTotal, 'min' => $this->min_order_amount]);
            return false;
        }

        // Проверка минимального количества позиций
        $itemsCount = 0;
        foreach ($context['items'] ?? [] as $item) {
            $itemsCount += $item['quantity'] ?? 1;
        }
        if ($this->min_items_count > 0 && $itemsCount < $this->min_items_count) {
            \Log::debug("Promo {$this->name}: failed min_items_count", ['count' => $itemsCount, 'min' => $this->min_items_count]);
            return false;
        }

        // Проверка требования промокода
        if ($this->requires_promo_code && empty($context['promo_code'])) {
            \Log::debug("Promo {$this->name}: requires promo code");
            return false;
        }

        // Проверка комбо-акции: требуются ВСЕ указанные товары
        if ($this->requires_all_dishes && !empty($this->applicable_dishes)) {
            $orderDishIds = [];
            foreach ($context['items'] ?? [] as $item) {
                $dishId = $item['dish_id'] ?? null;
                if ($dishId) {
                    $orderDishIds[] = (int) $dishId;
                }
            }
            $orderDishIds = array_unique($orderDishIds);

            // Проверяем, что ВСЕ товары из applicable_dishes есть в заказе
            foreach ($this->applicable_dishes as $requiredDishId) {
                if (!in_array((int) $requiredDishId, $orderDishIds)) {
                    \Log::debug("Promo {$this->name}: combo missing dish", [
                        'required' => $requiredDishId,
                        'order_dishes' => $orderDishIds
                    ]);
                    return false;
                }
            }
            \Log::debug("Promo {$this->name}: combo all dishes present");
        }

        \Log::debug("Promo {$this->name}: ALL CHECKS PASSED");
        return true;
    }

    /**
     * Проверка типа заказа
     */
    protected function checkOrderType(?string $orderType): bool
    {
        if (empty($this->order_types)) return true;
        if (!$orderType) return true;
        return in_array($orderType, $this->order_types);
    }

    /**
     * Проверка способа оплаты
     */
    protected function checkPaymentMethod(?string $paymentMethod): bool
    {
        if (empty($this->payment_methods)) return true;
        if (!$paymentMethod) return true;
        return in_array($paymentMethod, $this->payment_methods);
    }

    /**
     * Проверка канала продаж
     */
    protected function checkSourceChannel(?string $sourceChannel): bool
    {
        if (empty($this->source_channels)) return true;
        if (!$sourceChannel) return true;
        return in_array($sourceChannel, $this->source_channels);
    }

    /**
     * Проверка уровня лояльности
     */
    protected function checkLoyaltyLevel(?int $loyaltyLevel): bool
    {
        if (empty($this->loyalty_levels)) return true;
        if (!$loyaltyLevel) return false;
        return in_array($loyaltyLevel, $this->loyalty_levels);
    }

    /**
     * Проверка исключенного клиента
     */
    protected function isCustomerExcluded(?int $customerId): bool
    {
        if (empty($this->excluded_customers)) return false;
        if (!$customerId) return false;
        return in_array($customerId, $this->excluded_customers);
    }

    /**
     * Проверка зоны/зала
     */
    protected function checkZone(?int $zoneId): bool
    {
        if (empty($this->zones)) return true;
        if (!$zoneId) return true;
        return in_array($zoneId, $this->zones);
    }

    /**
     * Проверка стола
     */
    protected function checkTable(?int $tableId): bool
    {
        if (empty($this->tables_list)) return true;
        if (!$tableId) return true;
        return in_array($tableId, $this->tables_list);
    }

    /**
     * Проверка попадания в диапазон дня рождения
     * Использует TimeHelper для правильного часового пояса ресторана
     *
     * @param string|Carbon|null $birthday Дата рождения клиента
     * @return bool
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

    // ==================== РАСЧЕТ СКИДКИ ====================

    /**
     * Расчет скидки для заказа
     */
    public function calculateDiscount(array $orderItems, float $orderTotal, array $context = []): float
    {
        if (!$this->isApplicableToOrder(array_merge($context, [
            'order_total' => $orderTotal,
            'items' => $orderItems
        ]))) {
            return 0;
        }

        // Фильтрация применимых блюд (с fallback на orderTotal для whole_order)
        $applicableTotal = $this->getApplicableTotal($orderItems, $orderTotal);

        switch ($this->type) {
            case 'discount_percent':
                $discount = $applicableTotal * ($this->discount_value / 100);
                if ($this->max_discount && $discount > $this->max_discount) {
                    $discount = $this->max_discount;
                }
                return round($discount, 2);

            case 'discount_fixed':
                return min($this->discount_value, $applicableTotal);

            case 'progressive_discount':
                return $this->calculateProgressiveDiscount($applicableTotal);

            case 'free_delivery':
                // Обрабатывается отдельно
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Расчет суммы к которой применяется скидка
     *
     * @param array $orderItems Позиции заказа
     * @param float|null $orderTotal Общая сумма заказа (fallback для whole_order)
     */
    public function getApplicableTotal(array $orderItems, ?float $orderTotal = null): float
    {
        // Для whole_order без исключений можно использовать переданную сумму если items пустой
        $hasExclusions = !empty($this->excluded_dishes) || !empty($this->excluded_categories);
        if ($this->applies_to === 'whole_order' && empty($orderItems) && $orderTotal !== null && !$hasExclusions) {
            return $orderTotal;
        }

        // Если есть исключения или applies_to != whole_order, нужны товары
        if (empty($orderItems)) {
            // Для whole_order без товаров но с исключениями - возвращаем полную сумму
            // (исключения не могут быть применены без списка товаров)
            if ($this->applies_to === 'whole_order' && $orderTotal !== null) {
                return $orderTotal;
            }
            return 0;
        }

        // === КОМБО-ЛОГИКА: скидка только на полные комплекты ===
        if ($this->requires_all_dishes && !empty($this->applicable_dishes) && $this->applies_to === 'dishes') {
            return $this->calculateComboTotal($orderItems);
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

            switch ($this->applies_to) {
                case 'whole_order':
                    // Применимо ко всем (кроме исключённых выше)
                    $applicable = true;
                    break;

                case 'dishes':
                    // Только конкретные товары
                    if (!empty($this->applicable_dishes)) {
                        $applicable = in_array($dishId, $this->applicable_dishes);
                    }
                    break;

                case 'categories':
                    // Только конкретные категории
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

    /**
     * Расчёт суммы для комбо-акции (только полные комплекты)
     * Пример: Пицца ×3 + Напиток ×2 → 2 комплекта (min(3,2))
     */
    protected function calculateComboTotal(array $orderItems): float
    {
        // 1. Группируем товары заказа по dish_id с суммированием количества и средней ценой
        $orderDishData = [];
        foreach ($orderItems as $item) {
            $dishId = (int) ($item['dish_id'] ?? $item['id'] ?? 0);
            if (!$dishId) continue;

            if (!isset($orderDishData[$dishId])) {
                $orderDishData[$dishId] = [
                    'quantity' => 0,
                    'total_price' => 0,
                ];
            }
            $qty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $orderDishData[$dishId]['quantity'] += $qty;
            $orderDishData[$dishId]['total_price'] += $price * $qty;
        }

        // 2. Считаем количество каждого товара из комбо в заказе
        $comboDishQuantities = [];
        foreach ($this->applicable_dishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            $comboDishQuantities[$dishId] = $orderDishData[$dishId]['quantity'] ?? 0;
        }

        // 3. Находим минимум - это количество полных комплектов
        $comboSets = min($comboDishQuantities);

        if ($comboSets <= 0) {
            return 0;
        }

        // 4. Считаем сумму: для каждого товара комбо берём (цена за единицу × количество комплектов)
        $total = 0;
        foreach ($this->applicable_dishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            if (!isset($orderDishData[$dishId]) || $orderDishData[$dishId]['quantity'] <= 0) {
                continue;
            }
            // Средняя цена за единицу
            $avgPrice = $orderDishData[$dishId]['total_price'] / $orderDishData[$dishId]['quantity'];
            $total += $avgPrice * $comboSets;
        }

        return $total;
    }

    /**
     * Расчёт прогрессивной скидки
     */
    public function calculateProgressiveDiscount(float $orderTotal): float
    {
        if (empty($this->progressive_tiers) || !is_array($this->progressive_tiers)) {
            return 0;
        }

        $tiers = collect($this->progressive_tiers)
            ->sortByDesc('min_amount')
            ->values();

        foreach ($tiers as $tier) {
            $minAmount = $tier['min_amount'] ?? 0;
            $discountPercent = $tier['discount_percent'] ?? 0;

            if ($orderTotal >= $minAmount) {
                $discount = $orderTotal * ($discountPercent / 100);

                if ($this->max_discount && $discount > $this->max_discount) {
                    $discount = $this->max_discount;
                }

                return round($discount, 2);
            }
        }

        return 0;
    }

    /**
     * Получить процент скидки для суммы (для отображения)
     */
    public function getProgressiveDiscountPercent(float $orderTotal): ?float
    {
        if (empty($this->progressive_tiers) || !is_array($this->progressive_tiers)) {
            return null;
        }

        $tiers = collect($this->progressive_tiers)
            ->sortByDesc('min_amount')
            ->values();

        foreach ($tiers as $tier) {
            if ($orderTotal >= ($tier['min_amount'] ?? 0)) {
                return $tier['discount_percent'] ?? 0;
            }
        }

        return null;
    }

    // ==================== БОНУСЫ ====================

    /**
     * Расчет бонусов к начислению
     */
    public function calculateBonusEarning(array $orderItems, float $orderTotal): float
    {
        if ($this->reward_type !== 'bonus') return 0;
        if (!$this->isCurrentlyActive()) return 0;

        $settings = $this->bonus_settings ?? [];
        $percent = $settings['earning_percent'] ?? $this->discount_value ?? 0;
        $excludedCategories = $settings['excluded_categories'] ?? [];

        // Фильтруем товары, исключая категории
        $applicableTotal = 0;
        foreach ($orderItems as $item) {
            $categoryId = $item['category_id'] ?? null;
            if ($categoryId && in_array($categoryId, $excludedCategories)) {
                continue;
            }
            $applicableTotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        return round($applicableTotal * ($percent / 100), 2);
    }

    /**
     * Получить задержку активации бонусов (в днях)
     */
    public function getBonusActivationDelay(): int
    {
        $settings = $this->bonus_settings ?? [];
        return $settings['activation_delay'] ?? 0;
    }

    /**
     * Получить срок действия бонусов (в днях, null = бессрочно)
     */
    public function getBonusExpiryDays(): ?int
    {
        $settings = $this->bonus_settings ?? [];
        return $settings['expiry_days'] ?? null;
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

    /**
     * Проверка валидности промокода
     */
    public function checkCodeValidity(?int $customerId = null, float $orderTotal = 0): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'error' => 'Акция неактивна'];
        }

        $now = \App\Helpers\TimeHelper::now($this->restaurant_id ?? 1);

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return ['valid' => false, 'error' => 'Акция ещё не началась'];
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return ['valid' => false, 'error' => 'Срок действия акции истёк'];
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return ['valid' => false, 'error' => 'Лимит использования акции исчерпан'];
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
            if ($this->is_first_order_only) {
                $customer = Customer::find($customerId);
                if ($customer && $customer->orders()->where('payment_status', 'paid')->exists()) {
                    return ['valid' => false, 'error' => 'Акция только для первого заказа'];
                }
            }
        }

        // Проверка расписания
        if (!$this->checkSchedule($now)) {
            return ['valid' => false, 'error' => 'Акция сейчас не действует'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Полная проверка применимости промокода к контексту заказа
     */
    public function isApplicableToContext(array $context): array
    {
        $result = $this->checkCodeValidity(
            $context['customer_id'] ?? null,
            $context['order_total'] ?? 0
        );

        if (!$result['valid']) {
            return $result;
        }

        // Проверка типа заказа
        if (!$this->checkOrderType($context['order_type'] ?? null)) {
            return ['valid' => false, 'error' => 'Акция не действует для этого типа заказа'];
        }

        // Проверка дня рождения
        if ($this->is_birthday_only) {
            if (empty($context['customer_id'])) {
                return ['valid' => false, 'error' => 'Для применения этой акции необходимо привязать клиента'];
            }
            $customerBirthday = $context['customer_birthday'] ?? null;
            if (!$customerBirthday) {
                return ['valid' => false, 'error' => 'У клиента не указана дата рождения'];
            }
            if (!$this->isWithinBirthdayRange($customerBirthday)) {
                return ['valid' => false, 'error' => 'Акция действует только в период дня рождения'];
            }
        }

        // Проверка уровня лояльности
        if (!$this->checkLoyaltyLevel($context['customer_loyalty_level'] ?? null)) {
            return ['valid' => false, 'error' => 'Акция недоступна для вашего уровня лояльности'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Применить акцию (записать использование)
     */
    public function apply(?int $customerId, ?int $orderId, float $discountAmount): void
    {
        PromotionUsage::create([
            'promotion_id' => $this->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
        ]);

        $this->increment('usage_count');
    }

    /**
     * Сгенерировать уникальный код
     */
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
