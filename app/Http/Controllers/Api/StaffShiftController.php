<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffShiftController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список смен
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::with(['user'])
            ->where('restaurant_id', $this->getRestaurantId($request));

        // Фильтр по дате
        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Фильтр по неделе
        if ($request->has('week_of')) {
            $date = Carbon::parse($request->input('week_of'));
            $query->whereBetween('date', [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek(),
            ]);
        }

        // Фильтр по сотруднику
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $shifts = $query->orderBy('date')->orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Создать смену
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        // Проверяем пересечение с другими сменами
        $overlap = Shift::where('user_id', $validated['user_id'])
            ->whereDate('date', $validated['date'])
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']]);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Смена пересекается с другой сменой этого сотрудника',
            ], 422);
        }

        $shift = Shift::create([
            'restaurant_id' => $this->getRestaurantId($request),
            'user_id' => $validated['user_id'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->input('created_by'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена создана',
            'data' => $shift->load('user'),
        ], 201);
    }

    /**
     * Обновить смену
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('manageSchedule', $shift->user);

        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'status' => 'sometimes|in:scheduled,confirmed,cancelled',
            'notes' => 'nullable|string|max:500',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Смена обновлена',
            'data' => $shift->fresh('user'),
        ]);
    }

    /**
     * Удалить смену
     */
    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('manageSchedule', $shift->user);

        if ($shift->status === 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить активную смену',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Смена удалена',
        ]);
    }

    /**
     * Расписание на неделю
     */
    public function weekSchedule(Request $request): JsonResponse
    {
        $weekOf = $request->input('week_of', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($weekOf);
        $restaurantId = $this->getRestaurantId($request);

        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        // Получаем всех активных сотрудников
        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereIn('role', ['waiter', 'cook', 'cashier', 'manager'])
            ->orderBy('name')
            ->get();

        // Получаем смены за неделю
        $shifts = Shift::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->groupBy('user_id');

        // Формируем расписание
        $schedule = $users->map(function ($user) use ($shifts, $weekStart) {
            $userShifts = $shifts->get($user->id, collect());
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $dayDate = $weekStart->copy()->addDays($i)->format('Y-m-d');
                $dayShift = $userShifts->firstWhere('date', $dayDate);
                $days[] = [
                    'date' => $dayDate,
                    'shift' => $dayShift,
                ];
            }

            return [
                'user' => $user,
                'days' => $days,
                'total_hours' => $userShifts->sum('duration_hours'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'schedule' => $schedule,
            ],
        ]);
    }
}
