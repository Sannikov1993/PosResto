<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reservation\Actions\CancelReservation;
use App\Domain\Reservation\Actions\CompleteReservation;
use App\Domain\Reservation\Actions\ConfirmReservation;
use App\Domain\Reservation\Actions\MarkNoShow;
use App\Domain\Reservation\Actions\SeatGuests;
use App\Domain\Reservation\Actions\UnseatGuests;
use App\Domain\Reservation\DTOs\CancelReservationData;
use App\Domain\Reservation\DTOs\SeatGuestsData;
use App\Domain\Reservation\Exceptions\ReservationException;
use App\Domain\Reservation\Services\DepositService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\RealtimeEvent;
use App\Models\CashOperation;
use App\Models\CashShift;
use App\Models\Customer;
use App\Events\ReservationEvent;
use App\Helpers\TimeHelper;
use App\ValueObjects\TimeSlot;
use App\Services\ReservationConflictService;
use App\Rules\ValidTimeSlot;
use App\Rules\NoReservationConflict;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\BroadcastsEvents;

class ReservationController extends Controller
{
    use Traits\ResolvesRestaurantId;
    use BroadcastsEvents;
    use \App\Domain\Reservation\Exceptions\HandlesReservationExceptions;

    public function __construct(
        private readonly ConfirmReservation $confirmAction,
        private readonly SeatGuests $seatGuestsAction,
        private readonly UnseatGuests $unseatGuestsAction,
        private readonly CompleteReservation $completeAction,
        private readonly CancelReservation $cancelAction,
        private readonly MarkNoShow $noShowAction,
        private readonly DepositService $depositService,
    ) {}
    /**
     * Список бронирований
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Проверяем включены ли уровни лояльности
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $restaurantId) !== '0';

        $relations = ['table', 'customer'];
        if ($levelsEnabled) {
            $relations = ['table', 'customer.loyaltyLevel'];
        }

        $query = Reservation::with($relations)
            ->where('restaurant_id', $restaurantId);

        // Фильтр по дате
        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Фильтр по диапазону дат
        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        // Только сегодня (календарная дата)
        if ($request->boolean('today')) {
            $query->whereDate('date', TimeHelper::today($restaurantId));
        }

        // Только "рабочий день" (учитывает работу после полуночи)
        if ($request->boolean('business_today')) {
            $query->whereDate('date', TimeHelper::getBusinessDate($restaurantId));
        }

        // Предстоящие
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтр по столу (учитываем и основной стол, и связанные столы)
        if ($request->has('table_id')) {
            $tableId = (int) $request->input('table_id');
            $query->where(function ($q) use ($tableId) {
                $q->where('table_id', $tableId)
                  ->orWhereJsonContains('linked_table_ids', $tableId)
                  ->orWhereJsonContains('linked_table_ids', (string) $tableId);
            });
        }

        // Пагинация: per_page по умолчанию 50, максимум 200
        $perPage = min($request->input('per_page', 50), 200);

        if ($request->has('page')) {
            $paginated = $query->orderBy('date')
                               ->orderBy('time_from')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // Обратная совместимость: без page возвращаем с лимитом
        $reservations = $query->orderBy('date')
                              ->orderBy('time_from')
                              ->limit($perPage)
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
        $restaurantId = $this->getRestaurantId($request);

        // Get timezone for this restaurant
        $tz = TimeHelper::getTimezone($restaurantId);

        $validated = $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
            'table_ids' => 'nullable|array',                   // Для мультивыбора
            'table_ids.*' => 'integer|exists:tables,id',
            'guest_name' => 'nullable|string|max:100',
            'guest_phone' => 'nullable|string|max:20',
            'guest_email' => 'nullable|email|max:100',
            'date' => 'required|date|after_or_equal:today',
            'time_from' => 'required|date_format:H:i',
            'time_to' => [
                'required',
                'date_format:H:i',
                // New validation rules with proper midnight-crossing support
                new ValidTimeSlot(
                    minMinutes: 30,
                    maxMinutes: 720,
                    dateField: 'date',
                    timeFromField: 'time_from',
                    timezone: $tz
                ),
            ],
            'guests_count' => 'required|integer|min:1|max:50',
            'notes' => 'nullable|string|max:500',
            'special_requests' => 'nullable|string|max:500',
            'deposit' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
        ]);

        // Проверка что время не в прошлом для сегодняшней даты
        $reservationDate = Carbon::parse($validated['date'], $tz)->startOfDay();
        $today = TimeHelper::today($restaurantId);

        if ($reservationDate->equalTo($today)) {
            $reservationTime = Carbon::parse($validated['time_from'], $tz);
            $now = TimeHelper::now($restaurantId);

            // Время бронирования должно быть минимум через 15 минут от текущего
            if ($reservationTime->format('H:i') <= $now->format('H:i')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя создать бронирование на прошедшее время. Выберите время позже ' . $now->addMinutes(15)->format('H:i'),
                ], 422);
            }
        }

        // Проверка что телефон полный (если указан)
        if (!empty($validated['guest_phone']) && !Customer::isPhoneComplete($validated['guest_phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Введите полный номер телефона (минимум 10 цифр)',
            ], 422);
        }

        // Форматируем имя гостя
        if (!empty($validated['guest_name'])) {
            $validated['guest_name'] = Customer::formatName($validated['guest_name']);
        }

        // Определяем все столы для бронирования
        $allTableIds = $validated['table_ids'] ?? [$validated['table_id']];
        $allTableIds = array_unique($allTableIds);

        // Проверяем общую вместимость всех столов
        $tables = Table::whereIn('id', $allTableIds)->get();
        $totalSeats = $tables->sum('seats');
        if ($validated['guests_count'] > $totalSeats) {
            return response()->json([
                'success' => false,
                'message' => "Выбранные столы вмещают максимум {$totalSeats} гостей",
            ], 422);
        }

        // Create TimeSlot for conflict checking and dual-write
        $timeSlot = TimeSlot::fromDateAndTimes(
            $validated['date'],
            $validated['time_from'],
            $validated['time_to'],
            $tz
        );
        $utcSlot = $timeSlot->toUtc();

        try {
            $reservation = DB::transaction(function () use ($validated, $restaurantId, $allTableIds, $tables, $timeSlot, $utcSlot, $tz) {
                // Проверяем конфликты с блокировкой (предотвращение race condition)
                // Now using TimeSlot for proper midnight-crossing support
                $conflictService = app(ReservationConflictService::class);
                if ($conflictService->hasConflictWithLock($allTableIds, $timeSlot)) {
                    $tableNumbers = $tables->pluck('number')->join(', ');
                    throw new \Exception("Столы {$tableNumbers} уже заняты в это время. Выберите другое время.");
                }

                // Создаём или находим клиента по телефону
                $customerId = $validated['customer_id'] ?? null;
                if (!$customerId && !empty($validated['guest_phone'])) {
                    $normalizedPhone = preg_replace('/[^0-9]/', '', $validated['guest_phone']);
                    $customer = Customer::where('restaurant_id', $restaurantId)
                        ->byPhone($normalizedPhone)
                        ->first();

                    if ($customer) {
                        $customerId = $customer->id;
                        // Обновляем данные клиента если нужно
                        $updateData = [];
                        if (!empty($validated['guest_name']) && !$customer->name) {
                            $updateData['name'] = $validated['guest_name'];
                        }
                        if (!empty($validated['guest_email']) && !$customer->email) {
                            $updateData['email'] = $validated['guest_email'];
                        }
                        if (!empty($updateData)) {
                            $customer->update($updateData);
                        }
                    } else {
                        // Создаём нового клиента
                        $customer = Customer::create([
                            'restaurant_id' => $restaurantId,
                            'phone' => $validated['guest_phone'],
                            'name' => $validated['guest_name'] ?? null,
                            'email' => $validated['guest_email'] ?? null,
                            'source' => 'reservation',
                        ]);
                        $customerId = $customer->id;
                    }
                }

                // Определяем связанные столы (все кроме основного)
                $linkedTableIds = array_values(array_diff($allTableIds, [$validated['table_id']]));

                // Dual-write: заполняем и legacy, и новые поля
                return Reservation::create([
                    'restaurant_id' => $restaurantId,
                    'table_id' => $validated['table_id'],
                    'linked_table_ids' => !empty($linkedTableIds) ? $linkedTableIds : null,
                    'customer_id' => $customerId,
                    'guest_name' => $validated['guest_name'] ?? null,
                    'guest_phone' => $validated['guest_phone'] ?? null,
                    'guest_email' => $validated['guest_email'] ?? null,
                    // Legacy fields (for backward compatibility)
                    'date' => $validated['date'],
                    'time_from' => $validated['time_from'],
                    'time_to' => $validated['time_to'],
                    // New datetime fields (UTC)
                    'starts_at' => $utcSlot->startsAt(),
                    'ends_at' => $utcSlot->endsAt(),
                    'duration_minutes' => $timeSlot->durationMinutes(),
                    'timezone' => $tz,
                    // Other fields
                    'guests_count' => $validated['guests_count'],
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                    'special_requests' => $validated['special_requests'] ?? null,
                    'deposit' => $validated['deposit'] ?? 0,
                ]);
            });

            $tableNumbers = $tables->pluck('number')->join(', ');
            $message = count($allTableIds) > 1
                ? "Бронирование на столы {$tableNumbers} создано"
                : 'Бронирование создано';

            // Real-time событие через Reverb
            ReservationEvent::dispatch($restaurantId, 'reservation_new', [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'customer_name' => $reservation->guest_name,
                'date' => $reservation->date,
                'time_from' => $reservation->time_from,
                'guests_count' => $reservation->guests_count,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $reservation->load(['table']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Показать бронирование
     */
    public function show(Reservation $reservation): JsonResponse
    {
        // Проверяем включены ли уровни лояльности
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $reservation->restaurant_id) !== '0';

        $relations = $levelsEnabled ? ['table', 'customer.loyaltyLevel'] : ['table', 'customer'];

        return response()->json([
            'success' => true,
            'data' => $reservation->load($relations),
        ]);
    }

    /**
     * Обновить бронирование
     */
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        Log::info('Reservation update request', [
            'reservation_id' => $reservation->id,
            'input' => $request->all()
        ]);

        // Get timezone for this restaurant
        $tz = TimeHelper::getTimezone($reservation->restaurant_id);

        $validated = $request->validate([
            'table_id' => 'sometimes|integer|exists:tables,id',
            'guest_name' => 'sometimes|string|max:100',
            'guest_phone' => 'sometimes|string|max:20',
            'guest_email' => 'nullable|email|max:100',
            'date' => 'sometimes|date',
            'time_from' => 'sometimes|date_format:H:i',
            'time_to' => 'sometimes|date_format:H:i',
            'guests_count' => 'sometimes|integer|min:1|max:50',
            'notes' => 'nullable|string|max:500',
            'special_requests' => 'nullable|string|max:500',
            'deposit' => 'nullable|numeric|min:0',
            'deposit_paid' => 'sometimes|boolean',
            'deposit_payment_method' => 'nullable|in:cash,card',
        ]);

        // Проверка что телефон полный (если указан)
        if (!empty($validated['guest_phone']) && !Customer::isPhoneComplete($validated['guest_phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Введите полный номер телефона (минимум 10 цифр)',
            ], 422);
        }

        // Форматируем имя гостя
        if (!empty($validated['guest_name'])) {
            $validated['guest_name'] = Customer::formatName($validated['guest_name']);
        }

        // Если меняется время или стол - проверяем конфликты
        $tableId = $validated['table_id'] ?? $reservation->table_id;

        // Get date value properly (handle Carbon or string)
        $currentDate = $reservation->date instanceof \Carbon\Carbon
            ? $reservation->date->format('Y-m-d')
            : substr($reservation->date, 0, 10);
        $date = $validated['date'] ?? $currentDate;

        $currentTimeFrom = substr($reservation->time_from, 0, 5);
        $currentTimeTo = substr($reservation->time_to, 0, 5);
        $timeFrom = $validated['time_from'] ?? $currentTimeFrom;
        $timeTo = $validated['time_to'] ?? $currentTimeTo;

        // Проверяем конфликты только если значения РЕАЛЬНО изменились
        $tableChanged = isset($validated['table_id']) && (int)$validated['table_id'] !== (int)$reservation->table_id;
        $dateChanged = isset($validated['date']) && $validated['date'] !== $currentDate;
        $timeFromChanged = isset($validated['time_from']) && $validated['time_from'] !== $currentTimeFrom;
        $timeToChanged = isset($validated['time_to']) && $validated['time_to'] !== $currentTimeTo;

        $needsConflictCheck = $tableChanged || $dateChanged || $timeFromChanged || $timeToChanged;
        $needsDatetimeUpdate = $dateChanged || $timeFromChanged || $timeToChanged;

        // Create TimeSlot if time/date changed for proper midnight-crossing support
        $timeSlot = null;
        $utcSlot = null;
        if ($needsDatetimeUpdate) {
            $timeSlot = TimeSlot::fromDateAndTimes($date, $timeFrom, $timeTo, $tz);
            $utcSlot = $timeSlot->toUtc();
        }

        try {
            $updatedReservation = DB::transaction(function () use ($validated, $reservation, $tableId, $needsConflictCheck, $needsDatetimeUpdate, $timeSlot, $utcSlot, $tz) {
                // Проверяем конфликты с блокировкой (предотвращение race condition)
                if ($needsConflictCheck && $timeSlot) {
                    $conflictService = app(ReservationConflictService::class);
                    if ($conflictService->hasConflictWithLock([$tableId], $timeSlot, $reservation->id)) {
                        throw new \Exception('Это время уже занято');
                    }
                }

                // Add datetime fields if time/date changed (dual-write)
                if ($needsDatetimeUpdate && $utcSlot) {
                    $validated['starts_at'] = $utcSlot->startsAt();
                    $validated['ends_at'] = $utcSlot->endsAt();
                    $validated['duration_minutes'] = $timeSlot->durationMinutes();
                    $validated['timezone'] = $tz;
                }

                $reservation->update($validated);

                return $reservation;
            });

            return response()->json([
                'success' => true,
                'message' => 'Бронирование обновлено',
                'data' => $updatedReservation->fresh(['table']),
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка обновления брони: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'validated' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Удалить бронирование
     */
    public function destroy(Reservation $reservation): JsonResponse
    {
        // Проверяем, есть ли невозвращённый депозит
        if ($reservation->deposit > 0 && $reservation->deposit_status === Reservation::DEPOSIT_PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно удалить бронирование с оплаченным депозитом. Сначала верните депозит.',
            ], 422);
        }

        // Также проверяем статус transferred (депозит перенесён в заказ)
        if ($reservation->deposit > 0 && $reservation->deposit_status === Reservation::DEPOSIT_TRANSFERRED) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно удалить бронирование. Депозит уже учтён в заказе.',
            ], 422);
        }

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
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $result = $this->confirmAction->execute(
                reservation: $reservation,
                userId: auth()->id() ?? $request->input('user_id')
            );

            // Real-time событие через Reverb
            ReservationEvent::dispatch($reservation->restaurant_id, 'reservation_confirmed', [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'customer_name' => $reservation->guest_name,
                'date' => $reservation->date,
                'time_from' => $reservation->time_from,
            ]);

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'data' => new ReservationResource($result->reservation),
            ]);
        }, 'confirm');
    }

    /**
     * Отменить бронирование
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $request->validate(CancelReservationData::rules());

            $data = CancelReservationData::fromRequest($request);

            $result = $this->cancelAction->execute(
                reservation: $reservation,
                reason: $data->reason,
                refundDeposit: $data->refundDeposit,
                userId: $data->userId
            );

            $depositRefunded = $result->metadata['deposit_refunded'] ?? false;

            // Real-time событие через Reverb
            $this->broadcast('reservations', 'reservation_cancelled', [
                'restaurant_id' => $reservation->restaurant_id,
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'deposit_refunded' => $depositRefunded ? $reservation->deposit : 0,
            ]);

            // Real-time событие через Reverb
            ReservationEvent::dispatch($reservation->restaurant_id, 'reservation_cancelled', [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'customer_name' => $reservation->guest_name,
                'reason' => $data->reason,
                'deposit_refunded' => $depositRefunded ? $reservation->deposit : 0,
            ]);

            $message = 'Бронирование отменено';
            if ($depositRefunded) {
                $message .= sprintf(' (депозит %.0f₽ возвращён)', $reservation->deposit);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new ReservationResource($result->reservation),
                'deposit_refunded' => $depositRefunded ? $reservation->deposit : 0,
            ]);
        }, 'cancel');
    }

    /**
     * Гости сели за стол
     */
    public function seat(Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($reservation) {
            $result = $this->seatGuestsAction->execute(
                reservation: $reservation,
                createOrder: false,
                userId: auth()->id()
            );

            // Real-time событие через Reverb
            ReservationEvent::dispatch($reservation->restaurant_id, 'reservation_seated', [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'customer_name' => $reservation->guest_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'data' => new ReservationResource($result->reservation->load('table')),
            ]);
        }, 'seat');
    }

    /**
     * Снять гостей со стола - вернуть бронь в статус confirmed
     */
    public function unseat(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $result = $this->unseatGuestsAction->execute(
                reservation: $reservation,
                force: $request->boolean('force', false),
                userId: auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'data' => ['reservation' => new ReservationResource($result->reservation->load('table'))],
            ]);
        }, 'unseat');
    }

    /**
     * Гости пришли - создать заказ из брони
     */
    public function seatWithOrder(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $data = SeatGuestsData::fromRequest($request);

            $result = $this->seatGuestsAction->execute(
                reservation: $reservation,
                createOrder: true,
                userId: $data->userId,
                transferDeposit: $data->transferDeposit,
                guestsCount: $data->guestsCount
            );

            // Real-time события для столов
            foreach ($result->getTableIds() as $tableId) {
                $this->broadcastTableStatusChanged($tableId, 'occupied', $reservation->restaurant_id);
            }

            // Real-time событие через Reverb
            ReservationEvent::dispatch($reservation->restaurant_id, 'reservation_seated', [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_id,
                'order_id' => $result->order?->id,
                'customer_name' => $reservation->guest_name,
            ]);

            $message = 'Гости сели, заказ создан';
            if ($result->depositTransferred) {
                $message .= sprintf(' (депозит %.0f₽ учтён)', $reservation->deposit);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'reservation' => new ReservationResource($result->reservation->load('table')),
                    'order' => $result->order,
                    'deposit_transferred' => $result->depositTransferred ? $reservation->deposit : 0,
                ],
            ]);
        }, 'seatWithOrder');
    }

    /**
     * Создать предзаказ для брони (без посадки гостей)
     */
    public function preorder(Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя создать предзаказ для этой брони',
            ], 422);
        }

        // Проверяем, есть ли уже предзаказ
        $existingOrder = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['new', 'confirmed'])
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success' => true,
                'message' => 'Предзаказ уже существует',
                'data' => [
                    'reservation' => $reservation,
                    'order' => $existingOrder,
                ],
            ]);
        }

        $allTableIds = $reservation->linked_table_ids ?? [];
        if (!in_array($reservation->table_id, $allTableIds)) {
            array_unshift($allTableIds, $reservation->table_id);
        }

        // Создаём заказ-предзаказ (без изменения статуса брони и стола)
        $order = Order::create([
            'restaurant_id' => $reservation->restaurant_id,
            'table_id' => $reservation->table_id,
            'linked_table_ids' => count($allTableIds) > 1 ? $allTableIds : null,
            'reservation_id' => $reservation->id,
            'order_number' => Order::generateOrderNumber($reservation->restaurant_id),
            'type' => 'preorder', // предзаказ - не показывается на столе
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
            'persons' => $reservation->guests_count ?? $reservation->guests ?? 2,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Предзаказ создан',
            'data' => [
                'reservation' => $reservation,
                'order' => $order,
            ],
        ]);
    }

    /**
     * Получить товары предзаказа
     */
    public function preorderItems(Reservation $reservation): JsonResponse
    {
        $order = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready'])
            ->with(['items.dish'])
            ->first();

        if (!$order || $order->items->isEmpty()) {
            return response()->json([
                'success' => true,
                'items' => [],
                'total' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name ?? $item->dish?->getFullName() ?? 'Товар',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->quantity * $item->price,
                'modifiers' => $item->modifiers ?? [],
                'comment' => $item->comment,
            ]),
            'total' => $order->total,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Добавить товар в предзаказ
     */
    public function addPreorderItem(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'quantity' => 'nullable|integer|min:1',
            'modifiers' => 'nullable|array',
            'comment' => 'nullable|string',
        ]);

        // Получаем или создаём предзаказ
        $order = Order::where('reservation_id', $reservation->id)
            ->where('type', 'preorder')
            ->first();

        if (!$order) {
            $allTableIds = $reservation->linked_table_ids ?? [];
            if (!in_array($reservation->table_id, $allTableIds)) {
                array_unshift($allTableIds, $reservation->table_id);
            }

            // Формируем scheduled_at из даты и времени брони для отображения на кухне
            $scheduledAt = null;
            if ($reservation->date && $reservation->time_from) {
                // $reservation->date может быть Carbon объектом или строкой с временем, берём только дату
                $dateOnly = $reservation->date instanceof \Carbon\Carbon
                    ? $reservation->date->format('Y-m-d')
                    : substr($reservation->date, 0, 10);
                $scheduledAt = Carbon::parse($dateOnly . ' ' . $reservation->time_from);
            }

            $order = Order::create([
                'restaurant_id' => $reservation->restaurant_id,
                'table_id' => $reservation->table_id,
                'linked_table_ids' => count($allTableIds) > 1 ? $allTableIds : null,
                'reservation_id' => $reservation->id,
                'order_number' => Order::generateOrderNumber($reservation->restaurant_id),
                'type' => 'preorder',
                'status' => 'new',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
                'persons' => $reservation->guests_count ?? 2,
                'user_id' => auth()->id(),
                'scheduled_at' => $scheduledAt,
                'is_asap' => false,
                'phone' => $reservation->guest_phone,
            ]);
        }

        $dish = Dish::with('parent')->findOrFail($request->input('dish_id'));
        $quantity = $request->input('quantity', 1);
        $modifiers = $request->input('modifiers', []);
        $comment = $request->input('comment');

        $modifiersPrice = collect($modifiers)->sum(fn($m) => floatval($m['price'] ?? 0));
        $itemTotal = ($dish->price + $modifiersPrice) * $quantity;

        // Используем полное название (с размером для вариантов)
        $itemName = $dish->getFullName();

        // Явно передаём order_id и restaurant_id для надёжности
        // (trait тоже resolve restaurant_id из order_id, но явная передача быстрее)
        $item = OrderItem::create([
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
            'dish_id' => $dish->id,
            'name' => $itemName,
            'quantity' => $quantity,
            'price' => $dish->price,
            'modifiers_price' => $modifiersPrice,
            'total' => $itemTotal,
            'modifiers' => $modifiers,
            'comment' => $comment,
        ]);

        $order->recalculateTotal();

        return response()->json([
            'success' => true,
            'message' => 'Товар добавлен в предзаказ',
            'item' => [
                'id' => $item->id,
                'name' => $itemName,
                'quantity' => $quantity,
                'price' => $dish->price,
                'total' => $itemTotal,
            ],
            'order_total' => $order->fresh()->total,
        ]);
    }

    /**
     * Удалить товар из предзаказа
     */
    public function removePreorderItem(Reservation $reservation, int $itemId): JsonResponse
    {
        $order = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Предзаказ не найден',
            ], 404);
        }

        $item = $order->items()->find($itemId);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден',
            ], 404);
        }

        $item->delete();
        $order->recalculateTotal();

        return response()->json([
            'success' => true,
            'message' => 'Товар удалён из предзаказа',
            'order_total' => $order->fresh()->total,
        ]);
    }

    /**
     * Обновить количество товара в предзаказе
     */
    public function updatePreorderItem(Request $request, Reservation $reservation, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'comment' => 'nullable|string|max:500',
        ]);

        $order = Order::where('reservation_id', $reservation->id)
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Предзаказ не найден',
            ], 404);
        }

        $item = $order->items()->find($itemId);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден',
            ], 404);
        }

        $quantity = $request->input('quantity');
        $updateData = [
            'quantity' => $quantity,
            'total' => ($item->price + $item->modifiers_price) * $quantity,
        ];

        if ($request->has('comment')) {
            $updateData['comment'] = $request->input('comment');
        }

        $item->update($updateData);

        $order->recalculateTotal();

        return response()->json([
            'success' => true,
            'message' => 'Обновлено',
            'item' => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'total' => $item->total,
                'comment' => $item->comment,
            ],
            'order_total' => $order->fresh()->total,
        ]);
    }

    /**
     * Завершить бронирование
     */
    public function complete(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $result = $this->completeAction->execute(
                reservation: $reservation,
                force: $request->boolean('force', false),
                userId: auth()->id()
            );

            // Real-time события для освобождённых столов
            $tableIds = $result->metadata['table_ids'] ?? [];
            foreach ($tableIds as $tableId) {
                $table = Table::forRestaurant($reservation->restaurant_id)->find($tableId);
                if ($table && $table->status === 'free') {
                    $this->broadcastTableStatusChanged($tableId, 'free', $table->restaurant_id);
                }
            }

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'data' => new ReservationResource($result->reservation),
            ]);
        }, 'complete');
    }

    /**
     * Отметить как "не пришли"
     */
    public function noShow(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $result = $this->noShowAction->execute(
                reservation: $reservation,
                forfeitDeposit: $request->boolean('forfeit_deposit', true),
                userId: auth()->id(),
                notes: $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'data' => new ReservationResource($result->reservation),
            ]);
        }, 'noShow');
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
        $restaurantId = $this->getRestaurantId($request);

        // Получаем подходящие столы
        $tables = Table::where('seats', '>=', $guestsCount)
            ->where('is_active', true)
            ->orderBy('seats')
            ->get();

        // Рабочие часы из конфига
        $workStart = config("restaurant.work_hours.start", 10);
        $workEnd = config("restaurant.work_hours.end", 22);
        $slotStep = config("restaurant.reservation_slot_step", 30);

        $availableSlots = [];

        foreach ($tables as $table) {
            $tableSlots = [];
            
            // Получаем бронирования на этот стол и дату (включая linked_table_ids)
            $tableId = (int) $table->id;
            $reservations = Reservation::where(function($q) use ($tableId) {
                    $q->where('table_id', $tableId)
                      ->orWhereJsonContains('linked_table_ids', $tableId)
                      ->orWhereJsonContains('linked_table_ids', (string) $tableId);
                })
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
        $restaurantId = $this->getRestaurantId($request);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Бронирования по дням
        $reservations = Reservation::where('restaurant_id', $restaurantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // Заказы по дням (оплаченные)
        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate->endOfDay()])
            ->where('payment_status', 'paid')
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

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
                'orders_count' => $orders->has($dateKey) ? $orders[$dateKey]->count : 0,
                'total' => $orders->has($dateKey) ? (float) $orders[$dateKey]->total : 0,
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
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId);

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


    /**
     * Принять оплату депозита за бронь
     */
    public function payDeposit(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $request->validate([
                'method' => 'required|in:cash,card',
                'amount' => 'nullable|numeric|min:1',
                'transaction_id' => 'nullable|string|max:255',
            ]);

            $method = $request->input('method');
            $transactionId = $request->input('transaction_id');
            $restaurantId = $reservation->restaurant_id;

            // Проверяем открытую смену для наличных
            if ($method === 'cash') {
                $shift = CashShift::getCurrentShift($restaurantId);
                if (!$shift) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Для приёма наличных необходимо открыть кассовую смену',
                    ], 422);
                }
            }

            $userId = auth()->id() ?? 1;
            $amount = $reservation->deposit;

            // Используем DepositService для основной логики
            $result = $this->depositService->markAsPaid(
                reservation: $reservation,
                paymentMethod: $method,
                transactionId: $transactionId,
                userId: $userId
            );

            // Записываем в кассу
            $operation = CashOperation::recordPrepayment(
                $restaurantId,
                $reservation->id,
                $amount,
                $method,
                $userId,
                $reservation->guest_name
            );

            // Обновляем operation_id
            $reservation->update(['deposit_operation_id' => $operation->id]);

            Log::info('Deposit paid', [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
            ]);

            $this->broadcast('reservations', 'deposit_paid', [
                'restaurant_id' => $restaurantId,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Депозит успешно оплачен',
                'data' => [
                    'reservation' => new ReservationResource($result),
                    'amount' => $amount,
                    'method' => $method,
                ],
                'new_total' => $result->deposit,
            ]);
        }, 'payDeposit');
    }

    /**
     * Получить информацию о депозите
     */
    public function depositSummary(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->depositService->getSummary($reservation),
        ]);
    }

    /**
     * Вернуть депозит за бронь
     */
    public function refundDeposit(Request $request, Reservation $reservation): JsonResponse
    {
        return $this->handleReservationAction(function () use ($request, $reservation) {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $amount = (float) $reservation->deposit;
            $method = $reservation->deposit_payment_method ?? 'cash';
            $reason = $request->input('reason');
            $restaurantId = $reservation->restaurant_id;

            // Проверяем открытую смену для наличных
            if ($method === 'cash') {
                $shift = CashShift::getCurrentShift($restaurantId);
                if (!$shift) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Для возврата наличных необходимо открыть кассовую смену',
                    ], 422);
                }
            }

            $userId = auth()->id() ?? 1;

            // Используем DepositService для основной логики
            $result = $this->depositService->refund(
                reservation: $reservation,
                reason: $reason,
                userId: $userId
            );

            // Записываем возврат в кассу
            CashOperation::recordDepositRefund(
                $restaurantId,
                $reservation->id,
                $amount,
                $method,
                $userId,
                $reservation->guest_name,
                $reason,
                $reservation->deposit_operation_id,
                $reservation->deposit_paid_at?->toDateTimeString()
            );

            Log::info('Deposit refunded', [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
            ]);

            $this->broadcast('reservations', 'deposit_refunded', [
                'restaurant_id' => $restaurantId,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Депозит успешно возвращён',
                'data' => [
                    'reservation' => new ReservationResource($result),
                    'amount' => $amount,
                    'method' => $method,
                ],
            ]);
        }, 'refundDeposit');
    }

    /**
     * Внести предоплату за бронь (устаревший метод, оставлен для совместимости)
     * @deprecated Используйте payDeposit
     */
    public function prepayment(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card',
            'order_id' => 'nullable|integer',
        ]);

        $amount = $request->input('amount');
        $method = $request->input('method');

        try {
            $result = DB::transaction(function () use ($reservation, $amount, $method) {
                // Добавляем к существующему депозиту
                $reservation->deposit = ($reservation->deposit ?? 0) + $amount;
                $reservation->save();

                // Записываем в кассу
                $operation = CashOperation::recordPrepayment(
                    $reservation->restaurant_id,
                    $reservation->id,
                    $amount,
                    $method,
                    auth()->id(),
                    $reservation->guest_name
                );

                // Если депозит ещё не был оплачен - помечаем как оплачен
                if ($reservation->deposit_status === Reservation::DEPOSIT_PENDING) {
                    $reservation->payDeposit($method, auth()->id(), $operation->id);
                }

                return [
                    'amount' => $amount,
                    'total_deposit' => $reservation->deposit,
                ];
            });

            // Логируем событие после успешной транзакции
            Log::info('Prepayment received', [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'total_deposit' => $result['total_deposit'],
            ]);

            // Создаём событие для real-time обновления через Reverb
            $this->broadcast('reservations', 'prepayment_received', [
                'restaurant_id' => $reservation->restaurant_id,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'total_deposit' => $result['total_deposit'],
            ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Предоплата успешно внесена',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка внесения предоплаты: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при внесении предоплаты: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Печать предзаказа на кухню
     */
    public function printPreorder(Request $request, Reservation $reservation): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        Log::info('printPreorder called', [
            'reservation_id' => $reservation->id,
            'restaurant_id' => $restaurantId,
        ]);

        // Получаем заказ-предзаказ для этой брони
        $preorder = Order::where('reservation_id', $reservation->id)
            ->where('type', 'preorder')
            ->with(['items.dish'])
            ->first();

        Log::info('Preorder found', [
            'preorder_exists' => (bool) $preorder,
            'preorder_id' => $preorder?->id,
            'items_count' => $preorder?->items?->count() ?? 0,
        ]);

        if (!$preorder || $preorder->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет позиций для печати. Убедитесь что предзаказ сохранён.',
            ], 422);
        }

        // Получаем кухонные принтеры
        $kitchenPrinters = \App\Models\Printer::with('kitchenStation')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('type', ['kitchen', 'bar'])
            ->where('is_active', true)
            ->get();

        Log::info('Kitchen printers', [
            'count' => $kitchenPrinters->count(),
            'printers' => $kitchenPrinters->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'is_active' => $p->is_active,
            ])->toArray(),
        ]);

        if ($kitchenPrinters->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроены принтеры для кухни (тип: kitchen или bar)',
            ], 422);
        }

        // Загружаем связи для печати
        $reservation->load('table');

        $results = [];

        foreach ($kitchenPrinters as $printer) {
            $service = new \App\Services\ReceiptService($printer);

            // Фильтруем позиции по цеху принтера
            $items = $this->filterPreorderItemsByStation($preorder->items, $printer);

            if ($items->isEmpty()) continue;

            $content = $service->generatePreorderKitchen($reservation, $items->toArray());

            $job = \App\Models\PrintJob::create([
                'restaurant_id' => $restaurantId,
                'printer_id' => $printer->id,
                'order_id' => $preorder->id,
                'type' => 'kitchen',
                'status' => 'pending',
                'content' => $content,
            ]);

            $result = $job->process();
            $results[] = [
                'printer' => $printer->name,
                'station' => $printer->kitchenStation?->name,
                'items_count' => $items->count(),
                'success' => $result['success'],
                'message' => $result['message'] ?? null,
            ];
        }

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'message' => 'Нет позиций для печати на кухню',
            ], 422);
        }

        $allSuccess = collect($results)->every('success');

        return response()->json([
            'success' => $allSuccess,
            'message' => $allSuccess ? 'Предзаказ отправлен на кухню' : 'Есть ошибки печати',
            'results' => $results,
        ]);
    }

    /**
     * Фильтрация позиций предзаказа по цеху принтера
     */
    private function filterPreorderItemsByStation($items, \App\Models\Printer $printer)
    {
        // Если у принтера не указан цех — он печатает все позиции своего типа
        if (!$printer->kitchen_station_id) {
            // Для барного принтера — только барные позиции
            if ($printer->type === 'bar') {
                return $items->filter(function ($item) {
                    return $item->dish?->category?->is_bar ?? false;
                });
            }
            // Для кухонного принтера без цеха — все не-барные позиции
            return $items->filter(function ($item) {
                return !($item->dish?->category?->is_bar ?? false);
            });
        }

        // Если указан цех — фильтруем по категориям этого цеха
        return $items->filter(function ($item) use ($printer) {
            $categoryStationId = $item->dish?->category?->kitchen_station_id;
            return $categoryStationId === $printer->kitchen_station_id;
        });
    }

    /**
     * Получить сегодняшнюю дату в таймзоне ресторана
     */
    public function businessDate(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $today = TimeHelper::today($restaurantId)->toDateString();

        return response()->json([
            'success' => true,
            'data' => [
                'business_date' => $today,
            ],
        ]);
    }
}