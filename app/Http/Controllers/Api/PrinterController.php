<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Order;
use App\Services\EscPosService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrinterController extends Controller
{
    // ==========================================
    // ПРИНТЕРЫ
    // ==========================================

    public function index(Request $request): JsonResponse
    {
        $printers = Printer::with('kitchenStation')
            ->where('restaurant_id', $request->input('restaurant_id', 1))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $printers,
            'printers' => $printers, // для совместимости с backoffice
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:receipt,kitchen,bar,delivery,label',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'connection_type' => 'required|in:network,usb,bluetooth,file',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_path' => 'nullable|string|max:100',
            'paper_width' => 'nullable|in:58,80',
            'chars_per_line' => 'nullable|integer|min:20|max:80',
            'encoding' => 'nullable|string|max:20',
            'cut_paper' => 'nullable|boolean',
            'open_drawer' => 'nullable|boolean',
            'print_logo' => 'nullable|boolean',
            'print_qr' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        // Авто-расчёт символов в строке
        if (empty($validated['chars_per_line'])) {
            $validated['chars_per_line'] = ($validated['paper_width'] ?? 80) == 80 ? 48 : 32;
        }

        $printer = Printer::create([
            'restaurant_id' => $request->input('restaurant_id', 1),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Принтер добавлен',
            'data' => $printer,
        ], 201);
    }

    public function update(Request $request, Printer $printer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:receipt,kitchen,bar,delivery,label',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'connection_type' => 'sometimes|in:network,usb,bluetooth,file',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_path' => 'nullable|string|max:100',
            'paper_width' => 'nullable|in:58,80',
            'chars_per_line' => 'nullable|integer|min:20|max:80',
            'encoding' => 'nullable|string|max:20',
            'cut_paper' => 'nullable|boolean',
            'open_drawer' => 'nullable|boolean',
            'print_logo' => 'nullable|boolean',
            'print_qr' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        // Обрабатываем kitchen_station_id=null отдельно (сброс привязки)
        if ($request->has('kitchen_station_id')) {
            $validated['kitchen_station_id'] = $request->input('kitchen_station_id');
        }

        // Если делаем принтер дефолтным, сбрасываем у других
        if (!empty($validated['is_default']) && $validated['is_default']) {
            Printer::where('restaurant_id', $printer->restaurant_id)
                ->where('type', $printer->type)
                ->where('id', '!=', $printer->id)
                ->update(['is_default' => false]);
        }

        $printer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Принтер обновлён',
            'data' => $printer,
        ]);
    }

    public function destroy(Printer $printer): JsonResponse
    {
        $printer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Принтер удалён',
        ]);
    }

    // ==========================================
    // ТЕСТИРОВАНИЕ
    // ==========================================

    public function test(Printer $printer): JsonResponse
    {
        try {
            \Log::info('Test print started', ['printer_id' => $printer->id, 'name' => $printer->name, 'connection_type' => $printer->connection_type]);

            // Для USB принтеров не проверяем соединение, сразу печатаем
            if ($printer->connection_type !== 'usb') {
                $connection = $printer->testConnection();

                if (!$connection['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $connection['message'],
                    ], 422);
                }
            }

            // Генерация тестовой страницы
            $service = new ReceiptService($printer);
            $content = $service->generateTestPage();

            \Log::info('Test page generated', ['content_length' => strlen($content)]);

            // Отправка
            $result = $printer->send($content);

            \Log::info('Print result', $result);

            $response = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Тестовая страница отправлена' : ($result['message'] ?? 'Неизвестная ошибка'),
            ];

            // Добавляем debug если есть
            if (!empty($result['debug'])) {
                $response['debug'] = $result['debug'];
            }

            return response()->json($response, $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            \Log::error('Test print exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'debug' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Тестовая печать чека определённого типа
     */
    public function testReceipt(Request $request, Printer $printer): JsonResponse
    {
        $type = $request->input('type', 'guest');

        try {
            \Log::info('Test receipt print', ['printer_id' => $printer->id, 'type' => $type]);

            // Создаём тестовый заказ
            $testOrder = $this->createTestOrder($type);

            // Генерация чека нужного типа
            $service = new ReceiptService($printer);

            switch ($type) {
                case 'delivery':
                    $testOrder->type = 'delivery';
                    $content = $service->generateDeliveryReceipt($testOrder);
                    break;
                case 'kitchen':
                    $content = $service->generateKitchenOrder($testOrder);
                    break;
                case 'precheck':
                    $content = $service->generatePrecheck($testOrder);
                    break;
                default: // guest
                    $content = $service->generateReceipt($testOrder);
            }

            // Отправка на печать
            $result = $printer->send($content);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Тестовый чек отправлен' : ($result['message'] ?? 'Ошибка печати'),
            ], $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            \Log::error('Test receipt print exception', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать тестовый заказ для печати
     */
    private function createTestOrder(string $type): Order
    {
        $order = new Order();
        $order->id = 1;
        $order->order_number = $type === 'delivery' ? 'D-0001' : '0001';
        $order->type = $type === 'delivery' ? 'delivery' : 'dine_in';
        $order->status = 'completed';
        $order->subtotal = 1520;
        $order->total = 1520;
        $order->discount = 0;
        $order->bonus_used = 0;
        $order->bonus_earned = 15;
        $order->payment_method = 'card';
        $order->guests_count = 2;
        $order->created_at = now();

        // Тестовые позиции (с разными гостями для счёта)
        $items = collect([
            (object)[
                'quantity' => 2,
                'name' => 'Пицца Маргарита',
                'dish' => (object)['name' => 'Пицца Маргарита'],
                'price' => 445,
                'total' => 890,
                'modifiers' => [['name' => 'Двойной сыр']],
                'comment' => null,
                'guest_number' => 1,
            ],
            (object)[
                'quantity' => 1,
                'name' => 'Кола 0.5л',
                'dish' => (object)['name' => 'Кола 0.5л'],
                'price' => 90,
                'total' => 90,
                'modifiers' => [],
                'comment' => null,
                'guest_number' => 1,
            ],
            (object)[
                'quantity' => 1,
                'name' => 'Цезарь с курицей',
                'dish' => (object)['name' => 'Цезарь с курицей'],
                'price' => 450,
                'total' => 450,
                'modifiers' => [],
                'comment' => $type === 'kitchen' ? 'Без лука!' : null,
                'guest_number' => 2,
            ],
            (object)[
                'quantity' => 1,
                'name' => 'Кола 0.5л',
                'dish' => (object)['name' => 'Кола 0.5л'],
                'price' => 90,
                'total' => 90,
                'modifiers' => [],
                'comment' => null,
                'guest_number' => 2,
            ],
        ]);

        $order->setRelation('items', $items);

        // Тестовые связи
        $order->setRelation('table', (object)['number' => '5']);
        $order->setRelation('waiter', (object)['name' => 'Анна']);
        $order->setRelation('restaurant', (object)[
            'name' => 'Тестовый ресторан',
            'address' => 'ул. Тестовая, д. 1',
            'phone' => '+7 (999) 123-45-67',
            'inn' => '1234567890',
        ]);

        if ($type === 'delivery') {
            $order->delivery_address = 'ул. Ленина, д. 10';
            $order->delivery_entrance = '2';
            $order->delivery_floor = '5';
            $order->delivery_apartment = '42';
            $order->delivery_intercom = '42#';
            $order->delivery_fee = 0;
            $order->comment = 'Позвонить за 5 минут';
            $order->setRelation('customer', (object)[
                'name' => 'Иван Петров',
                'phone' => '+7 (999) 123-45-67',
            ]);
            $order->setRelation('courier', (object)['name' => 'Алексей']);
        }

        return $order;
    }

    public function checkConnection(Printer $printer): JsonResponse
    {
        $result = $printer->testConnection();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'status' => $result['success'] ? 'online' : 'offline',
        ]);
    }

    /**
     * Получить список доступных принтеров Windows
     */
    public function getSystemPrinters(): JsonResponse
    {
        // На Windows получаем список через PowerShell
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $psCommand = 'powershell -Command "Get-CimInstance -ClassName Win32_Printer | Select-Object Name, PortName, ShareName, DriverName, PrinterStatus | ConvertTo-Json"';
            $output = shell_exec($psCommand);

            $printers = json_decode($output, true);

            if (!$printers) {
                $printers = [];
            }

            // Нормализуем если только один принтер (не массив)
            if (isset($printers['Name'])) {
                $printers = [$printers];
            }

            return response()->json([
                'success' => true,
                'printers' => $printers,
                'message' => 'Найдено принтеров: ' . count($printers),
            ]);
        }

        // На Linux получаем через lpstat
        $output = shell_exec('lpstat -a 2>/dev/null');
        $printers = [];

        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (preg_match('/^(\S+)\s/', $line, $matches)) {
                    $printers[] = ['Name' => $matches[1]];
                }
            }
        }

        return response()->json([
            'success' => true,
            'printers' => $printers,
            'message' => 'Найдено принтеров: ' . count($printers),
        ]);
    }

    // ==========================================
    // ПЕЧАТЬ
    // ==========================================

    /**
     * Печать чека
     */
    public function printReceipt(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        // Для доставки сначала ищем принтер delivery, потом receipt
        if ($order->type === 'delivery') {
            $printer = Printer::getDefault('delivery', $restaurantId)
                    ?? Printer::getDefault('receipt', $restaurantId);
        } else {
            $printer = Printer::getDefault('receipt', $restaurantId);
        }

        if (!$printer) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен принтер для чеков',
            ], 422);
        }

        $service = new ReceiptService($printer);
        $content = $service->generateReceipt($order->load(['items.dish', 'table', 'waiter', 'customer', 'courier', 'restaurant']));

        // Создаём задание на печать
        $job = PrintJob::create([
            'restaurant_id' => $restaurantId,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'type' => 'receipt',
            'status' => 'pending',
            'content' => $content,
        ]);

        // Печатаем сразу
        $result = $job->process();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Чек напечатан' : $result['message'],
            'job_id' => $job->id,
        ]);
    }

    /**
     * Печать счёта (precheck)
     */
    public function printPrecheck(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $guestNumber = $request->input('guest_number'); // null = все гости

        // Отладка: проверяем настройки из кэша
        $cacheKey = "print_settings_{$restaurantId}";
        $cachedSettings = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        \Log::info('Precheck print settings', [
            'restaurant_id' => $restaurantId,
            'cache_key' => $cacheKey,
            'settings_count' => count($cachedSettings),
            'precheck_title' => $cachedSettings['precheck_title'] ?? 'DEFAULT',
            'precheck_footer' => $cachedSettings['precheck_footer'] ?? 'DEFAULT',
            'guest_number' => $guestNumber,
        ]);

        $printer = Printer::getDefault('receipt', $restaurantId);

        if (!$printer) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен принтер для чеков (type: receipt)',
            ], 422);
        }

        \Log::info('Precheck printer found', [
            'printer_id' => $printer->id,
            'printer_name' => $printer->name,
            'printer_restaurant_id' => $printer->restaurant_id,
        ]);

        $service = new ReceiptService($printer);
        $content = $service->generatePrecheck($order->load(['items.dish', 'table', 'waiter']), $guestNumber);

        $job = PrintJob::create([
            'restaurant_id' => $restaurantId,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'type' => 'precheck',
            'status' => 'pending',
            'content' => $content,
        ]);

        $result = $job->process();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Счёт напечатан' : $result['message'],
        ]);
    }

    /**
     * Печать на кухню
     */
    public function printToKitchen(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);

        // Получаем кухонные и барные принтеры
        $kitchenPrinters = Printer::with('kitchenStation')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('type', ['kitchen', 'bar'])
            ->where('is_active', true)
            ->get();

        if ($kitchenPrinters->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроены принтеры для кухни',
            ], 422);
        }

        $order->load(['items.dish.category', 'table', 'waiter']);
        $results = [];
        $printedStations = []; // Для отслеживания напечатанных цехов

        foreach ($kitchenPrinters as $printer) {
            $service = new ReceiptService($printer);

            // Фильтруем позиции по цеху принтера
            $items = $this->filterItemsByStation($order->items, $printer);

            // Пропускаем если нет позиций для этого принтера
            if ($items->isEmpty()) continue;

            $content = $service->generateKitchenOrder($order, $items->toArray());

            $job = PrintJob::create([
                'restaurant_id' => $restaurantId,
                'printer_id' => $printer->id,
                'order_id' => $order->id,
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
                'message' => $result['message'],
            ];

            if ($printer->kitchen_station_id) {
                $printedStations[] = $printer->kitchen_station_id;
            }
        }

        // Проверяем есть ли позиции без назначенного цеха
        $unassignedItems = $order->items->filter(function ($item) use ($printedStations) {
            $stationId = $item->dish?->category?->kitchen_station_id;
            return !$stationId || !in_array($stationId, $printedStations);
        });

        // Если есть позиции без цеха и есть принтер без привязки — печатаем на него
        if ($unassignedItems->isNotEmpty()) {
            $defaultPrinter = $kitchenPrinters->first(fn($p) => !$p->kitchen_station_id);

            if ($defaultPrinter && !collect($results)->contains('printer', $defaultPrinter->name)) {
                $service = new ReceiptService($defaultPrinter);
                $content = $service->generateKitchenOrder($order, $unassignedItems->toArray());

                $job = PrintJob::create([
                    'restaurant_id' => $restaurantId,
                    'printer_id' => $defaultPrinter->id,
                    'order_id' => $order->id,
                    'type' => 'kitchen',
                    'status' => 'pending',
                    'content' => $content,
                ]);

                $result = $job->process();
                $results[] = [
                    'printer' => $defaultPrinter->name,
                    'station' => null,
                    'items_count' => $unassignedItems->count(),
                    'success' => $result['success'],
                    'message' => $result['message'],
                ];
            }
        }

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'message' => 'Нет позиций для печати',
            ], 422);
        }

        $allSuccess = collect($results)->every('success');

        return response()->json([
            'success' => $allSuccess,
            'message' => $allSuccess ? 'Заказ отправлен на кухню' : 'Есть ошибки печати',
            'results' => $results,
        ]);
    }

    /**
     * Фильтрация позиций заказа по цеху принтера
     */
    private function filterItemsByStation($items, Printer $printer)
    {
        // Если у принтера не указан цех — он печатает все позиции своего типа
        if (!$printer->kitchen_station_id) {
            // Для барного принтера — только барные позиции (is_bar=true в категории)
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
     * Печать отчёта
     */
    public function printReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:X,Z',
            'data' => 'required|array',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $printer = Printer::getDefault('receipt', $restaurantId);

        if (!$printer) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен принтер',
            ], 422);
        }

        $service = new ReceiptService($printer);
        $content = $service->generateReport($validated['data'], $validated['type']);

        $job = PrintJob::create([
            'restaurant_id' => $restaurantId,
            'printer_id' => $printer->id,
            'type' => 'report',
            'status' => 'pending',
            'content' => $content,
        ]);

        $result = $job->process();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Отчёт напечатан' : $result['message'],
        ]);
    }

    // ==========================================
    // ОЧЕРЕДЬ ПЕЧАТИ
    // ==========================================

    public function queue(Request $request): JsonResponse
    {
        $jobs = PrintJob::with(['printer', 'order'])
            ->where('restaurant_id', $request->input('restaurant_id', 1))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    public function retryJob(PrintJob $job): JsonResponse
    {
        if ($job->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Задание уже выполнено',
            ], 422);
        }

        $job->update(['status' => 'pending', 'attempts' => 0]);
        $result = $job->process();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    public function cancelJob(PrintJob $job): JsonResponse
    {
        if ($job->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Задание уже выполнено',
            ], 422);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Задание отменено',
        ]);
    }

    // ==========================================
    // ПОЛУЧЕНИЕ RAW ДАННЫХ (для веб-печати)
    // ==========================================

    /**
     * Получить данные чека в base64 (для JavaScript печати)
     */
    public function getReceiptData(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $printerId = $request->input('printer_id');
        
        $printer = $printerId 
            ? Printer::find($printerId)
            : Printer::getDefault('receipt', $restaurantId);

        if (!$printer) {
            // Создаём виртуальный принтер для генерации
            $printer = new Printer([
                'paper_width' => 80,
                'chars_per_line' => 48,
                'encoding' => 'cp866',
                'cut_paper' => true,
                'open_drawer' => false,
                'print_qr' => true,
            ]);
        }

        $service = new ReceiptService($printer);
        $content = $service->generateReceipt($order->load(['items.dish', 'table', 'waiter', 'customer', 'courier', 'restaurant']));

        return response()->json([
            'success' => true,
            'data' => [
                'content' => $content,
                'raw' => base64_decode($content),
                'printer' => $printer->only(['name', 'ip_address', 'port']),
            ],
        ]);
    }

    /**
     * Получить данные для кухни
     */
    public function getKitchenData(Request $request, Order $order): JsonResponse
    {
        $printer = new Printer([
            'paper_width' => 80,
            'chars_per_line' => 48,
            'encoding' => 'cp866',
            'cut_paper' => true,
            'print_qr' => false,
        ]);

        $service = new ReceiptService($printer);
        $content = $service->generateKitchenOrder($order->load(['items.dish', 'table', 'waiter']));

        return response()->json([
            'success' => true,
            'data' => [
                'content' => $content,
            ],
        ]);
    }

    // ==========================================
    // ПРЕВЬЮ ЧЕКА (для браузера)
    // ==========================================

    /**
     * Получить HTML превью пречека
     */
    public function previewPrecheck(Request $request, Order $order): JsonResponse
    {
        $order->load(['items.dish', 'table', 'waiter', 'customer', 'restaurant']);

        $restaurantId = $request->input('restaurant_id', 1);

        // Загружаем настройки печати
        $cacheKey = "print_settings_{$restaurantId}";
        $settings = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $title = $settings['precheck_title'] ?? 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ';
        $subtitle = $settings['precheck_subtitle'] ?? '(не является фискальным документом)';
        $showTable = $settings['precheck_show_table'] ?? true;
        $showDate = $settings['precheck_show_date'] ?? true;
        $showWaiter = $settings['precheck_show_waiter'] ?? true;
        $showGuests = $settings['precheck_show_guests'] ?? false;
        $footer = $settings['precheck_footer'] ?? 'Приятного аппетита!';

        // Формируем данные для превью
        $preview = [
            'title' => $title,
            'subtitle' => $subtitle,
            'order_number' => $order->order_number,
            'table' => $showTable ? ($order->table?->number ?? $order->table?->name ?? '-') : null,
            'date' => $showDate ? $order->created_at->format('d.m.Y H:i') : null,
            'waiter' => $showWaiter ? ($order->waiter?->name ?? '-') : null,
            'guests' => ($showGuests && $order->guests_count) ? $order->guests_count : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->dish?->name ?? $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                    'modifiers' => $item->modifiers ?? [],
                    'comment' => $item->comment,
                ];
            }),
            'subtotal' => $order->subtotal ?? $order->total,
            'discount' => $order->discount ?? 0,
            'discount_percent' => $order->discount_percent ?? 0,
            'total' => $order->total,
            'footer' => $footer,
            'customer' => $order->customer ? [
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Получить HTML превью чека (receipt)
     */
    public function previewReceipt(Request $request, Order $order): JsonResponse
    {
        $order->load(['items.dish', 'table', 'waiter', 'customer', 'restaurant', 'payments']);

        $restaurantId = $request->input('restaurant_id', 1);

        // Загружаем настройки печати
        $cacheKey = "print_settings_{$restaurantId}";
        $settings = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $headerName = $settings['receipt_header_name'] ?? ($order->restaurant?->name ?? 'РЕСТОРАН');
        $headerAddress = $settings['receipt_header_address'] ?? ($order->restaurant?->address ?? '');
        $headerPhone = $settings['receipt_header_phone'] ?? ($order->restaurant?->phone ?? '');
        $headerInn = $settings['receipt_header_inn'] ?? '';

        $showTable = $settings['show_table'] ?? true;
        $showWaiter = $settings['show_waiter'] ?? true;
        $showOrderNumber = $settings['show_order_number'] ?? true;
        $showOrderTime = $settings['show_order_time'] ?? true;
        $showPaymentMethod = $settings['show_payment_method'] ?? true;

        $footerLine1 = $settings['receipt_footer_line1'] ?? 'Спасибо за визит!';
        $footerLine2 = $settings['receipt_footer_line2'] ?? 'Ждём вас снова!';

        // Получаем способ оплаты
        $paymentMethod = null;
        if ($showPaymentMethod && $order->payments->isNotEmpty()) {
            $methods = $order->payments->pluck('payment_method')->unique();
            $methodLabels = [
                'cash' => 'Наличные',
                'card' => 'Карта',
                'online' => 'Онлайн',
                'mixed' => 'Смешанная',
            ];
            $paymentMethod = $methods->map(fn($m) => $methodLabels[$m] ?? $m)->implode(', ');
        }

        $preview = [
            'header' => [
                'name' => $headerName,
                'address' => $headerAddress,
                'phone' => $headerPhone,
                'inn' => $headerInn,
            ],
            'order_number' => $showOrderNumber ? $order->order_number : null,
            'table' => $showTable ? ($order->table?->number ?? $order->table?->name ?? '-') : null,
            'date' => $showOrderTime ? $order->created_at->format('d.m.Y H:i') : null,
            'waiter' => $showWaiter ? ($order->waiter?->name ?? '-') : null,
            'payment_method' => $paymentMethod,
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->dish?->name ?? $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            }),
            'subtotal' => $order->subtotal ?? $order->total,
            'discount' => $order->discount ?? 0,
            'total' => $order->total,
            'footer' => [
                'line1' => $footerLine1,
                'line2' => $footerLine2,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }
}
