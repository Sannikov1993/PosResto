/**
 * Courier Store Unit Tests
 *
 * @group unit
 * @group courier
 * @group stores
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Use vi.hoisted so mock fns are available inside vi.mock factories
const { mockHttpPost, mockHttpGet, mockHttpPatch } = vi.hoisted(() => ({
    mockHttpPost: vi.fn(),
    mockHttpGet: vi.fn(),
    mockHttpPatch: vi.fn(),
}));

vi.mock('../../../shared/services/httpClient.js', () => ({
    createHttpClient: () => ({
        http: {
            post: mockHttpPost,
            get: mockHttpGet,
            patch: mockHttpPatch,
        },
    }),
}));

vi.mock('../../../shared/services/logger.js', () => ({
    createLogger: () => ({
        info: vi.fn(),
        error: vi.fn(),
        warn: vi.fn(),
        debug: vi.fn(),
    }),
}));

vi.mock('../../../shared/services/auth.js', () => ({
    default: {
        setSession: vi.fn(),
        clearAuth: vi.fn(),
        getSession: vi.fn(() => null),
    },
}));

vi.mock('../../../shared/services/notificationSound.js', () => ({
    playSound: vi.fn(),
}));

vi.mock('../../../shared/config/realtimeConfig.js', () => ({
    DEBOUNCE_CONFIG: { apiRefresh: 1000 },
    debounce: (fn: Function) => {
        const debounced = (...args: any[]) => fn(...args);
        debounced.cancel = vi.fn();
        return debounced;
    },
}));

vi.mock('../../../shared/stores/realtime.js', () => ({
    useRealtimeStore: () => ({
        connected: false,
        init: vi.fn(),
        on: vi.fn(),
        destroy: vi.fn(),
    }),
}));

vi.mock('../../../echo.js', () => ({}));

import { useCourierStore } from '../../stores/courier.js';
import authService from '../../../shared/services/auth.js';

// Mock localStorage
const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: vi.fn((key: string) => store[key] || null),
        setItem: vi.fn((key: string, value: string) => { store[key] = value; }),
        removeItem: vi.fn((key: string) => { delete store[key]; }),
        clear: vi.fn(() => { store = {}; }),
    };
})();
Object.defineProperty(global, 'localStorage', { value: localStorageMock, configurable: true });

// Mock navigator.geolocation
const mockGeolocation = {
    getCurrentPosition: vi.fn(),
    watchPosition: vi.fn(() => 1),
    clearWatch: vi.fn(),
};
Object.defineProperty(global.navigator, 'geolocation', { value: mockGeolocation, configurable: true });

// Mock Notification
Object.defineProperty(global, 'Notification', {
    value: { permission: 'default' },
    configurable: true,
});

describe('Courier Store', () => {
    let store: ReturnType<typeof useCourierStore>;

    beforeEach(() => {
        vi.useFakeTimers();
        localStorageMock.clear();
        vi.clearAllMocks();
        setActivePinia(createPinia());
        store = useCourierStore();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('should not be authenticated by default', () => {
            expect(store.isAuthenticated).toBe(false);
        });

        it('should have null user', () => {
            expect(store.user).toBeNull();
        });

        it('should have null courier ID', () => {
            expect(store.courierId).toBeNull();
        });

        it('should not be loading initially', () => {
            expect(store.isLoading).toBe(false);
        });

        it('should have "orders" as active tab', () => {
            expect(store.activeTab).toBe('orders');
        });

        it('should have empty order lists', () => {
            expect(store.myOrders).toEqual([]);
            expect(store.availableOrders).toEqual([]);
        });

        it('should have no selected order', () => {
            expect(store.selectedOrder).toBeNull();
        });

        it('should have courier status as "available"', () => {
            expect(store.courierStatus).toBe('available');
        });

        it('should have default stats', () => {
            expect(store.stats).toEqual({
                todayOrders: 0,
                todayEarnings: 0,
                avgDeliveryTime: 0,
            });
        });

        it('should have null toast', () => {
            expect(store.toast).toBeNull();
        });
    });

    // ==================== Computed / Getters ====================

    describe('computed properties', () => {
        describe('activeOrders', () => {
            it('should filter out completed and cancelled orders', () => {
                store.myOrders = [
                    { id: 1, delivery_status: 'in_transit' },
                    { id: 2, delivery_status: 'completed' },
                    { id: 3, delivery_status: 'picked_up' },
                    { id: 4, delivery_status: 'cancelled' },
                ] as any;

                expect(store.activeOrders).toHaveLength(2);
                expect(store.activeOrders[0].id).toBe(1);
                expect(store.activeOrders[1].id).toBe(3);
            });

            it('should return empty array when no orders', () => {
                expect(store.activeOrders).toEqual([]);
            });
        });

        describe('userInitials', () => {
            it('should return initials from user name', () => {
                store.user = { id: 1, name: 'Ivan Petrov' } as any;

                expect(store.userInitials).toBe('IP');
            });

            it('should return default when no user', () => {
                store.user = null;

                expect(store.userInitials).toBe('\u041A'); // Cyrillic 'K'
            });

            it('should limit to 2 characters', () => {
                store.user = { id: 1, name: 'Ivan Petrovich Sidorov' } as any;

                expect(store.userInitials).toBe('IP');
            });
        });

        describe('headerTitle', () => {
            it('should return correct title for orders tab', () => {
                store.activeTab = 'orders';
                expect(store.headerTitle).toBe('\u041C\u043E\u0438 \u0437\u0430\u043A\u0430\u0437\u044B');
            });

            it('should return correct title for available tab', () => {
                store.activeTab = 'available';
                expect(store.headerTitle).toBe('\u0414\u043E\u0441\u0442\u0443\u043F\u043D\u044B\u0435');
            });

            it('should return correct title for profile tab', () => {
                store.activeTab = 'profile';
                expect(store.headerTitle).toBe('\u041F\u0440\u043E\u0444\u0438\u043B\u044C');
            });

            it('should return default title for unknown tab', () => {
                store.activeTab = 'unknown';
                expect(store.headerTitle).toBe('MenuLab \u041A\u0443\u0440\u044C\u0435\u0440');
            });
        });
    });

    // ==================== Auth Actions ====================

    describe('login', () => {
        it('should authenticate successfully with valid PIN', async () => {
            mockHttpPost.mockResolvedValueOnce({
                data: {
                    token: 'test-token',
                    user: { id: 1, name: 'Test Courier', restaurant_id: 10 },
                    courier_id: 5,
                },
            });
            // loadData calls
            mockHttpGet.mockResolvedValue({ data: [] });

            const result = await store.login('1234');

            expect(result.success).toBe(true);
            expect(store.isAuthenticated).toBe(true);
            expect(store.user).toEqual({ id: 1, name: 'Test Courier', restaurant_id: 10 });
            expect(store.courierId).toBe(5);
        });

        it('should store session via authService on login', async () => {
            mockHttpPost.mockResolvedValueOnce({
                data: {
                    token: 'test-token',
                    user: { id: 1, name: 'Test' },
                    courier_id: 5,
                },
            });
            mockHttpGet.mockResolvedValue({ data: [] });

            await store.login('1234');

            expect(authService.setSession).toHaveBeenCalledWith(
                { token: 'test-token', user: { id: 1, name: 'Test' } },
                { app: 'courier' }
            );
        });

        it('should save courier_id to localStorage', async () => {
            mockHttpPost.mockResolvedValueOnce({
                data: {
                    token: 'abc',
                    user: { id: 1, name: 'Test' },
                    courier_id: 7,
                },
            });
            mockHttpGet.mockResolvedValue({ data: [] });

            await store.login('1234');

            expect(localStorageMock.setItem).toHaveBeenCalledWith('courier_id', '7');
        });

        it('should return error on failed login', async () => {
            mockHttpPost.mockRejectedValueOnce({
                response: { data: { message: 'Invalid PIN' } },
            });

            const result = await store.login('9999');

            expect(result.success).toBe(false);
            expect(result.message).toBe('Invalid PIN');
            expect(store.isAuthenticated).toBe(false);
        });

        it('should set isLoading during login', async () => {
            let isLoadingDuringCall = false;
            mockHttpPost.mockImplementation(async () => {
                isLoadingDuringCall = store.isLoading;
                return {
                    data: { token: 't', user: { id: 1, name: 'T' }, courier_id: 1 },
                };
            });
            mockHttpGet.mockResolvedValue({ data: [] });

            await store.login('1234');

            expect(isLoadingDuringCall).toBe(true);
            expect(store.isLoading).toBe(false);
        });
    });

    describe('logout', () => {
        it('should clear auth state on logout', async () => {
            // Set up authenticated state
            store.isAuthenticated = true;
            store.user = { id: 1, name: 'Test' } as any;
            store.courierId = 5 as any;
            store.myOrders = [{ id: 1, delivery_status: 'in_transit' }] as any;
            store.availableOrders = [{ id: 2, delivery_status: 'pending' }] as any;

            mockHttpPost.mockResolvedValueOnce({});

            await store.logout();

            expect(store.isAuthenticated).toBe(false);
            expect(store.user).toBeNull();
            expect(store.courierId).toBeNull();
            expect(store.myOrders).toEqual([]);
            expect(store.availableOrders).toEqual([]);
        });

        it('should call authService.clearAuth on logout', async () => {
            mockHttpPost.mockResolvedValueOnce({});

            await store.logout();

            expect(authService.clearAuth).toHaveBeenCalled();
        });

        it('should remove courier_id from localStorage', async () => {
            mockHttpPost.mockResolvedValueOnce({});

            await store.logout();

            expect(localStorageMock.removeItem).toHaveBeenCalledWith('courier_id');
        });

        it('should handle logout API errors gracefully', async () => {
            mockHttpPost.mockRejectedValueOnce(new Error('Network error'));

            // Should not throw
            await store.logout();

            expect(store.isAuthenticated).toBe(false);
            expect(authService.clearAuth).toHaveBeenCalled();
        });
    });

    describe('checkAuth', () => {
        it('should restore session from authService', () => {
            (authService.getSession as ReturnType<typeof vi.fn>).mockReturnValueOnce({
                token: 'saved-token',
                user: { id: 1, name: 'Saved User' },
            });
            localStorageMock.getItem.mockReturnValueOnce('3');

            const result = store.checkAuth();

            expect(result).toBe(true);
            expect(store.isAuthenticated).toBe(true);
            expect(store.user).toEqual({ id: 1, name: 'Saved User' });
            expect(store.courierId).toBe('3');
        });

        it('should return false when no session exists', () => {
            (authService.getSession as ReturnType<typeof vi.fn>).mockReturnValueOnce(null);

            const result = store.checkAuth();

            expect(result).toBe(false);
            expect(store.isAuthenticated).toBe(false);
        });
    });

    // ==================== Data Loading ====================

    describe('loadMyOrders', () => {
        it('should fetch and set my orders', async () => {
            const orders = [
                { id: 1, delivery_status: 'in_transit', courier_id: 5 },
                { id: 2, delivery_status: 'picked_up', courier_id: 5 },
            ];
            mockHttpGet.mockResolvedValueOnce({ data: orders });

            await store.loadMyOrders();

            expect(store.myOrders).toEqual(orders);
        });

        it('should handle load errors gracefully', async () => {
            mockHttpGet.mockRejectedValueOnce(new Error('Network error'));

            await store.loadMyOrders();

            // Should not throw, orders remain empty
            expect(store.myOrders).toEqual([]);
        });
    });

    describe('loadAvailableOrders', () => {
        it('should fetch available orders and filter out assigned ones', async () => {
            const orders = [
                { id: 1, delivery_status: 'pending', courier_id: null },
                { id: 2, delivery_status: 'ready', courier_id: 3 },
                { id: 3, delivery_status: 'preparing', courier_id: null },
            ];
            mockHttpGet.mockResolvedValueOnce({ data: orders });

            await store.loadAvailableOrders();

            expect(store.availableOrders).toHaveLength(2);
            expect(store.availableOrders.map((o: any) => o.id)).toEqual([1, 3]);
        });
    });

    describe('loadData', () => {
        it('should load orders and stats together', async () => {
            store.courierId = 1 as any;

            // loadMyOrders, loadAvailableOrders, loadStats each call http.get
            mockHttpGet
                .mockResolvedValueOnce({ data: [{ id: 1, delivery_status: 'in_transit', courier_id: 1 }] })
                .mockResolvedValueOnce({ data: [{ id: 2, delivery_status: 'pending', courier_id: null }] })
                .mockResolvedValueOnce({
                    data: {
                        today_orders: 5,
                        today_earnings: 2500,
                        avg_delivery_time: 30,
                    },
                });

            await store.loadData();

            expect(store.myOrders).toHaveLength(1);
            expect(store.availableOrders).toHaveLength(1);
            expect(store.stats).toEqual({
                todayOrders: 5,
                todayEarnings: 2500,
                avgDeliveryTime: 30,
            });
            expect(store.isLoading).toBe(false);
        });

        it('should set isLoading during data load', async () => {
            let isLoadingDuringCall = false;
            mockHttpGet.mockImplementation(async () => {
                isLoadingDuringCall = store.isLoading;
                return { data: [] };
            });

            await store.loadData();

            expect(isLoadingDuringCall).toBe(true);
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Order Actions ====================

    describe('acceptOrder', () => {
        it('should accept order and switch to orders tab', async () => {
            store.courierId = 5 as any;
            const order = { id: 10, delivery_status: 'pending' } as any;

            mockHttpPost.mockResolvedValueOnce({});
            mockHttpGet.mockResolvedValue({ data: [] });

            const result = await store.acceptOrder(order);

            expect(result.success).toBe(true);
            expect(mockHttpPost).toHaveBeenCalledWith('/delivery/orders/10/assign-courier', {
                courier_id: 5,
            });
            expect(store.selectedOrder).toBeNull();
            expect(store.activeTab).toBe('orders');
        });

        it('should return failure on API error', async () => {
            store.courierId = 5 as any;
            const order = { id: 10, delivery_status: 'pending' } as any;

            mockHttpPost.mockRejectedValueOnce({
                response: { data: { message: 'Order already taken' } },
            });

            const result = await store.acceptOrder(order);

            expect(result.success).toBe(false);
        });
    });

    describe('updateOrderStatus', () => {
        it('should update order status successfully', async () => {
            store.courierId = 5 as any;
            const order = { id: 10, delivery_status: 'picked_up' } as any;

            mockHttpPatch.mockResolvedValueOnce({});
            mockHttpGet.mockResolvedValue({ data: [] });

            const result = await store.updateOrderStatus(order, 'in_transit');

            expect(result.success).toBe(true);
            expect(mockHttpPatch).toHaveBeenCalledWith('/delivery/orders/10/status', {
                delivery_status: 'in_transit',
            });
        });

        it('should clear selectedOrder on completed status', async () => {
            store.selectedOrder = { id: 10, delivery_status: 'in_transit' } as any;
            const order = { id: 10, delivery_status: 'in_transit' } as any;

            mockHttpPatch.mockResolvedValueOnce({});
            mockHttpGet.mockResolvedValue({ data: [] });

            await store.updateOrderStatus(order, 'completed');

            expect(store.selectedOrder).toBeNull();
        });
    });

    describe('cancelOrder', () => {
        it('should cancel order with reason', async () => {
            store.courierId = 5 as any;
            const order = { id: 10, delivery_status: 'in_transit' } as any;

            mockHttpPatch.mockResolvedValueOnce({});
            mockHttpGet.mockResolvedValue({ data: [] });

            const result = await store.cancelOrder(order, 'Customer unreachable');

            expect(result.success).toBe(true);
            expect(mockHttpPatch).toHaveBeenCalledWith('/delivery/orders/10/status', {
                delivery_status: 'cancelled',
                cancel_reason: 'Customer unreachable',
            });
            expect(store.selectedOrder).toBeNull();
        });
    });

    // ==================== Courier Status ====================

    describe('toggleStatus', () => {
        it('should toggle from available to offline', async () => {
            store.courierId = 5 as any;
            store.courierStatus = 'available';

            mockHttpPatch.mockResolvedValueOnce({});

            await store.toggleStatus();

            expect(store.courierStatus).toBe('offline');
        });

        it('should toggle from offline to available', async () => {
            store.courierId = 5 as any;
            store.courierStatus = 'offline';

            mockHttpPatch.mockResolvedValueOnce({});

            await store.toggleStatus();

            expect(store.courierStatus).toBe('available');
        });

        it('should not change status on API error', async () => {
            store.courierId = 5 as any;
            store.courierStatus = 'available';

            mockHttpPatch.mockRejectedValueOnce(new Error('Network error'));

            await store.toggleStatus();

            expect(store.courierStatus).toBe('available');
        });
    });

    // ==================== Toast ====================

    describe('showToast', () => {
        it('should display toast message', () => {
            store.showToast('Test message', 'success');

            expect(store.toast).toEqual({ message: 'Test message', type: 'success' });
        });

        it('should auto-dismiss toast after 3 seconds', () => {
            store.showToast('Test message', 'info');

            expect(store.toast).not.toBeNull();

            vi.advanceTimersByTime(3000);

            expect(store.toast).toBeNull();
        });

        it('should default to "info" type', () => {
            store.showToast('Test message');

            expect(store.toast?.type).toBe('info');
        });
    });

    // ==================== Formatters ====================

    describe('formatMoney', () => {
        it('should format money in RUB currency', () => {
            const result = store.formatMoney(1500);

            // Check that it contains the number (locale-dependent formatting)
            expect(result).toContain('1');
            expect(result).toContain('500');
        });

        it('should handle zero amount', () => {
            const result = store.formatMoney(0);

            expect(result).toContain('0');
        });
    });

    describe('formatAddress', () => {
        it('should format street and house', () => {
            const order = {
                id: 1,
                delivery_status: 'pending',
                address_street: 'Lenina',
                address_house: '42',
            } as any;

            expect(store.formatAddress(order)).toBe('Lenina, 42');
        });

        it('should return street only when no house', () => {
            const order = {
                id: 1,
                delivery_status: 'pending',
                address_street: 'Lenina',
            } as any;

            expect(store.formatAddress(order)).toBe('Lenina');
        });
    });

    describe('formatFullAddress', () => {
        it('should format complete address with all parts', () => {
            const order = {
                id: 1,
                delivery_status: 'pending',
                address_street: 'Lenina',
                address_house: '42',
                address_apartment: '15',
                address_entrance: '3',
                address_floor: '5',
                address_intercom: '1542',
            } as any;

            const result = store.formatFullAddress(order);

            expect(result).toContain('Lenina');
            expect(result).toContain('\u0434. 42');
            expect(result).toContain('\u043A\u0432. 15');
            expect(result).toContain('\u043F\u043E\u0434\u044A\u0435\u0437\u0434 3');
            expect(result).toContain('\u044D\u0442\u0430\u0436 5');
            expect(result).toContain('\u0434\u043E\u043C\u043E\u0444\u043E\u043D 1542');
        });
    });

    describe('formatPaymentMethod', () => {
        it('should format known payment methods', () => {
            expect(store.formatPaymentMethod('cash')).toBe('\u041D\u0430\u043B\u0438\u0447\u043D\u044B\u0435');
            expect(store.formatPaymentMethod('card')).toBe('\u041A\u0430\u0440\u0442\u043E\u0439 \u043F\u0440\u0438 \u043F\u043E\u043B\u0443\u0447\u0435\u043D\u0438\u0438');
            expect(store.formatPaymentMethod('online')).toBe('\u041E\u043F\u043B\u0430\u0447\u0435\u043D \u043E\u043D\u043B\u0430\u0439\u043D');
        });

        it('should return original string for unknown methods', () => {
            expect(store.formatPaymentMethod('crypto')).toBe('crypto');
        });
    });

    describe('formatTime', () => {
        it('should format datetime to HH:MM', () => {
            const result = store.formatTime('2024-01-15T14:30:00');

            expect(result).toMatch(/14:30/);
        });

        it('should return empty string for null', () => {
            expect(store.formatTime(null)).toBe('');
        });
    });

    describe('getStatusClass', () => {
        it('should return correct CSS class for known statuses', () => {
            expect(store.getStatusClass('new')).toBe('bg-blue-500');
            expect(store.getStatusClass('cooking')).toBe('bg-yellow-500');
            expect(store.getStatusClass('ready')).toBe('bg-green-500');
            expect(store.getStatusClass('in_transit')).toBe('bg-purple-500');
            expect(store.getStatusClass('completed')).toBe('bg-gray-500');
            expect(store.getStatusClass('cancelled')).toBe('bg-red-500');
        });

        it('should return gray for unknown status', () => {
            expect(store.getStatusClass('unknown')).toBe('bg-gray-500');
        });
    });

    describe('getStatusLabel', () => {
        it('should return Russian labels for known statuses', () => {
            expect(store.getStatusLabel('new')).toBe('\u041D\u043E\u0432\u044B\u0439');
            expect(store.getStatusLabel('cooking')).toBe('\u0413\u043E\u0442\u043E\u0432\u0438\u0442\u0441\u044F');
            expect(store.getStatusLabel('ready')).toBe('\u0413\u043E\u0442\u043E\u0432');
            expect(store.getStatusLabel('completed')).toBe('\u0414\u043E\u0441\u0442\u0430\u0432\u043B\u0435\u043D');
        });

        it('should return original status for unknown values', () => {
            expect(store.getStatusLabel('mystery')).toBe('mystery');
        });
    });

    // ==================== Geolocation ====================

    describe('startLocationTracking', () => {
        it('should set geoEnabled to true', () => {
            store.startLocationTracking();

            expect(store.geoEnabled).toBe(true);
        });

        it('should call getCurrentPosition and watchPosition', () => {
            store.startLocationTracking();

            expect(mockGeolocation.getCurrentPosition).toHaveBeenCalled();
            expect(mockGeolocation.watchPosition).toHaveBeenCalled();
        });
    });

    describe('stopLocationTracking', () => {
        it('should set geoEnabled to false', () => {
            store.startLocationTracking();
            store.stopLocationTracking();

            expect(store.geoEnabled).toBe(false);
        });
    });
});
