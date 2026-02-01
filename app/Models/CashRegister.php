<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'legal_entity_id',
        'name',
        'serial_number',
        'registration_number',
        'fn_number',
        'fn_expires_at',
        'ofd_name',
        'ofd_inn',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'fn_expires_at' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function cashShifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    public function cashOperations(): HasMany
    {
        return $this->hasMany(CashOperation::class);
    }

    public function fiscalReceipts(): HasMany
    {
        return $this->hasMany(FiscalReceipt::class);
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

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ===== HELPERS =====

    /**
     * Истекает ли срок действия ФН
     */
    public function isFnExpiringSoon(int $daysThreshold = 30): bool
    {
        if (!$this->fn_expires_at) {
            return false;
        }

        return $this->fn_expires_at->diffInDays(now()) <= $daysThreshold
            && $this->fn_expires_at->isFuture();
    }

    /**
     * Истёк ли срок действия ФН
     */
    public function isFnExpired(): bool
    {
        if (!$this->fn_expires_at) {
            return false;
        }

        return $this->fn_expires_at->isPast();
    }

    /**
     * Сделать кассу по умолчанию для юрлица
     */
    public function makeDefault(): void
    {
        // Снимаем флаг с других касс этого юрлица
        static::where('legal_entity_id', $this->legal_entity_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Получить текущую открытую смену на этой кассе
     */
    public function getCurrentShift(): ?CashShift
    {
        return $this->cashShifts()
            ->where('status', CashShift::STATUS_OPEN)
            ->latest('opened_at')
            ->first();
    }
}
