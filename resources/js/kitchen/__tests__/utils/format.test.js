/**
 * Format Utils Unit Tests
 *
 * @group unit
 * @group kitchen
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
    formatCookingTime,
    formatTimeUntil,
    formatDisplayDate,
    getOrderTypeIcon,
    getItemsSummary,
    MONTH_NAMES,
    formatMonthYear,
} from '../../utils/format.js';
import { setTimezone } from '../../utils/time.js';

describe('Format Utils', () => {
    // ==================== formatCookingTime ====================

    describe('formatCookingTime()', () => {
        it('should format minutes under 60', () => {
            expect(formatCookingTime(5)).toBe('5 мин');
            expect(formatCookingTime(30)).toBe('30 мин');
            expect(formatCookingTime(59)).toBe('59 мин');
        });

        it('should format exact hours', () => {
            expect(formatCookingTime(60)).toBe('1 ч');
            expect(formatCookingTime(120)).toBe('2 ч');
        });

        it('should format hours and minutes', () => {
            expect(formatCookingTime(75)).toBe('1 ч 15 мин');
            expect(formatCookingTime(150)).toBe('2 ч 30 мин');
        });
    });

    // ==================== formatTimeUntil ====================

    describe('formatTimeUntil()', () => {
        it('should return empty string for null', () => {
            expect(formatTimeUntil(null)).toBe('');
        });

        it('should return "завтра" for far future', () => {
            expect(formatTimeUntil(9999)).toBe('завтра');
        });

        it('should return "просрочен" for far past', () => {
            expect(formatTimeUntil(-9999)).toBe('просрочен');
        });

        it('should format overdue minutes', () => {
            expect(formatTimeUntil(-30)).toBe('просрочен 30м');
        });

        it('should return "сейчас" for zero', () => {
            expect(formatTimeUntil(0)).toBe('сейчас');
        });

        it('should format future minutes', () => {
            expect(formatTimeUntil(15)).toBe('через 15м');
            expect(formatTimeUntil(45)).toBe('через 45м');
        });

        it('should format hours', () => {
            expect(formatTimeUntil(60)).toBe('через 1ч');
            expect(formatTimeUntil(90)).toBe('через 1ч 30м');
            expect(formatTimeUntil(120)).toBe('через 2ч');
        });
    });

    // ==================== formatDisplayDate ====================

    describe('formatDisplayDate()', () => {
        beforeEach(() => {
            setTimezone('UTC');
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:00:00Z'));
        });

        it('should return "Сегодня" for today', () => {
            expect(formatDisplayDate('2024-01-15')).toBe('Сегодня');
        });

        it('should return "Завтра" for tomorrow', () => {
            expect(formatDisplayDate('2024-01-16')).toBe('Завтра');
        });

        it('should format other dates', () => {
            expect(formatDisplayDate('2024-01-20')).toBe('20 янв');
            expect(formatDisplayDate('2024-02-05')).toBe('5 фев');
            expect(formatDisplayDate('2024-12-31')).toBe('31 дек');
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });

    // ==================== getOrderTypeIcon ====================

    describe('getOrderTypeIcon()', () => {
        it('should return delivery icon', () => {
            expect(getOrderTypeIcon({ type: 'delivery' })).toBe('🛵');
        });

        it('should return pickup icon', () => {
            expect(getOrderTypeIcon({ type: 'pickup' })).toBe('🏃');
        });

        it('should return preorder icon', () => {
            expect(getOrderTypeIcon({ type: 'preorder' })).toBe('📅');
        });

        it('should return dine-in icon for dine_in type', () => {
            expect(getOrderTypeIcon({ type: 'dine_in' })).toBe('🍽️');
        });

        it('should return default icon for unknown type', () => {
            expect(getOrderTypeIcon({ type: 'unknown' })).toBe('📋');
        });
    });

    // ==================== getItemsSummary ====================

    describe('getItemsSummary()', () => {
        it('should return empty string for empty array', () => {
            expect(getItemsSummary([])).toBe('');
            expect(getItemsSummary(null)).toBe('');
        });

        it('should join single item name', () => {
            expect(getItemsSummary([{ name: 'Pizza' }])).toBe('Pizza');
        });

        it('should join two item names', () => {
            expect(getItemsSummary([
                { name: 'Pizza' },
                { name: 'Pasta' },
            ])).toBe('Pizza, Pasta');
        });

        it('should show count for more than 2 items', () => {
            expect(getItemsSummary([
                { name: 'Pizza' },
                { name: 'Pasta' },
                { name: 'Salad' },
            ])).toBe('Pizza, Pasta +1');

            expect(getItemsSummary([
                { name: 'Pizza' },
                { name: 'Pasta' },
                { name: 'Salad' },
                { name: 'Soup' },
            ])).toBe('Pizza, Pasta +2');
        });

        it('should respect custom maxItems', () => {
            expect(getItemsSummary([
                { name: 'A' },
                { name: 'B' },
                { name: 'C' },
            ], 1)).toBe('A +2');
        });
    });

    // ==================== MONTH_NAMES / formatMonthYear ====================

    describe('MONTH_NAMES and formatMonthYear()', () => {
        it('should have 12 month names', () => {
            expect(MONTH_NAMES).toHaveLength(12);
            expect(MONTH_NAMES[0]).toBe('Январь');
            expect(MONTH_NAMES[11]).toBe('Декабрь');
        });

        it('should format month and year', () => {
            const date = new Date(2024, 5, 15); // June 2024
            expect(formatMonthYear(date)).toBe('Июнь 2024');
        });
    });
});
