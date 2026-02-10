/**
 * POS Shifts Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        shifts: {
            getAll: vi.fn(),
            getCurrent: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useShiftsStore } from '@/pos/stores/shifts.js';

describe('POS Shifts Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty shifts', () => {
            const store = useShiftsStore();
            expect(store.shifts).toEqual([]);
        });

        it('should have null currentShift', () => {
            const store = useShiftsStore();
            expect(store.currentShift).toBeNull();
        });

        it('should not be loading', () => {
            const store = useShiftsStore();
            expect(store.shiftsLoading).toBe(false);
        });

        it('should have zero version', () => {
            const store = useShiftsStore();
            expect(store.shiftsVersion).toBe(0);
        });
    });

    describe('loadShifts', () => {
        it('should fetch and set shifts', async () => {
            const mockShifts = [
                { id: 1, status: 'closed', opened_at: '2024-01-15T08:00:00Z' },
                { id: 2, status: 'open', opened_at: '2024-01-16T08:00:00Z' },
            ];

            mockApi.shifts.getAll.mockResolvedValue(mockShifts);

            const store = useShiftsStore();
            await store.loadShifts(true);

            expect(store.shifts).toEqual(mockShifts);
            expect(store.shiftsLoading).toBe(false);
            expect(store.shiftsVersion).toBe(1);
        });

        it('should set loading state during fetch', async () => {
            let resolvePromise: (value: unknown) => void;
            mockApi.shifts.getAll.mockReturnValue(new Promise(resolve => {
                resolvePromise = resolve;
            }));

            const store = useShiftsStore();
            const promise = store.loadShifts(true);

            expect(store.shiftsLoading).toBe(true);

            resolvePromise!([]);
            await promise;

            expect(store.shiftsLoading).toBe(false);
        });

        it('should reset loading on error', async () => {
            mockApi.shifts.getAll.mockRejectedValue(new Error('Network error'));

            const store = useShiftsStore();

            await expect(store.loadShifts(true)).rejects.toThrow();
            expect(store.shiftsLoading).toBe(false);
        });

        it('should use cache on subsequent calls', async () => {
            mockApi.shifts.getAll.mockResolvedValue([]);

            const store = useShiftsStore();
            await store.loadShifts(true);
            await store.loadShifts(); // cached

            expect(mockApi.shifts.getAll).toHaveBeenCalledOnce();
        });

        it('should increment version on each load', async () => {
            mockApi.shifts.getAll.mockResolvedValue([]);

            const store = useShiftsStore();
            await store.loadShifts(true);
            expect(store.shiftsVersion).toBe(1);

            await store.loadShifts(true);
            expect(store.shiftsVersion).toBe(2);
        });
    });

    describe('loadCurrentShift', () => {
        it('should fetch and set current shift', async () => {
            const mockShift = {
                id: 1,
                status: 'open',
                opening_amount: 5000,
                opened_at: '2024-01-16T08:00:00Z',
            };

            mockApi.shifts.getCurrent.mockResolvedValue(mockShift);

            const store = useShiftsStore();
            await store.loadCurrentShift();

            expect(store.currentShift).toEqual(mockShift);
        });

        it('should set null when no active shift', async () => {
            mockApi.shifts.getCurrent.mockResolvedValue(null);

            const store = useShiftsStore();
            await store.loadCurrentShift();

            expect(store.currentShift).toBeNull();
        });
    });
});
