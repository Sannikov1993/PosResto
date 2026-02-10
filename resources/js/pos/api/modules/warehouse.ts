import http, { extractArray, extractData } from '../httpClient.js';
import type { Ingredient, Warehouse, Invoice, InventoryCheck } from '@/shared/types';

interface Supplier {
    id: number;
    name: string;
    [key: string]: unknown;
}

interface InventoryCategory {
    id: number;
    name: string;
    [key: string]: unknown;
}

interface Unit {
    id: number;
    name: string;
    abbreviation?: string;
    [key: string]: unknown;
}

interface Packaging {
    id: number;
    name: string;
    [key: string]: unknown;
}

interface StockMovement {
    id: number;
    [key: string]: unknown;
}

const inventory = {
    async checkAvailability(dishId: number, warehouseId = 1, portions = 1): Promise<unknown> {
        return http.post('/inventory/check-availability', {
            dish_id: dishId,
            warehouse_id: warehouseId,
            portions
        });
    },

    async deductForOrder(orderId: number, warehouseId = 1): Promise<unknown> {
        return http.post(`/inventory/deduct-for-order/${orderId}`, {
            warehouse_id: warehouseId
        });
    }
};

const warehouse = {
    // Справочники
    async getWarehouses(): Promise<Warehouse[]> {
        const res = await http.get('/inventory/warehouses');
        return extractArray<Warehouse>(res);
    },

    async getSuppliers(): Promise<Supplier[]> {
        const res = await http.get('/inventory/suppliers');
        return extractArray<Supplier>(res);
    },

    async getIngredients(params: Record<string, any> = {}): Promise<Ingredient[]> {
        const res = await http.get('/inventory/ingredients', { params });
        return extractArray<Ingredient>(res);
    },

    async createIngredient(data: Record<string, any>): Promise<unknown> {
        return http.post('/inventory/ingredients', data);
    },

    async getCategories(): Promise<InventoryCategory[]> {
        const res = await http.get('/inventory/categories');
        return extractArray<InventoryCategory>(res);
    },

    async getUnits(): Promise<Unit[]> {
        const res = await http.get('/inventory/units');
        return extractArray<Unit>(res);
    },

    // Накладные (Invoices)
    async getInvoices(params: Record<string, any> = {}): Promise<Invoice[]> {
        const res = await http.get('/inventory/invoices', { params });
        return extractArray<Invoice>(res);
    },

    async createInvoice(data: Record<string, any>): Promise<unknown> {
        return http.post('/inventory/invoices', data);
    },

    async getInvoice(id: number): Promise<Invoice> {
        const res = await http.get(`/inventory/invoices/${id}`);
        return extractData<Invoice>(res);
    },

    async completeInvoice(id: number): Promise<unknown> {
        return http.post(`/inventory/invoices/${id}/complete`);
    },

    async cancelInvoice(id: number): Promise<unknown> {
        return http.post(`/inventory/invoices/${id}/cancel`);
    },

    // Инвентаризация (Inventory Checks)
    async getInventoryChecks(params: Record<string, any> = {}): Promise<InventoryCheck[]> {
        const res = await http.get('/inventory/checks', { params });
        return extractArray<InventoryCheck>(res);
    },

    async createInventoryCheck(data: Record<string, any>): Promise<unknown> {
        return http.post('/inventory/checks', data);
    },

    async getInventoryCheck(id: number): Promise<InventoryCheck> {
        const res = await http.get(`/inventory/checks/${id}`);
        return extractData<InventoryCheck>(res);
    },

    async updateInventoryCheckItem(checkId: number, itemId: number, data: Record<string, any>): Promise<unknown> {
        return http.put(`/inventory/checks/${checkId}/items/${itemId}`, data);
    },

    async addInventoryCheckItem(checkId: number, data: Record<string, any>): Promise<unknown> {
        return http.post(`/inventory/checks/${checkId}/items`, data);
    },

    async completeInventoryCheck(id: number): Promise<unknown> {
        return http.post(`/inventory/checks/${id}/complete`);
    },

    async cancelInventoryCheck(id: number): Promise<unknown> {
        return http.post(`/inventory/checks/${id}/cancel`);
    },

    // Статистика
    async getStats(): Promise<unknown> {
        return http.get('/inventory/stats');
    },

    async getStockMovements(params: Record<string, any> = {}): Promise<StockMovement[]> {
        const res = await http.get('/inventory/stock-movements', { params });
        return extractArray<StockMovement>(res);
    },

    // Распознавание накладной по фото (Yandex Vision OCR)
    async recognizeInvoice(imageBase64: string): Promise<unknown> {
        return http.post('/inventory/invoices/recognize', { image: imageBase64 });
    },

    async checkVisionConfig(): Promise<unknown> {
        return http.get('/inventory/vision/check');
    },

    // Ингредиенты (расширенные)
    async getIngredient(id: number): Promise<Ingredient> {
        const res = await http.get(`/inventory/ingredients/${id}`);
        return extractData<Ingredient>(res);
    },

    async updateIngredient(id: number, data: Record<string, any>): Promise<unknown> {
        return http.put(`/inventory/ingredients/${id}`, data);
    },

    async deleteIngredient(id: number): Promise<unknown> {
        return http.delete(`/inventory/ingredients/${id}`);
    },

    // Фасовки
    async getPackagings(ingredientId: number): Promise<Packaging[]> {
        const res = await http.get(`/inventory/ingredients/${ingredientId}/packagings`);
        return extractArray<Packaging>(res);
    },

    async createPackaging(ingredientId: number, data: Record<string, any>): Promise<unknown> {
        return http.post(`/inventory/ingredients/${ingredientId}/packagings`, data);
    },

    async updatePackaging(packagingId: number, data: Record<string, any>): Promise<unknown> {
        return http.put(`/inventory/packagings/${packagingId}`, data);
    },

    async deletePackaging(packagingId: number): Promise<unknown> {
        return http.delete(`/inventory/packagings/${packagingId}`);
    },

    // Конвертация единиц
    async convertUnits(ingredientId: number, quantity: number, fromUnitId: number, toUnitId: number): Promise<unknown> {
        return http.post('/inventory/convert-units', {
            ingredient_id: ingredientId,
            quantity,
            from_unit_id: fromUnitId,
            to_unit_id: toUnitId
        });
    },

    async calculateBruttoNetto(
        ingredientId: number,
        quantity: number,
        direction: string,
        processingType = 'both'
    ): Promise<unknown> {
        return http.post('/inventory/calculate-brutto-netto', {
            ingredient_id: ingredientId,
            quantity,
            direction,
            processing_type: processingType
        });
    },

    async getAvailableUnits(ingredientId: number): Promise<Unit[]> {
        const res = await http.get(`/inventory/ingredients/${ingredientId}/available-units`);
        return extractArray<Unit>(res);
    },

    async suggestParameters(ingredientId: number): Promise<unknown> {
        return http.get(`/inventory/ingredients/${ingredientId}/suggest-parameters`);
    }
};

export { inventory, warehouse };
