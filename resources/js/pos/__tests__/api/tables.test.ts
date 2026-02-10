/**
 * POS Tables API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray, mockExtractData } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
    mockExtractArray: vi.fn((res: any) => res?.data || []),
    mockExtractData: vi.fn((res: any) => res?.data?.data || res?.data || res),
}));

vi.mock('@/pos/api/httpClient.js', () => ({
    default: mockHttp,
    extractArray: mockExtractArray,
    extractData: mockExtractData,
}));

import { tables, zones } from '@/pos/api/modules/tables.js';

describe('POS Tables API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('tables.getAll', () => {
        it('should call GET /tables', async () => {
            const mockTables = [
                { id: 1, number: 1, capacity: 4, zone_id: 1 },
                { id: 2, number: 2, capacity: 6, zone_id: 1 },
            ];
            mockHttp.get.mockResolvedValue({ data: mockTables });
            mockExtractArray.mockReturnValue(mockTables);

            const result = await tables.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/tables');
            expect(result).toEqual(mockTables);
        });

        it('should return empty array when no tables', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            const result = await tables.getAll();

            expect(result).toEqual([]);
        });
    });

    describe('tables.get', () => {
        it('should call GET /tables/:id', async () => {
            const mockTable = { id: 5, number: 5, capacity: 2, zone_id: 1 };
            mockHttp.get.mockResolvedValue({ data: { data: mockTable } });
            mockExtractData.mockReturnValue(mockTable);

            const result = await tables.get(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/tables/5');
            expect(result).toEqual(mockTable);
        });

        it('should propagate error for non-existent table', async () => {
            mockHttp.get.mockRejectedValue(new Error('Not found'));

            await expect(tables.get(999)).rejects.toThrow('Not found');
        });
    });

    describe('tables.getOrders', () => {
        it('should call GET /tables/:id/orders', async () => {
            const mockOrders = [{ id: 1, status: 'new' }, { id: 2, status: 'cooking' }];
            mockHttp.get.mockResolvedValue({ data: mockOrders });
            mockExtractArray.mockReturnValue(mockOrders);

            const result = await tables.getOrders(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/tables/5/orders');
            expect(result).toEqual(mockOrders);
        });

        it('should return empty array when table has no orders', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            const result = await tables.getOrders(5);

            expect(result).toEqual([]);
        });
    });

    describe('tables.getOrderData', () => {
        it('should call GET /tables/:id/order-data with default params', async () => {
            mockHttp.get.mockResolvedValue({ data: { items: [] } });

            await tables.getOrderData(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/tables/5/order-data', { params: {} });
        });

        it('should pass custom params', async () => {
            mockHttp.get.mockResolvedValue({ data: { items: [] } });

            await tables.getOrderData(5, { include: 'items', status: 'active' });

            expect(mockHttp.get).toHaveBeenCalledWith('/tables/5/order-data', {
                params: { include: 'items', status: 'active' },
            });
        });
    });
});

describe('POS Zones API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('zones.getAll', () => {
        it('should call GET /zones', async () => {
            const mockZones = [
                { id: 1, name: 'Основной зал' },
                { id: 2, name: 'Терраса' },
            ];
            mockHttp.get.mockResolvedValue({ data: mockZones });
            mockExtractArray.mockReturnValue(mockZones);

            const result = await zones.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/zones');
            expect(result).toEqual(mockZones);
        });

        it('should return empty array when no zones', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            const result = await zones.getAll();

            expect(result).toEqual([]);
        });
    });
});
