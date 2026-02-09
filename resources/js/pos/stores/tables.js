/**
 * Tables & Floor Store — столы, зоны, объекты зала
 */

import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import api from '../api';

export const useTablesStore = defineStore('pos-tables', () => {
    const tables = shallowRef([]);
    const zones = ref([]);
    const floorObjects = ref([]);
    const floorWidth = ref(1200);
    const floorHeight = ref(800);
    const tablesLoading = ref(false);
    const selectedTable = ref(null);
    const selectedZone = ref(null);

    const loadTables = async () => {
        tablesLoading.value = true;
        try {
            tables.value = await api.tables.getAll();
        } finally {
            tablesLoading.value = false;
        }
    };

    const loadZones = async () => {
        zones.value = await api.zones.getAll();
    };

    const updateFloorObjects = (zone) => {
        if (!zone) {
            floorObjects.value = [];
            return;
        }
        const layout = zone.floor_layout || {};
        floorObjects.value = layout.objects || [];
        floorWidth.value = layout.width || 1200;
        floorHeight.value = layout.height || 800;
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
