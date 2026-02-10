/**
 * Waiter App - Menu Store
 * Manages categories and dishes state
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { menuApi } from '@/waiter/services';
import type { Category, Dish } from '@/waiter/types';

export const useMenuStore = defineStore('waiter-menu', () => {
  // === State ===
  const categories = ref<Category[]>([]);
  const dishes = ref<Dish[]>([]);
  const selectedCategoryId = ref<number | null>(null);
  const searchQuery = ref('');
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const lastFetchTime = ref<number>(0);

  // === Cache Settings ===
  const CACHE_TTL = 60000; // 1 minute

  // === Getters ===

  /**
   * Root categories (no parent)
   */
  const rootCategories = computed((): Category[] => {
    return categories.value.filter((c: any) => !c.parent_id && c.is_active);
  });

  /**
   * Selected category
   */
  const selectedCategory = computed((): Category | null => {
    if (!selectedCategoryId.value) return null;
    return categories.value.find((c: any) => c.id === selectedCategoryId.value) || null;
  });

  /**
   * Subcategories of selected category
   */
  const subcategories = computed((): Category[] => {
    if (!selectedCategoryId.value) return [];
    return categories.value.filter((c: any) =>
      c.parent_id === selectedCategoryId.value && c.is_active
    );
  });

  /**
   * Has subcategories
   */
  const hasSubcategories = computed((): boolean => {
    return subcategories.value.length > 0;
  });

  /**
   * Available dishes only
   */
  const availableDishes = computed((): Dish[] => {
    return dishes.value.filter((d: any) => d.is_available && !d.in_stop_list);
  });

  /**
   * Dishes filtered by selected category
   */
  const filteredDishes = computed((): Dish[] => {
    let result = availableDishes.value;

    // Filter by category
    if (selectedCategoryId.value) {
      // Include dishes from subcategories too
      const categoryIds = [selectedCategoryId.value];
      subcategories.value.forEach((sub: any) => categoryIds.push(sub.id));
      result = result.filter((d: any) => categoryIds.includes(d.category_id));
    }

    // Filter by search
    if (searchQuery.value.trim()) {
      const query = searchQuery.value.toLowerCase().trim();
      result = result.filter((d: any) =>
        d.name.toLowerCase().includes(query) ||
        d.description?.toLowerCase().includes(query)
      );
    }

    return result;
  });

  /**
   * Dishes in stop list
   */
  const stopListDishes = computed((): Dish[] => {
    return dishes.value.filter((d: any) => d.in_stop_list);
  });

  /**
   * Dishes grouped by category
   */
  const dishesByCategory = computed(() => {
    const grouped: Record<number, Dish[]> = {};

    for (const dish of availableDishes.value) {
      if (!grouped[dish.category_id]) {
        grouped[dish.category_id] = [];
      }
      grouped[dish.category_id].push(dish);
    }

    return grouped;
  });

  /**
   * Search results
   */
  const searchResults = computed((): Dish[] => {
    if (!searchQuery.value.trim()) return [];

    const query = searchQuery.value.toLowerCase().trim();
    return availableDishes.value.filter((d: any) =>
      d.name.toLowerCase().includes(query) ||
      d.description?.toLowerCase().includes(query)
    );
  });

  /**
   * Is searching
   */
  const isSearching = computed((): boolean => {
    return searchQuery.value.trim().length > 0;
  });

  // === Actions ===

  /**
   * Fetch categories
   */
  async function fetchCategories(): Promise<void> {
    try {
      const response = await menuApi.getCategories();
      if (response.success) {
        categories.value = response.data;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки категорий';
    }
  }

  /**
   * Fetch dishes
   */
  async function fetchDishes(): Promise<void> {
    try {
      const response = await menuApi.getAvailableDishes();
      if (response.success) {
        dishes.value = response.data;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки блюд';
    }
  }

  /**
   * Fetch all menu data
   */
  async function fetchAll(force = false): Promise<void> {
    // Check cache
    const now = Date.now();
    if (!force && lastFetchTime.value && (now - lastFetchTime.value) < CACHE_TTL) {
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const { categories: cats, dishes: dsh } = await menuApi.getFullMenu();
      categories.value = cats;
      dishes.value = dsh;
      lastFetchTime.value = now;
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки меню';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Search dishes
   */
  async function search(query: string): Promise<Dish[]> {
    if (!query.trim()) return [];

    try {
      const response = await menuApi.searchDishes(query);
      if (response.success) {
        return response.data;
      }
    } catch (e: any) {
      error.value = e.message;
    }

    return [] as any[];
  }

  /**
   * Select category
   */
  function selectCategory(categoryId: number | null): void {
    selectedCategoryId.value = categoryId;
    // Clear search when selecting category
    if (categoryId !== null) {
      searchQuery.value = '';
    }
  }

  /**
   * Set search query
   */
  function setSearchQuery(query: string): void {
    searchQuery.value = query;
    // Clear category when searching
    if (query.trim()) {
      selectedCategoryId.value = null;
    }
  }

  /**
   * Clear search
   */
  function clearSearch(): void {
    searchQuery.value = '';
  }

  /**
   * Get dish by ID
   */
  function getDishById(dishId: number): Dish | undefined {
    return dishes.value.find((d: any) => d.id === dishId);
  }

  /**
   * Get category by ID
   */
  function getCategoryById(categoryId: number): Category | undefined {
    return categories.value.find((c: any) => c.id === categoryId);
  }

  /**
   * Check if dish is available
   */
  function isDishAvailable(dishId: number): boolean {
    const dish = getDishById(dishId);
    return dish ? dish.is_available && !dish.in_stop_list : false;
  }

  /**
   * Clear error
   */
  function clearError(): void {
    error.value = null;
  }

  /**
   * Reset store
   */
  function $reset(): void {
    categories.value = [];
    dishes.value = [];
    selectedCategoryId.value = null;
    searchQuery.value = '';
    isLoading.value = false;
    error.value = null;
    lastFetchTime.value = 0;
  }

  return {
    // State
    categories,
    dishes,
    selectedCategoryId,
    searchQuery,
    isLoading,
    error,

    // Getters
    rootCategories,
    selectedCategory,
    subcategories,
    hasSubcategories,
    availableDishes,
    filteredDishes,
    stopListDishes,
    dishesByCategory,
    searchResults,
    isSearching,

    // Actions
    fetchCategories,
    fetchDishes,
    fetchAll,
    search,
    selectCategory,
    setSearchQuery,
    clearSearch,
    getDishById,
    getCategoryById,
    isDishAvailable,
    clearError,
    $reset,
  };
});
