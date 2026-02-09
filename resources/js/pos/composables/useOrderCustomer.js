/**
 * Enterprise Composable: Управление клиентом заказа
 *
 * Единый источник правды для работы с клиентом в заказе.
 * Автоматически сбрасывает скидки при смене/отвязке клиента.
 *
 * @example
 * const discounts = useOrderDiscounts();
 * const {
 *   customer,
 *   customerId,
 *   selectCustomer,
 *   clearCustomer,
 * } = useOrderCustomer({ discounts });
 */

import { ref, computed } from 'vue';
import { useCurrentCustomer } from './useCurrentCustomer';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:OrderCustomer');

export function useOrderCustomer(options = {}) {
    // Настройки
    const {
        discounts = null,           // Экземпляр useOrderDiscounts для автосброса
        onCustomerChange = null,    // Callback при смене клиента
        onCustomerClear = null,     // Callback при очистке клиента
        api = null,                 // API модуль для запросов к серверу
    } = options;

    // Интеграция с единым источником данных о клиенте
    const {
        bonusBalance: currentCustomerBonusBalance,
        setCustomer: setCurrentCustomer,
        clear: clearCurrentCustomer,
        updateCustomer: updateCurrentCustomer,
    } = useCurrentCustomer();

    // === State ===

    // ID клиента
    const customerId = ref(null);

    // Полные данные клиента
    const customerData = ref(null);

    // Флаг загрузки
    const loading = ref(false);

    // === Computed ===

    // Есть ли клиент
    const hasCustomer = computed(() => !!customerId.value);

    // Имя клиента
    const customerName = computed(() => customerData.value?.name || '');

    // Телефон клиента
    const customerPhone = computed(() => customerData.value?.phone || '');

    // Баланс бонусов клиента
    const customerBonusBalance = computed(() => {
        return customerData.value?.bonus_balance || currentCustomerBonusBalance.value || 0;
    });

    // Уровень лояльности клиента
    const customerLoyaltyLevel = computed(() => {
        return customerData.value?.loyaltyLevel ||
               customerData.value?.loyalty_level ||
               null;
    });

    // Скидка уровня лояльности (процент)
    const loyaltyDiscountPercent = computed(() => {
        return customerLoyaltyLevel.value?.discount_percent || 0;
    });

    // === Methods ===

    /**
     * Выбрать/сменить клиента
     * Enterprise: автоматически сбрасывает скидки при смене клиента
     *
     * @param {Object} customer - объект клиента
     * @param {Object} options - опции
     * @param {boolean} options.skipDiscountReset - не сбрасывать скидки (для первичной загрузки)
     */
    const selectCustomer = (customer, { skipDiscountReset = false } = {}) => {
        if (!customer) {
            clearCustomer();
            return;
        }

        // Проверяем смену клиента
        const previousCustomerId = customerId.value;
        const isCustomerChange = previousCustomerId && previousCustomerId !== customer.id;

        // Enterprise: сброс скидок при смене клиента
        if (isCustomerChange && !skipDiscountReset && discounts) {
            discounts.resetAllDiscounts(true);
            log.debug('Discounts reset on customer change');
        }

        // Устанавливаем нового клиента
        customerId.value = customer.id || null;
        customerData.value = customer;

        // Синхронизируем с глобальным состоянием
        setCurrentCustomer(customer);

        // Callback
        if (onCustomerChange) {
            onCustomerChange(customer, { isChange: isCustomerChange });
        }

        log.debug('Customer selected:', customer.id, customer.name);
    };

    /**
     * Очистить клиента
     * Enterprise: автоматически сбрасывает ВСЕ скидки
     */
    const clearCustomer = () => {
        const hadDiscounts = discounts?.hasDiscounts.value;

        customerId.value = null;
        customerData.value = null;

        // Синхронизируем с глобальным состоянием
        clearCurrentCustomer();

        // Enterprise: сброс ВСЕХ скидок при отвязке клиента
        if (discounts) {
            discounts.resetAllDiscounts(hadDiscounts);
        }

        // Callback
        if (onCustomerClear) {
            onCustomerClear();
        }

        log.debug('Customer cleared');
    };

    /**
     * Обновить данные клиента (после редактирования)
     * @param {Object} updatedCustomer - обновлённые данные
     */
    const updateCustomer = (updatedCustomer) => {
        if (!updatedCustomer || updatedCustomer.id !== customerId.value) {
            return;
        }

        customerData.value = updatedCustomer;
        updateCurrentCustomer(updatedCustomer);

        log.debug('Customer updated:', updatedCustomer.id);
    };

    /**
     * Привязать клиента к заказу на сервере
     * @param {number} orderId - ID заказа
     * @param {Object} customer - объект клиента
     */
    const attachToOrder = async (orderId, customer) => {
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
                // Сервер мог изменить данные - синхронизируемся
                selectCustomer(result.order?.customer || customer);
                return result;
            }

            return null;
        } catch (e) {
            log.error('Attach failed:', e);
            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Отвязать клиента от заказа на сервере
     * @param {number} orderId - ID заказа
     */
    const detachFromOrder = async (orderId) => {
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
        } catch (e) {
            log.error('Detach failed:', e);
            throw e;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Инициализировать из данных заказа
     * @param {Object} order - объект заказа
     */
    const initFromOrder = (order) => {
        if (!order) {
            clearCustomer();
            return;
        }

        if (order.customer) {
            selectCustomer(order.customer, { skipDiscountReset: true });
        } else if (order.customer_id) {
            customerId.value = order.customer_id;
            // Данные клиента нужно загрузить отдельно
        } else {
            customerId.value = null;
            customerData.value = null;
        }

        log.debug('Initialized from order:', order.id);
    };

    // === Return ===
    return {
        // State (refs)
        customerId,
        customerData,
        loading,

        // Computed
        hasCustomer,
        customerName,
        customerPhone,
        customerBonusBalance,
        customerLoyaltyLevel,
        loyaltyDiscountPercent,

        // Methods
        selectCustomer,
        clearCustomer,
        updateCustomer,
        attachToOrder,
        detachFromOrder,
        initFromOrder,
    };
}

export default useOrderCustomer;
