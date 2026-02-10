/**
 * Orders Store — активные заказы, оплаченные заказы
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api/index.js';
import type { Order } from '@/shared/types';

const CACHE_TTL = 10000;

export const useOrdersStore = defineStore('pos-orders', () => {
    const lastFetchTimes = ref<Record<string, number>>({});
    const orders = ref<Order[]>([]);
    const activeOrders = shallowRef<Order[]>([]);
    const paidOrders = shallowRef<Order[]>([]);

    const activeOrdersMap = computed(() => {
        const map = new Map<number, Order>();
        activeOrders.value.forEach((order: any) => {
            if (order.table_id) {
                map.set(order.table_id, order);
            }
        });
        return map;
    });

    const isCacheFresh = (key: string): boolean => {
        const lastFetch = lastFetchTimes.value[key] || 0;
        return (Date.now() - lastFetch) < CACHE_TTL;
    };

    const markFetched = (key: string): void => {
        lastFetchTimes.value[key] = Date.now();
    };

    const loadActiveOrders = async (force = false): Promise<void> => {
        if (!force && isCacheFresh('activeOrders')) return;
        activeOrders.value = await api.orders.getActive();
        markFetched('activeOrders');
    };

    const loadPaidOrders = async (force = false): Promise<void> => {
        if (!force && isCacheFresh('paidOrders')) return;
        paidOrders.value = await api.orders.getPaidToday();
        markFetched('paidOrders');
    };

    const getTableOrder = (tableId: number): Order | null => {
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
