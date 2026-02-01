<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;

class LegalEntity extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, BelongsToRestaurant;

    // Типы юрлиц
    const TYPE_LLC = 'llc'; // ООО
    const TYPE_IE = 'ie';   // ИП

    // Системы налогообложения
    const TAX_OSN = 'osn';                     // ОСН
    const TAX_USN_INCOME = 'usn_income';       // УСН доходы
    const TAX_USN_INCOME_EXPENSE = 'usn_income_expense'; // УСН доходы-расходы
    const TAX_PATENT = 'patent';               // Патент

    protected $fillable = [
        'restaurant_id',
        'tenant_id',
        'name',
        'short_name',
        'type',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'actual_address',
        'director_name',
        'director_position',
        'bank_name',
        'bank_bik',
        'bank_account',
        'bank_corr_account',
        'taxation_system',
        'vat_rate',
        'has_alcohol_license',
        'alcohol_license_number',
        'alcohol_license_expires_at',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'vat_rate' => 'decimal:2',
        'has_alcohol_license' => 'boolean',
        'alcohol_license_expires_at' => 'date',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class)->orderBy('sort_order');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
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
     * Получить название типа юрлица
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_LLC => 'ООО',
            self::TYPE_IE => 'ИП',
            default => $this->type,
        };
    }

    /**
     * Получить название системы налогообложения
     */
    public function getTaxationLabelAttribute(): string
    {
        return match($this->taxation_system) {
            self::TAX_OSN => 'ОСН',
            self::TAX_USN_INCOME => 'УСН (доходы)',
            self::TAX_USN_INCOME_EXPENSE => 'УСН (доходы-расходы)',
            self::TAX_PATENT => 'Патент',
            default => $this->taxation_system,
        };
    }

    /**
     * Является ли ООО
     */
    public function isLLC(): bool
    {
        return $this->type === self::TYPE_LLC;
    }

    /**
     * Является ли ИП
     */
    public function isIE(): bool
    {
        return $this->type === self::TYPE_IE;
    }

    /**
     * Есть ли лицензия на алкоголь и действительна ли она
     */
    public function hasValidAlcoholLicense(): bool
    {
        if (!$this->has_alcohol_license) {
            return false;
        }

        if (!$this->alcohol_license_expires_at) {
            return true;
        }

        return $this->alcohol_license_expires_at->isFuture();
    }

    /**
     * Получить кассу по умолчанию
     */
    public function getDefaultCashRegister(): ?CashRegister
    {
        return $this->cashRegisters()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? $this->cashRegisters()->where('is_active', true)->first();
    }

    /**
     * Сделать юрлицо по умолчанию (и убрать флаг с других)
     */
    public function makeDefault(): void
    {
        // Снимаем флаг с других юрлиц этого ресторана
        static::where('restaurant_id', $this->restaurant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Названия типов юрлиц
     */
    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_LLC => 'ООО',
            self::TYPE_IE => 'ИП',
        ];
    }

    /**
     * Названия систем налогообложения
     */
    public static function getTaxationLabels(): array
    {
        return [
            self::TAX_OSN => 'ОСН (общая система)',
            self::TAX_USN_INCOME => 'УСН (доходы 6%)',
            self::TAX_USN_INCOME_EXPENSE => 'УСН (доходы минус расходы 15%)',
            self::TAX_PATENT => 'Патентная система',
        ];
    }

    /**
     * Ставки НДС
     */
    public static function getVatRates(): array
    {
        return [
            null => 'Без НДС',
            0 => '0%',
            10 => '10%',
            20 => '20%',
        ];
    }
}
