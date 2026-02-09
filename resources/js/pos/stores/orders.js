/**
 * Orders Store — активные заказы, оплаченные заказы
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api';

const CACHE_TTL = 10000;

export const useOrdersStore = defineStore('pos-orders', () => {
    const lastFetchTimes = ref({});
    const orders = ref([]);
    const activeOrders = shallowRef([]);
    const paidOrders = shallowRef([]);

    const activeOrdersMap = computed(() => {
        const map = new Map();
        activeOrders.value.forEach(order => {
            if (order.table_id) {
                map.set(order.table_id, order);
            }
        });
        return map;
    });

    const isCacheFresh = (key) => {
        const lastFetch = lastFetchTimes.value[key] || 0;
        return (Date.now() - lastFetch) < CACHE_TTL;
    };

    const markFetched = (key) => {
        lastFetchTimes.value[key] = Date.now();
    };

    const loadActiveOrders = async (force = false) => {
        if (!force && isCacheFresh('activeOrders')) return;
        activeOrders.value = await api.orders.getActive();
        markFetched('activeOrders');
    };

    const loadPaidOrders = async (force = false) => {
        if (!force && isCacheFresh('paidOrders')) return;
        paidOrders.value = await api.orders.getPaidToday();
        markFetched('paidOrders');
    };

    const getTableOrder = (tableId) => {
        return activeOrdersMap.value.get(tableId) || null;
    };

    return {
        orders,
        activeOrders,
        paidOrders,
        activeOrdersMap,
        loadActiveOrders,
        loadPaidOrders,
        getTableOrder,
    };
});
