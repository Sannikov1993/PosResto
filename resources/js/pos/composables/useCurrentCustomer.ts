/**
 * useCurrentCustomer Composable
 *
 * Single source of truth for the current customer
 * in the context of an order or reservation.
 *
 * @module pos/composables/useCurrentCustomer
 */

import { ref, computed, readonly } from 'vue';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Customer, Order, Reservation, LoyaltyLevel } from '@/shared/types';

const log = createLogger('POS:CurrentCustomer');

interface CurrentCustomerData {
    id: number;
    name: string;
    phone?: string;
    email?: string;
    bonus_balance: number;
    loyalty_level: LoyaltyLevel | null;
    birthday?: string;
    notes?: string;
    orders_count: number;
    total_spent: number;
    is_new: boolean;
    [key: string]: unknown;
}

// Singleton state
const currentCustomer = ref<CurrentCustomerData | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

export function useCurrentCustomer() {
    const customerId = computed(() => currentCustomer.value?.id || null);
    const customerName = computed(() => currentCustomer.value?.name || '');
    const customerPhone = computed(() => currentCustomer.value?.phone || '');
    const bonusBalance = computed(() => currentCustomer.value?.bonus_balance || 0);
    const loyaltyLevel = computed(() => currentCustomer.value?.loyalty_level || null);
    const loyaltyLevelName = computed(() => currentCustomer.value?.loyalty_level?.name || '');
    const loyaltyDiscount = computed(() => currentCustomer.value?.loyalty_level?.discount_percent || 0);
    const hasCustomer = computed(() => !!currentCustomer.value?.id);
    const isNewCustomer = computed(() => currentCustomer.value?.is_new === true || !currentCustomer.value?.id);

    const setCustomer = (customer: Customer | CurrentCustomerData | null): void => {
        if (!customer) {
            currentCustomer.value = null;
            return;
        }

        currentCustomer.value = {
            id: customer.id,
            name: customer.name,
            phone: customer.phone,
            email: customer.email,
            bonus_balance: (customer as Record<string, any>).bonus_balance as number || 0,
            loyalty_level: ((customer as Record<string, any>).loyalty_level || (customer as Record<string, any>).loyaltyLevel || null) as LoyaltyLevel | null,
            birthday: (customer as Record<string, any>).birthday as string,
            notes: (customer as Record<string, any>).notes as string,
            orders_count: (customer as Record<string, any>).orders_count as number || 0,
            total_spent: (customer as Record<string, any>).total_spent as number || 0,
            is_new: (customer as Record<string, any>).is_new as boolean || false,
        };
    };

    const setFromOrder = (order: Order | null): void => {
        if (order?.customer) {
            setCustomer(order.customer as unknown as Customer);
        } else if (order?.customer_id) {
            loadById(order.customer_id);
        } else {
            currentCustomer.value = null;
        }
    };

    const setFromReservation = (reservation: Reservation | null): void => {
        if (reservation?.customer) {
            setCustomer(reservation.customer as unknown as Customer);
        } else if (reservation?.customer_id) {
            loadById(reservation.customer_id);
        } else {
            currentCustomer.value = null;
        }
    };

    const loadById = async (id: number): Promise<void> => {
        if (!id) return;

        loading.value = true;
        error.value = null;

        try {
            const data = await api.customers.get(id);
            setCustomer(data);
        } catch (e: unknown) {
            log.error('Failed to load customer:', e);
            error.value = (e as Error).message;
        } finally {
            loading.value = false;
        }
    };

    const loadFreshData = async (): Promise<void> => {
        if (!currentCustomer.value?.id) return;

        loading.value = true;
        error.value = null;

        try {
            const data = await api.customers.get(currentCustomer.value.id);
            currentCustomer.value = {
                ...currentCustomer.value,
                ...(data as Record<string, any>),
            } as CurrentCustomerData;
        } catch (e: unknown) {
            log.error('Failed to refresh customer data:', e);
            error.value = (e as Error).message;
        } finally {
            loading.value = false;
        }
    };

    const updateBonusBalance = (amount: number, isDelta = false): void => {
        if (!currentCustomer.value) return;

        if (isDelta) {
            currentCustomer.value.bonus_balance = (currentCustomer.value.bonus_balance || 0) + amount;
        } else {
            currentCustomer.value.bonus_balance = amount;
        }
    };

    const clear = (): void => {
        currentCustomer.value = null;
        error.value = null;
    };

    const updateCustomer = (updatedData: Partial<CurrentCustomerData>): void => {
        if (!currentCustomer.value) return;

        currentCustomer.value = {
            ...currentCustomer.value,
            ...updatedData,
        };
    };

    return {
        customer: readonly(currentCustomer),
        loading: readonly(loading),
        error: readonly(error),
        customerId,
        customerName,
        customerPhone,
        bonusBalance,
        loyaltyLevel,
        loyaltyLevelName,
        loyaltyDiscount,
        hasCustomer,
        isNewCustomer,
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
