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

function formatTime(): string {
    const now = new Date();
    return now.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatDate(): string {
    const now = new Date();
    return now.toLocaleDateString('ru-RU', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

interface KitchenTimeOptions {
    autoStart?: boolean;
    interval?: number;
}

export function useKitchenTime(options: KitchenTimeOptions = {}) {
    const {
        autoStart = true,
        interval = POLLING_CONFIG.TIME_UPDATE_INTERVAL,
    } = options;

    const uiStore = useUiStore();

    const currentTime = ref(formatTime());
    const currentDate = ref(formatDate());
    const intervalId = ref<ReturnType<typeof setInterval> | null>(null);
    const isRunning = ref(false);

    function updateTime(): void {
        currentTime.value = formatTime();
        currentDate.value = formatDate();
        uiStore.updateTimeDisplay(currentTime.value, currentDate.value);
    }

    function start(): void {
        if (isRunning.value) return;

        isRunning.value = true;
        updateTime();
        intervalId.value = setInterval(updateTime, interval);
    }

    function stop(): void {
        if (intervalId.value) {
            clearInterval(intervalId.value);
            intervalId.value = null;
        }
        isRunning.value = false;
    }

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
