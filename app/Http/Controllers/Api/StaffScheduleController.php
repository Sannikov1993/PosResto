<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffSchedule;
use App\Models\ScheduleTemplate;
use App\Models\User;
use App\Services\StaffNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StaffScheduleController extends Controller
{
    protected StaffNotificationService $notificationService;

    public function __construct(StaffNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get week schedule
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id ?? auth()->user()->restaurant_id;
        $weekStart = $request->week_start ? Carbon::parse($request->week_start)->startOfWeek() : now()->startOfWeek();

        $schedules = StaffSchedule::forRestaurant($restaurantId)
            ->forDateRange($weekStart, $weekStart->copy()->endOfWeek())
            ->with(['user:id,name,role,avatar', 'creator:id,name'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Get staff list for the restaurant
        $staff = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereIn('role', ['waiter', 'bartender', 'cook', 'hostess', 'courier', 'cashier', 'manager'])
            ->select('id', 'name', 'role', 'avatar')
            ->orderBy('name')
            ->get();

        // Group schedules by date and user
        $byDate = [];
        for ($date = $weekStart->copy(); $date->lte($weekStart->copy()->endOfWeek()); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            // Compare as strings since 'date' is cast to Carbon
            $byDate[$dateKey] = $schedules->filter(function ($schedule) use ($dateKey) {
                return $schedule->date->format('Y-m-d') === $dateKey;
            })->values();
        }

        // Check if any drafts exist
        $hasDrafts = $schedules->where('status', 'draft')->isNotEmpty();

        return response()->json([
            'success' => true,
            'data' => [
                'schedules' => $byDate,
                'staff' => $staff,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekStart->copy()->endOfWeek()->format('Y-m-d'),
                'has_drafts' => $hasDrafts,
            ],
        ]);
    }

    /**
     * Create schedule entry
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:120',
            'position' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'template_id' => 'nullable|exists:schedule_templates,id',
        ]);

        $user = auth()->user();
        $restaurantId = $user->restaurant_id;

        // If template provided, use its values
        if (!empty($validated['template_id'])) {
            $template = ScheduleTemplate::forRestaurant($restaurantId)->find($validated['template_id']);
            if ($template) {
                $validated['start_time'] = Carbon::parse($template->start_time)->format('H:i');
                $validated['end_time'] = Carbon::parse($template->end_time)->format('H:i');
                $validated['break_minutes'] = $template->break_minutes;
            }
        }

        // Check for overlapping shifts
        $existingShift = StaffSchedule::where('user_id', $validated['user_id'])
            ->whereDate('date', $validated['date'])
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>', $validated['start_time']);
                });
            })
            ->first();

        if ($existingShift) {
            return response()->json([
                'success' => false,
                'message' => 'У сотрудника уже есть смена в это время',
            ], 422);
        }

        $schedule = StaffSchedule::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $validated['user_id'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'break_minutes' => $validated['break_minutes'] ?? 0,
            'position' => $validated['position'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => StaffSchedule::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $schedule->load('user:id,name,role,avatar');

        return response()->json([
            'success' => true,
            'message' => 'Смена добавлена',
            'data' => $schedule,
        ]);
    }

    /**
     * Update schedule entry
     */
    public function update(Request $request, StaffSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:120',
            'position' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check for overlapping shifts if time changed
        if (isset($validated['start_time']) || isset($validated['end_time']) || isset($validated['date']) || isset($validated['user_id'])) {
            $checkUserId = $validated['user_id'] ?? $schedule->user_id;
            $checkDate = $validated['date'] ?? $schedule->date->format('Y-m-d');
            $checkStart = $validated['start_time'] ?? Carbon::parse($schedule->start_time)->format('H:i');
            $checkEnd = $validated['end_time'] ?? Carbon::parse($schedule->end_time)->format('H:i');

            $existingShift = StaffSchedule::where('user_id', $checkUserId)
                ->whereDate('date', $checkDate)
                ->where('id', '!=', $schedule->id)
                ->where(function ($query) use ($checkStart, $checkEnd) {
                    $query->where(function ($q) use ($checkStart, $checkEnd) {
                        $q->where('start_time', '<', $checkEnd)
                          ->where('end_time', '>', $checkStart);
                    });
                })
                ->first();

            if ($existingShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'У сотрудника уже есть смена в это время',
                ], 422);
            }
        }

        $schedule->update($validated);
        $schedule->load('user:id,name,role,avatar');

        return response()->json([
            'success' => true,
            'message' => 'Смена обновлена',
            'data' => $schedule,
        ]);
    }

    /**
     * Delete schedule entry
     */
    public function destroy(StaffSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Смена удалена',
        ]);
    }

    /**
     * Publish schedules for a week
     */
    public function publishWeek(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'required|date',
        ]);

        $user = auth()->user();
        $restaurantId = $user->restaurant_id;
        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Get all draft schedules for the week
        $drafts = StaffSchedule::forRestaurant($restaurantId)
            ->forDateRange($weekStart, $weekEnd)
            ->draft()
            ->with('user')
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет неопубликованных смен',
            ], 422);
        }

        // Publish all drafts
        $count = StaffSchedule::publishWeek($restaurantId, $weekStart);

        // Send notifications to affected users
        $affectedUsers = $drafts->pluck('user')->unique('id');
        $periodFormatted = $weekStart->format('d.m') . ' - ' . $weekEnd->format('d.m.Y');

        foreach ($affectedUsers as $staffUser) {
            if ($staffUser) {
                $this->notificationService->notifySchedulePublished($staffUser, $periodFormatted);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Опубликовано смен: {$count}",
            'data' => [
                'published_count' => $count,
                'notified_users' => $affectedUsers->count(),
            ],
        ]);
    }

    /**
     * Copy schedules from one week to another
     */
    public function copyWeek(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_week' => 'required|date',
            'to_week' => 'required|date',
        ]);

        $user = auth()->user();
        $restaurantId = $user->restaurant_id;

        $count = StaffSchedule::copyWeek(
            $restaurantId,
            $validated['from_week'],
            $validated['to_week'],
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' => "Скопировано смен: {$count}",
            'data' => ['copied_count' => $count],
        ]);
    }

    /**
     * Get schedule templates
     */
    public function templates(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id ?? auth()->user()->restaurant_id;

        $templates = ScheduleTemplate::forRestaurant($restaurantId)
            ->active()
            ->orderBy('start_time')
            ->get();

        // Create defaults if none exist
        if ($templates->isEmpty()) {
            ScheduleTemplate::createDefaults($restaurantId);
            $templates = ScheduleTemplate::forRestaurant($restaurantId)->active()->orderBy('start_time')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create schedule template
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:120',
            'color' => 'nullable|string|max:20',
        ]);

        $template = ScheduleTemplate::create([
            'restaurant_id' => auth()->user()->restaurant_id,
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'break_minutes' => $validated['break_minutes'] ?? 0,
            'color' => $validated['color'] ?? '#f97316',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Шаблон создан',
            'data' => $template,
        ]);
    }

    /**
     * Update schedule template
     */
    public function updateTemplate(Request $request, ScheduleTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:120',
            'color' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Шаблон обновлён',
            'data' => $template,
        ]);
    }

    /**
     * Delete schedule template
     */
    public function destroyTemplate(ScheduleTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Шаблон удалён',
        ]);
    }

    /**
     * Get my upcoming schedules (for staff app)
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $user = auth()->user();

        $schedules = StaffSchedule::forUser($user->id)
            ->published()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($request->limit ?? 14)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Get statistics for the week
     */
    public function weekStats(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id ?? auth()->user()->restaurant_id;
        $weekStart = $request->week_start ? Carbon::parse($request->week_start)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $schedules = StaffSchedule::forRestaurant($restaurantId)
            ->forDateRange($weekStart, $weekEnd)
            ->with('user:id,name,role')
            ->get();

        // Total hours by user
        $hoursByUser = $schedules->groupBy('user_id')->map(function ($userSchedules) {
            return [
                'user' => $userSchedules->first()?->user,
                'total_hours' => $userSchedules->sum('work_hours'),
                'shifts_count' => $userSchedules->count(),
            ];
        })->values();

        // Total hours by day
        $hoursByDay = [];
        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $daySchedules = $schedules->filter(function ($schedule) use ($dateKey) {
                return $schedule->date->format('Y-m-d') === $dateKey;
            });
            $hoursByDay[$dateKey] = [
                'total_hours' => $daySchedules->sum('work_hours'),
                'shifts_count' => $daySchedules->count(),
                'staff_count' => $daySchedules->unique('user_id')->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_shifts' => $schedules->count(),
                'total_hours' => round($schedules->sum('work_hours'), 1),
                'draft_count' => $schedules->where('status', 'draft')->count(),
                'published_count' => $schedules->where('status', 'published')->count(),
                'hours_by_user' => $hoursByUser,
                'hours_by_day' => $hoursByDay,
            ],
        ]);
    }
}
