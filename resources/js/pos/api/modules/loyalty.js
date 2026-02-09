import http, { extractArray } from '../httpClient';

const loyalty = {
    async getBonusSettings() {
        try {
            return await http.get('/loyalty/bonus-settings');
        } catch {
            return null;
        }
    },

    async calculateDiscount(params) {
        return http.post('/loyalty/calculate-discount', params);
    },

    async validatePromoCode(code, customerId = null, orderTotal = 0) {
        return http.post('/loyalty/promo-codes/validate', {
            code,
            customer_id: customerId,
            order_total: orderTotal
        });
    },

    async earnBonus(customerId, amount, orderId = null, description = null) {
        return http.post('/loyalty/bonus/earn', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    async spendBonus(customerId, amount, orderId = null, description = null) {
        return http.post('/loyalty/bonus/spend', {
            customer_id: customerId,
            amount,
            order_id: orderId,
            description
        });
    },

    async getActivePromotions() {
        const res = await http.get('/loyalty/promotions/active');
        return extractArray(res);
    },

    async getCustomerLoyalty(customerId) {
        return http.get(`/customers/${customerId}`);
    }
};

const giftCertificates = {
    async check(code) {
        return http.post('/gift-certificates/check', { code });
    },

    async use(certificateId, amount, orderId = null, customerId = null) {
        return http.post(`/gift-certificates/${certificateId}/use`, {
            amount,
            order_id: orderId,
            customer_id: customerId
        });
    }
};

export { loyalty, giftCertificates };
