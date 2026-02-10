<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashOperation;
use App\Models\CashShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD финансовых транзакций и категорий для бэк-офиса.
 *
 * Выделено из FinanceController для разделения ответственности.
 */
class FinanceTransactionController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список финансовых транзакций
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $operations = CashOperation::where('restaurant_id', $restaurantId)
            ->with('shift')
            ->orderByDesc('created_at')
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $operations,
        ]);
    }

    /**
     * Создать финансовую транзакцию
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense,deposit,withdrawal',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string|max:255',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $shift = CashShift::getCurrentShift($restaurantId);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Нет открытой смены',
            ], 400);
        }

        $operation = CashOperation::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? '',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $operation,
        ]);
    }

    /**
     * Обновить транзакцию
     */
    public function update(Request $request, CashOperation $transaction): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $transaction->update($validated);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * Удалить транзакцию
     */
    public function destroy(CashOperation $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Транзакция удалена',
        ]);
    }

    /**
     * Категории расходов/доходов
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = [
            ['id' => 1, 'name' => 'Продажи', 'type' => 'income', 'color' => '#10B981'],
            ['id' => 2, 'name' => 'Возвраты', 'type' => 'expense', 'color' => '#EF4444'],
            ['id' => 3, 'name' => 'Закупки', 'type' => 'expense', 'color' => '#F59E0B'],
            ['id' => 4, 'name' => 'Зарплаты', 'type' => 'expense', 'color' => '#8B5CF6'],
            ['id' => 5, 'name' => 'Аренда', 'type' => 'expense', 'color' => '#6366F1'],
            ['id' => 6, 'name' => 'Прочее', 'type' => 'expense', 'color' => '#6B7280'],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Создать категорию (заглушка)
     */
    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }

    /**
     * Обновить категорию (заглушка)
     */
    public function updateCategory(Request $request, int $category): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }

    /**
     * Удалить категорию (заглушка)
     */
    public function destroyCategory(int $category): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Категории расходов пока не реализованы',
        ], 501);
    }
}
