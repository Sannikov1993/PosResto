/**
 * POS Customers API Module Unit Tests
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

import customers from '@/pos/api/modules/customers.js';

describe('POS Customers API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('customers.getAll', () => {
        it('should call GET /customers', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await customers.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/customers', { params: {} });
        });

        it('should pass params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await customers.getAll({ page: 1, limit: 20 });

            expect(mockHttp.get).toHaveBeenCalledWith('/customers', {
                params: { page: 1, limit: 20 },
            });
        });
    });

    describe('customers.search', () => {
        it('should call GET /customers/search with query', async () => {
            const mockResults = [{ id: 1, name: 'Иван', phone: '+79001234567' }];
            mockHttp.get.mockResolvedValue({ data: mockResults });
            mockExtractArray.mockReturnValue(mockResults);

            const result = await customers.search('Иван');

            expect(mockHttp.get).toHaveBeenCalledWith('/customers/search', {
                params: { q: 'Иван', limit: 10 },
            });
            expect(result).toEqual(mockResults);
        });

        it('should respect custom limit', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await customers.search('test', 5);

            expect(mockHttp.get).toHaveBeenCalledWith('/customers/search', {
                params: { q: 'test', limit: 5 },
            });
        });
    });

    describe('customers.get', () => {
        it('should call GET /customers/:id', async () => {
            const mockCustomer = { id: 1, name: 'Иван' };
            mockHttp.get.mockResolvedValue({ data: { data: mockCustomer } });
            mockExtractData.mockReturnValue(mockCustomer);

            const result = await customers.get(1);

            expect(mockHttp.get).toHaveBeenCalledWith('/customers/1');
            expect(result).toEqual(mockCustomer);
        });
    });

    describe('customers.create', () => {
        it('should POST customer data', async () => {
            const data = { name: 'Новый', phone: '+79001234567' };
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await customers.create(data);

            expect(mockHttp.post).toHaveBeenCalledWith('/customers', data);
        });
    });

    describe('customers.update', () => {
        it('should PUT customer data', async () => {
            const data = { name: 'Обновлённый' };
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await customers.update(1, data);

            expect(mockHttp.put).toHaveBeenCalledWith('/customers/1', data);
        });
    });

    describe('customers.getOrders', () => {
        it('should fetch customer orders', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await customers.getOrders(1);

            expect(mockHttp.get).toHaveBeenCalledWith('/customers/1/orders');
        });
    });

    describe('customers.getAddresses', () => {
        it('should fetch customer addresses', async () => {
            const addresses = [{ id: 1, address: 'ул. Пушкина, 10' }];
            mockHttp.get.mockResolvedValue({ data: addresses });
            mockExtractArray.mockReturnValue(addresses);

            const result = await customers.getAddresses(1);

            expect(mockHttp.get).toHaveBeenCalledWith('/customers/1/addresses');
            expect(result).toEqual(addresses);
        });
    });

    describe('customers.toggleBlacklist', () => {
        it('should POST toggle blacklist', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await customers.toggleBlacklist(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/customers/1/toggle-blacklist');
        });
    });

    describe('customers.saveDeliveryAddress', () => {
        it('should POST address data', async () => {
            const addressData = { address: 'ул. Ленина, 5', entrance: '2' };
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await customers.saveDeliveryAddress(1, addressData);

            expect(mockHttp.post).toHaveBeenCalledWith('/customers/1/save-delivery-address', addressData);
        });
    });

    describe('customers.deleteAddress', () => {
        it('should DELETE address', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await customers.deleteAddress(1, 5);

            expect(mockHttp.delete).toHaveBeenCalledWith('/customers/1/addresses/5');
        });
    });
});
