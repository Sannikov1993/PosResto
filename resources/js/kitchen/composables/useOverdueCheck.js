/**
 * Overdue Check Composable
 *
 * Provides automatic checking for overdue orders
 * with alert notifications and sound warnings.
 *
 * @module kitchen/composables/useOverdueCheck
 */

import { onMounted, onUnmounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useOrdersStore } from '../stores/orders.js';
import { useUiStore } from '../stores/ui.js';
import { useSettingsStore } from '../stores/settings.js';
import { audioService } from '../services/audio/AudioService.js';
import { POLLING_CONFIG, ALERT_CONFIG } from '../constants/thresholds.js';

/**
 * Overdue check composable
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.autoStart=true] - Auto-start checking on mount
 * @param {number} [options.checkInterval] - Check interval in ms
 * @returns {Object} Overdue check composable
 */
export function useOverdueCheck(options = {}) {
    const {
        autoStart = true,
        checkInterval = POLLING_CONFIG.OVERDUE_CHECK_INTERVAL,
    } = options;

    const ordersStore = useOrdersStore();
    const uiStore = useUiStore();
    const settingsStore = useSettingsStore();

    // Refs
    const checkIntervalId = ref(null);
    const isChecking = ref(false);
    const lastCriticalAlertTime = ref(0);

    // Store refs
    const { overdueOrders } = storeToRefs(ordersStore);
    const { soundEnabled } = storeToRefs(settingsStore);

    /**
     * Check for overdue orders and trigger alerts
     */
    function checkOverdueOrders() {
        const now = Date.now();

        // Get orders that need alerts
        const alertOrders = overdueOrders.value.filter(o => o.isAlert);
        const criticalOrders = overdueOrders.value.filter(o => o.isCritical && !o.isAlert);

        // Full-screen alert for severely overdue
        if (alertOrders.length > 0 && soundEnabled.value) {
            // Show alert (throttled inside ui store)
            uiStore.showOverdue(alertOrders[0]);

            // Play warning sound
            if (soundEnabled.value) {
                audioService.playOverdueWarning();
            }
        }

        // Sound warning for critical orders
        if (criticalOrders.length > 0 && soundEnabled.value) {
            // Throttle critical warnings to once per minute
            if (now - lastCriticalAlertTime.value > ALERT_CONFIG.CRITICAL_WARNING_INTERVAL) {
                lastCriticalAlertTime.value = now;
                audioService.playOverdueWarning();
            }
        }
    }

    /**
     * Start checking for overdue orders
     */
    function startChecking() {
        if (isChecking.value) return;

        isChecking.value = true;
        checkIntervalId.value = setInterval(checkOverdueOrders, checkInterval);

        // Initial check
        checkOverdueOrders();
    }

    /**
     * Stop checking
     */
    function stopChecking() {
        if (checkIntervalId.value) {
            clearInterval(checkIntervalId.value);
            checkIntervalId.value = null;
        }
        isChecking.value = false;
    }

    /**
     * Manually trigger check
     */
    function triggerCheck() {
        checkOverdueOrders();
    }

    /**
     * Dismiss current overdue alert
     */
    function dismissAlert() {
        uiStore.dismissOverdueAlert();
    }

    // Watch for cooking orders changes
    watch(
        () => ordersStore.cookingOrders,
        () => {
            // Recalculate when cooking orders change
            if (isChecking.value) {
                checkOverdueOrders();
            }
        },
        { deep: true }
    );

    // Lifecycle
    onMounted(() => {
        if (autoStart) {
            startChecking();
        }
    });

    onUnmounted(() => {
        stopChecking();
    });

    return {
        // State
        overdueOrders,
        isChecking,

        // Actions
        startChecking,
        stopChecking,
        triggerCheck,
        dismissAlert,
    };
}
