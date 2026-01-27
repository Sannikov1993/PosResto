<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\CashOperation;
use App\Services\AtolOnlineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FiscalController extends Controller
{
    protected AtolOnlineService $atol;

    public function __construct(AtolOnlineService $atol)
    {
        $this->atol = $atol;
    }

    /**
     * Список фискальных чеков
     */
    public function index(Request $request): JsonResponse
    {
        $query = FiscalReceipt::with(['order'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        $receipts = $query->orderByDesc('created_at')
            ->limit($request->input('limit', 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $receipts,
        ]);
    }

    /**
     * Показать фискальный чек
     */
    public function show(FiscalReceipt $receipt): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $receipt->load('order'),
        ]);
    }

    /**
     * Проверить статус чека в АТОЛ
     */
    public function checkStatus(FiscalReceipt $receipt): JsonResponse
    {
        $receipt = $this->atol->checkStatus($receipt);

        return response()->json([
            'success' => true,
            'data' => $receipt,
        ]);
    }

    /**
     * Повторно отправить чек (retry)
     */
    public function retry(FiscalReceipt $receipt): JsonResponse
    {
        if ($receipt->status !== FiscalReceipt::STATUS_FAIL) {
            return response()->json([
                'success' => false,
                'message' => 'Повторная отправка возможна только для чеков со статусом fail',
            ], 400);
        }

        $order = $receipt->order;

        // Определяем тип операции
        if ($receipt->operation === 'sell') {
            $newReceipt = $this->atol->sell(
                $order,
                $order->payment_method ?? 'cash',
                $receipt->customer_email ?? $receipt->customer_phone
            );
        } else {
            $newReceipt = $this->atol->sellRefund(
                $order,
                $order->payment_method ?? 'cash',
                $receipt->customer_email ?? $receipt->customer_phone
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Чек отправлен повторно',
            'data' => $newReceipt,
        ]);
    }

    /**
     * Создать чек возврата
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'customer_contact' => 'nullable|string|max:100',
            'staff_id' => 'nullable|integer|exists:staff,id',
        ]);

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Возврат возможен только для оплаченных заказов',
            ], 400);
        }

        $customerContact = $validated['customer_contact'] ?? $order->phone ?? null;

        $receipt = $this->atol->sellRefund(
            $order,
            $order->payment_method ?? 'cash',
            $customerContact
        );

        // Записываем кассовую операцию возврата
        CashOperation::recordRefund(
            $order,
            $order->total,
            $order->payment_method ?? 'cash',
            $validated['staff_id'] ?? null,
            $receipt
        );

        // Обновляем статус заказа
        $order->update([
            'payment_status' => 'refunded',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Чек возврата создан',
            'data' => $receipt,
        ]);
    }

    /**
     * Callback от АТОЛ
     */
    public function callback(Request $request): JsonResponse
    {
        $data = $request->all();

        $receipt = $this->atol->handleCallback($data);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Чек не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $receipt,
        ]);
    }

    /**
     * Статус интеграции АТОЛ
     */
    public function status(): JsonResponse
    {
        $enabled = $this->atol->isEnabled();
        $testMode = config('atol.test_mode', true);

        $data = [
            'enabled' => $enabled,
            'test_mode' => $testMode,
            'group_code' => config('atol.group_code'),
            'company_inn' => config('atol.company.inn'),
            'company_name' => config('atol.company.name'),
        ];

        // Проверяем токен если включено
        if ($enabled) {
            $token = $this->atol->getToken();
            $data['token_valid'] = $token !== null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
