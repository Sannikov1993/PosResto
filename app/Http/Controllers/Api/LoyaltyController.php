<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyLevel;
use App\Models\BonusTransaction;
use App\Models\BonusSetting;
use App\Models\LoyaltySetting;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Customer;
use App\Models\Order;
use App\Services\DiscountCalculatorService;
use App\Services\BonusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LoyaltyController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // ==========================================
    // УРОВНИ ЛОЯЛЬНОСТИ
    // ==========================================

    public function levels(Request $request): JsonResponse
    {
        $levels = LoyaltyLevel::where('restaurant_id', $this->getRestaurantId($request))
            ->withCount('customers')
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }

    public function storeLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'min_total' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'cashback_percent' => 'nullable|numeric|min:0|max:100',
            'bonus_multiplier' => 'nullable|numeric|min:0|max:10',
            'birthday_bonus' => 'nullable|boolean',
            'birthday_discount' => 'nullable|numeric|min:0|max:100',
        ]);

        $level = LoyaltyLevel::create([
            'restaurant_id' => $this->getRestaurantId($request),
            ...$validated,
            'sort_order' => LoyaltyLevel::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Уровень создан',
            'data' => $level,
        ], 201);
    }

    public function updateLevel(Request $request, LoyaltyLevel $level): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'min_total' => 'sometimes|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'cashback_percent' => 'nullable|numeric|min:0|max:100',
            'bonus_multiplier' => 'nullable|numeric|min:0|max:10',
            'birthday_bonus' => 'nullable|boolean',
            'birthday_discount' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $level->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Уровень обновлён',
            'data' => $level,
        ]);
    }

    public function destroyLevel(LoyaltyLevel $level): JsonResponse
    {
        if ($level->customers()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить: есть клиенты с этим уровнем',
            ], 422);
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Уровень удалён',
        ]);
    }

    /**
     * Пересчёт уровней для всех клиентов
     */
    public function recalculateLevels(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customers = Customer::where('restaurant_id', $restaurantId)->get();
        $updated = 0;

        foreach ($customers as $customer) {
            $oldLevel = $customer->loyalty_level_id;
            $customer->updateLoyaltyLevel();

            if ($customer->loyalty_level_id !== $oldLevel) {
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Уровни пересчитаны. Обновлено клиентов: {$updated}",
            'updated' => $updated,
        ]);
    }

    // ==========================================
    // ПРОМОКОДЫ (теперь это Promotion с activation_type = 'by_code')
    // ==========================================

    public function promoCodes(Request $request): JsonResponse
    {
        $query = Promotion::where('restaurant_id', $this->getRestaurantId($request))
            ->whereNotNull('code');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $now = Carbon::now();
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) {
                    $now = Carbon::now();
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->where(function ($q) {
                    $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
                });
        }

        $codes = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    public function storePromoCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'type' => 'required|string',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string',
            'applicable_categories' => 'nullable|array',
            'applicable_dishes' => 'nullable|array',
            'excluded_dishes' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'order_types' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'source_channels' => 'nullable|array',
            'schedule' => 'nullable|array',
            'is_first_order_only' => 'nullable|boolean',
            'is_birthday_only' => 'nullable|boolean',
            'birthday_days_before' => 'nullable|integer|min:0',
            'birthday_days_after' => 'nullable|integer|min:0',
            'loyalty_levels' => 'nullable|array',
            'zones' => 'nullable|array',
            'tables_list' => 'nullable|array',
            'stackable' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_exclusive' => 'nullable|boolean',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_customer' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'gift_dish_id' => 'nullable|exists:dishes,id',
            'allowed_customer_ids' => 'nullable|array',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        // Устанавливаем name = code если не указан
        if (empty($validated['name'])) {
            $validated['name'] = $validated['code'];
        }

        // Промокод = акция с activation_type = 'by_code'
        $validated['activation_type'] = 'by_code';
        $validated['is_automatic'] = false;

        $restaurantId = $this->getRestaurantId($request);
        $slug = Str::slug($validated['name'] . '-' . $validated['code']);

        $promotion = Promotion::create([
            'restaurant_id' => $restaurantId,
            'slug' => $slug,
            'sort_order' => Promotion::max('sort_order') + 1,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Промокод создан',
            'data' => $promotion,
        ], 201);
    }

    public function updatePromoCode(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'type' => 'sometimes|string',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string',
            'applicable_categories' => 'nullable|array',
            'applicable_dishes' => 'nullable|array',
            'excluded_dishes' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'order_types' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'source_channels' => 'nullable|array',
            'schedule' => 'nullable|array',
            'is_first_order_only' => 'nullable|boolean',
            'is_birthday_only' => 'nullable|boolean',
            'birthday_days_before' => 'nullable|integer|min:0',
            'birthday_days_after' => 'nullable|integer|min:0',
            'loyalty_levels' => 'nullable|array',
            'zones' => 'nullable|array',
            'tables_list' => 'nullable|array',
            'stackable' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_exclusive' => 'nullable|boolean',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_customer' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'gift_dish_id' => 'nullable|exists:dishes,id',
            'allowed_customer_ids' => 'nullable|array',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $promotion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Промокод обновлён',
            'data' => $promotion->fresh(),
        ]);
    }

    public function destroyPromoCode(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Промокод удалён',
        ]);
    }

    public function validatePromoCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'customer_id' => 'nullable|integer',
            'order_total' => 'nullable|numeric|min:0',
            'order_type' => 'nullable|string|in:dine_in,delivery,pickup',
            'items' => 'nullable|array',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $promotion = Promotion::findByCode($validated['code'], $restaurantId);

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Промокод не найден',
            ], 404);
        }

        // Собираем контекст для проверки всех условий
        $context = [
            'customer_id' => $validated['customer_id'] ?? null,
            'order_total' => $validated['order_total'] ?? 0,
            'order_type' => $validated['order_type'] ?? null,
        ];

        // Загружаем данные клиента для проверки birthday и loyalty_level
        if (!empty($validated['customer_id'])) {
            $customer = Customer::forRestaurant($restaurantId)->find($validated['customer_id']);
            if ($customer) {
                $context['customer_birthday'] = $customer->birth_date;
                $context['customer_loyalty_level'] = $customer->loyalty_level_id;
            }
        }

        // Полная проверка применимости промокода к контексту
        $validation = $promotion->isApplicableToContext($context);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['error'] ?? 'Промокод недействителен',
            ], 422);
        }

        // Для бонусных типов требуется привязанный клиент
        if (in_array($promotion->type, ['bonus', 'bonus_multiplier']) && empty($validated['customer_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Для применения этого промокода необходимо привязать клиента к заказу',
            ], 422);
        }

        $discount = $promotion->calculateDiscount($validated['items'] ?? [], $validated['order_total'] ?? 0, $context);

        // Для gift промокодов загружаем информацию о подарочном блюде
        $giftDish = null;
        if ($promotion->gift_dish_id) {
            $promotion->load('giftDish.category');
            $giftDish = $promotion->giftDish;
        }

        // Получаем описание условий для информирования пользователя
        $conditionsSummary = $promotion->getConditionsSummary();

        return response()->json([
            'success' => true,
            'message' => 'Промокод действителен',
            'data' => [
                'promo_code' => $promotion,
                'discount' => $discount,
                'discount_type' => $promotion->type,
                'gift_dish' => $giftDish ? [
                    'id' => $giftDish->id,
                    'name' => $giftDish->name,
                    'price' => $giftDish->price,
                    'category' => $giftDish->category?->name,
                ] : null,
                'conditions' => $conditionsSummary,
            ],
        ]);
    }

    // ==========================================
    // БОНУСЫ
    // ==========================================

    public function bonusHistory(Request $request): JsonResponse
    {
        $query = BonusTransaction::with(['customer', 'order'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('customer_id')) {
            $query->forCustomer($request->input('customer_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query->orderByDesc('created_at')->limit(500)->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function earnBonus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|integer|exists:orders,id',
            'description' => 'nullable|string|max:255',
            'type' => 'nullable|in:earn,manual,birthday,promo',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $customer = Customer::forRestaurant($restaurantId)->find($validated['customer_id']);

        // Используем BonusService
        $bonusService = new BonusService($restaurantId);

        // Применяем множитель уровня
        $amount = (int) $validated['amount'];
        if ($customer->loyaltyLevel && $customer->loyaltyLevel->bonus_multiplier > 1) {
            $amount = (int) round($amount * $customer->loyaltyLevel->bonus_multiplier);
        }

        $type = $validated['type'] ?? BonusTransaction::TYPE_EARN;
        $transaction = $bonusService->earn(
            $customer,
            $amount,
            $type,
            $validated['order_id'] ?? null,
            $validated['description'] ?? null,
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'message' => "Начислено {$amount} бонусов",
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $bonusService->getBalance($customer),
            ],
        ]);
    }

    public function spendBonus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|integer|exists:orders,id',
            'description' => 'nullable|string|max:255',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $customer = Customer::forRestaurant($restaurantId)->find($validated['customer_id']);

        // Используем BonusService
        $bonusService = new BonusService($restaurantId);
        $result = $bonusService->spend(
            $customer,
            (int) $validated['amount'],
            $validated['order_id'] ?? null,
            $validated['description'] ?? null,
            $request->input('user_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Списано {$validated['amount']} бонусов",
            'data' => [
                'transaction' => $result['transaction'],
                'new_balance' => $result['new_balance'],
            ],
        ]);
    }

    // ==========================================
    // РАСЧЁТ СКИДКИ ДЛЯ ЗАКАЗА
    // ==========================================

    public function calculateDiscount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'order_total' => 'required|numeric|min:0',
            'order_subtotal' => 'nullable|numeric|min:0',
            'promo_code' => 'nullable|string',
            'use_bonus' => 'nullable|numeric|min:0',
            'order_type' => 'nullable|string|in:dine_in,delivery,pickup',
            'items' => 'nullable|array',
            'zone_id' => 'nullable|integer',
            'table_id' => 'nullable|integer',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $orderTotal = $validated['order_subtotal'] ?? $validated['order_total'];

        // Используем единый сервис расчёта скидок
        $calculator = new DiscountCalculatorService($restaurantId);
        $result = $calculator->calculate([
            'items' => $validated['items'] ?? [],
            'subtotal' => $orderTotal,
            'order_type' => $validated['order_type'] ?? 'dine_in',
            'customer_id' => $validated['customer_id'] ?? null,
            'promo_code' => $validated['promo_code'] ?? null,
            'zone_id' => $validated['zone_id'] ?? null,
            'table_id' => $validated['table_id'] ?? null,
        ]);

        // Обработка бонусов (оплата бонусами) через BonusService
        $bonusUsed = 0;
        $discounts = $result['discounts'];
        $totalDiscount = $result['total_discount'];
        $finalTotal = $result['final_total'];

        if (!empty($validated['use_bonus']) && !empty($validated['customer_id'])) {
            $customer = Customer::forRestaurant($restaurantId)->find($validated['customer_id']);
            if ($customer) {
                $bonusService = new BonusService($restaurantId);
                $maxSpendInfo = $bonusService->calculateMaxSpend($orderTotal, $customer, $totalDiscount);

                if ($maxSpendInfo['max_amount'] > 0) {
                    $bonusUsed = min((int) $validated['use_bonus'], $maxSpendInfo['max_amount']);

                    if ($bonusUsed > 0) {
                        $discounts[] = [
                            'type' => 'bonus',
                            'name' => '💰 Оплата бонусами',
                            'amount' => $bonusUsed,
                        ];
                        $totalDiscount += $bonusUsed;
                        $finalTotal = max(0, $finalTotal - $bonusUsed);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_total' => $orderTotal,
                'discounts' => $discounts,
                'applied_discounts' => $result['applied_discounts'],
                'total_discount' => round($totalDiscount, 2),
                'bonus_used' => round($bonusUsed, 2),
                'final_total' => round($finalTotal, 2),
                'bonus_earned' => $result['bonus_earned'],
                'free_delivery' => $result['free_delivery'],
                'gift_items' => $result['gift_items'],
                'applied_promotions' => $result['applied_promotions'],
                'customer' => $result['customer'],
            ],
        ]);
    }

    // ==========================================
    // НАСТРОЙКИ
    // ==========================================

    public function settings(Request $request): JsonResponse
    {
        $settings = LoyaltySetting::getAll($this->getRestaurantId($request));

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Поддержка двух форматов: { settings: {...} } или напрямую { key: value }
        $settings = $request->input('settings');

        if (!$settings) {
            // Напрямую переданные настройки
            $settings = $request->except(['restaurant_id']);
        }

        foreach ($settings as $key => $value) {
            LoyaltySetting::set($key, is_bool($value) ? ($value ? '1' : '0') : $value, $restaurantId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
        ]);
    }

    // ==========================================
    // СТАТИСТИКА
    // ==========================================

    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        // Статистика по уровням
        $levelStats = LoyaltyLevel::where('restaurant_id', $restaurantId)
            ->withCount('customers')
            ->ordered()
            ->get();

        // Бонусы за месяц
        $monthlyBonus = BonusTransaction::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw("
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as spent
            ")
            ->first();

        // Использование промокодов за месяц
        $promoStats = PromotionUsage::whereHas('promotion', function ($q) use ($restaurantId) {
                $q->where('restaurant_id', $restaurantId);
            })
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, SUM(discount_amount) as total_discount')
            ->first();

        // Активные промокоды (акции с кодом)
        $activePromoCodes = Promotion::where('restaurant_id', $restaurantId)
            ->whereNotNull('code')
            ->where('is_active', true)
            ->where(function ($q) {
                $now = Carbon::now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = Carbon::now();
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->count();

        // Клиенты с днём рождения на этой неделе (совместимо с MySQL и SQLite)
        $today = now();
        $weekLater = now()->addDays(7);
        $birthdayCustomers = Customer::where('restaurant_id', $restaurantId)
            ->whereNotNull('birthday')
            ->where(function ($query) use ($today, $weekLater) {
                // Получаем месяц-день для сравнения
                $todayMD = $today->format('m-d');
                $weekLaterMD = $weekLater->format('m-d');

                $driver = $query->getConnection()->getDriverName();

                if ($driver === 'sqlite') {
                    // SQLite: strftime
                    if ($todayMD <= $weekLaterMD) {
                        $query->whereRaw("strftime('%m-%d', birthday) BETWEEN ? AND ?", [$todayMD, $weekLaterMD]);
                    } else {
                        // Переход через год (декабрь -> январь)
                        $query->where(function ($q) use ($todayMD, $weekLaterMD) {
                            $q->whereRaw("strftime('%m-%d', birthday) >= ?", [$todayMD])
                              ->orWhereRaw("strftime('%m-%d', birthday) <= ?", [$weekLaterMD]);
                        });
                    }
                } else {
                    // MySQL: DATE_FORMAT
                    if ($todayMD <= $weekLaterMD) {
                        $query->whereRaw("DATE_FORMAT(birthday, '%m-%d') BETWEEN ? AND ?", [$todayMD, $weekLaterMD]);
                    } else {
                        $query->where(function ($q) use ($todayMD, $weekLaterMD) {
                            $q->whereRaw("DATE_FORMAT(birthday, '%m-%d') >= ?", [$todayMD])
                              ->orWhereRaw("DATE_FORMAT(birthday, '%m-%d') <= ?", [$weekLaterMD]);
                        });
                    }
                }
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'level_stats' => $levelStats,
                'monthly_bonus' => [
                    'earned' => $monthlyBonus->earned ?? 0,
                    'spent' => $monthlyBonus->spent ?? 0,
                ],
                'promo_stats' => [
                    'usage_count' => $promoStats->count ?? 0,
                    'total_discount' => $promoStats->total_discount ?? 0,
                ],
                'active_promo_codes' => $activePromoCodes,
                'birthday_customers' => $birthdayCustomers,
            ],
        ]);
    }

    // ==========================================
    // ОБНОВЛЕНИЕ УРОВНЯ КЛИЕНТА
    // ==========================================

    public function recalculateCustomerLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $customer = Customer::forRestaurant($restaurantId)->find($validated['customer_id']);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Клиент не найден',
            ], 404);
        }

        $newLevel = LoyaltyLevel::getLevelForTotal($customer->total_spent, $restaurantId);

        if ($newLevel && $newLevel->id !== $customer->loyalty_level_id) {
            $customer->update(['loyalty_level_id' => $newLevel->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Уровень обновлён',
            'data' => [
                'customer' => $customer->fresh('loyaltyLevel'),
                'level' => $newLevel,
            ],
        ]);
    }

    // ==========================================
    // АКЦИИ И СПЕЦПРЕДЛОЖЕНИЯ
    // ==========================================

    public function promotions(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $query = Promotion::where('restaurant_id', $restaurantId);

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $now = Carbon::now();
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) {
                    $now = Carbon::now();
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $promotions = $query->orderBy('sort_order')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $promotions,
        ]);
    }

    public function showPromotion(Promotion $promotion): JsonResponse
    {
        $promotion->load('giftDish');

        return response()->json([
            'success' => true,
            'data' => $promotion,
        ]);
    }

    public function storePromotion(Request $request): JsonResponse
    {
        // Конвертируем пустые строки в null для дат и числовых полей
        $input = $request->all();
        foreach (['starts_at', 'ends_at', 'usage_limit', 'min_order_amount', 'discount_value', 'max_discount'] as $field) {
            if (isset($input[$field]) && $input[$field] === '') {
                $input[$field] = null;
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'promo_text' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'image' => 'nullable|string',
            'type' => 'required|in:discount_percent,discount_fixed,progressive_discount,buy_x_get_y,free_delivery,gift,combo,happy_hour,first_order,birthday,bonus,bonus_multiplier',
            'reward_type' => 'nullable|in:discount,bonus,gift,free_delivery',
            'applies_to' => 'nullable|in:whole_order,categories,dishes',
            'discount_value' => 'nullable|numeric|min:0',
            'progressive_tiers' => 'nullable|array',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'min_items_count' => 'nullable|integer|min:0',
            'applicable_categories' => 'nullable|array',
            'applicable_dishes' => 'nullable|array',
            'requires_all_dishes' => 'nullable|boolean',
            'excluded_dishes' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'gift_dish_id' => 'nullable|exists:dishes,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'schedule' => 'nullable|array',
            'conditions' => 'nullable|array',
            'bonus_settings' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_customer' => 'nullable|integer|min:1',
            'order_types' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'source_channels' => 'nullable|array',
            'stackable' => 'nullable|boolean',
            'auto_apply' => 'nullable|boolean',
            'is_exclusive' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_automatic' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_first_order_only' => 'nullable|boolean',
            'is_birthday_only' => 'nullable|boolean',
            'birthday_days_before' => 'nullable|integer|min:0',
            'birthday_days_after' => 'nullable|integer|min:0',
            'requires_promo_code' => 'nullable|boolean',
            'loyalty_levels' => 'nullable|array',
            'excluded_customers' => 'nullable|array',
            'zones' => 'nullable|array',
            'tables_list' => 'nullable|array',
        ]);

        // Конвертируем null в дефолтные значения для NOT NULL полей
        $defaults = [
            'min_order_amount' => 0,
            'min_items_count' => 0,
            'discount_value' => 0,
            'priority' => 0,
            'birthday_days_before' => 0,
            'birthday_days_after' => 0,
        ];

        foreach ($defaults as $field => $default) {
            if (array_key_exists($field, $validated) && $validated[$field] === null) {
                $validated[$field] = $default;
            }
        }

        $restaurantId = $this->getRestaurantId($request);
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Promotion::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $promotion = Promotion::create([
            'restaurant_id' => $restaurantId,
            'slug' => $slug,
            'sort_order' => Promotion::max('sort_order') + 1,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Акция создана',
            'data' => $promotion,
        ], 201);
    }

    public function updatePromotion(Request $request, Promotion $promotion): JsonResponse
    {
        // Конвертируем пустые строки в null
        $input = $request->all();
        foreach (['starts_at', 'ends_at', 'usage_limit', 'min_order_amount', 'discount_value', 'max_discount'] as $field) {
            if (isset($input[$field]) && $input[$field] === '') {
                $input[$field] = null;
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'promo_text' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'image' => 'nullable|string',
            'type' => 'sometimes|in:discount_percent,discount_fixed,progressive_discount,buy_x_get_y,free_delivery,gift,combo,happy_hour,first_order,birthday,bonus,bonus_multiplier',
            'reward_type' => 'nullable|in:discount,bonus,gift,free_delivery',
            'applies_to' => 'nullable|in:whole_order,categories,dishes',
            'discount_value' => 'nullable|numeric|min:0',
            'progressive_tiers' => 'nullable|array',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'min_items_count' => 'nullable|integer|min:0',
            'applicable_categories' => 'nullable|array',
            'applicable_dishes' => 'nullable|array',
            'requires_all_dishes' => 'nullable|boolean',
            'excluded_dishes' => 'nullable|array',
            'excluded_categories' => 'nullable|array',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'gift_dish_id' => 'nullable|exists:dishes,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'schedule' => 'nullable|array',
            'conditions' => 'nullable|array',
            'bonus_settings' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_customer' => 'nullable|integer|min:1',
            'order_types' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'source_channels' => 'nullable|array',
            'stackable' => 'nullable|boolean',
            'auto_apply' => 'nullable|boolean',
            'is_exclusive' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_automatic' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_first_order_only' => 'nullable|boolean',
            'is_birthday_only' => 'nullable|boolean',
            'birthday_days_before' => 'nullable|integer|min:0',
            'birthday_days_after' => 'nullable|integer|min:0',
            'requires_promo_code' => 'nullable|boolean',
            'loyalty_levels' => 'nullable|array',
            'excluded_customers' => 'nullable|array',
            'zones' => 'nullable|array',
            'tables_list' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $promotion->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Конвертируем null в дефолтные значения для NOT NULL полей
        $defaults = [
            'min_order_amount' => 0,
            'min_items_count' => 0,
            'discount_value' => 0,
            'priority' => 0,
            'birthday_days_before' => 0,
            'birthday_days_after' => 0,
        ];

        foreach ($defaults as $field => $default) {
            if (array_key_exists($field, $validated) && $validated[$field] === null) {
                $validated[$field] = $default;
            }
        }

        $promotion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Акция обновлена',
            'data' => $promotion,
        ]);
    }

    public function destroyPromotion(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Акция удалена',
        ]);
    }

    public function togglePromotion(Promotion $promotion): JsonResponse
    {
        $promotion->update(['is_active' => !$promotion->is_active]);

        return response()->json([
            'success' => true,
            'message' => $promotion->is_active ? 'Акция активирована' : 'Акция деактивирована',
            'data' => $promotion,
        ]);
    }

    // Получить активные акции для фронтенда
    public function activePromotions(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $promotions = Promotion::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
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
            ->get()
            ->filter(function ($promo) {
                return $promo->isCurrentlyActive();
            });

        return response()->json([
            'success' => true,
            'data' => $promotions->values(),
        ]);
    }

    // Генерация промокода
    public function generatePromoCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'length' => 'nullable|integer|min:4|max:20',
            'prefix' => 'nullable|string|max:10',
        ]);

        $length = $validated['length'] ?? 8;
        $prefix = $validated['prefix'] ?? '';

        $code = $prefix . Promotion::generateCode($length - strlen($prefix));

        return response()->json([
            'success' => true,
            'data' => ['code' => $code],
        ]);
    }

    // Получить доступные промокоды для клиента
    public function availablePromoCodes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $customerId = $request->input('customer_id');

        $now = Carbon::now();
        $query = Promotion::where('restaurant_id', $restaurantId)
            ->whereNotNull('code')
            ->where('is_public', true)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });

        if ($customerId) {
            // Также включить персональные промокоды
            $query->orWhere(function ($q) use ($restaurantId, $customerId, $now) {
                $q->where('restaurant_id', $restaurantId)
                    ->whereNotNull('code')
                    ->whereJsonContains('allowed_customer_ids', $customerId)
                    ->where('is_active', true)
                    ->where(function ($sq) use ($now) {
                        $sq->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($sq) use ($now) {
                        $sq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    });
            });
        }

        $promoCodes = $query->get()->filter(function ($promotion) use ($customerId) {
            $validation = $promotion->checkCodeValidity($customerId);
            return $validation['valid'];
        });

        return response()->json([
            'success' => true,
            'data' => $promoCodes->values(),
        ]);
    }

    // ==========================================
    // НАСТРОЙКИ БОНУСНОЙ ПРОГРАММЫ
    // ==========================================

    public function bonusSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $settings = \App\Models\BonusSetting::getForRestaurant($restaurantId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateBonusSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'currency_name' => 'nullable|string|max:50',
            'currency_symbol' => 'nullable|string|max:10',
            'earn_rate' => 'nullable|numeric|min:0|max:100',
            'min_order_for_earn' => 'nullable|numeric|min:0',
            'earn_rounding' => 'nullable|integer|min:1|max:100',
            'spend_rate' => 'nullable|numeric|min:0|max:100',
            'min_spend_amount' => 'nullable|numeric|min:0',
            'bonus_to_ruble' => 'nullable|numeric|min:0',
            'expiry_days' => 'nullable|integer|min:0|max:365',
            'notify_before_expiry' => 'nullable|boolean',
            'notify_days_before' => 'nullable|integer|min:1|max:30',
            'registration_bonus' => 'nullable|numeric|min:0',
            'birthday_bonus' => 'nullable|numeric|min:0',
            'referral_bonus' => 'nullable|numeric|min:0',
            'referral_friend_bonus' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $settings = \App\Models\BonusSetting::getForRestaurant($restaurantId);
        $settings->update(array_filter($validated, fn($v) => $v !== null));

        return response()->json([
            'success' => true,
            'message' => 'Настройки бонусов сохранены',
            'data' => $settings->fresh(),
        ]);
    }
}
