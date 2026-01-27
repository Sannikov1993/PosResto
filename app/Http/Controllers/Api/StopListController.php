<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\StopList;
use App\Models\RealtimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StopListController extends Controller
{
    /**
     * Получить список блюд в стоп-листе
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $stopList = StopList::with(['dish.category', 'stoppedByUser'])
            ->where('restaurant_id', $restaurantId)
            ->active()
            ->orderBy('stopped_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'dish_id' => $item->dish_id,
                    'dish' => $item->dish ? [
                        'id' => $item->dish->id,
                        'name' => $item->dish->name,
                        'price' => $item->dish->price,
                        'image' => $item->dish->image,
                        'category' => $item->dish->category ? [
                            'id' => $item->dish->category->id,
                            'name' => $item->dish->category->name,
                            'color' => $item->dish->category->color,
                        ] : null,
                    ] : null,
                    'reason' => $item->reason,
                    'stopped_at' => $item->stopped_at?->format('Y-m-d H:i:s'),
                    'resume_at' => $item->resume_at?->format('Y-m-d H:i:s'),
                    'stopped_by' => $item->stoppedByUser ? [
                        'id' => $item->stoppedByUser->id,
                        'name' => $item->stoppedByUser->name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stopList,
            'count' => $stopList->count(),
        ]);
    }

    /**
     * Добавить блюдо в стоп-лист
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'reason' => 'nullable|string|max:255',
            'resume_at' => 'nullable|date',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $dish = Dish::findOrFail($validated['dish_id']);

        // Проверяем, что блюдо принадлежит этому ресторану
        if ($dish->restaurant_id !== $restaurantId) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо не принадлежит данному ресторану',
            ], 403);
        }

        // Проверяем, не в активном стоп-листе ли уже
        $activeEntry = StopList::where('restaurant_id', $restaurantId)
            ->where('dish_id', $dish->id)
            ->active()
            ->first();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо уже в стоп-листе',
            ], 422);
        }

        // Ищем любую существующую запись (включая истёкшие) для обновления
        $existing = StopList::where('restaurant_id', $restaurantId)
            ->where('dish_id', $dish->id)
            ->first();

        if ($existing) {
            // Обновляем существующую запись
            $existing->update([
                'reason' => $validated['reason'] ?? null,
                'stopped_at' => now(),
                'resume_at' => $validated['resume_at'] ?? null,
                'stopped_by' => auth()->id(),
            ]);
            $stopListEntry = $existing;
        } else {
            // Создаём новую запись
            $stopListEntry = StopList::create([
                'restaurant_id' => $restaurantId,
                'dish_id' => $dish->id,
                'reason' => $validated['reason'] ?? null,
                'stopped_at' => now(),
                'resume_at' => $validated['resume_at'] ?? null,
                'stopped_by' => auth()->id(),
            ]);
        }

        // Отправляем real-time событие
        $this->broadcastStopListChange($restaurantId, 'added', $dish);

        $stopListEntry->load(['dish.category', 'stoppedByUser']);

        return response()->json([
            'success' => true,
            'message' => "Блюдо «{$dish->name}» добавлено в стоп-лист",
            'data' => [
                'id' => $stopListEntry->id,
                'dish_id' => $stopListEntry->dish_id,
                'dish' => [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'category' => $dish->category ? [
                        'id' => $dish->category->id,
                        'name' => $dish->category->name,
                    ] : null,
                ],
                'reason' => $stopListEntry->reason,
                'stopped_at' => $stopListEntry->stopped_at->format('Y-m-d H:i:s'),
                'resume_at' => $stopListEntry->resume_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    /**
     * Обновить запись в стоп-листе
     */
    public function update(Request $request, Dish $dish): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'resume_at' => 'nullable|date',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        $entry = StopList::where('restaurant_id', $restaurantId)
            ->where('dish_id', $dish->id)
            ->active()
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо не найдено в стоп-листе',
            ], 404);
        }

        $entry->update([
            'reason' => $validated['reason'] ?? $entry->reason,
            'resume_at' => array_key_exists('resume_at', $validated) ? $validated['resume_at'] : $entry->resume_at,
        ]);

        // Отправляем real-time событие
        $this->broadcastStopListChange($restaurantId, 'updated', $dish);

        $entry->load(['dish.category', 'stoppedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Запись обновлена',
            'data' => [
                'id' => $entry->id,
                'dish_id' => $entry->dish_id,
                'dish' => $entry->dish ? [
                    'id' => $entry->dish->id,
                    'name' => $entry->dish->name,
                    'price' => $entry->dish->price,
                    'category' => $entry->dish->category ? [
                        'id' => $entry->dish->category->id,
                        'name' => $entry->dish->category->name,
                    ] : null,
                ] : null,
                'reason' => $entry->reason,
                'stopped_at' => $entry->stopped_at?->format('Y-m-d H:i:s'),
                'resume_at' => $entry->resume_at?->format('Y-m-d H:i:s'),
                'stopped_by' => $entry->stoppedByUser ? [
                    'id' => $entry->stoppedByUser->id,
                    'name' => $entry->stoppedByUser->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Убрать блюдо из стоп-листа
     */
    public function destroy(Request $request, Dish $dish): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $entry = StopList::where('restaurant_id', $restaurantId)
            ->where('dish_id', $dish->id)
            ->active()
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо не найдено в стоп-листе',
            ], 404);
        }

        $entry->delete();

        // Отправляем real-time событие
        $this->broadcastStopListChange($restaurantId, 'removed', $dish);

        return response()->json([
            'success' => true,
            'message' => "Блюдо «{$dish->name}» убрано из стоп-листа",
        ]);
    }

    /**
     * Получить ID блюд в стоп-листе (для быстрой проверки)
     */
    public function dishIds(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $ids = StopList::where('restaurant_id', $restaurantId)
            ->active()
            ->pluck('dish_id');

        return response()->json([
            'success' => true,
            'data' => $ids,
        ]);
    }

    /**
     * Поиск блюд для добавления в стоп-лист
     */
    public function searchDishes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $search = $request->get('q', '');

        // Получаем ID блюд уже в стоп-листе
        $stopListIds = StopList::where('restaurant_id', $restaurantId)
            ->active()
            ->pluck('dish_id');

        $query = Dish::with('category')
            ->where('restaurant_id', $restaurantId)
            ->where('is_available', true)
            ->whereNotIn('id', $stopListIds);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $dishes = $query->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($dish) {
                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'image' => $dish->image,
                    'category' => $dish->category ? [
                        'id' => $dish->category->id,
                        'name' => $dish->category->name,
                        'color' => $dish->category->color,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $dishes,
        ]);
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(Request $request): int
    {
        if ($request->has('restaurant_id')) {
            return $request->restaurant_id;
        }
        if (auth()->check() && auth()->user()->restaurant_id) {
            return auth()->user()->restaurant_id;
        }
        return 1;
    }

    /**
     * Отправить real-time событие об изменении стоп-листа
     */
    protected function broadcastStopListChange(int $restaurantId, string $action, Dish $dish): void
    {
        try {
            RealtimeEvent::dispatch('global', 'stop_list_changed', [
                'restaurant_id' => $restaurantId,
                'action' => $action,
                'dish_id' => $dish->id,
                'dish_name' => $dish->name,
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки real-time
        }
    }
}
