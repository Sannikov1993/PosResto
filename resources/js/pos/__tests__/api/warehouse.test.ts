/**
 * POS Warehouse API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray, mockExtractData } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
    mockExtractArray: vi.fn((res: any) => res?.data || []),
    mockExtractData: vi.fn((res: any) => res?.data?.data || res?.data || res),
}));

vi.mock('@/pos/api/httpClient.js', () => ({
    default: mockHttp,
    extractArray: mockExtractArray,
    extractData: mockExtractData,
}));

import { inventory, warehouse } from '@/pos/api/modules/warehouse.js';

describe('POS Inventory API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('inventory.checkAvailability', () => {
        it('should POST availability check with defaults', async () => {
            mockHttp.post.mockResolvedValue({ data: { available: true } });

            await inventory.checkAvailability(10);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/check-availability', {
                dish_id: 10,
                warehouse_id: 1,
                portions: 1,
            });
        });

        it('should pass custom warehouse_id and portions', async () => {
            mockHttp.post.mockResolvedValue({ data: { available: false } });

            await inventory.checkAvailability(10, 2, 5);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/check-availability', {
                dish_id: 10,
                warehouse_id: 2,
                portions: 5,
            });
        });
    });

    describe('inventory.deductForOrder', () => {
        it('should POST deduction for order with default warehouse', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await inventory.deductForOrder(100);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/deduct-for-order/100', {
                warehouse_id: 1,
            });
        });

        it('should pass custom warehouse_id', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await inventory.deductForOrder(100, 3);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/deduct-for-order/100', {
                warehouse_id: 3,
            });
        });
    });
});

describe('POS Warehouse API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // Directories (справочники)

    describe('warehouse.getWarehouses', () => {
        it('should call GET /inventory/warehouses', async () => {
            const mockWarehouses = [{ id: 1, name: 'Основной склад' }];
            mockHttp.get.mockResolvedValue({ data: mockWarehouses });
            mockExtractArray.mockReturnValue(mockWarehouses);

            const result = await warehouse.getWarehouses();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/warehouses');
            expect(result).toEqual(mockWarehouses);
        });
    });

    describe('warehouse.getSuppliers', () => {
        it('should call GET /inventory/suppliers', async () => {
            const mockSuppliers = [{ id: 1, name: 'ООО Продукты' }];
            mockHttp.get.mockResolvedValue({ data: mockSuppliers });
            mockExtractArray.mockReturnValue(mockSuppliers);

            const result = await warehouse.getSuppliers();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/suppliers');
            expect(result).toEqual(mockSuppliers);
        });
    });

    describe('warehouse.getIngredients', () => {
        it('should call GET /inventory/ingredients with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getIngredients();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients', { params: {} });
        });

        it('should pass custom params', async () => {
            const mockIngredients = [{ id: 1, name: 'Мука' }];
            mockHttp.get.mockResolvedValue({ data: mockIngredients });
            mockExtractArray.mockReturnValue(mockIngredients);

            const result = await warehouse.getIngredients({ category_id: 2 });

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients', {
                params: { category_id: 2 },
            });
            expect(result).toEqual(mockIngredients);
        });
    });

    describe('warehouse.createIngredient', () => {
        it('should POST ingredient data', async () => {
            const data = { name: 'Сахар', unit_id: 1, category_id: 3 };
            mockHttp.post.mockResolvedValue({ data: { id: 10, ...data } });

            await warehouse.createIngredient(data);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/ingredients', data);
        });
    });

    describe('warehouse.getCategories', () => {
        it('should call GET /inventory/categories', async () => {
            const mockCategories = [{ id: 1, name: 'Сыпучие' }];
            mockHttp.get.mockResolvedValue({ data: mockCategories });
            mockExtractArray.mockReturnValue(mockCategories);

            const result = await warehouse.getCategories();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/categories');
            expect(result).toEqual(mockCategories);
        });
    });

    describe('warehouse.getUnits', () => {
        it('should call GET /inventory/units', async () => {
            const mockUnits = [{ id: 1, name: 'кг', abbreviation: 'кг' }];
            mockHttp.get.mockResolvedValue({ data: mockUnits });
            mockExtractArray.mockReturnValue(mockUnits);

            const result = await warehouse.getUnits();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/units');
            expect(result).toEqual(mockUnits);
        });
    });

    // Invoices (накладные)

    describe('warehouse.getInvoices', () => {
        it('should call GET /inventory/invoices with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getInvoices();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/invoices', { params: {} });
        });

        it('should pass custom params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getInvoices({ status: 'draft' });

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/invoices', {
                params: { status: 'draft' },
            });
        });
    });

    describe('warehouse.createInvoice', () => {
        it('should POST invoice data', async () => {
            const data = { supplier_id: 1, warehouse_id: 1, items: [] };
            mockHttp.post.mockResolvedValue({ data: { id: 1 } });

            await warehouse.createInvoice(data);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/invoices', data);
        });
    });

    describe('warehouse.getInvoice', () => {
        it('should call GET /inventory/invoices/:id', async () => {
            const mockInvoice = { id: 5, status: 'draft' };
            mockHttp.get.mockResolvedValue({ data: { data: mockInvoice } });
            mockExtractData.mockReturnValue(mockInvoice);

            const result = await warehouse.getInvoice(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/invoices/5');
            expect(result).toEqual(mockInvoice);
        });
    });

    describe('warehouse.completeInvoice', () => {
        it('should POST /inventory/invoices/:id/complete', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await warehouse.completeInvoice(5);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/invoices/5/complete');
        });
    });

    describe('warehouse.cancelInvoice', () => {
        it('should POST /inventory/invoices/:id/cancel', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await warehouse.cancelInvoice(5);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/invoices/5/cancel');
        });
    });

    // Inventory Checks (инвентаризация)

    describe('warehouse.getInventoryChecks', () => {
        it('should call GET /inventory/checks with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getInventoryChecks();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/checks', { params: {} });
        });
    });

    describe('warehouse.createInventoryCheck', () => {
        it('should POST inventory check data', async () => {
            const data = { warehouse_id: 1, items: [] };
            mockHttp.post.mockResolvedValue({ data: { id: 1 } });

            await warehouse.createInventoryCheck(data);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/checks', data);
        });
    });

    describe('warehouse.getInventoryCheck', () => {
        it('should call GET /inventory/checks/:id', async () => {
            const mockCheck = { id: 3, status: 'in_progress' };
            mockHttp.get.mockResolvedValue({ data: { data: mockCheck } });
            mockExtractData.mockReturnValue(mockCheck);

            const result = await warehouse.getInventoryCheck(3);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/checks/3');
            expect(result).toEqual(mockCheck);
        });
    });

    describe('warehouse.updateInventoryCheckItem', () => {
        it('should PUT check item data', async () => {
            const data = { actual_quantity: 50 };
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await warehouse.updateInventoryCheckItem(3, 10, data);

            expect(mockHttp.put).toHaveBeenCalledWith('/inventory/checks/3/items/10', data);
        });
    });

    describe('warehouse.addInventoryCheckItem', () => {
        it('should POST check item', async () => {
            const data = { ingredient_id: 5, actual_quantity: 100 };
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await warehouse.addInventoryCheckItem(3, data);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/checks/3/items', data);
        });
    });

    describe('warehouse.completeInventoryCheck', () => {
        it('should POST /inventory/checks/:id/complete', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await warehouse.completeInventoryCheck(3);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/checks/3/complete');
        });
    });

    describe('warehouse.cancelInventoryCheck', () => {
        it('should POST /inventory/checks/:id/cancel', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await warehouse.cancelInventoryCheck(3);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/checks/3/cancel');
        });
    });

    // Stats & movements

    describe('warehouse.getStats', () => {
        it('should call GET /inventory/stats', async () => {
            mockHttp.get.mockResolvedValue({ data: { total_items: 100 } });

            await warehouse.getStats();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/stats');
        });
    });

    describe('warehouse.getStockMovements', () => {
        it('should call GET /inventory/stock-movements with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getStockMovements();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/stock-movements', { params: {} });
        });

        it('should pass custom params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await warehouse.getStockMovements({ ingredient_id: 5 });

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/stock-movements', {
                params: { ingredient_id: 5 },
            });
        });
    });

    // Vision OCR

    describe('warehouse.recognizeInvoice', () => {
        it('should POST image base64 for recognition', async () => {
            mockHttp.post.mockResolvedValue({ data: { items: [] } });

            await warehouse.recognizeInvoice('base64data...');

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/invoices/recognize', {
                image: 'base64data...',
            });
        });
    });

    describe('warehouse.checkVisionConfig', () => {
        it('should call GET /inventory/vision/check', async () => {
            mockHttp.get.mockResolvedValue({ data: { configured: true } });

            await warehouse.checkVisionConfig();

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/vision/check');
        });
    });

    // Extended ingredients

    describe('warehouse.getIngredient', () => {
        it('should call GET /inventory/ingredients/:id', async () => {
            const mockIngredient = { id: 5, name: 'Мука' };
            mockHttp.get.mockResolvedValue({ data: { data: mockIngredient } });
            mockExtractData.mockReturnValue(mockIngredient);

            const result = await warehouse.getIngredient(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients/5');
            expect(result).toEqual(mockIngredient);
        });
    });

    describe('warehouse.updateIngredient', () => {
        it('should PUT ingredient data', async () => {
            const data = { name: 'Мука пшеничная' };
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await warehouse.updateIngredient(5, data);

            expect(mockHttp.put).toHaveBeenCalledWith('/inventory/ingredients/5', data);
        });
    });

    describe('warehouse.deleteIngredient', () => {
        it('should DELETE /inventory/ingredients/:id', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await warehouse.deleteIngredient(5);

            expect(mockHttp.delete).toHaveBeenCalledWith('/inventory/ingredients/5');
        });
    });

    // Packagings

    describe('warehouse.getPackagings', () => {
        it('should call GET /inventory/ingredients/:id/packagings', async () => {
            const mockPackagings = [{ id: 1, name: 'Мешок 50кг' }];
            mockHttp.get.mockResolvedValue({ data: mockPackagings });
            mockExtractArray.mockReturnValue(mockPackagings);

            const result = await warehouse.getPackagings(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients/5/packagings');
            expect(result).toEqual(mockPackagings);
        });
    });

    describe('warehouse.createPackaging', () => {
        it('should POST packaging data', async () => {
            const data = { name: 'Пакет 1кг', weight: 1 };
            mockHttp.post.mockResolvedValue({ data: { id: 1 } });

            await warehouse.createPackaging(5, data);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/ingredients/5/packagings', data);
        });
    });

    describe('warehouse.updatePackaging', () => {
        it('should PUT packaging data', async () => {
            const data = { name: 'Пакет 2кг' };
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await warehouse.updatePackaging(10, data);

            expect(mockHttp.put).toHaveBeenCalledWith('/inventory/packagings/10', data);
        });
    });

    describe('warehouse.deletePackaging', () => {
        it('should DELETE /inventory/packagings/:id', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await warehouse.deletePackaging(10);

            expect(mockHttp.delete).toHaveBeenCalledWith('/inventory/packagings/10');
        });
    });

    // Unit conversion

    describe('warehouse.convertUnits', () => {
        it('should POST unit conversion params', async () => {
            mockHttp.post.mockResolvedValue({ data: { result: 1000 } });

            await warehouse.convertUnits(5, 1, 1, 2);

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/convert-units', {
                ingredient_id: 5,
                quantity: 1,
                from_unit_id: 1,
                to_unit_id: 2,
            });
        });
    });

    describe('warehouse.calculateBruttoNetto', () => {
        it('should POST brutto-netto calculation with defaults', async () => {
            mockHttp.post.mockResolvedValue({ data: { brutto: 1.2, netto: 1.0 } });

            await warehouse.calculateBruttoNetto(5, 1.2, 'brutto_to_netto');

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/calculate-brutto-netto', {
                ingredient_id: 5,
                quantity: 1.2,
                direction: 'brutto_to_netto',
                processing_type: 'both',
            });
        });

        it('should pass custom processing_type', async () => {
            mockHttp.post.mockResolvedValue({ data: { brutto: 1.5, netto: 1.0 } });

            await warehouse.calculateBruttoNetto(5, 1.5, 'netto_to_brutto', 'cold');

            expect(mockHttp.post).toHaveBeenCalledWith('/inventory/calculate-brutto-netto', {
                ingredient_id: 5,
                quantity: 1.5,
                direction: 'netto_to_brutto',
                processing_type: 'cold',
            });
        });
    });

    describe('warehouse.getAvailableUnits', () => {
        it('should call GET /inventory/ingredients/:id/available-units', async () => {
            const mockUnits = [{ id: 1, name: 'кг' }, { id: 2, name: 'г' }];
            mockHttp.get.mockResolvedValue({ data: mockUnits });
            mockExtractArray.mockReturnValue(mockUnits);

            const result = await warehouse.getAvailableUnits(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients/5/available-units');
            expect(result).toEqual(mockUnits);
        });
    });

    describe('warehouse.suggestParameters', () => {
        it('should call GET /inventory/ingredients/:id/suggest-parameters', async () => {
            mockHttp.get.mockResolvedValue({ data: { suggested: true } });

            await warehouse.suggestParameters(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/inventory/ingredients/5/suggest-parameters');
        });
    });
});
