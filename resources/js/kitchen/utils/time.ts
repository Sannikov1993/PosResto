/**
 * Time Utilities
 *
 * Functions for parsing, calculating, and formatting time values
 * in the kitchen display system.
 *
 * @module kitchen/utils/time
 */

import { TIME_SLOT_CONFIG, URGENCY_LEVEL, calculateUrgency } from '../constants/thresholds.js';
import { createLogger } from '../../shared/services/logger.js';
import type { ParsedTime, NowInTimezone, Order } from '../types/index.js';

const log = createLogger('KitchenTime');

let currentTimezone = 'UTC';

export function setTimezone(timezone: string): void {
    if (timezone && typeof timezone === 'string') {
        currentTimezone = timezone;
        log.debug('Timezone set to:', timezone);
    }
}

export function getTimezone(): string {
    return currentTimezone;
}

export function getNowInTimezone(): NowInTimezone {
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: currentTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    const parts = formatter.formatToParts(now);
    const get = (type: string): number => parseInt(parts.find((p: any) => p.type === type)?.value || '0', 10);

    return {
        year: get('year'),
        month: get('month'),
        day: get('day'),
        hours: get('hour'),
        minutes: get('minute'),
    };
}

export function parseScheduledTime(scheduledAt: string | null | undefined): ParsedTime | null {
    if (!scheduledAt) return null;

    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;

    return {
        date: match[1],
        hours: parseInt(match[2], 10),
        minutes: parseInt(match[3], 10),
        timeStr: `${match[2]}:${match[3]}`,
    };
}

export function getLocalDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function getTodayString(): string {
    const { year, month, day } = getNowInTimezone();
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function parseLocalDate(dateStr: string): Date {
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day);
}

export function getMinutesUntil(scheduledAt: string | null | undefined): number | null {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const nowTz = getNowInTimezone();
    const todayStr = getTodayString();

    if (parsed.date !== todayStr) {
        return parsed.date > todayStr ? 9999 : -9999;
    }

    const currentMins = nowTz.hours * 60 + nowTz.minutes;
    const targetMins = parsed.hours * 60 + parsed.minutes;
    return targetMins - currentMins;
}

export function getCookingMinutes(order: Order): number {
    const startTime = order.cooking_started_at || order.updated_at;
    if (!startTime) return 0;

    const now = Date.now();
    const start = new Date(startTime).getTime();
    return Math.floor((now - start) / 60000);
}

export function getTimeSlotKey(scheduledAt: string | null | undefined): string | null {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const slotMinutes = parsed.minutes < 30 ? '00' : '30';
    return `${parsed.date}-${String(parsed.hours).padStart(2, '0')}:${slotMinutes}`;
}

export function getTimeSlotLabel(slotKey: string): string {
    if (!slotKey) return '';

    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const [hours, mins] = timePart.split(':').map(Number);

    const endMins = mins + TIME_SLOT_CONFIG.DURATION_MINUTES;
    const endHours = endMins >= 60 ? hours + 1 : hours;
    const endMinsNormalized = endMins >= 60 ? '00' : '30';

    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')} - ` +
           `${String(endHours).padStart(2, '0')}:${endMinsNormalized}`;
}

export function getSlotUrgency(slotKey: string): string {
    if (!slotKey) return URGENCY_LEVEL.NORMAL;

    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const datePart = parts.slice(0, 3).join('-');
    const [hours, mins] = timePart.split(':').map(Number);

    const nowTz = getNowInTimezone();
    const todayStr = getTodayString();

    if (datePart !== todayStr) {
        return datePart > todayStr ? URGENCY_LEVEL.NORMAL : URGENCY_LEVEL.OVERDUE;
    }

    const slotStartMins = hours * 60 + mins;
    const currentMins = nowTz.hours * 60 + nowTz.minutes;
    const diffMins = slotStartMins - currentMins;

    return calculateUrgency(diffMins);
}

export function isToday(dateStr: string): boolean {
    return dateStr === getTodayString();
}

export function isPastDate(dateStr: string): boolean {
    return dateStr < getTodayString();
}

export function getTomorrowString(): string {
    const { year, month, day } = getNowInTimezone();
    const tomorrow = new Date(year, month - 1, day + 1);
    return `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
}
