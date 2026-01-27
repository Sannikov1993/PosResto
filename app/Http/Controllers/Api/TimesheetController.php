<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSession;
use App\Models\WorkDayOverride;
use App\Models\StaffSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimesheetController extends Controller
{
    /**
     * Получить табель сотрудников за месяц (в стиле Saby)
     * GET /api/backoffice/timesheet
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Ленивое автозакрытие: закрываем смены старше 18 часов (fallback для cron)
        $this->autoCloseStaleSessionsForRestaurant($restaurantId);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        // Получаем всех активных сотрудников ресторана
        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'avatar']);

        // Получаем все рабочие сессии за месяц (все статусы)
        $sessions = WorkSession::where('restaurant_id', $restaurantId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->whereIn('status', [
                WorkSession::STATUS_COMPLETED,
                WorkSession::STATUS_ACTIVE,
                WorkSession::STATUS_CORRECTED,
                WorkSession::STATUS_AUTO_CLOSED,
            ])
            ->get();

        // Получаем расписание (план) за месяц
        $schedules = StaffSchedule::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Получаем все overrides (ручные переопределения дней)
        $allOverrides = WorkDayOverride::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Группируем данные по сотрудникам
        $employees = [];
        $unclosedSessions = []; // Для уведомлений админа

        foreach ($users as $user) {
            $userSessions = $sessions->where('user_id', $user->id);
            $userSchedules = $schedules->where('user_id', $user->id);
            $userOverrides = $allOverrides->where('user_id', $user->id)->keyBy(fn($o) => $o->date->format('Y-m-d'));

            // Считаем часы по дням
            $dailyHours = [];
            $totalWorked = 0;
            $daysWorked = 0;
            $hasActiveSession = false;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                $dateStr = $date->toDateString();

                // Проверяем есть ли override для этого дня
                $override = $userOverrides->get($dateStr);

                // Находим сессии за этот день
                $daySessions = $userSessions->filter(function ($session) use ($dateStr) {
                    return $session->clock_in->toDateString() === $dateStr;
                });

                // Проверяем статусы сессий
                $dayHasActive = false;
                $dayHasAutoClosed = false;
                $dayHours = 0;

                foreach ($daySessions as $session) {
                    if ($session->status === WorkSession::STATUS_ACTIVE) {
                        $dayHasActive = true;
                        $hasActiveSession = true;

                        // Считаем часы от начала смены до сейчас (max 14ч, min 0)
                        $hoursFromStart = max(0, $session->clock_in->diffInMinutes(now(), false) / 60);
                        $sessionHours = min($hoursFromStart, 14);

                        $dayHours += $sessionHours;

                        // Добавляем в уведомления, если сессия открыта более 12 часов
                        if ($hoursFromStart > 12) {
                            $unclosedSessions[] = [
                                'session_id' => $session->id,
                                'user_id' => $user->id,
                                'user_name' => $user->name,
                                'clock_in' => $session->clock_in->format('d.m.Y H:i'),
                                'hours_open' => round($hoursFromStart, 1),
                                'date' => $dateStr,
                            ];
                        }
                    } elseif ($session->status === WorkSession::STATUS_AUTO_CLOSED) {
                        // Автозакрытая смена - часы = 0, но есть приход
                        $dayHasAutoClosed = true;
                        // hours_worked = 0, не добавляем к dayHours
                    } else {
                        // Обычная или скорректированная смена
                        $dayHours += max(0, $session->hours_worked); // Защита от отрицательных
                    }
                }

                // Если есть override, используем его часы (кроме активных сессий)
                if ($override && !$dayHasActive) {
                    $dayHours = $override->hours;
                }

                if ($dayHours > 0 || $dayHasActive || $dayHasAutoClosed) {
                    $dailyHours[$day] = [
                        'hours' => round(max(0, $dayHours), 2),
                        'formatted' => $this->formatHours(max(0, $dayHours)),
                        'has_override' => $override !== null,
                        'has_active' => $dayHasActive, // Флаг незакрытой сессии
                        'has_auto_closed' => $dayHasAutoClosed, // Автозакрытая (забыл уйти)
                    ];
                    $totalWorked += max(0, $dayHours);
                    if ($dayHours > 0) {
                        $daysWorked++;
                    }
                }
            }

            // План из расписания
            $totalPlanned = $userSchedules->sum(function ($schedule) {
                if ($schedule->start_time && $schedule->end_time) {
                    $start = Carbon::parse($schedule->start_time);
                    $end = Carbon::parse($schedule->end_time);
                    return $start->diffInMinutes($end) / 60;
                }
                return 8; // По умолчанию 8 часов
            });

            // Стандартный план: рабочие дни по 8 часов (если нет расписания)
            if ($totalPlanned == 0) {
                $workDays = 0;
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($year, $month, $day);
                    if (!$date->isWeekend()) {
                        $workDays++;
                    }
                }
                $totalPlanned = $workDays * 8;
            }

            $plannedDays = $totalPlanned / 8;
            $underworked = max(0, $totalPlanned - $totalWorked);

            $employees[] = [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'initials' => $this->getInitials($user->name),
                'daily_hours' => $dailyHours,
                'total_worked' => round($totalWorked, 2),
                'total_worked_formatted' => $this->formatHours($totalWorked),
                'days_worked' => $daysWorked,
                'total_planned' => round($totalPlanned, 2),
                'total_planned_formatted' => $this->formatHours($totalPlanned),
                'planned_days' => round($plannedDays),
                'underworked' => round($underworked, 2),
                'underworked_formatted' => $this->formatHours($underworked),
                'has_active_session' => $hasActiveSession, // Есть ли незакрытая сессия
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'days_in_month' => $daysInMonth,
                'employees' => $employees,
                'unclosed_sessions' => $unclosedSessions, // Уведомления о незакрытых сменах
            ],
        ]);
    }

    /**
     * Создать рабочую сессию вручную
     * POST /api/backoffice/attendance/sessions
     */
    public function createSession(Request $request): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
        ]);

        // Проверяем что пользователь принадлежит ресторану
        $user = User::where('id', $validated['user_id'])
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        // Формируем даты
        $date = Carbon::parse($validated['date']);
        $clockIn = Carbon::parse($validated['date'] . ' ' . $validated['clock_in']);
        $clockOut = null;
        $hoursWorked = 0;
        $status = WorkSession::STATUS_ACTIVE;

        $isOvernight = false;
        if (!empty($validated['clock_out'])) {
            $clockOut = Carbon::parse($validated['date'] . ' ' . $validated['clock_out']);
            // Если clock_out раньше clock_in, значит это ночная смена
            if ($clockOut->lt($clockIn)) {
                $clockOut->addDay();
                $isOvernight = true;
            }
            $hoursWorked = $clockIn->diffInMinutes($clockOut) / 60;
            $status = WorkSession::STATUS_COMPLETED;
        }

        // Создаём сессию (is_manual=true - биометрия не будет менять эту смену)
        $session = WorkSession::create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurantId,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'hours_worked' => $hoursWorked,
            'status' => $status,
            'is_manual' => true,
            'notes' => 'Добавлено вручную',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $session->id,
                'clock_in' => $session->clock_in->format('H:i'),
                'clock_out' => $session->clock_out?->format('H:i'),
                'hours' => round($session->hours_worked, 2),
                'status' => $session->status,
                'is_overnight' => $isOvernight,
            ],
        ]);
    }

    /**
     * Удалить рабочую сессию
     * DELETE /api/backoffice/attendance/sessions/{id}
     */
    public function deleteSession(int $id): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $session = WorkSession::where('id', $id)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'error' => 'session_not_found'], 404);
        }

        $session->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Закрыть активную сессию (установить время ухода)
     * PUT /api/backoffice/attendance/sessions/{id}/close
     */
    public function closeSession(Request $request, int $id): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $validated = $request->validate([
            'clock_out' => 'required|date_format:H:i',
        ]);

        $session = WorkSession::where('id', $id)
            ->where('restaurant_id', $restaurantId)
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'error' => 'session_not_found'], 404);
        }

        // Формируем время ухода
        $clockIn = $session->clock_in;
        $clockOut = Carbon::parse($clockIn->toDateString() . ' ' . $validated['clock_out']);

        // Ночная смена: если clock_out раньше clock_in
        $isOvernight = false;
        if ($clockOut->lt($clockIn)) {
            $clockOut->addDay();
            $isOvernight = true;
        }

        $hoursWorked = $clockIn->diffInMinutes($clockOut) / 60;

        $session->update([
            'clock_out' => $clockOut,
            'hours_worked' => $hoursWorked,
            'status' => WorkSession::STATUS_CORRECTED,
            'is_manual' => true, // Биометрия больше не будет менять эту смену
            'notes' => ($session->notes ? $session->notes . '; ' : '') . 'Закрыто вручную',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $session->id,
                'clock_in' => $session->clock_in->format('H:i'),
                'clock_out' => $session->clock_out->format('H:i'),
                'hours' => round($session->hours_worked, 2),
                'status' => $session->status,
                'is_overnight' => $isOvernight,
            ],
        ]);
    }

    /**
     * Получить детальный табель одного сотрудника
     * GET /api/backoffice/timesheet/{userId}
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $user = User::where('id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        // Получаем сессии с деталями (все статусы)
        $sessions = WorkSession::where('user_id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->whereIn('status', [
                WorkSession::STATUS_COMPLETED,
                WorkSession::STATUS_ACTIVE,
                WorkSession::STATUS_CORRECTED,
                WorkSession::STATUS_AUTO_CLOSED,
            ])
            ->orderBy('clock_in')
            ->get();

        // Получаем overrides (типы дней)
        $overrides = WorkDayOverride::where('user_id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn($o) => $o->date->format('Y-m-d'));

        // Формируем календарь
        $calendar = [];
        $totalWorked = 0;
        $daysWorked = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateStr = $date->toDateString();

            $daySessions = $sessions->filter(function ($session) use ($dateStr) {
                return $session->clock_in->toDateString() === $dateStr;
            });

            // Проверяем есть ли override для этого дня
            $override = $overrides->get($dateStr);

            // Обрабатываем сессии
            $dayHasActive = false;
            $dayHasAutoClosed = false;
            $dayHours = 0;
            $mappedSessions = $daySessions->map(function ($s) use (&$dayHasActive, &$dayHasAutoClosed, &$dayHours) {
                $isOvernight = $s->clock_out && $s->clock_out->toDateString() !== $s->clock_in->toDateString();
                $isActive = $s->status === WorkSession::STATUS_ACTIVE;
                $isAutoClosed = $s->status === WorkSession::STATUS_AUTO_CLOSED;
                $hours = max(0, $s->hours_worked); // Защита от отрицательных

                if ($isActive) {
                    $dayHasActive = true;
                    // Считаем часы от начала смены до сейчас (max 14ч, min 0)
                    $hoursFromStart = max(0, $s->clock_in->diffInMinutes(now(), false) / 60);
                    $hours = min($hoursFromStart, 14);
                } elseif ($isAutoClosed) {
                    $dayHasAutoClosed = true;
                    $hours = 0; // Автозакрытая смена - часы = 0
                }

                $dayHours += $hours;

                return [
                    'id' => $s->id,
                    'clock_in' => $s->clock_in->format('H:i'),
                    'clock_out' => $s->clock_out?->format('H:i'),
                    'hours' => round($hours, 2),
                    'status' => $s->status,
                    'is_manual' => str_contains($s->notes ?? '', 'вручную'),
                    'is_overnight' => $isOvernight,
                    'is_active' => $isActive,
                    'is_auto_closed' => $isAutoClosed, // Автозакрыто (забыл уйти)
                ];
            })->values();

            // Если есть override и нет активных сессий - используем часы override
            if ($override && !$dayHasActive) {
                $dayHours = $override->hours;
            }

            $calendar[$day] = [
                'date' => $dateStr,
                'day_of_week' => $date->dayOfWeek,
                'is_weekend' => $date->isWeekend(),
                'hours' => round(max(0, $dayHours), 2),
                'formatted' => $dayHours > 0 ? $this->formatHoursShort($dayHours) : null,
                'has_active' => $dayHasActive,
                'has_auto_closed' => $dayHasAutoClosed, // Был приход, но забыл уйти
                'sessions' => $mappedSessions,
                'override' => $override ? [
                    'id' => $override->id,
                    'type' => $override->type,
                    'type_label' => WorkDayOverride::getTypeLabel($override->type),
                    'type_color' => WorkDayOverride::getTypeColor($override->type),
                    'start_time' => $override->start_time,
                    'end_time' => $override->end_time,
                    'hours' => round($override->hours, 2),
                    'notes' => $override->notes,
                ] : null,
            ];

            if ($dayHours > 0) {
                $totalWorked += $dayHours;
                $daysWorked++;
            }
        }

        // План
        $workDays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            if (!$date->isWeekend()) {
                $workDays++;
            }
        }
        $totalPlanned = $workDays * 8;
        $underworked = max(0, $totalPlanned - $totalWorked);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'initials' => $this->getInitials($user->name),
                ],
                'year' => $year,
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'calendar' => $calendar,
                'day_types' => WorkDayOverride::getAllTypes(),
                'summary' => [
                    'total_worked' => round($totalWorked, 2),
                    'total_worked_formatted' => $this->formatHours($totalWorked),
                    'days_worked' => $daysWorked,
                    'total_planned' => round($totalPlanned, 2),
                    'total_planned_formatted' => $this->formatHours($totalPlanned),
                    'planned_days' => $workDays,
                    'underworked' => round($underworked, 2),
                    'underworked_formatted' => $this->formatHours($underworked),
                ],
            ],
        ]);
    }

    /**
     * Установить тип дня (override)
     * POST /api/backoffice/attendance/day-override
     */
    public function setDayOverride(Request $request): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'type' => 'required|in:shift,day_off,vacation,sick_leave,absence',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'hours' => 'nullable|numeric|min:0|max:24',
            'notes' => 'nullable|string|max:255',
        ]);

        // Проверяем что пользователь принадлежит ресторану
        $user = User::where('id', $validated['user_id'])
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        // Считаем часы
        $hours = 0;
        if (in_array($validated['type'], WorkDayOverride::TYPES_WITH_HOURS)) {
            if (!empty($validated['hours'])) {
                $hours = $validated['hours'];
            } elseif (!empty($validated['start_time']) && !empty($validated['end_time'])) {
                $start = Carbon::parse($validated['start_time']);
                $end = Carbon::parse($validated['end_time']);
                // Ночная смена
                if ($end->lt($start)) {
                    $end->addDay();
                }
                $hours = $start->diffInMinutes($end) / 60;
            } elseif ($validated['type'] === WorkDayOverride::TYPE_VACATION ||
                      $validated['type'] === WorkDayOverride::TYPE_SICK_LEAVE) {
                $hours = 8; // Стандартные 8 часов для отпуска/больничного
            }
        }

        // Создаём или обновляем override
        $override = WorkDayOverride::where('user_id', $user->id)
            ->where('restaurant_id', $restaurantId)
            ->whereDate('date', $validated['date'])
            ->first();

        if ($override) {
            $override->update([
                'type' => $validated['type'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'hours' => $hours,
                'notes' => $validated['notes'] ?? null,
            ]);
        } else {
            $override = WorkDayOverride::create([
                'user_id' => $user->id,
                'restaurant_id' => $restaurantId,
                'date' => $validated['date'],
                'type' => $validated['type'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'hours' => $hours,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $override->id,
                'type' => $override->type,
                'type_label' => WorkDayOverride::getTypeLabel($override->type),
                'type_color' => WorkDayOverride::getTypeColor($override->type),
                'start_time' => $override->start_time,
                'end_time' => $override->end_time,
                'hours' => round($override->hours, 2),
                'notes' => $override->notes,
            ],
        ]);
    }

    /**
     * Удалить тип дня (вернуть к автоматическому режиму)
     * DELETE /api/backoffice/attendance/day-override/{id}
     */
    public function deleteDayOverride(int $id): JsonResponse
    {
        $restaurantId = Auth::user()->restaurant_id;

        $override = WorkDayOverride::where('id', $id)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$override) {
            return response()->json(['success' => false, 'error' => 'override_not_found'], 404);
        }

        $override->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Форматирование часов (17д 132:54ч)
     */
    private function formatHours(float $hours): string
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * Короткий формат (6:15)
     */
    private function formatHoursShort(float $hours): string
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * Получить инициалы
     */
    private function getInitials(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= mb_substr($part, 0, 1);
            }
        }
        return mb_strtoupper(mb_substr($initials, 0, 2));
    }

    /**
     * Название месяца
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
            4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
            7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
            10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Ленивое автозакрытие смен старше 18 часов (fallback для cron)
     */
    private function autoCloseStaleSessionsForRestaurant(int $restaurantId): void
    {
        $maxHours = 18;
        $cutoffTime = Carbon::now()->subHours($maxHours);

        // Находим активные смены старше 18 часов для этого ресторана
        $staleSessions = WorkSession::where('restaurant_id', $restaurantId)
            ->where('status', WorkSession::STATUS_ACTIVE)
            ->whereNull('clock_out')
            ->where('clock_in', '<', $cutoffTime)
            ->get();

        foreach ($staleSessions as $session) {
            $session->update([
                'clock_out' => now(),
                'hours_worked' => 0,
                'status' => WorkSession::STATUS_AUTO_CLOSED,
                'notes' => ($session->notes ? $session->notes . '; ' : '') .
                    "Автозакрыто по таймауту ({$maxHours}ч)",
            ]);
        }
    }
}
