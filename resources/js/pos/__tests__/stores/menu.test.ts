/**
 * POS Menu Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

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
        menu: {
            getCategories: vi.fn(),
            getDishes: vi.fn(),
        },
        priceLists: {
            getAll: vi.fn(),
        },
        stopList: {
            getAll: vi.fn(),
        },
        customers: {
            getAll: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useMenuStore } from '@/pos/stores/menu.js';

describe('POS Menu Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty categories and dishes', () => {
            const store = useMenuStore();
            expect(store.menuCategories).toEqual([]);
            expect(store.menuDishes).toEqual([]);
        });

        it('should have empty price lists', () => {
            const store = useMenuStore();
            expect(store.availablePriceLists).toEqual([]);
            expect(store.selectedPriceListId).toBeNull();
        });

        it('should have empty stop list', () => {
            const store = useMenuStore();
            expect(store.stopList).toEqual([]);
            expect(store.stopListDishIds.size).toBe(0);
        });

        it('should have empty customers', () => {
            const store = useMenuStore();
            expect(store.customers).toEqual([]);
        });
    });

    describe('loadMenu', () => {
        it('should fetch categories and dishes', async () => {
            const mockCategories = [
                { id: 1, name: 'Супы', sort_order: 1 },
                { id: 2, name: 'Салаты', sort_order: 2 },
            ];
            const mockDishes = [
                { id: 1, name: 'Борщ', category_id: 1, price: 350 },
                { id: 2, name: 'Цезарь', category_id: 2, price: 450 },
            ];

            mockApi.menu.getCategories.mockResolvedValue(mockCategories);
            mockApi.menu.getDishes.mockResolvedValue(mockDishes);

            const store = useMenuStore();
            await store.loadMenu();

            expect(store.menuCategories).toEqual(mockCategories);
            expect(store.menuDishes).toEqual(mockDishes);
        });

        it('should pass selected priceListId to getDishes', async () => {
            mockApi.menu.getCategories.mockResolvedValue([]);
            mockApi.menu.getDishes.mockResolvedValue([]);

            const store = useMenuStore();
            store.selectedPriceListId = 5;
            await store.loadMenu();

            expect(mockApi.menu.getDishes).toHaveBeenCalledWith(null, 5);
        });
    });

    describe('loadPriceLists', () => {
        it('should filter only active price lists', async () => {
            mockApi.priceLists.getAll.mockResolvedValue([
                { id: 1, name: 'Обед', is_active: true },
                { id: 2, name: 'Архив', is_active: false },
                { id: 3, name: 'Вечер', is_active: true },
            ]);

            const store = useMenuStore();
            await store.loadPriceLists();

            expect(store.availablePriceLists).toHaveLength(2);
            expect(store.availablePriceLists.every(pl => pl.is_active)).toBe(true);
        });

        it('should handle empty response', async () => {
            mockApi.priceLists.getAll.mockResolvedValue([]);

            const store = useMenuStore();
            await store.loadPriceLists();

            expect(store.availablePriceLists).toEqual([]);
        });

        it('should handle API error gracefully', async () => {
            mockApi.priceLists.getAll.mockRejectedValue(new Error('Network error'));

            const store = useMenuStore();
            await store.loadPriceLists();

            expect(store.availablePriceLists).toEqual([]);
        });
    });

    describe('setPriceList', () => {
        it('should set selectedPriceListId and reload menu', async () => {
            mockApi.menu.getCategories.mockResolvedValue([]);
            mockApi.menu.getDishes.mockResolvedValue([]);

            const store = useMenuStore();
            await store.setPriceList(3);

            expect(store.selectedPriceListId).toBe(3);
            expect(mockApi.menu.getDishes).toHaveBeenCalledWith(null, 3);
        });

        it('should handle null to reset to default price list', async () => {
            mockApi.menu.getCategories.mockResolvedValue([]);
            mockApi.menu.getDishes.mockResolvedValue([]);

            const store = useMenuStore();
            await store.setPriceList(null);

            expect(store.selectedPriceListId).toBeNull();
            expect(mockApi.menu.getDishes).toHaveBeenCalledWith(null, null);
        });
    });

    describe('loadStopList', () => {
        it('should load stop list and build dish IDs set', async () => {
            const mockStopList = [
                { id: 1, dish_id: 10, reason: 'Закончилось' },
                { id: 2, dish_id: 20, reason: 'Сезон' },
            ];

            mockApi.stopList.getAll.mockResolvedValue(mockStopList);

            const store = useMenuStore();
            await store.loadStopList();

            expect(store.stopList).toEqual(mockStopList);
            expect(store.stopListDishIds.has(10)).toBe(true);
            expect(store.stopListDishIds.has(20)).toBe(true);
            expect(store.stopListDishIds.has(30)).toBe(false);
        });
    });

    describe('loadCustomers', () => {
        it('should load customers', async () => {
            const mockCustomers = [
                { id: 1, name: 'Иван', phone: '+79001234567' },
                { id: 2, name: 'Мария', phone: '+79007654321' },
            ];

            mockApi.customers.getAll.mockResolvedValue(mockCustomers);

            const store = useMenuStore();
            await store.loadCustomers();

            expect(store.customers).toEqual(mockCustomers);
        });
    });
});
