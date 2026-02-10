/**
 * Menu Store — категории, блюда, прайс-листы, стоп-лист
 */

import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Category, Dish, PriceList, StopListItem, Customer } from '@/shared/types';

const log = createLogger('POS:Menu');

export const useMenuStore = defineStore('pos-menu', () => {
    const menuCategories = shallowRef<Category[]>([]);
    const menuDishes = shallowRef<Dish[]>([]);
    const availablePriceLists = ref<PriceList[]>([]);
    const selectedPriceListId = ref<number | null>(null);
    const stopList = shallowRef<StopListItem[]>([]);
    const stopListDishIds = ref<Set<number>>(new Set());
    const customers = shallowRef<Customer[]>([]);

    const loadMenu = async (): Promise<void> => {
        const [categories, dishes] = await Promise.all([
            api.menu.getCategories(),
            api.menu.getDishes(null, selectedPriceListId.value)
        ]);
        menuCategories.value = categories;
        menuDishes.value = dishes;
    };

    const loadPriceLists = async (): Promise<void> => {
        try {
            const result = await api.priceLists.getAll();
            availablePriceLists.value = (Array.isArray(result) ? result : []).filter((pl: any) => pl.is_active);
        } catch (e: any) {
            log.warn('Failed to load price lists:', e);
            availablePriceLists.value = [];
        }
    };

    const setPriceList = async (priceListId: number | null): Promise<void> => {
        selectedPriceListId.value = priceListId;
        await loadMenu();
    };

    const loadStopList = async (): Promise<void> => {
        stopList.value = await api.stopList.getAll();
        stopListDishIds.value = new Set(stopList.value.map((item: any) => item.dish_id));
    };

    const loadCustomers = async (): Promise<void> => {
        customers.value = await api.customers.getAll();
    };

    return {
        menuCategories,
        menuDishes,
        availablePriceLists,
        selectedPriceListId,
        stopList,
        stopListDishIds,
        customers,
        loadMenu,
        loadPriceLists,
        setPriceList,
        loadStopList,
        loadCustomers,
    };
});
