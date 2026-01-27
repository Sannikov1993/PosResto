<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'street',
        'apartment',
        'entrance',
        'floor',
        'intercom',
        'latitude',
        'longitude',
        'comment',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Helper: получить полный адрес
    public function getFullAddressAttribute(): string
    {
        $parts = [$this->street];

        if ($this->apartment) {
            $parts[] = "кв. {$this->apartment}";
        }
        if ($this->entrance) {
            $parts[] = "подъезд {$this->entrance}";
        }
        if ($this->floor) {
            $parts[] = "этаж {$this->floor}";
        }

        return implode(', ', $parts);
    }

    // Helper: проверить есть ли координаты
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
