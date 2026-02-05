<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use App\Models\BonusTransaction;
use App\Models\LoyaltyLevel;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Loyalty API Controller
 *
 * Bonus balance, transactions, loyalty levels and rewards.
 */
class LoyaltyController extends BaseApiController
{
    /**
     * Get loyalty program info and levels
     *
     * GET /api/v1/loyalty/program
     */
    public function program(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $levels = LoyaltyLevel::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('min_total')
            ->get();

        $bonusService = new BonusService($restaurantId);
        $settings = $bonusService->getSettings();

        return $this->success([
            'enabled' => $settings->is_enabled,
            'currency' => 'bonus', // Could be 'points', 'stars', etc.
            'earn_rate' => $settings->earn_rate ?? 0,
            'min_order_for_earn' => $settings->min_order_for_earn ?? 0,
            'max_spend_percent' => $settings->max_spend_percent ?? 100,
            'bonus_to_currency_rate' => 1, // 1 bonus = 1 ruble

            'levels' => $levels->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'icon' => $level->icon,
                    'color' => $level->color,
                    'min_total_spent' => number_format($level->min_total, 2, '.', ''),
                    'discount_percent' => $level->discount_percent,
                    'cashback_percent' => $level->cashback_percent,
                    'bonus_multiplier' => $level->bonus_multiplier,
                    'birthday_bonus' => $level->birthday_bonus,
                ];
            }),
        ]);
    }

    /**
     * Get customer bonus balance and level
     *
     * GET /api/v1/loyalty/balance/{customerId}
     */
    public function balance(Request $request, int $customerId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->with('loyaltyLevel')
            ->find($customerId);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $bonusService = new BonusService($restaurantId);
        $balance = $bonusService->getBalance($customer);

        // Get next level info
        $nextLevel = LoyaltyLevel::getNextLevel($customer->loyaltyLevel, $restaurantId);
        $progressToNextLevel = null;

        if ($nextLevel) {
            $currentMin = $customer->loyaltyLevel?->min_total ?? 0;
            $nextMin = $nextLevel->min_total;
            $progress = ($customer->total_spent - $currentMin) / ($nextMin - $currentMin) * 100;
            $progressToNextLevel = [
                'next_level_name' => $nextLevel->name,
                'required_spent' => number_format($nextMin, 2, '.', ''),
                'current_spent' => number_format($customer->total_spent ?? 0, 2, '.', ''),
                'remaining' => number_format(max(0, $nextMin - ($customer->total_spent ?? 0)), 2, '.', ''),
                'progress_percent' => min(100, max(0, round($progress, 1))),
            ];
        }

        return $this->success([
            'customer_id' => $customer->id,
            'balance' => $balance,
            'total_spent' => number_format($customer->total_spent ?? 0, 2, '.', ''),
            'total_orders' => $customer->total_orders ?? 0,

            'level' => $customer->loyaltyLevel ? [
                'id' => $customer->loyaltyLevel->id,
                'name' => $customer->loyaltyLevel->name,
                'icon' => $customer->loyaltyLevel->icon,
                'color' => $customer->loyaltyLevel->color,
                'discount_percent' => $customer->loyaltyLevel->discount_percent,
                'cashback_percent' => $customer->loyaltyLevel->cashback_percent,
                'bonus_multiplier' => $customer->loyaltyLevel->bonus_multiplier,
            ] : null,

            'next_level' => $progressToNextLevel,
        ]);
    }

    /**
     * Get customer bonus transaction history
     *
     * GET /api/v1/loyalty/transactions/{customerId}
     */
    public function transactions(Request $request, int $customerId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)->find($customerId);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $data = $this->validateRequest($request, [
            'type' => 'nullable|string|in:earn,spend,expire,manual,birthday,promo,referral,registration,refund',
            'since' => 'nullable|date',
            'until' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $query = BonusTransaction::where('customer_id', $customerId)
            ->where('restaurant_id', $restaurantId);

        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        if (!empty($data['since'])) {
            $query->where('created_at', '>=', $data['since']);
        }

        if (!empty($data['until'])) {
            $query->where('created_at', '<=', $data['until']);
        }

        $total = $query->count();

        $transactions = $query
            ->orderBy('created_at', 'desc')
            ->limit($data['limit'] ?? 50)
            ->offset($data['offset'] ?? 0)
            ->get();

        $result = $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'type_label' => $tx->type_label,
                'amount' => (int) $tx->amount,
                'balance_after' => (int) $tx->balance_after,
                'description' => $tx->description,
                'order_id' => $tx->order_id,
                'created_at' => $this->formatDateTime($tx->created_at),
                'expires_at' => $this->formatDateTime($tx->expires_at),
            ];
        });

        return $this->success([
            'transactions' => $result,
            'pagination' => [
                'total' => $total,
                'limit' => $data['limit'] ?? 50,
                'offset' => $data['offset'] ?? 0,
            ],
        ]);
    }

    /**
     * Calculate bonus earning for an order
     *
     * POST /api/v1/loyalty/calculate-earning
     */
    public function calculateEarning(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_total' => 'required|numeric|min:0',
            'customer_id' => 'nullable|integer',
        ]);

        $customer = null;
        if (!empty($data['customer_id'])) {
            $customer = Customer::where('restaurant_id', $restaurantId)
                ->find($data['customer_id']);
        }

        $bonusService = new BonusService($restaurantId);
        $result = $bonusService->calculateEarning($data['order_total'], $customer);

        return $this->success([
            'order_total' => number_format($data['order_total'], 2, '.', ''),
            'bonus_to_earn' => $result['amount'],
            'earn_rate' => $result['rate'],
            'details' => $result['details'] ?? null,
        ]);
    }

    /**
     * Calculate max bonus spending for an order
     *
     * POST /api/v1/loyalty/calculate-spending
     */
    public function calculateSpending(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_total' => 'required|numeric|min:0',
            'customer_id' => 'required|integer',
        ]);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->find($data['customer_id']);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $bonusService = new BonusService($restaurantId);
        $balance = $bonusService->getBalance($customer);
        $result = $bonusService->calculateSpending($data['order_total'], $customer);

        return $this->success([
            'order_total' => number_format($data['order_total'], 2, '.', ''),
            'customer_balance' => $balance,
            'max_spend' => $result['max_spend'],
            'max_spend_percent' => $result['max_percent'],
            'order_total_after_bonus' => number_format($data['order_total'] - $result['max_spend'], 2, '.', ''),
        ]);
    }

    /**
     * Earn bonuses for customer (usually after order completion)
     *
     * POST /api/v1/loyalty/earn
     */
    public function earn(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'customer_id' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'order_id' => 'nullable|integer',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|string|in:earn,promo,birthday,referral,registration,manual',
        ]);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->find($data['customer_id']);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $bonusService = new BonusService($restaurantId);

        $transaction = $bonusService->earn(
            customer: $customer,
            amount: $data['amount'],
            type: $data['type'] ?? BonusTransaction::TYPE_EARN,
            orderId: $data['order_id'] ?? null,
            description: $data['description'] ?? null
        );

        // Dispatch webhook
        $this->dispatchWebhook('customer.bonus_earned', [
            'customer_id' => $customer->id,
            'amount' => $data['amount'],
            'new_balance' => $transaction->balance_after,
            'transaction_id' => $transaction->id,
        ], $restaurantId);

        return $this->success([
            'transaction_id' => $transaction->id,
            'amount' => (int) $transaction->amount,
            'new_balance' => (int) $transaction->balance_after,
        ], 'Bonuses earned successfully');
    }

    /**
     * Spend bonuses for customer (usually during order payment)
     *
     * POST /api/v1/loyalty/spend
     */
    public function spend(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'customer_id' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'order_id' => 'nullable|integer',
            'description' => 'nullable|string|max:500',
        ]);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->find($data['customer_id']);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $bonusService = new BonusService($restaurantId);

        $result = $bonusService->spend(
            customer: $customer,
            amount: $data['amount'],
            orderId: $data['order_id'] ?? null,
            description: $data['description'] ?? null
        );

        if (!$result['success']) {
            return $this->businessError(
                'INSUFFICIENT_BALANCE',
                $result['error'] ?? 'Failed to spend bonuses'
            );
        }

        $transaction = $result['transaction'];

        // Dispatch webhook
        $this->dispatchWebhook('customer.bonus_spent', [
            'customer_id' => $customer->id,
            'amount' => $data['amount'],
            'new_balance' => $result['new_balance'],
            'transaction_id' => $transaction->id,
        ], $restaurantId);

        return $this->success([
            'transaction_id' => $transaction->id,
            'amount' => (int) abs($transaction->amount),
            'new_balance' => (int) $result['new_balance'],
        ], 'Bonuses spent successfully');
    }

    /**
     * Dispatch webhook event
     */
    protected function dispatchWebhook(string $eventType, array $data, int $restaurantId): void
    {
        try {
            app(\App\Services\WebhookService::class)->dispatch($eventType, $data, $restaurantId);
        } catch (\Exception $e) {
            // Log but don't fail the request
            report($e);
        }
    }
}
