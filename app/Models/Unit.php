<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Unit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'short_name',
        'type',
        'base_ratio',
    ];

    protected $casts = [
        'base_ratio' => 'decimal:4',
    ];

    // Relationships
    public function ingredients()
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
}