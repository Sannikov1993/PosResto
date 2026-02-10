/**
 * Enterprise Composable: Управление клиентом заказа
 */

import { ref, computed } from 'vue';
import { useCurrentCustomer } from './useCurrentCustomer.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Customer, Order } from '@/shared/types';

const log = createLogger('POS:OrderCustomer');

interface OrderDiscountsInstance {
    hasDiscounts: { value: boolean };
    resetAllDiscounts: (showToast?: boolean) => void;
}

interface ApiInstance {
    post: (url: string, data?: Record<string, any>) => Promise<Record<string, any>>;
    delete: (url: string) => Promise<Record<string, any>>;
}

interface UseOrderCustomerOptions {
    discounts?: OrderDiscountsInstance | null;
    onCustomerChange?: ((customer: Customer, meta: { isChange: boolean }) => void) | null;
    onCustomerClear?: (() => void) | null;
    api?: ApiInstance | null;
}

interface SelectOptions {
    skipDiscountReset?: boolean;
}

export function useOrderCustomer(options: UseOrderCustomerOptions = {}) {
    const {
        discounts = null,
        onCustomerChange = null,
        onCustomerClear = null,
        api = null,
    } = options as any;

    const {
        bonusBalance: currentCustomerBonusBalance,
        setCustomer: setCurrentCustomer,
        clear: clearCurrentCustomer,
        updateCustomer: updateCurrentCustomer,
    } = useCurrentCustomer();

    const customerId = ref<number | null>(null);
    const customerData = ref<Customer | null>(null);
    const loading = ref(false);

    const hasCustomer = computed(() => !!customerId.value);
    const customerName = computed(() => customerData.value?.name || '');
    const customerPhone = computed(() => customerData.value?.phone || '');

    const customerBonusBalance = computed(() => {
        return (customerData.value as Record<string, any>)?.bonus_balance as number || currentCustomerBonusBalance.value || 0;
    });

    const customerLoyaltyLevel = computed(() => {
        const data = customerData.value as Record<string, any> | null;
        return data?.loyaltyLevel || data?.loyalty_level || null;
    });

    const loyaltyDiscountPercent = computed(() => {
        const level = customerLoyaltyLevel.value as Record<string, any> | null;
        return (level?.discount_percent as number) || 0;
    });

    const selectCustomer = (customer: Customer | null, { skipDiscountReset = false }: SelectOptions = {}): void => {
        if (!customer) {
            clearCustomer();
            return;
        }

        const previousCustomerId = customerId.value;
        const isCustomerChange = !!previousCustomerId && previousCustomerId !== customer.id;

        if (isCustomerChange && !skipDiscountReset && discounts) {
            discounts.resetAllDiscounts(true);
            log.debug('Discounts reset on customer change');
        }

        customerId.value = customer.id || null;
        customerData.value = customer;

        setCurrentCustomer(customer);

        if (onCustomerChange) {
            onCustomerChange(customer, { isChange: isCustomerChange });
        }

        log.debug('Customer selected:', customer.id, customer.name);
    };

    const clearCustomer = (): void => {
        const hadDiscounts = discounts?.hasDiscounts.value;

        customerId.value = null;
        customerData.value = null;

        clearCurrentCustomer();

        if (discounts) {
            discounts.resetAllDiscounts(hadDiscounts);
        }

        if (onCustomerClear) {
            onCustomerClear();
        }

        log.debug('Customer cleared');
    };

    const updateCustomer = (updatedCustomer: Customer): void => {
        if (!updatedCustomer || updatedCustomer.id !== customerId.value) {
            return;
        }

        customerData.value = updatedCustomer;
        updateCurrentCustomer(updatedCustomer as Record<string, any>);

        log.debug('Customer updated:', updatedCustomer.id);
    };

    const attachToOrder = async (orderId: number, customer: Customer): Promise<Record<string, any> | null> => {
        if (!api || !orderId || !customer?.id) {
            log.warn('Cannot attach: missing api, orderId or customer');
            return null;
        }

        loading.value = true;
        try {
            const result = await api.post(`/api/table-order/${orderId}/customer`, {
                customer_id: customer.id
            });

            if (result.success || result.order) {
                selectCustomer(((result.order as Record<string, any>)?.customer || customer) as Customer);
                return result;
            }

            return null;
        } catch (e: any) {
            log.error('Attach failed:', e);
            throw e;
        } finally {
            loading.value = false;
        }
    };

    const detachFromOrder = async (orderId: number): Promise<Record<string, any> | null> => {
        if (!api || !orderId) {
            log.warn('Cannot detach: missing api or orderId');
            return null;
        }

        loading.value = true;
        try {
            const result = await api.delete(`/api/table-order/${orderId}/customer`);

            if (result.success || result.order) {
                clearCustomer();
                return result;
            }

            return null;
        } catch (e: any) {
            log.error('Detach failed:', e);
            throw e;
        } finally {
            loading.value = false;
        }
    };

    const initFromOrder = (order: Order | null): void => {
        if (!order) {
            clearCustomer();
            return;
        }

        if (order.customer) {
            selectCustomer(order.customer as unknown as Customer, { skipDiscountReset: true });
        } else if (order.customer_id) {
            customerId.value = order.customer_id;
        } else {
            customerId.value = null;
            customerData.value = null;
        }

        log.debug('Initialized from order:', order.id);
    };

    return {
        customerId,
        customerData,
        loading,
        hasCustomer,
        customerName,
        customerPhone,
        customerBonusBalance,
        customerLoyaltyLevel,
        loyaltyDiscountPercent,
        selectCustomer,
        clearCustomer,
        updateCustomer,
        attachToOrder,
        detachFromOrder,
        initFromOrder,
    };
}

export default useOrderCustomer;
