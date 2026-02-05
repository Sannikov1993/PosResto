/**
 * Time Utilities
 *
 * Functions for parsing, calculating, and formatting time values
 * in the kitchen display system.
 *
 * Uses restaurant's timezone for all date calculations to ensure
 * consistency across different client devices.
 *
 * @module kitchen/utils/time
 */

import { TIME_SLOT_CONFIG, URGENCY_LEVEL, calculateUrgency } from '../constants/thresholds.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenTime');

/**
 * Current timezone for date calculations.
 * Set from restaurant settings via setTimezone().
 * @type {string}
 */
let currentTimezone = 'UTC';

/**
 * Set the timezone for all time calculations.
 * Should be called when device is initialized with restaurant's timezone.
 * @param {string} timezone - IANA timezone (e.g., 'Asia/Yekaterinburg')
 */
export function setTimezone(timezone) {
    if (timezone && typeof timezone === 'string') {
        currentTimezone = timezone;
        log.debug('Timezone set to:', timezone);
    }
}

/**
 * Get current timezone
 * @returns {string} Current timezone
 */
export function getTimezone() {
    return currentTimezone;
}

/**
 * Get current date/time in restaurant's timezone
 * @returns {{ year: number, month: number, day: number, hours: number, minutes: number }}
 */
export function getNowInTimezone() {
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
    const get = (type) => parseInt(parts.find(p => p.type === type)?.value || '0', 10);

    return {
        year: get('year'),
        month: get('month'),
        day: get('day'),
        hours: get('hour'),
        minutes: get('minute'),
    };
}

/**
 * Parsed scheduled time object
 * @typedef {Object} ParsedTime
 * @property {string} date - Date string (YYYY-MM-DD)
 * @property {number} hours - Hour (0-23)
 * @property {number} minutes - Minutes (0-59)
 * @property {string} timeStr - Time string (HH:MM)
 */

/**
 * Parse scheduled_at timestamp without timezone conversion
 * @param {string|null} scheduledAt - ISO timestamp or datetime string
 * @returns {ParsedTime|null} Parsed time components or null
 */
export function parseScheduledTime(scheduledAt) {
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

/**
 * Get local date string (YYYY-MM-DD) for a Date object
 * @param {Date} date - Date object
 * @returns {string} Date string in YYYY-MM-DD format
 */
export function getLocalDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Get today's date string in restaurant's timezone
 * @returns {string} Today in YYYY-MM-DD format
 */
export function getTodayString() {
    const { year, month, day } = getNowInTimezone();
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/**
 * Parse date string as local date (not UTC)
 * @param {string} dateStr - Date string in YYYY-MM-DD format
 * @returns {Date} Local Date object
 */
export function parseLocalDate(dateStr) {
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day);
}

/**
 * Calculate minutes until a scheduled time
 * @param {string|null} scheduledAt - Scheduled timestamp
 * @returns {number|null} Minutes until scheduled time (negative if past)
 */
export function getMinutesUntil(scheduledAt) {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const nowTz = getNowInTimezone();
    const todayStr = getTodayString();

    // Different date handling
    if (parsed.date !== todayStr) {
        return parsed.date > todayStr ? 9999 : -9999;
    }

    const currentMins = nowTz.hours * 60 + nowTz.minutes;
    const targetMins = parsed.hours * 60 + parsed.minutes;
    return targetMins - currentMins;
}

/**
 * Calculate cooking time in minutes for an order
 * @param {Object} order - Order with cooking_started_at or updated_at
 * @returns {number} Minutes since cooking started
 */
export function getCookingMinutes(order) {
    const startTime = order.cooking_started_at || order.updated_at;
    if (!startTime) return 0;

    const now = Date.now();
    const start = new Date(startTime).getTime();
    return Math.floor((now - start) / 60000);
}

/**
 * Get time slot key for grouping preorders (30-minute slots)
 * @param {string|null} scheduledAt - Scheduled timestamp
 * @returns {string|null} Slot key (YYYY-MM-DD-HH:mm) or null
 */
export function getTimeSlotKey(scheduledAt) {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const slotMinutes = parsed.minutes < 30 ? '00' : '30';
    return `${parsed.date}-${String(parsed.hours).padStart(2, '0')}:${slotMinutes}`;
}

/**
 * Get display label for a time slot
 * @param {string} slotKey - Slot key
 * @returns {string} Display label (e.g., "14:00 - 14:30")
 */
export function getTimeSlotLabel(slotKey) {
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

/**
 * Calculate urgency level for a time slot
 * @param {string} slotKey - Time slot key
 * @returns {string} Urgency level
 */
export function getSlotUrgency(slotKey) {
    if (!slotKey) return URGENCY_LEVEL.NORMAL;

    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const datePart = parts.slice(0, 3).join('-');
    const [hours, mins] = timePart.split(':').map(Number);

    const nowTz = getNowInTimezone();
    const todayStr = getTodayString();

    // Different date handling
    if (datePart !== todayStr) {
        return datePart > todayStr ? URGENCY_LEVEL.NORMAL : URGENCY_LEVEL.OVERDUE;
    }

    const slotStartMins = hours * 60 + mins;
    const currentMins = nowTz.hours * 60 + nowTz.minutes;
    const diffMins = slotStartMins - currentMins;

    return calculateUrgency(diffMins);
}

/**
 * Check if a date is today
 * @param {string} dateStr - Date string in YYYY-MM-DD format
 * @returns {boolean}
 */
export function isToday(dateStr) {
    return dateStr === getTodayString();
}

/**
 * Check if a date is in the past
 * @param {string} dateStr - Date string in YYYY-MM-DD format
 * @returns {boolean}
 */
export function isPastDate(dateStr) {
    return dateStr < getTodayString();
}

/**
 * Get tomorrow's date string
 * @returns {string} Tomorrow in YYYY-MM-DD format
 */
export function getTomorrowString() {
    const { year, month, day } = getNowInTimezone();
    // Create date in local context and add one day
    const tomorrow = new Date(year, month - 1, day + 1);
    return `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
}
