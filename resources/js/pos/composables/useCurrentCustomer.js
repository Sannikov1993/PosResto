/**
 * useCurrentCustomer Composable
 *
 * Enterprise-level single source of truth for the current customer
 * in the context of an order or reservation.
 *
 * Features:
 * - Centralized customer state management
 * - Auto-sync with order/reservation changes
 * - Reactive bonus balance and loyalty level
 * - Fresh data loading from API
 *
 * Usage:
 *   const { customer, bonusBalance, setCustomer, loadFreshData } = useCurrentCustomer();
 *
 * @module pos/composables/useCurrentCustomer
 */

import { ref, computed, readonly } from 'vue';
import api from '../api';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:CurrentCustomer');

// Singleton state - shared across all component instances
const currentCustomer = ref(null);
const loading = ref(false);
const error = ref(null);

/**
 * Composable for current customer management
 * @returns {Object} Customer state and methods
 */
export function useCurrentCustomer() {
    // ==================== COMPUTED ====================

    /**
     * Customer ID
     */
    const customerId = computed(() => currentCustomer.value?.id || null);

    /**
     * Customer name
     */
    const customerName = computed(() => currentCustomer.value?.name || '');

    /**
     * Customer phone
     */
    const customerPhone = computed(() => currentCustomer.value?.phone || '');

    /**
     * Bonus balance (reactive, always fresh)
     */
    const bonusBalance = computed(() => currentCustomer.value?.bonus_balance || 0);

    /**
     * Loyalty level info
     */
    const loyaltyLevel = computed(() => currentCustomer.value?.loyalty_level || null);

    /**
     * Loyalty level name
     */
    const loyaltyLevelName = computed(() => currentCustomer.value?.loyalty_level?.name || '');

    /**
     * Loyalty discount percent
     */
    const loyaltyDiscount = computed(() => currentCustomer.value?.loyalty_level?.discount_percent || 0);

    /**
     * Check if customer is set
     */
    const hasCustomer = computed(() => !!currentCustomer.value?.id);

    /**
     * Check if customer is new (not saved in DB)
     */
    const isNewCustomer = computed(() => currentCustomer.value?.is_new === true || !currentCustomer.value?.id);

    // ==================== METHODS ====================

    /**
     * Set current customer
     * @param {Object|null} customer - Customer object or null to clear
     */
    const setCustomer = (customer) => {
        if (!customer) {
            currentCustomer.value = null;
            return;
        }

        currentCustomer.value = {
            id: customer.id,
            name: customer.name,
            phone: customer.phone,
            email: customer.email,
            bonus_balance: customer.bonus_balance || 0,
            loyalty_level: customer.loyalty_level || customer.loyaltyLevel || null,
            birthday: customer.birthday,
            notes: customer.notes,
            orders_count: customer.orders_count || 0,
            total_spent: customer.total_spent || 0,
            is_new: customer.is_new || false,
        };
    };

    /**
     * Set customer from order data
     * @param {Object} order - Order object with customer relation
     */
    const setFromOrder = (order) => {
        if (order?.customer) {
            setCustomer(order.customer);
        } else if (order?.customer_id) {
            // Only ID available - need to load full data
            loadById(order.customer_id);
        } else {
            currentCustomer.value = null;
        }
    };

    /**
     * Set customer from reservation data
     * @param {Object} reservation - Reservation object
     */
    const setFromReservation = (reservation) => {
        if (reservation?.customer) {
            setCustomer(reservation.customer);
        } else if (reservation?.customer_id) {
            loadById(reservation.customer_id);
        } else {
            currentCustomer.value = null;
        }
    };

    /**
     * Load customer by ID from API
     * @param {number} id - Customer ID
     */
    const loadById = async (id) => {
        if (!id) return;

        loading.value = true;
        error.value = null;

        try {
            const data = await api.customers.get(id);
            setCustomer(data);
        } catch (e) {
            log.error('Failed to load customer:', e);
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Load fresh customer data from API (update bonus balance, etc.)
     */
    const loadFreshData = async () => {
        if (!currentCustomer.value?.id) return;

        loading.value = true;
        error.value = null;

        try {
            const data = await api.customers.getById(currentCustomer.value.id);
            // Merge fresh data while preserving any local additions
            currentCustomer.value = {
                ...currentCustomer.value,
                ...data,
            };
        } catch (e) {
            log.error('Failed to refresh customer data:', e);
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update bonus balance locally (optimistic update)
     * @param {number} amount - New balance or delta
     * @param {boolean} isDelta - If true, amount is added to current balance
     */
    const updateBonusBalance = (amount, isDelta = false) => {
        if (!currentCustomer.value) return;

        if (isDelta) {
            currentCustomer.value.bonus_balance = (currentCustomer.value.bonus_balance || 0) + amount;
        } else {
            currentCustomer.value.bonus_balance = amount;
        }
    };

    /**
     * Clear current customer
     */
    const clear = () => {
        currentCustomer.value = null;
        error.value = null;
    };

    /**
     * Update customer data after edit
     * @param {Object} updatedData - Updated customer fields
     */
    const updateCustomer = (updatedData) => {
        if (!currentCustomer.value) return;

        currentCustomer.value = {
            ...currentCustomer.value,
            ...updatedData,
        };
    };

    // ==================== RETURN ====================

    return {
        // State (readonly to prevent direct mutation)
        customer: readonly(currentCustomer),
        loading: readonly(loading),
        error: readonly(error),

        // Computed
        customerId,
        customerName,
        customerPhone,
        bonusBalance,
        loyaltyLevel,
        loyaltyLevelName,
        loyaltyDiscount,
        hasCustomer,
        isNewCustomer,

        // Methods
        setCustomer,
        setFromOrder,
        setFromReservation,
        loadById,
        loadFreshData,
        updateBonusBalance,
        updateCustomer,
        clear,
    };
}

export default useCurrentCustomer;
