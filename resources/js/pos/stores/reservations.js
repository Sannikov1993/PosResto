/**
 * Reservations Store — бронирования
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:Reservations');
const CACHE_TTL = 10000;

export const useReservationsStore = defineStore('pos-reservations', () => {
    const lastFetchTimes = ref({});
    const reservations = shallowRef([]);

    const getLocalDateString = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const floorDate = ref(getLocalDateString());

    const tableReservationsMap = computed(() => {
        const map = new Map();
        reservations.value
            .filter(r => ['pending', 'confirmed'].includes(r.status))
            .forEach(r => {
                if (!map.has(r.table_id)) {
                    map.set(r.table_id, []);
                }
                map.get(r.table_id).push(r);
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

    const loadReservations = async (date, force = false) => {
        if (!force && isCacheFresh('reservations')) return;
        try {
            reservations.value = await api.reservations.getByDate(date);
            markFetched('reservations');
        } catch (error) {
            log.error('Error loading reservations:', error);
            reservations.value = [];
        }
    };

    const setFloorDate = async (date) => {
        floorDate.value = date;
        await loadReservations(date);
    };

    const getTableReservations = (tableId) => {
        return tableReservationsMap.value.get(tableId) || [];
    };

    return {
        reservations,
        floorDate,
        tableReservationsMap,
        loadReservations,
        setFloorDate,
        getTableReservations,
    };
});
