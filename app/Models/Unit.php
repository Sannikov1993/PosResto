<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class Unit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'short_name',
        'type',
        'base_ratio',
        'is_system',
    ];

    protected $casts = [
        'base_ratio' => 'decimal:4',
        'is_system' => 'boolean',
    ];

    // Relationships
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    // Конвертация
    public function convertTo(Unit $target, $value)
    {
        if ($this->type !== $target->type) {
            throw new \Exception('Cannot convert between different unit types');
        }
        return $value * $this->base_ratio / $target->base_ratio;
    }

    // Получить базовые единицы измерения
    public static function getDefaultUnits(): array
    {
        return [
            // Вес
            ['name' => 'Килограмм', 'short_name' => 'кг', 'type' => 'weight', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Грамм', 'short_name' => 'г', 'type' => 'weight', 'base_ratio' => 0.001, 'is_system' => true],
            // Объём
            ['name' => 'Литр', 'short_name' => 'л', 'type' => 'volume', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Миллилитр', 'short_name' => 'мл', 'type' => 'volume', 'base_ratio' => 0.001, 'is_system' => true],
            // Штуки
            ['name' => 'Штука', 'short_name' => 'шт', 'type' => 'piece', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Порция', 'short_name' => 'порц', 'type' => 'piece', 'base_ratio' => 1, 'is_system' => true],
            // Упаковки
            ['name' => 'Упаковка', 'short_name' => 'уп', 'type' => 'pack', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Коробка', 'short_name' => 'кор', 'type' => 'pack', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Бутылка', 'short_name' => 'бут', 'type' => 'pack', 'base_ratio' => 1, 'is_system' => true],
        ];
    }
}
