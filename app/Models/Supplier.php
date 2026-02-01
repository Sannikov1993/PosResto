<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;

class Supplier extends Model
{
    use BelongsToTenant, BelongsToRestaurant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'inn',
        'kpp',
        'payment_terms',
        'delivery_days',
        'min_order_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'delivery_days' => 'integer',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getTotalPurchasesAttribute(): float
    {
        return $this->invoices()
            ->where('type', 'income')
            ->where('status', 'completed')
            ->sum('total_amount');
    }
}
