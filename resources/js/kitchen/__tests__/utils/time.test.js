/**
 * Time Utils Unit Tests
 *
 * @group unit
 * @group kitchen
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
    parseScheduledTime,
    getLocalDateString,
    getTodayString,
    parseLocalDate,
    getMinutesUntil,
    getCookingMinutes,
    getTimeSlotKey,
    getTimeSlotLabel,
    getSlotUrgency,
    isToday,
    isPastDate,
    getTomorrowString,
    setTimezone,
} from '../../utils/time.js';

describe('Time Utils', () => {
    // Reset timezone to UTC before all tests to ensure consistent behavior
    beforeEach(() => {
        setTimezone('UTC');
    });

    // ==================== parseScheduledTime ====================

    describe('parseScheduledTime()', () => {
        it('should parse ISO datetime string', () => {
            const result = parseScheduledTime('2024-01-15T14:30:00');

            expect(result).toEqual({
                date: '2024-01-15',
                hours: 14,
                minutes: 30,
                timeStr: '14:30',
            });
        });

        it('should parse datetime with space separator', () => {
            const result = parseScheduledTime('2024-01-15 09:00:00');

            expect(result).toEqual({
                date: '2024-01-15',
                hours: 9,
                minutes: 0,
                timeStr: '09:00',
            });
        });

        it('should return null for null input', () => {
            expect(parseScheduledTime(null)).toBeNull();
        });

        it('should return null for invalid format', () => {
            expect(parseScheduledTime('invalid')).toBeNull();
        });
    });

    // ==================== getLocalDateString ====================

    describe('getLocalDateString()', () => {
        it('should format date as YYYY-MM-DD', () => {
            const date = new Date(2024, 0, 15); // Jan 15, 2024
            expect(getLocalDateString(date)).toBe('2024-01-15');
        });

        it('should pad single digit month and day', () => {
            const date = new Date(2024, 5, 5); // Jun 5, 2024
            expect(getLocalDateString(date)).toBe('2024-06-05');
        });
    });

    // ==================== parseLocalDate ====================

    describe('parseLocalDate()', () => {
        it('should parse YYYY-MM-DD as local date', () => {
            const result = parseLocalDate('2024-01-15');

            expect(result.getFullYear()).toBe(2024);
            expect(result.getMonth()).toBe(0); // January
            expect(result.getDate()).toBe(15);
        });
    });

    // ==================== getMinutesUntil ====================

    describe('getMinutesUntil()', () => {
        beforeEach(() => {
            // Mock current time to 2024-01-15 12:00:00 UTC
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:00:00Z'));
        });

        it('should return positive minutes for future time', () => {
            const result = getMinutesUntil('2024-01-15T14:30:00');
            expect(result).toBe(150); // 2h30m = 150 minutes
        });

        it('should return negative minutes for past time', () => {
            const result = getMinutesUntil('2024-01-15T10:00:00');
            expect(result).toBe(-120); // 2 hours ago
        });

        it('should return 9999 for future date', () => {
            const result = getMinutesUntil('2024-01-16T14:00:00');
            expect(result).toBe(9999);
        });

        it('should return -9999 for past date', () => {
            const result = getMinutesUntil('2024-01-14T14:00:00');
            expect(result).toBe(-9999);
        });

        it('should return null for null input', () => {
            expect(getMinutesUntil(null)).toBeNull();
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });

    // ==================== getCookingMinutes ====================

    describe('getCookingMinutes()', () => {
        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:30:00Z'));
        });

        it('should calculate minutes from cooking_started_at', () => {
            const order = {
                cooking_started_at: '2024-01-15T12:00:00Z',
            };
            expect(getCookingMinutes(order)).toBe(30);
        });

        it('should use updated_at as fallback', () => {
            const order = {
                updated_at: '2024-01-15T12:15:00Z',
            };
            expect(getCookingMinutes(order)).toBe(15);
        });

        it('should return 0 when no timestamp', () => {
            expect(getCookingMinutes({})).toBe(0);
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });

    // ==================== getTimeSlotKey ====================

    describe('getTimeSlotKey()', () => {
        it('should round down to 00 for first half hour', () => {
            expect(getTimeSlotKey('2024-01-15T14:15:00')).toBe('2024-01-15-14:00');
        });

        it('should round down to 30 for second half hour', () => {
            expect(getTimeSlotKey('2024-01-15T14:45:00')).toBe('2024-01-15-14:30');
        });

        it('should handle exactly 30 minutes', () => {
            expect(getTimeSlotKey('2024-01-15T14:30:00')).toBe('2024-01-15-14:30');
        });

        it('should return null for null input', () => {
            expect(getTimeSlotKey(null)).toBeNull();
        });
    });

    // ==================== getTimeSlotLabel ====================

    describe('getTimeSlotLabel()', () => {
        it('should format 30-minute time range', () => {
            expect(getTimeSlotLabel('2024-01-15-14:00')).toBe('14:00 - 14:30');
            expect(getTimeSlotLabel('2024-01-15-14:30')).toBe('14:30 - 15:00');
        });

        it('should handle hour rollover', () => {
            expect(getTimeSlotLabel('2024-01-15-23:30')).toBe('23:30 - 24:00');
        });

        it('should return empty string for null/empty', () => {
            expect(getTimeSlotLabel(null)).toBe('');
            expect(getTimeSlotLabel('')).toBe('');
        });
    });

    // ==================== getSlotUrgency ====================

    describe('getSlotUrgency()', () => {
        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:00:00Z'));
        });

        it('should return "overdue" for past time', () => {
            expect(getSlotUrgency('2024-01-15-11:30')).toBe('overdue');
        });

        it('should return "urgent" within 30 minutes', () => {
            expect(getSlotUrgency('2024-01-15-12:00')).toBe('urgent');
            expect(getSlotUrgency('2024-01-15-12:30')).toBe('urgent');
        });

        it('should return "warning" within 60 minutes', () => {
            expect(getSlotUrgency('2024-01-15-13:00')).toBe('warning');
        });

        it('should return "normal" for far future', () => {
            expect(getSlotUrgency('2024-01-15-15:00')).toBe('normal');
        });

        it('should return "overdue" for past date', () => {
            expect(getSlotUrgency('2024-01-14-14:00')).toBe('overdue');
        });

        it('should return "normal" for future date', () => {
            expect(getSlotUrgency('2024-01-16-14:00')).toBe('normal');
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });

    // ==================== isToday / isPastDate ====================

    describe('isToday() and isPastDate()', () => {
        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:00:00Z'));
        });

        it('isToday should return true for today', () => {
            expect(isToday('2024-01-15')).toBe(true);
        });

        it('isToday should return false for other days', () => {
            expect(isToday('2024-01-14')).toBe(false);
            expect(isToday('2024-01-16')).toBe(false);
        });

        it('isPastDate should return true for past dates', () => {
            expect(isPastDate('2024-01-14')).toBe(true);
        });

        it('isPastDate should return false for today and future', () => {
            expect(isPastDate('2024-01-15')).toBe(false);
            expect(isPastDate('2024-01-16')).toBe(false);
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });

    // ==================== getTodayString / getTomorrowString ====================

    describe('getTodayString() and getTomorrowString()', () => {
        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2024-01-15T12:00:00Z'));
        });

        it('getTodayString should return current date', () => {
            expect(getTodayString()).toBe('2024-01-15');
        });

        it('getTomorrowString should return next day', () => {
            expect(getTomorrowString()).toBe('2024-01-16');
        });

        afterEach(() => {
            vi.useRealTimers();
        });
    });
});
