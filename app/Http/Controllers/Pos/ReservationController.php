<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * Создать бронирование
     */
    public function store(Request $request, Table $table)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'guests_count' => 'required|integer|min:1|max:20',
            'guest_name' => 'required|string|max:100',
            'guest_phone' => 'required|string|max:20',
            'deposit' => 'nullable|numeric|min:0',
            'wishes' => 'nullable|array',
            'comment' => 'nullable|string|max:500',
        ]);

        // Проверка доступности времени (совместимо с SQLite)
        $requestedTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);
        $minGapMinutes = 120; // 2 часа между бронированиями

        $existingReservations = Reservation::where('table_id', $table->id)
            ->where('date', $validated['date'])
            ->where('status', '!=', 'cancelled')
            ->get();

        $hasConflict = $existingReservations->contains(function ($reservation) use ($requestedTime, $minGapMinutes) {
            $existingTime = Carbon::parse($reservation->date . ' ' . $reservation->time_from);
            $diffMinutes = abs($requestedTime->diffInMinutes($existingTime));
            return $diffMinutes < $minGapMinutes;
        });

        if ($hasConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Это время уже занято или слишком близко к другой брони',
            ], 422);
        }

        // Рассчитываем время окончания (2 часа по умолчанию)
        $timeFrom = Carbon::parse($validated['time']);
        $timeTo = $timeFrom->copy()->addHours(2);

        $reservation = Reservation::create([
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
            'date' => $validated['date'],
            'time_from' => $validated['time'],
            'time_to' => $timeTo->format('H:i'),
            'guests_count' => $validated['guests_count'],
            'guest_name' => $validated['guest_name'],
            'guest_phone' => $validated['guest_phone'],
            'deposit' => $validated['deposit'] ?? 0,
            'special_requests' => json_encode($validated['wishes'] ?? []),
            'notes' => $validated['comment'],
            'status' => 'confirmed',
        ]);

        return response()->json([
            'success' => true,
            'reservation' => $reservation,
        ]);
    }

    /**
     * Получить доступные слоты времени
     */
    public function getAvailableSlots(Request $request, Table $table)
    {
        $date = $request->input('date', today()->format('Y-m-d'));

        $bookedSlots = Reservation::where('table_id', $table->id)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(function ($reservation) {
                return [
                    'from' => substr($reservation->time_from, 0, 5),
                    'to' => substr($reservation->time_to, 0, 5),
                ];
            });

        $allSlots = [];
        for ($h = 12; $h <= 23; $h++) {
            $slotTime = sprintf('%02d:00', $h);
            $isBooked = $bookedSlots->contains(function ($booked) use ($slotTime) {
                return $slotTime >= $booked['from'] && $slotTime < $booked['to'];
            });

            $allSlots[] = [
                'time' => $slotTime,
                'available' => !$isBooked,
            ];
        }

        return response()->json($allSlots);
    }

    /**
     * Отменить бронирование
     */
    public function cancel(Table $table, Reservation $reservation)
    {
        if ($reservation->table_id !== $table->id) {
            return response()->json([
                'success' => false,
                'message' => 'Бронирование не принадлежит этому столу',
            ], 400);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Бронирование отменено',
        ]);
    }
}
