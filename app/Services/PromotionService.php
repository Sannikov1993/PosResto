<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Customer;
use Carbon\Carbon;

class PromotionService
{
    // ==================== ПРОВЕРКИ АКТИВНОСТИ ====================

    /**
     * Проверка базовой активности акции (без контекста заказа)
     */
    public function isCurrentlyActive(Promotion $promotion): bool
    {
        if (!$promotion->is_active) return false;

        $now = \App\Helpers\TimeHelper::now($promotion->restaurant_id);

        if ($promotion->starts_at && $now->lt($promotion->starts_at)) return false;
        if ($promotion->ends_at && $now->gt($promotion->ends_at)) return false;

        if ($promotion->usage_limit && $promotion->usage_count >= $promotion->usage_limit) return false;

        if (!$this->checkSchedule($promotion, $now)) return false;

        return true;
    }

    /**
     * Проверка расписания (дни недели, время)
     */
    public function checkSchedule(Promotion $promotion, ?Carbon $now = null): bool
    {
        if (empty($promotion->schedule)) return true;

        $now = $now ?? \App\Helpers\TimeHelper::now($promotion->restaurant_id);
        $dayOfWeek = $now->dayOfWeek;

        if (!empty($promotion->schedule['days'])) {
            $scheduleDays = array_map('intval', $promotion->schedule['days']);
            if (!in_array($dayOfWeek, $scheduleDays, true)) {
                return false;
            }
        }

        if (!empty($promotion->schedule['time_from']) && !empty($promotion->schedule['time_to'])) {
            $timezone = \App\Helpers\TimeHelper::getTimezone($promotion->restaurant_id);
            $timeFrom = Carbon::parse($promotion->schedule['time_from'], $timezone);
            $timeTo = Carbon::parse($promotion->schedule['time_to'], $timezone);
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
    public function isApplicableToOrder(Promotion $promotion, array $context): bool
    {
        if (!$this->isCurrentlyActive($promotion)) {
            \Log::debug("Promo {$promotion->name}: failed isCurrentlyActive");
            return false;
        }

        if (!$this->checkOrderType($promotion, $context['order_type'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: failed checkOrderType", ['order_type' => $context['order_type'] ?? null, 'promo_order_types' => $promotion->order_types]);
            return false;
        }

        if (!$this->checkPaymentMethod($promotion, $context['payment_method'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: failed checkPaymentMethod");
            return false;
        }

        if (!$this->checkSourceChannel($promotion, $context['source_channel'] ?? 'pos')) {
            \Log::debug("Promo {$promotion->name}: failed checkSourceChannel");
            return false;
        }

        if ($promotion->is_first_order_only && !($context['is_first_order'] ?? false)) {
            \Log::debug("Promo {$promotion->name}: failed is_first_order_only check");
            return false;
        }

        if ($promotion->is_birthday_only) {
            $customerBirthday = $context['customer_birthday'] ?? null;
            if (!$this->isWithinBirthdayRange($promotion, $customerBirthday)) {
                \Log::debug("Promo {$promotion->name}: failed birthday check", ['birthday' => $customerBirthday]);
                return false;
            }
        }

        if (!$this->checkLoyaltyLevel($promotion, $context['customer_loyalty_level'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: failed checkLoyaltyLevel", ['level' => $context['customer_loyalty_level'] ?? null, 'promo_levels' => $promotion->loyalty_levels]);
            return false;
        }

        if ($this->isCustomerExcluded($promotion, $context['customer_id'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: customer excluded");
            return false;
        }

        if (!$this->checkZone($promotion, $context['zone_id'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: failed checkZone");
            return false;
        }

        if (!$this->checkTable($promotion, $context['table_id'] ?? null)) {
            \Log::debug("Promo {$promotion->name}: failed checkTable");
            return false;
        }

        $orderTotal = $context['order_total'] ?? 0;
        if ($promotion->min_order_amount > 0 && $orderTotal < $promotion->min_order_amount) {
            \Log::debug("Promo {$promotion->name}: failed min_order_amount", ['total' => $orderTotal, 'min' => $promotion->min_order_amount]);
            return false;
        }

        $itemsCount = 0;
        foreach ($context['items'] ?? [] as $item) {
            $itemsCount += $item['quantity'] ?? 1;
        }
        if ($promotion->min_items_count > 0 && $itemsCount < $promotion->min_items_count) {
            \Log::debug("Promo {$promotion->name}: failed min_items_count", ['count' => $itemsCount, 'min' => $promotion->min_items_count]);
            return false;
        }

        if ($promotion->requires_promo_code && empty($context['promo_code'])) {
            \Log::debug("Promo {$promotion->name}: requires promo code");
            return false;
        }

        if ($promotion->requires_all_dishes && !empty($promotion->applicable_dishes)) {
            $orderDishIds = [];
            foreach ($context['items'] ?? [] as $item) {
                $dishId = $item['dish_id'] ?? null;
                if ($dishId) {
                    $orderDishIds[] = (int) $dishId;
                }
            }
            $orderDishIds = array_unique($orderDishIds);

            foreach ($promotion->applicable_dishes as $requiredDishId) {
                if (!in_array((int) $requiredDishId, $orderDishIds)) {
                    \Log::debug("Promo {$promotion->name}: combo missing dish", [
                        'required' => $requiredDishId,
                        'order_dishes' => $orderDishIds
                    ]);
                    return false;
                }
            }
            \Log::debug("Promo {$promotion->name}: combo all dishes present");
        }

        \Log::debug("Promo {$promotion->name}: ALL CHECKS PASSED");
        return true;
    }

    public function checkOrderType(Promotion $promotion, ?string $orderType): bool
    {
        if (empty($promotion->order_types)) return true;
        if (!$orderType) return true;
        return in_array($orderType, $promotion->order_types);
    }

    public function checkPaymentMethod(Promotion $promotion, ?string $paymentMethod): bool
    {
        if (empty($promotion->payment_methods)) return true;
        if (!$paymentMethod) return true;
        return in_array($paymentMethod, $promotion->payment_methods);
    }

    public function checkSourceChannel(Promotion $promotion, ?string $sourceChannel): bool
    {
        if (empty($promotion->source_channels)) return true;
        if (!$sourceChannel) return true;
        return in_array($sourceChannel, $promotion->source_channels);
    }

    public function checkLoyaltyLevel(Promotion $promotion, ?int $loyaltyLevel): bool
    {
        if (empty($promotion->loyalty_levels)) return true;
        if (!$loyaltyLevel) return false;
        return in_array($loyaltyLevel, $promotion->loyalty_levels);
    }

    public function isCustomerExcluded(Promotion $promotion, ?int $customerId): bool
    {
        if (empty($promotion->excluded_customers)) return false;
        if (!$customerId) return false;
        return in_array($customerId, $promotion->excluded_customers);
    }

    public function checkZone(Promotion $promotion, ?int $zoneId): bool
    {
        if (empty($promotion->zones)) return true;
        if (!$zoneId) return true;
        return in_array($zoneId, $promotion->zones);
    }

    public function checkTable(Promotion $promotion, ?int $tableId): bool
    {
        if (empty($promotion->tables_list)) return true;
        if (!$tableId) return true;
        return in_array($tableId, $promotion->tables_list);
    }

    /**
     * Проверка попадания в диапазон дня рождения
     */
    public function isWithinBirthdayRange(Promotion $promotion, $birthday): bool
    {
        if (!$birthday) return false;

        $timezone = \App\Helpers\TimeHelper::getTimezone($promotion->restaurant_id);
        $birthday = $birthday instanceof Carbon ? $birthday : Carbon::parse($birthday, $timezone);
        $today = \App\Helpers\TimeHelper::today($promotion->restaurant_id);

        $birthdayThisYear = $birthday->copy()->year($today->year);
        $daysBefore = $promotion->birthday_days_before ?? 0;
        $daysAfter = $promotion->birthday_days_after ?? 0;

        $rangeStart = $birthdayThisYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEnd = $birthdayThisYear->copy()->addDays($daysAfter)->endOfDay();

        if ($today->between($rangeStart, $rangeEnd)) {
            return true;
        }

        $birthdayLastYear = $birthday->copy()->year($today->year - 1);
        $rangeStartLastYear = $birthdayLastYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEndLastYear = $birthdayLastYear->copy()->addDays($daysAfter)->endOfDay();

        if ($today->between($rangeStartLastYear, $rangeEndLastYear)) {
            return true;
        }

        $birthdayNextYear = $birthday->copy()->year($today->year + 1);
        $rangeStartNextYear = $birthdayNextYear->copy()->subDays($daysBefore)->startOfDay();
        $rangeEndNextYear = $birthdayNextYear->copy()->addDays($daysAfter)->endOfDay();

        return $today->between($rangeStartNextYear, $rangeEndNextYear);
    }

    /**
     * Получить описание диапазона дня рождения
     */
    public function getBirthdayRangeDescription(Promotion $promotion): ?string
    {
        if (!$promotion->is_birthday_only) return null;

        $before = $promotion->birthday_days_before ?? 0;
        $after = $promotion->birthday_days_after ?? 0;

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
    public function calculateDiscount(Promotion $promotion, array $orderItems, float $orderTotal, array $context = []): float
    {
        if (!$this->isApplicableToOrder($promotion, array_merge($context, [
            'order_total' => $orderTotal,
            'items' => $orderItems
        ]))) {
            return 0;
        }

        $applicableTotal = $this->getApplicableTotal($promotion, $orderItems, $orderTotal);

        switch ($promotion->type) {
            case 'discount_percent':
                $discount = $applicableTotal * ($promotion->discount_value / 100);
                if ($promotion->max_discount && $discount > $promotion->max_discount) {
                    $discount = $promotion->max_discount;
                }
                return round($discount, 2);

            case 'discount_fixed':
                return min($promotion->discount_value, $applicableTotal);

            case 'progressive_discount':
                return $this->calculateProgressiveDiscount($promotion, $applicableTotal);

            case 'free_delivery':
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Расчет суммы к которой применяется скидка
     */
    public function getApplicableTotal(Promotion $promotion, array $orderItems, ?float $orderTotal = null): float
    {
        $hasExclusions = !empty($promotion->excluded_dishes) || !empty($promotion->excluded_categories);
        if ($promotion->applies_to === 'whole_order' && empty($orderItems) && $orderTotal !== null && !$hasExclusions) {
            return $orderTotal;
        }

        if (empty($orderItems)) {
            if ($promotion->applies_to === 'whole_order' && $orderTotal !== null) {
                return $orderTotal;
            }
            return 0;
        }

        if ($promotion->requires_all_dishes && !empty($promotion->applicable_dishes) && $promotion->applies_to === 'dishes') {
            return $this->calculateComboTotal($promotion, $orderItems);
        }

        $total = 0;

        foreach ($orderItems as $item) {
            $dishId = $item['dish_id'] ?? $item['id'] ?? null;
            $categoryId = $item['category_id'] ?? null;

            if (!empty($promotion->excluded_dishes) && in_array($dishId, $promotion->excluded_dishes)) {
                continue;
            }

            if (!empty($promotion->excluded_categories) && in_array($categoryId, $promotion->excluded_categories)) {
                continue;
            }

            $applicable = false;

            switch ($promotion->applies_to) {
                case 'whole_order':
                    $applicable = true;
                    break;

                case 'dishes':
                    if (!empty($promotion->applicable_dishes)) {
                        $applicable = in_array($dishId, $promotion->applicable_dishes);
                    }
                    break;

                case 'categories':
                    if (!empty($promotion->applicable_categories)) {
                        $applicable = in_array($categoryId, $promotion->applicable_categories);
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
     */
    public function calculateComboTotal(Promotion $promotion, array $orderItems): float
    {
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

        $comboDishQuantities = [];
        foreach ($promotion->applicable_dishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            $comboDishQuantities[$dishId] = $orderDishData[$dishId]['quantity'] ?? 0;
        }

        $comboSets = min($comboDishQuantities);

        if ($comboSets <= 0) {
            return 0;
        }

        $total = 0;
        foreach ($promotion->applicable_dishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            if (!isset($orderDishData[$dishId]) || $orderDishData[$dishId]['quantity'] <= 0) {
                continue;
            }
            $avgPrice = $orderDishData[$dishId]['total_price'] / $orderDishData[$dishId]['quantity'];
            $total += $avgPrice * $comboSets;
        }

        return $total;
    }

    /**
     * Расчёт прогрессивной скидки
     */
    public function calculateProgressiveDiscount(Promotion $promotion, float $orderTotal): float
    {
        if (empty($promotion->progressive_tiers) || !is_array($promotion->progressive_tiers)) {
            return 0;
        }

        $tiers = collect($promotion->progressive_tiers)
            ->sortByDesc('min_amount')
            ->values();

        foreach ($tiers as $tier) {
            $minAmount = $tier['min_amount'] ?? 0;
            $discountPercent = $tier['discount_percent'] ?? 0;

            if ($orderTotal >= $minAmount) {
                $discount = $orderTotal * ($discountPercent / 100);

                if ($promotion->max_discount && $discount > $promotion->max_discount) {
                    $discount = $promotion->max_discount;
                }

                return round($discount, 2);
            }
        }

        return 0;
    }

    /**
     * Получить процент скидки для суммы (для отображения)
     */
    public function getProgressiveDiscountPercent(Promotion $promotion, float $orderTotal): ?float
    {
        if (empty($promotion->progressive_tiers) || !is_array($promotion->progressive_tiers)) {
            return null;
        }

        $tiers = collect($promotion->progressive_tiers)
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
    public function calculateBonusEarning(Promotion $promotion, array $orderItems, float $orderTotal): float
    {
        if ($promotion->reward_type !== 'bonus') return 0;
        if (!$this->isCurrentlyActive($promotion)) return 0;

        $settings = $promotion->bonus_settings ?? [];
        $percent = $settings['earning_percent'] ?? $promotion->discount_value ?? 0;
        $excludedCategories = $settings['excluded_categories'] ?? [];

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
    public function getBonusActivationDelay(Promotion $promotion): int
    {
        $settings = $promotion->bonus_settings ?? [];
        return $settings['activation_delay'] ?? 0;
    }

    /**
     * Получить срок действия бонусов (в днях, null = бессрочно)
     */
    public function getBonusExpiryDays(Promotion $promotion): ?int
    {
        $settings = $promotion->bonus_settings ?? [];
        return $settings['expiry_days'] ?? null;
    }

    // ==================== ПРОМОКОДЫ ====================

    /**
     * Проверка валидности промокода
     */
    public function checkCodeValidity(Promotion $promotion, ?int $customerId = null, float $orderTotal = 0): array
    {
        if (!$promotion->is_active) {
            return ['valid' => false, 'error' => 'Акция неактивна'];
        }

        $now = \App\Helpers\TimeHelper::now($promotion->restaurant_id);

        if ($promotion->starts_at && $now->lt($promotion->starts_at)) {
            return ['valid' => false, 'error' => 'Акция ещё не началась'];
        }

        if ($promotion->ends_at && $now->gt($promotion->ends_at)) {
            return ['valid' => false, 'error' => 'Срок действия акции истёк'];
        }

        if ($promotion->usage_limit && $promotion->usage_count >= $promotion->usage_limit) {
            return ['valid' => false, 'error' => 'Лимит использования акции исчерпан'];
        }

        if ($orderTotal > 0 && $promotion->min_order_amount && $orderTotal < $promotion->min_order_amount) {
            return ['valid' => false, 'error' => "Минимальная сумма заказа: " . number_format($promotion->min_order_amount, 0) . " ₽"];
        }

        if ($customerId) {
            if ($promotion->allowed_customer_ids && !in_array($customerId, $promotion->allowed_customer_ids)) {
                return ['valid' => false, 'error' => 'Промокод недоступен для вашего аккаунта'];
            }

            if ($promotion->usage_per_customer) {
                $customerUsages = $promotion->usages()->where('customer_id', $customerId)->count();
                if ($customerUsages >= $promotion->usage_per_customer) {
                    return ['valid' => false, 'error' => 'Вы уже использовали этот промокод'];
                }
            }

            if ($promotion->is_first_order_only) {
                $customer = Customer::find($customerId);
                if ($customer && $customer->orders()->where('payment_status', 'paid')->exists()) {
                    return ['valid' => false, 'error' => 'Акция только для первого заказа'];
                }
            }
        }

        if (!$this->checkSchedule($promotion, $now)) {
            return ['valid' => false, 'error' => 'Акция сейчас не действует'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Полная проверка применимости промокода к контексту заказа
     */
    public function isApplicableToContext(Promotion $promotion, array $context): array
    {
        $result = $this->checkCodeValidity(
            $promotion,
            $context['customer_id'] ?? null,
            $context['order_total'] ?? 0
        );

        if (!$result['valid']) {
            return $result;
        }

        if (!$this->checkOrderType($promotion, $context['order_type'] ?? null)) {
            return ['valid' => false, 'error' => 'Акция не действует для этого типа заказа'];
        }

        if ($promotion->is_birthday_only) {
            if (empty($context['customer_id'])) {
                return ['valid' => false, 'error' => 'Для применения этой акции необходимо привязать клиента'];
            }
            $customerBirthday = $context['customer_birthday'] ?? null;
            if (!$customerBirthday) {
                return ['valid' => false, 'error' => 'У клиента не указана дата рождения'];
            }
            if (!$this->isWithinBirthdayRange($promotion, $customerBirthday)) {
                return ['valid' => false, 'error' => 'Акция действует только в период дня рождения'];
            }
        }

        if (!$this->checkLoyaltyLevel($promotion, $context['customer_loyalty_level'] ?? null)) {
            return ['valid' => false, 'error' => 'Акция недоступна для вашего уровня лояльности'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Применить акцию (записать использование)
     */
    public function applyUsage(Promotion $promotion, ?int $customerId, ?int $orderId, float $discountAmount): void
    {
        PromotionUsage::create([
            'promotion_id' => $promotion->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
        ]);

        $promotion->increment('usage_count');
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
        } while (Promotion::where('code', $code)->exists());

        return $code;
    }
}
