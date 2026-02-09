/**
 * Enterprise Composable: Управление скидками заказа
 *
 * Единый источник правды для работы со скидками.
 * Используется в зале (TableOrderAppWrapper) и доставке (NewDeliveryOrderModal).
 *
 * @example
 * const {
 *   discounts,
 *   resetAllDiscounts,
 *   applyDiscountData,
 *   totalDiscount
 * } = useOrderDiscounts();
 */

import { ref, computed, reactive } from 'vue';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:OrderDiscounts');

export function useOrderDiscounts(options = {}) {
    // Настройки
    const {
        onReset = null,  // Callback при сбросе скидок
        onApply = null,  // Callback при применении скидок
    } = options;

    // === State ===

    // Скидка по промокоду
    const promoDiscount = ref(0);
    const promoCode = ref('');

    // Ручная скидка (процент)
    const manualDiscountPercent = ref(0);

    // Скидка по акции
    const promotionDiscount = ref(0);
    const selectedPromotion = ref(null);

    // Скидка уровня лояльности
    const loyaltyDiscount = ref(0);
    const loyaltyLevelName = ref('');

    // Бонусы к списанию
    const bonusToSpend = ref(0);

    // Массив примененных скидок (для сохранения в заказ)
    const appliedDiscounts = ref([]);

    // Подарочные позиции (dish_id)
    const giftItems = ref([]);

    // Бесплатная доставка
    const freeDelivery = ref(false);

    // === Computed ===

    // Общая сумма скидок (без бонусов)
    const totalDiscountAmount = computed(() => {
        return promoDiscount.value +
               promotionDiscount.value +
               loyaltyDiscount.value;
    });

    // Общая сумма с учётом бонусов
    const totalDiscountWithBonus = computed(() => {
        return totalDiscountAmount.value + bonusToSpend.value;
    });

    // Есть ли активные скидки
    const hasDiscounts = computed(() => {
        return totalDiscountWithBonus.value > 0 ||
               appliedDiscounts.value.length > 0 ||
               giftItems.value.length > 0 ||
               freeDelivery.value;
    });

    // === Methods ===

    /**
     * Полный сброс ВСЕХ скидок
     * Enterprise: вызывается при смене/отвязке клиента
     */
    const resetAllDiscounts = (showToast = true) => {
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

        // Callback
        if (onReset) {
            onReset();
        }

        if (showToast && window.$toast) {
            window.$toast('Все скидки сброшены', 'info');
        }

        log.debug('All discounts reset');
    };

    /**
     * Применить данные от DiscountModal
     * @param {Object} discountData - данные от @apply события DiscountModal
     */
    const applyDiscountData = (discountData) => {
        log.debug('Applying discount data:', discountData);

        // Сохраняем applied_discounts
        appliedDiscounts.value = discountData.appliedDiscounts || [];

        // Парсим скидки по типам
        const promoDisc = appliedDiscounts.value.find(d => d.sourceType === 'promotion');
        const manualDisc = appliedDiscounts.value.find(d => d.sourceType === 'manual' || d.type === 'percent');
        const levelDisc = appliedDiscounts.value.find(d => d.sourceType === 'level' || d.type === 'level');
        const promoCodeDisc = appliedDiscounts.value.find(d => d.sourceType === 'promo_code');

        // Акция
        if (promoDisc) {
            selectedPromotion.value = { id: promoDisc.sourceId, name: promoDisc.name };
            promotionDiscount.value = promoDisc.amount || 0;
        } else {
            selectedPromotion.value = null;
            promotionDiscount.value = 0;
        }

        // Ручная скидка
        if (manualDisc && manualDisc.percent) {
            manualDiscountPercent.value = manualDisc.percent;
        } else {
            manualDiscountPercent.value = 0;
        }

        // Уровень лояльности
        loyaltyDiscount.value = levelDisc?.amount || 0;
        loyaltyLevelName.value = levelDisc?.name?.replace('Скидка ', '') || '';

        // Промокод
        if (promoCodeDisc) {
            promoCode.value = promoCodeDisc.code || '';
            promoDiscount.value = promoCodeDisc.amount || 0;
        } else {
            promoCode.value = '';
            promoDiscount.value = 0;
        }

        // Бонусы
        bonusToSpend.value = discountData.bonusToSpend || 0;

        // Callback
        if (onApply) {
            onApply(discountData);
        }
    };

    /**
     * Установить скидку уровня лояльности
     * @param {number} amount - сумма скидки
     * @param {string} levelName - название уровня
     */
    const setLoyaltyDiscount = (amount, levelName = '') => {
        loyaltyDiscount.value = amount || 0;
        loyaltyLevelName.value = levelName;
    };

    /**
     * Установить бонусы к списанию
     * @param {number} amount - количество бонусов
     */
    const setBonusToSpend = (amount) => {
        bonusToSpend.value = Math.max(0, amount || 0);
    };

    /**
     * Получить данные для отправки на сервер
     */
    const getServerData = () => {
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

    /**
     * Инициализировать из данных заказа (с сервера)
     * @param {Object} order - объект заказа
     */
    const initFromOrder = (order) => {
        if (!order) {
            resetAllDiscounts(false);
            return;
        }

        // Восстанавливаем состояние из заказа
        promoCode.value = order.promo_code || '';
        manualDiscountPercent.value = order.discount_percent || 0;
        bonusToSpend.value = order.pending_bonus_spend || order.bonus_used || 0;
        appliedDiscounts.value = order.applied_discounts || [];
        freeDelivery.value = order.free_delivery || false;

        // Парсим applied_discounts для восстановления отдельных значений
        if (appliedDiscounts.value.length > 0) {
            const levelDisc = appliedDiscounts.value.find(d => d.sourceType === 'level' || d.type === 'level');
            const promoDisc = appliedDiscounts.value.find(d => d.sourceType === 'promotion');
            const promoCodeDisc = appliedDiscounts.value.find(d => d.sourceType === 'promo_code');

            loyaltyDiscount.value = levelDisc?.amount || 0;
            loyaltyLevelName.value = levelDisc?.name?.replace('Скидка ', '') || '';
            promotionDiscount.value = promoDisc?.amount || 0;
            promoDiscount.value = promoCodeDisc?.amount || 0;

            if (promoDisc) {
                selectedPromotion.value = { id: promoDisc.sourceId, name: promoDisc.name };
            }
        } else {
            // Fallback на отдельные поля заказа
            loyaltyDiscount.value = order.loyalty_discount_amount || 0;
        }

        log.debug('Initialized from order:', order.id);
    };

    // === Return ===
    return {
        // State (refs)
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

        // Computed
        totalDiscountAmount,
        totalDiscountWithBonus,
        hasDiscounts,

        // Methods
        resetAllDiscounts,
        applyDiscountData,
        setLoyaltyDiscount,
        setBonusToSpend,
        getServerData,
        initFromOrder,
    };
}

export default useOrderDiscounts;
