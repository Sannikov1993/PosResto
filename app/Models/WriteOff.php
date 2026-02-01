<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Traits\BelongsToRestaurant;

class WriteOff extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'warehouse_id',
        'user_id',
        'approved_by',
        'type',
        'total_amount',
        'description',
        'photo_path',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    protected $appends = ['photo_url'];

    /**
     * Типы списаний
     */
    public const TYPES = [
        'spoilage' => 'Порча продукта',
        'expired' => 'Истек срок годности',
        'loss' => 'Потеря/недостача',
        'staff_meal' => 'Питание персонала',
        'promo' => 'Промо/дегустация',
        'other' => 'Другое',
    ];

    // ==================== RELATIONSHIPS ====================

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WriteOffItem::class);
    }

    // ==================== ACCESSORS ====================

    /**
     * URL фото списания
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }
        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Название типа списания
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Списание';
    }

    // ==================== SCOPES ====================

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBetweenDates($query, ?string $dateFrom, ?string $dateTo)
    {
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        return $query;
    }

    // ==================== METHODS ====================

    /**
     * Списать ингредиенты со склада
     */
    public function deductFromInventory(): void
    {
        if (!$this->warehouse_id) {
            return;
        }

        foreach ($this->items as $item) {
            if ($item->item_type === 'ingredient' && $item->ingredient_id) {
                $ingredient = Ingredient::find($item->ingredient_id);
                if ($ingredient && $ingredient->track_stock) {
                    $ingredient->writeOff(
                        $this->warehouse_id,
                        $item->quantity,
                        "Списание #{$this->id}: {$this->description}"
                    );
                }
            }
        }
    }
}
