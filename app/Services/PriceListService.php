<?php

namespace App\Services;

use App\Models\Dish;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PriceListService
{
    /**
     * Получить цену блюда из прайс-листа или базовую цену
     *
     * БЕЗОПАСНОСТЬ: проверяет что прайс-лист принадлежит тому же ресторану, что и блюдо
     */
    public function resolvePrice(Dish $dish, ?int $priceListId): float
    {
        if (!$priceListId) {
            return (float) $dish->price;
        }

        // БЕЗОПАСНОСТЬ: проверяем что прайс-лист принадлежит ресторану блюда
        $priceList = PriceList::forRestaurant($dish->restaurant_id)->find($priceListId);
        if (!$priceList) {
            Log::warning('PriceListService: price_list does not belong to dish restaurant', [
                'dish_id' => $dish->id,
                'dish_restaurant_id' => $dish->restaurant_id,
                'price_list_id' => $priceListId,
            ]);
            return (float) $dish->price;
        }

        $item = PriceListItem::forRestaurant($dish->restaurant_id)
            ->where('price_list_id', $priceListId)
            ->where('dish_id', $dish->id)
            ->first();

        return $item ? (float) $item->price : (float) $dish->price;
    }

    /**
     * Batch-резолв цен для коллекции блюд (1 SQL запрос)
     *
     * БЕЗОПАСНОСТЬ: проверяет что прайс-лист принадлежит ресторану блюд
     *
     * @param Collection $dishes Коллекция блюд (все должны быть из одного ресторана)
     * @param int|null $priceListId ID прайс-листа
     */
    public function resolvePrices(Collection $dishes, ?int $priceListId): Collection
    {
        if (!$priceListId || $dishes->isEmpty()) {
            return $dishes;
        }

        // Получаем restaurant_id из первого блюда
        $restaurantId = $dishes->first()->restaurant_id ?? null;
        if (!$restaurantId) {
            return $dishes;
        }

        // БЕЗОПАСНОСТЬ: проверяем что прайс-лист принадлежит ресторану
        $priceList = PriceList::forRestaurant($restaurantId)->find($priceListId);
        if (!$priceList) {
            Log::warning('PriceListService: price_list does not belong to restaurant', [
                'restaurant_id' => $restaurantId,
                'price_list_id' => $priceListId,
            ]);
            return $dishes;
        }

        $dishIds = $dishes->pluck('id')->toArray();

        $priceMap = PriceListItem::forRestaurant($restaurantId)
            ->where('price_list_id', $priceListId)
            ->whereIn('dish_id', $dishIds)
            ->pluck('price', 'dish_id');

        return $dishes->map(function ($dish) use ($priceMap) {
            $dish->resolved_price = isset($priceMap[$dish->id])
                ? (float) $priceMap[$dish->id]
                : (float) $dish->price;
            return $dish;
        });
    }

    /**
     * Получить прайс-лист по умолчанию для ресторана
     */
    public function getDefaultPriceList(int $restaurantId): ?PriceList
    {
        return PriceList::forRestaurant($restaurantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
