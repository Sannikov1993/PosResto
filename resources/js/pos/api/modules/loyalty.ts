import http, { extractArray } from '../httpClient.js';
import type { Promotion, Customer, GiftCertificate } from '@/shared/types';

interface BonusSettings {
    earn_percent?: number;
    spend_percent?: number;
    [key: string]: unknown;
}

interface DiscountResult {
    discount?: number;
    [key: string]: unknown;
}

interface PromoCodeValidation {
    valid: boolean;
    discount?: number;
    [key: string]: unknown;
}

const loyalty = {
    async getBonusSettings(): Promise<BonusSettings | null> {
        try {
            return await http.get('/loyalty/bonus-settings');
        } catch {
            return null;
        }
    },

    async calculateDiscount(params: Record<string, any>): Promise<DiscountResult> {
        return http.post('/loyalty/calculate-discount', params) as Promise<DiscountResult>;
    },

    async validatePromoCode(code: string, customerId: number | null = null, orderTotal = 0): Promise<PromoCodeValidation> {
        return http.post('/loyalty/promo-codes/validate', {
            code,
            customer_id: customerId,
            order_total: orderTotal
        }) as Promise<PromoCodeValidation>;
    },

    async earnBonus(
        customerId: number,
        amount: number,
        orderId: number | null = null,
        description: string | null = null
    ): Promise<unknown> {
        return http.post('/loyalty/bonus/earn', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    async spendBonus(
        customerId: number,
        amount: number,
        orderId: number | null = null,
        description: string | null = null
    ): Promise<unknown> {
        return http.post('/loyalty/bonus/spend', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    async getActivePromotions(): Promise<Promotion[]> {
        const res = await http.get('/loyalty/promotions/active');
        return extractArray<Promotion>(res);
    },

    async getCustomerLoyalty(customerId: number): Promise<unknown> {
        return http.get(`/customers/${customerId}`);
    }
};

const giftCertificates = {
    async check(code: string): Promise<unknown> {
        return http.post('/gift-certificates/check', { code });
    },

    async use(
        certificateId: number,
        amount: number,
        orderId: number | null = null,
        customerId: number | null = null
    ): Promise<unknown> {
        return http.post(`/gift-certificates/${certificateId}/use`, {
            amount,
            order_id: orderId,
            customer_id: customerId
        });
    }
};

export { loyalty, giftCertificates };
