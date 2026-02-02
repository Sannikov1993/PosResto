/**
 * Tables Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useTablesStore } from '@/waiter/stores/tables';

// Mock the tables API
vi.mock('@/waiter/services', () => ({
  tablesApi: {
    getZones: vi.fn(),
    getTables: vi.fn(),
    getTable: vi.fn(),
    getAll: vi.fn(),
    openTable: vi.fn(),
    closeTable: vi.fn(),
    requestBill: vi.fn(),
  },
}));

import { tablesApi } from '@/waiter/services';

describe('Tables Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useTablesStore();

      expect(store.zones).toEqual([]);
      expect(store.tables).toEqual([]);
      expect(store.selectedZoneId).toBeNull();
      expect(store.selectedTableId).toBeNull();
      expect(store.isLoading).toBe(false);
      expect(store.error).toBeNull();
    });
  });

  describe('fetchAll', () => {
    it('should fetch zones and tables', async () => {
      const mockData = {
        zones: [
          { id: 1, name: 'Зал 1', sort_order: 1 },
          { id: 2, name: 'Терраса', sort_order: 2 },
        ],
        tables: [
          { id: 1, number: '1', zone_id: 1, status: 'free', seats: 4 },
          { id: 2, number: '2', zone_id: 1, status: 'occupied', seats: 2 },
          { id: 3, number: '3', zone_id: 2, status: 'free', seats: 6 },
        ],
      };

      vi.mocked(tablesApi.getAll).mockResolvedValue(mockData);

      const store = useTablesStore();
      await store.fetchAll();

      expect(store.zones).toEqual(mockData.zones);
      expect(store.tables).toEqual(mockData.tables);
      expect(store.isLoading).toBe(false);
    });

    it('should handle fetch error', async () => {
      vi.mocked(tablesApi.getAll).mockRejectedValue(new Error('Network error'));

      const store = useTablesStore();
      await store.fetchAll();

      expect(store.error).toBe('Network error');
      expect(store.isLoading).toBe(false);
    });

    it('should use cache if not expired', async () => {
      vi.mocked(tablesApi.getAll).mockResolvedValue({ zones: [], tables: [] });

      const store = useTablesStore();

      // First fetch
      await store.fetchAll();
      expect(tablesApi.getAll).toHaveBeenCalledTimes(1);

      // Second fetch (should use cache)
      await store.fetchAll();
      expect(tablesApi.getAll).toHaveBeenCalledTimes(1);

      // Force fetch
      await store.fetchAll(true);
      expect(tablesApi.getAll).toHaveBeenCalledTimes(2);
    });
  });

  describe('Getters', () => {
    it('selectedZone should return zone by selectedZoneId', () => {
      const store = useTablesStore();
      store.zones = [
        { id: 1, name: 'Зал 1', sort_order: 1 },
        { id: 2, name: 'Терраса', sort_order: 2 },
      ] as any;
      store.selectedZoneId = 2;

      expect(store.selectedZone?.name).toBe('Терраса');
    });

    it('selectedTable should return table by selectedTableId', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, number: '1', zone_id: 1 },
        { id: 2, number: '2', zone_id: 1 },
      ] as any;
      store.selectedTableId = 2;

      expect(store.selectedTable?.number).toBe('2');
    });

    it('filteredTables should filter by selected zone', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, number: '1', zone_id: 1 },
        { id: 2, number: '2', zone_id: 1 },
        { id: 3, number: '3', zone_id: 2 },
      ] as any;

      // No zone selected - show all
      expect(store.filteredTables).toHaveLength(3);

      // Zone 1 selected
      store.selectedZoneId = 1;
      expect(store.filteredTables).toHaveLength(2);
      expect(store.filteredTables.every(t => t.zone_id === 1)).toBe(true);
    });

    it('freeTablesCount should count free tables', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, status: 'free', zone_id: 1 },
        { id: 2, status: 'occupied', zone_id: 1 },
        { id: 3, status: 'free', zone_id: 1 },
      ] as any;

      expect(store.freeTablesCount).toBe(2);
    });

    it('occupiedTablesCount should count occupied tables', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, status: 'free', zone_id: 1 },
        { id: 2, status: 'occupied', zone_id: 1 },
        { id: 3, status: 'occupied', zone_id: 1 },
      ] as any;

      expect(store.occupiedTablesCount).toBe(2);
    });

    it('tablesByZone should group tables by zone_id', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, zone_id: 1 },
        { id: 2, zone_id: 1 },
        { id: 3, zone_id: 2 },
      ] as any;

      expect(store.tablesByZone[1]).toHaveLength(2);
      expect(store.tablesByZone[2]).toHaveLength(1);
    });

    it('tablesByStatus should group tables by status', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, status: 'free', zone_id: 1 },
        { id: 2, status: 'occupied', zone_id: 1 },
        { id: 3, status: 'free', zone_id: 1 },
        { id: 4, status: 'reserved', zone_id: 1 },
      ] as any;

      expect(store.tablesByStatus.free).toHaveLength(2);
      expect(store.tablesByStatus.occupied).toHaveLength(1);
      expect(store.tablesByStatus.reserved).toHaveLength(1);
    });
  });

  describe('Actions', () => {
    it('selectZone should update selectedZoneId', () => {
      const store = useTablesStore();
      store.selectZone(5);

      expect(store.selectedZoneId).toBe(5);
    });

    it('selectTable should update selectedTableId', () => {
      const store = useTablesStore();
      store.selectTable(10);

      expect(store.selectedTableId).toBe(10);
    });

    it('openTable should open table and return success', async () => {
      const mockResponse = {
        success: true,
        data: {
          table: { id: 1, number: '1', status: 'occupied' },
          order: { id: 100, total: 0 },
        },
      };

      vi.mocked(tablesApi.openTable).mockResolvedValue(mockResponse);

      const store = useTablesStore();
      store.tables = [{ id: 1, number: '1', status: 'free', zone_id: 1 }] as any;

      const result = await store.openTable(1, 2);

      expect(result).toBe(true);
      expect(tablesApi.openTable).toHaveBeenCalledWith(1, { guests_count: 2 });
    });

    it('updateTable should update table in array', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, number: '1', status: 'free' },
        { id: 2, number: '2', status: 'free' },
      ] as any;

      store.updateTable({ id: 1, number: '1', status: 'occupied' } as any);

      expect(store.tables[0].status).toBe('occupied');
    });

    it('getTableById should return table by id', () => {
      const store = useTablesStore();
      store.tables = [
        { id: 1, number: '1' },
        { id: 2, number: '2' },
      ] as any;

      expect(store.getTableById(2)?.number).toBe('2');
      expect(store.getTableById(999)).toBeUndefined();
    });

    it('getZoneById should return zone by id', () => {
      const store = useTablesStore();
      store.zones = [
        { id: 1, name: 'Зал 1' },
        { id: 2, name: 'Терраса' },
      ] as any;

      expect(store.getZoneById(1)?.name).toBe('Зал 1');
      expect(store.getZoneById(999)).toBeUndefined();
    });
  });

  describe('$reset', () => {
    it('should reset store to initial state', () => {
      const store = useTablesStore();

      store.zones = [{ id: 1, name: 'Test' }] as any;
      store.tables = [{ id: 1, number: '1' }] as any;
      store.selectedZoneId = 1;
      store.selectedTableId = 1;
      store.error = 'Some error';

      store.$reset();

      expect(store.zones).toEqual([]);
      expect(store.tables).toEqual([]);
      expect(store.selectedZoneId).toBeNull();
      expect(store.selectedTableId).toBeNull();
      expect(store.error).toBeNull();
    });
  });
});
