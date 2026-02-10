/**
 * POS API Module — Централизованные API вызовы
 *
 * Модульная архитектура: каждый домен вынесен в отдельный файл.
 * Этот файл — фасад, сохраняющий обратную совместимость.
 */

import http from './httpClient.js';
import auth from './modules/auth.js';
import { tables, zones } from './modules/tables.js';
import { orders, orderItems, cancellations } from './modules/orders.js';
import reservations from './modules/reservations.js';
import { shifts, cashOperations } from './modules/finance.js';
import customers from './modules/customers.js';
import { menu, priceLists, stopList } from './modules/menu.js';
import { delivery, couriers } from './modules/delivery.js';
import { inventory, warehouse } from './modules/warehouse.js';
import { loyalty, giftCertificates } from './modules/loyalty.js';
import bar from './modules/bar.js';
import writeOffs from './modules/writeOffs.js';
import settings from './modules/settings.js';
import { payroll, realtime, dashboard } from './modules/misc.js';
import type { AxiosRequestConfig } from 'axios';

// Generic HTTP helpers
const get = async (url: string, config: AxiosRequestConfig = {}): Promise<unknown> => {
    return http.get(url, config);
};

const post = async (url: string, data: unknown = {}, config: AxiosRequestConfig = {}): Promise<unknown> => {
    return http.post(url, data, config);
};

// Re-export auth service for convenience
export { default as authService } from '../../shared/services/auth.js';

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
