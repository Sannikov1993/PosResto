<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    use HasFactory;

    // Указываем имя таблицы явно, т.к. "tables" - зарезервированное слово
    protected $table = 'tables';

    protected $fillable = [
        'restaurant_id',
        'zone_id',
        'number',
        'name',
        'seats',
        'min_order',
        'shape',
        'position_x',
        'position_y',
        'width',
        'height',
        'rotation',
        'status',
        'is_active',
    ];

    protected $casts = [
        'seats' => 'integer',
        'min_order' => 'decimal:2',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'rotation' => 'integer',
        'is_active' => 'boolean',
    ];

    // Статусы столов
    const STATUS_FREE = 'free';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_RESERVED = 'reserved';
    const STATUS_BILL = 'bill';

    // Формы столов
    const SHAPE_ROUND = 'round';
    const SHAPE_SQUARE = 'square';
    const SHAPE_RECTANGLE = 'rectangle';
    const SHAPE_OVAL = 'oval';

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFree($query)
    {
        return $query->where('status', self::STATUS_FREE);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    public function scopeInZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    // ===== HELPERS =====

    public function isFree(): bool
    {
        return $this->status === self::STATUS_FREE;
    }

    public function isOccupied(): bool
    {
        return $this->status === self::STATUS_OCCUPIED;
    }

    public function occupy(): void
    {
        $this->update(['status' => self::STATUS_OCCUPIED]);
    }

    public function free(): void
    {
        $this->update(['status' => self::STATUS_FREE]);
    }

    public function requestBill(): void
    {
        $this->update(['status' => self::STATUS_BILL]);
    }

    public function reserve(): void
    {
        $this->update(['status' => self::STATUS_RESERVED]);
    }

    public function getDisplayName(): string
    {
        return $this->name ?: "Стол {$this->number}";
    }

    // Цвет статуса для UI
    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_FREE => '#10B981',     // Зелёный
            self::STATUS_OCCUPIED => '#EF4444', // Красный
            self::STATUS_RESERVED => '#F59E0B', // Оранжевый
            self::STATUS_BILL => '#8B5CF6',     // Фиолетовый
            default => '#6B7280',               // Серый
        };
    }
	
	public function qrCode()
{
    return $this->hasOne(TableQrCode::class);
}
	
	
	
	
}
