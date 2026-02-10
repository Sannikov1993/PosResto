/**
 * Session Lifecycle — управление IdleService, LogoutBroadcast, polling
 */

import { ref } from 'vue';
import { IdleService } from '../services/IdleService.js';
import { LogoutBroadcast } from '../services/LogoutBroadcast.js';
import { usePosStore } from '../stores/pos.js';
import { useRealtimeStore } from '../../shared/stores/realtime.js';
import { useNavigationStore } from '../../shared/stores/navigation.js';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:Session');

const DELIVERY_POLL_INTERVAL = 60000;
const BAR_POLL_INTERVAL = 30000;
const CASH_POLL_INTERVAL = 60000;
const IDLE_TIMEOUT = 30 * 60 * 1000;

export function useSessionLifecycle() {
    const posStore = usePosStore();
    const realtimeStore = useRealtimeStore();
    const navigationStore = useNavigationStore();

    let idleService: IdleService | null = null;
    let logoutBroadcast: LogoutBroadcast | null = null;

    function startIdleService(onIdle: () => void): void {
        if (idleService) idleService.stop();
        idleService = new IdleService({
            idleTimeout: IDLE_TIMEOUT,
            onIdle,
        });
        idleService.start();
    }

    function stopIdleService(): void {
        if (idleService) {
            idleService.stop();
            idleService = null;
        }
    }

    function resetIdleTimer(): void {
        if (idleService) idleService.resetTimer();
    }

    function initLogoutBroadcast(onLogout: () => void): void {
        if (logoutBroadcast) logoutBroadcast.destroy();
        logoutBroadcast = new LogoutBroadcast();
        logoutBroadcast.onLogout(onLogout);
    }

    function notifyLogout(): void {
        if (logoutBroadcast) logoutBroadcast.notifyLogout();
    }

    let deliveryRefreshInterval: ReturnType<typeof setInterval> | null = null;
    let barRefreshInterval: ReturnType<typeof setInterval> | null = null;
    let cashRefreshInterval: ReturnType<typeof setInterval> | null = null;

    function startPolling(hasBar: boolean, isLoggedInFn: () => boolean): void {
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

    function stopPolling(): void {
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

    const hasBar = ref(false);
    const barItemsCount = ref(0);

    async function checkBar(): Promise<void> {
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

    async function refreshBarCount(): Promise<void> {
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

    function cleanupAll(): void {
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
        startIdleService,
        stopIdleService,
        resetIdleTimer,
        initLogoutBroadcast,
        notifyLogout,
        startPolling,
        stopPolling,
        hasBar,
        barItemsCount,
        checkBar,
        refreshBarCount,
        cleanupAll,
    };
}
