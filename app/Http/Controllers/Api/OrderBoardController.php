<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Order\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderBoardController extends Controller
{
    use Traits\ResolvesRestaurantId;
    /**
     * Публичное табло заказов для клиентов (без авторизации).
     * Возвращает минимум полей для максимально лёгкого ответа.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereIn('status', [OrderStatus::COOKING->value, OrderStatus::READY->value])
            ->whereDate('created_at', today())
            ->select([
                'id',
                'daily_number',
                'order_number',
                'status',
                'type',
                'created_at',
                'cooking_started_at',
                'ready_at',
            ])
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }
}
