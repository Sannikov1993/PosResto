<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToRestaurant;

class Zone extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'name',
        'color',
        'sort_order',
        'is_active',
        'floor_layout',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'floor_layout' => 'array',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class)->orderBy('number');
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ===== HELPERS =====

    public function getTablesCount(): int
    {
        return $this->tables()->count();
    }

    public function getActiveTablesCount(): int
    {
        return $this->tables()->where('is_active', true)->count();
    }
}
