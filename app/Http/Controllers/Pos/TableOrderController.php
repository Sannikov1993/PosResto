<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\RealtimeEvent;
use App\Models\CashOperation;
use App\Models\CashShift;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\BonusSetting;
use App\Models\BonusTransaction;
use App\Models\LoyaltySetting;
use App\Models\Promotion;
use App\Services\BonusService;
use App\Services\PaymentService;
use App\Traits\BroadcastsEvents;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TableOrderController extends Controller
{
    use BroadcastsEvents;
    /**
     * Показать страницу заказа для стола
     */
    public function show(Request $request, Table $table)
    {
        $table->load(['zone']);
        $initialGuests = $request->input('guests', null);

        // Получаем ID брони из URL (для предзаказов)
        $reservationId = $request->input('reservation');

        // Получаем связанные столы из URL (для объединённых заказов)
        $linkedTablesParam = $request->input('linked_tables', null);
        $linkedTableIds = null;
        if ($linkedTablesParam) {
            $linkedTableIds = array_map('intval', explode(',', $linkedTablesParam));
        }

        // Получаем активные заказы для стола (включая связанные)
        $orders = Order::where(function($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhereRaw("linked_table_ids LIKE ?", ['%' . $table->id . '%']);
            })
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
            ->get();

        // Удаляем лишние пустые заказы (оставляем только один пустой)
        $emptyOrders = $orders->filter(fn($o) => $o->items->isEmpty() && !$o->reservation_id);
        $nonEmptyOrders = $orders->filter(fn($o) => $o->items->isNotEmpty() || $o->reservation_id);
        if ($emptyOrders->count() > 1) {
            $emptyOrders->skip(1)->each(fn($o) => $o->delete());
            $orders = $nonEmptyOrders->merge($emptyOrders->take(1))->sortBy('id')->values();
        }

        // Если переданы linked_tables - нужен заказ с этими связями
        if ($linkedTableIds) {
            // Удаляем все пустые заказы без связей - будем создавать новый
            $orders->filter(fn($o) => $o->items->isEmpty() && empty($o->linked_table_ids) && !$o->reservation_id)
                   ->each(fn($o) => $o->delete());

            // Пересчитываем заказы
            $orders = Order::where(function($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhereRaw("linked_table_ids LIKE ?", ['%' . $table->id . '%']);
            })
                ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
                ->where('payment_status', 'pending')
                ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
                ->get();
        }

        // Если нет активного заказа - создаём новый
        if ($orders->isEmpty()) {
            $today = Carbon::today();
            $orderCount = Order::whereDate('created_at', $today)->count() + 1;
            $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

            $newOrder = Order::create([
                'restaurant_id' => $table->restaurant_id,
                'order_number' => $orderNumber,
                'daily_number' => '#' . $orderNumber,
                'type' => 'dine_in',
                'table_id' => $table->id,
                'linked_table_ids' => $linkedTableIds,
                'status' => 'new',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
            ]);

            $orders = collect([$newOrder->load('items')]);

            // НЕ занимаем стол - он станет occupied только после добавления первого блюда
        }

        // Получаем категории с блюдами (с проверкой стоп-листа)
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['dishes' => function ($query) {
                $query->orderBy('sort_order')
                      ->with(['stopListEntry', 'category']);
            }])
            ->get()
            ->filter(fn($cat) => $cat->dishes->count() > 0)
            ->map(function ($category) {
                // Градиенты для разнообразия
                $gradients = [
                    'bg-gradient-to-br from-orange-400 to-red-500',
                    'bg-gradient-to-br from-blue-400 to-indigo-500',
                    'bg-gradient-to-br from-green-400 to-emerald-500',
                    'bg-gradient-to-br from-purple-400 to-pink-500',
                    'bg-gradient-to-br from-yellow-400 to-orange-500',
                    'bg-gradient-to-br from-cyan-400 to-blue-500',
                ];

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon ?? '📦',
                    'products' => $category->dishes->map(function ($dish) use ($gradients) {
                        // Проверяем доступность: is_available И не в стоп-листе
                        $inStopList = $dish->stopListEntry !== null;
                        $isAvailable = $dish->is_available && !$inStopList;

                        return [
                            'id' => $dish->id,
                            'name' => $dish->name,
                            'price' => (float) $dish->price,
                            'icon' => $this->getDishIcon($dish),
                            'weight' => $dish->weight,
                            'cooking_time' => $dish->cooking_time,
                            'is_available' => $isAvailable,
                            'is_popular' => $dish->is_popular,
                            'is_new' => $dish->is_new,
                            'is_spicy' => $dish->is_spicy,
                            'gradient' => $gradients[$dish->id % count($gradients)],
                        ];
                    }),
                ];
            })->values();

        // Получаем связанные столы из существующего заказа если не заданы
        if (!$linkedTableIds && $orders->isNotEmpty()) {
            $firstOrder = $orders->first();
            if (!empty($firstOrder->linked_table_ids)) {
                $linkedTableIds = $firstOrder->linked_table_ids;
            }
        }

        // Проверяем есть ли бронь у заказа или в параметре URL
        $reservation = null;
        $reservationId = $request->input('reservation');

        // Сначала проверяем параметр из URL (загружаем customer для бонусов)
        if ($reservationId) {
            $reservation = Reservation::forRestaurant($table->restaurant_id)
                ->with('customer')
                ->find($reservationId);
        }

        // Если не найдена в URL - ищем в заказе
        if (!$reservation) {
            $firstOrder = $orders->first();
            if ($firstOrder && $firstOrder->reservation_id) {
                $reservation = Reservation::forRestaurant($table->restaurant_id)
                    ->with('customer')
                    ->find($firstOrder->reservation_id);
            }
        }

        // Добавляем prepayment к заказам с бронью
        $restaurantId = $table->restaurant_id;
        $orders = $orders->map(function ($order) use ($restaurantId) {
            if ($order->reservation_id) {
                $res = Reservation::forRestaurant($restaurantId)->find($order->reservation_id);
                $order->prepayment = $res ? $res->deposit : 0;
            }
            return $order;
        });

        // Автоматически конвертируем preorder в dine_in при открытии страницы заказа
        // (единый интерфейс для всех заказов по брони)
        $orders->each(function ($order) {
            if ($order->type === 'preorder') {
                $order->update(['type' => 'dine_in']);
                $order->type = 'dine_in'; // Обновляем и в памяти
                // Переводим saved позиции в pending
                $order->items()->where('status', 'saved')->update(['status' => 'pending']);
            }
        });

        return view('pos.table-order-vue', [
            'table' => $table,
            'orders' => $orders,
            'categories' => $categories,
            'initialGuests' => $initialGuests,
            'linkedTableIds' => $linkedTableIds,
            'reservation' => $reservation,
        ]);
    }

    /**
     * Показать страницу заказа для стола (Vue SFC версия)
     */
    public function showVue(Request $request, Table $table)
    {
        // Используем тот же код что и show(), но возвращаем другой view
        $table->load(['zone']);
        $initialGuests = $request->input('guests', null);

        $linkedTablesParam = $request->input('linked_tables', null);
        $linkedTableIds = null;
        if ($linkedTablesParam) {
            $linkedTableIds = array_map('intval', explode(',', $linkedTablesParam));
        }

        // Получаем ID брони из URL
        $reservationId = $request->input('reservation');

        $orders = Order::where(function($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhereRaw("linked_table_ids LIKE ?", ['%' . $table->id . '%']);
            })
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
            ->get();

        $emptyOrders = $orders->filter(fn($o) => $o->items->isEmpty() && !$o->reservation_id);
        $nonEmptyOrders = $orders->filter(fn($o) => $o->items->isNotEmpty() || $o->reservation_id);
        if ($emptyOrders->count() > 1) {
            $emptyOrders->skip(1)->each(fn($o) => $o->delete());
            $orders = $nonEmptyOrders->merge($emptyOrders->take(1))->sortBy('id')->values();
        }

        if ($orders->isEmpty()) {
            $today = Carbon::today();
            $orderCount = Order::whereDate('created_at', $today)->count() + 1;
            $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

            $newOrder = Order::create([
                'restaurant_id' => $table->restaurant_id,
                'order_number' => $orderNumber,
                'daily_number' => '#' . $orderNumber,
                'type' => 'dine_in',
                'table_id' => $table->id,
                'linked_table_ids' => $linkedTableIds,
                'status' => 'new',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
            ]);

            $orders = collect([$newOrder->load('items')]);
        }

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['dishes' => function ($query) {
                $query->orderBy('sort_order')
                      ->with(['stopListEntry', 'category']);
            }])
            ->get()
            ->filter(fn($cat) => $cat->dishes->count() > 0)
            ->map(function ($category) {
                $gradients = [
                    'bg-gradient-to-br from-orange-400 to-red-500',
                    'bg-gradient-to-br from-blue-400 to-indigo-500',
                    'bg-gradient-to-br from-green-400 to-emerald-500',
                    'bg-gradient-to-br from-purple-400 to-pink-500',
                    'bg-gradient-to-br from-yellow-400 to-orange-500',
                    'bg-gradient-to-br from-cyan-400 to-blue-500',
                ];

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon ?? '📦',
                    'products' => $category->dishes->map(function ($dish) use ($gradients) {
                        $inStopList = $dish->stopListEntry !== null;
                        $isAvailable = $dish->is_available && !$inStopList;

                        return [
                            'id' => $dish->id,
                            'name' => $dish->name,
                            'price' => (float) $dish->price,
                            'icon' => $this->getDishIcon($dish),
                            'weight' => $dish->weight,
                            'cooking_time' => $dish->cooking_time,
                            'is_available' => $isAvailable,
                            'is_popular' => $dish->is_popular,
                            'is_new' => $dish->is_new,
                            'is_spicy' => $dish->is_spicy,
                            'gradient' => $gradients[$dish->id % count($gradients)],
                        ];
                    }),
                ];
            })->values();

        if (!$linkedTableIds && $orders->isNotEmpty()) {
            $firstOrder = $orders->first();
            if (!empty($firstOrder->linked_table_ids)) {
                $linkedTableIds = $firstOrder->linked_table_ids;
            }
        }

        // Проверяем есть ли бронь в параметре URL или в заказе
        $reservation = null;

        // Сначала проверяем параметр из URL (загружаем customer для бонусов)
        if ($reservationId) {
            $reservation = Reservation::forRestaurant($table->restaurant_id)
                ->with('customer')
                ->find($reservationId);
        }

        // Если не найдена в URL - ищем в заказе
        if (!$reservation) {
            $firstOrder = $orders->first();
            if ($firstOrder && $firstOrder->reservation_id) {
                $reservation = Reservation::forRestaurant($table->restaurant_id)
                    ->with('customer')
                    ->find($firstOrder->reservation_id);
            }
        }

        $restaurantId = $table->restaurant_id;
        $orders = $orders->map(function ($order) use ($restaurantId) {
            if ($order->reservation_id) {
                $res = Reservation::forRestaurant($restaurantId)->find($order->reservation_id);
                $order->prepayment = $res ? $res->deposit : 0;
            }
            return $order;
        });

        return view('pos.table-order-vue', [
            'table' => $table,
            'orders' => $orders,
            'categories' => $categories,
            'initialGuests' => $initialGuests,
            'linkedTableIds' => $linkedTableIds,
            'reservation' => $reservation,
        ]);
    }

    /**
     * Получить актуальное меню (API для обновления в реальном времени)
     */
    public function getMenu(Request $request, Table $table)
    {
        $priceListId = $request->input('price_list_id') ? (int) $request->input('price_list_id') : null;
        $categories = $this->getCategoriesWithProducts($priceListId);

        return response()->json($categories);
    }

    /**
     * Get menu data without table requirement (for bar, etc.)
     */
    public function getMenuData(Request $request)
    {
        $priceListId = $request->input('price_list_id') ? (int) $request->input('price_list_id') : null;
        $categories = $this->getCategoriesWithProducts($priceListId);

        return response()->json($categories);
    }

    /**
     * Get table order data as JSON (for modal/embedded use)
     * Note: We avoid route model binding here because of TenantManager/middleware ordering issues
     */
    public function getData(Request $request, $table)
    {
        // If $table is not already a model, load it manually
        if (!$table instanceof Table) {
            $table = Table::findOrFail($table);
        }

        $data = $this->prepareTableOrderData($request, $table);

        // Get linked table numbers for display (только столы того же ресторана)
        $linkedTableNumbers = $table->name ?? $table->number;
        if (!empty($data['linkedTableIds'])) {
            $linkedTables = Table::forRestaurant($table->restaurant_id)
                ->whereIn('id', $data['linkedTableIds'])
                ->pluck('name', 'id');
            $names = collect($data['linkedTableIds'])->map(fn($id) => $linkedTables[$id] ?? $id);
            $linkedTableNumbers = $names->join(', ');
        }

        return response()->json([
            'success' => true,
            'table' => $data['table'],
            'orders' => $data['orders'],
            'categories' => $data['categories'],
            'initialGuests' => $data['initialGuests'],
            'linkedTableIds' => $data['linkedTableIds'],
            'linkedTableNumbers' => $linkedTableNumbers,
            'reservation' => $data['reservation'],
        ]);
    }

    /**
     * Prepare table order data (shared between show, showVue, getData)
     */
    private function prepareTableOrderData(Request $request, Table $table): array
    {
        $table->load(['zone']);
        $initialGuests = $request->input('guests', null);
        $reservationId = $request->input('reservation');

        $linkedTablesParam = $request->input('linked_tables', null);
        $linkedTableIds = null;
        if ($linkedTablesParam) {
            $linkedTableIds = array_map('intval', explode(',', $linkedTablesParam));
        }

        // Get active orders for table
        $orders = Order::where(function($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhereRaw("linked_table_ids LIKE ?", ['%' . $table->id . '%']);
            })
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
            ->get();

        // Remove extra empty orders
        $emptyOrders = $orders->filter(fn($o) => $o->items->isEmpty() && !$o->reservation_id);
        $nonEmptyOrders = $orders->filter(fn($o) => $o->items->isNotEmpty() || $o->reservation_id);
        if ($emptyOrders->count() > 1) {
            $emptyOrders->skip(1)->each(fn($o) => $o->delete());
            $orders = $nonEmptyOrders->merge($emptyOrders->take(1))->sortBy('id')->values();
        }

        // Handle linked tables
        if ($linkedTableIds) {
            $orders->filter(fn($o) => $o->items->isEmpty() && empty($o->linked_table_ids) && !$o->reservation_id)
                   ->each(fn($o) => $o->delete());

            $orders = Order::where(function($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhereRaw("linked_table_ids LIKE ?", ['%' . $table->id . '%']);
            })
                ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
                ->where('payment_status', 'pending')
                ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
                ->get();
        }

        // Create new order if none exists
        if ($orders->isEmpty()) {
            $today = Carbon::today();
            $orderCount = Order::whereDate('created_at', $today)->count() + 1;
            $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

            $newOrder = Order::create([
                'restaurant_id' => $table->restaurant_id,
                'order_number' => $orderNumber,
                'daily_number' => '#' . $orderNumber,
                'type' => 'dine_in',
                'table_id' => $table->id,
                'linked_table_ids' => $linkedTableIds,
                'status' => 'new',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
            ]);

            $orders = collect([$newOrder->load('items')]);
        }

        // Get categories with products
        $categories = $this->getCategoriesWithProducts();

        // Get linked table IDs from existing order
        if (!$linkedTableIds && $orders->isNotEmpty()) {
            $firstOrder = $orders->first();
            if (!empty($firstOrder->linked_table_ids)) {
                $linkedTableIds = $firstOrder->linked_table_ids;
            }
        }

        // Check reservation (загружаем customer для отображения бонусов)
        $reservation = null;
        if ($reservationId) {
            $reservation = Reservation::forRestaurant($table->restaurant_id)
                ->with('customer')
                ->find($reservationId);
        }
        if (!$reservation) {
            $firstOrder = $orders->first();
            if ($firstOrder && $firstOrder->reservation_id) {
                $reservation = Reservation::forRestaurant($table->restaurant_id)
                    ->with('customer')
                    ->find($firstOrder->reservation_id);
            }
        }

        // Add prepayment to orders
        $restaurantId = $table->restaurant_id;
        $orders = $orders->map(function ($order) use ($restaurantId) {
            if ($order->reservation_id) {
                $res = Reservation::forRestaurant($restaurantId)->find($order->reservation_id);
                $order->prepayment = $res ? $res->deposit : 0;
            }
            return $order;
        });

        // Convert preorder to dine_in
        $orders->each(function ($order) {
            if ($order->type === 'preorder') {
                $order->update(['type' => 'dine_in']);
                $order->type = 'dine_in';
                $order->items()->where('status', 'saved')->update(['status' => 'pending']);
            }
        });

        return [
            'table' => $table,
            'orders' => $orders,
            'categories' => $categories,
            'initialGuests' => $initialGuests,
            'linkedTableIds' => $linkedTableIds,
            'reservation' => $reservation,
        ];
    }

    /**
     * Get categories with products for menu
     */
    private function getCategoriesWithProducts(?int $priceListId = null)
    {
        $gradients = [
            'bg-gradient-to-br from-orange-400 to-red-500',
            'bg-gradient-to-br from-blue-400 to-indigo-500',
            'bg-gradient-to-br from-green-400 to-emerald-500',
            'bg-gradient-to-br from-purple-400 to-pink-500',
            'bg-gradient-to-br from-yellow-400 to-orange-500',
            'bg-gradient-to-br from-cyan-400 to-blue-500',
        ];

        // Load price overrides if price list specified
        $priceOverrides = [];
        if ($priceListId) {
            $priceOverrides = PriceListItem::where('price_list_id', $priceListId)
                ->pluck('price', 'dish_id')
                ->toArray();
        }

        return Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['dishes' => function ($query) {
                $query->where('is_available', true)
                      ->whereIn('product_type', ['simple', 'parent']) // Only top-level products
                      ->orderBy('sort_order')
                      ->with(['stopListEntry', 'category', 'modifiers.options', 'variants']);
            }])
            ->get()
            ->filter(fn($cat) => $cat->dishes->count() > 0)
            ->map(function ($category) use ($gradients, $priceOverrides) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon ?? '📦',
                    'products' => $category->dishes->map(function ($dish) use ($gradients, $priceOverrides) {
                        $inStopList = $dish->stopListEntry !== null;
                        $isAvailable = $dish->is_available && !$inStopList;

                        $basePrice = (float) $dish->price;
                        $effectivePrice = isset($priceOverrides[$dish->id])
                            ? (float) $priceOverrides[$dish->id]
                            : $basePrice;

                        $product = [
                            'id' => $dish->id,
                            'name' => $dish->name,
                            'price' => $effectivePrice,
                            'base_price' => $basePrice,
                            'icon' => $this->getDishIcon($dish),
                            'image' => $dish->image,
                            'description' => $dish->description,
                            'weight' => $dish->weight,
                            'cooking_time' => $dish->cooking_time,
                            'is_available' => $isAvailable,
                            'is_popular' => $dish->is_popular,
                            'is_new' => $dish->is_new,
                            'is_spicy' => $dish->is_spicy,
                            'gradient' => $gradients[$dish->id % count($gradients)],
                            'product_type' => $dish->product_type ?? 'simple',
                            'category_id' => $dish->category_id,
                        ];

                        // Add variants for parent products
                        if ($dish->product_type === 'parent' && $dish->variants->count() > 0) {
                            $product['variants'] = $dish->variants->map(function ($variant) use ($priceOverrides) {
                                $variantBase = (float) $variant->price;
                                $variantPrice = isset($priceOverrides[$variant->id])
                                    ? (float) $priceOverrides[$variant->id]
                                    : $variantBase;

                                return [
                                    'id' => $variant->id,
                                    'variant_name' => $variant->variant_name,
                                    'price' => $variantPrice,
                                    'base_price' => $variantBase,
                                    'is_available' => $variant->is_available && !$variant->stopListEntry,
                                ];
                            })->values()->toArray();
                        }

                        // Add modifiers
                        if ($dish->modifiers->count() > 0) {
                            $product['modifiers'] = $dish->modifiers->map(function ($modifier) {
                                return [
                                    'id' => $modifier->id,
                                    'name' => $modifier->name,
                                    'type' => $modifier->type ?? 'single',
                                    'is_required' => (bool) $modifier->is_required,
                                    'max_selections' => $modifier->max_selections,
                                    'options' => $modifier->options->map(function ($option) {
                                        return [
                                            'id' => $option->id,
                                            'name' => $option->name,
                                            'price' => (float) ($option->price ?? 0),
                                            'is_default' => (bool) $option->is_default,
                                        ];
                                    })->values()->toArray(),
                                ];
                            })->values()->toArray();
                        }

                        return $product;
                    }),
                ];
            })->values();
    }

    /**
     * Создать новый заказ для стола
     */
    public function store(Request $request, Table $table)
    {
        $today = Carbon::today();
        $orderCount = Order::whereDate('created_at', $today)->count() + 1;
        $orderNumber = $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

        // Получаем связанные столы из запроса
        $linkedTableIds = $request->input('linked_table_ids');
        if ($linkedTableIds && !is_array($linkedTableIds)) {
            $linkedTableIds = array_map('intval', explode(',', $linkedTableIds));
        }

        $priceListId = $request->input('price_list_id') ? (int) $request->input('price_list_id') : null;

        $order = Order::create([
            'restaurant_id' => $table->restaurant_id,
            'order_number' => $orderNumber,
            'daily_number' => '#' . $orderNumber,
            'type' => 'dine_in',
            'table_id' => $table->id,
            'linked_table_ids' => $linkedTableIds,
            'price_list_id' => $priceListId,
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
        ]);

        return response()->json([
            'success' => true,
            'order' => $order->load('items'),
        ]);
    }

    /**
     * Обновить прайс-лист заказа
     */
    public function updateOrderPriceList(Request $request, Table $table, Order $order)
    {
        $order->update([
            'price_list_id' => $request->input('price_list_id'),
        ]);

        return response()->json([
            'success' => true,
            'order' => $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']),
        ]);
    }

    /**
     * Закрыть/удалить пустой заказ
     */
    public function closeEmptyOrder(Table $table, Order $order)
    {
        // Проверяем что заказ принадлежит этому столу
        if ($order->table_id !== $table->id) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ не принадлежит этому столу',
            ], 400);
        }

        // Проверяем что заказ пустой
        if ($order->items()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя закрыть заказ с позициями',
            ], 400);
        }

        // Заказ от брони нельзя удалить - только завершить через оплату
        if ($order->reservation_id) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ от брони нельзя удалить. Завершите через оплату.',
            ], 400);
        }

        // Удаляем пустой заказ
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Пустой заказ закрыт',
        ]);
    }

    /**
     * Очистить пустые заказы при закрытии страницы (для sendBeacon)
     */
    public function cleanupEmptyOrders(Request $request, Table $table)
    {
        // Находим все пустые заказы на этом столе (total = 0, нет позиций)
        $emptyOrders = Order::where('table_id', $table->id)
            ->whereIn('status', ['new', 'confirmed'])
            ->where('payment_status', 'pending')
            ->where('total', 0)
            ->whereNull('reservation_id')
            ->whereDoesntHave('items')
            ->get();

        $deletedCount = 0;
        foreach ($emptyOrders as $order) {
            $order->delete();
            $deletedCount++;
        }

        // Проверяем нужно ли освободить стол
        $hasActiveOrders = Order::where('table_id', $table->id)
            ->whereIn('status', ['new', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->exists();

        if (!$hasActiveOrders && $table->status === 'occupied') {
            $table->update(['status' => 'free']);
            $this->broadcastTableStatusChanged($table->id, 'free', $table->restaurant_id);
        }

        return response()->json([
            'success' => true,
            'deleted' => $deletedCount,
        ]);
    }


    /**
     * Добавить позицию в заказ
     * Note: We avoid route model binding here because of TenantManager/middleware ordering issues
     */
    public function addItem(Request $request, $table, $order)
    {
        // Load models manually after middleware has set up TenantManager
        if (!$table instanceof Table) {
            $table = Table::findOrFail($table);
        }
        if (!$order instanceof Order) {
            $order = Order::findOrFail($order);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:dishes,id',
            'guest_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1',
            'modifiers' => 'nullable|array',
            'comment' => 'nullable|string|max:255',
        ]);

        $dish = Dish::with(['stopListEntry', 'parent'])->findOrFail($validated['product_id']);

        // Проверка доступности
        if (!$dish->is_available || $dish->stopListEntry !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо недоступно или в стоп-листе',
            ], 422);
        }

        $quantity = $validated['quantity'] ?? 1;
        $modifiers = $validated['modifiers'] ?? [];

        // Calculate modifier price
        $modifiersPrice = 0;
        if (!empty($modifiers)) {
            foreach ($modifiers as $mod) {
                $modifiersPrice += (float) ($mod['price'] ?? 0);
            }
        }

        $itemPrice = $dish->price + $modifiersPrice;
        $itemTotal = $itemPrice * $quantity;

        $item = OrderItem::create([
            'restaurant_id' => $order->restaurant_id, // Явно передаём restaurant_id
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->getFullName(),
            'price' => $itemPrice,
            'quantity' => $quantity,
            'total' => $itemTotal,
            'guest_number' => $validated['guest_id'] ?? 1,
            'modifiers' => !empty($modifiers) ? $modifiers : null,
            'modifiers_price' => $modifiersPrice,
            'comment' => $validated['comment'] ?? null,
            'status' => 'pending',
        ]);

        // Пересчитываем сумму заказа
        $order->recalculateTotal();

        // Занимаем стол при добавлении первого блюда
        // НО не для предзаказов (когда бронь ещё не посажена)
        $order->refresh(); // Перезагружаем заказ из БД
        $table->refresh(); // Перезагружаем стол из БД

        $isPreorder = false;
        if ($order->reservation_id) {
            $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
            // Предзаказ = бронь существует и НЕ посажена (pending/confirmed)
            if ($reservation && in_array($reservation->status, ['pending', 'confirmed'])) {
                $isPreorder = true;
            }
        }

        if ($table->status === 'free' && !$isPreorder) {
            $table->update(['status' => 'occupied']);
            $this->broadcastTableStatusChanged($table->id, 'occupied', $table->restaurant_id);
        }

        // Возвращаем обновлённый заказ с позициями
        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
            'item' => $item->load('dish'),
        ]);
    }

    /**
     * Обновить количество позиции
     */
    public function updateItem(Request $request, Table $table, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ], 400);
        }


        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:99',
            'comment' => 'sometimes|nullable|string|max:255',
            'guest_number' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:served',
            'modifiers' => 'sometimes|nullable|array',
        ]);

        $updateData = [];

        // Обработка статуса "подано"
        if (isset($validated['status']) && $validated['status'] === 'served') {
            if ($item->status === 'ready') {
                $updateData['status'] = 'served';
                $updateData['served_at'] = now();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Можно подать только готовые позиции',
                ], 400);
            }
        }

        // Количество и комментарий можно менять только для pending
        if (isset($validated['quantity']) || array_key_exists('comment', $validated)) {
            if ($item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя изменить - позиция уже на кухне',
                ], 400);
            }

            if (isset($validated['quantity'])) {
                $updateData['quantity'] = $validated['quantity'];
                $updateData['total'] = $item->price * $validated['quantity'];
            }

            if (array_key_exists('comment', $validated)) {
                $updateData['comment'] = $validated['comment'];
            }
        }

        // Гостя можно менять для любого статуса
        if (isset($validated['guest_number'])) {
            $updateData['guest_number'] = $validated['guest_number'];
        }

        // Модификаторы можно менять только для pending
        if (array_key_exists('modifiers', $validated)) {
            if ($item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя изменить модификаторы - позиция уже на кухне',
                ], 400);
            }

            $modifiers = $validated['modifiers'] ?? [];

            // Calculate new price with modifiers
            $dish = $item->dish;
            $basePrice = $dish ? $dish->price : $item->price;

            $modifiersPrice = 0;
            if (!empty($modifiers)) {
                foreach ($modifiers as $mod) {
                    $modifiersPrice += (float) ($mod['price'] ?? 0);
                }
            }

            $newPrice = $basePrice + $modifiersPrice;
            $updateData['modifiers'] = !empty($modifiers) ? $modifiers : null;
            $updateData['modifiers_price'] = $modifiersPrice;
            $updateData['price'] = $newPrice;
            $updateData['total'] = $newPrice * $item->quantity;
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        $order->recalculateTotal();

        // Возвращаем обновлённый заказ с позициями
        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
            'item' => $item->fresh(),
        ]);
    }

    /**
     * Удалить позицию из заказа
     */
    public function removeItem(Table $table, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ], 400);
        }

        $item->delete();
        $order->recalculateTotal();

        // Проверяем остались ли товары в заказе
        $order->refresh();
        $orderDeleted = false;

        if ($order->items()->count() === 0) {
            // Получаем связанные столы до удаления заказа
            $linkedTableIds = $order->linked_table_ids ?? [];

            // Если заказ был от брони - сбрасываем бронь обратно в pending
            if ($order->reservation_id) {
                $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
                if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                    $reservation->update(['status' => 'pending']);
                }
            }

            // Удаляем пустой заказ
            $order->delete();
            $orderDeleted = true;

            // Освобождаем основной стол
            $table->update(['status' => 'free']);
            $this->broadcastTableStatusChanged($table->id, 'free', $table->restaurant_id);

            // Освобождаем все связанные столы (только того же ресторана)
            if (!empty($linkedTableIds)) {
                foreach ($linkedTableIds as $linkedTableId) {
                    if ($linkedTableId != $table->id) {
                        $linkedTable = Table::forRestaurant($table->restaurant_id)->find($linkedTableId);
                        if ($linkedTable && $linkedTable->status === 'occupied') {
                            $linkedTable->update(['status' => 'free']);
                            $this->broadcastTableStatusChanged($linkedTableId, 'free', $linkedTable->restaurant_id);
                        }
                    }
                }
            }
        }

        // Возвращаем обновлённый заказ (или null если удалён)
        if (!$orderDeleted) {
            $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);
        }

        return response()->json([
            'success' => true,
            'order' => $orderDeleted ? null : $order,
            'order_deleted' => $orderDeleted,
        ]);
    }

    /**
     * Отправить на кухню
     */
    public function sendToKitchen(Request $request, Table $table, Order $order)
    {
        // Получаем item_ids из JSON или form data
        $itemIds = $request->input('item_ids') ?? $request->json('item_ids') ?? [];

        // Убеждаемся что это массив
        if (!is_array($itemIds)) {
            $itemIds = [$itemIds];
        }

        $query = $order->items()->where('status', 'pending');

        // Если переданы конкретные ID - обновляем только их
        if (!empty($itemIds) && count($itemIds) > 0) {
            $query->whereIn('id', $itemIds);
        }

        $updatedCount = $query->update([
            'status' => 'cooking',
        ]);

        \Log::info('sendToKitchen', [
            'item_ids_received' => $itemIds,
            'updated_count' => $updatedCount,
            'order_id' => $order->id,
            'order_status_before' => $order->status
        ]);

        // Обновляем статус заказа - отправляем на кухню (confirmed = новый для повара)
        if ($order->status === 'new') {
            $order->update(['status' => 'confirmed']);
            \Log::info('sendToKitchen: order status changed to confirmed', ['order_id' => $order->id]);
        } else {
            \Log::info('sendToKitchen: order status NOT changed', ['order_id' => $order->id, 'current_status' => $order->status]);
        }

        // Broadcast через Reverb - уведомляем кухню о новом заказе
        $freshOrder = $order->fresh();
        $freshOrder->load('table');
        $this->broadcastOrderStatusChanged($freshOrder, 'new', 'confirmed');

        // Возвращаем обновлённый заказ
        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }


    /**
     * Сохранить предзаказ (без отправки на кухню)
     */
    public function savePreorder(Request $request, Table $table, Order $order)
    {
        // Помечаем все pending позиции как saved
        $updatedCount = $order->items()
            ->where('status', 'pending')
            ->update(['status' => 'saved']);

        \Log::info('savePreorder', [
            'updated_count' => $updatedCount,
            'order_id' => $order->id
        ]);

        return response()->json([
            'success' => true,
            'message' => "Предзаказ сохранён ({$updatedCount} поз.)"
        ]);
    }

    /**
     * Оплата заказа
     */
    public function payment(Request $request, Table $table, Order $order)
    {
        try {
            // Проверяем открытую смену (используем restaurant_id стола, он всегда корректен)
            $restaurantId = $table->restaurant_id ?? $order->restaurant_id;

            \Log::info('[Payment] Checking shift', [
                'table_id' => $table->id,
                'table_restaurant_id' => $table->restaurant_id,
                'order_id' => $order->id,
                'order_restaurant_id' => $order->restaurant_id,
                'resolved_restaurant_id' => $restaurantId,
            ]);

            $shift = CashShift::getCurrentShift($restaurantId);
            if (!$shift) {
                // Логируем для отладки (только смены текущего ресторана)
                $openShifts = CashShift::forRestaurant($restaurantId)
                    ->where('status', 'open')
                    ->get(['id', 'restaurant_id', 'opened_at']);
                \Log::warning('[Payment] No shift found', [
                    'restaurant_id' => $restaurantId,
                    'open_shifts' => $openShifts->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Касса закрыта! Откройте смену для приёма оплаты.',
                    'error_code' => 'SHIFT_CLOSED',
                    'debug' => [
                        'restaurant_id' => $restaurantId,
                        'table_restaurant_id' => $table->restaurant_id,
                        'order_restaurant_id' => $order->restaurant_id,
                    ]
                ], 400);
            }

            $validator = \Validator::make($request->all(), [
                'payment_method' => 'required|in:cash,card,split,deposit,mixed',
                'guest_ids' => 'nullable|array',
                'tips_percent' => 'nullable|integer|min:0|max:100',
                'amount' => 'nullable|numeric|min:0',
                'refund_amount' => 'nullable|numeric|min:0',
                'fully_paid_by_deposit' => 'nullable|boolean',
                'deposit_used' => 'nullable|numeric|min:0',
                'bonus_used' => 'nullable|numeric|min:0',
                'reservation_id' => 'nullable|integer',
                'cash_amount' => 'nullable|numeric|min:0',
                'card_amount' => 'nullable|numeric|min:0',
                'split_by_guests' => 'nullable|boolean',
                'guest_numbers' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации: ' . $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

        // Если раздельная оплата - оплачиваем только выбранных гостей
        if ($validated['payment_method'] === 'split' && !empty($validated['guest_ids'])) {
            $guestIds = $validated['guest_ids'];
            $paidItems = $order->items()->whereIn('guest_number', $guestIds)->get();
            $paidAmount = $paidItems->sum(fn($i) => $i->price * $i->quantity);

            // Помечаем позиции как оплаченные
            $order->items()->whereIn('guest_number', $guestIds)->update(['is_paid' => true]);

            // Проверяем, все ли позиции оплачены
            $unpaidCount = $order->items()->where('is_paid', false)->count();

            if ($unpaidCount === 0) {
                // Все оплачено - закрываем заказ ('mixed' т.к. enum не поддерживает 'split')
                $this->completeOrder($order, 'mixed');
            }

            // Возвращаем обновлённый заказ
            $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

            return response()->json([
                'success' => true,
                'order' => $order,
                'paid_amount' => $paidAmount,
                'remaining' => $unpaidCount > 0,
            ]);
        }

        // Оплата по гостям (из модалки оплаты)
        $splitByGuests = filter_var($validated['split_by_guests'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $guestNumbers = $validated['guest_numbers'] ?? [];

        if ($splitByGuests === true && is_array($guestNumbers) && count($guestNumbers) > 0) {
            $paymentMethod = $validated['payment_method'];
            $cashAmount = $validated['cash_amount'] ?? 0;
            $cardAmount = $validated['card_amount'] ?? 0;
            $depositUsed = $validated['deposit_used'] ?? 0;
            $refundAmount = $validated['refund_amount'] ?? 0;
            $bonusUsed = $validated['bonus_used'] ?? 0;
            // Сумма к оплате из фронтенда (уже с учётом скидки, но без вычета депозита)
            // amount = effectiveTotal = selectedGuestsTotal - depositToApply
            // Чтобы получить сумму со скидкой: amount + depositUsed
            $amountFromFrontend = $validated['amount'] ?? 0;

            // Получаем позиции выбранных гостей (только неоплаченные)
            $paidItems = $order->items()->whereIn('guest_number', $guestNumbers)->where('is_paid', false)->with('dish')->get();
            // Сумма товаров без скидки
            $itemsSubtotal = $paidItems->sum(fn($i) => $i->price * $i->quantity);

            // Сумма гостей со скидкой = то что прислал фронтенд + депозит (если был)
            // Если фронтенд не прислал сумму - используем сумму товаров
            $paidAmount = ($amountFromFrontend > 0 || $depositUsed > 0)
                ? ($amountFromFrontend + $depositUsed)
                : $itemsSubtotal;

            // Если нет неоплаченных позиций у этих гостей - возвращаем успех
            if ($paidItems->isEmpty()) {
                // Возвращаем обновлённый заказ
                $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

                return response()->json([
                    'success' => true,
                    'order' => $order,
                    'paid_amount' => 0,
                    'paid_guests' => $guestNumbers,
                    'remaining' => $order->items()->where('is_paid', false)->count() > 0,
                    'message' => 'Эти гости уже оплачены'
                ]);
            }

            // Подготавливаем данные о товарах для записи в кассу
            $itemsForNotes = $paidItems->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->dish?->name ?? $item->name ?? 'Позиция',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'guest_number' => $item->guest_number,
            ])->toArray();

            // Помечаем позиции как оплаченные
            $order->items()->whereIn('guest_number', $guestNumbers)->update(['is_paid' => true]);

            // Если использован депозит - переводим его из брони
            if ($depositUsed > 0 && $order->reservation_id) {
                $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
                if ($reservation && $reservation->deposit_status === 'paid') {
                    $reservation->transferDeposit();
                    $order->update(['deposit_used' => $depositUsed]);
                }
            }

            // Если есть возврат депозита (депозит > суммы заказа) - записываем возврат
            if ($refundAmount > 0) {
                try {
                    CashOperation::recordRefund(
                        $order,
                        $refundAmount,
                        $paymentMethod === 'mixed' ? 'cash' : $paymentMethod
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Deposit refund recording failed: ' . $e->getMessage());
                }
            }

            // Сумма для записи в кассу (за вычетом депозита и бонусов)
            $actualPayment = $paidAmount - $depositUsed - $bonusUsed;

            // Записываем оплату в кассу
            if ($paymentMethod === 'mixed' && ($cashAmount > 0 || $cardAmount > 0)) {
                try {
                    if ($cashAmount > 0) {
                        CashOperation::recordOrderPayment($order, 'cash', null, null, $cashAmount, $itemsForNotes, $guestNumbers);
                    }
                    if ($cardAmount > 0) {
                        CashOperation::recordOrderPayment($order, 'card', null, null, $cardAmount, $itemsForNotes, $guestNumbers);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Split by guests mixed payment failed: ' . $e->getMessage());
                }
            } else if ($actualPayment > 0) {
                try {
                    CashOperation::recordOrderPayment($order, $paymentMethod, null, null, $actualPayment, $itemsForNotes, $guestNumbers);
                } catch (\Throwable $e) {
                    \Log::warning('Split by guests payment failed: ' . $e->getMessage());
                }
            }

            // Списываем бонусы клиента через BonusService
            if ($bonusUsed > 0 && $order->customer_id) {
                $order->load('customer');
                if ($order->customer) {
                    $bonusService = new BonusService($order->restaurant_id);
                    $result = $bonusService->spendForOrder($order, (int) $bonusUsed);
                    if (!$result['success']) {
                        \Log::warning('Split payment bonus spend failed: ' . ($result['error'] ?? 'Unknown error'));
                    }
                }
            }

            // Проверяем, все ли позиции оплачены
            $unpaidCount = $order->items()->where('is_paid', false)->count();

            if ($unpaidCount === 0) {
                // Все оплачено - закрываем заказ
                // Используем 'mixed' для раздельной оплаты по гостям (enum не поддерживает 'split')
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'payment_method' => 'mixed',
                    'paid_at' => now(),
                    'completed_at' => now(),
                ]);

                // Отправляем событие через WebSocket
                $this->broadcastOrderPaid($order, 'mixed');

                // Завершаем связанную бронь
                if ($order->reservation_id) {
                    $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
                    if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                        $reservation->update(['status' => 'completed']);
                    }
                }

                // Освобождаем столы
                $allTableIds = [$order->table_id];
                if (!empty($order->linked_table_ids)) {
                    $allTableIds = array_merge($allTableIds, $order->linked_table_ids);
                    $allTableIds = array_unique($allTableIds);
                }

                $activeOrders = Order::where(function($q) use ($allTableIds) {
                        $q->whereIn('table_id', $allTableIds);
                    })
                    ->where('id', '!=', $order->id)
                    ->whereIn('status', ['new', 'cooking', 'ready', 'served'])
                    ->where('payment_status', 'pending')
                    ->where('total', '>', 0)
                    ->count();

                if ($activeOrders === 0) {
                    foreach ($allTableIds as $tableId) {
                        Table::where('id', $tableId)->update(['status' => 'free']);
                        $this->broadcastTableStatusChanged($tableId, 'free', $order->restaurant_id);
                    }
                }

                // Начисляем бонусы клиенту при полной оплате заказа через BonusService
                if ($order->customer_id) {
                    try {
                        $bonusService = new BonusService($order->restaurant_id);
                        if ($bonusService->isEnabled()) {
                            $bonusService->earnForOrder($order);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Split payment bonus accrual failed: ' . $e->getMessage());
                    }
                }

                // Записываем использование промокода
                if ($order->promo_code) {
                    try {
                        $promotion = Promotion::findByCode($order->promo_code, $order->restaurant_id);
                        if ($promotion) {
                            $promotion->apply(
                                $order->customer_id,
                                $order->id,
                                $order->discount_amount ?? 0
                            );
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Split payment promo code usage recording failed: ' . $e->getMessage());
                    }
                }

                $this->broadcastOrderPaid($order, 'mixed');
            }

            // Возвращаем обновлённый заказ
            $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

            return response()->json([
                'success' => true,
                'order' => $order,
                'paid_amount' => $paidAmount,
                'paid_guests' => $guestNumbers,
                'remaining' => $unpaidCount > 0,
            ]);
        }

        // Обработка депозита из брони
        $depositUsed = $validated['deposit_used'] ?? 0;
        $refundAmount = $validated['refund_amount'] ?? 0;
        $fullyPaidByDeposit = $validated['fully_paid_by_deposit'] ?? false;
        $reservationId = $validated['reservation_id'] ?? $order->reservation_id;

        if ($reservationId && $depositUsed > 0) {
            $reservation = Reservation::forRestaurant($order->restaurant_id)->find($reservationId);
            if ($reservation && $reservation->deposit_status === 'paid') {
                // Переводим депозит в заказ
                $reservation->transferDeposit();

                // Если нужен возврат - записываем операцию расхода
                if ($refundAmount > 0) {
                    try {
                        CashOperation::recordDepositRefund(
                            $order->restaurant_id,
                            $reservation->id,
                            $refundAmount,
                            'cash',
                            null,
                            $reservation->guest_name,
                            "Возврат разницы депозита (заказ #{$order->order_number})"
                        );
                    } catch (\Throwable $e) {
                        \Log::warning('Deposit refund cash operation failed: ' . $e->getMessage());
                    }
                }
            }
        }

        // Полная оплата
        $cashAmount = $validated['cash_amount'] ?? 0;
        $cardAmount = $validated['card_amount'] ?? 0;
        $bonusUsed = $validated['bonus_used'] ?? 0;
        $this->completeOrder($order, $validated['payment_method'], $depositUsed, $fullyPaidByDeposit, $cashAmount, $cardAmount, $bonusUsed);

        // Возвращаем обновлённый заказ
        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
            'deposit_used' => $depositUsed,
            'refund_amount' => $refundAmount,
        ]);

        } catch (\Throwable $e) {
            \Log::error('Payment error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'table' => $table->id,
                'order' => $order->id,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка оплаты: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Завершить заказ
     */
    private function completeOrder(Order $order, string $paymentMethod, float $depositUsed = 0, bool $fullyPaidByDeposit = false, float $cashAmount = 0, float $cardAmount = 0, float $bonusUsed = 0)
    {
        // Пересчитываем сумму заказа перед оплатой
        $order->recalculateTotal();
        $order->refresh();

        // Создаём клиента из брони, если его ещё нет
        if (!$order->customer_id && $order->reservation_id) {
            $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
            if ($reservation && $reservation->guest_phone) {
                $normalizedPhone = preg_replace('/[^0-9]/', '', $reservation->guest_phone);
                $customer = Customer::where('restaurant_id', $order->restaurant_id)
                    ->byPhone($normalizedPhone)
                    ->first();

                if (!$customer) {
                    $customer = Customer::create([
                        'restaurant_id' => $order->restaurant_id,
                        'phone' => $reservation->guest_phone,
                        'name' => Customer::formatName($reservation->guest_name) ?? 'Гость',
                        'email' => $reservation->guest_email,
                        'source' => 'reservation',
                    ]);
                }

                $order->update(['customer_id' => $customer->id]);
                $reservation->update(['customer_id' => $customer->id]);
            }
        }

        // Списываем бонусы клиента через BonusService
        if ($bonusUsed > 0 && $order->customer_id) {
            $order->load('customer');
            if ($order->customer) {
                $bonusService = new BonusService($order->restaurant_id);
                $result = $bonusService->spendForOrder($order, (int) $bonusUsed);
                if (!$result['success']) {
                    \Log::warning('Order bonus spend failed: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
        }

        // Определяем метод оплаты для записи
        $effectivePaymentMethod = $paymentMethod;
        if ($fullyPaidByDeposit) {
            $effectivePaymentMethod = 'bonus'; // Депозит - используем 'bonus' т.к. enum не поддерживает 'deposit'
        } elseif ($depositUsed > 0 && $paymentMethod !== 'mixed') {
            $effectivePaymentMethod = 'mixed'; // Депозит + доплата
        }

        $order->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => $effectivePaymentMethod,
            'paid_at' => now(),
            'completed_at' => now(),
            'deposit_used' => $depositUsed,
            'bonus_used' => $bonusUsed,
        ]);

        // Отправляем событие через WebSocket
        $this->broadcastOrderPaid($order, $effectivePaymentMethod);

        // Получаем неоплаченные позиции с данными блюд
        $unpaidItems = $order->items()
            ->where('is_paid', false)
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->with('dish')
            ->get();

        $unpaidAmount = $unpaidItems->sum(fn($item) => $item->price * $item->quantity);

        // Подготавливаем данные о товарах для записи в кассу
        $itemsForNotes = $unpaidItems->map(fn($item) => [
            'id' => $item->id,
            'name' => $item->dish?->name ?? $item->name ?? 'Позиция',
            'quantity' => $item->quantity,
            'price' => $item->price,
            'guest_number' => $item->guest_number,
        ])->toArray();

        // Помечаем все оставшиеся позиции как оплаченные
        $order->items()->where('is_paid', false)->update(['is_paid' => true]);

        // Записываем в кассу
        if ($paymentMethod === 'mixed' && ($cashAmount > 0 || $cardAmount > 0)) {
            // Смешанная оплата: отдельно записываем нал и безнал
            try {
                if ($cashAmount > 0) {
                    CashOperation::recordOrderPayment($order, 'cash', null, null, $cashAmount, $itemsForNotes, null);
                }
                if ($cardAmount > 0) {
                    CashOperation::recordOrderPayment($order, 'card', null, null, $cardAmount, $itemsForNotes, null);
                }
            } catch (\Throwable $e) {
                \Log::warning('TableOrder mixed payment cash operation failed: ' . $e->getMessage());
            }
        } else {
            // Обычная оплата: записываем сумму заказа (со скидками) минус депозит и бонусы
            $actualPayment = $order->total - $depositUsed - $bonusUsed;
            if ($actualPayment > 0) {
                try {
                    CashOperation::recordOrderPayment($order, $paymentMethod, null, null, $actualPayment, $itemsForNotes, null);
                } catch (\Throwable $e) {
                    \Log::warning('TableOrder payment cash operation failed: ' . $e->getMessage());
                }
            }
        }

        // Завершаем связанную бронь если есть
        if ($order->reservation_id) {
            $reservation = Reservation::forRestaurant($order->restaurant_id)->find($order->reservation_id);
            if ($reservation && !in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
                $reservation->update(['status' => 'completed']);
            }
        }

        // Получаем все связанные столы
        $allTableIds = [$order->table_id];
        if (!empty($order->linked_table_ids)) {
            $allTableIds = array_merge($allTableIds, $order->linked_table_ids);
            $allTableIds = array_unique($allTableIds);
        }

        // Освобождаем столы если нет других активных заказов
        $activeOrders = Order::where(function($q) use ($allTableIds) {
                $q->whereIn('table_id', $allTableIds);
            })
            ->where('id', '!=', $order->id)
            ->whereIn('status', ['new', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->where('total', '>', 0)
            ->count();

        if ($activeOrders === 0) {
            foreach ($allTableIds as $tableId) {
                Table::where('id', $tableId)->update(['status' => 'free']);
                $this->broadcastTableStatusChanged($tableId, 'free', $order->restaurant_id);
            }
        }

        // Начисляем бонусы клиенту через BonusService
        if ($order->customer_id) {
            try {
                $bonusService = new BonusService($order->restaurant_id);
                if ($bonusService->isEnabled()) {
                    $order->load('customer');
                    if ($order->customer) {
                        // Проверяем промокод на бонусы
                        $bonusMultiplier = 1.0;
                        $promoBonusAdd = 0;
                        if ($order->promo_code) {
                            $promotion = Promotion::findByCode($order->promo_code, $order->restaurant_id);
                            if ($promotion && $promotion->discount_value > 0) {
                                // Проверяем тип акции для бонусов
                                if ($promotion->type === 'bonus_multiplier') {
                                    $bonusMultiplier = (float) $promotion->discount_value;
                                } elseif ($promotion->type === 'bonus') {
                                    $promoBonusAdd = (int) $promotion->discount_value;
                                }
                            }
                        }

                        // Начисляем бонусы за заказ (с учётом множителя)
                        $bonusService->earnForOrder($order, $bonusMultiplier);

                        // Начисляем дополнительные бонусы по промокоду
                        if ($promoBonusAdd > 0) {
                            $bonusService->earn(
                                $order->customer,
                                $promoBonusAdd,
                                BonusTransaction::TYPE_PROMO,
                                $order->id,
                                "Бонусы по промокоду {$order->promo_code}"
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Bonus accrual failed: ' . $e->getMessage());
            }
        }

        // Записываем использование промокода (для работы лимитов usage_limit и usage_per_customer)
        if ($order->promo_code) {
            try {
                $promotion = Promotion::findByCode($order->promo_code, $order->restaurant_id);
                if ($promotion) {
                    $promotion->apply(
                        $order->customer_id,
                        $order->id,
                        $order->discount_amount ?? 0
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('Promo code usage recording failed: ' . $e->getMessage());
            }
        }

        $this->broadcastOrderPaid($order, $paymentMethod);
    }

    /**
     * Применить скидку к заказу
     */
    public function applyDiscount(Request $request, Table $table, Order $order)
    {
        $validated = $request->validate([
            'discount_amount' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0', // Убрали max:100 - может быть сумма нескольких скидок
            'discount_max_amount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:500', // Увеличили для нескольких скидок
            'promo_code' => 'nullable|string|max:50',
            'applied_discounts' => 'nullable|array', // Детальная информация о скидках
            'bonus_to_spend' => 'nullable|integer|min:0', // Enterprise: бонусы для списания
            'gift_item' => 'nullable|array',
            'gift_item.id' => 'nullable|integer|exists:dishes,id',
            'gift_item.name' => 'nullable|string',
            'gift_item.price' => 'nullable|numeric',
            'gift_item.category' => 'nullable|string',
        ]);

        $discountAmount = floatval($validated['discount_amount']);
        $discountPercent = floatval($validated['discount_percent'] ?? 0);
        $discountMaxAmount = isset($validated['discount_max_amount']) ? floatval($validated['discount_max_amount']) : null;
        $discountReason = $validated['discount_reason'] ?? '';
        $promoCode = $validated['promo_code'] ?? '';
        $giftItem = $validated['gift_item'] ?? null;

        // Формируем причину скидки
        $reasonParts = [];
        if ($promoCode) {
            $reasonParts[] = "Промокод: {$promoCode}";
        } elseif ($discountPercent > 0 || $discountAmount > 0) {
            // Ручная скидка (если не промокод)
            $manualParts = ['Ручная'];
            if ($discountPercent > 0) {
                $manualParts[] = "{$discountPercent}%";
            }
            if ($discountReason) {
                $manualParts[] = "({$discountReason})";
            }
            $reasonParts[] = implode(' ', $manualParts);
        }

        // Добавляем информацию о подарке в причину
        if ($giftItem && !empty($giftItem['name'])) {
            $reasonParts[] = "Подарок: {$giftItem['name']}";
        }

        $fullReason = implode(' | ', $reasonParts);

        // Округляем сумму скидки до целого числа
        $discountAmount = round($discountAmount);

        // Подготавливаем applied_discounts для хранения
        $appliedDiscounts = $validated['applied_discounts'] ?? null;

        // Enterprise: получаем бонусы для списания (сохраняем на сервере до оплаты)
        $pendingBonusSpend = intval($validated['bonus_to_spend'] ?? 0);

        // Обновляем заказ (сохраняем и процент для автопересчёта при изменении товаров)
        $order->update([
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'discount_max_amount' => $discountMaxAmount,
            'discount_reason' => $fullReason,
            'promo_code' => $promoCode ?: null,
            'applied_discounts' => $appliedDiscounts,
            'pending_bonus_spend' => $pendingBonusSpend, // Enterprise: сервер = источник правды
        ]);

        // Добавляем подарочный товар в заказ (если есть, только из того же ресторана)
        if ($giftItem && !empty($giftItem['id'])) {
            $dish = \App\Models\Dish::forRestaurant($order->restaurant_id)->find($giftItem['id']);
            if ($dish) {
                // Проверяем, нет ли уже такого подарка в заказе
                $existingGift = $order->items()
                    ->where('dish_id', $dish->id)
                    ->where('is_gift', true)
                    ->first();

                if (!$existingGift) {
                    $order->items()->create([
                        'dish_id' => $dish->id,
                        'name' => $dish->name,
                        'quantity' => 1,
                        'price' => 0, // Подарок бесплатный
                        'original_price' => $dish->price,
                        'total' => 0, // Итого тоже 0
                        'is_gift' => true,
                        'comment' => 'Подарок по промокоду ' . $promoCode,
                        'guest_number' => 1,
                    ]);
                }
            }
        }

        // Пересчитываем итого (использует discount_percent для пересчёта)
        $order->recalculateTotal();

        // Загружаем свежий заказ с позициями, клиентом и уровнем лояльности
        $order = $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'discount_amount' => $order->discount_amount,
            'discount_percent' => $order->discount_percent,
            'discount_reason' => $fullReason,
            'new_total' => $order->total,
            'order' => $order,
        ]);
    }

    /**
     * Предварительный расчёт скидки (без сохранения)
     * Единый источник истины для расчёта скидок
     */
    public function calculateDiscountPreview(Request $request, Table $table, Order $order)
    {
        $validated = $request->validate([
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_max_amount' => 'nullable|numeric|min:0',
            'discount_fixed' => 'nullable|numeric|min:0',
        ]);

        $subtotal = $order->items()->sum('total');
        $discountPercent = floatval($validated['discount_percent'] ?? 0);
        $discountMaxAmount = isset($validated['discount_max_amount']) ? floatval($validated['discount_max_amount']) : null;
        $discountFixed = floatval($validated['discount_fixed'] ?? 0);

        $discountAmount = 0;

        if ($discountPercent > 0 && $subtotal > 0) {
            // Процентная скидка
            $discountAmount = $subtotal * $discountPercent / 100;
            // Применяем лимит если есть
            if ($discountMaxAmount > 0 && $discountAmount > $discountMaxAmount) {
                $discountAmount = $discountMaxAmount;
            }
        } elseif ($discountFixed > 0) {
            // Фиксированная скидка
            $discountAmount = min($discountFixed, $subtotal);
        }

        // Округляем до целого
        $discountAmount = round($discountAmount);

        // Скидка не может превышать subtotal
        $discountAmount = min($discountAmount, $subtotal);

        return response()->json([
            'success' => true,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'discount_max_amount' => $discountMaxAmount,
            'total' => max(0, $subtotal - $discountAmount),
        ]);
    }

    /**
     * Получить иконку для блюда на основе категории или названия
     */
    private function getDishIcon($dish): string
    {
        // Если у блюда есть своя иконка в описании или отдельном поле
        if (!empty($dish->icon)) {
            return $dish->icon;
        }

        // Определяем по названию категории или блюда
        $name = mb_strtolower($dish->name);
        $categoryName = mb_strtolower($dish->category->name ?? '');

        // Пицца
        if (str_contains($name, 'пицц') || str_contains($categoryName, 'пицц')) return '🍕';
        // Бургеры
        if (str_contains($name, 'бургер') || str_contains($categoryName, 'бургер')) return '🍔';
        // Салаты
        if (str_contains($name, 'салат') || str_contains($categoryName, 'салат')) return '🥗';
        // Супы
        if (str_contains($name, 'суп') || str_contains($categoryName, 'суп')) return '🍜';
        // Стейки, мясо
        if (str_contains($name, 'стейк') || str_contains($name, 'мясо') || str_contains($categoryName, 'мяс')) return '🥩';
        // Паста
        if (str_contains($name, 'паста') || str_contains($name, 'спагетти')) return '🍝';
        // Рыба, морепродукты
        if (str_contains($name, 'рыба') || str_contains($name, 'лосось') || str_contains($name, 'креветк') || str_contains($categoryName, 'морепродукт')) return '🦐';
        // Курица
        if (str_contains($name, 'куриц') || str_contains($name, 'курин')) return '🍗';
        // Десерты
        if (str_contains($categoryName, 'десерт') || str_contains($name, 'торт') || str_contains($name, 'пирог')) return '🍰';
        // Мороженое
        if (str_contains($name, 'морожен')) return '🍨';
        // Кофе
        if (str_contains($name, 'кофе') || str_contains($name, 'капучино') || str_contains($name, 'латте') || str_contains($name, 'эспрессо')) return '☕';
        // Чай
        if (str_contains($name, 'чай')) return '🍵';
        // Пиво
        if (str_contains($name, 'пиво') || str_contains($categoryName, 'пив')) return '🍺';
        // Вино
        if (str_contains($name, 'вино') || str_contains($categoryName, 'вин')) return '🍷';
        // Коктейли
        if (str_contains($name, 'коктейль') || str_contains($categoryName, 'коктейл')) return '🍹';
        // Сок, лимонад
        if (str_contains($name, 'сок') || str_contains($name, 'лимонад') || str_contains($name, 'морс')) return '🧃';
        // Хлеб
        if (str_contains($name, 'хлеб') || str_contains($name, 'булк')) return '🍞';
        // Суши, роллы
        if (str_contains($name, 'суши') || str_contains($name, 'ролл') || str_contains($categoryName, 'японск')) return '🍣';
        // Завтраки
        if (str_contains($categoryName, 'завтрак') || str_contains($name, 'яичниц') || str_contains($name, 'омлет')) return '🍳';
        // Картошка
        if (str_contains($name, 'картош') || str_contains($name, 'фри')) return '🍟';
        // Закуски
        if (str_contains($categoryName, 'закуск')) return '🧆';

        // По умолчанию
        return '🍽️';
    }

    /**
     * Привязать клиента к заказу
     */
    public function attachCustomer(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        // Находим клиента только в рамках ресторана заказа
        $customer = Customer::forRestaurant($order->restaurant_id)
            ->with('loyaltyLevel')
            ->find($validated['customer_id']);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Клиент не найден',
            ], 404);
        }

        // Enterprise: проверяем смену клиента - нужно сбросить ВСЕ скидки
        $previousCustomerId = $order->customer_id;
        $isCustomerChange = $previousCustomerId && $previousCustomerId != $validated['customer_id'];

        $updateData = [
            'customer_id' => $validated['customer_id'],
        ];

        // Enterprise: полный сброс скидок при смене клиента (безопасный подход)
        if ($isCustomerChange) {
            $updateData['pending_bonus_spend'] = 0;
            $updateData['discount_amount'] = 0;
            $updateData['discount_percent'] = 0;
            $updateData['discount_max_amount'] = null;
            $updateData['discount_reason'] = null;
            $updateData['promo_code'] = null;
            $updateData['applied_discounts'] = null;
            $updateData['free_delivery'] = false;

            // Удаляем подарочные позиции
            $order->items()->where('is_gift', true)->delete();

            \Log::info('Resetting all discounts on customer change', [
                'order_id' => $order->id,
                'previous_customer' => $previousCustomerId,
                'new_customer' => $validated['customer_id'],
            ]);
        }

        // Автоматически применяем скидку уровня лояльности
        $loyaltyDiscount = 0;
        $loyaltyLevelId = null;
        $loyaltyLevelName = null;

        // Проверяем включены ли уровни лояльности
        $levelsEnabled = LoyaltySetting::get('levels_enabled', '1', $order->restaurant_id) !== '0';

        \Log::info('attachCustomer debug', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'loyalty_level_id' => $customer->loyalty_level_id,
            'loyalty_level' => $customer->loyaltyLevel?->toArray(),
            'levels_enabled' => $levelsEnabled,
            'subtotal' => $order->subtotal,
        ]);

        if ($levelsEnabled && $customer->loyaltyLevel && $customer->loyaltyLevel->discount_percent > 0) {
            $subtotal = $order->subtotal ?? 0;
            $loyaltyDiscount = round($subtotal * $customer->loyaltyLevel->discount_percent / 100, 2);
            $loyaltyLevelId = $customer->loyaltyLevel->id;
            $loyaltyLevelName = $customer->loyaltyLevel->name;

            $updateData['loyalty_discount_amount'] = $loyaltyDiscount;
            $updateData['loyalty_level_id'] = $loyaltyLevelId;
        }

        $order->update($updateData);

        // Проверяем автоматические акции (включая день рождения)
        $appliedPromotions = [];
        $promotionDiscount = 0;

        $context = [
            'customer_id' => $customer->id,
            'customer_birthday' => $customer->birth_date,
            'customer_loyalty_level' => $customer->loyalty_level_id,
            'is_first_order' => $customer->orders_count == 0,
            'order_type' => $order->order_type ?? 'dine_in',
            'order_total' => $order->subtotal ?? 0,
            'zone_id' => $order->zone_id,
            'table_id' => $order->table_id,
            'source_channel' => 'pos',
            'items' => $order->items->map(fn($item) => [
                'dish_id' => $item->dish_id,
                'category_id' => $item->dish?->category_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->toArray(),
        ];

        $automaticPromotions = Promotion::where('restaurant_id', $order->restaurant_id)
            ->where('is_active', true)
            ->where('is_automatic', true)
            ->where('requires_promo_code', false)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('sort_order')
            ->get();

        $hasExclusivePromo = false;
        $remainingTotal = $order->subtotal ?? 0;
        $giftItems = [];
        $freeDelivery = false;
        $bonusMultiplier = 1;

        \Log::debug('Automatic promotions found', [
            'count' => $automaticPromotions->count(),
            'names' => $automaticPromotions->pluck('name')->toArray(),
            'context' => $context,
        ]);

        foreach ($automaticPromotions as $promo) {
            // Пропускаем если уже применена эксклюзивная акция
            if ($hasExclusivePromo) {
                continue;
            }

            // Проверяем применимость к заказу
            if (!$promo->isApplicableToOrder($context)) {
                \Log::debug("Promo {$promo->name} skipped: isApplicableToOrder returned false");
                continue;
            }

            \Log::debug("Promo {$promo->name} passed all checks, applying...");

            $promoDiscount = 0;
            $promoData = [
                'name' => $promo->name,
                'type' => $promo->is_birthday_only ? 'birthday' : $promo->type,
                'amount' => 0,
                'percent' => $promo->discount_value ?? 0,
                'auto' => true,
                'stackable' => $promo->stackable,
                'sourceType' => 'promotion',
                'sourceId' => $promo->id,
            ];

            switch ($promo->type) {
                case 'discount_percent':
                case 'happy_hour':
                    $promoDiscount = $promo->calculateDiscount($context['items'], $remainingTotal, $context);
                    $promoData['amount'] = $promoDiscount;
                    break;

                case 'discount_fixed':
                    $promoDiscount = min($promo->discount_value ?? 0, $remainingTotal);
                    $promoData['amount'] = $promoDiscount;
                    break;

                case 'progressive_discount':
                    $promoDiscount = $promo->calculateProgressiveDiscount($remainingTotal);
                    $promoData['amount'] = $promoDiscount;
                    $promoData['percent'] = $promo->getProgressiveDiscountPercent($remainingTotal);
                    $promoData['tiers'] = $promo->progressive_tiers;
                    break;

                case 'free_delivery':
                    $freeDelivery = true;
                    $promoData['free_delivery'] = true;
                    break;

                case 'gift':
                    if ($promo->gift_dish_id && $promo->giftDish) {
                        $giftItems[] = [
                            'dish_id' => $promo->giftDish->id,
                            'name' => $promo->giftDish->name,
                            'promotion_id' => $promo->id,
                        ];
                        $promoData['gift_dish'] = [
                            'id' => $promo->giftDish->id,
                            'name' => $promo->giftDish->name,
                        ];
                    }
                    break;

                case 'bonus':
                case 'bonus_multiplier':
                    $bonusMultiplier = max($bonusMultiplier, $promo->discount_value ?? 1);
                    $promoData['bonus_multiplier'] = $promo->discount_value;
                    break;
            }

            // Всегда добавляем автоматическую акцию если она прошла проверки
            // (даже если сумма скидки = 0 при пустом заказе - пересчитается при добавлении блюд)
            $appliedPromotions[] = $promoData;
            $promotionDiscount += $promoDiscount;
            $remainingTotal = max(0, $remainingTotal - $promoDiscount);

            \Log::debug("Promo {$promo->name} applied", [
                'discount' => $promoDiscount,
                'total_discount' => $promotionDiscount,
                'remaining_total' => $remainingTotal,
                'promo_type' => $promo->type,
            ]);

            // Если акция эксклюзивная или не суммируется
            if ($promo->is_exclusive || !$promo->stackable) {
                $hasExclusivePromo = true;
            }
        }

        // Обновляем заказ если есть применённые акции
        $updateData = [];

        if ($promotionDiscount > 0) {
            $currentDiscount = $order->discount_amount ?? 0;
            $updateData['discount_amount'] = $currentDiscount + $promotionDiscount;
        }

        if (!empty($appliedPromotions)) {
            $updateData['applied_discounts'] = $appliedPromotions;
        }

        if ($freeDelivery) {
            $updateData['free_delivery'] = true;
        }

        if (!empty($updateData)) {
            $order->update($updateData);
        }

        // Пересчитываем итого
        $order->recalculateTotal();

        // Загружаем обновлённый заказ со всеми связями
        $order = $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        \Log::info('attachCustomer result', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'loyalty_discount' => $loyaltyDiscount,
            'promotion_discount' => $promotionDiscount,
            'applied_promotions_count' => count($appliedPromotions),
            'applied_promotions' => $appliedPromotions,
            'order_discount_amount' => $order->discount_amount,
            'order_applied_discounts' => $order->applied_discounts,
            'new_total' => $order->total,
        ]);

        // Refresh customer чтобы accessor bonus_balance пересчитался
        $customer = $order->customer->fresh();

        // Принудительно добавляем bonus_balance в customer для передачи на фронт
        $customerData = $customer->toArray();
        $customerData['bonus_balance'] = $customer->bonus_balance;
        $customerData['bonus_balance'] = $customer->bonus_balance ?? 0;

        // Обновляем customer в order для корректной сериализации
        $order->setRelation('customer', $customer);

        return response()->json([
            'success' => true,
            'order' => $order,
            'customer' => $customerData,
            'loyalty_discount' => $loyaltyDiscount,
            'loyalty_level' => $loyaltyLevelName,
            'promotion_discount' => $promotionDiscount,
            'applied_promotions' => $appliedPromotions,
            'new_total' => $order->total,
            'debug_bonus' => [
                'bonus_balance' => $customer->bonus_balance,
                'bonus_balance' => $customer->bonus_balance ?? 0,
            ]
        ]);
    }

    /**
     * Отвязать клиента от заказа
     * Enterprise: полный сброс всех скидок (безопасный подход)
     */
    public function detachCustomer(Order $order)
    {
        // Удаляем все подарочные позиции
        $order->items()->where('is_gift', true)->delete();

        // Enterprise: полный сброс ВСЕХ скидок при отвязке клиента
        $order->update([
            'customer_id' => null,
            'loyalty_discount_amount' => 0,
            'loyalty_level_id' => null,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'discount_max_amount' => null,
            'discount_reason' => null,
            'promo_code' => null,
            'applied_discounts' => null,
            'free_delivery' => false,
            'pending_bonus_spend' => 0,
        ]);

        \Log::info('Detached customer and reset all discounts', [
            'order_id' => $order->id,
        ]);

        // Пересчитываем итого
        $order->recalculateTotal();

        // Загружаем обновлённый заказ
        $order = $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
            'new_total' => $order->total,
        ]);
    }

    // ==================== БАР (виртуальный стол) ====================

    /**
     * Get bar order data as JSON
     */
    public function getBarData(Request $request)
    {
        $initialGuests = $request->input('guests', 1);

        // Получаем активные барные заказы (type='bar', table_id=null)
        $orders = Order::where('type', 'bar')
            ->whereNull('table_id')
            ->whereIn('status', ['new', 'confirmed', 'cooking', 'ready', 'served'])
            ->where('payment_status', 'pending')
            ->with(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel'])
            ->get();

        // Удаляем лишние пустые заказы
        $emptyOrders = $orders->filter(fn($o) => $o->items->isEmpty());
        $nonEmptyOrders = $orders->filter(fn($o) => $o->items->isNotEmpty());
        if ($emptyOrders->count() > 1) {
            $emptyOrders->skip(1)->each(fn($o) => $o->delete());
            $orders = $nonEmptyOrders->merge($emptyOrders->take(1))->sortBy('id')->values();
        }

        // Если нет активного заказа - создаём новый
        if ($orders->isEmpty()) {
            $today = Carbon::today();
            $orderCount = Order::whereDate('created_at', $today)->count() + 1;
            $orderNumber = 'BAR-' . $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

            // restaurant_id заполняется автоматически через BelongsToRestaurant trait
            $newOrder = Order::create([
                'order_number' => $orderNumber,
                'daily_number' => '#' . $orderNumber,
                'type' => 'bar',
                'table_id' => null,
                'status' => 'new',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
                'guests_count' => $initialGuests,
            ]);

            $orders = collect([$newOrder->load('items')]);
        }

        // Получаем категории с продуктами
        $categories = $this->getCategoriesWithProducts();

        // Виртуальный объект "стол" для бара
        $barTable = [
            'id' => 'bar',
            'number' => 'БАР',
            'name' => 'Барная стойка',
            'seats' => 10,
            'status' => $orders->isNotEmpty() && $orders->first()->items->isNotEmpty() ? 'occupied' : 'free',
            'is_bar' => true,
            'zone' => null,
        ];

        return response()->json([
            'success' => true,
            'table' => $barTable,
            'orders' => $orders,
            'categories' => $categories,
            'initialGuests' => $initialGuests,
            'linkedTableIds' => null,
            'linkedTableNumbers' => 'БАР',
            'reservation' => null,
            'isBar' => true,
        ]);
    }

    /**
     * Store new bar order
     */
    public function storeBarOrder(Request $request)
    {
        $today = Carbon::today();
        $orderCount = Order::whereDate('created_at', $today)->count() + 1;
        $orderNumber = 'BAR-' . $today->format('dmy') . '-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT);

        // restaurant_id заполняется автоматически через BelongsToRestaurant trait
        $order = Order::create([
            'order_number' => $orderNumber,
            'daily_number' => '#' . $orderNumber,
            'type' => 'bar',
            'table_id' => null,
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
            'guests_count' => $request->input('guests', 1),
        ]);

        // Real-time событие
        $this->broadcastBarOrderCreated($order);

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    /**
     * Add item to bar order
     */
    public function addBarItem(Request $request, Order $order)
    {
        // Используем тот же метод что и для столов
        return $this->addItemToOrder($request, $order);
    }

    /**
     * Update bar order item
     */
    public function updateBarItem(Request $request, Order $order, OrderItem $item)
    {
        return $this->updateOrderItem($request, $order, $item);
    }

    /**
     * Remove bar order item
     */
    public function removeBarItem(Request $request, Order $order, OrderItem $item)
    {
        return $this->removeOrderItem($request, $order, $item);
    }

    /**
     * Send bar order to kitchen
     */
    public function sendBarToKitchen(Request $request, Order $order)
    {
        return $this->sendOrderToKitchen($request, $order);
    }

    /**
     * Process bar order payment
     */
    public function barPayment(Request $request, Order $order)
    {
        return $this->processPayment($request, $order);
    }

    /**
     * Helper: Add item to any order (same logic as addItem but without Table binding)
     */
    private function addItemToOrder(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:dishes,id',
            'guest_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1',
            'modifiers' => 'nullable|array',
            'comment' => 'nullable|string|max:255',
        ]);

        $dish = Dish::with(['stopListEntry', 'parent'])->findOrFail($validated['product_id']);

        // Проверка доступности
        if (!$dish->is_available || $dish->stopListEntry !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Блюдо недоступно или в стоп-листе',
            ], 422);
        }

        $quantity = $validated['quantity'] ?? 1;
        $modifiers = $validated['modifiers'] ?? [];

        // Calculate modifier price
        $modifiersPrice = 0;
        if (!empty($modifiers)) {
            foreach ($modifiers as $mod) {
                $modifiersPrice += (float) ($mod['price'] ?? 0);
            }
        }

        // Resolve price from price list (request override or order default)
        $priceListId = $request->input('price_list_id') ?? $order->price_list_id;
        $basePrice = (float) $dish->price;
        if ($priceListId) {
            $priceListItem = PriceListItem::where('price_list_id', $priceListId)
                ->where('dish_id', $dish->id)
                ->first();
            if ($priceListItem) {
                $basePrice = (float) $priceListItem->price;
            }
        }

        $finalPrice = $basePrice + $modifiersPrice;

        $total = $finalPrice * $quantity;

        $item = OrderItem::create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => $quantity,
            'price' => $finalPrice,
            'total' => $total,
            'guest_id' => $validated['guest_id'] ?? 1,
            'status' => 'pending',
            'comment' => $validated['comment'] ?? null,
            'modifiers' => !empty($modifiers) ? $modifiers : null,
        ]);

        $order->recalculateTotal();

        // Real-time событие для заказа бара
        if ($order->type === 'bar') {
            $this->broadcastBarOrderUpdated($order->fresh());
        }

        return response()->json([
            'success' => true,
            'item' => $item->load('dish'),
            'order' => $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']),
        ]);
    }

    /**
     * Helper: Update order item
     */
    private function updateOrderItem(Request $request, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ], 400);
        }

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:99',
            'comment' => 'sometimes|nullable|string|max:255',
            'guest_number' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:served',
            'modifiers' => 'sometimes|nullable|array',
        ]);

        $updateData = [];

        if (isset($validated['quantity'])) {
            $updateData['quantity'] = $validated['quantity'];
        }
        if (array_key_exists('comment', $validated)) {
            $updateData['comment'] = $validated['comment'];
        }
        if (isset($validated['guest_number'])) {
            $updateData['guest_id'] = $validated['guest_number'];
        }
        if (isset($validated['status']) && $validated['status'] === 'served' && $item->status === 'ready') {
            $updateData['status'] = 'served';
            $updateData['served_at'] = now();
        }
        if (array_key_exists('modifiers', $validated)) {
            $updateData['modifiers'] = $validated['modifiers'];
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        $order->recalculateTotal();

        return response()->json([
            'success' => true,
            'item' => $item->fresh('dish'),
            'order' => $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']),
        ]);
    }

    /**
     * Helper: Remove order item
     */
    private function removeOrderItem(Request $request, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Позиция не принадлежит этому заказу',
            ], 400);
        }

        $item->delete();
        $order->recalculateTotal();
        $order->refresh();

        $orderDeleted = false;

        // Если заказ пустой - удаляем его (для бара)
        if ($order->items()->count() === 0 && $order->type === 'bar') {
            $order->delete();
            $orderDeleted = true;
        }

        return response()->json([
            'success' => true,
            'order' => $orderDeleted ? null : $order->fresh(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']),
            'orderDeleted' => $orderDeleted,
        ]);
    }

    /**
     * Helper: Send order to kitchen
     */
    private function sendOrderToKitchen(Request $request, Order $order)
    {
        // Получаем item_ids из JSON или form data
        $itemIds = $request->input('item_ids') ?? $request->json('item_ids') ?? [];

        if (!is_array($itemIds)) {
            $itemIds = [$itemIds];
        }

        $query = $order->items()->where('status', 'pending');

        // Если переданы конкретные ID - обновляем только их
        if (!empty($itemIds) && count($itemIds) > 0) {
            $query->whereIn('id', $itemIds);
        }

        $query->update(['status' => 'cooking']);

        // Обновляем статус заказа
        if ($order->status === 'new') {
            $order->update(['status' => 'confirmed']);
        }

        // Broadcast через Reverb - уведомляем кухню о новом заказе
        $freshOrder = $order->fresh();
        $freshOrder->load('table');
        $this->broadcastOrderStatusChanged($freshOrder, 'new', 'confirmed');

        $order->load(['items.dish', 'customer.loyaltyLevel', 'loyaltyLevel']);

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    /**
     * Helper: Process payment
     */
    private function processPayment(Request $request, Order $order)
    {
        $paymentService = app(PaymentService::class);

        $result = $paymentService->processPayment($order, [
            'method' => $request->input('payment_method', 'cash'),
            'amount' => $request->input('amount', $order->total),
            'staff_id' => auth()->id(),
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'order' => $result['data']['order'],
        ]);
    }
}

