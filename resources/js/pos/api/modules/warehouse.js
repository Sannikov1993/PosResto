import http, { extractArray, extractData } from '../httpClient';

const inventory = {
    async checkAvailability(dishId, warehouseId = 1, portions = 1) {
        return http.post('/inventory/check-availability', {
            dish_id: dishId,
            warehouse_id: warehouseId,
            portions
        });
    },

    async deductForOrder(orderId, warehouseId = 1) {
        return http.post(`/inventory/deduct-for-order/${orderId}`, {
            warehouse_id: warehouseId
        });
    }
};

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

    // Ингредиенты (расширенные)
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

    // Фасовки
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

    // Конвертация единиц
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
            direction,
            processing_type: processingType
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

export { inventory, warehouse };
