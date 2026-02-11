<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffTimeTrackingController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Отметка прихода
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'method' => 'nullable|in:manual,pin,qr',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $method = $validated['method'] ?? 'manual';

        // Проверяем активную смену
        $activeEntry = TimeEntry::where('user_id', $validated['user_id'])
            ->where('status', 'active')
            ->first();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Уже есть активная смена. Сначала завершите её.',
                'data' => $activeEntry,
            ], 422);
        }

        // Ищем запланированную смену на сегодня
        $scheduledShift = Shift::where('user_id', $validated['user_id'])
            ->whereDate('date', Carbon::today())
            ->where('status', 'scheduled')
            ->first();

        $entry = TimeEntry::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'shift_id' => $scheduledShift?->id,
            'date' => Carbon::today(),
            'clock_in' => now(),
            'status' => 'active',
            'clock_in_method' => $method,
        ]);

        // Обновляем статус смены
        if ($scheduledShift) {
            $scheduledShift->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Отмечено начало работы',
            'data' => $entry->load('user'),
        ]);
    }

    /**
     * Отметка ухода
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'method' => 'nullable|in:manual,pin,qr',
        ]);

        $entry = TimeEntry::where('user_id', $validated['user_id'])
            ->where('status', 'active')
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активной смены',
            ], 422);
        }

        $entry->clockOut($validated['method'] ?? 'manual');

        return response()->json([
            'success' => true,
            'message' => 'Отмечено окончание работы',
            'data' => $entry->fresh('user'),
        ]);
    }

    /**
     * История учёта времени
     */
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::with(['user', 'shift'])
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

        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        $entries = $query->orderByDesc('date')->orderByDesc('clock_in')->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Кто сейчас работает
     */
    public function whoIsWorking(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $activeEntries = TimeEntry::with('user')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(function ($entry) {
                return [
                    'user' => $entry->user,
                    'clock_in' => $entry->clock_in->format('H:i'),
                    'worked_hours' => $entry->worked_hours,
                    'entry_id' => $entry->id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $activeEntries,
        ]);
    }
}
