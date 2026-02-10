/**
 * useCustomers Composable
 *
 * Centralized customer data management with caching, loading, and search.
 *
 * @module pos/composables/useCustomers
 */

import { ref, computed } from 'vue';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Customer } from '@/shared/types';

const log = createLogger('useCustomers');

// Shared state (singleton pattern for caching)
const customers = ref<Customer[]>([]);
const loading = ref(false);
const loaded = ref(false);
const lastLoadTime = ref<number | null>(null);

let loadingPromise: Promise<Customer[]> | null = null;

const CACHE_DURATION = 5 * 60 * 1000;

export function useCustomers() {
    const loadCustomers = async (force = false): Promise<Customer[]> => {
        if (!force && loaded.value && lastLoadTime.value) {
            const elapsed = Date.now() - lastLoadTime.value;
            if (elapsed < CACHE_DURATION) {
                return customers.value;
            }
        }

        if (loading.value && loadingPromise) {
            return loadingPromise;
        }

        loading.value = true;
        loadingPromise = (async () => {
            try {
                const response = await api.customers.getAll();
                customers.value = Array.isArray(response) ? response : [];
                loaded.value = true;
                lastLoadTime.value = Date.now();
                return customers.value;
            } catch (error: any) {
                log.error('Failed to load customers:', error);
                throw error;
            } finally {
                loading.value = false;
                loadingPromise = null;
            }
        })();
        return loadingPromise;
    };

    const searchCustomers = async (query: string): Promise<Customer[]> => {
        if (!query || query.length < 2) {
            return customers.value;
        }

        try {
            const response = await api.customers.search(query);
            return Array.isArray(response) ? response : [];
        } catch (error: any) {
            log.error('Search failed:', error);
            return filterCustomers(query);
        }
    };

    const filterCustomers = (query: string): Customer[] => {
        if (!query) return customers.value;

        const q = query.toLowerCase().trim();
        const digits = query.replace(/\D/g, '');

        return customers.value.filter((customer: any) => {
            if (customer.name?.toLowerCase().includes(q)) return true;

            if (digits.length >= 3) {
                const customerDigits = customer.phone?.replace(/\D/g, '') || '';
                if (customerDigits.includes(digits)) return true;
            }

            if (customer.email?.toLowerCase().includes(q)) return true;

            return false;
        });
    };

    const getCustomerById = (id: number): Customer | null => {
        return customers.value.find((c: any) => c.id === id) || null;
    };

    const getCustomerByPhone = (phone: string): Customer | null => {
        if (!phone) return null;
        const digits = phone.replace(/\D/g, '');
        return customers.value.find((c: any) => {
            const customerDigits = c.phone?.replace(/\D/g, '') || '';
            return customerDigits === digits;
        }) || null;
    };

    const refreshCustomers = (): Promise<Customer[]> => loadCustomers(true);

    const clearCache = (): void => {
        loaded.value = false;
        lastLoadTime.value = null;
    };

    const addToCache = (customer: Customer): void => {
        if (customer && customer.id) {
            const index = customers.value.findIndex((c: any) => c.id === customer.id);
            if (index >= 0) {
                customers.value.splice(index, 1, customer);
            } else {
                customers.value.unshift(customer);
            }
        }
    };

    const updateInCache = (customer: Customer): void => {
        if (customer && customer.id) {
            const index = customers.value.findIndex((c: any) => c.id === customer.id);
            if (index >= 0) {
                customers.value.splice(index, 1, customer);
            }
        }
    };

    const customersCount = computed(() => customers.value.length);
    const hasCustomers = computed(() => customers.value.length > 0);
    const isLoading = computed(() => loading.value);
    const isLoaded = computed(() => loaded.value);

    return {
        customers,
        loading: isLoading,
        loaded: isLoaded,
        customersCount,
        hasCustomers,
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
