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
import type { ProcessedOrder } from '../types/index.js';

interface OverdueCheckOptions {
    autoStart?: boolean;
    checkInterval?: number;
}

export function useOverdueCheck(options: OverdueCheckOptions = {}) {
    const {
        autoStart = true,
        checkInterval = POLLING_CONFIG.OVERDUE_CHECK_INTERVAL,
    } = options;

    const ordersStore = useOrdersStore();
    const uiStore = useUiStore();
    const settingsStore = useSettingsStore();

    const checkIntervalId = ref<ReturnType<typeof setInterval> | null>(null);
    const isChecking = ref(false);
    const lastCriticalAlertTime = ref(0);

    const { overdueOrders } = storeToRefs(ordersStore);
    const { soundEnabled } = storeToRefs(settingsStore);

    function checkOverdueOrders(): void {
        const now = Date.now();

        const alertOrders = (overdueOrders.value as ProcessedOrder[]).filter((o: any) => o.isAlert);
        const criticalOrders = (overdueOrders.value as ProcessedOrder[]).filter((o: any) => o.isCritical && !o.isAlert);

        if (alertOrders.length > 0 && soundEnabled.value) {
            uiStore.showOverdue(alertOrders[0] as any);

            if (soundEnabled.value) {
                audioService.playOverdueWarning();
            }
        }

        if (criticalOrders.length > 0 && soundEnabled.value) {
            if (now - lastCriticalAlertTime.value > ALERT_CONFIG.CRITICAL_WARNING_INTERVAL) {
                lastCriticalAlertTime.value = now;
                audioService.playOverdueWarning();
            }
        }
    }

    function startChecking(): void {
        if (isChecking.value) return;

        isChecking.value = true;
        checkIntervalId.value = setInterval(checkOverdueOrders, checkInterval);
        checkOverdueOrders();
    }

    function stopChecking(): void {
        if (checkIntervalId.value) {
            clearInterval(checkIntervalId.value);
            checkIntervalId.value = null;
        }
        isChecking.value = false;
    }

    function triggerCheck(): void {
        checkOverdueOrders();
    }

    function dismissAlert(): void {
        uiStore.dismissOverdueAlert();
    }

    watch(
        () => ordersStore.cookingOrders,
        () => {
            if (isChecking.value) {
                checkOverdueOrders();
            }
        },
        { deep: true }
    );

    onMounted(() => {
        if (autoStart) {
            startChecking();
        }
    });

    onUnmounted(() => {
        stopChecking();
    });

    return {
        overdueOrders,
        isChecking,
        startChecking,
        stopChecking,
        triggerCheck,
        dismissAlert,
    };
}
