<?php

namespace App\Services;

use App\Models\Dish;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Support\Collection;

class PriceListService
{
    /**
     * Получить цену блюда из прайс-листа или базовую цену
     */
    public function resolvePrice(Dish $dish, ?int $priceListId): float
    {
        if (!$priceListId) {
            return (float) $dish->price;
        }

        $item = PriceListItem::where('price_list_id', $priceListId)
            ->where('dish_id', $dish->id)
            ->first();

        return $item ? (float) $item->price : (float) $dish->price;
    }

    /**
     * Batch-резолв цен для коллекции блюд (1 SQL запрос)
     */
    public function resolvePrices(Collection $dishes, ?int $priceListId): Collection
    {
        if (!$priceListId || $dishes->isEmpty()) {
            return $dishes;
        }

        $dishIds = $dishes->pluck('id')->toArray();

        $priceMap = PriceListItem::where('price_list_id', $priceListId)
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
        return PriceList::where('restaurant_id', $restaurantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
