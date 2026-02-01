<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;

class Modifier extends Model
{
    use BelongsToTenant, BelongsToRestaurant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'type',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
        'is_active',
        'is_global',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order');
    }

    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_modifier');
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(ModifierOption::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    // Helpers
    public function isSingle(): bool
    {
        return $this->type === 'single';
    }

    public function isMultiple(): bool
    {
        return $this->type === 'multiple';
    }
}
