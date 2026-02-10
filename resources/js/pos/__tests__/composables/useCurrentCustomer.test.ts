/**
 * useCurrentCustomer Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        customers: {
            get: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

// Need to reset module state between tests since useCurrentCustomer uses singleton refs
let useCurrentCustomer: () => ReturnType<typeof import('@/pos/composables/useCurrentCustomer.js')['useCurrentCustomer']>;

describe('useCurrentCustomer', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        vi.resetModules();

        vi.doMock('@/shared/services/logger.js', () => ({
            createLogger: () => ({
                debug: vi.fn(),
                warn: vi.fn(),
                error: vi.fn(),
                info: vi.fn(),
            }),
        }));

        vi.doMock('@/pos/api/index.js', () => ({
            default: mockApi,
        }));

        const module = await import('@/pos/composables/useCurrentCustomer.js');
        useCurrentCustomer = module.useCurrentCustomer;
    });

    describe('initial state', () => {
        it('should have null customer and computed defaults', () => {
            const cc = useCurrentCustomer();

            expect(cc.customer.value).toBeNull();
            expect(cc.loading.value).toBe(false);
            expect(cc.error.value).toBeNull();
            expect(cc.customerId.value).toBeNull();
            expect(cc.customerName.value).toBe('');
            expect(cc.customerPhone.value).toBe('');
            expect(cc.bonusBalance.value).toBe(0);
            expect(cc.loyaltyLevel.value).toBeNull();
            expect(cc.loyaltyLevelName.value).toBe('');
            expect(cc.loyaltyDiscount.value).toBe(0);
            expect(cc.hasCustomer.value).toBe(false);
            expect(cc.isNewCustomer.value).toBe(true);
        });
    });

    describe('setCustomer', () => {
        it('should set customer data from a Customer object', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({
                id: 1,
                name: 'Иван Петров',
                phone: '+79001234567',
                email: 'ivan@test.com',
                bonus_balance: 500,
                loyalty_level: { id: 1, name: 'Золотой', discount_percent: 10 },
                total_orders: 5,
                orders_count: 5,
                total_spent: 10000,
                is_new: false,
                is_blocked: false,
                created_at: '2024-01-01',
            } as any);

            expect(cc.customer.value).not.toBeNull();
            expect(cc.customerId.value).toBe(1);
            expect(cc.customerName.value).toBe('Иван Петров');
            expect(cc.customerPhone.value).toBe('+79001234567');
            expect(cc.bonusBalance.value).toBe(500);
            expect(cc.loyaltyLevel.value).toEqual({ id: 1, name: 'Золотой', discount_percent: 10 });
            expect(cc.loyaltyLevelName.value).toBe('Золотой');
            expect(cc.loyaltyDiscount.value).toBe(10);
            expect(cc.hasCustomer.value).toBe(true);
            expect(cc.isNewCustomer.value).toBe(false);
        });

        it('should clear customer when null is passed', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({
                id: 1,
                name: 'Test',
                phone: '+7000',
                bonus_balance: 0,
                total_orders: 0,
                total_spent: 0,
                is_blocked: false,
                created_at: '2024-01-01',
            } as any);

            expect(cc.hasCustomer.value).toBe(true);

            cc.setCustomer(null);

            expect(cc.customer.value).toBeNull();
            expect(cc.hasCustomer.value).toBe(false);
        });

        it('should handle customer with missing optional fields', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({
                id: 2,
                name: 'Мария',
                phone: '+79007654321',
                bonus_balance: 0,
                total_orders: 0,
                total_spent: 0,
                is_blocked: false,
                created_at: '2024-01-01',
            } as any);

            expect(cc.customerId.value).toBe(2);
            expect(cc.loyaltyLevel.value).toBeNull();
            expect(cc.loyaltyLevelName.value).toBe('');
            expect(cc.loyaltyDiscount.value).toBe(0);
        });
    });

    describe('setFromOrder', () => {
        it('should set customer from order.customer', () => {
            const cc = useCurrentCustomer();

            cc.setFromOrder({
                id: 100,
                customer_id: 1,
                customer: {
                    id: 1,
                    name: 'Заказчик',
                    phone: '+79001111111',
                    bonus_balance: 200,
                    total_orders: 3,
                    total_spent: 5000,
                    is_blocked: false,
                    created_at: '2024-01-01',
                },
            } as any);

            expect(cc.customerId.value).toBe(1);
            expect(cc.customerName.value).toBe('Заказчик');
        });

        it('should call loadById when order has customer_id but no customer object', () => {
            const cc = useCurrentCustomer();
            mockApi.customers.get.mockResolvedValue({
                id: 5,
                name: 'Loaded Customer',
                bonus_balance: 0,
            });

            cc.setFromOrder({
                id: 200,
                customer_id: 5,
            } as any);

            expect(mockApi.customers.get).toHaveBeenCalledWith(5);
        });

        it('should clear customer when order has no customer info', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test' } as any);
            cc.setFromOrder({ id: 300 } as any);

            expect(cc.customer.value).toBeNull();
        });

        it('should clear customer when null order is passed', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test' } as any);
            cc.setFromOrder(null);

            expect(cc.customer.value).toBeNull();
        });
    });

    describe('setFromReservation', () => {
        it('should set customer from reservation.customer', () => {
            const cc = useCurrentCustomer();

            cc.setFromReservation({
                id: 10,
                customer_id: 3,
                customer: {
                    id: 3,
                    name: 'Гость',
                    phone: '+79003333333',
                    bonus_balance: 100,
                    total_orders: 1,
                    total_spent: 2000,
                    is_blocked: false,
                    created_at: '2024-01-01',
                },
            } as any);

            expect(cc.customerId.value).toBe(3);
            expect(cc.customerName.value).toBe('Гость');
        });

        it('should call loadById when reservation has customer_id only', () => {
            const cc = useCurrentCustomer();
            mockApi.customers.get.mockResolvedValue({
                id: 7,
                name: 'Reserved',
                bonus_balance: 0,
            });

            cc.setFromReservation({
                id: 20,
                customer_id: 7,
            } as any);

            expect(mockApi.customers.get).toHaveBeenCalledWith(7);
        });

        it('should clear customer when reservation has no customer', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test' } as any);
            cc.setFromReservation({ id: 30 } as any);

            expect(cc.customer.value).toBeNull();
        });
    });

    describe('loadById', () => {
        it('should load customer from API and set it', async () => {
            const cc = useCurrentCustomer();

            mockApi.customers.get.mockResolvedValue({
                id: 10,
                name: 'API Customer',
                phone: '+79005555555',
                bonus_balance: 1000,
                loyalty_level: { id: 2, name: 'Серебряный', discount_percent: 5 },
            });

            await cc.loadById(10);

            expect(mockApi.customers.get).toHaveBeenCalledWith(10);
            expect(cc.customerId.value).toBe(10);
            expect(cc.customerName.value).toBe('API Customer');
            expect(cc.loading.value).toBe(false);
        });

        it('should set error on API failure', async () => {
            const cc = useCurrentCustomer();

            mockApi.customers.get.mockRejectedValue(new Error('Not found'));

            await cc.loadById(999);

            expect(cc.error.value).toBe('Not found');
            expect(cc.loading.value).toBe(false);
        });

        it('should do nothing for falsy id', async () => {
            const cc = useCurrentCustomer();

            await cc.loadById(0);

            expect(mockApi.customers.get).not.toHaveBeenCalled();
        });
    });

    describe('loadFreshData', () => {
        it('should refresh existing customer data from API', async () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({
                id: 1,
                name: 'Old Name',
                bonus_balance: 100,
            } as any);

            mockApi.customers.get.mockResolvedValue({
                id: 1,
                name: 'Updated Name',
                bonus_balance: 500,
            });

            await cc.loadFreshData();

            expect(mockApi.customers.get).toHaveBeenCalledWith(1);
            expect(cc.customerName.value).toBe('Updated Name');
            expect(cc.bonusBalance.value).toBe(500);
        });

        it('should do nothing when no current customer', async () => {
            const cc = useCurrentCustomer();

            await cc.loadFreshData();

            expect(mockApi.customers.get).not.toHaveBeenCalled();
        });

        it('should set error on API failure during refresh', async () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test' } as any);
            mockApi.customers.get.mockRejectedValue(new Error('Server error'));

            await cc.loadFreshData();

            expect(cc.error.value).toBe('Server error');
            expect(cc.loading.value).toBe(false);
        });
    });

    describe('updateBonusBalance', () => {
        it('should set absolute bonus balance', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test', bonus_balance: 100 } as any);
            cc.updateBonusBalance(500);

            expect(cc.bonusBalance.value).toBe(500);
        });

        it('should add delta to existing bonus balance', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test', bonus_balance: 100 } as any);
            cc.updateBonusBalance(50, true);

            expect(cc.bonusBalance.value).toBe(150);
        });

        it('should do nothing when no current customer', () => {
            const cc = useCurrentCustomer();

            cc.updateBonusBalance(500); // should not throw

            expect(cc.bonusBalance.value).toBe(0);
        });
    });

    describe('updateCustomer', () => {
        it('should merge partial data into current customer', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({
                id: 1,
                name: 'Original',
                phone: '+79001111111',
                bonus_balance: 100,
            } as any);

            cc.updateCustomer({ name: 'Updated', bonus_balance: 999 } as any);

            expect(cc.customerName.value).toBe('Updated');
            expect(cc.bonusBalance.value).toBe(999);
            expect(cc.customerPhone.value).toBe('+79001111111'); // unchanged
        });

        it('should do nothing when no current customer', () => {
            const cc = useCurrentCustomer();

            cc.updateCustomer({ name: 'Test' } as any); // should not throw

            expect(cc.customer.value).toBeNull();
        });
    });

    describe('clear', () => {
        it('should reset customer and error', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Test', bonus_balance: 100 } as any);

            cc.clear();

            expect(cc.customer.value).toBeNull();
            expect(cc.error.value).toBeNull();
            expect(cc.hasCustomer.value).toBe(false);
            expect(cc.customerId.value).toBeNull();
        });
    });

    describe('isNewCustomer', () => {
        it('should be true when customer has is_new flag', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'New', is_new: true } as any);

            expect(cc.isNewCustomer.value).toBe(true);
        });

        it('should be false for existing customer', () => {
            const cc = useCurrentCustomer();

            cc.setCustomer({ id: 1, name: 'Existing', is_new: false } as any);

            expect(cc.isNewCustomer.value).toBe(false);
        });

        it('should be true when no customer is set', () => {
            const cc = useCurrentCustomer();

            expect(cc.isNewCustomer.value).toBe(true);
        });
    });
});
