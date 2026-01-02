<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * Список бронирований
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['table', 'customer'])
            ->where('restaurant_id', $request->input('restaurant_id', 1));

        // Фильтр по дате
        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Фильтр по диапазону дат
        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        // Только сегодня
        if ($request->boolean('today')) {
            $query->whereDate('date', Carbon::today());
        }

        // Предстоящие
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтр по столу
        if ($request->has('table_id')) {
            $query->where('table_id', $request->input('table_id'));
        }

        $reservations = $query->orderBy('date')
                              ->orderBy('time_from')
                              ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * Создание бронирования
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
            'guest_name' => 'required|string|max:100',
            'guest_phone' => 'required|string|max:20',
            'guest_email' => 'nullable|email|max:100',
            'date' => 'required|date|after_or_equal:today',
            'time_from' => 'required|date_format:H:i',
            'time_to' => 'required|date_format:H:i|after:time_from',
            'guests_count' => 'required|integer|min:1|max:20',
            'notes' => 'nullable|string|max:500',
            'special_requests' => 'nullable|string|max:500',
            'deposit' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);

        // Проверяем вместимость стола
        $table = Table::find($validated['table_id']);
        if ($table && $validated['guests_count'] > $table->seats) {
            return response()->json([
                'success' => false,
                'message' => "Стол вмещает максимум {$table->seats} гостей",
            ], 422);
        }

        // Проверяем конфликты
        if (Reservation::hasConflict(
            $validated['table_id'],
            $validated['date'],
            $validated['time_from'],
            $validated['time_to']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Это время уже занято. Выберите другое время или стол.',
            ], 422);
        }

        $reservation = Reservation::create([
            'restaurant_id' => $restaurantId,
            'table_id' => $validated['table_id'],
            'customer_id' => $validated['customer_id'] ?? null,
            'guest_name' => $validated['guest_name'],
            'guest_phone' => $validated['guest_phone'],
            'guest_email' => $validated['guest_email'] ?? null,
            'date' => $validated['date'],
            'time_from' => $validated['time_from'],
            'time_to' => $validated['time_to'],
            'guests_count' => $validated['guests_count'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'special_requests' => $validated['special_requests'] ?? null,
            'deposit' => $validated['deposit'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Бронирование создано',
            'data' => $reservation->load(['table']),
        ], 201);
    }

    /**
     * Показать бронирование
     */
    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $reservation->load(['table', 'customer']),
        ]);
    }

    /**
     * Обновить бронирование
     */
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'sometimes|integer|exists:tables,id',
            'guest_name' => 'sometimes|string|max:100',
            'guest_phone' => 'sometimes|string|max:20',
            'guest_email' => 'nullable|email|max:100',
            'date' => 'sometimes|date',
            'time_from' => 'sometimes|date_format:H:i',
            'time_to' => 'sometimes|date_format:H:i',
            'guests_count' => 'sometimes|integer|min:1|max:20',
            'notes' => 'nullable|string|max:500',
            'special_requests' => 'nullable|string|max:500',
            'deposit' => 'nullable|numeric|min:0',
            'deposit_paid' => 'sometimes|boolean',
        ]);

        // Если меняется время или стол - проверяем конфликты
        $tableId = $validated['table_id'] ?? $reservation->table_id;
        $date = $validated['date'] ?? $reservation->date;
        $timeFrom = $validated['time_from'] ?? $reservation->time_from;
        $timeTo = $validated['time_to'] ?? $reservation->time_to;

        if (isset($validated['table_id']) || isset($validated['date']) || 
            isset($validated['time_from']) || isset($validated['time_to'])) {
            
            if (Reservation::hasConflict($tableId, $date, $timeFrom, $timeTo, $reservation->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Это время уже занято',
                ], 422);
            }
        }

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Бронирование обновлено',
            'data' => $reservation->fresh(['table']),
        ]);
    }

    /**
     * Удалить бронирование
     */
    public function destroy(Reservation $reservation): JsonResponse
    {
        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Бронирование удалено',
        ]);
    }

    /**
     * Подтвердить бронирование
     */
    public function confirm(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Можно подтвердить только ожидающее бронирование',
            ], 422);
        }

        $reservation->confirm($request->input('user_id'));

        return response()->json([
            'success' => true,
            'message' => 'Бронирование подтверждено',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Отменить бронирование
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if (in_array($reservation->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить это бронирование',
            ], 422);
        }

        $reservation->cancel($request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Бронирование отменено',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Гости сели за стол
     */
    public function seat(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Сначала подтвердите бронирование',
            ], 422);
        }

        $reservation->seat();

        return response()->json([
            'success' => true,
            'message' => 'Гости сели за стол',
            'data' => $reservation->fresh(['table']),
        ]);
    }

    /**
     * Завершить бронирование
     */
    public function complete(Reservation $reservation): JsonResponse
    {
        $reservation->complete();

        return response()->json([
            'success' => true,
            'message' => 'Бронирование завершено',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Отметить как "не пришли"
     */
    public function noShow(Reservation $reservation): JsonResponse
    {
        $reservation->markNoShow();

        return response()->json([
            'success' => true,
            'message' => 'Отмечено как "не пришли"',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Получить доступные слоты на дату
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'guests_count' => 'required|integer|min:1',
            'duration' => 'nullable|integer|min:30|max:240', // минуты
        ]);

        $date = Carbon::parse($request->input('date'));
        $guestsCount = $request->input('guests_count');
        $duration = $request->input('duration', 120); // по умолчанию 2 часа
        $restaurantId = $request->input('restaurant_id', 1);

        // Получаем подходящие столы
        $tables = Table::where('seats', '>=', $guestsCount)
            ->where('is_active', true)
            ->orderBy('seats')
            ->get();

        // Рабочие часы ресторана (можно вынести в настройки)
        $workStart = 10; // 10:00
        $workEnd = 22;   // 22:00
        $slotStep = 30;  // шаг 30 минут

        $availableSlots = [];

        foreach ($tables as $table) {
            $tableSlots = [];
            
            // Получаем бронирования на этот стол и дату
            $reservations = Reservation::where('table_id', $table->id)
                ->whereDate('date', $date)
                ->whereIn('status', ['pending', 'confirmed', 'seated'])
                ->orderBy('time_from')
                ->get();

            // Проверяем каждый временной слот
            for ($hour = $workStart; $hour < $workEnd; $hour++) {
                for ($min = 0; $min < 60; $min += $slotStep) {
                    $slotStart = sprintf('%02d:%02d', $hour, $min);
                    $slotEndCarbon = Carbon::createFromFormat('H:i', $slotStart)->addMinutes($duration);
                    
                    // Не выходим за рабочие часы
                    if ($slotEndCarbon->hour >= $workEnd && $slotEndCarbon->minute > 0) {
                        continue;
                    }
                    
                    $slotEnd = $slotEndCarbon->format('H:i');

                    // Проверяем конфликты
                    $hasConflict = false;
                    foreach ($reservations as $res) {
                        $resStart = Carbon::parse($res->time_from)->format('H:i');
                        $resEnd = Carbon::parse($res->time_to)->format('H:i');
                        
                        if (!($slotEnd <= $resStart || $slotStart >= $resEnd)) {
                            $hasConflict = true;
                            break;
                        }
                    }

                    if (!$hasConflict) {
                        $tableSlots[] = [
                            'time_from' => $slotStart,
                            'time_to' => $slotEnd,
                        ];
                    }
                }
            }

            if (!empty($tableSlots)) {
                $availableSlots[] = [
                    'table' => $table,
                    'slots' => $tableSlots,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->format('Y-m-d'),
                'guests_count' => $guestsCount,
                'duration_minutes' => $duration,
                'available' => $availableSlots,
            ],
        ]);
    }

    /**
     * Календарь бронирований (для отображения)
     */
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2024',
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $restaurantId = $request->input('restaurant_id', 1);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $reservations = Reservation::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // Формируем данные по дням
        $days = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $days[$dateKey] = [
                'date' => $dateKey,
                'day' => $current->day,
                'weekday' => $current->dayOfWeek,
                'reservations_count' => $reservations->has($dateKey) ? $reservations[$dateKey]->count : 0,
            ];
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'year' => $year,
                'days' => array_values($days),
            ],
        ]);
    }

    /**
     * Статистика бронирований
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $today = Carbon::today();

        $todayReservations = Reservation::where('restaurant_id', $restaurantId)
            ->whereDate('date', $today)
            ->get();

        $upcomingReservations = Reservation::where('restaurant_id', $restaurantId)
            ->where('date', '>', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'total' => $todayReservations->count(),
                    'pending' => $todayReservations->where('status', 'pending')->count(),
                    'confirmed' => $todayReservations->where('status', 'confirmed')->count(),
                    'seated' => $todayReservations->where('status', 'seated')->count(),
                    'completed' => $todayReservations->where('status', 'completed')->count(),
                    'cancelled' => $todayReservations->where('status', 'cancelled')->count(),
                    'no_show' => $todayReservations->where('status', 'no_show')->count(),
                    'total_guests' => $todayReservations->whereIn('status', ['pending', 'confirmed', 'seated'])->sum('guests_count'),
                ],
                'upcoming' => $upcomingReservations,
            ],
        ]);
    }
}
