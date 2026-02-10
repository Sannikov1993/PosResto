/**
 * useSessionLifecycle Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock IdleService and LogoutBroadcast using hoisted mocks
const {
    mockIdleStart, mockIdleStop, mockIdleResetTimer,
    mockBroadcastDestroy, mockBroadcastNotifyLogout, mockBroadcastOnLogout,
} = vi.hoisted(() => ({
    mockIdleStart: vi.fn(),
    mockIdleStop: vi.fn(),
    mockIdleResetTimer: vi.fn(),
    mockBroadcastDestroy: vi.fn(),
    mockBroadcastNotifyLogout: vi.fn(),
    mockBroadcastOnLogout: vi.fn(),
}));

vi.mock('@/pos/services/IdleService.js', () => {
    const IdleService = function (this: any, opts: any) {
        this.start = mockIdleStart;
        this.stop = mockIdleStop;
        this.resetTimer = mockIdleResetTimer;
        this._opts = opts;
    } as any;
    return { IdleService };
});

vi.mock('@/pos/services/LogoutBroadcast.js', () => {
    const LogoutBroadcast = function (this: any) {
        this.destroy = mockBroadcastDestroy;
        this.notifyLogout = mockBroadcastNotifyLogout;
        this.onLogout = mockBroadcastOnLogout;
    } as any;
    return { LogoutBroadcast };
});

// Mock stores
const mockLoadDeliveryOrders = vi.fn();
const mockLoadCurrentShift = vi.fn();
const mockLoadPaidOrders = vi.fn();
const mockRealtimeDestroy = vi.fn();
const mockNavigationReset = vi.fn();

vi.mock('@/pos/stores/pos.js', () => ({
    usePosStore: () => ({
        loadDeliveryOrders: mockLoadDeliveryOrders,
        loadCurrentShift: mockLoadCurrentShift,
        loadPaidOrders: mockLoadPaidOrders,
    }),
}));

vi.mock('@/shared/stores/realtime.js', () => ({
    useRealtimeStore: () => ({
        destroy: mockRealtimeDestroy,
    }),
}));

vi.mock('@/shared/stores/navigation.js', () => ({
    useNavigationStore: () => ({
        reset: mockNavigationReset,
    }),
}));

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        bar: {
            check: vi.fn(),
            getOrders: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useSessionLifecycle } from '@/pos/composables/useSessionLifecycle.js';

describe('useSessionLifecycle', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('initial state', () => {
        it('should return all expected functions and refs', () => {
            const lifecycle = useSessionLifecycle();

            expect(lifecycle.startIdleService).toBeTypeOf('function');
            expect(lifecycle.stopIdleService).toBeTypeOf('function');
            expect(lifecycle.resetIdleTimer).toBeTypeOf('function');
            expect(lifecycle.initLogoutBroadcast).toBeTypeOf('function');
            expect(lifecycle.notifyLogout).toBeTypeOf('function');
            expect(lifecycle.startPolling).toBeTypeOf('function');
            expect(lifecycle.stopPolling).toBeTypeOf('function');
            expect(lifecycle.checkBar).toBeTypeOf('function');
            expect(lifecycle.refreshBarCount).toBeTypeOf('function');
            expect(lifecycle.cleanupAll).toBeTypeOf('function');
            expect(lifecycle.hasBar.value).toBe(false);
            expect(lifecycle.barItemsCount.value).toBe(0);
        });
    });

    describe('startIdleService', () => {
        it('should create and start IdleService with callback', () => {
            const { startIdleService } = useSessionLifecycle();
            const onIdle = vi.fn();

            startIdleService(onIdle);

            expect(mockIdleStart).toHaveBeenCalledOnce();
        });

        it('should stop previous IdleService before starting new one', () => {
            const { startIdleService } = useSessionLifecycle();

            startIdleService(vi.fn());
            startIdleService(vi.fn());

            expect(mockIdleStop).toHaveBeenCalled();
        });
    });

    describe('stopIdleService', () => {
        it('should stop IdleService when running', () => {
            const { startIdleService, stopIdleService } = useSessionLifecycle();

            startIdleService(vi.fn());
            stopIdleService();

            expect(mockIdleStop).toHaveBeenCalled();
        });

        it('should do nothing when no IdleService is active', () => {
            const { stopIdleService } = useSessionLifecycle();

            stopIdleService(); // should not throw

            expect(mockIdleStop).not.toHaveBeenCalled();
        });
    });

    describe('resetIdleTimer', () => {
        it('should reset timer when IdleService is running', () => {
            const { startIdleService, resetIdleTimer } = useSessionLifecycle();

            startIdleService(vi.fn());
            resetIdleTimer();

            expect(mockIdleResetTimer).toHaveBeenCalledOnce();
        });

        it('should do nothing when no IdleService is active', () => {
            const { resetIdleTimer } = useSessionLifecycle();

            resetIdleTimer(); // should not throw

            expect(mockIdleResetTimer).not.toHaveBeenCalled();
        });
    });

    describe('initLogoutBroadcast', () => {
        it('should create LogoutBroadcast and register callback', () => {
            const { initLogoutBroadcast } = useSessionLifecycle();
            const onLogout = vi.fn();

            initLogoutBroadcast(onLogout);

            expect(mockBroadcastOnLogout).toHaveBeenCalledWith(onLogout);
        });

        it('should destroy previous broadcast before creating new one', () => {
            const { initLogoutBroadcast } = useSessionLifecycle();

            initLogoutBroadcast(vi.fn());
            initLogoutBroadcast(vi.fn());

            expect(mockBroadcastDestroy).toHaveBeenCalled();
        });
    });

    describe('notifyLogout', () => {
        it('should call notifyLogout on broadcast when initialized', () => {
            const { initLogoutBroadcast, notifyLogout } = useSessionLifecycle();

            initLogoutBroadcast(vi.fn());
            notifyLogout();

            expect(mockBroadcastNotifyLogout).toHaveBeenCalledOnce();
        });

        it('should do nothing when broadcast not initialized', () => {
            const { notifyLogout } = useSessionLifecycle();

            notifyLogout(); // should not throw

            expect(mockBroadcastNotifyLogout).not.toHaveBeenCalled();
        });
    });

    describe('startPolling', () => {
        it('should start delivery polling interval', () => {
            const { startPolling } = useSessionLifecycle();
            const isLoggedIn = () => true;

            startPolling(false, isLoggedIn);

            vi.advanceTimersByTime(60000);
            expect(mockLoadDeliveryOrders).toHaveBeenCalledOnce();
        });

        it('should start bar polling when hasBar param is true', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: true });
            mockApi.bar.getOrders.mockResolvedValue({ counts: { new: 0, in_progress: 0 } });

            const { checkBar, startPolling } = useSessionLifecycle();
            const isLoggedIn = () => true;

            // First enable hasBar ref via checkBar
            await checkBar();

            startPolling(true, isLoggedIn);

            vi.advanceTimersByTime(30000);
            // bar refresh is called indirectly via refreshBarCount
            // checkBar already called getOrders once; polling adds another
            expect(mockApi.bar.getOrders).toHaveBeenCalledTimes(2);
        });

        it('should start cash polling interval', () => {
            const { startPolling } = useSessionLifecycle();
            const isLoggedIn = () => true;

            startPolling(false, isLoggedIn);

            vi.advanceTimersByTime(60000);
            expect(mockLoadCurrentShift).toHaveBeenCalled();
            expect(mockLoadPaidOrders).toHaveBeenCalled();
        });

        it('should not poll when isLoggedIn returns false', () => {
            const { startPolling } = useSessionLifecycle();
            const isLoggedIn = () => false;

            startPolling(false, isLoggedIn);

            vi.advanceTimersByTime(120000);
            expect(mockLoadDeliveryOrders).not.toHaveBeenCalled();
            expect(mockLoadCurrentShift).not.toHaveBeenCalled();
        });

        it('should stop previous polling before starting new one', () => {
            const { startPolling } = useSessionLifecycle();
            const isLoggedIn = () => true;

            startPolling(false, isLoggedIn);
            startPolling(false, isLoggedIn);

            vi.advanceTimersByTime(60000);
            // Should only fire once per interval, not doubled
            expect(mockLoadDeliveryOrders).toHaveBeenCalledTimes(1);
        });
    });

    describe('stopPolling', () => {
        it('should clear all polling intervals', () => {
            const { startPolling, stopPolling } = useSessionLifecycle();
            const isLoggedIn = () => true;

            startPolling(true, isLoggedIn);
            stopPolling();

            vi.advanceTimersByTime(120000);
            expect(mockLoadDeliveryOrders).not.toHaveBeenCalled();
            expect(mockLoadCurrentShift).not.toHaveBeenCalled();
        });

        it('should be safe to call when no polling is active', () => {
            const { stopPolling } = useSessionLifecycle();

            expect(() => stopPolling()).not.toThrow();
        });
    });

    describe('checkBar', () => {
        it('should set hasBar to true when API returns has_bar true', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: true });
            mockApi.bar.getOrders.mockResolvedValue({ counts: { new: 3, in_progress: 2 } });

            const { checkBar, hasBar, barItemsCount } = useSessionLifecycle();
            await checkBar();

            expect(hasBar.value).toBe(true);
            expect(barItemsCount.value).toBe(5);
        });

        it('should set hasBar to false when API returns has_bar false', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: false });

            const { checkBar, hasBar } = useSessionLifecycle();
            await checkBar();

            expect(hasBar.value).toBe(false);
        });

        it('should set hasBar to false on API error', async () => {
            mockApi.bar.check.mockRejectedValue(new Error('Network error'));

            const { checkBar, hasBar } = useSessionLifecycle();
            await checkBar();

            expect(hasBar.value).toBe(false);
        });
    });

    describe('refreshBarCount', () => {
        it('should update barItemsCount from API response', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: true });
            mockApi.bar.getOrders.mockResolvedValue({ counts: { new: 5, in_progress: 3 } });

            const { checkBar, refreshBarCount, barItemsCount } = useSessionLifecycle();
            await checkBar();

            mockApi.bar.getOrders.mockResolvedValue({ counts: { new: 1, in_progress: 0 } });
            await refreshBarCount();

            expect(barItemsCount.value).toBe(1);
        });

        it('should do nothing when hasBar is false', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: false });

            const { checkBar, refreshBarCount } = useSessionLifecycle();
            await checkBar();

            mockApi.bar.getOrders.mockClear();
            await refreshBarCount();

            expect(mockApi.bar.getOrders).not.toHaveBeenCalled();
        });

        it('should handle API errors silently', async () => {
            mockApi.bar.check.mockResolvedValue({ has_bar: true });
            mockApi.bar.getOrders
                .mockResolvedValueOnce({ counts: { new: 2, in_progress: 1 } })
                .mockRejectedValueOnce(new Error('fail'));

            const { checkBar, refreshBarCount, barItemsCount } = useSessionLifecycle();
            await checkBar();

            expect(barItemsCount.value).toBe(3);

            await refreshBarCount(); // should not throw
            // barItemsCount stays the same since error is ignored
            expect(barItemsCount.value).toBe(3);
        });
    });

    describe('cleanupAll', () => {
        it('should stop idle service', () => {
            const { startIdleService, cleanupAll } = useSessionLifecycle();

            startIdleService(vi.fn());
            cleanupAll();

            expect(mockIdleStop).toHaveBeenCalled();
        });

        it('should stop polling', () => {
            const { startPolling, cleanupAll } = useSessionLifecycle();

            startPolling(false, () => true);
            cleanupAll();

            vi.advanceTimersByTime(120000);
            expect(mockLoadDeliveryOrders).not.toHaveBeenCalled();
        });

        it('should destroy realtime store', () => {
            const { cleanupAll } = useSessionLifecycle();

            cleanupAll();

            expect(mockRealtimeDestroy).toHaveBeenCalledOnce();
        });

        it('should reset navigation store', () => {
            const { cleanupAll } = useSessionLifecycle();

            cleanupAll();

            expect(mockNavigationReset).toHaveBeenCalledOnce();
        });

        it('should destroy logout broadcast when initialized', () => {
            const { initLogoutBroadcast, cleanupAll } = useSessionLifecycle();

            initLogoutBroadcast(vi.fn());
            cleanupAll();

            expect(mockBroadcastDestroy).toHaveBeenCalled();
        });
    });
});
