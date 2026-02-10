/**
 * Menu Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useMenuStore } from '@/waiter/stores/menu';

// Mock the menu API
vi.mock('@/waiter/services', () => ({
  menuApi: {
    getCategories: vi.fn(),
    getAvailableDishes: vi.fn(),
    getFullMenu: vi.fn(),
    searchDishes: vi.fn(),
  },
}));

import { menuApi } from '@/waiter/services';

describe('Menu Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useMenuStore();

      expect(store.categories).toEqual([]);
      expect(store.dishes).toEqual([]);
      expect(store.selectedCategoryId).toBeNull();
      expect(store.searchQuery).toBe('');
      expect(store.isLoading).toBe(false);
      expect(store.error).toBeNull();
    });
  });

  describe('fetchAll', () => {
    it('should fetch categories and dishes', async () => {
      const mockData = {
        categories: [
          { id: 1, name: 'Пицца', parent_id: null, is_active: true },
          { id: 2, name: 'Паста', parent_id: null, is_active: true },
        ],
        dishes: [
          { id: 1, name: 'Маргарита', category_id: 1, is_available: true, in_stop_list: false, price: 500 },
          { id: 2, name: 'Карбонара', category_id: 2, is_available: true, in_stop_list: false, price: 450 },
        ],
      };

      vi.mocked(menuApi.getFullMenu).mockResolvedValue(mockData as any);

      const store = useMenuStore();
      await store.fetchAll();

      expect(store.categories).toEqual(mockData.categories);
      expect(store.dishes).toEqual(mockData.dishes);
      expect(store.isLoading).toBe(false);
    });

    it('should handle fetch error', async () => {
      vi.mocked(menuApi.getFullMenu).mockRejectedValue(new Error('Network error'));

      const store = useMenuStore();
      await store.fetchAll();

      expect(store.error).toBe('Network error');
    });
  });

  describe('Getters', () => {
    it('rootCategories should return categories without parent', () => {
      const store = useMenuStore();
      store.categories = [
        { id: 1, name: 'Пицца', parent_id: null, is_active: true },
        { id: 2, name: 'Паста', parent_id: null, is_active: true },
        { id: 3, name: 'Детская пицца', parent_id: 1, is_active: true },
        { id: 4, name: 'Неактивная', parent_id: null, is_active: false },
      ] as any;

      expect(store.rootCategories).toHaveLength(2);
      expect(store.rootCategories.every(c => !c.parent_id && c.is_active)).toBe(true);
    });

    it('selectedCategory should return category by id', () => {
      const store = useMenuStore();
      store.categories = [
        { id: 1, name: 'Пицца' },
        { id: 2, name: 'Паста' },
      ] as any;
      store.selectedCategoryId = 2;

      expect(store.selectedCategory?.name).toBe('Паста');
    });

    it('subcategories should return children of selected category', () => {
      const store = useMenuStore();
      store.categories = [
        { id: 1, name: 'Пицца', parent_id: null, is_active: true },
        { id: 2, name: 'Детская', parent_id: 1, is_active: true },
        { id: 3, name: 'Острая', parent_id: 1, is_active: true },
        { id: 4, name: 'Паста', parent_id: null, is_active: true },
      ] as any;
      store.selectedCategoryId = 1;

      expect(store.subcategories).toHaveLength(2);
      expect(store.subcategories.every(c => c.parent_id === 1)).toBe(true);
    });

    it('availableDishes should filter available and not in stop list', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, is_available: true, in_stop_list: false },
        { id: 2, is_available: false, in_stop_list: false },
        { id: 3, is_available: true, in_stop_list: true },
        { id: 4, is_available: true, in_stop_list: false },
      ] as any;

      expect(store.availableDishes).toHaveLength(2);
      expect(store.availableDishes.map(d => d.id)).toEqual([1, 4]);
    });

    it('filteredDishes should filter by category', () => {
      const store = useMenuStore();
      store.categories = [
        { id: 1, name: 'Пицца', parent_id: null, is_active: true },
      ] as any;
      store.dishes = [
        { id: 1, category_id: 1, is_available: true, in_stop_list: false },
        { id: 2, category_id: 1, is_available: true, in_stop_list: false },
        { id: 3, category_id: 2, is_available: true, in_stop_list: false },
      ] as any;
      store.selectedCategoryId = 1;

      expect(store.filteredDishes).toHaveLength(2);
      expect(store.filteredDishes.every(d => d.category_id === 1)).toBe(true);
    });

    it('filteredDishes should filter by search query', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, name: 'Маргарита', is_available: true, in_stop_list: false, category_id: 1 },
        { id: 2, name: 'Пепперони', is_available: true, in_stop_list: false, category_id: 1 },
        { id: 3, name: 'Карбонара', is_available: true, in_stop_list: false, category_id: 2 },
      ] as any;
      store.searchQuery = 'мар';

      expect(store.filteredDishes).toHaveLength(1);
      expect(store.filteredDishes[0].name).toBe('Маргарита');
    });

    it('stopListDishes should return dishes in stop list', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, in_stop_list: false },
        { id: 2, in_stop_list: true },
        { id: 3, in_stop_list: true },
      ] as any;

      expect(store.stopListDishes).toHaveLength(2);
    });

    it('dishesByCategory should group dishes', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, category_id: 1, is_available: true, in_stop_list: false },
        { id: 2, category_id: 1, is_available: true, in_stop_list: false },
        { id: 3, category_id: 2, is_available: true, in_stop_list: false },
      ] as any;

      expect(store.dishesByCategory[1]).toHaveLength(2);
      expect(store.dishesByCategory[2]).toHaveLength(1);
    });

    it('isSearching should be true when searchQuery is not empty', () => {
      const store = useMenuStore();

      expect(store.isSearching).toBe(false);

      store.searchQuery = 'pizza';
      expect(store.isSearching).toBe(true);

      store.searchQuery = '   ';
      expect(store.isSearching).toBe(false);
    });
  });

  describe('Actions', () => {
    it('selectCategory should update selectedCategoryId and clear search', () => {
      const store = useMenuStore();
      store.searchQuery = 'test';

      store.selectCategory(5);

      expect(store.selectedCategoryId).toBe(5);
      expect(store.searchQuery).toBe('');
    });

    it('setSearchQuery should update query and clear category', () => {
      const store = useMenuStore();
      store.selectedCategoryId = 1;

      store.setSearchQuery('pizza');

      expect(store.searchQuery).toBe('pizza');
      expect(store.selectedCategoryId).toBeNull();
    });

    it('clearSearch should reset searchQuery', () => {
      const store = useMenuStore();
      store.searchQuery = 'test';

      store.clearSearch();

      expect(store.searchQuery).toBe('');
    });

    it('getDishById should return dish', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, name: 'Маргарита' },
        { id: 2, name: 'Пепперони' },
      ] as any;

      expect(store.getDishById(2)?.name).toBe('Пепперони');
      expect(store.getDishById(999)).toBeUndefined();
    });

    it('getCategoryById should return category', () => {
      const store = useMenuStore();
      store.categories = [
        { id: 1, name: 'Пицца' },
        { id: 2, name: 'Паста' },
      ] as any;

      expect(store.getCategoryById(1)?.name).toBe('Пицца');
      expect(store.getCategoryById(999)).toBeUndefined();
    });

    it('isDishAvailable should check availability', () => {
      const store = useMenuStore();
      store.dishes = [
        { id: 1, is_available: true, in_stop_list: false },
        { id: 2, is_available: false, in_stop_list: false },
        { id: 3, is_available: true, in_stop_list: true },
      ] as any;

      expect(store.isDishAvailable(1)).toBe(true);
      expect(store.isDishAvailable(2)).toBe(false);
      expect(store.isDishAvailable(3)).toBe(false);
      expect(store.isDishAvailable(999)).toBe(false);
    });
  });

  describe('$reset', () => {
    it('should reset store to initial state', () => {
      const store = useMenuStore();

      store.categories = [{ id: 1 }] as any;
      store.dishes = [{ id: 1 }] as any;
      store.selectedCategoryId = 1;
      store.searchQuery = 'test';
      store.error = 'Error';

      store.$reset();

      expect(store.categories).toEqual([]);
      expect(store.dishes).toEqual([]);
      expect(store.selectedCategoryId).toBeNull();
      expect(store.searchQuery).toBe('');
      expect(store.error).toBeNull();
    });
  });
});
