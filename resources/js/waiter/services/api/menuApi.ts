/**
 * Waiter App - Menu API Service
 * Handles categories, dishes, and modifiers API calls
 */

import { api } from './client';
import type {
  ApiResponse,
  CategoriesResponse,
  DishesResponse,
  DishResponse,
  GetDishesParams,
  Category,
  Dish,
  ModifierGroup,
} from '@/waiter/types';

export const menuApi = {
  // === Categories ===

  /**
   * Get all categories (hierarchical)
   */
  async getCategories(): Promise<CategoriesResponse> {
    return api.get<CategoriesResponse>('/menu/categories');
  },

  /**
   * Get category by ID
   */
  async getCategory(categoryId: number): Promise<ApiResponse<Category>> {
    return api.get<ApiResponse<Category>>(`/menu/categories/${categoryId}`);
  },

  /**
   * Get root categories only
   */
  async getRootCategories(): Promise<CategoriesResponse> {
    return api.get<CategoriesResponse>('/menu/categories', { root_only: true });
  },

  /**
   * Get subcategories
   */
  async getSubcategories(parentId: number): Promise<CategoriesResponse> {
    return api.get<CategoriesResponse>('/menu/categories', { parent_id: parentId });
  },

  // === Dishes ===

  /**
   * Get dishes with optional filters
   */
  async getDishes(params?: GetDishesParams): Promise<DishesResponse> {
    return api.get<DishesResponse>('/menu/dishes', params);
  },

  /**
   * Get dishes by category
   */
  async getDishesByCategory(categoryId: number): Promise<DishesResponse> {
    return this.getDishes({ category_id: categoryId, available_only: true });
  },

  /**
   * Get dish by ID
   */
  async getDish(dishId: number): Promise<DishResponse> {
    return api.get<DishResponse>(`/menu/dishes/${dishId}`);
  },

  /**
   * Search dishes
   */
  async searchDishes(query: string): Promise<DishesResponse> {
    return this.getDishes({ search: query, available_only: true });
  },

  /**
   * Get available dishes only
   */
  async getAvailableDishes(): Promise<DishesResponse> {
    return this.getDishes({ available_only: true });
  },

  // === Modifiers ===

  /**
   * Get modifiers for dish
   */
  async getDishModifiers(dishId: number): Promise<ApiResponse<ModifierGroup[]>> {
    return api.get<ApiResponse<ModifierGroup[]>>(`/menu/dishes/${dishId}/modifiers`);
  },

  // === Stop List ===

  /**
   * Get stop list (unavailable dishes)
   */
  async getStopList(): Promise<DishesResponse> {
    return api.get<DishesResponse>('/menu/stop-list');
  },

  /**
   * Check if dish is in stop list
   */
  async checkAvailability(dishId: number): Promise<ApiResponse<{ available: boolean; reason?: string }>> {
    return api.get(`/menu/dishes/${dishId}/availability`);
  },

  // === Batch Operations ===

  /**
   * Get full menu (categories + dishes)
   */
  async getFullMenu(): Promise<{ categories: Category[]; dishes: Dish[] }> {
    const [categoriesRes, dishesRes] = await Promise.all([
      this.getCategories(),
      this.getAvailableDishes(),
    ]);

    return {
      categories: categoriesRes.success ? categoriesRes.data : [],
      dishes: dishesRes.success ? dishesRes.data : [],
    };
  },

  /**
   * Get menu for display (grouped by categories)
   */
  async getMenuGrouped(): Promise<Map<number, Dish[]>> {
    const { dishes } = await this.getFullMenu();

    const grouped = new Map<number, Dish[]>();
    for (const dish of dishes) {
      if (!grouped.has(dish.category_id)) {
        grouped.set(dish.category_id, []);
      }
      grouped.get(dish.category_id)!.push(dish);
    }

    return grouped;
  },
};
