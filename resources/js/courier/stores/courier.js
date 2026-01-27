import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const API_BASE = '/api';

export const useCourierStore = defineStore('courier', () => {
    // Auth state
    const isAuthenticated = ref(false);
    const user = ref(null);
    const courierId = ref(null);
    const token = ref('');
    const isLoading = ref(false);

    // App state
    const activeTab = ref('orders');
    const myOrders = ref([]);
    const availableOrders = ref([]);
    const selectedOrder = ref(null);
    const courierStatus = ref('available');
    const isOnline = ref(navigator.onLine);

    // Stats
    const stats = ref({
        todayOrders: 0,
        todayEarnings: 0,
        avgDeliveryTime: 0
    });

    // PWA/Features
    const notificationPermission = ref(Notification?.permission || 'default');
    const geoEnabled = ref(false);

    // Toast
    const toast = ref(null);

    // SSE/Geo
    let sseConnection = null;
    let geoWatchId = null;

    // Computed
    const activeOrders = computed(() => {
        return myOrders.value.filter(o =>
            !['completed', 'cancelled'].includes(o.delivery_status)
        );
    });

    const userInitials = computed(() => {
        if (!user.value?.name) return 'К';
        return user.value.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    });

    const headerTitle = computed(() => {
        switch (activeTab.value) {
            case 'orders': return 'Мои заказы';
            case 'available': return 'Доступные';
            case 'profile': return 'Профиль';
            default: return 'PosResto Курьер';
        }
    });

    // API helper
    async function api(endpoint, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token.value) {
            headers['Authorization'] = `Bearer ${token.value}`;
        }

        const response = await axios({
            url: API_BASE + endpoint,
            method: options.method || 'GET',
            headers,
            data: options.body ? JSON.parse(options.body) : undefined
        });

        return response.data;
    }

    // Auth methods
    async function login(pin) {
        isLoading.value = true;
        try {
            const response = await api('/auth/login-pin', {
                method: 'POST',
                body: JSON.stringify({ pin })
            });

            if (response.success) {
                token.value = response.data.token;
                user.value = response.data.user;
                courierId.value = response.data.courier_id || response.data.user.id;
                isAuthenticated.value = true;

                localStorage.setItem('courier_token', token.value);
                localStorage.setItem('courier_user', JSON.stringify(user.value));
                localStorage.setItem('courier_id', courierId.value);

                await loadData();
                startLocationTracking();
                connectSSE();

                return { success: true };
            }
        } catch (error) {
            return { success: false, message: error.response?.data?.message || 'Неверный PIN-код' };
        } finally {
            isLoading.value = false;
        }
    }

    async function logout() {
        try {
            await api('/auth/logout', { method: 'POST' });
        } catch (e) {}

        localStorage.removeItem('courier_token');
        localStorage.removeItem('courier_user');
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

    function checkAuth() {
        const savedToken = localStorage.getItem('courier_token');
        const savedUser = localStorage.getItem('courier_user');
        const savedCourierId = localStorage.getItem('courier_id');

        if (savedToken && savedUser) {
            token.value = savedToken;
            user.value = JSON.parse(savedUser);
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
        } catch (error) {
            showToast('Ошибка загрузки данных', 'error');
        } finally {
            isLoading.value = false;
        }
    }

    async function loadMyOrders() {
        try {
            const response = await api(`/delivery/orders?courier_id=${courierId.value}&today=true`);
            if (response.success) {
                myOrders.value = response.data || [];
            }
        } catch (error) {
            console.error('Failed to load my orders:', error);
        }
    }

    async function loadAvailableOrders() {
        try {
            const response = await api('/delivery/orders?delivery_status=pending,preparing,ready&today=true&no_courier=true');
            if (response.success) {
                availableOrders.value = (response.data || []).filter(o => !o.courier_id);
            }
        } catch (error) {
            console.error('Failed to load available orders:', error);
        }
    }

    async function loadStats() {
        try {
            const response = await api(`/delivery/couriers/${courierId.value}`);
            if (response.success && response.data) {
                stats.value = {
                    todayOrders: response.data.today_orders || 0,
                    todayEarnings: response.data.today_earnings || 0,
                    avgDeliveryTime: response.data.avg_delivery_time || 25
                };
            }
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    }

    // Order actions
    async function acceptOrder(order) {
        isLoading.value = true;
        try {
            await api(`/delivery/orders/${order.id}/assign-courier`, {
                method: 'POST',
                body: JSON.stringify({ courier_id: courierId.value })
            });

            showToast('Заказ принят', 'success');
            await loadData();
            selectedOrder.value = null;
            activeTab.value = 'orders';
            return { success: true };
        } catch (error) {
            showToast(error.response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function updateOrderStatus(order, status) {
        isLoading.value = true;
        try {
            await api(`/delivery/orders/${order.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ delivery_status: status })
            });

            const statusLabels = {
                'picked_up': 'Заказ забран',
                'in_transit': 'В пути',
                'completed': 'Заказ доставлен'
            };

            showToast(statusLabels[status] || 'Статус обновлен', 'success');
            await loadData();

            if (status === 'completed') {
                selectedOrder.value = null;
            } else {
                selectedOrder.value = myOrders.value.find(o => o.id === order.id);
            }
            return { success: true };
        } catch (error) {
            showToast(error.response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function cancelOrder(order, reason) {
        isLoading.value = true;
        try {
            await api(`/delivery/orders/${order.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({
                    delivery_status: 'cancelled',
                    cancel_reason: reason
                })
            });

            showToast('Заказ отменен', 'success');
            await loadData();
            selectedOrder.value = null;
            return { success: true };
        } catch (error) {
            showToast(error.response?.data?.message || 'Ошибка', 'error');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    // Courier status
    async function toggleStatus() {
        const newStatus = courierStatus.value === 'available' ? 'offline' : 'available';

        try {
            await api(`/delivery/couriers/${courierId.value}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: newStatus })
            });

            courierStatus.value = newStatus;
            showToast(newStatus === 'available' ? 'Вы онлайн' : 'Вы оффлайн', 'info');
        } catch (error) {
            showToast('Ошибка смены статуса', 'error');
        }
    }


    // Geolocation - улучшенный трекинг для live-отслеживания
    let lastLocationSent = 0;
    const MIN_LOCATION_INTERVAL = 5000; // Минимум 5 секунд между отправками

    function startLocationTracking() {
        if (!navigator.geolocation) return;

        geoEnabled.value = true;

        const geoOptions = {
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
            (err) => console.warn('Geolocation error:', err),
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
            (err) => console.warn('Geolocation watch error:', err),
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

    async function sendLocation(lat, lng, accuracy = null, speed = null, heading = null) {
        const now = Date.now();
        if (now - lastLocationSent < MIN_LOCATION_INTERVAL) {
            return;
        }
        lastLocationSent = now;

        try {
            await api('/courier/location', {
                method: 'POST',
                body: JSON.stringify({
                    latitude: lat,
                    longitude: lng,
                    accuracy: accuracy,
                    speed: speed,
                    heading: heading
                })
            });
        } catch (error) {
            try {
                await api(`/delivery/couriers/${courierId.value}/status`, {
                    method: 'PATCH',
                    body: JSON.stringify({ location: { lat, lng } })
                });
            } catch (fallbackError) {
                console.warn('Failed to send location:', fallbackError);
            }
        }
    }

    // SSE
    function connectSSE() {
        if (sseConnection) return;

        try {
            sseConnection = new EventSource(`${API_BASE}/realtime/stream?channels=delivery&token=${token.value}`);

            sseConnection.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    handleRealtimeEvent(data);
                } catch (e) {}
            };

            sseConnection.onerror = () => {
                disconnectSSE();
                setTimeout(connectSSE, 5000);
            };
        } catch (error) {
            console.warn('SSE connection failed:', error);
        }
    }

    function disconnectSSE() {
        if (sseConnection) {
            sseConnection.close();
            sseConnection = null;
        }
    }

    function handleRealtimeEvent(data) {
        switch (data.event) {
            case 'delivery_new':
            case 'delivery_status_changed':
            case 'courier_assigned':
                loadData();
                break;
        }
    }

    // Toast
    function showToast(message, type = 'info') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    // Formatters
    function formatMoney(amount) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 0
        }).format(amount || 0);
    }

    function formatAddress(order) {
        const parts = [order.address_street];
        if (order.address_house) parts.push(order.address_house);
        return parts.join(', ');
    }

    function formatFullAddress(order) {
        const parts = [order.address_street];
        if (order.address_house) parts.push(`д. ${order.address_house}`);
        if (order.address_apartment) parts.push(`кв. ${order.address_apartment}`);
        if (order.address_entrance) parts.push(`подъезд ${order.address_entrance}`);
        if (order.address_floor) parts.push(`этаж ${order.address_floor}`);
        if (order.address_intercom) parts.push(`домофон ${order.address_intercom}`);
        return parts.join(', ');
    }

    function formatPaymentMethod(method) {
        const methods = {
            'cash': 'Наличные',
            'card': 'Картой при получении',
            'online': 'Оплачен онлайн'
        };
        return methods[method] || method;
    }

    function formatTime(datetime) {
        if (!datetime) return '';
        return new Date(datetime).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function getStatusClass(status) {
        const classes = {
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

    function getStatusLabel(status) {
        const labels = {
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
        // State
        isAuthenticated,
        user,
        courierId,
        isLoading,
        activeTab,
        myOrders,
        availableOrders,
        selectedOrder,
        courierStatus,
        isOnline,
        stats,
        notificationPermission,
        geoEnabled,
        toast,

        // Computed
        activeOrders,
        userInitials,
        headerTitle,

        // Auth
        login,
        logout,
        checkAuth,

        // Data
        loadData,
        loadMyOrders,
        loadAvailableOrders,

        // Orders
        acceptOrder,
        updateOrderStatus,
        cancelOrder,

        // Courier
        toggleStatus,
        startLocationTracking,
        stopLocationTracking,
        connectSSE,
        disconnectSSE,

        // Utils
        showToast,
        formatMoney,
        formatAddress,
        formatFullAddress,
        formatPaymentMethod,
        formatTime,
        getStatusClass,
        getStatusLabel
    };
});
