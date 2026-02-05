/**
 * Format Utilities
 *
 * Functions for formatting display values in the kitchen display system.
 *
 * @module kitchen/utils/format
 */

import { getTodayString, getTomorrowString } from './time.js';

/**
 * Format cooking time for display
 * @param {number} minutes - Minutes cooking
 * @returns {string} Formatted string (e.g., "5 мин" or "1 ч 15 мин")
 */
export function formatCookingTime(minutes) {
    if (minutes < 60) {
        return `${minutes} мин`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours} ч ${mins} мин` : `${hours} ч`;
}

/**
 * Format time until scheduled time
 * @param {number|null} minutes - Minutes until (negative if overdue)
 * @returns {string} Formatted string
 */
export function formatTimeUntil(minutes) {
    if (minutes === null) return '';
    if (minutes >= 9999) return 'завтра';
    if (minutes <= -9999) return 'просрочен';
    if (minutes < 0) return `просрочен ${Math.abs(minutes)}м`;
    if (minutes === 0) return 'сейчас';
    if (minutes < 60) return `через ${minutes}м`;

    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `через ${h}ч ${m}м` : `через ${h}ч`;
}

/**
 * Format date for display (relative or absolute)
 * @param {string} dateStr - Date string in YYYY-MM-DD format
 * @returns {string} Formatted display string
 */
export function formatDisplayDate(dateStr) {
    const todayStr = getTodayString();
    const tomorrowStr = getTomorrowString();

    if (dateStr === todayStr) return 'Сегодня';
    if (dateStr === tomorrowStr) return 'Завтра';

    // Parse directly from string to avoid Date object timezone issues
    const [, month, day] = dateStr.split('-').map(Number);
    return `${day} ${MONTH_NAMES_SHORT[month - 1]}`;
}

/**
 * Format datetime for display
 * @param {string} dateTimeStr - ISO datetime string
 * @returns {string} Formatted string
 */
export function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr);
    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Format stop list resume time
 * @param {string} resumeAt - ISO datetime string
 * @returns {string} Formatted time
 */
export function formatStopListTime(resumeAt) {
    if (!resumeAt) return '';
    const date = new Date(resumeAt);
    const now = new Date();

    // If same day, show just time
    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    // Otherwise show date and time
    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Get order type icon
 * @param {Object|string} orderOrType - Order object or type string
 * @returns {string} Emoji icon
 */
export function getOrderTypeIcon(orderOrType) {
    const type = typeof orderOrType === 'string' ? orderOrType : orderOrType?.type;
    const icons = {
        dine_in: '🍽️',
        delivery: '🛵',
        pickup: '🏃',
        preorder: '📅',
    };
    return icons[type] || '📋';
}

/**
 * Get order type label in Russian
 * @param {string} type - Order type
 * @returns {string} Type label
 */
export function getOrderTypeLabel(type) {
    const labels = {
        dine_in: 'В зале',
        delivery: 'Доставка',
        pickup: 'Самовывоз',
        preorder: 'Бронь',
    };
    return labels[type] || type;
}

/**
 * Get category icon based on category name
 * @param {string|null|undefined} categoryName - Category name
 * @returns {string} Emoji icon
 */
export function getCategoryIcon(categoryName) {
    if (!categoryName) return '🍽️';

    const name = categoryName.toLowerCase();

    // Define category mappings
    const mappings = [
        { keywords: ['пицц'], icon: '🍕' },
        { keywords: ['салат'], icon: '🥗' },
        { keywords: ['суп'], icon: '🍲' },
        { keywords: ['мяс', 'стейк', 'гриль'], icon: '🥩' },
        { keywords: ['рыб', 'море'], icon: '🐟' },
        { keywords: ['паст', 'макарон'], icon: '🍝' },
        { keywords: ['бургер'], icon: '🍔' },
        { keywords: ['десерт', 'торт', 'пирог'], icon: '🍰' },
        { keywords: ['напит', 'кофе', 'чай'], icon: '☕' },
        { keywords: ['завтрак'], icon: '🍳' },
        { keywords: ['суши', 'ролл'], icon: '🍣' },
        { keywords: ['закуск'], icon: '🥟' },
        { keywords: ['гарнир'], icon: '🍚' },
        { keywords: ['соус'], icon: '🫙' },
    ];

    for (const mapping of mappings) {
        if (mapping.keywords.some(kw => name.includes(kw))) {
            return mapping.icon;
        }
    }

    return '🍽️';
}

/**
 * Format wait time from created date
 * @param {string} dateStr - ISO date string
 * @returns {string} Formatted wait time
 */
export function formatWaitTime(dateStr) {
    if (!dateStr) return '';

    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000);
    if (diff < 1) return 'только что';
    if (diff < 60) return `${diff} мин`;
    return `${Math.floor(diff / 60)} ч ${diff % 60} мин`;
}

/**
 * Format time only from datetime
 * @param {string} dateStr - ISO date string
 * @returns {string} Formatted time (HH:MM)
 */
export function formatTimeOnly(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Get items summary for compact display
 * @param {Array} items - Order items
 * @param {number} [maxItems=2] - Max items to show
 * @returns {string} Summary string
 */
export function getItemsSummary(items, maxItems = 2) {
    if (!items || items.length === 0) return '';

    const names = items.slice(0, maxItems).map(i => i.name);
    if (items.length > maxItems) {
        return names.join(', ') + ` +${items.length - maxItems}`;
    }
    return names.join(', ');
}

/**
 * Russian month names
 * @type {readonly string[]}
 */
export const MONTH_NAMES = Object.freeze([
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
]);

/**
 * Short Russian month names
 * @type {readonly string[]}
 */
export const MONTH_NAMES_SHORT = Object.freeze([
    'янв', 'фев', 'мар', 'апр', 'май', 'июн',
    'июл', 'авг', 'сен', 'окт', 'ноя', 'дек',
]);

/**
 * Russian weekday names (starting Monday)
 * @type {readonly string[]}
 */
export const WEEKDAY_NAMES = Object.freeze([
    'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс',
]);

/**
 * Format month and year for calendar header
 * @param {Date} date - Date object
 * @returns {string} Formatted string (e.g., "Январь 2024")
 */
export function formatMonthYear(date) {
    return `${MONTH_NAMES[date.getMonth()]} ${date.getFullYear()}`;
}
