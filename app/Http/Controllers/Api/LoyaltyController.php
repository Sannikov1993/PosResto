<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyLevel;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\BonusTransaction;
use App\Models\LoyaltySetting;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LoyaltyController extends Controller
{
    // ==========================================
    // УРОВНИ ЛОЯЛЬНОСТИ
    // ==========================================

    public function levels(Request $request): JsonResponse
    {
        $levels = LoyaltyLevel::where('restaurant_id', $request->input('restaurant_id', 1))
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
            'restaurant_id' => $request->input('restaurant_id', 1),
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

    // ==========================================
    // ПРОМОКОДЫ
    // ==========================================

    public function promoCodes(Request $request): JsonResponse
    {
        $query = PromoCode::where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->boolean('active_only')) {
            $query->valid();
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
            'code' => 'required|string|max:30|unique:promo_codes,code',
            'name' => 'required|string|max:100',
            'type' => 'required|in:percent,fixed,bonus',
            'value' => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'first_order_only' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $promoCode = PromoCode::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Промокод создан',
            'data' => $promoCode,
        ], 201);
    }

    public function updatePromoCode(Request $request, PromoCode $promoCode): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:percent,fixed,bonus',
            'value' => 'sometimes|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'first_order_only' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $promoCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Промокод обновлён',
            'data' => $promoCode,
        ]);
    }

    public function destroyPromoCode(PromoCode $promoCode): JsonResponse
    {
        $promoCode->delete();

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
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $promoCode = PromoCode::findByCode($validated['code'], $restaurantId);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Промокод не найден',
            ], 404);
        }

        $validation = $promoCode->validate(
            $validated['customer_id'] ?? null,
            $validated['order_total'] ?? 0
        );

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['errors'][0],
                'errors' => $validation['errors'],
            ], 422);
        }

        $discount = $promoCode->calculateDiscount($validated['order_total'] ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Промокод действителен',
            'data' => [
                'promo_code' => $promoCode,
                'discount' => $discount,
                'discount_type' => $promoCode->type,
            ],
        ]);
    }

    // ==========================================
    // БОНУСЫ
    // ==========================================

    public function bonusHistory(Request $request): JsonResponse
    {
        $query = BonusTransaction::with(['customer', 'order'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

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

        $customer = Customer::find($validated['customer_id']);
        $restaurantId = $request->input('restaurant_id', 1);

        // Применяем множитель уровня
        $amount = $validated['amount'];
        if ($customer->loyaltyLevel) {
            $amount *= $customer->loyaltyLevel->bonus_multiplier;
        }

        // Срок действия бонусов
        $expireDays = LoyaltySetting::get('bonus_expire_days', 365, $restaurantId);
        $expiresAt = $expireDays > 0 ? Carbon::today()->addDays($expireDays) : null;

        $newBalance = $customer->bonus_balance + $amount;

        $transaction = BonusTransaction::create([
            'restaurant_id' => $restaurantId,
            'customer_id' => $customer->id,
            'order_id' => $validated['order_id'] ?? null,
            'type' => $validated['type'] ?? 'earn',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $validated['description'] ?? 'Начисление бонусов',
            'expires_at' => $expiresAt,
            'created_by' => $request->input('user_id'),
        ]);

        $customer->update(['bonus_balance' => $newBalance]);

        return response()->json([
            'success' => true,
            'message' => "Начислено {$amount} бонусов",
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $newBalance,
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

        $customer = Customer::find($validated['customer_id']);
        $restaurantId = $request->input('restaurant_id', 1);

        if ($customer->bonus_balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно бонусов',
            ], 422);
        }

        $newBalance = $customer->bonus_balance - $validated['amount'];

        $transaction = BonusTransaction::create([
            'restaurant_id' => $restaurantId,
            'customer_id' => $customer->id,
            'order_id' => $validated['order_id'] ?? null,
            'type' => 'spend',
            'amount' => -$validated['amount'],
            'balance_after' => $newBalance,
            'description' => $validated['description'] ?? 'Списание бонусов',
            'created_by' => $request->input('user_id'),
        ]);

        $customer->update(['bonus_balance' => $newBalance]);

        return response()->json([
            'success' => true,
            'message' => "Списано {$validated['amount']} бонусов",
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $newBalance,
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
            'promo_code' => 'nullable|string',
            'use_bonus' => 'nullable|numeric|min:0',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $orderTotal = $validated['order_total'];
        $discounts = [];
        $totalDiscount = 0;
        $bonusEarned = 0;

        $customer = null;
        if (!empty($validated['customer_id'])) {
            $customer = Customer::with('loyaltyLevel')->find($validated['customer_id']);
        }

        // 1. Скидка по уровню лояльности
        if ($customer && $customer->loyaltyLevel && $customer->loyaltyLevel->discount_percent > 0) {
            $levelDiscount = round($orderTotal * $customer->loyaltyLevel->discount_percent / 100, 2);
            $discounts[] = [
                'type' => 'level',
                'name' => "Скидка {$customer->loyaltyLevel->name}",
                'percent' => $customer->loyaltyLevel->discount_percent,
                'amount' => $levelDiscount,
            ];
            $totalDiscount += $levelDiscount;
        }

        // 2. Скидка ко дню рождения
        if ($customer && $customer->birthday && $customer->loyaltyLevel?->birthday_bonus) {
            $birthday = Carbon::parse($customer->birthday)->setYear(Carbon::now()->year);
            $daysBefore = LoyaltySetting::get('birthday_days_before', 7, $restaurantId);
            $daysAfter = LoyaltySetting::get('birthday_days_after', 7, $restaurantId);
            
            $periodStart = $birthday->copy()->subDays($daysBefore);
            $periodEnd = $birthday->copy()->addDays($daysAfter);
            
            if (Carbon::today()->between($periodStart, $periodEnd) && !$customer->birthday_used_this_year) {
                $birthdayDiscount = round($orderTotal * $customer->loyaltyLevel->birthday_discount / 100, 2);
                $discounts[] = [
                    'type' => 'birthday',
                    'name' => '🎂 Скидка ко дню рождения',
                    'percent' => $customer->loyaltyLevel->birthday_discount,
                    'amount' => $birthdayDiscount,
                ];
                $totalDiscount += $birthdayDiscount;
            }
        }

        // 3. Промокод
        $promoDiscount = 0;
        if (!empty($validated['promo_code'])) {
            $promoCode = PromoCode::findByCode($validated['promo_code'], $restaurantId);
            if ($promoCode) {
                $validation = $promoCode->validate($customer?->id, $orderTotal);
                if ($validation['valid']) {
                    $promoDiscount = $promoCode->calculateDiscount($orderTotal);
                    $discounts[] = [
                        'type' => 'promo',
                        'name' => "🎁 Промокод {$promoCode->code}",
                        'code' => $promoCode->code,
                        'amount' => $promoCode->type === 'bonus' ? 0 : $promoDiscount,
                        'bonus' => $promoCode->type === 'bonus' ? $promoDiscount : 0,
                    ];
                    if ($promoCode->type !== 'bonus') {
                        $totalDiscount += $promoDiscount;
                    }
                }
            }
        }

        // 4. Оплата бонусами
        $bonusUsed = 0;
        if (!empty($validated['use_bonus']) && $customer && $customer->bonus_balance > 0) {
            $maxBonusPercent = LoyaltySetting::get('bonus_pay_percent', 50, $restaurantId);
            $maxBonusAmount = ($orderTotal - $totalDiscount) * $maxBonusPercent / 100;
            $bonusUsed = min($validated['use_bonus'], $customer->bonus_balance, $maxBonusAmount);
            
            if ($bonusUsed > 0) {
                $discounts[] = [
                    'type' => 'bonus',
                    'name' => '💰 Оплата бонусами',
                    'amount' => $bonusUsed,
                ];
                $totalDiscount += $bonusUsed;
            }
        }

        // 5. Расчёт бонусов к начислению
        $finalTotal = max(0, $orderTotal - $totalDiscount);
        if ($customer && $customer->loyaltyLevel) {
            $cashbackPercent = $customer->loyaltyLevel->cashback_percent;
            $multiplier = $customer->loyaltyLevel->bonus_multiplier;
            $bonusEarned = round($finalTotal * $cashbackPercent / 100 * $multiplier, 0);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_total' => $orderTotal,
                'discounts' => $discounts,
                'total_discount' => round($totalDiscount, 2),
                'bonus_used' => round($bonusUsed, 2),
                'final_total' => round($finalTotal, 2),
                'bonus_earned' => $bonusEarned,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'level' => $customer->loyaltyLevel?->name,
                    'bonus_balance' => $customer->bonus_balance,
                ] : null,
            ],
        ]);
    }

    // ==========================================
    // НАСТРОЙКИ
    // ==========================================

    public function settings(Request $request): JsonResponse
    {
        $settings = LoyaltySetting::getAll($request->input('restaurant_id', 1));

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        foreach ($validated['settings'] as $key => $value) {
            LoyaltySetting::set($key, $value, $restaurantId);
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
        $restaurantId = $request->input('restaurant_id', 1);
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
        $promoStats = PromoCodeUsage::whereHas('promoCode', function ($q) use ($restaurantId) {
                $q->where('restaurant_id', $restaurantId);
            })
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, SUM(discount_amount) as total_discount')
            ->first();

        // Активные промокоды
        $activePromoCodes = PromoCode::where('restaurant_id', $restaurantId)
            ->valid()
            ->count();

        // Клиенты с днём рождения на этой неделе
        $birthdayCustomers = Customer::where('restaurant_id', $restaurantId)
            ->whereNotNull('birthday')
            ->whereRaw("DATE_FORMAT(birthday, '%m-%d') BETWEEN DATE_FORMAT(NOW(), '%m-%d') AND DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 7 DAY), '%m-%d')")
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

        $customer = Customer::find($validated['customer_id']);
        $restaurantId = $customer->restaurant_id;

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
}
