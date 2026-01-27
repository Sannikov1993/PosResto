<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'restaurant_id',
        'warehouse_id',
        'supplier_id',
        'user_id',
        'type',
        'number',
        'external_number',
        'status',
        'total_amount',
        'target_warehouse_id',
        'invoice_date',
        'notes',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['type_label', 'status_label', 'items_count'];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    // Static
    public static function getTypes(): array
    {
        return [
            'income' => 'Приход',
            'expense' => 'Расход',
            'transfer' => 'Перемещение',
            'write_off' => 'Списание',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            'draft' => 'Черновик',
            'pending' => 'На проверке',
            'completed' => 'Проведён',
            'cancelled' => 'Отменён',
        ];
    }

    // Methods
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total')
        ]);
    }

    public function complete(?int $userId = null): bool
    {
        if ($this->status !== 'draft' && $this->status !== 'pending') {
            return false;
        }

        // Проводим позиции
        foreach ($this->items as $item) {
            $ingredient = $item->ingredient;
            $quantity = $this->type === 'income' ? $item->quantity : -$item->quantity;

            $ingredient->adjustStock(
                $this->warehouse_id,
                $quantity,
                $this->type,
                $userId,
                null,
                $this->id,
                'invoice'
            );

            // Для перемещения - добавляем на целевой склад
            if ($this->type === 'transfer' && $this->target_warehouse_id) {
                $ingredient->adjustStock(
                    $this->target_warehouse_id,
                    abs($item->quantity),
                    'transfer_in',
                    $userId,
                    null,
                    $this->id,
                    'invoice'
                );
            }
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);

        return true;
    }

    public function cancel(): bool
    {
        if ($this->status === 'completed') {
            return false; // Нельзя отменить проведённый документ
        }

        $this->update(['status' => 'cancelled']);
        return true;
    }

    public static function generateNumber(string $type): string
    {
        $prefix = match($type) {
            'income' => 'ПР',
            'expense' => 'РС',
            'transfer' => 'ПМ',
            'write_off' => 'СП',
            default => 'ДК'
        };

        $lastNumber = self::where('type', $type)
            ->whereYear('created_at', now()->year)
            ->max('id') ?? 0;

        return $prefix . '-' . now()->format('y') . '-' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
    }
}
