/**
 * Format Utilities
 *
 * Functions for formatting display values in the kitchen display system.
 *
 * @module kitchen/utils/format
 */

import { getTodayString, getTomorrowString } from './time.js';
import type { Order, OrderItem } from '../types/index.js';

export function formatCookingTime(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} мин`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours} ч ${mins} мин` : `${hours} ч`;
}

export function formatTimeUntil(minutes: number | null): string {
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

export function formatDisplayDate(dateStr: string): string {
    const todayStr = getTodayString();
    const tomorrowStr = getTomorrowString();

    if (dateStr === todayStr) return 'Сегодня';
    if (dateStr === tomorrowStr) return 'Завтра';

    const [, month, day] = dateStr.split('-').map(Number);
    return `${day} ${MONTH_NAMES_SHORT[month - 1]}`;
}

export function formatDateTime(dateTimeStr: string | null | undefined): string {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr);
    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatStopListTime(resumeAt: string | null | undefined): string {
    if (!resumeAt) return '';
    const date = new Date(resumeAt);
    const now = new Date();

    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function getOrderTypeIcon(orderOrType: Order | string): string {
    const type = typeof orderOrType === 'string' ? orderOrType : orderOrType?.type;
    const icons: Record<string, string> = {
        dine_in: '🍽️',
        delivery: '🛵',
        pickup: '🏃',
        preorder: '📅',
    };
    return icons[type] || '📋';
}

export function getOrderTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        dine_in: 'В зале',
        delivery: 'Доставка',
        pickup: 'Самовывоз',
        preorder: 'Бронь',
    };
    return labels[type] || type;
}

export function getCategoryIcon(categoryName: string | null | undefined): string {
    if (!categoryName) return '🍽️';

    const name = categoryName.toLowerCase();

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
        if (mapping.keywords.some((kw: any) => name.includes(kw))) {
            return mapping.icon;
        }
    }

    return '🍽️';
}

export function formatWaitTime(dateStr: string | null | undefined): string {
    if (!dateStr) return '';

    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000);
    if (diff < 1) return 'только что';
    if (diff < 60) return `${diff} мин`;
    return `${Math.floor(diff / 60)} ч ${diff % 60} мин`;
}

export function formatTimeOnly(dateStr: string | null | undefined): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function getItemsSummary(items: OrderItem[] | null | undefined, maxItems = 2): string {
    if (!items || items.length === 0) return '';

    const names = items.slice(0, maxItems).map((i: any) => i.name);
    if (items.length > maxItems) {
        return names.join(', ') + ` +${items.length - maxItems}`;
    }
    return names.join(', ');
}

export const MONTH_NAMES: readonly string[] = Object.freeze([
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
]);

export const MONTH_NAMES_SHORT: readonly string[] = Object.freeze([
    'янв', 'фев', 'мар', 'апр', 'май', 'июн',
    'июл', 'авг', 'сен', 'окт', 'ноя', 'дек',
]);

export const WEEKDAY_NAMES: readonly string[] = Object.freeze([
    'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс',
]);

export function formatMonthYear(date: Date): string {
    return `${MONTH_NAMES[date.getMonth()]} ${date.getFullYear()}`;
}
