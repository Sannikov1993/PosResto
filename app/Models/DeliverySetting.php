<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Модель настроек доставки
 */
class DeliverySetting extends Model
{
    protected $fillable = [
        'restaurant_id',
        'key',
        'value',
    ];

    /**
     * Получить настройку по ключу
     */
    public static function getValue(string $key, $default = null, int $restaurantId)
    {
        $setting = self::where('restaurant_id', $restaurantId)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        // Пытаемся декодировать JSON
        $decoded = json_decode($setting->value, true);
        return $decoded !== null ? $decoded : $setting->value;
    }

    /**
     * Установить настройку
     */
    public static function setValue(string $key, $value, int $restaurantId): self
    {
        $storedValue = is_array($value) ? json_encode($value) : $value;

        return self::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'key' => $key],
            ['value' => $storedValue]
        );
    }

    /**
     * Получить все настройки как массив
     */
    public static function getAllSettings(int $restaurantId): array
    {
        $settings = self::where('restaurant_id', $restaurantId)->get();
        $result = [];

        foreach ($settings as $setting) {
            $decoded = json_decode($setting->value, true);
            $result[$setting->key] = $decoded !== null ? $decoded : $setting->value;
        }

        // Дефолтные значения
        return array_merge([
            'min_order_amount' => 500,
            'working_hours' => [
                'mon' => ['10:00', '22:00'],
                'tue' => ['10:00', '22:00'],
                'wed' => ['10:00', '22:00'],
                'thu' => ['10:00', '22:00'],
                'fri' => ['10:00', '23:00'],
                'sat' => ['10:00', '23:00'],
                'sun' => ['11:00', '21:00'],
            ],
            'sms_on_create' => true,
            'sms_on_courier' => true,
            'push_courier' => true,
            'alert_unassigned_minutes' => 10,
            'default_prep_time' => 30,
            'allow_preorder' => true,
            'preorder_days' => 7,
        ], $result);
    }
}
