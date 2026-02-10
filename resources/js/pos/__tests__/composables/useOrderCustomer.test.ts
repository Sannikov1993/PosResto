/**
 * useOrderCustomer Composable Unit Tests
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

// Mock useCurrentCustomer
const mockSetCurrentCustomer = vi.fn();
const mockClearCurrentCustomer = vi.fn();
const mockUpdateCurrentCustomer = vi.fn();

vi.mock('@/pos/composables/useCurrentCustomer.js', () => ({
    useCurrentCustomer: () => ({
        bonusBalance: { value: 0 },
        setCustomer: mockSetCurrentCustomer,
        clear: mockClearCurrentCustomer,
        updateCustomer: mockUpdateCurrentCustomer,
    }),
}));

import { useOrderCustomer } from '@/pos/composables/useOrderCustomer.js';

describe('useOrderCustomer', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have null customer data and defaults', () => {
            const oc = useOrderCustomer();

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
            expect(oc.loading.value).toBe(false);
            expect(oc.hasCustomer.value).toBe(false);
            expect(oc.customerName.value).toBe('');
            expect(oc.customerPhone.value).toBe('');
            expect(oc.customerBonusBalance.value).toBe(0);
            expect(oc.customerLoyaltyLevel.value).toBeNull();
            expect(oc.loyaltyDiscountPercent.value).toBe(0);
        });
    });

    describe('selectCustomer', () => {
        it('should set customer data and id', () => {
            const oc = useOrderCustomer();

            const customer = {
                id: 1,
                name: 'Иван Петров',
                phone: '+79001234567',
                bonus_balance: 500,
            } as any;

            oc.selectCustomer(customer);

            expect(oc.customerId.value).toBe(1);
            expect(oc.customerData.value).toEqual(customer);
            expect(oc.hasCustomer.value).toBe(true);
            expect(oc.customerName.value).toBe('Иван Петров');
            expect(oc.customerPhone.value).toBe('+79001234567');
        });

        it('should call setCurrentCustomer on the shared composable', () => {
            const oc = useOrderCustomer();

            const customer = { id: 1, name: 'Test' } as any;
            oc.selectCustomer(customer);

            expect(mockSetCurrentCustomer).toHaveBeenCalledWith(customer);
        });

        it('should clear customer when null is passed', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.selectCustomer(null);

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
        });

        it('should call onCustomerChange callback', () => {
            const onCustomerChange = vi.fn();
            const oc = useOrderCustomer({ onCustomerChange });

            const customer = { id: 1, name: 'Test' } as any;
            oc.selectCustomer(customer);

            expect(onCustomerChange).toHaveBeenCalledWith(customer, { isChange: false });
        });

        it('should detect customer change and reset discounts', () => {
            const mockDiscounts = {
                hasDiscounts: { value: true },
                resetAllDiscounts: vi.fn(),
            };

            const oc = useOrderCustomer({ discounts: mockDiscounts });

            oc.selectCustomer({ id: 1, name: 'First' } as any);
            oc.selectCustomer({ id: 2, name: 'Second' } as any);

            expect(mockDiscounts.resetAllDiscounts).toHaveBeenCalledWith(true);
        });

        it('should report isChange true when switching customers', () => {
            const onCustomerChange = vi.fn();
            const oc = useOrderCustomer({ onCustomerChange });

            oc.selectCustomer({ id: 1, name: 'First' } as any);
            oc.selectCustomer({ id: 2, name: 'Second' } as any);

            expect(onCustomerChange).toHaveBeenLastCalledWith(
                { id: 2, name: 'Second' },
                { isChange: true }
            );
        });

        it('should skip discount reset when option is set', () => {
            const mockDiscounts = {
                hasDiscounts: { value: true },
                resetAllDiscounts: vi.fn(),
            };

            const oc = useOrderCustomer({ discounts: mockDiscounts });

            oc.selectCustomer({ id: 1, name: 'First' } as any);
            oc.selectCustomer({ id: 2, name: 'Second' } as any, { skipDiscountReset: true });

            expect(mockDiscounts.resetAllDiscounts).not.toHaveBeenCalled();
        });

        it('should not reset discounts for same customer', () => {
            const mockDiscounts = {
                hasDiscounts: { value: true },
                resetAllDiscounts: vi.fn(),
            };

            const oc = useOrderCustomer({ discounts: mockDiscounts });

            oc.selectCustomer({ id: 1, name: 'Same' } as any);
            oc.selectCustomer({ id: 1, name: 'Same Updated' } as any);

            expect(mockDiscounts.resetAllDiscounts).not.toHaveBeenCalled();
        });
    });

    describe('clearCustomer', () => {
        it('should reset all customer data', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.clearCustomer();

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
            expect(oc.hasCustomer.value).toBe(false);
        });

        it('should call clearCurrentCustomer on shared composable', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.clearCustomer();

            expect(mockClearCurrentCustomer).toHaveBeenCalled();
        });

        it('should reset discounts when clearing', () => {
            const mockDiscounts = {
                hasDiscounts: { value: true },
                resetAllDiscounts: vi.fn(),
            };

            const oc = useOrderCustomer({ discounts: mockDiscounts });

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.clearCustomer();

            expect(mockDiscounts.resetAllDiscounts).toHaveBeenCalledWith(true);
        });

        it('should call onCustomerClear callback', () => {
            const onCustomerClear = vi.fn();
            const oc = useOrderCustomer({ onCustomerClear });

            oc.clearCustomer();

            expect(onCustomerClear).toHaveBeenCalledOnce();
        });
    });

    describe('updateCustomer', () => {
        it('should update customer data for matching id', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Old Name', phone: '+79001111111' } as any);
            oc.updateCustomer({ id: 1, name: 'New Name', phone: '+79001111111' } as any);

            expect(oc.customerName.value).toBe('New Name');
            expect(mockUpdateCurrentCustomer).toHaveBeenCalled();
        });

        it('should not update when ids do not match', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Original' } as any);
            oc.updateCustomer({ id: 999, name: 'Wrong' } as any);

            expect(oc.customerName.value).toBe('Original');
        });

        it('should not update when no customer is set', () => {
            const oc = useOrderCustomer();

            oc.updateCustomer({ id: 1, name: 'Test' } as any);

            expect(oc.customerData.value).toBeNull();
            expect(mockUpdateCurrentCustomer).not.toHaveBeenCalled();
        });

        it('should not update when updatedCustomer is null', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.updateCustomer(null as any);

            expect(oc.customerName.value).toBe('Test');
        });
    });

    describe('attachToOrder', () => {
        it('should POST customer to order via API', async () => {
            const mockApiInstance = {
                post: vi.fn().mockResolvedValue({
                    success: true,
                    order: { customer: { id: 1, name: 'Attached' } },
                }),
                delete: vi.fn(),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            const result = await oc.attachToOrder(100, { id: 1, name: 'Attached' } as any);

            expect(mockApiInstance.post).toHaveBeenCalledWith('/api/table-order/100/customer', {
                customer_id: 1,
            });
            expect(result).not.toBeNull();
            expect(result!.success).toBe(true);
        });

        it('should select customer from response after attach', async () => {
            const mockApiInstance = {
                post: vi.fn().mockResolvedValue({
                    success: true,
                    order: { customer: { id: 1, name: 'FromResponse' } },
                }),
                delete: vi.fn(),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            await oc.attachToOrder(100, { id: 1, name: 'Original' } as any);

            expect(oc.customerName.value).toBe('FromResponse');
        });

        it('should return null when api is not provided', async () => {
            const oc = useOrderCustomer();
            const result = await oc.attachToOrder(100, { id: 1, name: 'Test' } as any);

            expect(result).toBeNull();
        });

        it('should return null when orderId is missing', async () => {
            const mockApiInstance = { post: vi.fn(), delete: vi.fn() };
            const oc = useOrderCustomer({ api: mockApiInstance });

            const result = await oc.attachToOrder(0, { id: 1, name: 'Test' } as any);

            expect(result).toBeNull();
            expect(mockApiInstance.post).not.toHaveBeenCalled();
        });

        it('should return null when customer is missing', async () => {
            const mockApiInstance = { post: vi.fn(), delete: vi.fn() };
            const oc = useOrderCustomer({ api: mockApiInstance });

            const result = await oc.attachToOrder(100, null as any);

            expect(result).toBeNull();
        });

        it('should return null on unsuccessful response', async () => {
            const mockApiInstance = {
                post: vi.fn().mockResolvedValue({ success: false }),
                delete: vi.fn(),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            const result = await oc.attachToOrder(100, { id: 1, name: 'Test' } as any);

            expect(result).toBeNull();
        });

        it('should throw on API error', async () => {
            const mockApiInstance = {
                post: vi.fn().mockRejectedValue(new Error('Network error')),
                delete: vi.fn(),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });

            await expect(oc.attachToOrder(100, { id: 1, name: 'Test' } as any))
                .rejects.toThrow('Network error');
            expect(oc.loading.value).toBe(false);
        });

        it('should manage loading state during attach', async () => {
            let resolvePromise: (v: any) => void;
            const mockApiInstance = {
                post: vi.fn().mockReturnValue(new Promise((r) => { resolvePromise = r; })),
                delete: vi.fn(),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            const promise = oc.attachToOrder(100, { id: 1, name: 'Test' } as any);

            expect(oc.loading.value).toBe(true);

            resolvePromise!({ success: true, order: { customer: { id: 1, name: 'Test' } } });
            await promise;

            expect(oc.loading.value).toBe(false);
        });
    });

    describe('detachFromOrder', () => {
        it('should DELETE customer from order via API', async () => {
            const mockApiInstance = {
                post: vi.fn(),
                delete: vi.fn().mockResolvedValue({ success: true, order: {} }),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            oc.selectCustomer({ id: 1, name: 'Test' } as any);

            const result = await oc.detachFromOrder(100);

            expect(mockApiInstance.delete).toHaveBeenCalledWith('/api/table-order/100/customer');
            expect(result).not.toBeNull();
        });

        it('should clear customer after successful detach', async () => {
            const mockApiInstance = {
                post: vi.fn(),
                delete: vi.fn().mockResolvedValue({ success: true }),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });
            oc.selectCustomer({ id: 1, name: 'Test' } as any);

            await oc.detachFromOrder(100);

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
        });

        it('should return null when api is not provided', async () => {
            const oc = useOrderCustomer();
            const result = await oc.detachFromOrder(100);

            expect(result).toBeNull();
        });

        it('should return null when orderId is falsy', async () => {
            const mockApiInstance = { post: vi.fn(), delete: vi.fn() };
            const oc = useOrderCustomer({ api: mockApiInstance });

            const result = await oc.detachFromOrder(0);

            expect(result).toBeNull();
        });

        it('should throw on API error', async () => {
            const mockApiInstance = {
                post: vi.fn(),
                delete: vi.fn().mockRejectedValue(new Error('Detach failed')),
            };

            const oc = useOrderCustomer({ api: mockApiInstance });

            await expect(oc.detachFromOrder(100)).rejects.toThrow('Detach failed');
            expect(oc.loading.value).toBe(false);
        });
    });

    describe('initFromOrder', () => {
        it('should initialize customer from order with customer object', () => {
            const oc = useOrderCustomer();

            oc.initFromOrder({
                id: 100,
                customer: { id: 1, name: 'Order Customer', phone: '+79001111111' },
                customer_id: 1,
            } as any);

            expect(oc.customerId.value).toBe(1);
            expect(oc.customerName.value).toBe('Order Customer');
        });

        it('should set only customerId when order has customer_id but no customer object', () => {
            const oc = useOrderCustomer();

            oc.initFromOrder({
                id: 200,
                customer_id: 5,
            } as any);

            expect(oc.customerId.value).toBe(5);
            expect(oc.customerData.value).toBeNull();
        });

        it('should clear customer when order has no customer info', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Previous' } as any);
            oc.initFromOrder({ id: 300 } as any);

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
        });

        it('should clear customer when null order is passed', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);
            oc.initFromOrder(null);

            expect(oc.customerId.value).toBeNull();
            expect(oc.customerData.value).toBeNull();
        });

        it('should use skipDiscountReset when initializing from order', () => {
            const mockDiscounts = {
                hasDiscounts: { value: false },
                resetAllDiscounts: vi.fn(),
            };

            const oc = useOrderCustomer({ discounts: mockDiscounts });

            // First set a customer, then init from order with a different customer
            oc.selectCustomer({ id: 1, name: 'First' } as any);
            mockDiscounts.resetAllDiscounts.mockClear();

            oc.initFromOrder({
                id: 100,
                customer: { id: 2, name: 'From Order' },
            } as any);

            // selectCustomer is called with skipDiscountReset: true,
            // so resetAllDiscounts should NOT be called for the customer switch
            expect(mockDiscounts.resetAllDiscounts).not.toHaveBeenCalled();
        });
    });

    describe('computed: customerBonusBalance', () => {
        it('should return bonus_balance from customerData', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test', bonus_balance: 750 } as any);

            expect(oc.customerBonusBalance.value).toBe(750);
        });
    });

    describe('computed: customerLoyaltyLevel', () => {
        it('should return loyalty_level from customerData', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({
                id: 1,
                name: 'Test',
                loyalty_level: { id: 1, name: 'Gold', discount_percent: 10 },
            } as any);

            expect(oc.customerLoyaltyLevel.value).toEqual({
                id: 1,
                name: 'Gold',
                discount_percent: 10,
            });
        });

        it('should return null when no loyalty level', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({ id: 1, name: 'Test' } as any);

            expect(oc.customerLoyaltyLevel.value).toBeNull();
        });
    });

    describe('computed: loyaltyDiscountPercent', () => {
        it('should return discount_percent from loyalty level', () => {
            const oc = useOrderCustomer();

            oc.selectCustomer({
                id: 1,
                name: 'Test',
                loyalty_level: { id: 1, name: 'Silver', discount_percent: 5 },
            } as any);

            expect(oc.loyaltyDiscountPercent.value).toBe(5);
        });

        it('should return 0 when no loyalty level', () => {
            const oc = useOrderCustomer();

            expect(oc.loyaltyDiscountPercent.value).toBe(0);
        });
    });
});
