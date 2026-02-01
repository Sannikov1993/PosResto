<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\Order;
use App\Models\Dish;
use App\Models\RealtimeEvent;
use App\Models\CashOperation;
use App\Models\CashShift;
use App\Models\Customer;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    use Traits\ResolvesRestaurantId;
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

        $validated = $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
            'table_ids' => 'nullable|array',                   // Для мультивыбора
            'table_ids.*' => 'integer|exists:tables,id',
            'guest_name' => 'nullable|string|max:100',
            'guest_phone' => 'nullable|string|max:20',
            'guest_email' => 'nullable|email|max:100',
            'date' => 'required|date|after_or_equal:today',
            'time_from' => 'required|date_format:H:i',
            'time_to' => 'required|date_format:H:i|after:time_from',
            'guests_count' => 'required|integer|min:1|max:50',
            'notes' => 'nullable|string|max:500',
            'special_requests' => 'nullable|string|max:500',
            'deposit' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
        ]);

        // Проверка что время не в прошлом для сегодняшней даты
        $tz = TimeHelper::getTimezone($restaurantId);
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

        try {
            $reservation = DB::transaction(function () use ($validated, $restaurantId, $allTableIds, $tables) {
                // Проверяем конфликты с блокировкой (предотвращение race condition)
                foreach ($allTableIds as $tableId) {
                    if (Reservation::hasConflictWithLock(
                        $tableId,
                        $validated['date'],
                        $validated['time_from'],
                        $validated['time_to']
                    )) {
                        $table = Table::find($tableId);
                        throw new \Exception("Стол {$table->number} уже занят в это время. Выберите другое время.");
                    }
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

                return Reservation::create([
                    'restaurant_id' => $restaurantId,
                    'table_id' => $validated['table_id'],
                    'linked_table_ids' => !empty($linkedTableIds) ? $linkedTableIds : null,
                    'customer_id' => $customerId,
                    'guest_name' => $validated['guest_name'] ?? null,
                    'guest_phone' => $validated['guest_phone'] ?? null,
                    'guest_email' => $validated['guest_email'] ?? null,
                    'date' => $validated['date'],
                    'time_from' => $validated['time_from'],
                    'time_to' => $validated['time_to'],
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
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $reservation->restaurant_id ?? 1) !== '0';

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
        $date = $validated['date'] ?? $reservation->date;
        $timeFrom = $validated['time_from'] ?? substr($reservation->time_from, 0, 5);
        $timeTo = $validated['time_to'] ?? substr($reservation->time_to, 0, 5);

        // Получаем текущие значения в правильном формате для сравнения
        $currentDate = $reservation->date instanceof \Carbon\Carbon
            ? $reservation->date->format('Y-m-d')
            : substr($reservation->date, 0, 10);
        $currentTimeFrom = substr($reservation->time_from, 0, 5);
        $currentTimeTo = substr($reservation->time_to, 0, 5);

        // Проверяем конфликты только если значения РЕАЛЬНО изменились
        $tableChanged = isset($validated['table_id']) && (int)$validated['table_id'] !== (int)$reservation->table_id;
        $dateChanged = isset($validated['date']) && $validated['date'] !== $currentDate;
        $timeFromChanged = isset($validated['time_from']) && $validated['time_from'] !== $currentTimeFrom;
        $timeToChanged = isset($validated['time_to']) && $validated['time_to'] !== $currentTimeTo;

        $needsConflictCheck = $tableChanged || $dateChanged || $timeFromChanged || $timeToChanged;

        try {
            $updatedReservation = DB::transaction(function () use ($validated, $reservation, $tableId, $date, $timeFrom, $timeTo, $needsConflictCheck) {
                // Проверяем конфликты с блокировкой (предотвращение race condition)
                if ($needsConflictCheck) {
                    if (Reservation::hasConflictWithLock($tableId, $date, $timeFrom, $timeTo, $reservation->id)) {
                        throw new \Exception('Это время уже занято');
                    }
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

        $request->validate([
            'reason' => 'nullable|string|max:500',
            'refund_deposit' => 'nullable|boolean',
            'refund_method' => 'nullable|in:cash,card',
        ]);

        $reason = $request->input('reason');
        $refundDeposit = $request->boolean('refund_deposit', false);
        $refundMethod = $request->input('refund_method', 'cash');

        try {
            $result = DB::transaction(function () use ($request, $reservation, $reason, $refundDeposit, $refundMethod) {
                // Собираем все столы брони
                $allTableIds = $reservation->linked_table_ids ?? [];
                if (!in_array($reservation->table_id, $allTableIds)) {
                    array_unshift($allTableIds, $reservation->table_id);
                }

                $depositRefunded = 0;

                // Возврат депозита если запрошен и депозит был оплачен
                if ($refundDeposit && $reservation->deposit > 0 && $reservation->deposit_status === 'paid') {
                    $depositRefunded = $reservation->deposit;

                    // Записываем возврат в кассу с информацией об оригинальной оплате
                    CashOperation::recordDepositRefund(
                        $reservation->restaurant_id ?? 1,
                        $reservation->id,
                        $depositRefunded,
                        $reservation->deposit_payment_method ?? $refundMethod,
                        auth()->id() ?? 1,
                        $reservation->guest_name,
                        $reason ?? 'Отмена брони',
                        $reservation->deposit_operation_id,
                        $reservation->deposit_paid_at?->toDateTimeString()
                    );

                    // Обновляем статус депозита
                    $reservation->deposit_status = 'refunded';
                }

                // Отменяем бронь
                $updateData = [
                    'status' => 'cancelled',
                    'notes' => $reservation->notes
                        ? $reservation->notes . "\nПричина отмены: " . ($reason ?? 'не указана')
                            . ($depositRefunded > 0 ? " (депозит {$depositRefunded}₽ возвращён)" : '')
                        : "Причина отмены: " . ($reason ?? 'не указана')
                            . ($depositRefunded > 0 ? " (депозит {$depositRefunded}₽ возвращён)" : ''),
                ];

                if ($depositRefunded > 0) {
                    $updateData['deposit_status'] = 'refunded';
                }

                $reservation->update($updateData);

                // Отменяем связанный предзаказ, если есть
                $preorder = Order::where('reservation_id', $reservation->id)
                    ->where('type', 'preorder')
                    ->whereIn('status', ['new', 'confirmed'])
                    ->first();

                if ($preorder) {
                    $preorder->update(['status' => 'cancelled']);
                }

                return [
                    'reservation' => $reservation,
                    'tableIds' => $allTableIds,
                    'deposit_refunded' => $depositRefunded,
                ];
            });

            // Real-time событие
            RealtimeEvent::dispatch(
                RealtimeEvent::CHANNEL_RESERVATIONS,
                'reservation_cancelled',
                [
                    'restaurant_id' => $reservation->restaurant_id ?? 1,
                    'reservation_id' => $reservation->id,
                    'table_id' => $reservation->table_id,
                    'deposit_refunded' => $result['deposit_refunded'],
                ]
            );

            $message = 'Бронирование отменено';
            if ($result['deposit_refunded'] > 0) {
                $message .= " (депозит {$result['deposit_refunded']}₽ возвращён)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result['reservation']->fresh(),
                'deposit_refunded' => $result['deposit_refunded'],
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка отмены брони: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отмене брони: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Гости сели за стол
     */
    public function seat(Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя посадить гостей для этой брони',
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
     * Снять гостей со стола - вернуть бронь в статус confirmed
     */
    public function unseat(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'seated') {
            return response()->json([
                'success' => false,
                'message' => 'Можно снять только посаженных гостей',
            ], 422);
        }

        $reservation->unseat();

        return response()->json([
            'success' => true,
            'message' => 'Гости сняты со стола',
            'data' => ['reservation' => $reservation->fresh(['table'])],
        ]);
    }

    /**
     * Гости пришли - создать заказ из брони
     */
    public function seatWithOrder(Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя посадить гостей для этой брони',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($reservation) {
                $allTableIds = $reservation->linked_table_ids ?? [];
                if (!in_array($reservation->table_id, $allTableIds)) {
                    array_unshift($allTableIds, $reservation->table_id);
                }

                // Обновляем статус брони
                $reservation->update(['status' => 'seated']);

                // Проверяем, есть ли предзаказ для этой брони (сначала по reservation_id, потом по table_id)
                $existingOrder = Order::where('reservation_id', $reservation->id)
                    ->where('type', 'preorder')
                    ->first();

                // Если не нашли по reservation_id, ищем по table_id (на случай если связь потерялась)
                if (!$existingOrder) {
                    $existingOrder = Order::where('table_id', $reservation->table_id)
                        ->where('type', 'preorder')
                        ->whereIn('status', ['new', 'confirmed'])
                        ->first();

                    // Если нашли - привязываем к брони
                    if ($existingOrder) {
                        $existingOrder->update(['reservation_id' => $reservation->id]);
                    }
                }

                // Определяем, есть ли оплаченный депозит для переноса в заказ
                $depositAmount = 0;
                $depositMethod = null;
                if ($reservation->isDepositPaid() && $reservation->deposit > 0) {
                    $depositAmount = $reservation->deposit;
                    $depositMethod = $reservation->deposit_payment_method;
                }

                // Получаем или создаём клиента из данных брони
                $customerId = $reservation->customer_id;
                if (!$customerId && $reservation->guest_phone) {
                    // Ищем клиента по телефону
                    $normalizedPhone = preg_replace('/[^0-9]/', '', $reservation->guest_phone);
                    $customer = Customer::where('restaurant_id', $reservation->restaurant_id)
                        ->byPhone($normalizedPhone)
                        ->first();

                    if ($customer) {
                        $customerId = $customer->id;
                        // Обновляем имя если нужно
                        if ($reservation->guest_name && !$customer->name) {
                            $customer->update(['name' => Customer::formatName($reservation->guest_name)]);
                        }
                    } else {
                        // Создаём нового клиента
                        $customer = Customer::create([
                            'restaurant_id' => $reservation->restaurant_id,
                            'phone' => $reservation->guest_phone,
                            'name' => Customer::formatName($reservation->guest_name) ?? 'Гость',
                            'email' => $reservation->guest_email,
                            'source' => 'reservation',
                        ]);
                        $customerId = $customer->id;

                        // Привязываем клиента к брони
                        $reservation->update(['customer_id' => $customerId]);
                    }
                }

                if ($existingOrder) {
                    // Конвертируем предзаказ в обычный заказ
                    $updateData = [
                        'type' => 'dine_in',
                        'user_id' => auth()->id() ?? $existingOrder->user_id,
                        'customer_id' => $customerId ?? $existingOrder->customer_id,
                    ];

                    // Если есть оплаченный депозит - переносим в заказ
                    if ($depositAmount > 0) {
                        $updateData['paid_amount'] = ($existingOrder->paid_amount ?? 0) + $depositAmount;
                        $updateData['payment_method'] = $depositMethod;
                    }

                    $existingOrder->update($updateData);

                    // Переводим сохранённые позиции в pending для отправки на кухню
                    $existingOrder->items()->where('status', 'saved')->update(['status' => 'pending']);

                    $order = $existingOrder;
                } else {
                    // Создаём новый заказ
                    $orderData = [
                        'restaurant_id' => $reservation->restaurant_id,
                        'table_id' => $reservation->table_id,
                        'linked_table_ids' => count($allTableIds) > 1 ? $allTableIds : null,
                        'reservation_id' => $reservation->id,
                        'customer_id' => $customerId,
                        'order_number' => Order::generateOrderNumber($reservation->restaurant_id),
                        'type' => 'dine_in',
                        'status' => 'new',
                        'payment_status' => 'pending',
                        'subtotal' => 0,
                        'total' => 0,
                        'persons' => $reservation->guests_count ?? $reservation->guests ?? 2,
                        'user_id' => auth()->id(),
                    ];

                    // Если есть оплаченный депозит - переносим в заказ
                    if ($depositAmount > 0) {
                        $orderData['paid_amount'] = $depositAmount;
                        $orderData['payment_method'] = $depositMethod;
                    }

                    $order = Order::create($orderData);
                }

                // Депозит НЕ переводим в transferred здесь - это делается при оплате заказа
                // в TableOrderController::pay()

                // Занимаем все столы
                foreach ($allTableIds as $tableId) {
                    $table = Table::find($tableId);
                    if ($table) {
                        $table->update(['status' => 'occupied']);
                    }
                }

                return [
                    'reservation' => $reservation,
                    'order' => $order,
                    'tableIds' => $allTableIds,
                    'deposit_transferred' => $depositAmount,
                ];
            });

            // Real-time события отправляем после успешной транзакции
            foreach ($result['tableIds'] as $tableId) {
                RealtimeEvent::tableStatusChanged($tableId, 'occupied');
            }

            $message = 'Гости сели, заказ создан';
            if ($result['deposit_transferred'] > 0) {
                $message .= " (депозит {$result['deposit_transferred']}₽ учтён)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'reservation' => $result['reservation']->fresh(['table']),
                    'order' => $result['order'],
                    'deposit_transferred' => $result['deposit_transferred'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка посадки гостей: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при посадке гостей: ' . $e->getMessage(),
            ], 500);
        }
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

        $item = $order->items()->create([
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
    public function complete(Reservation $reservation): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($reservation) {
                // Собираем все столы брони
                $allTableIds = $reservation->linked_table_ids ?? [];
                if (!in_array($reservation->table_id, $allTableIds)) {
                    array_unshift($allTableIds, $reservation->table_id);
                }

                // Завершаем бронь
                $reservation->update(['status' => 'completed']);

                // Освобождаем столы (если нет активных заказов)
                foreach ($allTableIds as $tableId) {
                    $table = Table::find($tableId);
                    if ($table) {
                        // Проверяем, есть ли активные заказы на столе
                        $activeOrders = Order::where('table_id', $tableId)
                            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready'])
                            ->where('type', '!=', 'preorder')
                            ->count();

                        if ($activeOrders === 0) {
                            $table->update(['status' => 'free']);
                        }
                    }
                }

                return ['reservation' => $reservation, 'tableIds' => $allTableIds];
            });

            // Real-time события после успешной транзакции
            foreach ($result['tableIds'] as $tableId) {
                $table = Table::find($tableId);
                if ($table && $table->status === 'free') {
                    RealtimeEvent::tableStatusChanged($tableId, 'free');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Бронирование завершено',
                'data' => $result['reservation']->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка завершения брони: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при завершении брони: ' . $e->getMessage(),
            ], 500);
        }
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
        $request->validate([
            'method' => 'required|in:cash,card',
            'amount' => 'nullable|numeric|min:1',
        ]);

        $isAddingMore = $reservation->deposit_status === Reservation::DEPOSIT_PAID;

        // Проверяем, можно ли принять депозит
        if (!$reservation->canPayDeposit() && !$isAddingMore) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно принять депозит для этой брони',
            ], 422);
        }

        // Если добавляем к уже оплаченному - берём сумму из запроса
        // Иначе берём сумму депозита из брони
        $amount = $isAddingMore ? $request->input('amount') : $reservation->deposit;

        if (!$amount || $amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Укажите сумму депозита',
            ], 422);
        }
        $method = $request->input('method');
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

        // Получаем ID пользователя (или используем 1 как дефолт для системы)
        $userId = auth()->id() ?? 1;

        try {
            $result = DB::transaction(function () use ($reservation, $amount, $method, $restaurantId, $userId, $isAddingMore) {
                // Записываем в кассу
                $operation = CashOperation::recordPrepayment(
                    $restaurantId,
                    $reservation->id,
                    $amount,
                    $method,
                    $userId,
                    $reservation->guest_name
                );

                // Если добавляем к уже оплаченному - увеличиваем сумму
                if ($isAddingMore) {
                    $newTotal = $reservation->deposit + $amount;
                    $reservation->update([
                        'deposit' => $newTotal,
                        'deposit_payment_method' => $method,
                    ]);
                } else {
                    // Обновляем статус депозита
                    $reservation->payDeposit($method, $userId, $operation->id);
                }

                return [
                    'amount' => $amount,
                    'new_total' => $isAddingMore ? ($reservation->deposit) : $amount,
                    'method' => $method,
                    'operation_id' => $operation->id,
                ];
            });

            // Логируем событие
            Log::info('Deposit paid', [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
            ]);

            // Создаём событие для real-time обновления
            RealtimeEvent::dispatch(
                RealtimeEvent::CHANNEL_RESERVATIONS,
                'deposit_paid',
                [
                    'restaurant_id' => $reservation->restaurant_id ?? 1,
                    'reservation_id' => $reservation->id,
                    'amount' => $amount,
                    'method' => $method,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $isAddingMore ? 'Депозит успешно пополнен' : 'Депозит успешно оплачен',
                'data' => [
                    'reservation' => $reservation->fresh(),
                    'amount' => $result['amount'],
                    'method' => $result['method'],
                ],
                'new_total' => $reservation->fresh()->deposit,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка приёма депозита: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при приёме депозита: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Вернуть депозит за бронь
     */
    public function refundDeposit(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Проверяем, можно ли вернуть депозит
        if (!$reservation->canRefundDeposit()) {
            return response()->json([
                'success' => false,
                'message' => 'Невозможно вернуть депозит для этой брони',
            ], 422);
        }

        $amount = $reservation->deposit;
        // Возврат делаем тем же способом, каким была оплата
        $method = $reservation->deposit_payment_method ?? 'cash';
        $reason = $request->input('reason');
        $restaurantId = $reservation->restaurant_id;

        // Получаем информацию об оригинальной оплате для отслеживания кросс-сменных возвратов
        $originalOperationId = $reservation->deposit_operation_id;
        $originalPaidAt = $reservation->deposit_paid_at;

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

        // Получаем ID пользователя
        $userId = auth()->id() ?? 1;

        try {
            $result = DB::transaction(function () use ($reservation, $amount, $method, $reason, $restaurantId, $userId, $originalOperationId, $originalPaidAt) {
                // Записываем возврат в кассу с информацией об оригинальной оплате
                $operation = CashOperation::recordDepositRefund(
                    $restaurantId,
                    $reservation->id,
                    $amount,
                    $method,
                    $userId,
                    $reservation->guest_name,
                    $reason,
                    $originalOperationId,
                    $originalPaidAt?->toDateTimeString()
                );

                // Обновляем статус депозита
                $reservation->refundDeposit();

                return [
                    'amount' => $amount,
                    'method' => $method,
                    'operation_id' => $operation->id,
                ];
            });

            // Логируем событие
            Log::info('Deposit refunded', [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'original_paid_at' => $originalPaidAt,
            ]);

            // Создаём событие для real-time обновления
            RealtimeEvent::dispatch(
                RealtimeEvent::CHANNEL_RESERVATIONS,
                'deposit_refunded',
                [
                    'restaurant_id' => $reservation->restaurant_id ?? 1,
                    'reservation_id' => $reservation->id,
                    'amount' => $amount,
                    'method' => $method,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Депозит успешно возвращён',
                'data' => [
                    'reservation' => $reservation->fresh(),
                    'amount' => $result['amount'],
                    'method' => $result['method'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка возврата депозита: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'method' => $method,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при возврате депозита: ' . $e->getMessage(),
            ], 500);
        }
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
                    $reservation->restaurant_id ?? 1,
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

            // Создаём событие для real-time обновления
            RealtimeEvent::dispatch(
                RealtimeEvent::CHANNEL_RESERVATIONS,
                'prepayment_received',
                [
                    'restaurant_id' => $reservation->restaurant_id ?? 1,
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