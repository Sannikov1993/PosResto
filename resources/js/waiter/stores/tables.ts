/**
 * Waiter App - Tables Store
 * Manages zones and tables state
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { tablesApi } from '@/waiter/services';
import type { Zone, Table, TableStatus } from '@/waiter/types';

export const useTablesStore = defineStore('waiter-tables', () => {
  // === State ===
  const zones = ref<Zone[]>([]);
  const tables = ref<Table[]>([]);
  const selectedZoneId = ref<number | null>(null);
  const selectedTableId = ref<number | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const lastFetchTime = ref<number>(0);

  // === Cache Settings ===
  const CACHE_TTL = 30000; // 30 seconds

  // === Getters ===

  /**
   * Currently selected zone
   */
  const selectedZone = computed((): Zone | null => {
    if (!selectedZoneId.value) return null;
    return zones.value.find((z: any) => z.id === selectedZoneId.value) || null;
  });

  /**
   * Currently selected table
   */
  const selectedTable = computed((): Table | null => {
    if (!selectedTableId.value) return null;
    return tables.value.find((t: any) => t.id === selectedTableId.value) || null;
  });

  /**
   * Tables filtered by selected zone
   */
  const filteredTables = computed((): Table[] => {
    if (!selectedZoneId.value) return tables.value;
    return tables.value.filter((t: any) => t.zone_id === selectedZoneId.value);
  });

  /**
   * Free tables count
   */
  const freeTablesCount = computed((): number => {
    return filteredTables.value.filter((t: any) => t.status === 'free').length;
  });

  /**
   * Occupied tables count
   */
  const occupiedTablesCount = computed((): number => {
    return filteredTables.value.filter((t: any) => t.status === 'occupied').length;
  });

  /**
   * Tables grouped by status
   */
  const tablesByStatus = computed(() => {
    const grouped: Record<TableStatus, Table[]> = {
      free: [] as any[],
      occupied: [] as any[],
      reserved: [] as any[],
      bill_requested: [] as any[],
    };

    for (const table of filteredTables.value) {
      if (grouped[table.status]) {
        grouped[table.status].push(table);
      }
    }

    return grouped;
  });

  /**
   * Tables grouped by zone
   */
  const tablesByZone = computed(() => {
    const grouped: Record<number, Table[]> = {};

    for (const table of tables.value) {
      if (!grouped[table.zone_id]) {
        grouped[table.zone_id] = [];
      }
      grouped[table.zone_id].push(table);
    }

    return grouped;
  });

  /**
   * Zone statistics
   */
  const zoneStats = computed(() => {
    return zones.value.map((zone: any) => {
      const zoneTables = tablesByZone.value[zone.id] || [];
      return {
        ...zone,
        total: zoneTables.length,
        free: zoneTables.filter((t: any) => t.status === 'free').length,
        occupied: zoneTables.filter((t: any) => t.status === 'occupied').length,
        reserved: zoneTables.filter((t: any) => t.status === 'reserved').length,
      };
    });
  });

  // === Actions ===

  /**
   * Fetch zones from API
   */
  async function fetchZones(): Promise<void> {
    try {
      const response = await tablesApi.getZones();
      if (response.success) {
        zones.value = response.data;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки зон';
    }
  }

  /**
   * Fetch tables from API
   */
  async function fetchTables(): Promise<void> {
    try {
      const response = await tablesApi.getTables();
      if (response.success) {
        tables.value = response.data;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки столов';
    }
  }

  /**
   * Fetch all data (zones + tables)
   */
  async function fetchAll(force = false): Promise<void> {
    // Check cache
    const now = Date.now();
    if (!force && lastFetchTime.value && (now - lastFetchTime.value) < CACHE_TTL) {
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const { zones: z, tables: t } = await tablesApi.getAll();
      zones.value = z;
      tables.value = t;
      lastFetchTime.value = now;
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки данных';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Refresh table data
   */
  async function refreshTable(tableId: number): Promise<Table | null> {
    try {
      const response = await tablesApi.getTable(tableId);
      if (response.success) {
        updateTable(response.data);
        return response.data;
      }
    } catch (e: any) {
      error.value = e.message;
    }
    return null;
  }

  /**
   * Open table (create order)
   */
  async function openTable(tableId: number, guestsCount: number): Promise<boolean> {
    isLoading.value = true;

    try {
      const response = await tablesApi.openTable(tableId, { guests_count: guestsCount });
      if (response.success) {
        // Update table in state
        const { table, order } = response.data;
        updateTable({
          ...table,
          status: 'occupied',
          current_order: order,
          current_order_id: order.id,
        });
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка открытия стола';
    } finally {
      isLoading.value = false;
    }

    return false;
  }

  /**
   * Close table
   */
  async function closeTable(tableId: number): Promise<boolean> {
    try {
      const response = await tablesApi.closeTable(tableId);
      if (response.success) {
        updateTable({
          ...response.data,
          status: 'free',
          current_order: undefined,
          current_order_id: undefined,
        });
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка закрытия стола';
    }
    return false;
  }

  /**
   * Request bill for table
   */
  async function requestBill(tableId: number): Promise<boolean> {
    try {
      const response = await tablesApi.requestBill(tableId);
      if (response.success) {
        updateTable(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка запроса счёта';
    }
    return false;
  }

  /**
   * Select zone
   */
  function selectZone(zoneId: number | null): void {
    selectedZoneId.value = zoneId;
  }

  /**
   * Select table
   */
  function selectTable(tableId: number | null): void {
    selectedTableId.value = tableId;
  }

  /**
   * Update table in state
   */
  function updateTable(updatedTable: Table): void {
    const index = tables.value.findIndex((t: any) => t.id === updatedTable.id);
    if (index !== -1) {
      tables.value[index] = updatedTable;
    }
  }

  /**
   * Get table by ID
   */
  function getTableById(tableId: number): Table | undefined {
    return tables.value.find((t: any) => t.id === tableId);
  }

  /**
   * Get zone by ID
   */
  function getZoneById(zoneId: number): Zone | undefined {
    return zones.value.find((z: any) => z.id === zoneId);
  }

  /**
   * Clear error
   */
  function clearError(): void {
    error.value = null;
  }

  /**
   * Reset store
   */
  function $reset(): void {
    zones.value = [];
    tables.value = [];
    selectedZoneId.value = null;
    selectedTableId.value = null;
    isLoading.value = false;
    error.value = null;
    lastFetchTime.value = 0;
  }

  return {
    // State
    zones,
    tables,
    selectedZoneId,
    selectedTableId,
    isLoading,
    error,

    // Getters
    selectedZone,
    selectedTable,
    filteredTables,
    freeTablesCount,
    occupiedTablesCount,
    tablesByStatus,
    tablesByZone,
    zoneStats,

    // Actions
    fetchZones,
    fetchTables,
    fetchAll,
    refreshTable,
    openTable,
    closeTable,
    requestBill,
    selectZone,
    selectTable,
    updateTable,
    getTableById,
    getZoneById,
    clearError,
    $reset,
  };
});
