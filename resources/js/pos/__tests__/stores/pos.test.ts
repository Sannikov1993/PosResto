/**
 * POS Facade Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        tables: { getAll: vi.fn() },
        zones: { getAll: vi.fn() },
        shifts: { getAll: vi.fn(), getCurrent: vi.fn() },
        orders: { getActive: vi.fn(), getPaidToday: vi.fn(), getDelivery: vi.fn() },
        reservations: { getByDate: vi.fn() },
        priceLists: { getAll: vi.fn() },
        stopList: { getAll: vi.fn() },
        settings: { getGeneral: vi.fn() },
        menu: { getCategories: vi.fn(), getDishes: vi.fn() },
        couriers: { getAll: vi.fn() },
        writeOffs: { getAll: vi.fn(), getCancelledOrders: vi.fn() },
        cancellations: { getPending: vi.fn() },
        customers: { getAll: vi.fn() },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

// Mock timezone and formatAmount utilities
vi.mock('@/utils/timezone.js', () => ({
    setTimezone: vi.fn(),
}));

vi.mock('@/utils/formatAmount.js', () => ({
    setRoundAmounts: vi.fn(),
}));

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        error: vi.fn(),
        warn: vi.fn(),
        info: vi.fn(),
        debug: vi.fn(),
    }),
}));

// Mock uiConfig
vi.mock('@/shared/config/uiConfig.js', () => ({
    FLOOR_WIDTH: 1200,
    FLOOR_HEIGHT: 800,
}));

import { usePosStore } from '@/pos/stores/pos.js';

describe('POS Facade Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have default roundAmounts as false', () => {
            const store = usePosStore();
            expect(store.roundAmounts).toBe(false);
        });

        it('should have default timezone as Europe/Moscow', () => {
            const store = usePosStore();
            expect(store.timezone).toBe('Europe/Moscow');
        });

        it('should have empty tables', () => {
            const store = usePosStore();
            expect(store.tables).toEqual([]);
        });

        it('should have empty activeOrders', () => {
            const store = usePosStore();
            expect(store.activeOrders).toEqual([]);
        });

        it('should have null currentShift', () => {
            const store = usePosStore();
            expect(store.currentShift).toBeNull();
        });

        it('should have empty deliveryOrders', () => {
            const store = usePosStore();
            expect(store.deliveryOrders).toEqual([]);
        });

        it('should have zero pendingDeliveryCount', () => {
            const store = usePosStore();
            expect(store.pendingDeliveryCount).toBe(0);
        });

        it('should have empty writeOffs', () => {
            const store = usePosStore();
            expect(store.writeOffs).toEqual([]);
        });

        it('should have zero pendingCancellationsCount', () => {
            const store = usePosStore();
            expect(store.pendingCancellationsCount).toBe(0);
        });
    });

    describe('getTableStatus', () => {
        it('should return free for table with no order and no reservation', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([]);
            mockApi.reservations.getByDate.mockResolvedValue([]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 1, name: 'T1' } as any);
            expect(status).toBe('free');
        });

        it('should return occupied for table with an active order', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([
                { id: 10, table_id: 1, status: 'cooking', bill_requested: false },
            ]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 1, name: 'T1' } as any);
            expect(status).toBe('occupied');
        });

        it('should return bill for table with bill_requested', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([
                { id: 10, table_id: 1, status: 'cooking', bill_requested: true },
            ]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 1, name: 'T1' } as any);
            expect(status).toBe('bill');
        });

        it('should return ready for table with order in ready status', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([
                { id: 10, table_id: 1, status: 'ready', bill_requested: false },
            ]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 1, name: 'T1' } as any);
            expect(status).toBe('ready');
        });

        it('should return reserved for table with active reservation but no order', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([]);
            mockApi.reservations.getByDate.mockResolvedValue([
                { id: 1, table_id: 5, status: 'confirmed', guest_name: 'Alice' },
            ]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 5, name: 'T5' } as any);
            expect(status).toBe('reserved');
        });

        it('should prioritize order status over reservation', async () => {
            setupDefaultApiMocks();
            mockApi.orders.getActive.mockResolvedValue([
                { id: 10, table_id: 5, status: 'cooking', bill_requested: false },
            ]);
            mockApi.reservations.getByDate.mockResolvedValue([
                { id: 1, table_id: 5, status: 'confirmed', guest_name: 'Alice' },
            ]);

            const store = usePosStore();
            await store.loadInitialData();

            const status = store.getTableStatus({ id: 5, name: 'T5' } as any);
            expect(status).toBe('occupied');
        });
    });

    describe('loadInitialData', () => {
        it('should load all data in parallel', async () => {
            setupDefaultApiMocks();

            const store = usePosStore();
            await store.loadInitialData();

            expect(mockApi.tables.getAll).toHaveBeenCalledOnce();
            expect(mockApi.zones.getAll).toHaveBeenCalledOnce();
            expect(mockApi.shifts.getAll).toHaveBeenCalledOnce();
            expect(mockApi.orders.getPaidToday).toHaveBeenCalledOnce();
            expect(mockApi.shifts.getCurrent).toHaveBeenCalledOnce();
            expect(mockApi.orders.getActive).toHaveBeenCalledOnce();
            expect(mockApi.orders.getDelivery).toHaveBeenCalledOnce();
        });

        it('should set tables loading to false after completion', async () => {
            setupDefaultApiMocks();

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.tablesLoading).toBe(false);
        });

        it('should set shifts loading to false after completion', async () => {
            setupDefaultApiMocks();

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.shiftsLoading).toBe(false);
        });

        it('should populate tables from API response', async () => {
            setupDefaultApiMocks();
            const mockTables = [
                { id: 1, name: 'Table 1' },
                { id: 2, name: 'Table 2' },
            ];
            mockApi.tables.getAll.mockResolvedValue(mockTables);

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.tables).toEqual(mockTables);
        });

        it('should populate shifts from API response', async () => {
            setupDefaultApiMocks();
            const mockShifts = [
                { id: 1, status: 'closed' },
                { id: 2, status: 'open' },
            ];
            mockApi.shifts.getAll.mockResolvedValue(mockShifts);

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.shifts).toEqual(mockShifts);
        });

        it('should populate currentShift from API response', async () => {
            setupDefaultApiMocks();
            const mockShift = { id: 2, status: 'open', opening_amount: 5000 };
            mockApi.shifts.getCurrent.mockResolvedValue(mockShift);

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.currentShift).toEqual(mockShift);
        });

        it('should handle API errors gracefully with empty defaults', async () => {
            mockApi.tables.getAll.mockRejectedValue(new Error('fail'));
            mockApi.zones.getAll.mockRejectedValue(new Error('fail'));
            mockApi.shifts.getAll.mockRejectedValue(new Error('fail'));
            mockApi.orders.getPaidToday.mockRejectedValue(new Error('fail'));
            mockApi.shifts.getCurrent.mockRejectedValue(new Error('fail'));
            mockApi.orders.getActive.mockRejectedValue(new Error('fail'));
            mockApi.orders.getDelivery.mockRejectedValue(new Error('fail'));
            mockApi.reservations.getByDate.mockRejectedValue(new Error('fail'));
            mockApi.priceLists.getAll.mockRejectedValue(new Error('fail'));
            mockApi.stopList.getAll.mockRejectedValue(new Error('fail'));
            mockApi.settings.getGeneral.mockRejectedValue(new Error('fail'));

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.tables).toEqual([]);
            expect(store.tablesLoading).toBe(false);
            expect(store.shiftsLoading).toBe(false);
        });

        it('should apply settings when returned from API', async () => {
            setupDefaultApiMocks();
            mockApi.settings.getGeneral.mockResolvedValue({
                round_amounts: true,
                timezone: 'Asia/Tokyo',
            });

            const { setTimezone } = await import('@/utils/timezone.js');
            const { setRoundAmounts } = await import('@/utils/formatAmount.js');

            const store = usePosStore();
            await store.loadInitialData();

            expect(store.roundAmounts).toBe(true);
            expect(store.timezone).toBe('Asia/Tokyo');
            expect(setTimezone).toHaveBeenCalledWith('Asia/Tokyo');
            expect(setRoundAmounts).toHaveBeenCalledWith(true);
        });
    });

    // Helper to set up all API mocks with safe defaults
    function setupDefaultApiMocks() {
        mockApi.tables.getAll.mockResolvedValue([]);
        mockApi.zones.getAll.mockResolvedValue([]);
        mockApi.shifts.getAll.mockResolvedValue([]);
        mockApi.orders.getPaidToday.mockResolvedValue([]);
        mockApi.shifts.getCurrent.mockResolvedValue(null);
        mockApi.orders.getActive.mockResolvedValue([]);
        mockApi.orders.getDelivery.mockResolvedValue([]);
        mockApi.reservations.getByDate.mockResolvedValue([]);
        mockApi.priceLists.getAll.mockResolvedValue([]);
        mockApi.stopList.getAll.mockResolvedValue([]);
        mockApi.settings.getGeneral.mockResolvedValue(null);
    }
});
