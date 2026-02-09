/**
 * POS API Module — Централизованные API вызовы
 *
 * Модульная архитектура: каждый домен вынесен в отдельный файл.
 * Этот файл — фасад, сохраняющий обратную совместимость.
 */

import http from './httpClient';
import auth from './modules/auth';
import { tables, zones } from './modules/tables';
import { orders, orderItems, cancellations } from './modules/orders';
import reservations from './modules/reservations';
import { shifts, cashOperations } from './modules/finance';
import customers from './modules/customers';
import { menu, priceLists, stopList } from './modules/menu';
import { delivery, couriers } from './modules/delivery';
import { inventory, warehouse } from './modules/warehouse';
import { loyalty, giftCertificates } from './modules/loyalty';
import bar from './modules/bar';
import writeOffs from './modules/writeOffs';
import settings from './modules/settings';
import { payroll, realtime, dashboard } from './modules/misc';

// Generic HTTP helpers
const get = async (url, config = {}) => {
    return http.get(url, config);
};

const post = async (url, data = {}, config = {}) => {
    return http.post(url, data, config);
};

// Re-export auth service for convenience
export { default as authService } from '../../shared/services/auth';

// Export all API modules (backward-compatible default export)
export default {
    auth,
    bar,
    tables,
    zones,
    orders,
    reservations,
    shifts,
    cashOperations,
    customers,
    couriers,
    menu,
    priceLists,
    stopList,
    writeOffs,
    cancellations,
    orderItems,
    settings,
    loyalty,
    inventory,
    warehouse,
    delivery,
    giftCertificates,
    realtime,
    dashboard,
    payroll,
    // Generic helpers
    get,
    post
};
