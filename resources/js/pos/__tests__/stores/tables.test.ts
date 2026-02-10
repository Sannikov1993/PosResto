/**
 * POS Tables Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock uiConfig
vi.mock('@/shared/config/uiConfig.js', () => ({
    FLOOR_WIDTH: 1000,
    FLOOR_HEIGHT: 700,
}));

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        tables: {
            getAll: vi.fn(),
        },
        zones: {
            getAll: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useTablesStore } from '@/pos/stores/tables.js';

describe('POS Tables Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty tables and zones', () => {
            const store = useTablesStore();
            expect(store.tables).toEqual([]);
            expect(store.zones).toEqual([]);
        });

        it('should have default floor dimensions', () => {
            const store = useTablesStore();
            expect(store.floorWidth).toBe(1000);
            expect(store.floorHeight).toBe(700);
        });

        it('should have empty floor objects', () => {
            const store = useTablesStore();
            expect(store.floorObjects).toEqual([]);
        });

        it('should have null selected table/zone', () => {
            const store = useTablesStore();
            expect(store.selectedTable).toBeNull();
            expect(store.selectedZone).toBeNull();
        });

        it('should not be loading', () => {
            const store = useTablesStore();
            expect(store.tablesLoading).toBe(false);
        });
    });

    describe('loadTables', () => {
        it('should fetch and set tables', async () => {
            const mockTables = [
                { id: 1, number: 1, status: 'free', seats: 4 },
                { id: 2, number: 2, status: 'occupied', seats: 6 },
            ];

            mockApi.tables.getAll.mockResolvedValue(mockTables);

            const store = useTablesStore();
            await store.loadTables();

            expect(store.tables).toEqual(mockTables);
            expect(store.tablesLoading).toBe(false);
        });

        it('should set loading state during fetch', async () => {
            let resolvePromise: (value: unknown) => void;
            mockApi.tables.getAll.mockReturnValue(new Promise(resolve => {
                resolvePromise = resolve;
            }));

            const store = useTablesStore();
            const promise = store.loadTables();

            expect(store.tablesLoading).toBe(true);

            resolvePromise!([]);
            await promise;

            expect(store.tablesLoading).toBe(false);
        });

        it('should reset loading on error', async () => {
            mockApi.tables.getAll.mockRejectedValue(new Error('Network error'));

            const store = useTablesStore();

            await expect(store.loadTables()).rejects.toThrow();
            expect(store.tablesLoading).toBe(false);
        });
    });

    describe('loadZones', () => {
        it('should fetch and set zones', async () => {
            const mockZones = [
                { id: 1, name: 'Основной зал', color: '#ff0000' },
                { id: 2, name: 'Терраса', color: '#00ff00' },
            ];

            mockApi.zones.getAll.mockResolvedValue(mockZones);

            const store = useTablesStore();
            await store.loadZones();

            expect(store.zones).toEqual(mockZones);
        });
    });

    describe('updateFloorObjects', () => {
        it('should extract objects from zone floor_layout', () => {
            const store = useTablesStore();
            const mockZone = {
                id: 1,
                name: 'Test Zone',
                floor_layout: {
                    objects: [
                        { type: 'wall', x: 0, y: 0, width: 100 },
                        { type: 'decoration', x: 50, y: 50 },
                    ],
                    width: 1200,
                    height: 800,
                },
            } as any;

            store.updateFloorObjects(mockZone);

            expect(store.floorObjects).toHaveLength(2);
            expect(store.floorWidth).toBe(1200);
            expect(store.floorHeight).toBe(800);
        });

        it('should clear objects when zone is null', () => {
            const store = useTablesStore();
            store.floorObjects = [{ type: 'wall' }] as any;

            store.updateFloorObjects(null);

            expect(store.floorObjects).toEqual([]);
        });

        it('should use default dimensions when layout has no size', () => {
            const store = useTablesStore();
            const mockZone = {
                id: 1,
                name: 'Test Zone',
                floor_layout: {
                    objects: [],
                },
            } as any;

            store.updateFloorObjects(mockZone);

            expect(store.floorWidth).toBe(1000);
            expect(store.floorHeight).toBe(700);
        });

        it('should handle zone without floor_layout', () => {
            const store = useTablesStore();
            const mockZone = {
                id: 1,
                name: 'Test Zone',
            } as any;

            store.updateFloorObjects(mockZone);

            expect(store.floorObjects).toEqual([]);
        });
    });
});
