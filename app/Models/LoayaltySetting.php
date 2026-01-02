<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class LoyaltySetting extends Model
{
    protected $fillable = [
        'restaurant_id',
        'key',
        'value',
    ];

    public static function get($key, $default = null, $restaurantId = 1)
    {
        $setting = self::where('restaurant_id', $restaurantId)
            ->where('key', $key)
            ->first();
        
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value, $restaurantId = 1)
    {
        return self::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getAll($restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
