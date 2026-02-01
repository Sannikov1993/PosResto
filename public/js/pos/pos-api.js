/**
 * MenuLab POS - API Module
 * Централизованные API вызовы для POS системы
 */

const PosAPI = {
    // Base API URL - устанавливается при инициализации
    baseUrl: '/api',
    restaurantId: null,

    // ==================== ИНИЦИАЛИЗАЦИЯ ====================

    init(restaurantId) {
        this.restaurantId = restaurantId;
        console.log('[PosAPI] Initialized for restaurant:', restaurantId);
    },

    // ==================== АВТОРИЗАЦИЯ ====================

    async loginWithPin(pin) {
        const { data } = await axios.post(`${this.baseUrl}/auth/login-pin`, { pin });
        return data;
    },

    async checkAuth(token) {
        const { data } = await axios.get(`${this.baseUrl}/auth/check`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        return data;
    },

    async logout(token) {
        await axios.post(`${this.baseUrl}/auth/logout`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });
    },

    // ==================== СТОЛЫ И ЗОНЫ ====================

    async getTables() {
        const { data } = await axios.get(`${this.baseUrl}/tables`);
        return data.data || data;
    },

    async getZones() {
        const { data } = await axios.get(`${this.baseUrl}/zones`);
        return data.data || data;
    },

    async getFloorPlan() {
        const [tablesRes, zonesRes] = await Promise.all([
            axios.get(`${this.baseUrl}/tables`),
            axios.get(`${this.baseUrl}/zones`)
        ]);
        return {
            tables: tablesRes.data.data || tablesRes.data,
            zones: zonesRes.data.data || zonesRes.data
        };
    },

    // ==================== ЗАКАЗЫ ====================

    async getOrders(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/orders`, { params });
        return data.data || data;
    },

    async getActiveOrders() {
        return this.getOrders({
            status: 'new,confirmed,cooking,ready,served',
            type: 'dine_in'
        });
    },

    async getPaidTodayOrders() {
        return this.getOrders({ paid_today: true });
    },

    async getDeliveryOrders() {
        return this.getOrders({ type: 'delivery,pickup' });
    },

    async getOrder(orderId) {
        const { data } = await axios.get(`${this.baseUrl}/orders/${orderId}`);
        return data.data || data;
    },

    async createOrder(orderData) {
        const { data } = await axios.post(`${this.baseUrl}/orders`, orderData);
        return data.data || data;
    },

    async updateOrder(orderId, orderData) {
        const { data } = await axios.put(`${this.baseUrl}/orders/${orderId}`, orderData);
        return data.data || data;
    },

    async cancelOrder(orderId, reason, staffId) {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/cancel`, {
            reason,
            staff_id: staffId
        });
        return data;
    },

    async payOrder(orderId, paymentData) {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/pay`, paymentData);
        return data;
    },

    // ==================== БРОНИРОВАНИЯ ====================

    async getReservations(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/reservations`, { params });
        return data.data || data;
    },

    async getReservationCalendar(month, year) {
        const { data } = await axios.get(`${this.baseUrl}/reservations/calendar`, {
            params: { month, year }
        });
        return data;
    },

    async getTableReservations(tableId, date) {
        const { data } = await axios.get(`${this.baseUrl}/reservations`, {
            params: { table_id: tableId, date }
        });
        return data.data || data;
    },

    async createReservation(reservationData) {
        const { data } = await axios.post(`${this.baseUrl}/reservations`, reservationData);
        return data.data || data;
    },

    async updateReservation(reservationId, reservationData) {
        const { data } = await axios.put(`${this.baseUrl}/reservations/${reservationId}`, reservationData);
        return data.data || data;
    },

    async cancelReservation(reservationId) {
        const { data } = await axios.delete(`${this.baseUrl}/reservations/${reservationId}`);
        return data;
    },

    async seatReservation(reservationId) {
        const { data } = await axios.post(`${this.baseUrl}/reservations/${reservationId}/seat`);
        return data;
    },

    async checkReservationConflict(tableId, date, timeFrom, timeTo, excludeId = null) {
        const { data } = await axios.post(`${this.baseUrl}/reservations/check-conflict`, {
            table_id: tableId,
            date,
            time_from: timeFrom,
            time_to: timeTo,
            exclude_id: excludeId
        });
        return data;
    },

    // ==================== КАССОВЫЕ СМЕНЫ ====================

    async getShifts() {
        const { data } = await axios.get(`${this.baseUrl}/finance/shifts`);
        return data.data || data;
    },

    async getCurrentShift() {
        try {
            const { data } = await axios.get(`${this.baseUrl}/finance/shifts/current`);
            return data.data || data;
        } catch (e) {
            return null;
        }
    },

    async getShiftDetails(shiftId) {
        const { data } = await axios.get(`${this.baseUrl}/finance/shifts/${shiftId}`);
        return data.data || data;
    },

    async getShiftOrders(shiftId) {
        const { data } = await axios.get(`${this.baseUrl}/finance/shifts/${shiftId}/orders`);
        return data.data || data;
    },

    async openShift(openingAmount, cashierId) {
        const { data } = await axios.post(`${this.baseUrl}/finance/shifts/open`, {
            opening_amount: openingAmount,
            cashier_id: cashierId
        });
        return data.data || data;
    },

    async closeShift(shiftId, closingAmount, notes) {
        const { data } = await axios.post(`${this.baseUrl}/finance/shifts/${shiftId}/close`, {
            closing_amount: closingAmount,
            notes
        });
        return data;
    },

    // ==================== ФИНАНСОВЫЕ ОПЕРАЦИИ ====================

    async getFinanceOperations(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/finance/operations`, { params });
        return data.data || data;
    },

    async getTodayPrepayments() {
        return this.getFinanceOperations({ today: 1, category: 'prepayment' });
    },

    async createPrepayment(prepaymentData) {
        const { data } = await axios.post(`${this.baseUrl}/finance/prepayments`, prepaymentData);
        return data;
    },

    // ==================== МЕНЮ ====================

    async getMenu() {
        const { data } = await axios.get(`${this.baseUrl}/menu`);
        return data.data || data;
    },

    async getCategories() {
        const { data } = await axios.get(`${this.baseUrl}/categories`);
        return data.data || data;
    },

    async getDishes(categoryId = null) {
        const params = categoryId ? { category_id: categoryId } : {};
        const { data } = await axios.get(`${this.baseUrl}/dishes`, { params });
        return data.data || data;
    },

    async getDish(dishId) {
        const { data } = await axios.get(`${this.baseUrl}/dishes/${dishId}`);
        return data.data || data;
    },

    // ==================== СТОП-ЛИСТ ====================

    async getStopList() {
        const { data } = await axios.get(`${this.baseUrl}/stop-list`);
        return data.data || data;
    },

    async searchDishesForStopList(query) {
        const { data } = await axios.get(`${this.baseUrl}/stop-list/search-dishes`, {
            params: { query }
        });
        return data.data || data;
    },

    async addToStopList(dishId, reason, availableAt = null) {
        const { data } = await axios.post(`${this.baseUrl}/stop-list`, {
            dish_id: dishId,
            reason,
            available_at: availableAt
        });
        return data;
    },

    async removeFromStopList(dishId) {
        await axios.delete(`${this.baseUrl}/stop-list/${dishId}`);
    },

    async updateStopListItem(dishId, reason, availableAt) {
        const { data } = await axios.put(`${this.baseUrl}/stop-list/${dishId}`, {
            reason,
            available_at: availableAt
        });
        return data;
    },

    // ==================== ДОСТАВКА ====================

    async getCouriers() {
        const { data } = await axios.get(`${this.baseUrl}/delivery/couriers`);
        return data.data || data;
    },

    async assignCourier(orderId, courierId) {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
        return data;
    },

    async updateDeliveryStatus(orderId, status) {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/delivery-status`, {
            status
        });
        return data;
    },

    // ==================== КЛИЕНТЫ ====================

    async getCustomers(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/customers`, { params });
        return data.data || data;
    },

    async searchCustomers(query) {
        return this.getCustomers({ search: query });
    },

    async getCustomer(customerId) {
        const { data } = await axios.get(`${this.baseUrl}/customers/${customerId}`);
        return data.data || data;
    },

    async createCustomer(customerData) {
        const { data } = await axios.post(`${this.baseUrl}/customers`, customerData);
        return data.data || data;
    },

    async updateCustomer(customerId, customerData) {
        const { data } = await axios.put(`${this.baseUrl}/customers/${customerId}`, customerData);
        return data.data || data;
    },

    async getCustomerOrders(customerId) {
        const { data } = await axios.get(`${this.baseUrl}/customers/${customerId}/orders`);
        return data.data || data;
    },

    async getCustomerAddresses(customerId) {
        const { data } = await axios.get(`${this.baseUrl}/customers/${customerId}/addresses`);
        return data.data || data;
    },

    async toggleCustomerBlacklist(customerId) {
        const { data } = await axios.post(`${this.baseUrl}/customers/${customerId}/toggle-blacklist`);
        return data;
    },

    // ==================== СПИСАНИЯ И ОТМЕНЫ ====================

    async getWriteOffs(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/write-offs`, { params });
        return data.data || data;
    },

    async getPendingCancellations() {
        const { data } = await axios.get(`${this.baseUrl}/cancellations/pending`);
        return data.data || data;
    },

    async approveCancellation(cancellationId) {
        const { data } = await axios.post(`${this.baseUrl}/cancellations/${cancellationId}/approve`);
        return data;
    },

    async rejectCancellation(cancellationId) {
        const { data } = await axios.post(`${this.baseUrl}/cancellations/${cancellationId}/reject`);
        return data;
    },

    // ==================== ИНВЕНТАРЬ ====================

    async getInventoryIngredients() {
        const { data } = await axios.get(`${this.baseUrl}/inventory/ingredients`);
        return data.data || data;
    },

    async getInventoryWarehouses() {
        const { data } = await axios.get(`${this.baseUrl}/inventory/warehouses`);
        return data.data || data;
    },

    async getInventoryMovements(params = {}) {
        const { data } = await axios.get(`${this.baseUrl}/inventory/movements`, { params });
        return data.data || data;
    },

    async createInventoryWriteOff(writeOffData) {
        const { data } = await axios.post(`${this.baseUrl}/inventory/write-offs`, writeOffData);
        return data;
    },

    // ==================== НАСТРОЙКИ ====================

    async getPosSettings() {
        try {
            const { data } = await axios.get(`${this.baseUrl}/settings/pos`);
            return data.data || data;
        } catch (e) {
            return null;
        }
    },

    async savePosSettings(settings) {
        const { data } = await axios.post(`${this.baseUrl}/settings/pos`, settings);
        return data;
    },

    async getPrinters() {
        const { data } = await axios.get(`${this.baseUrl}/printers`);
        return data.data || data;
    },

    async testPrinter(printerId) {
        const { data } = await axios.post(`${this.baseUrl}/printers/${printerId}/test`);
        return data;
    },

    // ==================== ПЕЧАТЬ ====================

    async printReceipt(orderId, printerType = 'receipt') {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/print`, {
            type: printerType
        });
        return data;
    },

    async printKitchenOrder(orderId) {
        const { data } = await axios.post(`${this.baseUrl}/orders/${orderId}/print-kitchen`);
        return data;
    },

    // ==================== ЗАГРУЗКА ВСЕХ НАЧАЛЬНЫХ ДАННЫХ ====================

    async loadInitialData() {
        const [
            tablesRes,
            zonesRes,
            shiftsRes,
            paidOrdersRes,
            currentShiftRes,
            activeOrdersRes
        ] = await Promise.all([
            axios.get(`${this.baseUrl}/tables`).catch(() => ({ data: { data: [] } })),
            axios.get(`${this.baseUrl}/zones`).catch(() => ({ data: { data: [] } })),
            axios.get(`${this.baseUrl}/finance/shifts`).catch(() => ({ data: { data: [] } })),
            axios.get(`${this.baseUrl}/orders`, { params: { paid_today: true } }).catch(() => ({ data: { data: [] } })),
            axios.get(`${this.baseUrl}/finance/shifts/current`).catch(() => ({ data: { data: null } })),
            axios.get(`${this.baseUrl}/orders`, { params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' } }).catch(() => ({ data: { data: [] } }))
        ]);

        return {
            tables: tablesRes.data.data || tablesRes.data || [],
            zones: zonesRes.data.data || zonesRes.data || [],
            shifts: shiftsRes.data.data || shiftsRes.data || [],
            paidOrders: paidOrdersRes.data.data || paidOrdersRes.data || [],
            currentShift: currentShiftRes.data.data || currentShiftRes.data,
            activeOrders: activeOrdersRes.data.data || activeOrdersRes.data || []
        };
    }
};

// Export for global usage
window.PosAPI = PosAPI;
