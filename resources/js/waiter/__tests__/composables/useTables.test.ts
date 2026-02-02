/**
 * useTables Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock stores
vi.mock('@/waiter/stores/tables', () => ({
  useTablesStore: vi.fn(() => ({
    zones: [],
    tables: [],
    selectedZoneId: null,
    selectedTableId: null,
    selectedZone: null,
    selectedTable: null,
    filteredTables: [],
    freeTablesCount: 0,
    occupiedTablesCount: 0,
    tablesByStatus: { free: [], occupied: [], reserved: [], bill_requested: [] },
    tablesByZone: {},
    zoneStats: [],
    isLoading: false,
    error: null,
    fetchAll: vi.fn(),
    selectZone: vi.fn(),
    selectTable: vi.fn(),
    openTable: vi.fn(),
    closeTable: vi.fn(),
    requestBill: vi.fn(),
    getTableById: vi.fn(),
    getZoneById: vi.fn(),
    clearError: vi.fn(),
  })),
}));

vi.mock('@/waiter/stores/orders', () => ({
  useOrdersStore: vi.fn(() => ({
    setCurrentOrder: vi.fn(),
  })),
}));

vi.mock('@/waiter/stores/ui', () => ({
  useUiStore: vi.fn(() => ({
    showSuccess: vi.fn(),
    showError: vi.fn(),
    showWarning: vi.fn(),
    goToTableOrder: vi.fn(),
    goToTables: vi.fn(),
  })),
}));

import { useTables } from '@/waiter/composables/useTables';
import { useTablesStore } from '@/waiter/stores/tables';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useUiStore } from '@/waiter/stores/ui';

describe('useTables Composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('selectTable', () => {
    it('should select table and navigate', async () => {
      const tablesStore = useTablesStore();
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      const { selectTable } = useTables();

      const table = { id: 1, number: '1', current_order: { id: 100, total: 500 } } as any;
      await selectTable(table);

      expect(tablesStore.selectTable).toHaveBeenCalledWith(1);
      expect(ordersStore.setCurrentOrder).toHaveBeenCalledWith(table.current_order);
      expect(uiStore.goToTableOrder).toHaveBeenCalled();
    });

    it('should set null order for table without order', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      const { selectTable } = useTables();

      const table = { id: 1, number: '1', current_order: null } as any;
      await selectTable(table);

      expect(ordersStore.setCurrentOrder).toHaveBeenCalledWith(null);
      expect(uiStore.goToTableOrder).toHaveBeenCalled();
    });
  });

  describe('openTable', () => {
    it('should open table successfully', async () => {
      const tablesStore = useTablesStore();
      const uiStore = useUiStore();

      vi.mocked(tablesStore.openTable).mockResolvedValue(true);
      vi.mocked(tablesStore.getTableById).mockReturnValue({
        id: 1,
        number: '5',
        current_order: { id: 100 },
      } as any);

      const { openTable } = useTables();
      const result = await openTable(1, 2);

      expect(result).toBe(true);
      expect(tablesStore.openTable).toHaveBeenCalledWith(1, 2);
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Стол 5 открыт');
      expect(uiStore.goToTableOrder).toHaveBeenCalled();
    });

    it('should show error on failure', async () => {
      const tablesStore = useTablesStore();
      const uiStore = useUiStore();

      vi.mocked(tablesStore.openTable).mockResolvedValue(false);
      Object.defineProperty(tablesStore, 'error', { value: 'Table busy', writable: true });

      const { openTable } = useTables();
      const result = await openTable(1, 2);

      expect(result).toBe(false);
      expect(uiStore.showError).toHaveBeenCalledWith('Table busy');
    });
  });

  describe('closeTable', () => {
    it('should close table successfully', async () => {
      const tablesStore = useTablesStore();
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      vi.mocked(tablesStore.closeTable).mockResolvedValue(true);
      vi.mocked(tablesStore.getTableById).mockReturnValue({ id: 1, number: '5' } as any);

      const { closeTable } = useTables();
      const result = await closeTable(1);

      expect(result).toBe(true);
      expect(tablesStore.closeTable).toHaveBeenCalledWith(1);
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Стол 5 закрыт');
      expect(ordersStore.setCurrentOrder).toHaveBeenCalledWith(null);
      expect(uiStore.goToTables).toHaveBeenCalled();
    });
  });

  describe('requestBill', () => {
    it('should request bill successfully', async () => {
      const tablesStore = useTablesStore();
      const uiStore = useUiStore();

      vi.mocked(tablesStore.requestBill).mockResolvedValue(true);

      const { requestBill } = useTables();
      const result = await requestBill(1);

      expect(result).toBe(true);
      expect(tablesStore.requestBill).toHaveBeenCalledWith(1);
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Запрос на счёт отправлен');
    });
  });

  describe('getStatusColor', () => {
    it('should return correct color for each status', () => {
      const { getStatusColor } = useTables();

      expect(getStatusColor('free')).toContain('green');
      expect(getStatusColor('occupied')).toContain('orange');
      expect(getStatusColor('reserved')).toContain('blue');
      expect(getStatusColor('bill_requested')).toContain('red');
    });
  });

  describe('getStatusLabel', () => {
    it('should return correct label for each status', () => {
      const { getStatusLabel } = useTables();

      expect(getStatusLabel('free')).toBe('Свободен');
      expect(getStatusLabel('occupied')).toBe('Занят');
      expect(getStatusLabel('reserved')).toBe('Бронь');
      expect(getStatusLabel('bill_requested')).toBe('Счёт');
    });
  });

  describe('computed tables', () => {
    it('freeTables should filter free tables', () => {
      const tablesStore = useTablesStore();

      Object.defineProperty(tablesStore, 'filteredTables', {
        value: [
          { id: 1, status: 'free' },
          { id: 2, status: 'occupied' },
          { id: 3, status: 'free' },
        ],
        writable: true,
      });

      const { freeTables } = useTables();

      expect(freeTables.value).toHaveLength(2);
      expect(freeTables.value.every(t => t.status === 'free')).toBe(true);
    });

    it('occupiedTables should filter occupied tables', () => {
      const tablesStore = useTablesStore();

      Object.defineProperty(tablesStore, 'filteredTables', {
        value: [
          { id: 1, status: 'free' },
          { id: 2, status: 'occupied' },
          { id: 3, status: 'occupied' },
        ],
        writable: true,
      });

      const { occupiedTables } = useTables();

      expect(occupiedTables.value).toHaveLength(2);
    });
  });
});
