<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCertificateController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Список всех сертификатов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $query = GiftCertificate::where('restaurant_id', $restaurantId)
            ->with(['buyerCustomer:id,name,phone', 'recipientCustomer:id,name,phone', 'soldByUser:id,name'])
            ->orderByDesc('created_at');

        // Фильтр по статусу
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Поиск по коду
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_phone', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%");
            });
        }

        $certificates = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $certificates->items(),
            'meta' => [
                'total' => $certificates->total(),
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
            ],
        ]);
    }

    /**
     * Создать новый сертификат
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:100000',
            'buyer_customer_id' => 'nullable|exists:customers,id',
            'buyer_name' => 'nullable|string|max:255',
            'buyer_phone' => 'nullable|string|max:20',
            'recipient_customer_id' => 'nullable|exists:customers,id',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'payment_method' => 'required|in:cash,card,online',
            'expires_at' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
            'activate' => 'boolean',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $certificate = GiftCertificate::create([
            'restaurant_id' => $restaurantId,
            'code' => GiftCertificate::generateCode(),
            'amount' => $validated['amount'],
            'balance' => $validated['amount'],
            'buyer_customer_id' => $validated['buyer_customer_id'] ?? null,
            'buyer_name' => $validated['buyer_name'] ?? null,
            'buyer_phone' => $validated['buyer_phone'] ?? null,
            'recipient_customer_id' => $validated['recipient_customer_id'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'payment_method' => $validated['payment_method'],
            'sold_by_user_id' => auth()->id(),
            'status' => GiftCertificate::STATUS_PENDING,
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Если сразу активируем
        if ($request->input('activate', true)) {
            $certificate->activate();
        }

        return response()->json([
            'success' => true,
            'data' => $certificate->fresh(['buyerCustomer', 'recipientCustomer']),
            'message' => 'Сертификат создан',
        ], 201);
    }

    /**
     * Получить сертификат по ID
     */
    public function show(GiftCertificate $giftCertificate): JsonResponse
    {
        $giftCertificate->load([
            'buyerCustomer:id,name,phone',
            'recipientCustomer:id,name,phone',
            'soldByUser:id,name',
            'usages.order:id,order_number,daily_number',
            'usages.customer:id,name,phone',
        ]);

        return response()->json([
            'success' => true,
            'data' => $giftCertificate,
        ]);
    }

    /**
     * Обновить сертификат
     */
    public function update(Request $request, GiftCertificate $giftCertificate): JsonResponse
    {
        $validated = $request->validate([
            'recipient_customer_id' => 'nullable|exists:customers,id',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $giftCertificate->update($validated);

        return response()->json([
            'success' => true,
            'data' => $giftCertificate->fresh(['buyerCustomer', 'recipientCustomer']),
            'message' => 'Сертификат обновлён',
        ]);
    }

    /**
     * Проверить сертификат по коду (для использования в POS)
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper(trim($request->input('code')));

        $certificate = GiftCertificate::byCode($code)->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Сертификат не найден',
            ], 404);
        }

        // Проверяем срок действия
        $certificate->checkExpiration();

        if (!$certificate->canBeUsed()) {
            $reason = match ($certificate->status) {
                GiftCertificate::STATUS_PENDING => 'Сертификат не активирован',
                GiftCertificate::STATUS_USED => 'Сертификат полностью использован',
                GiftCertificate::STATUS_EXPIRED => 'Срок действия сертификата истёк',
                GiftCertificate::STATUS_CANCELLED => 'Сертификат отменён',
                default => 'Сертификат недоступен',
            };

            return response()->json([
                'success' => false,
                'message' => $reason,
                'data' => [
                    'code' => $certificate->code,
                    'status' => $certificate->status,
                    'status_label' => $certificate->status_label,
                    'balance' => (float) $certificate->balance,
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $certificate->id,
                'code' => $certificate->code,
                'amount' => (float) $certificate->amount,
                'balance' => (float) $certificate->balance,
                'status' => $certificate->status,
                'status_label' => $certificate->status_label,
                'expires_at' => $certificate->expires_at,
                'recipient_name' => $certificate->recipient_name,
            ],
            'message' => 'Сертификат действителен',
        ]);
    }

    /**
     * Использовать сертификат
     */
    public function use(Request $request, GiftCertificate $giftCertificate): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'nullable|exists:orders,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if (!$giftCertificate->canBeUsed()) {
            return response()->json([
                'success' => false,
                'message' => 'Сертификат не может быть использован',
            ], 400);
        }

        $amount = min($validated['amount'], $giftCertificate->balance);

        try {
            $usage = $giftCertificate->use(
                $amount,
                $validated['order_id'] ?? null,
                $validated['customer_id'] ?? null,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'used_amount' => (float) $usage->amount,
                    'remaining_balance' => (float) $giftCertificate->balance,
                    'certificate_status' => $giftCertificate->status,
                ],
                'message' => "Списано {$usage->amount} руб. с сертификата",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Активировать сертификат
     */
    public function activate(GiftCertificate $giftCertificate): JsonResponse
    {
        if ($giftCertificate->status !== GiftCertificate::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Можно активировать только сертификаты в статусе "Ожидает оплаты"',
            ], 400);
        }

        $giftCertificate->activate();

        return response()->json([
            'success' => true,
            'data' => $giftCertificate,
            'message' => 'Сертификат активирован',
        ]);
    }

    /**
     * Отменить сертификат
     */
    public function cancel(GiftCertificate $giftCertificate): JsonResponse
    {
        if ($giftCertificate->status === GiftCertificate::STATUS_USED) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить использованный сертификат',
            ], 400);
        }

        $giftCertificate->cancel();

        return response()->json([
            'success' => true,
            'data' => $giftCertificate,
            'message' => 'Сертификат отменён',
        ]);
    }

    /**
     * Статистика по сертификатам
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $stats = [
            'total_count' => GiftCertificate::where('restaurant_id', $restaurantId)->count(),
            'active_count' => GiftCertificate::where('restaurant_id', $restaurantId)
                ->where('status', GiftCertificate::STATUS_ACTIVE)
                ->count(),
            'total_sold' => (float) GiftCertificate::where('restaurant_id', $restaurantId)
                ->whereIn('status', [GiftCertificate::STATUS_ACTIVE, GiftCertificate::STATUS_USED])
                ->sum('amount'),
            'total_balance' => (float) GiftCertificate::where('restaurant_id', $restaurantId)
                ->where('status', GiftCertificate::STATUS_ACTIVE)
                ->sum('balance'),
            'expiring_soon' => GiftCertificate::where('restaurant_id', $restaurantId)
                ->expiringSoon(7)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
