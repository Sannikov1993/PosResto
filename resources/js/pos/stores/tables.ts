/**
 * Tables & Floor Store — столы, зоны, объекты зала
 */

import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import api from '../api/index.js';
import { FLOOR_WIDTH, FLOOR_HEIGHT } from '../../shared/config/uiConfig.js';
import type { Table, Zone } from '@/shared/types';

interface FloorObject {
    [key: string]: unknown;
}

interface FloorLayout {
    objects?: FloorObject[];
    width?: number;
    height?: number;
}

export const useTablesStore = defineStore('pos-tables', () => {
    const tables = shallowRef<Table[]>([]);
    const zones = ref<Zone[]>([]);
    const floorObjects = ref<FloorObject[]>([]);
    const floorWidth = ref(FLOOR_WIDTH);
    const floorHeight = ref(FLOOR_HEIGHT);
    const tablesLoading = ref(false);
    const selectedTable = ref<Table | null>(null);
    const selectedZone = ref<Zone | null>(null);

    const loadTables = async (): Promise<void> => {
        tablesLoading.value = true;
        try {
            tables.value = await api.tables.getAll();
        } finally {
            tablesLoading.value = false;
        }
    };

    const loadZones = async (): Promise<void> => {
        zones.value = await api.zones.getAll();
    };

    const updateFloorObjects = (zone: Zone | null): void => {
        if (!zone) {
            floorObjects.value = [];
            return;
        }
        const layout = ((zone as Record<string, any>).floor_layout || {}) as FloorLayout;
        floorObjects.value = layout.objects || [];
        floorWidth.value = layout.width || FLOOR_WIDTH;
        floorHeight.value = layout.height || FLOOR_HEIGHT;
    };

    return {
        tables,
        zones,
        floorObjects,
        floorWidth,
        floorHeight,
        tablesLoading,
        selectedTable,
        selectedZone,
        loadTables,
        loadZones,
        updateFloorObjects,
    };
});
