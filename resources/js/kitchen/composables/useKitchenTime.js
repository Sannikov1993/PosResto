/**
 * Kitchen Time Composable
 *
 * Provides real-time clock functionality for the kitchen display.
 *
 * @module kitchen/composables/useKitchenTime
 */

import { onMounted, onUnmounted, ref } from 'vue';
import { useUiStore } from '../stores/ui.js';
import { POLLING_CONFIG } from '../constants/thresholds.js';

/**
 * Format current time with seconds
 * @returns {string} Time string (HH:MM:SS)
 */
function formatTime() {
    const now = new Date();
    return now.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

/**
 * Format current date
 * @returns {string} Date string
 */
function formatDate() {
    const now = new Date();
    return now.toLocaleDateString('ru-RU', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

/**
 * Kitchen time composable
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.autoStart=true] - Auto-start on mount
 * @param {number} [options.interval] - Update interval in ms
 * @returns {Object} Time composable
 */
export function useKitchenTime(options = {}) {
    const {
        autoStart = true,
        interval = POLLING_CONFIG.TIME_UPDATE_INTERVAL,
    } = options;

    const uiStore = useUiStore();

    // Refs
    const currentTime = ref(formatTime());
    const currentDate = ref(formatDate());
    const intervalId = ref(null);
    const isRunning = ref(false);

    /**
     * Update time and date
     */
    function updateTime() {
        currentTime.value = formatTime();
        currentDate.value = formatDate();

        // Also update in UI store
        uiStore.updateTimeDisplay(currentTime.value, currentDate.value);
    }

    /**
     * Start time updates
     */
    function start() {
        if (isRunning.value) return;

        isRunning.value = true;
        updateTime(); // Initial update
        intervalId.value = setInterval(updateTime, interval);
    }

    /**
     * Stop time updates
     */
    function stop() {
        if (intervalId.value) {
            clearInterval(intervalId.value);
            intervalId.value = null;
        }
        isRunning.value = false;
    }

    // Lifecycle
    onMounted(() => {
        if (autoStart) {
            start();
        }
    });

    onUnmounted(() => {
        stop();
    });

    return {
        currentTime,
        currentDate,
        isRunning,
        start,
        stop,
        updateTime,
    };
}
