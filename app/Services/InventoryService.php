<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Добавить остаток на склад
     */
    public function addStock(
        int $ingredientId,
        int $warehouseId,
        float $quantity,
        ?float $costPrice = null,
        ?int $userId = null
    ): StockMovement {
        $ingredient = Ingredient::findOrFail($ingredientId);

        // Обновляем или создаём запись остатка
        $stock = IngredientStock::firstOrNew([
            'ingredient_id' => $ingredientId,
            'warehouse_id' => $warehouseId,
        ]);

        $stock->quantity = ($stock->quantity ?? 0) + $quantity;
        $stock->cost_price = $costPrice ?? $ingredient->cost_price ?? 0;
        $stock->save();

        // Создаём запись движения
        return StockMovement::create([
            'restaurant_id' => $ingredient->restaurant_id,
            'ingredient_id' => $ingredientId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'type' => 'income',
            'quantity' => $quantity,
            'cost_price' => $costPrice ?? $ingredient->cost_price ?? 0,
            'total_cost' => $quantity * ($costPrice ?? $ingredient->cost_price ?? 0),
            'movement_date' => now(),
        ]);
    }

    /**
     * Списать со склада
     */
    public function writeOff(
        int $ingredientId,
        int $warehouseId,
        float $quantity,
        string $reason,
        ?int $userId = null
    ): ?StockMovement {
        $ingredient = Ingredient::findOrFail($ingredientId);
        $currentStock = $this->getStockInWarehouse($ingredientId, $warehouseId);

        if ($currentStock < $quantity) {
            return null; // Недостаточно остатка
        }

        // Уменьшаем остаток
        IngredientStock::where('ingredient_id', $ingredientId)
            ->where('warehouse_id', $warehouseId)
            ->decrement('quantity', $quantity);

        // Создаём запись движения
        return StockMovement::create([
            'restaurant_id' => $ingredient->restaurant_id,
            'ingredient_id' => $ingredientId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'type' => 'write_off',
            'quantity' => -$quantity,
            'cost_price' => $ingredient->cost_price ?? 0,
            'total_cost' => -($quantity * ($ingredient->cost_price ?? 0)),
            'reason' => $reason,
            'movement_date' => now(),
        ]);
    }

    /**
     * Перемещение между складами
     */
    public function transfer(
        int $ingredientId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?int $userId = null
    ): bool {
        $currentStock = $this->getStockInWarehouse($ingredientId, $fromWarehouseId);

        if ($currentStock < $quantity) {
            return false;
        }

        return DB::transaction(function () use ($ingredientId, $fromWarehouseId, $toWarehouseId, $quantity, $userId) {
            $ingredient = Ingredient::find($ingredientId);
            $costPrice = $ingredient->cost_price ?? 0;

            // Уменьшаем на исходном складе
            IngredientStock::where('ingredient_id', $ingredientId)
                ->where('warehouse_id', $fromWarehouseId)
                ->decrement('quantity', $quantity);

            // Увеличиваем на целевом складе
            $targetStock = IngredientStock::firstOrNew([
                'ingredient_id' => $ingredientId,
                'warehouse_id' => $toWarehouseId,
            ]);
            $targetStock->quantity = ($targetStock->quantity ?? 0) + $quantity;
            $targetStock->cost_price = $costPrice;
            $targetStock->save();

            // Движение: расход с исходного склада
            StockMovement::create([
                'restaurant_id' => $ingredient->restaurant_id,
                'ingredient_id' => $ingredientId,
                'warehouse_id' => $fromWarehouseId,
                'user_id' => $userId,
                'type' => 'transfer_out',
                'quantity' => -$quantity,
                'cost_price' => $costPrice,
                'total_cost' => -($quantity * $costPrice),
                'target_warehouse_id' => $toWarehouseId,
                'movement_date' => now(),
            ]);

            // Движение: приход на целевой склад
            StockMovement::create([
                'restaurant_id' => $ingredient->restaurant_id,
                'ingredient_id' => $ingredientId,
                'warehouse_id' => $toWarehouseId,
                'user_id' => $userId,
                'type' => 'transfer_in',
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'total_cost' => $quantity * $costPrice,
                'source_warehouse_id' => $fromWarehouseId,
                'movement_date' => now(),
            ]);

            return true;
        });
    }

    /**
     * Получить остаток ингредиента на складе
     */
    public function getStockInWarehouse(int $ingredientId, int $warehouseId): float
    {
        return IngredientStock::where('ingredient_id', $ingredientId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0;
    }

    /**
     * Получить общий остаток ингредиента
     */
    public function getTotalStock(int $ingredientId): float
    {
        return IngredientStock::where('ingredient_id', $ingredientId)
            ->sum('quantity');
    }

    /**
     * Создать и провести накладную прихода
     */
    public function createIncomeInvoice(array $data, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $invoice = Invoice::create([
                'restaurant_id' => $data['restaurant_id'] ?? 1,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $userId,
                'type' => 'income',
                'number' => Invoice::generateNumber('income'),
                'external_number' => $data['external_number'] ?? null,
                'status' => 'draft',
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $ingredient = Ingredient::find($item['ingredient_id']);
                $costPrice = $item['cost_price'] ?? $ingredient->cost_price ?? 0;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'cost_price' => $costPrice,
                    'total' => $item['quantity'] * $costPrice,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }

            $invoice->recalculateTotal();

            return $invoice;
        });
    }

    /**
     * Провести накладную (применить изменения к остаткам)
     */
    public function completeInvoice(Invoice $invoice, ?int $userId = null): bool
    {
        if ($invoice->status === 'completed') {
            return false;
        }

        return DB::transaction(function () use ($invoice, $userId) {
            foreach ($invoice->items as $item) {
                switch ($invoice->type) {
                    case 'income':
                        $this->addStock(
                            $item->ingredient_id,
                            $invoice->warehouse_id,
                            $item->quantity,
                            $item->cost_price,
                            $userId
                        );
                        break;

                    case 'write_off':
                    case 'expense':
                        $this->writeOff(
                            $item->ingredient_id,
                            $invoice->warehouse_id,
                            $item->quantity,
                            $invoice->notes ?? 'Накладная расхода',
                            $userId
                        );
                        break;

                    case 'transfer':
                        if ($invoice->target_warehouse_id) {
                            $this->transfer(
                                $item->ingredient_id,
                                $invoice->warehouse_id,
                                $invoice->target_warehouse_id,
                                $item->quantity,
                                $userId
                            );
                        }
                        break;
                }
            }

            $invoice->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Создать инвентаризацию
     */
    public function createInventoryCheck(int $warehouseId, int $restaurantId, ?int $userId = null, ?string $notes = null): InventoryCheck
    {
        $check = InventoryCheck::create([
            'restaurant_id' => $restaurantId,
            'warehouse_id' => $warehouseId,
            'created_by' => $userId,
            'number' => InventoryCheck::generateNumber(),
            'status' => 'draft',
            'date' => now()->toDateString(),
            'notes' => $notes,
        ]);

        // Заполняем позиции из текущих остатков
        $this->populateInventoryCheckFromStock($check);

        return $check;
    }

    /**
     * Заполнить инвентаризацию из текущих остатков
     */
    public function populateInventoryCheckFromStock(InventoryCheck $check): void
    {
        $stocks = IngredientStock::where('warehouse_id', $check->warehouse_id)
            ->with('ingredient')
            ->get();

        foreach ($stocks as $stock) {
            if ($stock->ingredient && $stock->ingredient->track_stock) {
                InventoryCheckItem::create([
                    'inventory_check_id' => $check->id,
                    'ingredient_id' => $stock->ingredient_id,
                    'expected_quantity' => $stock->quantity,
                    'cost_price' => $stock->ingredient->cost_price ?? 0,
                ]);
            }
        }
    }

    /**
     * Завершить инвентаризацию (применить корректировки)
     */
    public function completeInventoryCheck(InventoryCheck $check, ?int $userId = null): bool
    {
        if ($check->status === 'completed') {
            return false;
        }

        // Проверяем что все позиции заполнены
        $unfilled = $check->items()->whereNull('actual_quantity')->count();
        if ($unfilled > 0) {
            return false;
        }

        return DB::transaction(function () use ($check, $userId) {
            foreach ($check->items as $item) {
                $diff = $item->actual_quantity - $item->expected_quantity;

                if ($diff == 0) {
                    continue;
                }

                if ($diff > 0) {
                    // Излишек - приход
                    $this->addStock(
                        $item->ingredient_id,
                        $check->warehouse_id,
                        $diff,
                        $item->cost_price,
                        $userId
                    );
                } else {
                    // Недостача - списание
                    $this->writeOff(
                        $item->ingredient_id,
                        $check->warehouse_id,
                        abs($diff),
                        'Инвентаризация ' . $check->number,
                        $userId
                    );
                }

                // Обновляем разницу в позиции
                $item->update(['difference' => $diff]);
            }

            $check->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            return true;
        });
    }

    /**
     * Получить ингредиенты с низким остатком
     */
    public function getLowStockIngredients(int $restaurantId): \Illuminate\Support\Collection
    {
        return Ingredient::with(['category', 'unit', 'stocks'])
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->get()
            ->filter(function ($ingredient) {
                $totalStock = $ingredient->stocks->sum('quantity');
                return $totalStock <= $ingredient->min_stock;
            });
    }

    /**
     * Получить сводку по остаткам
     */
    public function getStockSummary(int $restaurantId, ?int $warehouseId = null): array
    {
        $query = IngredientStock::whereHas('ingredient', function ($q) use ($restaurantId) {
            $q->where('restaurant_id', $restaurantId);
        });

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->with('ingredient')->get();

        $totalValue = 0;
        $totalItems = 0;
        $lowStockCount = 0;

        foreach ($stocks as $stock) {
            $totalValue += $stock->quantity * ($stock->cost_price ?? $stock->ingredient->cost_price ?? 0);
            $totalItems++;

            if ($stock->ingredient->track_stock &&
                $stock->quantity <= $stock->ingredient->min_stock) {
                $lowStockCount++;
            }
        }

        return [
            'total_value' => round($totalValue, 2),
            'total_items' => $totalItems,
            'low_stock_count' => $lowStockCount,
        ];
    }

    /**
     * Списание по рецепту блюда
     */
    public function writeOffByRecipe(int $dishId, int $warehouseId, int $quantity = 1, ?int $userId = null): bool
    {
        $dish = \App\Models\Dish::with('recipes.ingredient')->find($dishId);

        if (!$dish || $dish->recipes->isEmpty()) {
            return true; // Нет рецепта - ничего не списываем
        }

        return DB::transaction(function () use ($dish, $warehouseId, $quantity, $userId) {
            foreach ($dish->recipes as $recipe) {
                if (!$recipe->ingredient || !$recipe->ingredient->track_stock) {
                    continue;
                }

                $totalQuantity = $recipe->quantity * $quantity;

                $this->writeOff(
                    $recipe->ingredient_id,
                    $warehouseId,
                    $totalQuantity,
                    "Продажа: {$dish->name} x{$quantity}",
                    $userId
                );
            }

            return true;
        });
    }
}
