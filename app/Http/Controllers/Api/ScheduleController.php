<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * Get schedule for a month
     * GET /api/backoffice/attendance/schedule
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->header('X-Restaurant-ID');
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Get all employees
        $employees = User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->orderBy('name')
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $this->getInitials($user->name),
                'role' => $user->getRoleLabel(),
            ]);

        // Get schedule for the month
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $schedules = WorkSchedule::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        // Build schedule data
        $scheduleData = [];
        foreach ($employees as $emp) {
            $empSchedules = $schedules->get($emp['id'], collect());
            $scheduleData[$emp['id']] = [];

            foreach ($empSchedules as $schedule) {
                $day = Carbon::parse($schedule->date)->day;
                $scheduleData[$emp['id']][$day] = [
                    'id' => $schedule->id,
                    'template' => $schedule->template,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'break_minutes' => $schedule->break_minutes,
                    'hours' => $schedule->planned_hours,
                ];
            }
        }

        // Month info
        $monthNames = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'month_name' => $monthNames[$month] ?? '',
                'days_in_month' => $startDate->daysInMonth,
                'employees' => $employees,
                'schedule' => $scheduleData,
            ],
        ]);
    }

    /**
     * Save a single shift
     * POST /api/backoffice/attendance/schedule/shift
     */
    public function saveShift(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'template' => 'nullable|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:180',
        ]);

        $restaurantId = $request->header('X-Restaurant-ID');
        $date = Carbon::parse($request->input('date'));

        // Calculate hours
        $startTime = Carbon::parse($request->input('start_time'));
        $endTime = Carbon::parse($request->input('end_time'));

        // Handle overnight shifts
        if ($endTime <= $startTime) {
            $endTime->addDay();
        }

        $totalMinutes = $startTime->diffInMinutes($endTime);
        $breakMinutes = $request->input('break_minutes', 0);
        $workedMinutes = $totalMinutes - $breakMinutes;
        $plannedHours = round($workedMinutes / 60, 2);

        $schedule = WorkSchedule::updateOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'user_id' => $request->input('user_id'),
                'date' => $date->format('Y-m-d'),
            ],
            [
                'template' => $request->input('template'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'break_minutes' => $breakMinutes,
                'planned_hours' => $plannedHours,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Delete a shift
     * DELETE /api/backoffice/attendance/schedule/shift
     */
    public function deleteShift(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
        ]);

        $restaurantId = $request->header('X-Restaurant-ID');
        $date = Carbon::parse($request->input('date'));

        WorkSchedule::where('restaurant_id', $restaurantId)
            ->where('user_id', $request->input('user_id'))
            ->where('date', $date->format('Y-m-d'))
            ->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Bulk save shifts
     * POST /api/backoffice/attendance/schedule/bulk
     */
    public function bulkSaveShifts(Request $request): JsonResponse
    {
        $request->validate([
            'shifts' => 'required|array',
            'shifts.*.user_id' => 'required|integer|exists:users,id',
            'shifts.*.date' => 'required|date',
            'shifts.*.template' => 'nullable|string',
            'shifts.*.start_time' => 'required|date_format:H:i',
            'shifts.*.end_time' => 'required|date_format:H:i',
            'shifts.*.break_minutes' => 'nullable|integer|min:0|max:180',
        ]);

        $restaurantId = $request->header('X-Restaurant-ID');
        $shifts = $request->input('shifts');
        $saved = 0;

        foreach ($shifts as $shiftData) {
            $date = Carbon::parse($shiftData['date']);

            // Calculate hours
            $startTime = Carbon::parse($shiftData['start_time']);
            $endTime = Carbon::parse($shiftData['end_time']);

            if ($endTime <= $startTime) {
                $endTime->addDay();
            }

            $totalMinutes = $startTime->diffInMinutes($endTime);
            $breakMinutes = $shiftData['break_minutes'] ?? 0;
            $workedMinutes = $totalMinutes - $breakMinutes;
            $plannedHours = round($workedMinutes / 60, 2);

            WorkSchedule::updateOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'user_id' => $shiftData['user_id'],
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'template' => $shiftData['template'] ?? null,
                    'start_time' => $shiftData['start_time'],
                    'end_time' => $shiftData['end_time'],
                    'break_minutes' => $breakMinutes,
                    'planned_hours' => $plannedHours,
                ]
            );

            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => "Сохранено смен: {$saved}",
        ]);
    }

    /**
     * Copy first week to entire month
     * POST /api/backoffice/attendance/schedule/copy-week
     */
    public function copyWeek(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'source_week' => 'nullable|integer|between:1,5',
        ]);

        $restaurantId = $request->header('X-Restaurant-ID');
        $year = $request->input('year');
        $month = $request->input('month');

        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Get first week's shifts (days 1-7)
        $firstWeekShifts = WorkSchedule::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $startOfMonth->copy()->addDays(6)->format('Y-m-d')
            ])
            ->get();

        if ($firstWeekShifts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет смен в первой неделе для копирования',
            ], 400);
        }

        // Build template by day of week
        $weekTemplate = [];
        foreach ($firstWeekShifts as $shift) {
            $dayOfWeek = Carbon::parse($shift->date)->dayOfWeek;
            if (!isset($weekTemplate[$shift->user_id])) {
                $weekTemplate[$shift->user_id] = [];
            }
            $weekTemplate[$shift->user_id][$dayOfWeek] = [
                'template' => $shift->template,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'break_minutes' => $shift->break_minutes,
                'planned_hours' => $shift->planned_hours,
            ];
        }

        // Apply template to remaining weeks
        $copied = 0;
        $currentDate = $startOfMonth->copy()->addWeek();

        while ($currentDate <= $endOfMonth) {
            $dayOfWeek = $currentDate->dayOfWeek;

            foreach ($weekTemplate as $userId => $shifts) {
                if (isset($shifts[$dayOfWeek])) {
                    $shiftData = $shifts[$dayOfWeek];

                    WorkSchedule::updateOrCreate(
                        [
                            'restaurant_id' => $restaurantId,
                            'user_id' => $userId,
                            'date' => $currentDate->format('Y-m-d'),
                        ],
                        $shiftData
                    );

                    $copied++;
                }
            }

            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'message' => "Скопировано смен: {$copied}",
        ]);
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
        }
        return mb_substr($name, 0, 2);
    }
}
