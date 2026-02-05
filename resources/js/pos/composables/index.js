/**
 * POS Composables - Enterprise-level reusable logic
 *
 * Централизованные composables для переиспользования логики
 * между компонентами зала и доставки.
 */

// Клиенты
export { useCustomers } from './useCustomers';
export { useCurrentCustomer } from './useCurrentCustomer';

// Заказы - Enterprise
export { useOrderDiscounts } from './useOrderDiscounts';
export { useOrderCustomer } from './useOrderCustomer';
