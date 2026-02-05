<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payments API Controller
 *
 * Payment processing, refunds, and payment calculations.
 */
class PaymentsController extends BaseApiController
{
    /**
     * Get available payment methods
     *
     * GET /api/v1/payments/methods
     */
    public function methods(Request $request): JsonResponse
    {
        return $this->success([
            'methods' => [
                [
                    'id' => 'cash',
                    'name' => 'Cash',
                    'name_ru' => 'Наличные',
                    'icon' => 'cash',
                    'requires_change' => true,
                ],
                [
                    'id' => 'card',
                    'name' => 'Card',
                    'name_ru' => 'Банковская карта',
                    'icon' => 'credit-card',
                    'requires_change' => false,
                ],
                [
                    'id' => 'online',
                    'name' => 'Online Payment',
                    'name_ru' => 'Онлайн-оплата',
                    'icon' => 'smartphone',
                    'requires_change' => false,
                ],
                [
                    'id' => 'mixed',
                    'name' => 'Split Payment',
                    'name_ru' => 'Раздельная оплата',
                    'icon' => 'split',
                    'requires_change' => true,
                ],
            ],
        ]);
    }

    /**
     * Calculate payment for an order
     *
     * POST /api/v1/payments/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_id' => 'required|integer',
            'bonus_use' => 'nullable|integer|min:0',
            'promo_code' => 'nullable|string|max:50',
        ]);

        $order = Order::where('restaurant_id', $restaurantId)
            ->with(['customer', 'items'])
            ->find($data['order_id']);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->payment_status === 'paid') {
            return $this->businessError('ALREADY_PAID', 'Order is already paid');
        }

        $subtotal = (float) $order->subtotal;
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $bonusUsed = 0;
        $maxBonusSpend = 0;
        $customerBalance = 0;

        // Calculate bonus availability
        if ($order->customer_id) {
            $bonusService = new BonusService($restaurantId);
            $customerBalance = $bonusService->getBalance($order->customer);

            $spendingResult = $bonusService->calculateSpending($subtotal - $discountAmount, $order->customer);
            $maxBonusSpend = $spendingResult['max_spend'];

            if (!empty($data['bonus_use'])) {
                $bonusUsed = min($data['bonus_use'], $maxBonusSpend, $customerBalance);
            }
        }

        // TODO: Validate promo code if provided
        $promoDiscount = 0;
        if (!empty($data['promo_code'])) {
            // Promo code validation would go here
        }

        $total = max(0, $subtotal - $discountAmount - $promoDiscount - $bonusUsed + $deliveryFee);

        return $this->success([
            'order_id' => $order->id,
            'calculation' => [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'promo_discount' => number_format($promoDiscount, 2, '.', ''),
                'bonus_used' => $bonusUsed,
                'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
            ],
            'bonus' => [
                'customer_balance' => $customerBalance,
                'max_spend' => $maxBonusSpend,
                'used' => $bonusUsed,
            ],
            'promo_code' => $data['promo_code'] ?? null,
        ]);
    }

    /**
     * Process payment for an order
     *
     * POST /api/v1/payments/pay
     */
    public function pay(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_id' => 'required|integer',
            'method' => 'required|string|in:cash,card,online,mixed',
            'amount' => 'nullable|numeric|min:0',
            'cash_amount' => 'nullable|numeric|min:0',
            'card_amount' => 'nullable|numeric|min:0',
            'bonus_use' => 'nullable|integer|min:0',
            'promo_code' => 'nullable|string|max:50',
            'external_transaction_id' => 'nullable|string|max:100',
        ]);

        $order = Order::where('restaurant_id', $restaurantId)
            ->with(['customer', 'items'])
            ->find($data['order_id']);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->payment_status === 'paid') {
            return $this->businessError('ALREADY_PAID', 'Order is already paid');
        }

        // Prepare payment data for PaymentService
        $paymentData = [
            'method' => $data['method'],
            'amount' => $data['amount'] ?? null,
            'cash_amount' => $data['cash_amount'] ?? 0,
            'card_amount' => $data['card_amount'] ?? 0,
            'bonus_used' => $data['bonus_use'] ?? 0,
            'promo_code' => $data['promo_code'] ?? null,
        ];

        $paymentService = new PaymentService();
        $result = $paymentService->processPayment($order, $paymentData);

        if (!$result['success']) {
            $errorCode = $result['error_code'] ?? 'PAYMENT_FAILED';
            return $this->businessError($errorCode, $result['message']);
        }

        $order->refresh();

        // Dispatch webhook
        $this->dispatchWebhook('order.paid', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method,
            'paid_at' => $this->formatDateTime($order->paid_at),
        ], $restaurantId);

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total' => number_format($order->total, 2, '.', ''),
            'bonus_used' => (int) ($order->bonus_used ?? 0),
            'paid_at' => $this->formatDateTime($order->paid_at),
        ], 'Payment successful');
    }

    /**
     * Get payment status for an order
     *
     * GET /api/v1/payments/status/{orderId}
     */
    public function status(Request $request, int $orderId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $order = Order::where('restaurant_id', $restaurantId)
            ->find($orderId);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total' => number_format($order->total, 2, '.', ''),
            'paid_amount' => number_format($order->paid_amount ?? 0, 2, '.', ''),
            'bonus_used' => (int) ($order->bonus_used ?? 0),
            'deposit_used' => number_format($order->deposit_used ?? 0, 2, '.', ''),
            'paid_at' => $this->formatDateTime($order->paid_at),
        ]);
    }

    /**
     * Process refund for a paid order
     *
     * POST /api/v1/payments/refund
     */
    public function refund(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'order_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'refund_method' => 'nullable|string|in:cash,card,original',
        ]);

        $order = Order::where('restaurant_id', $restaurantId)
            ->find($data['order_id']);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->payment_status !== 'paid') {
            return $this->businessError('NOT_PAID', 'Order is not paid');
        }

        if ($data['amount'] > (float) $order->total) {
            return $this->businessError('INVALID_AMOUNT', 'Refund amount exceeds order total');
        }

        $refundMethod = $data['refund_method'] ?? $order->payment_method;
        if ($refundMethod === 'original') {
            $refundMethod = $order->payment_method;
        }

        $paymentService = new PaymentService();
        $result = $paymentService->processRefund($order, $data['amount'], $refundMethod);

        if (!$result['success']) {
            $errorCode = $result['error_code'] ?? 'REFUND_FAILED';
            return $this->businessError($errorCode, $result['message']);
        }

        // Update order payment status if fully refunded
        if ($data['amount'] >= (float) $order->total) {
            $order->update(['payment_status' => 'refunded']);
        }

        // Dispatch webhook
        $this->dispatchWebhook('order.refunded', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'refund_amount' => $data['amount'],
            'refund_method' => $refundMethod,
            'reason' => $data['reason'],
        ], $restaurantId);

        return $this->success([
            'order_id' => $order->id,
            'refund_amount' => number_format($data['amount'], 2, '.', ''),
            'refund_method' => $refundMethod,
            'payment_status' => $order->fresh()->payment_status,
        ], 'Refund processed');
    }

    /**
     * Validate promo code
     *
     * POST /api/v1/payments/validate-promo
     */
    public function validatePromo(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = $this->validateRequest($request, [
            'promo_code' => 'required|string|max:50',
            'order_total' => 'required|numeric|min:0',
            'customer_id' => 'nullable|integer',
        ]);

        // TODO: Implement promo code validation logic
        // For now, return a placeholder response
        return $this->success([
            'valid' => false,
            'promo_code' => $data['promo_code'],
            'message' => 'Promo code validation not implemented',
        ]);
    }

    /**
     * Dispatch webhook event
     */
    protected function dispatchWebhook(string $eventType, array $data, int $restaurantId): void
    {
        try {
            app(\App\Services\WebhookService::class)->dispatch($eventType, $data, $restaurantId);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
