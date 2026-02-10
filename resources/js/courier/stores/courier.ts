import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { createHttpClient } from '../../shared/services/httpClient.js';
import { createLogger } from '../../shared/services/logger.js';
import authService from '../../shared/services/auth.js';
import { playSound } from '../../shared/services/notificationSound.js';
import { DEBOUNCE_CONFIG, debounce } from '../../shared/config/realtimeConfig.js';
import { useRealtimeStore } from '../../shared/stores/realtime.js';
import '../../echo.js';

const { http } = createHttpClient({ module: 'Courier' });
const log = createLogger('Courier');

interface CourierUser {
    id: number;
    name: string;
    restaurant_id?: number;
    [key: string]: unknown;
}

interface DeliveryOrder {
    id: number;
    order_number?: string;
    delivery_status: string;
    courier_id?: number | null;
    address_street?: string;
    address_house?: string;
    address_apartment?: string;
    address_entrance?: string;
    address_floor?: string;
    address_intercom?: string;
    [key: string]: unknown;
}

interface CourierStats {
    todayOrders: number;
    todayEarnings: number;
    avgDeliveryTime: number;
}

interface ToastMessage {
    message: string;
    type: string;
}

export const useCourierStore = defineStore('courier', () => {
    // Auth state
    const isAuthenticated = ref(false);
    const user = ref<CourierUser | null>(null);
    const courierId = ref<number | string | null>(null);
    const token = ref('');
    const isLoading = ref(false);

    // App state
    const activeTab = ref('orders');
    const myOrders = ref<DeliveryOrder[]>([]);
    const availableOrders = ref<DeliveryOrder[]>([]);
    const selectedOrder = ref<DeliveryOrder | null>(null);
    const courierStatus = ref('available');
    const isOnline = ref(navigator.onLine);

    // Stats
    const stats = ref<CourierStats>({
        todayOrders: 0,
        todayEarnings: 0,
        avgDeliveryTime: 0
    });

    // PWA/Features
    const notificationPermission = ref(Notification?.permission || 'default');
    const geoEnabled = ref(false);

    // Toast
    const toast = ref<ToastMessage | null>(null);

    // SSE/Geo
    let sseConnection: unknown = null;
    let geoWatchId: number | null = null;

    // Computed
    const activeOrders = computed(() => {
        return myOrders.value.filter((o: any) =>
            !['completed', 'cancelled'].includes(o.delivery_status)
        );
    });

    const userInitials = computed(() => {
        if (!user.value?.name) return 'К';
        return user.value.name.split(' ').map((n: any) => n[0]).join('').toUpperCase().slice(0, 2);
    });

    const headerTitle = computed(() => {
        switch (activeTab.value) {
            case 'orders': return 'Мои заказы';
            case 'available': return 'Доступные';
            case 'profile': return 'Профиль';
            default: return 'MenuLab Курьер';
        }
    });

    // Auth methods
    async function login(pin: string): Promise<{ success: boolean; message?: string }> {
        isLoading.value = true;
        try {
            const response = await http.post('/auth/login-pin', { pin });
            const data = response?.data || response;

            token.value = data.token;
            user.value = data.user;
            courierId.value = data.courier_id || data.user.id;
            isAuthenticated.value = true;

            authService.setSession({ token: data.token, user: data.user }, { app: 'courier' });
            localStorage.setItem('courier_id', String(courierId.value));

            await loadData();
            startLocationTracking();
            connectSSE();

            return { success: true };
        } catch (error: unknown) {
            return { success: false, message: (error as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Неверный PIN-код' };
        } finally {
            isLoading.value = false;
        }
    }

    async function logout() {
        try {
            await http.post('/auth/logout');
        } catch (e: any) { /* ignore */ }

        authService.clearAuth();
        localStorage.removeItem('courier_id');

        isAuthenticated.value = false;
        token.value = '';
        user.value = null;
        courierId.value = null;
        myOrders.value = [];
        availableOrders.value = [];

        stopLocationTracking();
        disconnectSSE();
    }

    function checkAuth(): boolean {
        const session = authService.getSession();
        const savedCourierId = localStorage.getItem('courier_id');

        if (session?.token && session?.user) {
            token.value = session.token as string;
            user.value = session.user as CourierUser;
            courierId.value = savedCourierId;
            isAuthenticated.value = true;
            return true;
        }
        return false;
    }

    // Data loading
    async function loadData() {
        isLoading.value = true;
        try {
            await Promise.all([
                loadMyOrders(),
                loadAvailableOrders(),
                loadStats()
            ]);
        } catch (error: any) {
            showToast('Ошибка загрузки данных', 'error');
        } finally {
            isLoading.value = false;
        }
    }

    async function loadMyOrders() {
        try {
            const response = await http.get('/delivery/orders', {
                params: { courier_id: courierId.value, today: true }
            });
            myOrders.value = response?.data || [];
        } catch (error: unknown) {
            log.error('Failed to load my orders:', (error as Error).message);
        }
    }

    async function loadAvailableOrders() {
        try {
            const response = await http.get('/delivery/orders', {
                params: { delivery_status: 'pending,preparing,ready', today: true, no_courier: true }
            });
            const data: DeliveryOrder[] = response?.data || [];
            availableOrders.value = data.filter((o: any) => !o.courier_id);
        } catch (error: unknown) {
            log.error('Failed to load available orders:', (error as Error).message);
        }
    }

    async function loadStats() {
        try {
            const response = await http.get(`/delivery/couriers/${courierId.value}`);
            const data = response?.data || response;
            if (data) {
                stats.value = {
                    todayOrders: data.today_orders || 0,
                    todayEarnings: data.today_earnings || 0,
                    avgDeliveryTime: data.avg_delivery_time || 25
                };
            }
        } catch (error: unknown) {
            log.error('Failed to load stats:', (error as Error).message);
        }
    }

    // Order actions
    async function acceptOrder(order: DeliveryOrder): Promise<{ success: boolean }> {
        isLoading.value = true;
        try {
            await http.post(`/delivery/orders/${order.id}/assign-courier`, {
                courier_id: courierId.value
            });

            showToast('Заказ принят', 'success');
            await loadData();
            selectedOrder.value = null;
            activeTab.value = 'orders';
            return { success: true };
        } catch (error: unknown) {
            showToast((error as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function updateOrderStatus(order: DeliveryOrder, status: string): Promise<{ success: boolean }> {
        isLoading.value = true;
        try {
            await http.patch(`/delivery/orders/${order.id}/status`, {
                delivery_status: status
            });

            const statusLabels: Record<string, string> = {
                'picked_up': 'Заказ забран',
                'in_transit': 'В пути',
                'completed': 'Заказ доставлен'
            };

            showToast(statusLabels[status] || 'Статус обновлен', 'success');
            await loadData();

            if (status === 'completed') {
                selectedOrder.value = null;
            } else {
                selectedOrder.value = myOrders.value.find((o: any) => o.id === order.id) || null;
            }
            return { success: true };
        } catch (error: unknown) {
            showToast((error as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function cancelOrder(order: DeliveryOrder, reason: string): Promise<{ success: boolean }> {
        isLoading.value = true;
        try {
            await http.patch(`/delivery/orders/${order.id}/status`, {
                delivery_status: 'cancelled',
                cancel_reason: reason
            });

            showToast('Заказ отменен', 'success');
            await loadData();
            selectedOrder.value = null;
            return { success: true };
        } catch (error: unknown) {
            showToast((error as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    // Courier status
    async function toggleStatus() {
        const newStatus = courierStatus.value === 'available' ? 'offline' : 'available';

        try {
            await http.patch(`/delivery/couriers/${courierId.value}/status`, {
                status: newStatus
            });

            courierStatus.value = newStatus;
            showToast(newStatus === 'available' ? 'Вы онлайн' : 'Вы оффлайн', 'info');
        } catch (error: any) {
            showToast('Ошибка смены статуса', 'error');
        }
    }

    // Geolocation
    let lastLocationSent = 0;
    const MIN_LOCATION_INTERVAL = 5000;

    function startLocationTracking() {
        if (!navigator.geolocation) return;

        geoEnabled.value = true;

        const geoOptions: PositionOptions = {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 15000
        };

        navigator.geolocation.getCurrentPosition(
            (pos) => sendLocation(
                pos.coords.latitude,
                pos.coords.longitude,
                pos.coords.accuracy,
                pos.coords.speed,
                pos.coords.heading
            ),
            (err) => log.warn('Geolocation error:', err),
            geoOptions
        );

        geoWatchId = navigator.geolocation.watchPosition(
            (pos) => {
                if (pos.coords.accuracy < 100) {
                    sendLocation(
                        pos.coords.latitude,
                        pos.coords.longitude,
                        pos.coords.accuracy,
                        pos.coords.speed,
                        pos.coords.heading
                    );
                }
            },
            (err) => log.warn('Geolocation watch error:', err),
            geoOptions
        );
    }

    function stopLocationTracking() {
        if (geoWatchId) {
            navigator.geolocation.clearWatch(geoWatchId);
            geoWatchId = null;
        }
        geoEnabled.value = false;
    }

    async function sendLocation(lat: number, lng: number, accuracy: number | null = null, speed: number | null = null, heading: number | null = null) {
        const now = Date.now();
        if (now - lastLocationSent < MIN_LOCATION_INTERVAL) {
            return;
        }
        lastLocationSent = now;

        try {
            await http.post('/courier/location', {
                latitude: lat,
                longitude: lng,
                accuracy,
                speed,
                heading
            });
        } catch (error: any) {
            try {
                await http.patch(`/delivery/couriers/${courierId.value}/status`, {
                    location: { lat, lng }
                });
            } catch (fallbackError: unknown) {
                log.warn('Failed to send location:', (fallbackError as Error).message);
            }
        }
    }

    // Real-time
    const debouncedLoadData = debounce(() => {
        loadData();
    }, DEBOUNCE_CONFIG.apiRefresh);

    const debouncedLoadAvailableOrders = debounce(() => {
        loadAvailableOrders();
    }, DEBOUNCE_CONFIG.apiRefresh);

    let realtimeStoreInstance: ReturnType<typeof useRealtimeStore> | null = null;
    let eventHandlersSetup = false;

    function getRealtimeStore() {
        if (!realtimeStoreInstance) {
            realtimeStoreInstance = useRealtimeStore();
        }
        return realtimeStoreInstance;
    }

    const reverbConnected = computed(() => {
        const store = getRealtimeStore();
        return store.connected;
    });

    function connectSSE() {
        connectReverb();
    }

    function disconnectSSE() {
        disconnectReverb();
    }

    function connectReverb() {
        const restaurantId = user.value?.restaurant_id;
        if (!restaurantId) {
            log.warn('No restaurant_id, skipping connection');
            return;
        }

        const realtimeStore = getRealtimeStore();

        realtimeStore.init(restaurantId, {
            channels: ['delivery', 'orders', 'kitchen', 'global'],
        });

        if (!eventHandlersSetup) {
            setupEventHandlers();
            eventHandlersSetup = true;
        }

        log.info('Initialized with restaurant:', restaurantId);
    }

    function setupEventHandlers() {
        const realtimeStore = getRealtimeStore();

        realtimeStore.on('delivery_new', ((data: Record<string, any>) => {
            log.debug('[Realtime] delivery_new:', data);
            playNotificationSound('new');
            showToast('Новый заказ на доставку!', 'info');
            debouncedLoadAvailableOrders();
        }) as any);

        realtimeStore.on('delivery_status', ((data: Record<string, any>) => {
            log.debug('[Realtime] delivery_status:', data);
            debouncedLoadData();
        }) as any);

        realtimeStore.on('courier_assigned', ((data: Record<string, any>) => {
            log.debug('[Realtime] courier_assigned:', data);
            if (data.courier_id === courierId.value) {
                playNotificationSound('assigned');
                showToast('Вам назначен новый заказ!', 'success');
            }
            debouncedLoadData();
        }) as any);

        realtimeStore.on('delivery_problem_created', ((data: Record<string, any>) => {
            log.debug('[Realtime] delivery_problem_created:', data);
            showToast(`Проблема с доставкой: ${data.problem_type || 'неизвестная'}`, 'error');
            debouncedLoadData();
        }) as any);

        realtimeStore.on('delivery_problem_resolved', ((data: Record<string, any>) => {
            log.debug('[Realtime] delivery_problem_resolved:', data);
            showToast('Проблема с доставкой решена', 'success');
            debouncedLoadData();
        }) as any);

        realtimeStore.on('order_status', ((data: Record<string, any>) => {
            log.debug('[Realtime] order_status:', data);
            if (data.new_status === 'ready') {
                const ourOrder = myOrders.value.find((o: any) => o.id === data.order_id);
                if (ourOrder) {
                    playNotificationSound('ready');
                    showToast(`Заказ #${data.order_number || ourOrder.order_number} готов к выдаче!`, 'success');
                }
            }
            debouncedLoadData();
        }) as any);

        realtimeStore.on('kitchen_ready', ((data: Record<string, any>) => {
            log.debug('[Realtime] kitchen_ready:', data);
            const ourKitchenOrder = myOrders.value.find((o: any) => o.id === data.order_id);
            if (ourKitchenOrder) {
                playNotificationSound('ready');
                showToast(`Заказ #${data.order_number || ourKitchenOrder.order_number} готов к выдаче!`, 'success');
            }
            debouncedLoadData();
        }) as any);

        realtimeStore.on('stop_list_changed', ((data: Record<string, any>) => {
            log.debug('[Realtime] stop_list_changed:', data);
            showToast('Стоп-лист обновлён', 'info');
        }) as any);

        log.info('Event handlers set up');
    }

    function disconnectReverb() {
        debouncedLoadData.cancel();
        debouncedLoadAvailableOrders.cancel();

        const realtimeStore = getRealtimeStore();
        realtimeStore.destroy();
        eventHandlersSetup = false;
    }

    function playNotificationSound(type: string = 'new') {
        const soundMap: Record<string, string> = {
            new: 'newOrder',
            ready: 'ready',
            assigned: 'courierAssigned',
        };
        playSound(soundMap[type] || 'beep');
    }

    // Toast
    function showToast(message: string, type: string = 'info') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    // Formatters
    function formatMoney(amount: number): string {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 0
        }).format(amount || 0);
    }

    function formatAddress(order: DeliveryOrder): string {
        const parts = [order.address_street];
        if (order.address_house) parts.push(order.address_house);
        return parts.filter(Boolean).join(', ');
    }

    function formatFullAddress(order: DeliveryOrder): string {
        const parts = [order.address_street];
        if (order.address_house) parts.push(`д. ${order.address_house}`);
        if (order.address_apartment) parts.push(`кв. ${order.address_apartment}`);
        if (order.address_entrance) parts.push(`подъезд ${order.address_entrance}`);
        if (order.address_floor) parts.push(`этаж ${order.address_floor}`);
        if (order.address_intercom) parts.push(`домофон ${order.address_intercom}`);
        return parts.filter(Boolean).join(', ');
    }

    function formatPaymentMethod(method: string): string {
        const methods: Record<string, string> = {
            'cash': 'Наличные',
            'card': 'Картой при получении',
            'online': 'Оплачен онлайн'
        };
        return methods[method] || method;
    }

    function formatTime(datetime: string | null): string {
        if (!datetime) return '';
        return new Date(datetime).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function getStatusClass(status: string): string {
        const classes: Record<string, string> = {
            'new': 'bg-blue-500',
            'cooking': 'bg-yellow-500',
            'ready': 'bg-green-500',
            'picked_up': 'bg-purple-500',
            'in_transit': 'bg-purple-500',
            'delivering': 'bg-purple-500',
            'completed': 'bg-gray-500',
            'cancelled': 'bg-red-500'
        };
        return classes[status] || 'bg-gray-500';
    }

    function getStatusLabel(status: string): string {
        const labels: Record<string, string> = {
            'new': 'Новый',
            'cooking': 'Готовится',
            'ready': 'Готов',
            'picked_up': 'Забран',
            'in_transit': 'В пути',
            'delivering': 'В пути',
            'completed': 'Доставлен',
            'cancelled': 'Отменён'
        };
        return labels[status] || status;
    }

    return {
        isAuthenticated, user, courierId, isLoading, activeTab, myOrders, availableOrders,
        selectedOrder, courierStatus, isOnline, stats, notificationPermission, geoEnabled,
        toast, reverbConnected,
        activeOrders, userInitials, headerTitle,
        login, logout, checkAuth,
        loadData, loadMyOrders, loadAvailableOrders,
        acceptOrder, updateOrderStatus, cancelOrder,
        toggleStatus, startLocationTracking, stopLocationTracking, connectSSE, disconnectSSE,
        showToast, formatMoney, formatAddress, formatFullAddress, formatPaymentMethod,
        formatTime, getStatusClass, getStatusLabel
    };
});
