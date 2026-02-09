/**
 * Delivery Store — заказы доставки, курьеры
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api';

const CACHE_TTL = 10000;

export const useDeliveryStore = defineStore('pos-delivery', () => {
    const lastFetchTimes = ref({});
    const deliveryOrders = shallowRef([]);
    const couriers = shallowRef([]);

    const pendingDeliveryCount = computed(() => {
        return deliveryOrders.value.filter(o => o.delivery_status === 'pending').length;
    });

    const isCacheFresh = (key) => {
        const lastFetch = lastFetchTimes.value[key] || 0;
        return (Date.now() - lastFetch) < CACHE_TTL;
    };

    const markFetched = (key) => {
        lastFetchTimes.value[key] = Date.now();
    };

    const loadDeliveryOrders = async (force = false) => {
        if (!force && isCacheFresh('deliveryOrders')) return;
        deliveryOrders.value = await api.orders.getDelivery();
        markFetched('deliveryOrders');
    };

    const loadCouriers = async () => {
        couriers.value = await api.couriers.getAll();
    };

    return {
        deliveryOrders,
        couriers,
        pendingDeliveryCount,
        loadDeliveryOrders,
        loadCouriers,
    };
});
