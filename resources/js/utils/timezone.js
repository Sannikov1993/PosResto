/**
 * Timezone utility for consistent date/time formatting across the application
 * Uses restaurant's configured timezone from settings
 */

// Current timezone (loaded from settings)
let currentTimezone = 'Europe/Moscow';

/**
 * Set the application timezone
 * @param {string} tz - IANA timezone string (e.g., 'Europe/Moscow', 'Asia/Yekaterinburg')
 */
export function setTimezone(tz) {
    if (tz && typeof tz === 'string') {
        currentTimezone = tz;
    }
}

/**
 * Get the current timezone
 * @returns {string}
 */
export function getTimezone() {
    return currentTimezone;
}

/**
 * Format time with configured timezone
 * @param {Date|string} date - Date object or ISO string
 * @param {object} options - Intl.DateTimeFormat options (default: hour and minute)
 * @returns {string}
 */
export function formatTime(date, options = { hour: '2-digit', minute: '2-digit' }) {
    if (!date) return '';
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleTimeString('ru-RU', {
        ...options,
        timeZone: currentTimezone
    });
}

/**
 * Format time with seconds
 * @param {Date|string} date
 * @returns {string}
 */
export function formatTimeWithSeconds(date) {
    return formatTime(date, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

/**
 * Format date with configured timezone
 * @param {Date|string} date - Date object or ISO string
 * @param {object} options - Intl.DateTimeFormat options
 * @returns {string}
 */
export function formatDate(date, options = { day: 'numeric', month: 'long' }) {
    if (!date) return '';
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleDateString('ru-RU', {
        ...options,
        timeZone: currentTimezone
    });
}

/**
 * Format full date with weekday
 * @param {Date|string} date
 * @returns {string}
 */
export function formatDateFull(date) {
    return formatDate(date, { weekday: 'long', day: 'numeric', month: 'long' });
}

/**
 * Format short date (e.g., "пн, 21 янв")
 * @param {Date|string} date
 * @returns {string}
 */
export function formatDateShort(date) {
    return formatDate(date, { weekday: 'short', day: 'numeric', month: 'short' });
}

/**
 * Format date and time together
 * @param {Date|string} date
 * @returns {string}
 */
export function formatDateTime(date) {
    if (!date) return '';
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: currentTimezone
    });
}

/**
 * Get current time in configured timezone
 * @returns {Date}
 */
export function getNow() {
    return new Date();
}

/**
 * Get current time string formatted
 * @returns {string}
 */
export function getCurrentTime() {
    return formatTime(new Date());
}

/**
 * Get current time string with seconds
 * @returns {string}
 */
export function getCurrentTimeWithSeconds() {
    return formatTimeWithSeconds(new Date());
}

/**
 * Get current date string formatted
 * @returns {string}
 */
export function getCurrentDate() {
    return formatDateFull(new Date());
}

/**
 * Get local date string in YYYY-MM-DD format (for API calls)
 * Respects configured timezone
 * @param {Date} date
 * @returns {string}
 */
export function getLocalDateString(date = new Date()) {
    // Format date parts in the configured timezone
    const formatter = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: currentTimezone
    });
    return formatter.format(date);
}

/**
 * Load timezone from API settings
 * @returns {Promise<string>}
 */
export async function loadTimezoneFromSettings() {
    try {
        const response = await fetch('/api/settings/general');
        const data = await response.json();
        if (data.success && data.data?.timezone) {
            setTimezone(data.data.timezone);
            return data.data.timezone;
        }
    } catch (e) {
        console.warn('[Timezone] Failed to load timezone from settings:', e);
    }
    return currentTimezone;
}

export default {
    setTimezone,
    getTimezone,
    formatTime,
    formatTimeWithSeconds,
    formatDate,
    formatDateFull,
    formatDateShort,
    formatDateTime,
    getNow,
    getCurrentTime,
    getCurrentTimeWithSeconds,
    getCurrentDate,
    getLocalDateString,
    loadTimezoneFromSettings
};
