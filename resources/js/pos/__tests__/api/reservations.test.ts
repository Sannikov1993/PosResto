/**
 * POS Reservations API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray, mockExtractData } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
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

import reservations from '@/pos/api/modules/reservations.js';

describe('POS Reservations API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('reservations.getAll', () => {
        it('should call GET /reservations with default empty params', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await reservations.getAll();

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations', { params: {} });
        });

        it('should pass custom params', async () => {
            const mockReservations = [{ id: 1, status: 'confirmed' }];
            mockHttp.get.mockResolvedValue({ data: mockReservations });
            mockExtractArray.mockReturnValue(mockReservations);

            const result = await reservations.getAll({ status: 'confirmed', date: '2026-02-11' });

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations', {
                params: { status: 'confirmed', date: '2026-02-11' },
            });
            expect(result).toEqual(mockReservations);
        });
    });

    describe('reservations.getByDate', () => {
        it('should call GET /reservations with date param', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await reservations.getByDate('2026-02-15');

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations', {
                params: { date: '2026-02-15' },
            });
        });
    });

    describe('reservations.getByTable', () => {
        it('should call GET /reservations with table_id and date', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await reservations.getByTable(5, '2026-02-15');

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations', {
                params: { table_id: 5, date: '2026-02-15' },
            });
        });
    });

    describe('reservations.getCalendar', () => {
        it('should call GET /reservations/calendar with year and month', async () => {
            const calendarData = { '2026-02-15': [{ id: 1 }] };
            mockHttp.get.mockResolvedValue({ data: calendarData });
            mockExtractData.mockReturnValue(calendarData);

            const result = await reservations.getCalendar(2026, 2);

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations/calendar', {
                params: { year: 2026, month: 2 },
            });
            expect(result).toEqual(calendarData);
        });
    });

    describe('reservations.create', () => {
        it('should POST reservation data', async () => {
            const data = {
                table_id: 5,
                date: '2026-02-15',
                time_from: '18:00',
                time_to: '20:00',
                guest_name: 'Иван',
                guest_count: 4,
            };
            mockHttp.post.mockResolvedValue({ data: { id: 1, ...data } });

            await reservations.create(data);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations', data);
        });
    });

    describe('reservations.update', () => {
        it('should PUT reservation data', async () => {
            const data = { guest_count: 6, time_to: '21:00' };
            mockHttp.put.mockResolvedValue({ data: { success: true } });

            await reservations.update(1, data);

            expect(mockHttp.put).toHaveBeenCalledWith('/reservations/1', data);
        });
    });

    describe('reservations.cancel', () => {
        it('should POST cancellation with all params', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.cancel(1, 'Guest cancelled', true, 'card');

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/cancel', {
                reason: 'Guest cancelled',
                refund_deposit: true,
                refund_method: 'card',
            });
        });

        it('should use default params when not provided', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.cancel(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/cancel', {
                reason: null,
                refund_deposit: false,
                refund_method: 'cash',
            });
        });
    });

    describe('reservations.seat', () => {
        it('should POST seat reservation', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.seat(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/seat');
        });
    });

    describe('reservations.seatWithOrder', () => {
        it('should POST seat with order', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.seatWithOrder(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/seat-with-order');
        });
    });

    describe('reservations.unseat', () => {
        it('should POST unseat reservation', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.unseat(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/unseat');
        });
    });

    describe('reservations.delete', () => {
        it('should DELETE /reservations/:id', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await reservations.delete(1);

            expect(mockHttp.delete).toHaveBeenCalledWith('/reservations/1');
        });
    });

    describe('reservations.checkConflict', () => {
        it('should POST conflict check with all params', async () => {
            mockHttp.post.mockResolvedValue({ data: { has_conflict: false } });

            await reservations.checkConflict(5, '2026-02-15', '18:00', '20:00', 1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/check-conflict', {
                table_id: 5,
                date: '2026-02-15',
                time_from: '18:00',
                time_to: '20:00',
                exclude_id: 1,
            });
        });

        it('should default exclude_id to null', async () => {
            mockHttp.post.mockResolvedValue({ data: { has_conflict: true } });

            await reservations.checkConflict(5, '2026-02-15', '18:00', '20:00');

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/check-conflict', {
                table_id: 5,
                date: '2026-02-15',
                time_from: '18:00',
                time_to: '20:00',
                exclude_id: null,
            });
        });
    });

    describe('reservations.payDeposit', () => {
        it('should POST deposit payment with method', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.payDeposit(1, 'cash');

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/deposit/pay', {
                method: 'cash',
            });
        });

        it('should include amount when provided', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.payDeposit(1, 'card', 2000);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/deposit/pay', {
                method: 'card',
                amount: 2000,
            });
        });
    });

    describe('reservations.refundDeposit', () => {
        it('should POST deposit refund with reason', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.refundDeposit(1, 'Guest no-show');

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/deposit/refund', {
                reason: 'Guest no-show',
            });
        });

        it('should default reason to null', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.refundDeposit(1);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/1/deposit/refund', {
                reason: null,
            });
        });
    });

    describe('reservations.getBusinessDate', () => {
        it('should call GET /reservations/business-date', async () => {
            mockHttp.get.mockResolvedValue({ data: { date: '2026-02-11' } });

            await reservations.getBusinessDate();

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations/business-date');
        });

        it('should return null on error', async () => {
            mockHttp.get.mockRejectedValue(new Error('Server error'));

            const result = await reservations.getBusinessDate();

            expect(result).toBeNull();
        });
    });

    describe('reservations.getPreorderItems', () => {
        it('should call GET /reservations/:id/preorder-items', async () => {
            mockHttp.get.mockResolvedValue({ data: [{ id: 1, dish_id: 10 }] });

            await reservations.getPreorderItems(5);

            expect(mockHttp.get).toHaveBeenCalledWith('/reservations/5/preorder-items');
        });
    });

    describe('reservations.addPreorderItem', () => {
        it('should POST preorder item data', async () => {
            const data = { dish_id: 10, quantity: 2 };
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.addPreorderItem(5, data);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/5/preorder-items', data);
        });
    });

    describe('reservations.updatePreorderItem', () => {
        it('should PATCH preorder item', async () => {
            const data = { quantity: 3 };
            mockHttp.patch.mockResolvedValue({ data: { success: true } });

            await reservations.updatePreorderItem(5, 10, data);

            expect(mockHttp.patch).toHaveBeenCalledWith('/reservations/5/preorder-items/10', data);
        });
    });

    describe('reservations.deletePreorderItem', () => {
        it('should DELETE preorder item', async () => {
            mockHttp.delete.mockResolvedValue({ data: { success: true } });

            await reservations.deletePreorderItem(5, 10);

            expect(mockHttp.delete).toHaveBeenCalledWith('/reservations/5/preorder-items/10');
        });
    });

    describe('reservations.printPreorder', () => {
        it('should POST print preorder', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await reservations.printPreorder(5);

            expect(mockHttp.post).toHaveBeenCalledWith('/reservations/5/print-preorder');
        });
    });
});
