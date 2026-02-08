/**
 * POS API Module - Централизованные API вызовы
 */

import axios from 'axios';
import authService from '../../shared/services/auth';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:API');

const API_BASE = '/api';

// Create axios instance
const http = axios.create({
    baseURL: API_BASE,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor — добавляем Bearer токен из централизованного auth сервиса
http.interceptors.request.use(config => {
    const authHeader = authService.getAuthHeader();
    if (authHeader) {
        config.headers.Authorization = authHeader;
    }
    return config;
});

// ==================== 401 RETRY LOGIC ====================
// Флаг: идёт ли сейчас проверка токена
let isRefreshing = false;
// Очередь запросов, ожидающих завершения проверки токена
let failedQueue = [];

/**
 * Обработать очередь запросов после проверки токена
 */
function processQueue(error, token = null) {
    failedQueue.forEach(({ resolve, reject }) => {
        if (error) {
            reject(error);
        } else {
            resolve(token);
        }
    });
    failedQueue = [];
}

// Response interceptor
http.interceptors.response.use(
    response => {
        const data = response.data;

        // Если API явно вернул success: false - это ошибка бизнес-логики
        if (data?.success === false) {
            const error = new Error(data.message || 'API Error');
            error.response = { data };
            error.isApiError = true;
            throw error;
        }

        // Возвращаем data как есть (сохраняем структуру ответа)
        return data;
    },
    async error => {
        const originalRequest = error.config;

        // 401 обработка: retry-once с ревалидацией токена
        if (error.response?.status === 401 && !originalRequest._retry) {
            // Если уже идёт проверка — ставим запрос в очередь
            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then(token => {
                    originalRequest.headers.Authorization = `Bearer ${token}`;
                    return http(originalRequest);
                });
            }

            originalRequest._retry = true;
            isRefreshing = true;

            try {
                // Пробуем ревалидировать токен через auth/check (raw axios, минуя interceptor)
                const session = authService.getSession();
                if (session?.token) {
                    const checkResponse = await axios.get(`${API_BASE}/auth/check`, {
                        headers: { Authorization: `Bearer ${session.token}` }
                    });

                    if (checkResponse.data?.success) {
                        // Токен валиден — транзиентная ошибка, повторяем запрос
                        log.info('Token revalidated, retrying request');
                        isRefreshing = false;
                        processQueue(null, session.token);
                        originalRequest.headers.Authorization = `Bearer ${session.token}`;
                        return http(originalRequest);
                    }
                }

                // Токен невалиден — реальная экспирация
                throw new Error('Token expired');
            } catch (refreshError) {
                // Токен действительно истёк — logout
                isRefreshing = false;
                processQueue(refreshError);
                log.error('Session expired (401), token invalid — logging out');
                authService.clearAuth();
                window.dispatchEvent(new Event('auth:session-expired'));
                return Promise.reject(error);
            }
        }

        log.error('API Error', error.response?.data || error.message);
        throw error;
    }
);

// ==================== AUTH ====================
const auth = {
    async loginWithPin(pin, userId = null) {
        const { data } = await axios.post(`${API_BASE}/auth/login-pin`, {
            pin,
            app_type: 'pos',
            user_id: userId,
        });
        return data;
    },

    async checkAuth(token) {
        const { data } = await axios.get(`${API_BASE}/auth/check`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        return data;
    },

    async logout(token) {
        await axios.post(`${API_BASE}/auth/logout`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });
    }
};

// Helper: извлекает массив из ответа { data: [...] } или возвращает как есть
const extractArray = (response) => {
    if (Array.isArray(response)) return response;
    if (response?.data && Array.isArray(response.data)) return response.data;
    return response || [];
};

// Helper: извлекает объект из ответа { data: {...} } или возвращает как есть
const extractData = (response) => {
    if (response?.data !== undefined) return response.data;
    return response;
};

// ==================== TABLES ====================
const tables = {
    async getAll() {
        const res = await http.get('/tables');
        return extractArray(res);
    },

    async get(id) {
        const res = await http.get(`/tables/${id}`);
        return extractData(res);
    },

    async getOrders(id) {
        const res = await http.get(`/tables/${id}/orders`);
        return extractArray(res);
    },

    async getOrderData(id, params = {}) {
        return http.get(`/tables/${id}/order-data`, { params });
    }
};

// ==================== ZONES ====================
const zones = {
    async getAll() {
        const res = await http.get('/zones');
        return extractArray(res);
    }
};

// ==================== ORDERS ====================
const orders = {
    async getAll(params = {}) {
        const res = await http.get('/orders', { params });
        return extractArray(res);
    },

    async getActive() {
        const res = await http.get('/orders', {
            params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' }
        });
        return extractArray(res);
    },

    async getPaidToday() {
        const res = await http.get('/orders', { params: { paid_today: true } });
        return extractArray(res);
    },

    async getDelivery() {
        const res = await http.get('/delivery/orders');
        return extractArray(res);
    },

    async createDelivery(orderData) {
        return http.post('/delivery/orders', orderData);
    },

    async updateDeliveryStatus(orderId, deliveryStatus) {
        return http.patch(`/delivery/orders/${orderId}/status`, {
            delivery_status: deliveryStatus
        });
    },

    async get(id) {
        return http.get(`/orders/${id}`);
    },

    async create(orderData) {
        return http.post('/orders', orderData);
    },

    async update(id, orderData) {
        return http.put(`/orders/${id}`, orderData);
    },

    async pay(id, paymentData) {
        return http.post(`/orders/${id}/pay`, paymentData);
    },

    async cancel(id, reason, managerId, isWriteOff = false) {
        return http.post(`/orders/${id}/cancel-with-writeoff`, {
            reason,
            manager_id: managerId,
            is_write_off: isWriteOff
        });
    },

    async requestCancellation(id, reason, requestedBy = null) {
        return http.post(`/orders/${id}/request-cancellation`, {
            reason,
            requested_by: requestedBy
        });
    },

    // Печать
    async printReceipt(id) {
        return http.post(`/orders/${id}/print/receipt`);
    },

    async printPrecheck(id) {
        return http.post(`/orders/${id}/print/precheck`);
    },

    async printToKitchen(id) {
        return http.post(`/orders/${id}/print/kitchen`);
    },

    async getReceiptData(id) {
        return http.get(`/orders/${id}/print/data`);
    },

    // Перенос заказа
    async transfer(id, targetTableId, force = false) {
        return http.post(`/orders/${id}/transfer`, { target_table_id: targetTableId, force });
    },

    // Оплата (v1 API)
    async payV1(id, paymentData) {
        return http.post(`/v1/orders/${id}/pay`, paymentData);
    },

    async printReceiptV1(id) {
        return http.post(`/v1/orders/${id}/print/receipt`);
    },

    async getPaymentSplitPreview(id) {
        return http.get(`/v1/orders/${id}/payment-split-preview`);
    }
};

// ==================== RESERVATIONS ====================
const reservations = {
    async getAll(params = {}) {
        const res = await http.get('/reservations', { params });
        return extractArray(res);
    },

    async getByDate(date) {
        const res = await http.get('/reservations', { params: { date } });
        return extractArray(res);
    },

    async getByTable(tableId, date) {
        const res = await http.get('/reservations', { params: { table_id: tableId, date } });
        return extractArray(res);
    },

    async getCalendar(year, month) {
        const res = await http.get('/reservations/calendar', { params: { year, month } });
        return extractData(res);
    },

    async create(data) {
        return http.post('/reservations', data);
    },

    async update(id, data) {
        return http.put(`/reservations/${id}`, data);
    },

    async cancel(id, reason = null, refundDeposit = false, refundMethod = 'cash') {
        return http.post(`/reservations/${id}/cancel`, {
            reason,
            refund_deposit: refundDeposit,
            refund_method: refundMethod
        });
    },

    async seat(id) {
        return http.post(`/reservations/${id}/seat`);
    },

    async seatWithOrder(id) {
        return http.post(`/reservations/${id}/seat-with-order`);
    },

    async unseat(id) {
        return http.post(`/reservations/${id}/unseat`);
    },

    async delete(id) {
        return http.delete(`/reservations/${id}`);
    },

    async checkConflict(tableId, date, timeFrom, timeTo, excludeId = null) {
        return http.post('/reservations/check-conflict', {
            table_id: tableId,
            date,
            time_from: timeFrom,
            time_to: timeTo,
            exclude_id: excludeId
        });
    },

    // Депозит
    async payDeposit(id, method, amount = null) {
        const payload = { method };
        if (amount !== null) payload.amount = amount;
        return http.post(`/reservations/${id}/deposit/pay`, payload);
    },

    async refundDeposit(id, reason = null) {
        return http.post(`/reservations/${id}/deposit/refund`, { reason });
    },

    async getBusinessDate() {
        try {
            return await http.get('/reservations/business-date');
        } catch {
            return null;
        }
    },

    // Preorder items
    async getPreorderItems(reservationId) {
        return http.get(`/reservations/${reservationId}/preorder-items`);
    },

    async addPreorderItem(reservationId, data) {
        return http.post(`/reservations/${reservationId}/preorder-items`, data);
    },

    async updatePreorderItem(reservationId, itemId, data) {
        return http.patch(`/reservations/${reservationId}/preorder-items/${itemId}`, data);
    },

    async deletePreorderItem(reservationId, itemId) {
        return http.delete(`/reservations/${reservationId}/preorder-items/${itemId}`);
    },

    async printPreorder(reservationId) {
        return http.post(`/reservations/${reservationId}/print-preorder`);
    }
};

// ==================== SHIFTS ====================
const shifts = {
    async getAll() {
        const res = await http.get('/finance/shifts');
        return extractArray(res);
    },

    async getCurrent() {
        try {
            const response = await http.get('/finance/shifts/current');
            const data = extractData(response);
            // API возвращает null когда смена закрыта
            return data?.id ? data : null;
        } catch {
            return null;
        }
    },

    async getLastBalance() {
        try {
            const res = await http.get('/finance/shifts/last-balance');
            return extractData(res) || { closing_amount: 0 };
        } catch {
            return { closing_amount: 0 };
        }
    },

    async get(id) {
        const res = await http.get(`/finance/shifts/${id}`);
        return extractData(res);
    },

    async getOrders(id) {
        const res = await http.get(`/finance/shifts/${id}/orders`);
        return extractArray(res);
    },

    async getPrepayments(id) {
        const res = await http.get(`/finance/shifts/${id}/prepayments`);
        return extractArray(res);
    },

    async open(openingAmount, cashierId = null) {
        return http.post('/finance/shifts/open', {
            opening_cash: openingAmount,
            cashier_id: cashierId
            // restaurant_id берётся из авторизованного пользователя на бэкенде
        });
    },

    async close(id, closingAmount) {
        return http.post(`/finance/shifts/${id}/close`, {
            closing_amount: closingAmount
        });
    }
};

// ==================== CASH OPERATIONS ====================
const cashOperations = {
    // Внесение денег в кассу
    async deposit(amount, description = null) {
        return http.post('/finance/operations/deposit', {
            amount,
            description
            // restaurant_id берётся из авторизованного пользователя на бэкенде
        });
    },

    // Изъятие денег из кассы
    async withdrawal(amount, category, description = null) {
        return http.post('/finance/operations/withdrawal', {
            amount,
            category,
            description
            // restaurant_id берётся из авторизованного пользователя на бэкенде
        });
    },

    // Предоплата за заказ (доставка/самовывоз)
    async orderPrepayment(amount, paymentMethod, customerName = null, orderType = 'delivery', orderId = null, orderNumber = null) {
        return http.post('/finance/operations/order-prepayment', {
            amount,
            payment_method: paymentMethod,
            customer_name: customerName,
            order_type: orderType,
            order_id: orderId,
            order_number: orderNumber
            // restaurant_id берётся из авторизованного пользователя на бэкенде
        });
    },

    // Возврат денег за отменённый заказ
    async refund(amount, refundMethod, orderId = null, orderNumber = null, reason = null) {
        return http.post('/finance/operations/refund', {
            amount,
            refund_method: refundMethod,
            order_id: orderId,
            order_number: orderNumber,
            reason
            // restaurant_id берётся из авторизованного пользователя на бэкенде
        });
    },

    // История операций
    async getAll(params = {}) {
        const res = await http.get('/finance/operations', { params });
        return extractArray(res);
    }
};

// ==================== CUSTOMERS ====================
const customers = {
    async getAll(params = {}) {
        const res = await http.get('/customers', { params });
        return extractArray(res);
    },

    async search(query, limit = 10) {
        const res = await http.get('/customers/search', { params: { q: query, limit } });
        return extractArray(res);
    },

    async get(id) {
        const res = await http.get(`/customers/${id}`);
        return extractData(res);
    },

    async create(data) {
        return http.post('/customers', data);
    },

    async update(id, data) {
        return http.put(`/customers/${id}`, data);
    },

    async getOrders(id) {
        const res = await http.get(`/customers/${id}/orders`);
        return extractArray(res);
    },

    async getAddresses(id) {
        const res = await http.get(`/customers/${id}/addresses`);
        return extractArray(res);
    },

    async getBonusHistory(id) {
        const res = await http.get(`/customers/${id}/bonus-history`);
        return extractArray(res);
    },

    async toggleBlacklist(id) {
        return http.post(`/customers/${id}/toggle-blacklist`);
    },

    async saveDeliveryAddress(customerId, addressData) {
        return http.post(`/customers/${customerId}/save-delivery-address`, addressData);
    },

    async deleteAddress(customerId, addressId) {
        return http.delete(`/customers/${customerId}/addresses/${addressId}`);
    },

    async setDefaultAddress(customerId, addressId) {
        return http.post(`/customers/${customerId}/addresses/${addressId}/set-default`);
    }
};

// ==================== COURIERS ====================
const couriers = {
    async getAll() {
        const res = await http.get('/delivery/couriers');
        return extractArray(res);
    },

    async assign(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
    }
};

// ==================== MENU ====================
const menu = {
    async getAll(priceListId = null) {
        const params = {};
        if (priceListId) params.price_list_id = priceListId;
        const res = await http.get('/menu', { params });
        return extractData(res);
    },

    async getCategories() {
        const res = await http.get('/categories');
        return extractArray(res);
    },

    async getDishes(categoryId = null, priceListId = null) {
        const params = { available: 1 }; // Only available dishes for POS
        if (categoryId) {
            params.category_id = categoryId;
        }
        if (priceListId) {
            params.price_list_id = priceListId;
        }
        const res = await http.get('/dishes', { params });
        return extractArray(res);
    },

    async getDish(id) {
        const res = await http.get(`/dishes/${id}`);
        return extractData(res);
    }
};

// ==================== PRICE LISTS ====================
const priceLists = {
    async getAll() {
        const res = await http.get('/price-lists');
        return extractArray(res);
    },

    async getActive() {
        const list = await this.getAll();
        return list.filter(pl => pl.is_active);
    }
};

// ==================== STOP LIST ====================
const stopList = {
    async getAll() {
        const res = await http.get('/stop-list');
        return extractArray(res);
    },

    async searchDishes(query) {
        const res = await http.get('/stop-list/search-dishes', { params: { q: query } });
        return extractArray(res);
    },

    async add(dishId, reason, resumeAt = null) {
        return http.post('/stop-list', {
            dish_id: dishId,
            reason,
            resume_at: resumeAt
        });
    },

    async remove(dishId) {
        return http.delete(`/stop-list/${dishId}`);
    },

    async update(dishId, reason, resumeAt) {
        return http.put(`/stop-list/${dishId}`, { reason, resume_at: resumeAt });
    }
};

// ==================== WRITE-OFFS ====================
const writeOffs = {
    // Получить список списаний
    async getAll(params = {}) {
        const res = await http.get('/write-offs', { params });
        return extractArray(res);
    },

    // Получить отменённые заказы (legacy)
    async getCancelledOrders(params = {}) {
        const res = await http.get('/write-offs/cancelled-orders', { params });
        return extractArray(res);
    },

    // Создать списание (с поддержкой фото)
    async create(data) {
        const formData = new FormData();

        formData.append('type', data.type);
        if (data.description) formData.append('description', data.description);
        if (data.warehouse_id) formData.append('warehouse_id', data.warehouse_id);
        if (data.manager_id) formData.append('manager_id', data.manager_id);
        if (data.photo) formData.append('photo', data.photo);

        // Items как JSON строка
        if (data.items && data.items.length > 0) {
            formData.append('items', JSON.stringify(data.items));
        } else if (data.amount) {
            // Режим ручного ввода (обратная совместимость)
            formData.append('amount', data.amount);
        }

        return http.post('/write-offs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },

    // Получить детали списания
    async get(id) {
        return http.get(`/write-offs/${id}`);
    },

    // Получить настройки (порог для подтверждения)
    async getSettings() {
        return http.get('/write-offs/settings');
    },

    // Проверить PIN менеджера
    async verifyManager(pin) {
        return http.post('/write-offs/verify-manager', { pin });
    }
};

// ==================== CANCELLATIONS ====================
const cancellations = {
    async getPending() {
        const res = await http.get('/cancellations/pending');
        return extractArray(res);
    },

    async approve(id) {
        return http.post(`/cancellations/${id}/approve`);
    },

    async reject(id, reason = null) {
        return http.post(`/cancellations/${id}/reject`, { reason });
    }
};

// ==================== ORDER ITEMS ====================
const orderItems = {
    async cancel(itemId, data) {
        return http.post(`/order-items/${itemId}/cancel`, data);
    },

    async requestCancellation(itemId, reason) {
        return http.post(`/order-items/${itemId}/request-cancellation`, { reason });
    },

    async approveCancellation(itemId) {
        return http.post(`/order-items/${itemId}/approve-cancellation`);
    },

    async rejectCancellation(itemId, reason = null) {
        return http.post(`/order-items/${itemId}/reject-cancellation`, { reason });
    }
};

// ==================== BAR ====================
const bar = {
    async check() {
        try {
            return await http.get('/bar/check');
        } catch {
            return { has_bar: false };
        }
    },

    async getOrders() {
        try {
            // Нужен полный ответ с items, station, counts
            const response = await http.get('/bar/orders');
            return {
                items: response.data || [],
                station: response.station || null,
                counts: response.counts || { new: 0, in_progress: 0, ready: 0 }
            };
        } catch {
            return { items: [], station: null, counts: { new: 0, in_progress: 0, ready: 0 } };
        }
    },

    async updateItemStatus(itemId, status) {
        return http.post('/bar/item-status', { item_id: itemId, status });
    }
};

// ==================== PAYROLL ====================
const payroll = {
    async getMyStatus() {
        return http.get('/payroll/my-status');
    },

    async clockIn() {
        return http.post('/payroll/my-clock-in');
    },

    async clockOut() {
        return http.post('/payroll/my-clock-out');
    }
};

// ==================== SETTINGS ====================
const settings = {
    async get() {
        try {
            return await http.get('/settings/pos');
        } catch {
            return null;
        }
    },

    async getGeneral() {
        try {
            return await http.get('/settings/general');
        } catch {
            return null;
        }
    },

    async save(settings) {
        return http.post('/settings/pos', settings);
    },

    async getPrinters() {
        const res = await http.get('/printers');
        return extractArray(res);
    },

    async testPrinter(id) {
        return http.post(`/printers/${id}/test`);
    }
};

// ==================== LOYALTY ====================
const loyalty = {
    /**
     * Получить настройки бонусной системы
     */
    async getBonusSettings() {
        try {
            return await http.get('/loyalty/bonus-settings');
        } catch {
            return null;
        }
    },

    /**
     * Рассчитать скидки для заказа
     * @param {Object} params - { customer_id, order_total, promo_code, use_bonus, order_type, items }
     */
    async calculateDiscount(params) {
        return http.post('/loyalty/calculate-discount', params);
    },

    /**
     * Проверить промокод
     */
    async validatePromoCode(code, customerId = null, orderTotal = 0) {
        return http.post('/loyalty/promo-codes/validate', {
            code,
            customer_id: customerId,
            order_total: orderTotal
        });
    },

    /**
     * Начислить бонусы клиенту
     */
    async earnBonus(customerId, amount, orderId = null, description = null) {
        return http.post('/loyalty/bonus/earn', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    /**
     * Списать бонусы клиента
     */
    async spendBonus(customerId, amount, orderId = null, description = null) {
        return http.post('/loyalty/bonus/spend', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    /**
     * Получить активные акции
     */
    async getActivePromotions() {
        const res = await http.get('/loyalty/promotions/active');
        return extractArray(res);
    },

    /**
     * Получить информацию о клиенте с уровнем лояльности
     */
    async getCustomerLoyalty(customerId) {
        return http.get(`/customers/${customerId}`);
    }
};

// ==================== DELIVERY ====================
const delivery = {
    /**
     * Рассчитать стоимость доставки по адресу
     */
    async calculateDelivery({ address, total, lat, lng }) {
        return http.post('/delivery/calculate', { address, total, lat, lng });
    },

    /**
     * Получить зоны доставки
     */
    async getZones() {
        const res = await http.get('/delivery/zones');
        return extractArray(res);
    },

    /**
     * Получить заказы на доставку
     */
    async getOrders(params = {}) {
        const res = await http.get('/delivery/orders', { params });
        return extractArray(res);
    },

    /**
     * Назначить курьера
     */
    async assignCourier(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, { courier_id: courierId });
    },

    /**
     * Получить проблемы доставки
     */
    async getProblems(params = {}) {
        const res = await http.get('/delivery/problems', { params });
        return extractArray(res);
    },

    /**
     * Решить проблему доставки
     */
    async resolveProblem(problemId, resolution) {
        return http.patch(`/delivery/problems/${problemId}/resolve`, { resolution });
    },

    /**
     * Отменить/удалить проблему доставки
     */
    async deleteProblem(problemId) {
        return http.delete(`/delivery/problems/${problemId}`);
    },

    /**
     * Получить данные для карты доставки
     */
    async getMapData() {
        const res = await http.get('/delivery/map-data');
        return extractData(res);
    }
};

// ==================== INVENTORY ====================
const inventory = {
    /**
     * Проверить доступность ингредиентов для блюда
     */
    async checkAvailability(dishId, warehouseId = 1, portions = 1) {
        return http.post('/inventory/check-availability', {
            dish_id: dishId,
            warehouse_id: warehouseId,
            portions
        });
    },

    /**
     * Списать ингредиенты при оплате заказа
     */
    async deductForOrder(orderId, warehouseId = 1) {
        return http.post(`/inventory/deduct-for-order/${orderId}`, {
            warehouse_id: warehouseId
        });
    }
};

// ==================== WAREHOUSE (СКЛАД) ====================
const warehouse = {
    // Справочники
    async getWarehouses() {
        const res = await http.get('/inventory/warehouses');
        return extractArray(res);
    },

    async getSuppliers() {
        const res = await http.get('/inventory/suppliers');
        return extractArray(res);
    },

    async getIngredients(params = {}) {
        const res = await http.get('/inventory/ingredients', { params });
        return extractArray(res);
    },

    async createIngredient(data) {
        return http.post('/inventory/ingredients', data);
    },

    async getCategories() {
        const res = await http.get('/inventory/categories');
        return extractArray(res);
    },

    async getUnits() {
        const res = await http.get('/inventory/units');
        return extractArray(res);
    },

    // Накладные (Invoices)
    async getInvoices(params = {}) {
        const res = await http.get('/inventory/invoices', { params });
        return extractArray(res);
    },

    async createInvoice(data) {
        return http.post('/inventory/invoices', data);
    },

    async getInvoice(id) {
        const res = await http.get(`/inventory/invoices/${id}`);
        return extractData(res);
    },

    async completeInvoice(id) {
        return http.post(`/inventory/invoices/${id}/complete`);
    },

    async cancelInvoice(id) {
        return http.post(`/inventory/invoices/${id}/cancel`);
    },

    // Инвентаризация (Inventory Checks)
    async getInventoryChecks(params = {}) {
        const res = await http.get('/inventory/checks', { params });
        return extractArray(res);
    },

    async createInventoryCheck(data) {
        return http.post('/inventory/checks', data);
    },

    async getInventoryCheck(id) {
        const res = await http.get(`/inventory/checks/${id}`);
        return extractData(res);
    },

    async updateInventoryCheckItem(checkId, itemId, data) {
        return http.put(`/inventory/checks/${checkId}/items/${itemId}`, data);
    },

    async addInventoryCheckItem(checkId, data) {
        return http.post(`/inventory/checks/${checkId}/items`, data);
    },

    async completeInventoryCheck(id) {
        return http.post(`/inventory/checks/${id}/complete`);
    },

    async cancelInventoryCheck(id) {
        return http.post(`/inventory/checks/${id}/cancel`);
    },

    // Статистика
    async getStats() {
        return http.get('/inventory/stats');
    },

    async getStockMovements(params = {}) {
        const res = await http.get('/inventory/stock-movements', { params });
        return extractArray(res);
    },

    // Распознавание накладной по фото (Yandex Vision OCR)
    async recognizeInvoice(imageBase64) {
        return http.post('/inventory/invoices/recognize', { image: imageBase64 });
    },

    async checkVisionConfig() {
        return http.get('/inventory/vision/check');
    },

    // ==================== ИНГРЕДИЕНТЫ (расширенные) ====================
    async getIngredient(id) {
        const res = await http.get(`/inventory/ingredients/${id}`);
        return extractData(res);
    },

    async updateIngredient(id, data) {
        return http.put(`/inventory/ingredients/${id}`, data);
    },

    async deleteIngredient(id) {
        return http.delete(`/inventory/ingredients/${id}`);
    },

    // ==================== ФАСОВКИ ====================
    async getPackagings(ingredientId) {
        const res = await http.get(`/inventory/ingredients/${ingredientId}/packagings`);
        return extractArray(res);
    },

    async createPackaging(ingredientId, data) {
        return http.post(`/inventory/ingredients/${ingredientId}/packagings`, data);
    },

    async updatePackaging(packagingId, data) {
        return http.put(`/inventory/packagings/${packagingId}`, data);
    },

    async deletePackaging(packagingId) {
        return http.delete(`/inventory/packagings/${packagingId}`);
    },

    // ==================== КОНВЕРТАЦИЯ ЕДИНИЦ ====================
    async convertUnits(ingredientId, quantity, fromUnitId, toUnitId) {
        return http.post('/inventory/convert-units', {
            ingredient_id: ingredientId,
            quantity,
            from_unit_id: fromUnitId,
            to_unit_id: toUnitId
        });
    },

    async calculateBruttoNetto(ingredientId, quantity, direction, processingType = 'both') {
        return http.post('/inventory/calculate-brutto-netto', {
            ingredient_id: ingredientId,
            quantity,
            direction, // 'to_net' или 'to_gross'
            processing_type: processingType // 'none', 'cold', 'hot', 'both'
        });
    },

    async getAvailableUnits(ingredientId) {
        const res = await http.get(`/inventory/ingredients/${ingredientId}/available-units`);
        return extractArray(res);
    },

    async suggestParameters(ingredientId) {
        return http.get(`/inventory/ingredients/${ingredientId}/suggest-parameters`);
    }
};

// ==================== GIFT CERTIFICATES ====================
const giftCertificates = {
    /**
     * Проверить сертификат по коду
     */
    async check(code) {
        return http.post('/gift-certificates/check', { code });
    },

    /**
     * Использовать сертификат для оплаты заказа
     */
    async use(certificateId, amount, orderId = null, customerId = null) {
        return http.post(`/gift-certificates/${certificateId}/use`, {
            amount,
            order_id: orderId,
            customer_id: customerId
        });
    }
};

// ==================== REALTIME ====================
const realtime = {
    async sendEvent(channel, event, data = {}) {
        return http.post('/realtime/send', {
            channel,
            event,
            data
        });
    },

    async sendKitchenNotification(message, data = {}) {
        return http.post('/realtime/send', {
            channel: 'kitchen',
            event: 'stop_list_notification',
            data: {
                message,
                priority: 'high',
                sound: true,
                ...data
            }
        });
    }
};

// ==================== GENERIC HTTP HELPERS ====================
const get = async (url, config = {}) => {
    const response = await http.get(url, config);
    // http interceptor returns response.data.data || response.data
    // For tenant API we need to return full response with success flag
    return response;
};

const post = async (url, data = {}, config = {}) => {
    const response = await http.post(url, data, config);
    return response;
};

// ==================== DASHBOARD ====================
const dashboard = {
    async getBriefStats() {
        try {
            return await http.get('/dashboard/stats/brief');
        } catch {
            return null;
        }
    }
};

// Re-export auth service for convenience
export { default as authService } from '../../shared/services/auth';

// Export all API modules
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
