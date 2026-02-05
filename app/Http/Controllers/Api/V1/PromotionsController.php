<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Promotion;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promotions API Controller
 *
 * Active promotions and promo code validation.
 */
class PromotionsController extends BaseApiController
{
    /**
     * Get active promotions
     *
     * GET /api/v1/promotions
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $now = now();

        $promotions = Promotion::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_visible', true) // Only show public promotions
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->orderBy('priority', 'desc')
            ->get();

        $transformed = $promotions->map(function ($promo) {
            return [
                'id' => $promo->id,
                'name' => $promo->name,
                'description' => $promo->description,
                'type' => $promo->type,
                'image_url' => $promo->image ? asset('storage/' . $promo->image) : null,

                // Discount info
                'discount' => [
                    'type' => $promo->type,
                    'value' => $promo->discount_value,
                    'max_discount' => $promo->max_discount,
                ],

                // Conditions
                'conditions' => [
                    'min_order_amount' => $promo->min_order_amount,
                    'order_types' => $promo->order_types,
                    'applies_to' => $promo->applies_to, // all, categories, dishes
                    'applicable_categories' => $promo->applicable_categories,
                    'applicable_dishes' => $promo->applicable_dishes,
                ],

                // Validity
                'start_date' => $this->formatDateTime($promo->start_date),
                'end_date' => $this->formatDateTime($promo->end_date),

                // Flags
                'requires_promo_code' => $promo->requires_promo_code,
                'is_automatic' => $promo->is_automatic,
                'stackable' => $promo->stackable,
            ];
        });

        return $this->success($transformed);
    }

    /**
     * Validate promo code
     *
     * POST /api/v1/promotions/validate-code
     */
    public function validateCode(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'code' => 'required|string|max:50',
            'order_total' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
            'order_type' => 'nullable|in:dine_in,delivery,pickup',
        ]);

        $code = strtoupper(trim($data['code']));
        $now = now();

        // Find promo code
        $promoCode = PromoCode::where('restaurant_id', $restaurantId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$promoCode) {
            return $this->success([
                'valid' => false,
                'code' => $code,
                'error' => 'INVALID_CODE',
                'message' => 'Promo code not found or inactive',
            ]);
        }

        // Check validity period
        if ($promoCode->start_date && $promoCode->start_date > $now) {
            return $this->success([
                'valid' => false,
                'code' => $code,
                'error' => 'NOT_YET_VALID',
                'message' => 'Promo code is not yet valid',
                'valid_from' => $this->formatDateTime($promoCode->start_date),
            ]);
        }

        if ($promoCode->end_date && $promoCode->end_date < $now) {
            return $this->success([
                'valid' => false,
                'code' => $code,
                'error' => 'EXPIRED',
                'message' => 'Promo code has expired',
            ]);
        }

        // Check usage limit
        if ($promoCode->usage_limit && $promoCode->usage_count >= $promoCode->usage_limit) {
            return $this->success([
                'valid' => false,
                'code' => $code,
                'error' => 'USAGE_LIMIT_REACHED',
                'message' => 'Promo code usage limit reached',
            ]);
        }

        // Check per-customer limit
        if ($promoCode->per_customer_limit && !empty($data['customer_id'])) {
            $customerUsage = $promoCode->usages()
                ->where('customer_id', $data['customer_id'])
                ->count();

            if ($customerUsage >= $promoCode->per_customer_limit) {
                return $this->success([
                    'valid' => false,
                    'code' => $code,
                    'error' => 'CUSTOMER_LIMIT_REACHED',
                    'message' => 'You have already used this promo code',
                ]);
            }
        }

        // Check minimum order amount
        if ($promoCode->min_order_amount && !empty($data['order_total'])) {
            if ($data['order_total'] < $promoCode->min_order_amount) {
                return $this->success([
                    'valid' => false,
                    'code' => $code,
                    'error' => 'MIN_ORDER_NOT_MET',
                    'message' => "Minimum order amount is {$promoCode->min_order_amount}",
                    'min_order_amount' => number_format($promoCode->min_order_amount, 2, '.', ''),
                ]);
            }
        }

        // Calculate discount if order total provided
        $discountAmount = null;
        if (!empty($data['order_total'])) {
            if ($promoCode->discount_type === 'percent') {
                $discountAmount = $data['order_total'] * $promoCode->discount_value / 100;
                if ($promoCode->max_discount && $discountAmount > $promoCode->max_discount) {
                    $discountAmount = $promoCode->max_discount;
                }
            } else {
                $discountAmount = min($promoCode->discount_value, $data['order_total']);
            }
        }

        return $this->success([
            'valid' => true,
            'code' => $code,
            'name' => $promoCode->name,
            'description' => $promoCode->description,
            'discount' => [
                'type' => $promoCode->discount_type,
                'value' => $promoCode->discount_value,
                'max_discount' => $promoCode->max_discount,
                'calculated_amount' => $discountAmount !== null
                    ? number_format($discountAmount, 2, '.', '')
                    : null,
            ],
            'conditions' => [
                'min_order_amount' => $promoCode->min_order_amount
                    ? number_format($promoCode->min_order_amount, 2, '.', '')
                    : null,
                'order_types' => $promoCode->order_types,
            ],
            'validity' => [
                'start_date' => $this->formatDateTime($promoCode->start_date),
                'end_date' => $this->formatDateTime($promoCode->end_date),
                'remaining_uses' => $promoCode->usage_limit
                    ? $promoCode->usage_limit - $promoCode->usage_count
                    : null,
            ],
        ]);
    }
}
