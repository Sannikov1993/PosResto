/**
 * Утилита для форматирования сумм с учётом настройки округления
 */

import { ref } from 'vue';

// Кэш настройки округления
const roundAmounts = ref(null);
let settingsLoaded = false;
let loadingPromise = null;

/**
 * Загрузить настройки из API
 */
async function loadSettings() {
    if (settingsLoaded) return;
    if (loadingPromise) return loadingPromise;

    loadingPromise = (async () => {
        try {
            const response = await fetch('/api/settings/general');
            const data = await response.json();
            if (data.success && data.data) {
                roundAmounts.value = data.data.round_amounts || false;
            }
            settingsLoaded = true;
        } catch (error) {
            console.error('Failed to load settings:', error);
            roundAmounts.value = false;
            settingsLoaded = true;
        }
    })();

    return loadingPromise;
}

/**
 * Установить настройку округления напрямую (для использования из store)
 * @param {boolean} value
 */
export function setRoundAmounts(value) {
    roundAmounts.value = value;
    settingsLoaded = true;
}

/**
 * Получить текущее значение настройки округления
 * @returns {boolean}
 */
export function getRoundAmounts() {
    return roundAmounts.value;
}

/**
 * Форматировать сумму с учётом настройки округления
 * Округление всегда в пользу клиента (вниз)
 * @param {number|string} amount - Сумма для форматирования
 * @param {boolean} forceRound - Принудительно округлить независимо от настройки
 * @returns {number} Отформатированная сумма
 */
export function formatAmount(amount, forceRound = false) {
    const num = parseFloat(amount) || 0;

    if (forceRound || roundAmounts.value) {
        // Округляем в пользу клиента (вниз)
        return Math.floor(num);
    }

    // Округляем до 2 знаков после запятой
    return Math.round(num * 100) / 100;
}

/**
 * Форматировать сумму для отображения (со знаком валюты)
 * Округление всегда в пользу клиента (вниз)
 * @param {number|string} amount - Сумма для форматирования
 * @param {string} currency - Символ валюты (по умолчанию ₽)
 * @returns {string} Отформатированная строка суммы
 */
export function formatAmountDisplay(amount, currency = '₽') {
    const num = formatAmount(amount);

    if (roundAmounts.value) {
        // Округляем в пользу клиента (вниз)
        return `${Math.floor(num)}${currency}`;
    }

    // Если целое число, показываем без дробной части
    if (Number.isInteger(num)) {
        return `${num}${currency}`;
    }

    return `${num.toFixed(2)}${currency}`;
}

/**
 * Инициализация - загрузить настройки при первом использовании
 */
export async function initSettings() {
    await loadSettings();
}

/**
 * Реактивная ссылка на настройку округления
 */
export const roundAmountsSetting = roundAmounts;

export default {
    formatAmount,
    formatAmountDisplay,
    setRoundAmounts,
    getRoundAmounts,
    initSettings,
    roundAmountsSetting
};
