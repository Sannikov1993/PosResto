/**
 * Waiter App - API Services Index
 * Re-export all API services for convenient imports
 */

// Client and utilities
export {
  api,
  apiClient,
  getToken,
  setToken,
  removeToken,
  hasToken,
  parseApiError,
} from './client';

// API Services
export { authApi } from './authApi';
export { tablesApi } from './tablesApi';
export { ordersApi } from './ordersApi';
export { menuApi } from './menuApi';
export { customersApi } from './customersApi';
