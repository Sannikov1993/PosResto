/**
 * useCustomers Composable
 *
 * Centralized customer data management with caching, loading, and search.
 * Provides a single source of truth for customer data across the application.
 *
 * @module pos/composables/useCustomers
 */

import { ref, computed } from 'vue';
import api from '../api';

// Shared state (singleton pattern for caching)
const customers = ref([]);
const loading = ref(false);
const loaded = ref(false);
const lastLoadTime = ref(null);

// Cache duration: 5 minutes
const CACHE_DURATION = 5 * 60 * 1000;

/**
 * Composable for customer data management
 * @returns {Object} Customer data and methods
 */
export function useCustomers() {
    /**
     * Load all customers with optional force refresh
     * @param {boolean} force - Force reload even if cached
     * @returns {Promise<Array>} List of customers
     */
    const loadCustomers = async (force = false) => {
        // Return cached data if valid
        if (!force && loaded.value && lastLoadTime.value) {
            const elapsed = Date.now() - lastLoadTime.value;
            if (elapsed < CACHE_DURATION) {
                return customers.value;
            }
        }

        // Prevent duplicate requests
        if (loading.value) {
            // Wait for current request to complete
            return new Promise((resolve) => {
                const check = setInterval(() => {
                    if (!loading.value) {
                        clearInterval(check);
                        resolve(customers.value);
                    }
                }, 100);
            });
        }

        loading.value = true;
        try {
            const response = await api.customers.getAll();
            customers.value = Array.isArray(response) ? response : (response?.data || []);
            loaded.value = true;
            lastLoadTime.value = Date.now();
            return customers.value;
        } catch (error) {
            console.error('[useCustomers] Failed to load customers:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Search customers by query (uses API for server-side search)
     * @param {string} query - Search query
     * @returns {Promise<Array>} Filtered customers
     */
    const searchCustomers = async (query) => {
        if (!query || query.length < 2) {
            return customers.value;
        }

        try {
            const response = await api.customers.search(query);
            return Array.isArray(response) ? response : (response?.data || []);
        } catch (error) {
            console.error('[useCustomers] Search failed:', error);
            // Fallback to local filter
            return filterCustomers(query);
        }
    };

    /**
     * Filter customers locally (instant, no API call)
     * @param {string} query - Search query
     * @returns {Array} Filtered customers
     */
    const filterCustomers = (query) => {
        if (!query) return customers.value;

        const q = query.toLowerCase().trim();
        const digits = query.replace(/\D/g, '');

        return customers.value.filter(customer => {
            // Search by name
            if (customer.name?.toLowerCase().includes(q)) return true;

            // Search by phone (compare digits only)
            if (digits.length >= 3) {
                const customerDigits = customer.phone?.replace(/\D/g, '') || '';
                if (customerDigits.includes(digits)) return true;
            }

            // Search by email
            if (customer.email?.toLowerCase().includes(q)) return true;

            return false;
        });
    };

    /**
     * Get customer by ID
     * @param {number} id - Customer ID
     * @returns {Object|null} Customer object or null
     */
    const getCustomerById = (id) => {
        return customers.value.find(c => c.id === id) || null;
    };

    /**
     * Get customer by phone
     * @param {string} phone - Phone number
     * @returns {Object|null} Customer object or null
     */
    const getCustomerByPhone = (phone) => {
        if (!phone) return null;
        const digits = phone.replace(/\D/g, '');
        return customers.value.find(c => {
            const customerDigits = c.phone?.replace(/\D/g, '') || '';
            return customerDigits === digits;
        }) || null;
    };

    /**
     * Refresh customer data (force reload)
     * @returns {Promise<Array>} Updated customers list
     */
    const refreshCustomers = () => loadCustomers(true);

    /**
     * Clear cache (useful after creating/updating customer)
     */
    const clearCache = () => {
        loaded.value = false;
        lastLoadTime.value = null;
    };

    /**
     * Add customer to local cache (after creation)
     * @param {Object} customer - New customer object
     */
    const addToCache = (customer) => {
        if (customer && customer.id) {
            // Remove if exists (update case)
            const index = customers.value.findIndex(c => c.id === customer.id);
            if (index >= 0) {
                customers.value.splice(index, 1, customer);
            } else {
                // Add to beginning (newest first)
                customers.value.unshift(customer);
            }
        }
    };

    /**
     * Update customer in local cache
     * @param {Object} customer - Updated customer object
     */
    const updateInCache = (customer) => {
        if (customer && customer.id) {
            const index = customers.value.findIndex(c => c.id === customer.id);
            if (index >= 0) {
                customers.value.splice(index, 1, customer);
            }
        }
    };

    // Computed
    const customersCount = computed(() => customers.value.length);
    const hasCustomers = computed(() => customers.value.length > 0);
    const isLoading = computed(() => loading.value);
    const isLoaded = computed(() => loaded.value);

    return {
        // State
        customers,
        loading: isLoading,
        loaded: isLoaded,
        customersCount,
        hasCustomers,

        // Methods
        loadCustomers,
        searchCustomers,
        filterCustomers,
        getCustomerById,
        getCustomerByPhone,
        refreshCustomers,
        clearCache,
        addToCache,
        updateInCache,
    };
}

export default useCustomers;
