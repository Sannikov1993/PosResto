<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToRestaurant;

class InventoryCheck extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'warehouse_id',
        'created_by',
        'number',
        'status',
        'date',
        'notes',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'discrepancy_count', 'total_difference_cost'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCheckItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'draft' => 'Черновик',
            'in_progress' => 'В процессе',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
        ][$this->status] ?? $this->status;
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getDiscrepancyCountAttribute(): int
    {
        return $this->items()
            ->whereNotNull('actual_quantity')
            ->whereColumn('actual_quantity', '!=', 'expected_quantity')
            ->count();
    }

    public function getTotalDifferenceCostAttribute(): float
    {
        return $this->items()
            ->whereNotNull('difference')
            ->selectRaw('SUM(difference * cost_price) as total')
            ->value('total') ?? 0;
    }

    public static function generateNumber(): string
    {
        $today = now()->format('ymd');
        $count = self::whereDate('created_at', today())
            ->lockForUpdate()
            ->count() + 1;
        return 'INV-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public function start(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function complete(int $userId): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId) {
            // Перезагружаем с lock для предотвращения двойного завершения
            $check = self::lockForUpdate()->findOrFail($this->id);

            if ($check->status === 'completed') {
                return false;
            }

            $unfilled = $check->items()->whereNull('actual_quantity')->count();
            if ($unfilled > 0) {
                return false;
            }

            foreach ($check->items()->whereNotNull('actual_quantity')->get() as $item) {
                if ($item->difference != 0) {
                    $ingredient = $item->ingredient;
                    if ($ingredient) {
                        IngredientStock::firstOrCreate(
                            ['warehouse_id' => $check->warehouse_id, 'ingredient_id' => $ingredient->id],
                            ['restaurant_id' => $check->restaurant_id, 'quantity' => 0, 'avg_cost' => $ingredient->cost_price ?? 0]
                        );

                        $stock = IngredientStock::lockForUpdate()
                            ->where('warehouse_id', $check->warehouse_id)
                            ->where('ingredient_id', $ingredient->id)
                            ->first();

                        $quantityBefore = $stock->quantity;
                        $stock->quantity = $item->actual_quantity;
                        $stock->save();

                        StockMovement::create([
                            'restaurant_id' => $check->restaurant_id,
                            'ingredient_id' => $ingredient->id,
                            'user_id' => $userId,
                            'type' => 'inventory',
                            'quantity' => abs($item->difference),
                            'quantity_before' => $quantityBefore,
                            'quantity_after' => $item->actual_quantity,
                            'cost_price' => $item->cost_price,
                            'total_cost' => abs($item->difference) * $item->cost_price,
                            'document_number' => $check->number,
                            'reason' => "Инвентаризация #{$check->number}",
                        ]);
                    }
                }
            }

            $check->update([
                'status' => 'completed',
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);

            return true;
        });
    }

    public function cancel(): void
    {
        if ($this->status !== 'completed') {
            $this->update(['status' => 'cancelled']);
        }
    }

    public function populateFromStock(): void
    {
        $stocks = IngredientStock::where('warehouse_id', $this->warehouse_id)
            ->with('ingredient')
            ->get();

        foreach ($stocks as $stock) {
            if ($stock->ingredient && $stock->ingredient->track_stock) {
                InventoryCheckItem::updateOrCreate(
                    [
                        'inventory_check_id' => $this->id,
                        'ingredient_id' => $stock->ingredient_id,
                    ],
                    [
                        'restaurant_id' => $this->restaurant_id,
                        'expected_quantity' => $stock->quantity,
                        'cost_price' => $stock->ingredient->cost_price ?? 0,
                    ]
                );
            }
        }
    }
}
