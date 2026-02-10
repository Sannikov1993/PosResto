/**
 * POS Finance API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray, mockExtractData } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
    },
    mockExtractArray: vi.fn((res: any) => res?.data || []),
    mockExtractData: vi.fn((res: any) => res?.data?.data || res?.data || res),
}));

vi.mock('@/pos/api/httpClient.js', () => ({
    default: mockHttp,
    extractArray: mockExtractArray,
    extractData: mockExtractData,
}));

import { shifts, cashOperations } from '@/pos/api/modules/finance.js';

describe('POS Finance API - Shifts', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('shifts.getAll', () => {
        it('should call GET /finance/shifts', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await shifts.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/finance/shifts');
        });
    });

    describe('shifts.getCurrent', () => {
        it('should return current shift', async () => {
            const mockShift = { id: 1, status: 'open', opening_amount: 5000 };
            mockHttp.get.mockResolvedValue({ data: { data: mockShift } });
            mockExtractData.mockReturnValue(mockShift);

            const result = await shifts.getCurrent();

            expect(mockHttp.get).toHaveBeenCalledWith('/finance/shifts/current');
            expect(result).toEqual(mockShift);
        });

        it('should return null when no active shift', async () => {
            mockHttp.get.mockResolvedValue({ data: { data: {} } });
            mockExtractData.mockReturnValue({});

            const result = await shifts.getCurrent();

            expect(result).toBeNull();
        });

        it('should return null on error', async () => {
            mockHttp.get.mockRejectedValue(new Error('Network error'));

            const result = await shifts.getCurrent();

            expect(result).toBeNull();
        });
    });

    describe('shifts.getLastBalance', () => {
        it('should return last balance', async () => {
            mockHttp.get.mockResolvedValue({ data: { data: { closing_amount: 15000 } } });
            mockExtractData.mockReturnValue({ closing_amount: 15000 });

            const result = await shifts.getLastBalance();

            expect(result).toEqual({ closing_amount: 15000 });
        });

        it('should return 0 on error', async () => {
            mockHttp.get.mockRejectedValue(new Error('Error'));

            const result = await shifts.getLastBalance();

            expect(result).toEqual({ closing_amount: 0 });
        });
    });

    describe('shifts.open', () => {
        it('should POST shift opening data', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await shifts.open(5000, 1);

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/shifts/open', {
                opening_cash: 5000,
                cashier_id: 1,
            });
        });

        it('should open without cashier_id', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await shifts.open(3000);

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/shifts/open', {
                opening_cash: 3000,
                cashier_id: null,
            });
        });
    });

    describe('shifts.close', () => {
        it('should POST shift closing data', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await shifts.close(1, 25000);

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/shifts/1/close', {
                closing_amount: 25000,
            });
        });
    });
});

describe('POS Finance API - Cash Operations', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('cashOperations.deposit', () => {
        it('should POST deposit data', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.deposit(5000, 'Opening deposit');

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/deposit', {
                amount: 5000,
                description: 'Opening deposit',
            });
        });

        it('should work without description', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.deposit(1000);

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/deposit', {
                amount: 1000,
                description: null,
            });
        });
    });

    describe('cashOperations.withdrawal', () => {
        it('should POST withdrawal data with category', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.withdrawal(2000, 'purchase', 'Grocery supplies');

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/withdrawal', {
                amount: 2000,
                category: 'purchase',
                description: 'Grocery supplies',
            });
        });
    });

    describe('cashOperations.refund', () => {
        it('should POST refund data', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.refund(500, 'cash', 1, 'ORD-1234', 'Wrong order');

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/refund', {
                amount: 500,
                refund_method: 'cash',
                order_id: 1,
                order_number: 'ORD-1234',
                reason: 'Wrong order',
            });
        });

        it('should work with minimal params', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.refund(300, 'card');

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/refund', {
                amount: 300,
                refund_method: 'card',
                order_id: null,
                order_number: null,
                reason: null,
            });
        });
    });

    describe('cashOperations.orderPrepayment', () => {
        it('should POST prepayment data', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cashOperations.orderPrepayment(1000, 'cash', 'Иван', 'delivery', 5, 'ORD-100');

            expect(mockHttp.post).toHaveBeenCalledWith('/finance/operations/order-prepayment', {
                amount: 1000,
                payment_method: 'cash',
                customer_name: 'Иван',
                order_type: 'delivery',
                order_id: 5,
                order_number: 'ORD-100',
            });
        });
    });

    describe('cashOperations.getAll', () => {
        it('should GET operations with params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await cashOperations.getAll({ today: true });

            expect(mockHttp.get).toHaveBeenCalledWith('/finance/operations', {
                params: { today: true },
            });
        });
    });
});
