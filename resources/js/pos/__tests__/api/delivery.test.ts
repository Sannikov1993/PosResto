/**
 * POS Delivery API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray, mockExtractData } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
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

import { delivery, couriers } from '@/pos/api/modules/delivery.js';

describe('POS Delivery API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('delivery.calculateDelivery', () => {
        it('should POST /delivery/calculate with address and total', async () => {
            mockHttp.post.mockResolvedValue({ data: { cost: 300, zone_id: 1 } });

            await delivery.calculateDelivery({ address: 'ул. Пушкина, 10', total: 2000 });

            expect(mockHttp.post).toHaveBeenCalledWith('/delivery/calculate', {
                address: 'ул. Пушкина, 10',
                total: 2000,
                lat: undefined,
                lng: undefined,
            });
        });

        it('should include lat/lng when provided', async () => {
            mockHttp.post.mockResolvedValue({ data: { cost: 200 } });

            await delivery.calculateDelivery({
                address: 'ул. Ленина, 5',
                total: 3000,
                lat: 55.751244,
                lng: 37.618423,
            });

            expect(mockHttp.post).toHaveBeenCalledWith('/delivery/calculate', {
                address: 'ул. Ленина, 5',
                total: 3000,
                lat: 55.751244,
                lng: 37.618423,
            });
        });
    });

    describe('delivery.getZones', () => {
        it('should call GET /delivery/zones', async () => {
            const mockZones = [{ id: 1, name: 'Центр', price: 200 }];
            mockHttp.get.mockResolvedValue({ data: mockZones });
            mockExtractArray.mockReturnValue(mockZones);

            const result = await delivery.getZones();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/zones');
            expect(result).toEqual(mockZones);
        });
    });

    describe('delivery.getOrders', () => {
        it('should call GET /delivery/orders with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await delivery.getOrders();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/orders', { params: {} });
        });

        it('should pass custom params', async () => {
            const mockOrders = [{ id: 1, status: 'delivering' }];
            mockHttp.get.mockResolvedValue({ data: mockOrders });
            mockExtractArray.mockReturnValue(mockOrders);

            const result = await delivery.getOrders({ status: 'delivering', courier_id: 3 });

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/orders', {
                params: { status: 'delivering', courier_id: 3 },
            });
            expect(result).toEqual(mockOrders);
        });
    });

    describe('delivery.assignCourier', () => {
        it('should POST courier assignment', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await delivery.assignCourier(10, 3);

            expect(mockHttp.post).toHaveBeenCalledWith('/delivery/orders/10/assign-courier', {
                courier_id: 3,
            });
        });
    });

    describe('delivery.getProblems', () => {
        it('should call GET /delivery/problems with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await delivery.getProblems();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/problems', { params: {} });
        });

        it('should pass custom params', async () => {
            const mockProblems = [{ id: 1, type: 'late' }];
            mockHttp.get.mockResolvedValue({ data: mockProblems });
            mockExtractArray.mockReturnValue(mockProblems);

            const result = await delivery.getProblems({ resolved: false });

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/problems', {
                params: { resolved: false },
            });
            expect(result).toEqual(mockProblems);
        });
    });

    describe('delivery.resolveProblem', () => {
        it('should PATCH problem resolution', async () => {
            mockHttp.patch.mockResolvedValue({ data: { success: true } });

            await delivery.resolveProblem(5, 'Called customer, rescheduled');

            expect(mockHttp.patch).toHaveBeenCalledWith('/delivery/problems/5/resolve', {
                resolution: 'Called customer, rescheduled',
            });
        });
    });

    describe('delivery.deleteProblem', () => {
        it('should DELETE /delivery/problems/:id', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await delivery.deleteProblem(5);

            expect(mockHttp.delete).toHaveBeenCalledWith('/delivery/problems/5');
        });
    });

    describe('delivery.getMapData', () => {
        it('should call GET /delivery/map-data', async () => {
            const mockMapData = { orders: [{ id: 1 }], couriers: [{ id: 2 }] };
            mockHttp.get.mockResolvedValue({ data: mockMapData });
            mockExtractData.mockReturnValue(mockMapData);

            const result = await delivery.getMapData();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/map-data');
            expect(result).toEqual(mockMapData);
        });
    });
});

describe('POS Couriers API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('couriers.getAll', () => {
        it('should call GET /delivery/couriers', async () => {
            const mockCouriers = [{ id: 1, name: 'Алексей' }, { id: 2, name: 'Мария' }];
            mockHttp.get.mockResolvedValue({ data: mockCouriers });
            mockExtractArray.mockReturnValue(mockCouriers);

            const result = await couriers.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/couriers');
            expect(result).toEqual(mockCouriers);
        });
    });

    describe('couriers.assign', () => {
        it('should POST courier assignment to order', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await couriers.assign(10, 2);

            expect(mockHttp.post).toHaveBeenCalledWith('/delivery/orders/10/assign-courier', {
                courier_id: 2,
            });
        });
    });
});
