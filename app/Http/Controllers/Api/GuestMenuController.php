<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TableQrCode;
use App\Models\WaiterCall;
use App\Models\Review;
use App\Models\GuestMenuSetting;
use App\Models\Table;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GuestMenuController extends Controller
{
    use Traits\ResolvesRestaurantId;
    // ==========================================
    // ГОСТЕВОЕ МЕНЮ (публичное)
    // ==========================================

    public function getMenuByCode(string $code): JsonResponse
    {
        $qr = TableQrCode::with(['table.zone'])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$qr) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный QR-код',
            ], 404);
        }

        // Записываем сканирование
        $qr->recordScan();

        $restaurantId = $qr->restaurant_id;
        $settings = GuestMenuSetting::getAll($restaurantId);

        // Получаем меню
        $categories = Category::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['dishes' => function ($q) {
                $q->where('is_available', true)->orderBy('sort_order');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'table' => [
                    'id' => $qr->table->id,
                    'number' => $qr->table->number,
                    'zone' => $qr->table->zone?->name,
                ],
                'restaurant' => [
                    'name' => $settings['restaurant_name'] ?? 'Ресторан',
                    'logo' => $settings['restaurant_logo'] ?? null,
                    'color' => $settings['primary_color'] ?? '#f97316',
                    'welcome' => $settings['welcome_text'] ?? 'Добро пожаловать!',
                    'wifi_name' => $settings['wifi_name'] ?? null,
                    'wifi_password' => $settings['wifi_password'] ?? null,
                ],
                'settings' => [
                    'show_prices' => ($settings['show_prices'] ?? 'true') === 'true',
                    'allow_waiter_call' => ($settings['allow_waiter_call'] ?? 'true') === 'true',
                    'allow_reviews' => ($settings['allow_reviews'] ?? 'true') === 'true',
                ],
                'categories' => $categories,
            ],
        ]);
    }

    public function getDish(Request $request, int $dishId): JsonResponse
    {
        // Получаем restaurant_id из QR-кода для изоляции
        $restaurantId = null;
        if ($request->has('code')) {
            $qr = TableQrCode::where('code', $request->input('code'))
                ->where('is_active', true)
                ->first();
            if ($qr) {
                $restaurantId = $qr->restaurant_id;
            }
        }

        // Ищем блюдо только в ресторане из QR-кода (если указан)
        $query = Dish::withoutGlobalScope('restaurant')
            ->where('is_available', true);

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        } else {
            // Без QR-кода - не показываем блюда (безопасность)
            return response()->json([
                'success' => false,
                'message' => 'Требуется QR-код для просмотра блюда',
            ], 400);
        }

        $dish = $query->find($dishId);

        if (!$dish) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо не найдено',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $dish,
        ]);
    }

    // ==========================================
    // ВЫЗОВ ОФИЦИАНТА
    // ==========================================

    public function callWaiter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'type' => 'required|in:waiter,bill,help',
            'message' => 'nullable|string|max:500',
        ]);

        $qr = TableQrCode::where('code', $validated['code'])
            ->where('is_active', true)
            ->first();

        if (!$qr) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный QR-код',
            ], 404);
        }

        // Проверяем, нет ли уже активного вызова
        $existingCall = WaiterCall::where('table_id', $qr->table_id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingCall) {
            return response()->json([
                'success' => false,
                'message' => 'Вызов уже отправлен, пожалуйста подождите',
            ], 422);
        }

        $call = WaiterCall::create([
            'restaurant_id' => $qr->restaurant_id,
            'table_id' => $qr->table_id,
            'type' => $validated['type'],
            'message' => $validated['message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Официант уже идёт к вам!',
            'data' => $call,
        ]);
    }

    public function cancelCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'call_id' => 'required|integer',
        ]);

        $qr = TableQrCode::where('code', $validated['code'])->first();

        if (!$qr) {
            return response()->json(['success' => false], 404);
        }

        $call = WaiterCall::where('id', $validated['call_id'])
            ->where('table_id', $qr->table_id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($call) {
            $call->cancel();
        }

        return response()->json([
            'success' => true,
            'message' => 'Вызов отменён',
        ]);
    }

    public function activeCalls(Request $request): JsonResponse
    {
        $calls = WaiterCall::with(['table.zone', 'acceptedBy'])
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->active()
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $calls,
        ]);
    }

    public function acceptCall(Request $request, WaiterCall $call): JsonResponse
    {
        if ($call->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Вызов уже обработан',
            ], 422);
        }

        $call->accept($request->input('user_id', 1));

        return response()->json([
            'success' => true,
            'message' => 'Вызов принят',
            'data' => $call->fresh(['table', 'acceptedBy']),
        ]);
    }

    public function completeCall(WaiterCall $call): JsonResponse
    {
        $call->complete();

        return response()->json([
            'success' => true,
            'message' => 'Вызов выполнен',
        ]);
    }

    // ==========================================
    // ОТЗЫВЫ
    // ==========================================

    public function submitReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string',
            'order_number' => 'nullable|string',
            'guest_name' => 'nullable|string|max:100',
            'guest_phone' => 'nullable|string|max:20',
            'rating' => 'required|integer|min:1|max:5',
            'food_rating' => 'nullable|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'atmosphere_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $restaurantId = null;
        $tableId = null;
        $orderId = null;

        if (!empty($validated['code'])) {
            $qr = TableQrCode::where('code', $validated['code'])->first();
            if ($qr) {
                $restaurantId = $qr->restaurant_id;
                $tableId = $qr->table_id;
            }
        }

        if (!empty($validated['order_number'])) {
            // БЕЗОПАСНОСТЬ: Ищем заказ только в ресторане из QR-кода
            // чтобы предотвратить доступ к заказам других ресторанов
            $orderQuery = Order::where('order_number', $validated['order_number']);
            if ($restaurantId) {
                $orderQuery->where('restaurant_id', $restaurantId);
            }
            $order = $orderQuery->first();
            if ($order) {
                $orderId = $order->id;
                $restaurantId = $restaurantId ?? $order->restaurant_id;
            }
        }

        // Если не удалось определить ресторан - ошибка
        if (!$restaurantId) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось определить ресторан. Отсканируйте QR код или укажите номер заказа.',
            ], 400);
        }

        $review = Review::create([
            'restaurant_id' => $restaurantId,
            'table_id' => $tableId,
            'order_id' => $orderId,
            'guest_name' => $validated['guest_name'] ?? null,
            'guest_phone' => $validated['guest_phone'] ?? null,
            'rating' => $validated['rating'],
            'food_rating' => $validated['food_rating'] ?? null,
            'service_rating' => $validated['service_rating'] ?? null,
            'atmosphere_rating' => $validated['atmosphere_rating'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'source' => 'qr',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Спасибо за ваш отзыв!',
            'data' => $review,
        ]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $query = Review::with(['table', 'order'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('published')) {
            $query->where('is_published', $request->boolean('published'));
        }

        if ($request->has('rating') && $request->input('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        $reviews = $query->recent()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    public function reviewStats(Request $request): JsonResponse
    {
        $stats = Review::getStats($this->getRestaurantId($request));

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function toggleReview(Review $review): JsonResponse
    {
        $review->update(['is_published' => !$review->is_published]);

        return response()->json([
            'success' => true,
            'message' => $review->is_published ? 'Отзыв опубликован' : 'Отзыв скрыт',
        ]);
    }

    public function respondToReview(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        $review->update(['admin_response' => $validated['response']]);

        return response()->json([
            'success' => true,
            'message' => 'Ответ сохранён',
        ]);
    }

    // ==========================================
    // QR-КОДЫ
    // ==========================================

    public function qrCodes(Request $request): JsonResponse
    {
        $codes = TableQrCode::with(['table.zone'])
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    public function generateQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $existing = TableQrCode::where('table_id', $validated['table_id'])->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'QR-код уже существует',
                'data' => $existing->load('table'),
            ]);
        }

        $qr = TableQrCode::createForTable($validated['table_id'], $restaurantId);

        return response()->json([
            'success' => true,
            'message' => 'QR-код создан',
            'data' => $qr->load('table'),
        ]);
    }

    public function generateAllQr(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        
        $tables = Table::where('restaurant_id', $restaurantId)
            ->whereDoesntHave('qrCode')
            ->get();

        $created = 0;
        foreach ($tables as $table) {
            TableQrCode::createForTable($table->id, $restaurantId);
            $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "Создано QR-кодов: $created",
        ]);
    }

    public function regenerateQr(TableQrCode $qrCode): JsonResponse
    {
        $qrCode->update([
            'code' => TableQrCode::generateCode(),
            'scan_count' => 0,
            'last_scanned_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR-код обновлён',
            'data' => $qrCode->fresh('table'),
        ]);
    }

    public function toggleQr(TableQrCode $qrCode): JsonResponse
    {
        $qrCode->update(['is_active' => !$qrCode->is_active]);

        return response()->json([
            'success' => true,
            'message' => $qrCode->is_active ? 'QR-код активирован' : 'QR-код деактивирован',
        ]);
    }

    // ==========================================
    // НАСТРОЙКИ
    // ==========================================

    public function settings(Request $request): JsonResponse
    {
        $settings = GuestMenuSetting::getAll($this->getRestaurantId($request));

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

        $restaurantId = $this->getRestaurantId($request);

        foreach ($validated['settings'] as $key => $value) {
            GuestMenuSetting::set($key, $value, $restaurantId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
        ]);
    }
}