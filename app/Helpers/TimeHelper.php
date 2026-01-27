<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Хелпер для работы со временем с учетом таймзоны ресторана
 */
class TimeHelper
{
    /**
     * Получить текущее время в таймзоне ресторана
     */
    public static function now(int $restaurantId = 1): Carbon
    {
        return Carbon::now(self::getTimezone($restaurantId));
    }

    /**
     * Получить сегодняшнюю дату в таймзоне ресторана
     */
    public static function today(int $restaurantId = 1): Carbon
    {
        return Carbon::today(self::getTimezone($restaurantId));
    }

    /**
     * Получить вчерашнюю дату в таймзоне ресторана
     */
    public static function yesterday(int $restaurantId = 1): Carbon
    {
        return Carbon::yesterday(self::getTimezone($restaurantId));
    }

    /**
     * Получить таймзону ресторана
     */
    public static function getTimezone(int $restaurantId = 1): string
    {
        // Сначала пробуем из кеша
        $cacheKey = "general_settings_{$restaurantId}";
        $settings = Cache::get($cacheKey, []);

        if (!empty($settings['timezone'])) {
            return $settings['timezone'];
        }

        // Если в кеше нет - берём напрямую из Restaurant
        $restaurant = \App\Models\Restaurant::find($restaurantId);
        if ($restaurant && !empty($restaurant->settings['timezone'])) {
            return $restaurant->settings['timezone'];
        }

        return 'Europe/Moscow';
    }

    /**
     * Конвертировать время в таймзону ресторана
     */
    public static function toRestaurantTime(Carbon $time, int $restaurantId = 1): Carbon
    {
        return $time->setTimezone(self::getTimezone($restaurantId));
    }

    /**
     * Парсить дату с учетом таймзоны ресторана
     */
    public static function parse(string $date, int $restaurantId = 1): Carbon
    {
        return Carbon::parse($date, self::getTimezone($restaurantId));
    }

    /**
     * Форматировать время для отображения
     */
    public static function format(Carbon $time, string $format = 'd.m.Y H:i', int $restaurantId = 1): string
    {
        return self::toRestaurantTime($time, $restaurantId)->format($format);
    }

    /**
     * Начало дня в таймзоне ресторана
     */
    public static function startOfDay(?string $date = null, int $restaurantId = 1): Carbon
    {
        $tz = self::getTimezone($restaurantId);
        if ($date) {
            return Carbon::parse($date, $tz)->startOfDay();
        }
        return Carbon::today($tz)->startOfDay();
    }

    /**
     * Конец дня в таймзоне ресторана
     */
    public static function endOfDay(?string $date = null, int $restaurantId = 1): Carbon
    {
        $tz = self::getTimezone($restaurantId);
        if ($date) {
            return Carbon::parse($date, $tz)->endOfDay();
        }
        return Carbon::today($tz)->endOfDay();
    }

    /**
     * Начало недели в таймзоне ресторана
     */
    public static function startOfWeek(int $restaurantId = 1): Carbon
    {
        return Carbon::now(self::getTimezone($restaurantId))->startOfWeek();
    }

    /**
     * Начало месяца в таймзоне ресторана
     */
    public static function startOfMonth(int $restaurantId = 1): Carbon
    {
        return Carbon::now(self::getTimezone($restaurantId))->startOfMonth();
    }

    /**
     * Начало года в таймзоне ресторана
     */
    public static function startOfYear(int $restaurantId = 1): Carbon
    {
        return Carbon::now(self::getTimezone($restaurantId))->startOfYear();
    }

    /**
     * Получить рабочие часы ресторана из кэша
     */
    public static function getWorkingHours(int $restaurantId = 1): array
    {
        $cacheKey = "general_settings_{$restaurantId}";
        $settings = Cache::get($cacheKey, []);

        $defaultHours = [
            'monday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'tuesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'wednesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'thursday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'friday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'saturday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'sunday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
        ];

        return $settings['working_hours'] ?? $defaultHours;
    }

    /**
     * Получить время окончания рабочего дня (business_day_ends_at)
     * Это время, после которого система считает что начался новый рабочий день.
     * По умолчанию 05:00 - т.е. до 5 утра это ещё "вчерашняя" смена.
     *
     * @return int Час окончания рабочего дня (0-23)
     */
    public static function getBusinessDayEndsAt(int $restaurantId = 1): int
    {
        $cacheKey = "general_settings_{$restaurantId}";
        $settings = Cache::get($cacheKey, []);

        // По умолчанию 5:00 - новый день начинается в 5 утра
        return (int) ($settings['business_day_ends_at'] ?? 5);
    }

    /**
     * Получить "рабочую дату" (business date)
     *
     * Приоритет:
     * 1. Если есть открытая кассовая смена (менее 20 часов) → дата открытия смены
     * 2. Fallback: если смена "забыта" (>20ч) или не открыта → business_day_ends_at логика
     *
     * @return array ['date' => string, 'source' => string, 'shift_warning' => bool, 'shift_hours' => int|null]
     */
    public static function getBusinessDateWithDetails(int $restaurantId = 1): array
    {
        $tz = self::getTimezone($restaurantId);
        $now = Carbon::now($tz);

        // Проверяем открытую кассовую смену
        $currentShift = \App\Models\CashShift::getCurrentShift($restaurantId);

        if ($currentShift) {
            $shiftOpenedAt = Carbon::parse($currentShift->opened_at, $tz);
            $hoursOpen = $shiftOpenedAt->diffInHours($now);

            // Если смена открыта менее 20 часов - используем дату открытия смены
            if ($hoursOpen < 20) {
                return [
                    'date' => $shiftOpenedAt->toDateString(),
                    'source' => 'shift',
                    'shift_warning' => false,
                    'shift_hours' => $hoursOpen,
                ];
            }

            // Смена открыта более 20 часов - предупреждение + fallback
            return [
                'date' => self::getBusinessDateByTime($restaurantId),
                'source' => 'time_fallback',
                'shift_warning' => true,
                'shift_hours' => $hoursOpen,
            ];
        }

        // Нет открытой смены - используем time-based логику
        return [
            'date' => self::getBusinessDateByTime($restaurantId),
            'source' => 'time',
            'shift_warning' => false,
            'shift_hours' => null,
        ];
    }

    /**
     * Получить рабочую дату на основе времени (fallback логика)
     */
    public static function getBusinessDateByTime(int $restaurantId = 1): string
    {
        $tz = self::getTimezone($restaurantId);
        $now = Carbon::now($tz);
        $currentHour = $now->hour;

        $businessDayEndsAt = self::getBusinessDayEndsAt($restaurantId);

        // Если сейчас до времени окончания рабочего дня (например до 05:00)
        if ($currentHour < $businessDayEndsAt) {
            return $now->copy()->subDay()->toDateString();
        }

        return $now->toDateString();
    }

    /**
     * Простой метод для получения только даты (для обратной совместимости)
     */
    public static function getBusinessDate(int $restaurantId = 1): string
    {
        return self::getBusinessDateWithDetails($restaurantId)['date'];
    }

    /**
     * Получить Carbon объект для рабочей даты
     */
    public static function businessDate(int $restaurantId = 1): Carbon
    {
        $tz = self::getTimezone($restaurantId);
        return Carbon::parse(self::getBusinessDate($restaurantId), $tz);
    }

    /**
     * Проверить, является ли указанная дата "сегодняшним" рабочим днём
     */
    public static function isBusinessToday(string $date, int $restaurantId = 1): bool
    {
        return self::getBusinessDate($restaurantId) === $date;
    }
}
