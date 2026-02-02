/**
 * Waiter App - useTables Composable
 * Tables and zones logic
 */

import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useTablesStore } from '@/waiter/stores/tables';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useUiStore } from '@/waiter/stores/ui';
import type { Table, Zone, TableStatus } from '@/waiter/types';

export function useTables() {
  const tablesStore = useTablesStore();
  const ordersStore = useOrdersStore();
  const uiStore = useUiStore();

  const {
    zones,
    tables,
    selectedZoneId,
    selectedTableId,
    selectedZone,
    selectedTable,
    filteredTables,
    freeTablesCount,
    occupiedTablesCount,
    tablesByStatus,
    tablesByZone,
    zoneStats,
    isLoading,
    error,
  } = storeToRefs(tablesStore);

  // === Computed ===

  /**
   * Free tables
   */
  const freeTables = computed((): Table[] => {
    return filteredTables.value.filter(t => t.status === 'free');
  });

  /**
   * Occupied tables
   */
  const occupiedTables = computed((): Table[] => {
    return filteredTables.value.filter(t => t.status === 'occupied');
  });

  /**
   * Tables with bill requested
   */
  const billRequestedTables = computed((): Table[] => {
    return filteredTables.value.filter(t => t.status === 'bill_requested');
  });

  /**
   * Reserved tables
   */
  const reservedTables = computed((): Table[] => {
    return filteredTables.value.filter(t => t.status === 'reserved');
  });

  // === Methods ===

  /**
   * Fetch all zones and tables
   */
  async function fetchAll(force = false): Promise<void> {
    await tablesStore.fetchAll(force);
  }

  /**
   * Select zone
   */
  function selectZone(zoneId: number | null): void {
    tablesStore.selectZone(zoneId);
  }

  /**
   * Select table and navigate to order
   */
  async function selectTable(table: Table): Promise<void> {
    tablesStore.selectTable(table.id);

    if (table.current_order) {
      ordersStore.setCurrentOrder(table.current_order);
    } else {
      ordersStore.setCurrentOrder(null);
    }

    uiStore.goToTableOrder();
  }

  /**
   * Open table (create order)
   */
  async function openTable(tableId: number, guestsCount: number): Promise<boolean> {
    const success = await tablesStore.openTable(tableId, guestsCount);

    if (success) {
      const table = tablesStore.getTableById(tableId);
      if (table) {
        uiStore.showSuccess(`Стол ${table.number} открыт`);

        // Set current order from updated table
        if (table.current_order) {
          ordersStore.setCurrentOrder(table.current_order);
        }

        uiStore.goToTableOrder();
      }
    } else {
      uiStore.showError(error.value || 'Ошибка открытия стола');
    }

    return success;
  }

  /**
   * Close table
   */
  async function closeTable(tableId: number): Promise<boolean> {
    const success = await tablesStore.closeTable(tableId);

    if (success) {
      const table = tablesStore.getTableById(tableId);
      uiStore.showSuccess(`Стол ${table?.number || tableId} закрыт`);
      ordersStore.setCurrentOrder(null);
      uiStore.goToTables();
    } else {
      uiStore.showError(error.value || 'Ошибка закрытия стола');
    }

    return success;
  }

  /**
   * Request bill for table
   */
  async function requestBill(tableId: number): Promise<boolean> {
    const success = await tablesStore.requestBill(tableId);

    if (success) {
      uiStore.showSuccess('Запрос на счёт отправлен');
    } else {
      uiStore.showError(error.value || 'Ошибка запроса счёта');
    }

    return success;
  }

  /**
   * Get table by ID
   */
  function getTableById(tableId: number): Table | undefined {
    return tablesStore.getTableById(tableId);
  }

  /**
   * Get zone by ID
   */
  function getZoneById(zoneId: number): Zone | undefined {
    return tablesStore.getZoneById(zoneId);
  }

  /**
   * Get status color class
   */
  function getStatusColor(status: TableStatus): string {
    switch (status) {
      case 'free':
        return 'bg-green-100 border-green-300 text-green-800';
      case 'occupied':
        return 'bg-orange-100 border-orange-300 text-orange-800';
      case 'reserved':
        return 'bg-blue-100 border-blue-300 text-blue-800';
      case 'bill_requested':
        return 'bg-red-100 border-red-300 text-red-800';
      default:
        return 'bg-gray-100 border-gray-300 text-gray-800';
    }
  }

  /**
   * Get status label
   */
  function getStatusLabel(status: TableStatus): string {
    switch (status) {
      case 'free':
        return 'Свободен';
      case 'occupied':
        return 'Занят';
      case 'reserved':
        return 'Бронь';
      case 'bill_requested':
        return 'Счёт';
      default:
        return '';
    }
  }

  /**
   * Clear error
   */
  function clearError(): void {
    tablesStore.clearError();
  }

  return {
    // State
    zones,
    tables,
    selectedZoneId,
    selectedTableId,
    selectedZone,
    selectedTable,
    filteredTables,
    freeTablesCount,
    occupiedTablesCount,
    tablesByStatus,
    tablesByZone,
    zoneStats,
    isLoading,
    error,

    // Computed
    freeTables,
    occupiedTables,
    billRequestedTables,
    reservedTables,

    // Methods
    fetchAll,
    selectZone,
    selectTable,
    openTable,
    closeTable,
    requestBill,
    getTableById,
    getZoneById,
    getStatusColor,
    getStatusLabel,
    clearError,
  };
}
