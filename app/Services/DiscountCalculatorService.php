<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\Customer;
use App\Models\Dish;
use App\Models\LoyaltySetting;
use App\Services\BonusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Единый сервис расчёта скидок
 * Используется для: зал, доставка, самовывоз, API, Order::recalculateTotal()
 */
class DiscountCalculatorService
{
    protected int $restaurantId;

    public function __construct(?int $restaurantId = null)
    {
        // Если не передан — берём из TenantManager (установленного middleware)
        $this->restaurantId = $restaurantId ?? tenant_id();
    }

    /**
     * Рассчитать все скидки для заказа (API)
     */
    public function calculate(array $params): array
    {
        $items = $params['items'] ?? [];
        $subtotal = $params['subtotal'] ?? $this->calculateSubtotal($items);
        $orderType = $params['order_type'] ?? 'dine_in';
        $customerId = $params['customer_id'] ?? null;
        $promoCode = $params['promo_code'] ?? null;
        $zoneId = $params['zone_id'] ?? null;
        $tableId = $params['table_id'] ?? null;

        $customer = $customerId ? Customer::forRestaurant($this->restaurantId)->with('loyaltyLevel')->find($customerId) : null;

        $discounts = [];
        $appliedDiscounts = [];
        $totalDiscount = 0;
        $bonusEarned = 0;
        $freeDelivery = false;
        $giftItems = [];

        // Контекст для проверки акций
        $context = [
            'order_type' => $orderType,
            'source_channel' => 'pos',
            'customer_id' => $customer?->id,
            'customer_loyalty_level' => $customer?->loyalty_level_id,
            'customer_birthday' => $customer?->birth_date,
            'is_first_order' => $customer ? $customer->total_orders == 0 : false,
            'zone_id' => $zoneId,
            'table_id' => $tableId,
            'order_total' => $subtotal,
            'items' => $items,
        ];

        // 1. Скидка по уровню лояльности
        if ($customer && $customer->loyaltyLevel && $customer->loyaltyLevel->discount_percent > 0) {
            $levelDiscount = round($subtotal * $customer->loyaltyLevel->discount_percent / 100, 2);
            $discounts[] = [
                'type' => 'level',
                'name' => "Скидка {$customer->loyaltyLevel->name}",
                'percent' => $customer->loyaltyLevel->discount_percent,
                'amount' => $levelDiscount,
            ];
            $appliedDiscounts[] = [
                'name' => "Скидка {$customer->loyaltyLevel->name}",
                'type' => 'level',
                'sourceType' => 'level',
                'sourceId' => $customer->loyaltyLevel->id,
                'amount' => $levelDiscount,
                'percent' => $customer->loyaltyLevel->discount_percent,
                'auto' => true,
                'stackable' => true,
            ];
            $totalDiscount += $levelDiscount;
        }

        // 2. Скидка ко дню рождения (через уровень)
        if ($customer && $customer->birth_date && $customer->loyaltyLevel?->birthday_bonus) {
            $birthday = Carbon::parse($customer->birth_date)->setYear(Carbon::now()->year);
            $daysBefore = (int) LoyaltySetting::get('birthday_days_before', 7, $this->restaurantId);
            $daysAfter = (int) LoyaltySetting::get('birthday_days_after', 7, $this->restaurantId);

            $periodStart = $birthday->copy()->subDays($daysBefore);
            $periodEnd = $birthday->copy()->addDays($daysAfter);

            if (Carbon::today()->between($periodStart, $periodEnd) && !$customer->birthday_used_this_year) {
                $birthdayDiscount = round($subtotal * $customer->loyaltyLevel->birthday_discount / 100, 2);
                $discounts[] = [
                    'type' => 'birthday',
                    'name' => '🎂 Скидка ко дню рождения',
                    'percent' => $customer->loyaltyLevel->birthday_discount,
                    'amount' => $birthdayDiscount,
                ];
                $appliedDiscounts[] = [
                    'name' => 'Скидка ко дню рождения',
                    'type' => 'birthday',
                    'sourceType' => 'birthday',
                    'amount' => $birthdayDiscount,
                    'percent' => $customer->loyaltyLevel->birthday_discount,
                    'auto' => true,
                    'stackable' => true,
                ];
                $totalDiscount += $birthdayDiscount;
            }
        }

        // 3. Автоматические акции
        $remainingTotal = $subtotal - $totalDiscount;
        $hasExclusivePromo = false;

        $automaticPromotions = Promotion::where('restaurant_id', $this->restaurantId)
            ->where('is_active', true)
            ->where('is_automatic', true)
            ->where('requires_promo_code', false)
            ->where(function ($q) {
                $now = Carbon::now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = Carbon::now();
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('sort_order')
            ->get();

        foreach ($automaticPromotions as $promo) {
            if ($hasExclusivePromo && !$promo->stackable) {
                continue;
            }

            if (!$promo->isApplicableToOrder($context)) {
                continue;
            }

            $promoResult = $this->calculatePromotionDiscount($promo, $items, $remainingTotal, $context);

            if ($promoResult['discount'] > 0 || $promoResult['type'] === 'free_delivery' || $promoResult['type'] === 'gift') {
                $discounts[] = $promoResult['display'];
                $appliedDiscounts[] = $promoResult['applied'];
                $totalDiscount += $promoResult['discount'];
                $remainingTotal = max(0, $subtotal - $totalDiscount);

                if ($promoResult['type'] === 'free_delivery') {
                    $freeDelivery = true;
                }

                if (!empty($promoResult['gift_dish'])) {
                    $giftItems[] = $promoResult['gift_dish'];
                }

                if ($promo->is_exclusive) {
                    $hasExclusivePromo = true;
                }

                if (!$promo->stackable) {
                    break;
                }
            }
        }

        // 4. Промокод
        if ($promoCode) {
            $promoCodeResult = $this->applyPromoCode($promoCode, $items, $remainingTotal, $context, $customer);
            if ($promoCodeResult) {
                $discounts[] = $promoCodeResult['display'];
                $appliedDiscounts[] = $promoCodeResult['applied'];
                $totalDiscount += $promoCodeResult['discount'];

                if (!empty($promoCodeResult['gift_dish'])) {
                    $giftItems[] = $promoCodeResult['gift_dish'];
                }
            }
        }

        // 5. Расчёт бонусов к начислению через BonusService
        $finalTotal = max(0, $subtotal - $totalDiscount);
        $bonusMultiplier = 1;

        foreach ($discounts as $d) {
            if (!empty($d['bonus_multiplier'])) {
                $bonusMultiplier = max($bonusMultiplier, $d['bonus_multiplier']);
            }
        }

        if ($customer) {
            $bonusService = new BonusService($this->restaurantId);
            $earning = $bonusService->calculateEarning($finalTotal, $customer, $bonusMultiplier);
            $bonusEarned = $earning['amount'];
        }

        return [
            'order_total' => $subtotal,
            'discounts' => $discounts,
            'applied_discounts' => $appliedDiscounts,
            'total_discount' => round($totalDiscount, 2),
            'final_total' => round($finalTotal, 2),
            'bonus_earned' => $bonusEarned,
            'free_delivery' => $freeDelivery,
            'gift_items' => $giftItems,
            'applied_promotions' => collect($appliedDiscounts)
                ->filter(fn($d) => ($d['sourceType'] ?? '') === 'promotion')
                ->pluck('sourceId')
                ->toArray(),
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'level' => $customer->loyaltyLevel?->name,
                'bonus_balance' => $customer->bonus_balance,
            ] : null,
        ];
    }

    /**
     * Пересчитать скидки из сохранённых applied_discounts (для Order::recalculateTotal)
     */
    public function recalculateFromAppliedDiscounts(array $appliedDiscounts, array $orderItems, float $subtotal): array
    {
        $updatedDiscounts = [];
        $totalDiscount = 0;

        // Фильтруем округление и скидку уровня (они пересчитываются отдельно)
        $appliedDiscounts = array_filter($appliedDiscounts, function($d) {
            $type = $d['type'] ?? '';
            $sourceType = $d['sourceType'] ?? '';
            return $type !== 'rounding' && $sourceType !== 'rounding'
                && $type !== 'level' && $sourceType !== 'level';
        });
        $appliedDiscounts = array_values($appliedDiscounts);

        foreach ($appliedDiscounts as $discount) {
            $discountData = $discount;

            // Вычисляем applicableTotal
            $applicableTotal = self::calculateApplicableTotal($orderItems, $discount);

            // Пересчитываем сумму скидки
            $amount = 0;

            if (!empty($discount['percent']) && $discount['percent'] > 0) {
                $amount = round($applicableTotal * $discount['percent'] / 100);

                if (!empty($discount['maxDiscount']) && $amount > $discount['maxDiscount']) {
                    $amount = $discount['maxDiscount'];
                }
            } elseif (!empty($discount['fixedAmount']) && $discount['fixedAmount'] > 0) {
                $amount = min($discount['fixedAmount'], $applicableTotal);
            } elseif (($discount['type'] ?? '') === 'discount_fixed' && ($discount['sourceType'] ?? '') === 'promotion') {
                $promo = Promotion::forRestaurant($this->restaurantId)->find($discount['sourceId'] ?? null);
                if ($promo && $promo->discount_value > 0) {
                    $amount = min($promo->discount_value, $applicableTotal);
                    $discountData['fixedAmount'] = $promo->discount_value;
                } else {
                    $amount = min($discount['amount'] ?? 0, $applicableTotal);
                }
            } elseif (!empty($discount['amount'])) {
                $amount = min($discount['amount'], $applicableTotal);
            }

            $discountData['amount'] = $amount;
            $totalDiscount += $amount;
            $updatedDiscounts[] = $discountData;
        }

        return [
            'discounts' => $updatedDiscounts,
            'total_discount' => $totalDiscount,
        ];
    }

    /**
     * Расчёт суммы товаров к которым применяется скидка
     * СТАТИЧЕСКИЙ метод - можно вызывать без создания экземпляра
     */
    public static function calculateApplicableTotal(array $orderItems, array $discount): float
    {
        $appliesTo = $discount['applies_to'] ?? 'whole_order';
        $applicableCategories = $discount['applicable_categories'] ?? null;
        $applicableDishes = $discount['applicable_dishes'] ?? null;
        $requiresAllDishes = $discount['requires_all_dishes'] ?? false;
        $excludedCategories = $discount['excluded_categories'] ?? null;
        $excludedDishes = $discount['excluded_dishes'] ?? null;

        // Комбо-логика: скидка только на полные комплекты
        if ($requiresAllDishes && !empty($applicableDishes) && $appliesTo === 'dishes') {
            return self::calculateComboTotal($orderItems, $applicableDishes);
        }

        // Если нет настроек фильтрации - возвращаем полную сумму
        $hasFilters = $appliesTo !== 'whole_order' ||
                      !empty($excludedCategories) ||
                      !empty($excludedDishes);

        if (!$hasFilters) {
            return array_sum(array_map(fn($i) => ($i['price'] ?? 0) * ($i['quantity'] ?? 1), $orderItems));
        }

        $total = 0;

        foreach ($orderItems as $item) {
            $dishId = $item['dish_id'] ?? null;
            $categoryId = $item['category_id'] ?? null;

            // Проверка исключений по товарам
            if (!empty($excludedDishes) && in_array($dishId, $excludedDishes)) {
                continue;
            }

            // Проверка исключений по категориям
            if (!empty($excludedCategories) && in_array($categoryId, $excludedCategories)) {
                continue;
            }

            // Проверка применимости
            $applicable = false;

            switch ($appliesTo) {
                case 'whole_order':
                    $applicable = true;
                    break;

                case 'dishes':
                    if (!empty($applicableDishes)) {
                        $applicable = in_array($dishId, $applicableDishes);
                    }
                    break;

                case 'categories':
                    if (!empty($applicableCategories)) {
                        $applicable = in_array($categoryId, $applicableCategories);
                    }
                    break;

                default:
                    $applicable = true;
            }

            if ($applicable) {
                $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            }
        }

        return $total;
    }

    /**
     * Расчёт суммы для комбо-акции (только полные комплекты)
     */
    public static function calculateComboTotal(array $orderItems, array $applicableDishes): float
    {
        // 1. Группируем товары заказа по dish_id
        $orderDishData = [];
        foreach ($orderItems as $item) {
            $dishId = $item['dish_id'] ?? null;
            if (!$dishId) continue;

            if (!isset($orderDishData[$dishId])) {
                $orderDishData[$dishId] = [
                    'quantity' => 0,
                    'total_price' => 0,
                ];
            }
            $orderDishData[$dishId]['quantity'] += $item['quantity'] ?? 1;
            $orderDishData[$dishId]['total_price'] += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        // 2. Считаем количество каждого комбо-товара в заказе
        $comboDishQuantities = [];
        foreach ($applicableDishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            $comboDishQuantities[$dishId] = $orderDishData[$dishId]['quantity'] ?? 0;
        }

        // 3. Минимальное количество = количество полных комплектов
        $comboSets = !empty($comboDishQuantities) ? min($comboDishQuantities) : 0;

        if ($comboSets <= 0) {
            return 0;
        }

        // 4. Считаем сумму только для полных комплектов
        $total = 0;
        foreach ($applicableDishes as $requiredDishId) {
            $dishId = (int) $requiredDishId;
            if (!isset($orderDishData[$dishId]) || $orderDishData[$dishId]['quantity'] <= 0) {
                continue;
            }

            // Средняя цена за единицу товара
            $avgPrice = $orderDishData[$dishId]['total_price'] / $orderDishData[$dishId]['quantity'];
            $total += $avgPrice * $comboSets;
        }

        return $total;
    }

    // ==================== PRIVATE METHODS ====================

    protected function calculateSubtotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $price = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $total += $price * $quantity;
        }
        return $total;
    }

    protected function calculatePromotionDiscount(Promotion $promo, array $items, float $remainingTotal, array $context): array
    {
        $discount = 0;
        $applicableTotal = $promo->getApplicableTotal($items, $remainingTotal);

        $displayData = [
            'type' => 'promotion',
            'promotion_id' => $promo->id,
            'name' => $promo->name,
            'promo_type' => $promo->type,
            'discount_type' => $promo->type,
            'auto' => true,
            'stackable' => $promo->stackable ?? true,
            'applies_to' => $promo->applies_to,
            'applicable_categories' => $promo->applicable_categories,
            'applicable_dishes' => $promo->applicable_dishes,
        ];

        $appliedData = [
            'name' => $promo->name,
            'type' => 'promotion',
            'sourceType' => 'promotion',
            'sourceId' => $promo->id,
            'promoType' => $promo->type,
            'stackable' => $promo->stackable ?? true,
            'auto' => true,
            'applies_to' => $promo->applies_to,
            'applicable_categories' => $promo->applicable_categories,
            'applicable_dishes' => $promo->applicable_dishes,
            'requires_all_dishes' => $promo->requires_all_dishes,
            'excluded_categories' => $promo->excluded_categories,
            'excluded_dishes' => $promo->excluded_dishes,
        ];

        $giftDish = null;

        switch ($promo->type) {
            case 'discount_percent':
                $discount = round($applicableTotal * ($promo->discount_value / 100));
                if ($promo->max_discount && $discount > $promo->max_discount) {
                    $discount = $promo->max_discount;
                }
                $displayData['percent'] = $promo->discount_value;
                $displayData['amount'] = $discount;
                $appliedData['percent'] = $promo->discount_value;
                $appliedData['maxDiscount'] = $promo->max_discount;
                break;

            case 'discount_fixed':
                $discount = min($promo->discount_value, $applicableTotal);
                $displayData['amount'] = $discount;
                $appliedData['fixedAmount'] = $promo->discount_value;
                break;

            case 'progressive_discount':
                $discount = $promo->calculateProgressiveDiscount($applicableTotal);
                $currentPercent = $promo->getProgressiveDiscountPercent($applicableTotal);
                $displayData['percent'] = $currentPercent;
                $displayData['amount'] = $discount;
                $displayData['tiers'] = $promo->progressive_tiers;
                $appliedData['percent'] = $currentPercent;
                break;

            case 'free_delivery':
                $displayData['amount'] = 0;
                $displayData['free_delivery'] = true;
                break;

            case 'gift':
                if ($promo->gift_dish_id) {
                    $dish = $promo->giftDish;
                    if ($dish) {
                        $giftDish = [
                            'dish_id' => $dish->id,
                            'name' => $dish->name,
                            'promotion_id' => $promo->id,
                        ];
                        $displayData['gift_dish'] = [
                            'id' => $dish->id,
                            'name' => $dish->name,
                        ];
                    }
                }
                $displayData['amount'] = 0;
                break;

            case 'bonus_multiplier':
                $displayData['amount'] = 0;
                $displayData['bonus_multiplier'] = $promo->discount_value;
                break;

            default:
                $discount = round($applicableTotal * ($promo->discount_value / 100));
                $displayData['amount'] = $discount;
        }

        $appliedData['amount'] = $discount;

        return [
            'type' => $promo->type,
            'discount' => $discount,
            'display' => $displayData,
            'applied' => $appliedData,
            'gift_dish' => $giftDish,
        ];
    }

    protected function applyPromoCode(string $code, array $items, float $remainingTotal, array $context, ?Customer $customer): ?array
    {
        $code = strtoupper(trim($code));
        $promotion = Promotion::findByCode($code, $this->restaurantId);

        if (!$promotion) {
            return null;
        }

        $validation = $promotion->checkCodeValidity($customer?->id, $remainingTotal);
        if (!$validation['valid']) {
            return null;
        }

        // Добавляем promo_code в контекст для проверки requires_promo_code
        $contextWithPromoCode = array_merge($context, ['promo_code' => $code]);
        $discount = $promotion->calculateDiscount($items, $remainingTotal, $contextWithPromoCode);
        if ($discount <= 0 && $promotion->type !== 'gift' && $promotion->type !== 'bonus') {
            return null;
        }

        $giftDish = null;
        if ($promotion->type === 'gift' && $promotion->gift_dish_id) {
            $dish = Dish::forRestaurant($this->restaurantId)->find($promotion->gift_dish_id);
            if ($dish) {
                $giftDish = [
                    'dish_id' => $dish->id,
                    'name' => $dish->name,
                    'promotion_id' => $promotion->id,
                ];
            }
        }

        $displayData = [
            'type' => 'promo_code',
            'name' => "🎁 Промокод {$code}",
            'code' => $code,
            'amount' => $promotion->type === 'bonus' ? 0 : $discount,
            'bonus' => $promotion->type === 'bonus' ? $discount : 0,
        ];

        $appliedData = [
            'name' => "Промокод {$code}",
            'type' => 'promo_code',
            'sourceType' => 'promotion',
            'sourceId' => $promotion->id,
            'code' => $code,
            'amount' => $promotion->type === 'bonus' ? 0 : $discount,
            'percent' => $promotion->type === 'discount_percent' ? $promotion->discount_value : 0,
            'maxDiscount' => $promotion->max_discount,
            'stackable' => $promotion->stackable ?? true,
            'auto' => false,
        ];

        return [
            'discount' => $promotion->type === 'bonus' ? 0 : $discount,
            'display' => $displayData,
            'applied' => $appliedData,
            'gift_dish' => $giftDish,
        ];
    }
}
