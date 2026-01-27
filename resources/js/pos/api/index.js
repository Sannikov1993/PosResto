/**
 * POS API Module - Централизованные API вызовы
 */

import axios from 'axios';

const API_BASE = '/api';

// Create axios instance
const http = axios.create({
    baseURL: API_BASE,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Response interceptor
http.interceptors.response.use(
    response => response.data.data || response.data,
    error => {
        console.error('[API Error]', error.response?.data || error.message);
        throw error;
    }
);

// ==================== AUTH ====================
const auth = {
    async loginWithPin(pin) {
        const { data } = await axios.post(`${API_BASE}/auth/login-pin`, { pin });
        return data;
    },

    async checkAuth(token) {
        const { data } = await axios.get(`${API_BASE}/auth/check`, {
            headers: { 'X-Auth-Token': token }
        });
        return data;
    },

    async logout(token) {
        await axios.post(`${API_BASE}/auth/logout`, {}, {
            headers: { 'X-Auth-Token': token }
        });
    }
};

// ==================== TABLES ====================
const tables = {
    async getAll() {
        return http.get('/tables');
    },

    async get(id) {
        return http.get(`/tables/${id}`);
    }
};

// ==================== ZONES ====================
const zones = {
    async getAll() {
        return http.get('/zones');
    }
};

// ==================== ORDERS ====================
const orders = {
    async getAll(params = {}) {
        return http.get('/orders', { params });
    },

    async getActive() {
        return http.get('/orders', {
            params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' }
        });
    },

    async getPaidToday() {
        return http.get('/orders', { params: { paid_today: true } });
    },

    async getDelivery() {
        return http.get('/delivery/orders');
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
    }
};

// ==================== RESERVATIONS ====================
const reservations = {
    async getAll(params = {}) {
        return http.get('/reservations', { params });
    },

    async getByDate(date) {
        return http.get('/reservations', { params: { date } });
    },

    async getByTable(tableId, date) {
        return http.get('/reservations', { params: { table_id: tableId, date } });
    },

    async getCalendar(year, month) {
        return http.get('/reservations/calendar', { params: { year, month } });
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
    async payDeposit(id, method) {
        return http.post(`/reservations/${id}/deposit/pay`, { method });
    },

    async refundDeposit(id, method, reason = null) {
        return http.post(`/reservations/${id}/deposit/refund`, { method, reason });
    }
};

// ==================== SHIFTS ====================
const shifts = {
    async getAll() {
        return http.get('/finance/shifts');
    },

    async getCurrent() {
        try {
            const response = await http.get('/finance/shifts/current');
            // API возвращает { success: true, data: null } когда смена закрыта
            return response?.id ? response : null;
        } catch {
            return null;
        }
    },

    async getLastBalance() {
        try {
            return await http.get('/finance/shifts/last-balance');
        } catch {
            return { closing_amount: 0 };
        }
    },

    async get(id) {
        return http.get(`/finance/shifts/${id}`);
    },

    async getOrders(id) {
        return http.get(`/finance/shifts/${id}/orders`);
    },

    async getPrepayments(id) {
        return http.get(`/finance/shifts/${id}/prepayments`);
    },

    async open(openingAmount, cashierId = null) {
        return http.post('/finance/shifts/open', {
            opening_cash: openingAmount,
            cashier_id: cashierId,
            restaurant_id: 1
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
            description,
            restaurant_id: 1
        });
    },

    // Изъятие денег из кассы
    async withdrawal(amount, category, description = null) {
        return http.post('/finance/operations/withdrawal', {
            amount,
            category,
            description,
            restaurant_id: 1
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
            order_number: orderNumber,
            restaurant_id: 1
        });
    },

    // Возврат денег за отменённый заказ
    async refund(amount, refundMethod, orderId = null, orderNumber = null, reason = null) {
        return http.post('/finance/operations/refund', {
            amount,
            refund_method: refundMethod,
            order_id: orderId,
            order_number: orderNumber,
            reason,
            restaurant_id: 1
        });
    },

    // История операций
    async getAll(params = {}) {
        return http.get('/finance/operations', { params });
    }
};

// ==================== CUSTOMERS ====================
const customers = {
    async getAll(params = {}) {
        return http.get('/customers', { params });
    },

    async search(query) {
        return http.get('/customers', { params: { search: query } });
    },

    async get(id) {
        return http.get(`/customers/${id}`);
    },

    async create(data) {
        return http.post('/customers', data);
    },

    async update(id, data) {
        return http.put(`/customers/${id}`, data);
    },

    async getOrders(id) {
        return http.get(`/customers/${id}/orders`);
    },

    async getAddresses(id) {
        return http.get(`/customers/${id}/addresses`);
    },

    async getBonusHistory(id) {
        return http.get(`/customers/${id}/bonus-history`);
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
        return http.get('/delivery/couriers');
    },

    async assign(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
    }
};

// ==================== MENU ====================
const menu = {
    async getAll() {
        return http.get('/menu');
    },

    async getCategories() {
        return http.get('/categories');
    },

    async getDishes(categoryId = null) {
        const params = { available: 1 }; // Only available dishes for POS
        if (categoryId) {
            params.category_id = categoryId;
        }
        return http.get('/dishes', { params });
    },

    async getDish(id) {
        return http.get(`/dishes/${id}`);
    }
};

// ==================== STOP LIST ====================
const stopList = {
    async getAll() {
        return http.get('/stop-list');
    },

    async searchDishes(query) {
        return http.get('/stop-list/search-dishes', { params: { q: query } });
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
        return http.get('/write-offs', { params });
    },

    // Получить отменённые заказы (legacy)
    async getCancelledOrders(params = {}) {
        return http.get('/write-offs/cancelled-orders', { params });
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

        return axios.post(`${API_BASE}/write-offs`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        }).then(r => r.data);
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
        return http.get('/cancellations/pending');
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

// ==================== SETTINGS ====================
const settings = {
    async get() {
        try {
            return await http.get('/settings/pos');
        } catch {
            return null;
        }
    },

    async save(settings) {
        return http.post('/settings/pos', settings);
    },

    async getPrinters() {
        return http.get('/printers');
    },

    async testPrinter(id) {
        return http.post(`/printers/${id}/test`);
    }
};

// ==================== LOYALTY ====================
const loyalty = {
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
        return http.get('/loyalty/promotions/active');
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
        return http.get('/delivery/zones');
    },

    /**
     * Получить заказы на доставку
     */
    async getOrders(params = {}) {
        return http.get('/delivery/orders', { params });
    },

    /**
     * Назначить курьера
     */
    async assignCourier(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, { courier_id: courierId });
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
        return http.get('/inventory/warehouses');
    },

    async getSuppliers() {
        return http.get('/inventory/suppliers');
    },

    async getIngredients(params = {}) {
        return http.get('/inventory/ingredients', { params });
    },

    async createIngredient(data) {
        return http.post('/inventory/ingredients', data);
    },

    async getCategories() {
        return http.get('/inventory/categories');
    },

    async getUnits() {
        return http.get('/inventory/units');
    },

    // Накладные (Invoices)
    async getInvoices(params = {}) {
        return http.get('/inventory/invoices', { params });
    },

    async createInvoice(data) {
        return http.post('/inventory/invoices', data);
    },

    async getInvoice(id) {
        return http.get(`/inventory/invoices/${id}`);
    },

    async completeInvoice(id) {
        return http.post(`/inventory/invoices/${id}/complete`);
    },

    async cancelInvoice(id) {
        return http.post(`/inventory/invoices/${id}/cancel`);
    },

    // Инвентаризация (Inventory Checks)
    async getInventoryChecks(params = {}) {
        return http.get('/inventory/checks', { params });
    },

    async createInventoryCheck(data) {
        return http.post('/inventory/checks', data);
    },

    async getInventoryCheck(id) {
        return http.get(`/inventory/checks/${id}`);
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
        return http.get('/inventory/stock-movements', { params });
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
        return http.get(`/inventory/ingredients/${id}`);
    },

    async updateIngredient(id, data) {
        return http.put(`/inventory/ingredients/${id}`, data);
    },

    async deleteIngredient(id) {
        return http.delete(`/inventory/ingredients/${id}`);
    },

    // ==================== ФАСОВКИ ====================
    async getPackagings(ingredientId) {
        return http.get(`/inventory/ingredients/${ingredientId}/packagings`);
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
        return http.get(`/inventory/ingredients/${ingredientId}/available-units`);
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

// Export all API modules
export default {
    auth,
    tables,
    zones,
    orders,
    reservations,
    shifts,
    cashOperations,
    customers,
    couriers,
    menu,
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
    realtime
};
