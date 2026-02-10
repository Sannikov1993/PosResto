/**
 * Shifts Store — смены, текущая смена
 */

import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import api from '../api/index.js';
import type { CashShift } from '@/shared/types';

const CACHE_TTL = 10000;

export const useShiftsStore = defineStore('pos-shifts', () => {
    const lastFetchTimes = ref<Record<string, number>>({});
    const shifts = shallowRef<CashShift[]>([]);
    const currentShift = ref<CashShift | null>(null);
    const shiftsLoading = ref(false);
    const shiftsVersion = ref(0);

    const isCacheFresh = (key: string): boolean => {
        const lastFetch = lastFetchTimes.value[key] || 0;
        return (Date.now() - lastFetch) < CACHE_TTL;
    };

    const markFetched = (key: string): void => {
        lastFetchTimes.value[key] = Date.now();
    };

    const loadShifts = async (force = false): Promise<void> => {
        if (!force && isCacheFresh('shifts')) return;
        shiftsLoading.value = true;
        try {
            shifts.value = await api.shifts.getAll();
            shiftsVersion.value++;
            markFetched('shifts');
        } finally {
            shiftsLoading.value = false;
        }
    };

    const loadCurrentShift = async (): Promise<void> => {
        currentShift.value = await api.shifts.getCurrent();
    };

    return {
        shifts,
        currentShift,
        shiftsLoading,
        shiftsVersion,
        loadShifts,
        loadCurrentShift,
    };
});
