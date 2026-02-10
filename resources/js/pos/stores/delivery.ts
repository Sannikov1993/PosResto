/**
 * Delivery Store — заказы доставки, курьеры
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api/index.js';
import type { DeliveryOrder, User } from '@/shared/types';

const CACHE_TTL = 10000;

export const useDeliveryStore = defineStore('pos-delivery', () => {
    const lastFetchTimes = ref<Record<string, number>>({});
    const deliveryOrders = shallowRef<DeliveryOrder[]>([]);
    const couriers = shallowRef<User[]>([]);

    const pendingDeliveryCount = computed(() => {
        return deliveryOrders.value.filter((o: any) => o.delivery_status === 'pending').length;
    });

    const isCacheFresh = (key: string): boolean => {
        const lastFetch = lastFetchTimes.value[key] || 0;
        return (Date.now() - lastFetch) < CACHE_TTL;
    };

    const markFetched = (key: string): void => {
        lastFetchTimes.value[key] = Date.now();
    };

    const loadDeliveryOrders = async (force = false): Promise<void> => {
        if (!force && isCacheFresh('deliveryOrders')) return;
        deliveryOrders.value = await api.orders.getDelivery() as any;
        markFetched('deliveryOrders');
    };

    const loadCouriers = async (): Promise<void> => {
        couriers.value = await api.couriers.getAll() as User[];
    };

    return {
        deliveryOrders,
        couriers,
        pendingDeliveryCount,
        loadDeliveryOrders,
        loadCouriers,
    };
});
