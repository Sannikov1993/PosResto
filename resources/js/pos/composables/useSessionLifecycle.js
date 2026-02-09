/**
 * Session Lifecycle — управление IdleService, LogoutBroadcast, polling
 *
 * Извлечено из App.vue для уменьшения сложности корневого компонента.
 */

import { ref, onUnmounted } from 'vue';
import { IdleService } from '../services/IdleService';
import { LogoutBroadcast } from '../services/LogoutBroadcast';
import { usePosStore } from '../stores/pos';
import { useRealtimeStore } from '../../shared/stores/realtime.js';
import { useNavigationStore } from '../../shared/stores/navigation.js';
import api from '../api';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:Session');

// Polling intervals (ms)
const DELIVERY_POLL_INTERVAL = 60000;
const BAR_POLL_INTERVAL = 30000;
const CASH_POLL_INTERVAL = 60000;
const IDLE_TIMEOUT = 30 * 60 * 1000;

export function useSessionLifecycle() {
    const posStore = usePosStore();
    const realtimeStore = useRealtimeStore();
    const navigationStore = useNavigationStore();

    // ===== Idle & Broadcast =====
    let idleService = null;
    let logoutBroadcast = null;

    function startIdleService(onIdle) {
        if (idleService) idleService.stop();
        idleService = new IdleService({
            idleTimeout: IDLE_TIMEOUT,
            onIdle,
        });
        idleService.start();
    }

    function stopIdleService() {
        if (idleService) {
            idleService.stop();
            idleService = null;
        }
    }

    function resetIdleTimer() {
        if (idleService) idleService.resetTimer();
    }

    function initLogoutBroadcast(onLogout) {
        if (logoutBroadcast) logoutBroadcast.destroy();
        logoutBroadcast = new LogoutBroadcast();
        logoutBroadcast.onLogout(onLogout);
    }

    function notifyLogout() {
        if (logoutBroadcast) logoutBroadcast.notifyLogout();
    }

    // ===== Polling =====
    let deliveryRefreshInterval = null;
    let barRefreshInterval = null;
    let cashRefreshInterval = null;

    function startPolling(hasBar, isLoggedInFn) {
        stopPolling();

        deliveryRefreshInterval = setInterval(async () => {
            if (isLoggedInFn()) await posStore.loadDeliveryOrders();
        }, DELIVERY_POLL_INTERVAL);

        if (hasBar) {
            barRefreshInterval = setInterval(async () => {
                if (isLoggedInFn()) await refreshBarCount();
            }, BAR_POLL_INTERVAL);
        }

        cashRefreshInterval = setInterval(async () => {
            if (isLoggedInFn()) {
                await Promise.all([
                    posStore.loadCurrentShift(),
                    posStore.loadPaidOrders()
                ]);
            }
        }, CASH_POLL_INTERVAL);
    }

    function stopPolling() {
        if (deliveryRefreshInterval) {
            clearInterval(deliveryRefreshInterval);
            deliveryRefreshInterval = null;
        }
        if (barRefreshInterval) {
            clearInterval(barRefreshInterval);
            barRefreshInterval = null;
        }
        if (cashRefreshInterval) {
            clearInterval(cashRefreshInterval);
            cashRefreshInterval = null;
        }
    }

    // ===== Bar =====
    const hasBar = ref(false);
    const barItemsCount = ref(0);

    async function checkBar() {
        try {
            const res = await api.bar.check();
            hasBar.value = res?.has_bar === true;
            if (hasBar.value) {
                await refreshBarCount();
            }
        } catch {
            hasBar.value = false;
        }
    }

    async function refreshBarCount() {
        if (!hasBar.value) return;
        try {
            const res = await api.bar.getOrders();
            if (res?.counts) {
                barItemsCount.value = (res.counts.new || 0) + (res.counts.in_progress || 0);
            }
        } catch {
            // ignore
        }
    }

    // ===== Cleanup =====
    function cleanupAll() {
        stopIdleService();
        stopPolling();
        realtimeStore.destroy();
        navigationStore.reset();
        if (logoutBroadcast) {
            logoutBroadcast.destroy();
            logoutBroadcast = null;
        }
    }

    return {
        // Idle & Broadcast
        startIdleService,
        stopIdleService,
        resetIdleTimer,
        initLogoutBroadcast,
        notifyLogout,

        // Polling
        startPolling,
        stopPolling,

        // Bar
        hasBar,
        barItemsCount,
        checkBar,
        refreshBarCount,

        // Cleanup
        cleanupAll,
    };
}
