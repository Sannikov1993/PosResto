/**
 * useOrderDiscounts Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
    }),
}));

import { useOrderDiscounts } from '@/pos/composables/useOrderDiscounts.js';

describe('useOrderDiscounts', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Clear $toast mock
        delete (window as any).$toast;
    });

    describe('initial state', () => {
        it('should have zero discounts', () => {
            const discounts = useOrderDiscounts();

            expect(discounts.promoDiscount.value).toBe(0);
            expect(discounts.promoCode.value).toBe('');
            expect(discounts.manualDiscountPercent.value).toBe(0);
            expect(discounts.promotionDiscount.value).toBe(0);
            expect(discounts.selectedPromotion.value).toBeNull();
            expect(discounts.loyaltyDiscount.value).toBe(0);
            expect(discounts.bonusToSpend.value).toBe(0);
        });

        it('should have zero totalDiscountAmount', () => {
            const { totalDiscountAmount, totalDiscountWithBonus } = useOrderDiscounts();

            expect(totalDiscountAmount.value).toBe(0);
            expect(totalDiscountWithBonus.value).toBe(0);
        });

        it('should have hasDiscounts false', () => {
            const { hasDiscounts } = useOrderDiscounts();
            expect(hasDiscounts.value).toBe(false);
        });
    });

    describe('totalDiscountAmount', () => {
        it('should sum promo + promotion + loyalty discounts', () => {
            const discounts = useOrderDiscounts();

            discounts.promoDiscount.value = 100;
            discounts.promotionDiscount.value = 200;
            discounts.loyaltyDiscount.value = 50;

            expect(discounts.totalDiscountAmount.value).toBe(350);
        });
    });

    describe('totalDiscountWithBonus', () => {
        it('should include bonus spending', () => {
            const discounts = useOrderDiscounts();

            discounts.promoDiscount.value = 100;
            discounts.bonusToSpend.value = 200;

            expect(discounts.totalDiscountWithBonus.value).toBe(300);
        });
    });

    describe('hasDiscounts', () => {
        it('should be true when totalDiscountWithBonus > 0', () => {
            const discounts = useOrderDiscounts();
            discounts.promoDiscount.value = 100;

            expect(discounts.hasDiscounts.value).toBe(true);
        });

        it('should be true when appliedDiscounts has items', () => {
            const discounts = useOrderDiscounts();
            discounts.appliedDiscounts.value = [{ sourceType: 'manual', amount: 50 }];

            expect(discounts.hasDiscounts.value).toBe(true);
        });

        it('should be true when freeDelivery', () => {
            const discounts = useOrderDiscounts();
            discounts.freeDelivery.value = true;

            expect(discounts.hasDiscounts.value).toBe(true);
        });

        it('should be true when gift items', () => {
            const discounts = useOrderDiscounts();
            discounts.giftItems.value = [1, 2];

            expect(discounts.hasDiscounts.value).toBe(true);
        });
    });

    describe('resetAllDiscounts', () => {
        it('should reset all discount values', () => {
            const discounts = useOrderDiscounts();

            discounts.promoDiscount.value = 100;
            discounts.promoCode.value = 'TEST';
            discounts.manualDiscountPercent.value = 15;
            discounts.promotionDiscount.value = 200;
            discounts.selectedPromotion.value = { id: 1, name: 'Test' };
            discounts.loyaltyDiscount.value = 50;
            discounts.bonusToSpend.value = 300;
            discounts.freeDelivery.value = true;

            discounts.resetAllDiscounts(false);

            expect(discounts.promoDiscount.value).toBe(0);
            expect(discounts.promoCode.value).toBe('');
            expect(discounts.manualDiscountPercent.value).toBe(0);
            expect(discounts.promotionDiscount.value).toBe(0);
            expect(discounts.selectedPromotion.value).toBeNull();
            expect(discounts.loyaltyDiscount.value).toBe(0);
            expect(discounts.bonusToSpend.value).toBe(0);
            expect(discounts.freeDelivery.value).toBe(false);
            expect(discounts.appliedDiscounts.value).toEqual([]);
            expect(discounts.giftItems.value).toEqual([]);
        });

        it('should call onReset callback', () => {
            const onReset = vi.fn();
            const discounts = useOrderDiscounts({ onReset });

            discounts.resetAllDiscounts(false);

            expect(onReset).toHaveBeenCalledOnce();
        });

        it('should show toast when enabled', () => {
            (window as any).$toast = vi.fn();
            const discounts = useOrderDiscounts();

            discounts.resetAllDiscounts(true);

            expect((window as any).$toast).toHaveBeenCalledWith('Все скидки сброшены', 'info');
        });
    });

    describe('applyDiscountData', () => {
        it('should apply promotion discount', () => {
            const discounts = useOrderDiscounts();

            discounts.applyDiscountData({
                appliedDiscounts: [
                    { sourceType: 'promotion', sourceId: 1, name: 'Акция 2+1', amount: 500 },
                ],
            });

            expect(discounts.selectedPromotion.value).toEqual({ id: 1, name: 'Акция 2+1' });
            expect(discounts.promotionDiscount.value).toBe(500);
        });

        it('should apply manual percent discount', () => {
            const discounts = useOrderDiscounts();

            discounts.applyDiscountData({
                appliedDiscounts: [
                    { sourceType: 'manual', type: 'percent', percent: 10, amount: 200 },
                ],
            });

            expect(discounts.manualDiscountPercent.value).toBe(10);
        });

        it('should apply loyalty level discount', () => {
            const discounts = useOrderDiscounts();

            discounts.applyDiscountData({
                appliedDiscounts: [
                    { sourceType: 'level', type: 'level', name: 'Скидка Золотой', amount: 150 },
                ],
            });

            expect(discounts.loyaltyDiscount.value).toBe(150);
            expect(discounts.loyaltyLevelName.value).toBe('Золотой');
        });

        it('should apply promo code discount', () => {
            const discounts = useOrderDiscounts();

            discounts.applyDiscountData({
                appliedDiscounts: [
                    { sourceType: 'promo_code', code: 'SUMMER10', amount: 100 },
                ],
            });

            expect(discounts.promoCode.value).toBe('SUMMER10');
            expect(discounts.promoDiscount.value).toBe(100);
        });

        it('should apply bonus spending', () => {
            const discounts = useOrderDiscounts();

            discounts.applyDiscountData({
                appliedDiscounts: [],
                bonusToSpend: 500,
            });

            expect(discounts.bonusToSpend.value).toBe(500);
        });

        it('should call onApply callback', () => {
            const onApply = vi.fn();
            const discounts = useOrderDiscounts({ onApply });

            const data = { appliedDiscounts: [] };
            discounts.applyDiscountData(data);

            expect(onApply).toHaveBeenCalledWith(data);
        });
    });

    describe('setLoyaltyDiscount', () => {
        it('should set loyalty discount and level name', () => {
            const discounts = useOrderDiscounts();

            discounts.setLoyaltyDiscount(200, 'Серебряный');

            expect(discounts.loyaltyDiscount.value).toBe(200);
            expect(discounts.loyaltyLevelName.value).toBe('Серебряный');
        });
    });

    describe('setBonusToSpend', () => {
        it('should set bonus amount', () => {
            const discounts = useOrderDiscounts();

            discounts.setBonusToSpend(500);

            expect(discounts.bonusToSpend.value).toBe(500);
        });

        it('should not allow negative values', () => {
            const discounts = useOrderDiscounts();

            discounts.setBonusToSpend(-100);

            expect(discounts.bonusToSpend.value).toBe(0);
        });
    });

    describe('getServerData', () => {
        it('should return formatted data for API', () => {
            const discounts = useOrderDiscounts();

            discounts.promoCode.value = 'TEST';
            discounts.manualDiscountPercent.value = 10;
            discounts.bonusToSpend.value = 200;
            discounts.freeDelivery.value = true;
            discounts.selectedPromotion.value = { id: 5, name: 'Test Promo' };

            const data = discounts.getServerData();

            expect(data).toEqual({
                discount_amount: 0,
                discount_percent: 10,
                promo_code: 'TEST',
                promotion_id: 5,
                applied_discounts: [],
                bonus_to_spend: 200,
                free_delivery: true,
            });
        });

        it('should return nulls for empty fields', () => {
            const discounts = useOrderDiscounts();

            const data = discounts.getServerData();

            expect(data.promo_code).toBeNull();
            expect(data.promotion_id).toBeNull();
        });
    });

    describe('initFromOrder', () => {
        it('should initialize from order data', () => {
            const discounts = useOrderDiscounts();

            discounts.initFromOrder({
                id: 1,
                promo_code: 'SALE',
                discount_percent: 5,
                pending_bonus_spend: 100,
                free_delivery: true,
                applied_discounts: [
                    { sourceType: 'level', type: 'level', name: 'Скидка Бронзовый', amount: 50 },
                ],
            } as any);

            expect(discounts.promoCode.value).toBe('SALE');
            expect(discounts.manualDiscountPercent.value).toBe(5);
            expect(discounts.bonusToSpend.value).toBe(100);
            expect(discounts.freeDelivery.value).toBe(true);
            expect(discounts.loyaltyDiscount.value).toBe(50);
            expect(discounts.loyaltyLevelName.value).toBe('Бронзовый');
        });

        it('should reset when null order', () => {
            const discounts = useOrderDiscounts();
            discounts.promoCode.value = 'TEST';

            discounts.initFromOrder(null);

            expect(discounts.promoCode.value).toBe('');
        });
    });
});
