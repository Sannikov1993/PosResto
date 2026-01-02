<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class InventoryCheck extends Model
{
    protected $fillable = [
        'restaurant_id',
        'number',
        'date',
        'status',
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'items_count', 'discrepancy_count'];

    // Relationships
    public function items()
    {
        return $this->hasMany(InventoryCheckItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return [
            'draft' => 'Черновик',
            'in_progress' => 'В процессе',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
        ][$this->status] ?? $this->status;
    }

    public function getItemsCountAttribute()
    {
        return $this->items()->count();
    }

    public function getDiscrepancyCountAttribute()
    {
        return $this->items()->whereRaw('actual_quantity != expected_quantity')->count();
    }

    // Methods
    public static function generateNumber()
    {
        $today = now()->format('ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'INV-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public function start()
    {
        $this->update(['status' => 'in_progress']);
    }

    public function complete($userId)
    {
        // Применяем все расхождения
        foreach ($this->items as $item) {
            if ($item->actual_quantity !== null && $item->difference != 0) {
                $ingredient = $item->ingredient;
                if ($ingredient) {
                    $ingredient->adjustStock(
                        $item->difference,
                        'inventory',
                        "Инвентаризация #{$this->number}",
                        null,
                        $userId
                    );
                }
            }
        }

        $this->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}