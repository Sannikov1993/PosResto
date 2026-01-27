<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\BonusTransaction;
use App\Services\BonusService;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CustomerController extends Controller
{
    /**
     * Список клиентов
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        // Проверяем включены ли уровни лояльности
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $restaurantId) !== '0';

        $relations = ['addresses', 'defaultAddress'];
        if ($levelsEnabled) {
            $relations[] = 'loyaltyLevel';
        }

        $query = Customer::with($relations)
            ->where('restaurant_id', $restaurantId);

        // Поиск
        if ($request->has('search')) {
            $query->search($request->input('search'));
        }

        // Фильтр по телефону
        if ($request->has('phone')) {
            $query->byPhone($request->input('phone'));
        }

        // Только активные
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Только в чёрном списке
        if ($request->boolean('blacklisted')) {
            $query->blacklisted();
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Пагинация
        $perPage = $request->input('per_page', 50);
        $customers = $query->paginate($perPage);

        // Подсчитываем статистику для каждого клиента (только оплаченные заказы)
        $customersData = collect($customers->items())->map(function ($customer) {
            $ordersStats = $customer->orders()
                ->where('payment_status', 'paid')
                ->selectRaw('COUNT(*) as orders_count')
                ->selectRaw('COALESCE(SUM(total), 0) as orders_total')
                ->first();

            // Присваиваем актуальные значения (фронтенд ожидает total_orders и total_spent)
            $customer->total_orders = (int) ($ordersStats->orders_count ?? 0);
            $customer->total_spent = (float) ($ordersStats->orders_total ?? 0);

            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customersData,
            'meta' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    /**
     * Поиск клиентов
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $restaurantId = $request->input('restaurant_id', 1);

        // Проверяем включены ли уровни лояльности
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $restaurantId) !== '0';

        $query = Customer::where('restaurant_id', $restaurantId)
            ->search($search)
            ->active()
            ->limit(20);

        if ($levelsEnabled) {
            $query->with('loyaltyLevel');
        }

        $customers = $query->get();

        // Если уровни отключены, убираем loyalty_level из ответа
        if (!$levelsEnabled) {
            $customers->each(fn($c) => $c->setRelation('loyaltyLevel', null));
        }

        // Подсчитываем актуальную статистику для каждого клиента
        $customers->each(function ($customer) {
            $ordersStats = $customer->orders()
                ->where('payment_status', 'paid')
                ->selectRaw('COUNT(*) as orders_count')
                ->selectRaw('COALESCE(SUM(total), 0) as orders_total')
                ->first();

            $customer->total_orders = (int) ($ordersStats->orders_count ?? 0);
            $customer->total_spent = (float) ($ordersStats->orders_total ?? 0);
        });

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Топ клиентов
     */
    public function top(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $customers = Customer::where('restaurant_id', $request->input('restaurant_id', 1))
            ->active()
            ->topCustomers($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Клиенты с днём рождения
     */
    public function birthdays(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $restaurantId = $request->input('restaurant_id', 1);

        $customers = Customer::where('restaurant_id', $restaurantId)
            ->active()
            ->whereNotNull('birth_date')
            ->get()
            ->filter(fn($c) => $c->hasBirthdaySoon($days))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Создание клиента
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:100',
            'birth_date' => 'nullable|date',
            'source' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|string',
            'tags' => 'nullable|array',
            'sms_consent' => 'nullable|boolean',
            'email_consent' => 'nullable|boolean',
        ]);

        // Проверка что телефон полный
        if (!Customer::isPhoneComplete($validated['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Введите полный номер телефона (минимум 10 цифр)',
            ], 422);
        }

        // Нормализуем телефон
        $normalizedPhone = Customer::normalizePhone($validated['phone']);

        // Форматируем имя (первая буква каждого слова заглавная)
        $formattedName = Customer::formatName($validated['name'] ?? null);

        $restaurantId = $request->input('restaurant_id', 1);

        // Проверка на дубликат телефона (по нормализованному)
        $existing = Customer::where('restaurant_id', $restaurantId)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ["%{$normalizedPhone}%"])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Клиент с таким телефоном уже существует',
                'data' => $existing,
            ], 422);
        }

        $customer = Customer::create([
            'restaurant_id' => $restaurantId,
            'name' => $formattedName,
            'gender' => $validated['gender'] ?? null,
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'source' => $validated['source'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'preferences' => $validated['preferences'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'bonus_balance' => 0,
            'total_orders' => 0,
            'total_spent' => 0,
            'is_blacklisted' => false,
            'sms_consent' => $validated['sms_consent'] ?? true,
            'email_consent' => $validated['email_consent'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Клиент создан',
            'data' => $customer,
        ], 201);
    }

    /**
     * Показать клиента
     */
    public function show(Customer $customer): JsonResponse
    {
        // Проверяем включены ли уровни лояльности
        $levelsEnabled = \App\Models\LoyaltySetting::get('levels_enabled', '1', $customer->restaurant_id) !== '0';

        $relations = ['addresses', 'reservations' => function($q) {
            $q->latest()->limit(10);
        }];
        if ($levelsEnabled) {
            $relations[] = 'loyaltyLevel';
        }

        $customer->load($relations);

        // Подсчёт статистики (только оплаченные заказы)
        $ordersStats = $customer->orders()
            ->where('payment_status', 'paid')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as orders_total')
            ->first();

        // Присваиваем актуальные значения (фронтенд ожидает total_orders и total_spent)
        $customer->total_orders = (int) ($ordersStats->orders_count ?? 0);
        $customer->total_spent = (float) ($ordersStats->orders_total ?? 0);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Обновление клиента
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'phone' => 'sometimes|string|max:30',
            'email' => 'nullable|email|max:100',
            'birth_date' => 'nullable|date',
            'source' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|string',
            'tags' => 'nullable|array',
            'sms_consent' => 'nullable|boolean',
            'email_consent' => 'nullable|boolean',
        ]);

        // Проверка что телефон полный (если передан)
        if (isset($validated['phone']) && !Customer::isPhoneComplete($validated['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Введите полный номер телефона (минимум 10 цифр)',
            ], 422);
        }

        // Проверка на дубликат телефона при изменении
        if (isset($validated['phone']) && $validated['phone'] !== $customer->phone) {
            $normalizedPhone = Customer::normalizePhone($validated['phone']);
            $existing = Customer::where('restaurant_id', $customer->restaurant_id)
                ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ["%{$normalizedPhone}%"])
                ->where('id', '!=', $customer->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Клиент с таким телефоном уже существует',
                ], 422);
            }
        }

        // Форматируем имя если передано
        if (isset($validated['name'])) {
            $validated['name'] = Customer::formatName($validated['name']);
        }

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Клиент обновлён',
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * Удаление клиента
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Если есть заказы - не удаляем, а помечаем в чёрный список
        if ($customer->orders()->count() > 0) {
            $customer->blacklist();
            return response()->json([
                'success' => true,
                'message' => 'Клиент перемещён в чёрный список (есть связанные заказы)',
            ]);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Клиент удалён',
        ]);
    }

    /**
     * Добавить бонусы
     */
    public function addBonus(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        // Используем BonusService
        $bonusService = new BonusService($customer->restaurant_id ?? 1);
        $bonusService->adjust(
            $customer,
            $validated['points'],
            $validated['reason'] ?? 'Ручное начисление бонусов'
        );

        return response()->json([
            'success' => true,
            'message' => "Начислено {$validated['points']} бонусов",
            'data' => [
                'bonus_balance' => $bonusService->getBalance($customer),
            ],
        ]);
    }

    /**
     * Списать бонусы
     */
    public function useBonus(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        // Используем BonusService
        $bonusService = new BonusService($customer->restaurant_id ?? 1);
        $result = $bonusService->spend(
            $customer,
            $validated['points'],
            null,
            $validated['reason'] ?? 'Ручное списание бонусов'
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Списано {$validated['points']} бонусов",
            'data' => [
                'bonus_balance' => $result['new_balance'],
            ],
        ]);
    }

    /**
     * Добавить в чёрный список
     */
    public function blacklist(Customer $customer): JsonResponse
    {
        $customer->blacklist();

        return response()->json([
            'success' => true,
            'message' => 'Клиент добавлен в чёрный список',
        ]);
    }

    /**
     * Убрать из чёрного списка
     */
    public function unblacklist(Customer $customer): JsonResponse
    {
        $customer->unblacklist();

        return response()->json([
            'success' => true,
            'message' => 'Клиент удалён из чёрного списка',
        ]);
    }

    /**
     * Адреса клиента
     */
    public function addresses(Customer $customer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $customer->addresses,
        ]);
    }

    /**
     * Добавить адрес
     */
    public function addAddress(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:50',
            'street' => 'required|string|max:255',
            'apartment' => 'nullable|string|max:20',
            'entrance' => 'nullable|string|max:10',
            'floor' => 'nullable|string|max:10',
            'intercom' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'comment' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        // Если новый адрес по умолчанию - убираем флаг у остальных
        if ($validated['is_default'] ?? false) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Адрес добавлен',
            'data' => $address,
        ], 201);
    }

    /**
     * Статистика по клиентам
     */
    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        $total = Customer::where('restaurant_id', $restaurantId)->count();
        $active = Customer::where('restaurant_id', $restaurantId)->active()->count();
        $now = TimeHelper::now($restaurantId);
        $newThisMonth = Customer::where('restaurant_id', $restaurantId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $totalBonuses = Customer::where('restaurant_id', $restaurantId)->sum('bonus_balance');
        $avgSpent = Customer::where('restaurant_id', $restaurantId)
            ->where('total_spent', '>', 0)
            ->avg('total_spent');

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'active' => $active,
                'blacklisted' => $total - $active,
                'new_this_month' => $newThisMonth,
                'total_bonuses' => $totalBonuses,
                'avg_spent' => round($avgSpent ?? 0, 2),
            ],
        ]);
    }

    /**
     * Заказы клиента (только оплаченные)
     */
    public function orders(Customer $customer): JsonResponse
    {
        $orders = $customer->orders()
            ->where('payment_status', 'paid')
            ->with(['items.dish', 'table'])
            ->latest()
            ->limit(50)
            ->get();

        // Получаем все бонусные транзакции для этих заказов
        $orderIds = $orders->pluck('id')->toArray();
        $bonusTransactions = BonusTransaction::whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        $mappedOrders = $orders->map(function ($order) use ($bonusTransactions) {
            // Получаем транзакции для этого заказа
            $orderTransactions = $bonusTransactions->get($order->id, collect());

            // Считаем начисленные и списанные бонусы
            $bonusEarned = $orderTransactions->where('amount', '>', 0)->sum('amount');
            $bonusSpent = abs($orderTransactions->where('amount', '<', 0)->sum('amount'));

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'daily_number' => $order->daily_number,
                'type' => $order->type,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => (float) $order->subtotal,
                'total' => (float) $order->total,
                'discount_amount' => (float) ($order->discount_amount ?? 0),
                'bonus_earned' => (float) $bonusEarned,
                'bonus_spent' => (float) $bonusSpent,
                'table' => $order->table ? [
                    'id' => $order->table->id,
                    'name' => $order->table->name,
                ] : null,
                'items_count' => $order->items->count(),
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->dish?->name ?? $item->name,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ]),
                'created_at' => $order->created_at,
                'paid_at' => $order->paid_at,
                'completed_at' => $order->completed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mappedOrders,
        ]);
    }

    /**
     * Все заказы клиента (включая отменённые и неоплаченные) - для бекофиса
     */
    public function allOrders(Customer $customer): JsonResponse
    {
        $orders = $customer->orders()
            ->with(['items.dish', 'table'])
            ->latest()
            ->limit(100)
            ->get();

        // Получаем все бонусные транзакции для этих заказов
        $orderIds = $orders->pluck('id')->toArray();
        $bonusTransactions = BonusTransaction::whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        $mappedOrders = $orders->map(function ($order) use ($bonusTransactions) {
            // Получаем транзакции для этого заказа
            $orderTransactions = $bonusTransactions->get($order->id, collect());

            // Считаем начисленные и списанные бонусы
            $bonusEarned = $orderTransactions->where('amount', '>', 0)->sum('amount');
            $bonusSpent = abs($orderTransactions->where('amount', '<', 0)->sum('amount'));

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'daily_number' => $order->daily_number,
                'type' => $order->type,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => (float) $order->subtotal,
                'total' => (float) $order->total,
                'discount_amount' => (float) ($order->discount_amount ?? 0),
                'bonus_earned' => (float) $bonusEarned,
                'bonus_spent' => (float) $bonusSpent,
                'table' => $order->table ? [
                    'id' => $order->table->id,
                    'name' => $order->table->name,
                ] : null,
                'items_count' => $order->items->count(),
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->dish?->name ?? $item->name,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ]),
                'created_at' => $order->created_at,
                'paid_at' => $order->paid_at,
                'completed_at' => $order->completed_at,
                'cancelled_at' => $order->cancelled_at,
                'cancellation_reason' => $order->cancellation_reason,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mappedOrders,
        ]);
    }

    /**
     * Переключить статус чёрного списка
     */
    public function toggleBlacklist(Customer $customer): JsonResponse
    {
        if ($customer->is_blacklisted) {
            $customer->unblacklist();
            $message = 'Клиент удалён из чёрного списка';
        } else {
            $customer->blacklist();
            $message = 'Клиент добавлен в чёрный список';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * Сохранить адрес из заказа доставки (если он новый)
     */
    public function saveDeliveryAddress(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'street' => 'required|string|max:255',
            'apartment' => 'nullable|string|max:20',
            'entrance' => 'nullable|string|max:10',
            'floor' => 'nullable|string|max:10',
            'intercom' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'comment' => 'nullable|string',
        ]);

        // Проверяем, есть ли уже такой адрес (по улице)
        $normalizedStreet = mb_strtolower(trim($validated['street']));
        $existingAddress = $customer->addresses()
            ->whereRaw('LOWER(TRIM(street)) = ?', [$normalizedStreet])
            ->first();

        if ($existingAddress) {
            // Обновляем существующий адрес если нужно
            $updateData = [];
            if (($validated['apartment'] ?? null) && !$existingAddress->apartment) {
                $updateData['apartment'] = $validated['apartment'];
            }
            if (($validated['entrance'] ?? null) && !$existingAddress->entrance) {
                $updateData['entrance'] = $validated['entrance'];
            }
            if (($validated['floor'] ?? null) && !$existingAddress->floor) {
                $updateData['floor'] = $validated['floor'];
            }
            if (($validated['intercom'] ?? null) && !$existingAddress->intercom) {
                $updateData['intercom'] = $validated['intercom'];
            }
            if (($validated['latitude'] ?? null) && !$existingAddress->latitude) {
                $updateData['latitude'] = $validated['latitude'];
                $updateData['longitude'] = $validated['longitude'] ?? null;
            }

            if (!empty($updateData)) {
                $existingAddress->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Адрес уже сохранён',
                'data' => $existingAddress->fresh(),
                'is_new' => false,
            ]);
        }

        // Создаём новый адрес
        // Если это первый адрес - делаем его по умолчанию
        $isDefault = $customer->addresses()->count() === 0;

        $address = $customer->addresses()->create([
            'title' => 'Доставка',
            'street' => $validated['street'],
            'apartment' => $validated['apartment'] ?? null,
            'entrance' => $validated['entrance'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'intercom' => $validated['intercom'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Адрес сохранён',
            'data' => $address,
            'is_new' => true,
        ], 201);
    }

    /**
     * Удалить адрес клиента
     */
    public function deleteAddress(Customer $customer, CustomerAddress $address): JsonResponse
    {
        // Проверяем что адрес принадлежит клиенту
        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Адрес не принадлежит этому клиенту',
            ], 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // Если удалили адрес по умолчанию - назначаем первый оставшийся
        if ($wasDefault) {
            $firstAddress = $customer->addresses()->first();
            if ($firstAddress) {
                $firstAddress->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Адрес удалён',
        ]);
    }

    /**
     * Установить адрес по умолчанию
     */
    public function setDefaultAddress(Customer $customer, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Адрес не принадлежит этому клиенту',
            ], 403);
        }

        // Убираем флаг у всех
        $customer->addresses()->update(['is_default' => false]);

        // Устанавливаем новый по умолчанию
        $address->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Адрес установлен по умолчанию',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * История бонусов клиента
     */
    public function bonusHistory(Customer $customer): JsonResponse
    {
        $transactions = $customer->bonusTransactions()
            ->with('order:id,order_number,daily_number,type')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->type_label,
                'type_icon' => $t->type_icon,
                'amount' => (float) $t->amount,
                'formatted_amount' => $t->formatted_amount,
                'balance_after' => (float) $t->balance_after,
                'description' => $t->description,
                'order_id' => $t->order_id,
                'order_number' => $t->order?->order_number ?? $t->order?->daily_number,
                'order_type' => $t->order?->type,
                'created_at' => $t->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
