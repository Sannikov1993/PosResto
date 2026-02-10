/**
 * Reservations Store — бронирования
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Reservation } from '@/shared/types';

const log = createLogger('POS:Reservations');
const CACHE_TTL = 10000;

export const useReservationsStore = defineStore('pos-reservations', () => {
    const lastFetchTimes = ref<Record<string, number>>({});
    const reservations = shallowRef<Reservation[]>([]);

    const getLocalDateString = (): string => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const floorDate = ref(getLocalDateString());

    const tableReservationsMap = computed(() => {
        const map = new Map<number, Reservation[]>();
        reservations.value
            .filter((r: any) => ['pending', 'confirmed'].includes(r.status))
            .forEach((r: any) => {
                if (!map.has(r.table_id)) {
                    map.set(r.table_id, []);
                }
                map.get(r.table_id)!.push(r);
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

    const loadReservations = async (date: string, force = false): Promise<void> => {
        if (!force && isCacheFresh('reservations')) return;
        try {
            reservations.value = await api.reservations.getByDate(date);
            markFetched('reservations');
        } catch (error: any) {
            log.error('Error loading reservations:', error);
            reservations.value = [];
        }
    };

    const setFloorDate = async (date: string): Promise<void> => {
        floorDate.value = date;
        await loadReservations(date);
    };

    const getTableReservations = (tableId: number): Reservation[] => {
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
