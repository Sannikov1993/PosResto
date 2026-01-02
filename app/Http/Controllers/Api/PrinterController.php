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
        $printers = Printer::where('restaurant_id', $request->input('restaurant_id', 1))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $printers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:receipt,kitchen,bar,label',
            'connection' => 'required|in:network,usb,bluetooth,file',
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
            'type' => 'sometimes|in:receipt,kitchen,bar,label',
            'connection' => 'sometimes|in:network,usb,bluetooth,file',
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
        // Проверка соединения
        $connection = $printer->testConnection();
        
        if (!$connection['success']) {
            return response()->json([
                'success' => false,
                'message' => $connection['message'],
            ], 422);
        }

        // Генерация тестовой страницы
        $service = new ReceiptService($printer);
        $content = $service->generateTestPage();

        // Отправка
        $result = $printer->send($content);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Тестовая страница отправлена' : $result['message'],
        ]);
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

    // ==========================================
    // ПЕЧАТЬ
    // ==========================================

    /**
     * Печать чека
     */
    public function printReceipt(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $printer = Printer::getDefault('receipt', $restaurantId);

        if (!$printer) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен принтер для чеков',
            ], 422);
        }

        $service = new ReceiptService($printer);
        $content = $service->generateReceipt($order->load(['items.dish', 'table', 'waiter', 'customer']));

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
     * Печать пречека
     */
    public function printPrecheck(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $printer = Printer::getDefault('receipt', $restaurantId);

        if (!$printer) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен принтер',
            ], 422);
        }

        $service = new ReceiptService($printer);
        $content = $service->generatePrecheck($order->load(['items.dish', 'table', 'waiter']));

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
            'message' => $result['success'] ? 'Пречек напечатан' : $result['message'],
        ]);
    }

    /**
     * Печать на кухню
     */
    public function printToKitchen(Request $request, Order $order): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $printers = Printer::getKitchenPrinters($restaurantId);

        if ($printers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроены принтеры для кухни',
            ], 422);
        }

        $order->load(['items.dish.category', 'table', 'waiter']);
        $results = [];

        foreach ($printers as $printer) {
            $service = new ReceiptService($printer);
            
            // Фильтруем позиции по категориям (если настроено)
            $items = $order->items;
            // TODO: фильтрация по категориям принтера
            
            if ($items->isEmpty()) continue;

            $content = $service->generateKitchenOrder($order, $items);

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
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        $allSuccess = collect($results)->every('success');

        return response()->json([
            'success' => $allSuccess,
            'message' => $allSuccess ? 'Заказ отправлен на кухню' : 'Есть ошибки печати',
            'results' => $results,
        ]);
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
        $content = $service->generateReceipt($order->load(['items.dish', 'table', 'waiter', 'customer']));

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
}
