/**
 * POS Reservations Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        reservations: {
            getByDate: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
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

import { useReservationsStore } from '@/pos/stores/reservations.js';

describe('POS Reservations Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty reservations', () => {
            const store = useReservationsStore();
            expect(store.reservations).toEqual([]);
        });

        it('should have floorDate set to todays date', () => {
            const store = useReservationsStore();
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const expected = `${year}-${month}-${day}`;
            expect(store.floorDate).toBe(expected);
        });

        it('should have empty tableReservationsMap', () => {
            const store = useReservationsStore();
            expect(store.tableReservationsMap.size).toBe(0);
        });
    });

    describe('tableReservationsMap', () => {
        it('should group reservations by table_id for pending/confirmed statuses', async () => {
            const mockReservations = [
                { id: 1, table_id: 5, status: 'pending', guest_name: 'Alice' },
                { id: 2, table_id: 5, status: 'confirmed', guest_name: 'Bob' },
                { id: 3, table_id: 8, status: 'confirmed', guest_name: 'Charlie' },
            ];

            mockApi.reservations.getByDate.mockResolvedValue(mockReservations);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            expect(store.tableReservationsMap.size).toBe(2);
            expect(store.tableReservationsMap.get(5)).toHaveLength(2);
            expect(store.tableReservationsMap.get(8)).toHaveLength(1);
        });

        it('should exclude cancelled/completed reservations from the map', async () => {
            const mockReservations = [
                { id: 1, table_id: 5, status: 'cancelled', guest_name: 'Alice' },
                { id: 2, table_id: 8, status: 'completed', guest_name: 'Bob' },
                { id: 3, table_id: 10, status: 'confirmed', guest_name: 'Charlie' },
            ];

            mockApi.reservations.getByDate.mockResolvedValue(mockReservations);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            expect(store.tableReservationsMap.size).toBe(1);
            expect(store.tableReservationsMap.has(5)).toBe(false);
            expect(store.tableReservationsMap.has(8)).toBe(false);
            expect(store.tableReservationsMap.get(10)).toHaveLength(1);
        });

        it('should return empty map when no reservations', () => {
            const store = useReservationsStore();
            expect(store.tableReservationsMap.size).toBe(0);
        });
    });

    describe('loadReservations', () => {
        it('should fetch and set reservations', async () => {
            const mockReservations = [
                { id: 1, table_id: 5, status: 'confirmed', guest_name: 'Alice' },
            ];

            mockApi.reservations.getByDate.mockResolvedValue(mockReservations);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            expect(store.reservations).toEqual(mockReservations);
            expect(mockApi.reservations.getByDate).toHaveBeenCalledWith('2024-01-15');
        });

        it('should use cache on subsequent calls', async () => {
            mockApi.reservations.getByDate.mockResolvedValue([]);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);
            await store.loadReservations('2024-01-15'); // should use cache

            expect(mockApi.reservations.getByDate).toHaveBeenCalledOnce();
        });

        it('should skip cache when forced', async () => {
            mockApi.reservations.getByDate.mockResolvedValue([]);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);
            await store.loadReservations('2024-01-15', true);

            expect(mockApi.reservations.getByDate).toHaveBeenCalledTimes(2);
        });

        it('should set reservations to empty array on error', async () => {
            mockApi.reservations.getByDate.mockRejectedValue(new Error('Network error'));

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            expect(store.reservations).toEqual([]);
        });
    });

    describe('setFloorDate', () => {
        it('should update floorDate and load reservations for that date', async () => {
            mockApi.reservations.getByDate.mockResolvedValue([
                { id: 1, table_id: 3, status: 'confirmed' },
            ]);

            const store = useReservationsStore();
            await store.setFloorDate('2024-02-20');

            expect(store.floorDate).toBe('2024-02-20');
            expect(mockApi.reservations.getByDate).toHaveBeenCalledWith('2024-02-20');
        });
    });

    describe('getTableReservations', () => {
        it('should return reservations for a given table', async () => {
            const mockReservations = [
                { id: 1, table_id: 5, status: 'pending', guest_name: 'Alice' },
                { id: 2, table_id: 5, status: 'confirmed', guest_name: 'Bob' },
                { id: 3, table_id: 8, status: 'confirmed', guest_name: 'Charlie' },
            ];

            mockApi.reservations.getByDate.mockResolvedValue(mockReservations);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            const tableRes = store.getTableReservations(5);
            expect(tableRes).toHaveLength(2);
            expect(tableRes[0].id).toBe(1);
            expect(tableRes[1].id).toBe(2);
        });

        it('should return empty array for table without reservations', async () => {
            mockApi.reservations.getByDate.mockResolvedValue([]);

            const store = useReservationsStore();
            await store.loadReservations('2024-01-15', true);

            expect(store.getTableReservations(999)).toEqual([]);
        });
    });
});
