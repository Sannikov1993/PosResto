/**
 * POS Menu API Module Unit Tests
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

import { menu, priceLists, stopList } from '@/pos/api/modules/menu.js';

describe('POS Menu API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('menu.getAll', () => {
        it('should call GET /menu without params by default', async () => {
            const mockData = { categories: [], dishes: [] };
            mockHttp.get.mockResolvedValue({ data: mockData });
            mockExtractData.mockReturnValue(mockData);

            const result = await menu.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/menu', { params: {} });
            expect(result).toEqual(mockData);
        });

        it('should pass price_list_id when provided', async () => {
            const mockData = { categories: [], dishes: [] };
            mockHttp.get.mockResolvedValue({ data: mockData });
            mockExtractData.mockReturnValue(mockData);

            await menu.getAll(3);

            expect(mockHttp.get).toHaveBeenCalledWith('/menu', { params: { price_list_id: 3 } });
        });

        it('should not pass price_list_id when null', async () => {
            mockHttp.get.mockResolvedValue({ data: {} });
            mockExtractData.mockReturnValue({});

            await menu.getAll(null);

            expect(mockHttp.get).toHaveBeenCalledWith('/menu', { params: {} });
        });
    });

    describe('menu.getCategories', () => {
        it('should call GET /categories', async () => {
            const mockCategories = [{ id: 1, name: 'Салаты' }, { id: 2, name: 'Супы' }];
            mockHttp.get.mockResolvedValue({ data: mockCategories });
            mockExtractArray.mockReturnValue(mockCategories);

            const result = await menu.getCategories();

            expect(mockHttp.get).toHaveBeenCalledWith('/categories');
            expect(result).toEqual(mockCategories);
        });
    });

    describe('menu.getDishes', () => {
        it('should call GET /dishes with available=1 by default', async () => {
            const mockDishes = [{ id: 1, name: 'Борщ' }];
            mockHttp.get.mockResolvedValue({ data: mockDishes });
            mockExtractArray.mockReturnValue(mockDishes);

            const result = await menu.getDishes();

            expect(mockHttp.get).toHaveBeenCalledWith('/dishes', { params: { available: 1 } });
            expect(result).toEqual(mockDishes);
        });

        it('should pass category_id when provided', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await menu.getDishes(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/dishes', {
                params: { available: 1, category_id: 5 },
            });
        });

        it('should pass both category_id and price_list_id', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await menu.getDishes(5, 2);

            expect(mockHttp.get).toHaveBeenCalledWith('/dishes', {
                params: { available: 1, category_id: 5, price_list_id: 2 },
            });
        });

        it('should pass only price_list_id when categoryId is null', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await menu.getDishes(null, 2);

            expect(mockHttp.get).toHaveBeenCalledWith('/dishes', {
                params: { available: 1, price_list_id: 2 },
            });
        });
    });

    describe('menu.getDish', () => {
        it('should call GET /dishes/:id', async () => {
            const mockDish = { id: 10, name: 'Пицца Маргарита', price: 500 };
            mockHttp.get.mockResolvedValue({ data: { data: mockDish } });
            mockExtractData.mockReturnValue(mockDish);

            const result = await menu.getDish(10);

            expect(mockHttp.get).toHaveBeenCalledWith('/dishes/10');
            expect(result).toEqual(mockDish);
        });
    });
});

describe('POS PriceLists API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('priceLists.getAll', () => {
        it('should call GET /price-lists', async () => {
            const mockLists = [{ id: 1, name: 'Основной', is_active: true }];
            mockHttp.get.mockResolvedValue({ data: mockLists });
            mockExtractArray.mockReturnValue(mockLists);

            const result = await priceLists.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/price-lists');
            expect(result).toEqual(mockLists);
        });
    });

    describe('priceLists.getActive', () => {
        it('should return only active price lists', async () => {
            const allLists = [
                { id: 1, name: 'Основной', is_active: true },
                { id: 2, name: 'Старый', is_active: false },
                { id: 3, name: 'Доставка', is_active: true },
            ];
            mockHttp.get.mockResolvedValue({ data: allLists });
            mockExtractArray.mockReturnValue(allLists);

            const result = await priceLists.getActive();

            expect(result).toEqual([
                { id: 1, name: 'Основной', is_active: true },
                { id: 3, name: 'Доставка', is_active: true },
            ]);
        });

        it('should return empty array when no active lists', async () => {
            const allLists = [{ id: 1, name: 'Старый', is_active: false }];
            mockHttp.get.mockResolvedValue({ data: allLists });
            mockExtractArray.mockReturnValue(allLists);

            const result = await priceLists.getActive();

            expect(result).toEqual([]);
        });
    });
});

describe('POS StopList API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('stopList.getAll', () => {
        it('should call GET /stop-list', async () => {
            const mockItems = [{ id: 1, dish_id: 10, reason: 'Out of stock' }];
            mockHttp.get.mockResolvedValue({ data: mockItems });
            mockExtractArray.mockReturnValue(mockItems);

            const result = await stopList.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/stop-list');
            expect(result).toEqual(mockItems);
        });
    });

    describe('stopList.searchDishes', () => {
        it('should call GET /stop-list/search-dishes with query', async () => {
            const mockDishes = [{ id: 5, name: 'Борщ' }];
            mockHttp.get.mockResolvedValue({ data: mockDishes });
            mockExtractArray.mockReturnValue(mockDishes);

            const result = await stopList.searchDishes('Борщ');

            expect(mockHttp.get).toHaveBeenCalledWith('/stop-list/search-dishes', {
                params: { q: 'Борщ' },
            });
            expect(result).toEqual(mockDishes);
        });
    });

    describe('stopList.add', () => {
        it('should POST dish to stop list with reason', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await stopList.add(10, 'Out of stock');

            expect(mockHttp.post).toHaveBeenCalledWith('/stop-list', {
                dish_id: 10,
                reason: 'Out of stock',
                resume_at: null,
            });
        });

        it('should include resume_at when provided', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await stopList.add(10, 'Limited supply', '2026-02-12 10:00:00');

            expect(mockHttp.post).toHaveBeenCalledWith('/stop-list', {
                dish_id: 10,
                reason: 'Limited supply',
                resume_at: '2026-02-12 10:00:00',
            });
        });
    });

    describe('stopList.remove', () => {
        it('should DELETE /stop-list/:dishId', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await stopList.remove(10);

            expect(mockHttp.delete).toHaveBeenCalledWith('/stop-list/10');
        });
    });

    describe('stopList.update', () => {
        it('should PUT /stop-list/:dishId with reason and resume_at', async () => {
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await stopList.update(10, 'Updated reason', '2026-02-13 12:00:00');

            expect(mockHttp.put).toHaveBeenCalledWith('/stop-list/10', {
                reason: 'Updated reason',
                resume_at: '2026-02-13 12:00:00',
            });
        });

        it('should allow null resume_at', async () => {
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await stopList.update(10, 'Permanent stop', null);

            expect(mockHttp.put).toHaveBeenCalledWith('/stop-list/10', {
                reason: 'Permanent stop',
                resume_at: null,
            });
        });
    });
});
