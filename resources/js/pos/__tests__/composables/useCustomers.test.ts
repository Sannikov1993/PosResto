/**
 * useCustomers Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        customers: {
            getAll: vi.fn(),
            search: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

// Need to reset module state between tests since useCustomers uses singleton refs
let useCustomers: () => ReturnType<typeof import('@/pos/composables/useCustomers.js')['useCustomers']>;

describe('useCustomers', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        // Re-import to reset singleton state
        vi.resetModules();

        vi.doMock('@/shared/services/logger.js', () => ({
            createLogger: () => ({
                debug: vi.fn(),
                warn: vi.fn(),
                error: vi.fn(),
            }),
        }));

        vi.doMock('@/pos/api/index.js', () => ({
            default: mockApi,
        }));

        const module = await import('@/pos/composables/useCustomers.js');
        useCustomers = module.useCustomers;
    });

    describe('initial state', () => {
        it('should have empty customers', () => {
            const { customers, loading, loaded, hasCustomers, customersCount } = useCustomers();

            expect(customers.value).toEqual([]);
            expect(loading.value).toBe(false);
            expect(loaded.value).toBe(false);
            expect(hasCustomers.value).toBe(false);
            expect(customersCount.value).toBe(0);
        });
    });

    describe('loadCustomers', () => {
        it('should load customers from API', async () => {
            const mockCustomers = [
                { id: 1, name: 'Иван', phone: '+79001234567' },
                { id: 2, name: 'Мария', phone: '+79007654321' },
            ];

            mockApi.customers.getAll.mockResolvedValue(mockCustomers);

            const { loadCustomers, customers, loaded, hasCustomers, customersCount } = useCustomers();
            await loadCustomers(true);

            expect(customers.value).toEqual(mockCustomers);
            expect(loaded.value).toBe(true);
            expect(hasCustomers.value).toBe(true);
            expect(customersCount.value).toBe(2);
        });

        it('should use cache within TTL', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Test' }]);

            const { loadCustomers } = useCustomers();
            await loadCustomers(true);
            await loadCustomers(); // should use cache

            expect(mockApi.customers.getAll).toHaveBeenCalledOnce();
        });

        it('should bypass cache when forced', async () => {
            mockApi.customers.getAll.mockResolvedValue([]);

            const { loadCustomers } = useCustomers();
            await loadCustomers(true);
            await loadCustomers(true);

            expect(mockApi.customers.getAll).toHaveBeenCalledTimes(2);
        });

        it('should handle API errors', async () => {
            mockApi.customers.getAll.mockRejectedValue(new Error('Network error'));

            const { loadCustomers } = useCustomers();
            await expect(loadCustomers(true)).rejects.toThrow('Network error');
        });

        it('should handle non-array response', async () => {
            mockApi.customers.getAll.mockResolvedValue(null);

            const { loadCustomers, customers } = useCustomers();
            await loadCustomers(true);

            expect(customers.value).toEqual([]);
        });
    });

    describe('searchCustomers', () => {
        it('should search via API', async () => {
            const results = [{ id: 1, name: 'Иван Петров', phone: '+79001234567' }];
            mockApi.customers.search.mockResolvedValue(results);

            const { searchCustomers } = useCustomers();
            const found = await searchCustomers('Иван');

            expect(mockApi.customers.search).toHaveBeenCalledWith('Иван');
            expect(found).toEqual(results);
        });

        it('should return all customers for short query', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'A' }]);

            const { loadCustomers, searchCustomers, customers } = useCustomers();
            await loadCustomers(true);

            const result = await searchCustomers('a');
            expect(result).toEqual(customers.value);
            expect(mockApi.customers.search).not.toHaveBeenCalled();
        });

        it('should return all customers for empty query', async () => {
            const { searchCustomers, customers } = useCustomers();
            const result = await searchCustomers('');
            expect(result).toEqual(customers.value);
        });
    });

    describe('filterCustomers', () => {
        it('should filter by name', async () => {
            const mockData = [
                { id: 1, name: 'Иван Петров', phone: '+79001234567' },
                { id: 2, name: 'Мария Сидорова', phone: '+79007654321' },
            ];
            mockApi.customers.getAll.mockResolvedValue(mockData);

            const { loadCustomers, filterCustomers } = useCustomers();
            await loadCustomers(true);

            const result = filterCustomers('иван');
            expect(result).toHaveLength(1);
            expect(result[0].name).toBe('Иван Петров');
        });

        it('should filter by phone digits', async () => {
            const mockData = [
                { id: 1, name: 'Иван', phone: '+7-900-123-45-67' },
                { id: 2, name: 'Мария', phone: '+7-900-765-43-21' },
            ];
            mockApi.customers.getAll.mockResolvedValue(mockData);

            const { loadCustomers, filterCustomers } = useCustomers();
            await loadCustomers(true);

            const result = filterCustomers('1234567');
            expect(result).toHaveLength(1);
            expect(result[0].name).toBe('Иван');
        });

        it('should return all for empty query', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Test' }]);

            const { loadCustomers, filterCustomers, customers } = useCustomers();
            await loadCustomers(true);

            const result = filterCustomers('');
            expect(result).toEqual(customers.value);
        });
    });

    describe('getCustomerById', () => {
        it('should find customer by id', async () => {
            mockApi.customers.getAll.mockResolvedValue([
                { id: 1, name: 'Иван' },
                { id: 2, name: 'Мария' },
            ]);

            const { loadCustomers, getCustomerById } = useCustomers();
            await loadCustomers(true);

            expect(getCustomerById(2)?.name).toBe('Мария');
        });

        it('should return null for unknown id', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Test' }]);

            const { loadCustomers, getCustomerById } = useCustomers();
            await loadCustomers(true);

            expect(getCustomerById(999)).toBeNull();
        });
    });

    describe('getCustomerByPhone', () => {
        it('should find customer by phone digits', async () => {
            mockApi.customers.getAll.mockResolvedValue([
                { id: 1, name: 'Иван', phone: '+7-900-123-45-67' },
            ]);

            const { loadCustomers, getCustomerByPhone } = useCustomers();
            await loadCustomers(true);

            expect(getCustomerByPhone('+79001234567')?.name).toBe('Иван');
        });

        it('should return null for empty phone', () => {
            const { getCustomerByPhone } = useCustomers();
            expect(getCustomerByPhone('')).toBeNull();
        });
    });

    describe('clearCache', () => {
        it('should reset loaded flag', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Test' }]);

            const { loadCustomers, clearCache, loaded } = useCustomers();
            await loadCustomers(true);
            expect(loaded.value).toBe(true);

            clearCache();
            expect(loaded.value).toBe(false);
        });
    });

    describe('addToCache', () => {
        it('should add new customer to beginning', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Existing' }]);

            const { loadCustomers, addToCache, customers } = useCustomers();
            await loadCustomers(true);

            addToCache({ id: 2, name: 'New' } as any);

            expect(customers.value[0].name).toBe('New');
            expect(customers.value).toHaveLength(2);
        });

        it('should update existing customer in cache', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Old Name' }]);

            const { loadCustomers, addToCache, customers } = useCustomers();
            await loadCustomers(true);

            addToCache({ id: 1, name: 'Updated Name' } as any);

            expect(customers.value).toHaveLength(1);
            expect(customers.value[0].name).toBe('Updated Name');
        });
    });

    describe('updateInCache', () => {
        it('should update existing customer', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Old' }]);

            const { loadCustomers, updateInCache, customers } = useCustomers();
            await loadCustomers(true);

            updateInCache({ id: 1, name: 'Updated' } as any);

            expect(customers.value[0].name).toBe('Updated');
        });

        it('should not add if customer not found', async () => {
            mockApi.customers.getAll.mockResolvedValue([{ id: 1, name: 'Test' }]);

            const { loadCustomers, updateInCache, customers } = useCustomers();
            await loadCustomers(true);

            updateInCache({ id: 999, name: 'Unknown' } as any);

            expect(customers.value).toHaveLength(1);
        });
    });
});
