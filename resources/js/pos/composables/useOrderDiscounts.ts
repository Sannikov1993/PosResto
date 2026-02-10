/**
 * Enterprise Composable: Управление скидками заказа
 */

import { ref, computed } from 'vue';
import { createLogger } from '../../shared/services/logger.js';
import type { Order, Promotion } from '@/shared/types';

const log = createLogger('POS:OrderDiscounts');

interface AppliedDiscount {
    sourceType?: string;
    sourceId?: number;
    type?: string;
    name?: string;
    amount?: number;
    percent?: number;
    code?: string;
    [key: string]: unknown;
}

interface DiscountData {
    appliedDiscounts?: AppliedDiscount[];
    bonusToSpend?: number;
    [key: string]: unknown;
}

interface ServerDiscountData {
    discount_amount: number;
    discount_percent: number;
    promo_code: string | null;
    promotion_id: number | null;
    applied_discounts: AppliedDiscount[];
    bonus_to_spend: number;
    free_delivery: boolean;
}

interface UseOrderDiscountsOptions {
    onReset?: (() => void) | null;
    onApply?: ((data: DiscountData) => void) | null;
}

declare global {
    interface Window {
        $toast?: (message: string, type?: string) => void;
    }
}

export function useOrderDiscounts(options: UseOrderDiscountsOptions = {}) {
    const {
        onReset = null,
        onApply = null,
    } = options;

    const promoDiscount = ref(0);
    const promoCode = ref('');
    const manualDiscountPercent = ref(0);
    const promotionDiscount = ref(0);
    const selectedPromotion = ref<{ id: number; name: string } | null>(null);
    const loyaltyDiscount = ref(0);
    const loyaltyLevelName = ref('');
    const bonusToSpend = ref(0);
    const appliedDiscounts = ref<AppliedDiscount[]>([]);
    const giftItems = ref<number[]>([]);
    const freeDelivery = ref(false);

    const totalDiscountAmount = computed(() => {
        return promoDiscount.value +
               promotionDiscount.value +
               loyaltyDiscount.value;
    });

    const totalDiscountWithBonus = computed(() => {
        return totalDiscountAmount.value + bonusToSpend.value;
    });

    const hasDiscounts = computed(() => {
        return totalDiscountWithBonus.value > 0 ||
               appliedDiscounts.value.length > 0 ||
               giftItems.value.length > 0 ||
               freeDelivery.value;
    });

    const resetAllDiscounts = (showToast: boolean | undefined = true): void => {
        promoDiscount.value = 0;
        promoCode.value = '';
        manualDiscountPercent.value = 0;
        promotionDiscount.value = 0;
        selectedPromotion.value = null;
        loyaltyDiscount.value = 0;
        loyaltyLevelName.value = '';
        bonusToSpend.value = 0;
        appliedDiscounts.value = [];
        giftItems.value = [];
        freeDelivery.value = false;

        if (onReset) {
            onReset();
        }

        if (showToast && window.$toast) {
            window.$toast('Все скидки сброшены', 'info');
        }

        log.debug('All discounts reset');
    };

    const applyDiscountData = (discountData: DiscountData): void => {
        log.debug('Applying discount data:', discountData);

        appliedDiscounts.value = discountData.appliedDiscounts || [];

        const promoDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'promotion');
        const manualDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'manual' || d.type === 'percent');
        const levelDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'level' || d.type === 'level');
        const promoCodeDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'promo_code');

        if (promoDisc) {
            selectedPromotion.value = { id: promoDisc.sourceId!, name: promoDisc.name! };
            promotionDiscount.value = promoDisc.amount || 0;
        } else {
            selectedPromotion.value = null;
            promotionDiscount.value = 0;
        }

        if (manualDisc && manualDisc.percent) {
            manualDiscountPercent.value = manualDisc.percent;
        } else {
            manualDiscountPercent.value = 0;
        }

        loyaltyDiscount.value = levelDisc?.amount || 0;
        loyaltyLevelName.value = levelDisc?.name?.replace('Скидка ', '') || '';

        if (promoCodeDisc) {
            promoCode.value = promoCodeDisc.code || '';
            promoDiscount.value = promoCodeDisc.amount || 0;
        } else {
            promoCode.value = '';
            promoDiscount.value = 0;
        }

        bonusToSpend.value = discountData.bonusToSpend || 0;

        if (onApply) {
            onApply(discountData);
        }
    };

    const setLoyaltyDiscount = (amount: number, levelName = ''): void => {
        loyaltyDiscount.value = amount || 0;
        loyaltyLevelName.value = levelName;
    };

    const setBonusToSpend = (amount: number): void => {
        bonusToSpend.value = Math.max(0, amount || 0);
    };

    const getServerData = (): ServerDiscountData => {
        return {
            discount_amount: totalDiscountAmount.value,
            discount_percent: manualDiscountPercent.value,
            promo_code: promoCode.value || null,
            promotion_id: selectedPromotion.value?.id || null,
            applied_discounts: appliedDiscounts.value,
            bonus_to_spend: bonusToSpend.value,
            free_delivery: freeDelivery.value,
        };
    };

    const initFromOrder = (order: Order | null): void => {
        if (!order) {
            resetAllDiscounts(false);
            return;
        }

        const o = order as Record<string, any>;
        promoCode.value = (o.promo_code as string) || '';
        manualDiscountPercent.value = (o.discount_percent as number) || 0;
        bonusToSpend.value = (o.pending_bonus_spend as number) || (o.bonus_used as number) || 0;
        appliedDiscounts.value = (o.applied_discounts as AppliedDiscount[]) || [];
        freeDelivery.value = (o.free_delivery as boolean) || false;

        if (appliedDiscounts.value.length > 0) {
            const levelDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'level' || d.type === 'level');
            const promoDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'promotion');
            const promoCodeDisc = appliedDiscounts.value.find((d: any) => d.sourceType === 'promo_code');

            loyaltyDiscount.value = levelDisc?.amount || 0;
            loyaltyLevelName.value = levelDisc?.name?.replace('Скидка ', '') || '';
            promotionDiscount.value = promoDisc?.amount || 0;
            promoDiscount.value = promoCodeDisc?.amount || 0;

            if (promoDisc) {
                selectedPromotion.value = { id: promoDisc.sourceId!, name: promoDisc.name! };
            }
        } else {
            loyaltyDiscount.value = (o.loyalty_discount_amount as number) || 0;
        }

        log.debug('Initialized from order:', order.id);
    };

    return {
        promoDiscount,
        promoCode,
        manualDiscountPercent,
        promotionDiscount,
        selectedPromotion,
        loyaltyDiscount,
        loyaltyLevelName,
        bonusToSpend,
        appliedDiscounts,
        giftItems,
        freeDelivery,
        totalDiscountAmount,
        totalDiscountWithBonus,
        hasDiscounts,
        resetAllDiscounts,
        applyDiscountData,
        setLoyaltyDiscount,
        setBonusToSpend,
        getServerData,
        initFromOrder,
    };
}

export default useOrderDiscounts;
