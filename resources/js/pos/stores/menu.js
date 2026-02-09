/**
 * Menu Store — категории, блюда, прайс-листы, стоп-лист
 */

import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import api from '../api';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:Menu');

export const useMenuStore = defineStore('pos-menu', () => {
    const menuCategories = shallowRef([]);
    const menuDishes = shallowRef([]);
    const availablePriceLists = ref([]);
    const selectedPriceListId = ref(null);
    const stopList = shallowRef([]);
    const stopListDishIds = ref(new Set());
    const customers = shallowRef([]);

    const loadMenu = async () => {
        const [categories, dishes] = await Promise.all([
            api.menu.getCategories(),
            api.menu.getDishes(null, selectedPriceListId.value)
        ]);
        menuCategories.value = categories;
        menuDishes.value = dishes;
    };

    const loadPriceLists = async () => {
        try {
            const result = await api.priceLists.getAll();
            availablePriceLists.value = (Array.isArray(result) ? result : []).filter(pl => pl.is_active);
        } catch (e) {
            log.warn('Failed to load price lists:', e);
            availablePriceLists.value = [];
        }
    };

    const setPriceList = async (priceListId) => {
        selectedPriceListId.value = priceListId;
        await loadMenu();
    };

    const loadStopList = async () => {
        stopList.value = await api.stopList.getAll();
        stopListDishIds.value = new Set(stopList.value.map(item => item.dish_id));
    };

    const loadCustomers = async () => {
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
