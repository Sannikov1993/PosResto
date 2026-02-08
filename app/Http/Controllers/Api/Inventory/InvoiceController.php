<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ResolvesRestaurantId;
use App\Http\Requests\Inventory\StoreInvoiceRequest;
use App\Models\Ingredient;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InventoryService;
use App\Services\YandexVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ResolvesRestaurantId;

    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['warehouse', 'supplier', 'user'])
            ->withCount('items')
            ->where('restaurant_id', $this->getRestaurantId($request));

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $perPage = min($request->input('per_page', 50), 200);

        if ($request->has('page')) {
            $paginated = $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate($perPage);

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

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restaurantId = $this->getRestaurantId($request);

        $invoice = Invoice::create([
            'restaurant_id' => $restaurantId,
            'warehouse_id' => $validated['warehouse_id'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'user_id' => $request->input('user_id') ?? auth()->id(),
            'type' => $validated['type'],
            'number' => Invoice::generateNumber($validated['type']),
            'external_number' => $validated['external_number'] ?? null,
            'status' => 'draft',
            'target_warehouse_id' => $validated['target_warehouse_id'] ?? null,
            'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $ingredient = Ingredient::forRestaurant($restaurantId)->find($item['ingredient_id']);
            if (!$ingredient) continue;
            $costPrice = $item['cost_price'] ?? $ingredient->cost_price ?? 0;

            InvoiceItem::create([
                'restaurant_id' => $restaurantId,
                'invoice_id' => $invoice->id,
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'cost_price' => $costPrice,
                'total' => $item['quantity'] * $costPrice,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);
        }

        $invoice->recalculateTotal();

        return response()->json([
            'success' => true,
            'message' => 'Накладная создана',
            'data' => $invoice->load(['items.ingredient.unit', 'warehouse', 'supplier']),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items.ingredient.unit', 'warehouse', 'targetWarehouse', 'supplier', 'user']),
        ]);
    }

    public function complete(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Накладная уже проведена',
            ], 422);
        }

        $success = $invoice->complete($request->input('user_id'));

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось провести накладную',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная проведена',
            'data' => $invoice->fresh(),
        ]);
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        if (!$invoice->cancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить проведённую накладную',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная отменена',
        ]);
    }

    public function recognize(Request $request, YandexVisionService $visionService): JsonResponse
    {
        if (!$visionService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Yandex Vision API не настроен. Добавьте YANDEX_VISION_API_KEY и YANDEX_FOLDER_ID в .env',
            ], 422);
        }

        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        $result = $visionService->recognizeInvoice($validated['image']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Ошибка распознавания',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Накладная распознана',
            'data' => [
                'items' => $result['items'],
                'items_count' => $result['items_count'],
                'raw_text' => $result['raw_text'] ?? null,
            ],
        ]);
    }

    public function checkVisionConfig(YandexVisionService $visionService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'configured' => $visionService->isConfigured(),
        ]);
    }
}
