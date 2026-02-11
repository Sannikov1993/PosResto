<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffTipController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список чаевых
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tip::with(['user', 'order'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        $tips = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $tips,
        ]);
    }

    /**
     * Добавить чаевые
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:cash,card,shared',
            'order_id' => 'nullable|integer|exists:orders,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $tip = Tip::create([
            'restaurant_id' => $this->getRestaurantId($request),
            'user_id' => $validated['user_id'],
            'order_id' => $validated['order_id'] ?? null,
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'date' => Carbon::today(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Чаевые добавлены',
            'data' => $tip->load('user'),
        ], 201);
    }
}
