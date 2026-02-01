<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestMenuSetting extends Model
{
    protected $fillable = [
        'restaurant_id',
        'key',
        'value',
    ];

    public static function get($key, $default = null, int $restaurantId)
    {
        $setting = self::where('restaurant_id', $restaurantId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value, int $restaurantId)
    {
        return self::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getAll(int $restaurantId)
    {
        return self::where('restaurant_id', $restaurantId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
