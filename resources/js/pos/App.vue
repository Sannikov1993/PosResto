<template>
    <div class="h-full" data-testid="pos-app">
        <!-- Loading state while checking session -->
        <div v-if="isInitializing" class="h-full flex items-center justify-center bg-dark-950 loading-screen" data-testid="pos-loading">
            <div class="text-center">
                <!-- Logo with pulse animation -->
                <div class="logo-container">
                    <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-16 h-16 mx-auto logo-pulse" />
                    <div class="logo-ring"></div>
                </div>
                <!-- Animated dots -->
                <div class="loading-dots mt-6">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Login Screen -->
        <LoginScreen v-else-if="sessionState === 'logged_out'" @login="handleLogin" />

        <!-- Post-login loading (data + navigation initializing) -->
        <div v-else-if="!navigationStore.initialized" class="h-full flex items-center justify-center bg-dark-950 loading-screen" data-testid="pos-post-login-loading">
            <div class="text-center">
                <div class="logo-container">
                    <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-16 h-16 mx-auto logo-pulse" />
                    <div class="logo-ring"></div>
                </div>
                <p class="text-gray-400 mt-6">Загрузка данных...</p>
            </div>
        </div>

        <!-- Main App (ACTIVE или LOCKED — POS остаётся в DOM) -->
        <div v-else class="h-full flex" data-testid="pos-main">
            <!-- Sidebar -->
            <Sidebar
                :user="user"
                :active-tab="activeTab"
                :current-shift="currentShift"
                :auth-token="authToken"
                :pending-cancellations-count="pendingCancellationsCount"
                :pending-delivery-count="pendingDeliveryCount"
                :restaurants="authStore.restaurants"
                :current-restaurant="authStore.currentRestaurant"
                :has-multiple-restaurants="authStore.hasMultipleRestaurants"
                @change-tab="changeTab"
                @logout="handleLogout"
                @lock="handleManualLock"
                @switch-restaurant="handleSwitchRestaurant"
                @open-shift="handleOpenShift"
            />

            <!-- Content -->
            <div class="flex-1 overflow-hidden flex flex-col">
                <!-- Connection Status Banner -->
                <Transition name="connection-banner">
                    <div
                        v-if="!isOnline"
                        class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 shrink-0"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0M12 9v4m0 4h.01" /></svg>
                        Нет подключения к интернету
                    </div>
                    <div
                        v-else-if="!realtimeStore.connected && realtimeStore.reconnectCount > 0 && realtimeStore.connecting"
                        class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-yellow-600 shrink-0"
                    >
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                        Переподключение к серверу...
                    </div>
                    <div
                        v-else-if="!realtimeStore.connected && !realtimeStore.connecting"
                        class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 shrink-0"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Нет соединения с сервером
                        <button
                            class="ml-2 px-3 py-0.5 text-xs font-semibold bg-white/20 hover:bg-white/30 rounded transition-colors"
                            @click="realtimeStore.reconnect()"
                        >
                            Переподключить
                        </button>
                    </div>
                </Transition>

                <CashTab v-if="activeTab === 'cash'" />
                <OrdersTab
                    v-else-if="activeTab === 'orders'"
                    :has-bar="hasBar"
                    :bar-items-count="barItemsCount"
                    @open-bar="barPanelOpen = true"
                />
                <DeliveryTab v-else-if="activeTab === 'delivery'" />
                <CustomersTab v-else-if="activeTab === 'customers'" />
                <WarehouseTab v-else-if="activeTab === 'warehouse'" />
                <StopListTab v-else-if="activeTab === 'stoplist'" />
                <WriteOffsTab v-else-if="activeTab === 'writeoffs'" />
                <SettingsTab v-else-if="activeTab === 'settings'" />
            </div>
        </div>

        <!-- Bar Panel -->
        <BarPanel
            v-if="hasBar"
            :is-open="barPanelOpen"
            @close="barPanelOpen = false"
            @update:count="barItemsCount = $event"
        />

        <!-- Global Modals -->
        <OrderModal />
        <PaymentModal />
        <ReservationModal />
        <OpenShiftModal v-model:show="showOpenShiftModal" />

        <!-- Toast Notifications -->
        <ToastContainer />

        <!-- Lock Screen (overlay поверх POS, не уничтожает его) -->
        <LockScreen
            v-if="sessionState === 'locked'"
            :locked-by-user="authStore.lockedByUser"
            @unlock="handleUnlock"
            @user-switch="handleUserSwitch"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { usePosStore } from './stores/pos';
import { useAuthStore } from './stores/auth';
import { getCurrentTime } from '../utils/timezone';
import { throttle } from '../shared/config/realtimeConfig.js';

// Components
import LoginScreen from './components/LoginScreen.vue';
import Sidebar from './components/Sidebar.vue';
import ToastContainer from './components/ui/ToastContainer.vue';
import LockScreen from './components/LockScreen.vue';

// Tabs — sync (core, shown first)
import CashTab from './components/tabs/CashTab.vue';
import OrdersTab from './components/tabs/OrdersTab.vue';

// Tabs — lazy-loaded (rarely used, loaded on first switch)
const DeliveryTab = defineAsyncComponent(() => import('./components/tabs/DeliveryTab.vue'));
const CustomersTab = defineAsyncComponent(() => import('./components/tabs/CustomersTab.vue'));
const WarehouseTab = defineAsyncComponent(() => import('./components/tabs/WarehouseTab.vue'));
const StopListTab = defineAsyncComponent(() => import('./components/tabs/StopListTab.vue'));
const WriteOffsTab = defineAsyncComponent(() => import('./components/tabs/WriteOffsTab.vue'));
const SettingsTab = defineAsyncComponent(() => import('./components/tabs/SettingsTab.vue'));

// Modals — sync (called immediately after actions)
import PaymentModal from './components/modals/PaymentModal.vue';
import OpenShiftModal from './components/modals/OpenShiftModal.vue';

// Modals — lazy-loaded (opened less frequently)
const OrderModal = defineAsyncComponent(() => import('./components/modals/OrderModal.vue'));
const ReservationModal = defineAsyncComponent(() => import('./components/modals/ReservationModal.vue'));

// Bar Panel
import BarPanel from './components/BarPanel.vue';

// Composables
import { useRealtimeStore } from '../shared/stores/realtime.js';
import { useRealtimeOrderEvents } from './composables/useRealtimeOrderEvents.js';
import { useRealtimeDeliveryEvents } from './composables/useRealtimeDeliveryEvents.js';
import { useRealtimeFinanceEvents } from './composables/useRealtimeFinanceEvents.js';
import { useRealtimeBarEvents } from './composables/useRealtimeBarEvents.js';
import { useRealtimeMiscEvents } from './composables/useRealtimeMiscEvents.js';
import { useSessionLifecycle } from './composables/useSessionLifecycle.js';
import { createLogger } from '../shared/services/logger.js';

// Enterprise Navigation Store
import { useNavigationStore } from '../shared/stores/navigation.js';

const log = createLogger('POS:App');

// Stores
const posStore = usePosStore();
const authStore = useAuthStore();
const realtimeStore = useRealtimeStore();
const navigationStore = useNavigationStore();

// Session lifecycle (idle, polling, bar, logout broadcast)
const {
    startIdleService, stopIdleService, resetIdleTimer,
    initLogoutBroadcast, notifyLogout,
    startPolling, stopPolling,
    hasBar, barItemsCount, checkBar, refreshBarCount,
    cleanupAll,
} = useSessionLifecycle();

// Connection status (browser online/offline)
const isOnline = ref(navigator.onLine);
const handleOnline = () => { isOnline.value = true; };
const handleOffline = () => { isOnline.value = false; };

// State - activeTab теперь управляется через NavigationStore
const activeTab = computed(() => navigationStore.activeTab);
const sessionState = computed(() => authStore.sessionState);
// If coming from payment (overlay exists), skip loading state - overlay will cover
const hasPaymentOverlay = !!document.getElementById('payment-success-overlay');
const isInitializing = ref(!hasPaymentOverlay); // Skip if overlay present

// Bar panel open/close state (hasBar и barItemsCount из useSessionLifecycle)
const barPanelOpen = ref(false);

// Open shift modal (triggered from sidebar when shift is closed)
const showOpenShiftModal = ref(false);

const handleOpenShift = () => {
    showOpenShiftModal.value = true;
};

// Computed
const isLoggedIn = computed(() => authStore.isLoggedIn);
const user = computed(() => authStore.user);
const authToken = computed(() => authStore.token);
const currentShift = computed(() => posStore.currentShift);
const pendingCancellationsCount = computed(() => posStore.pendingCancellationsCount);
const pendingDeliveryCount = computed(() => posStore.pendingDeliveryCount);

// ==========================================
// Lock Screen Handlers
// ==========================================
const handleManualLock = () => {
    authStore.lockScreen();
};

const handleUnlock = () => {
    authStore.unlockScreen();
    resetIdleTimer();
};

const handleUserSwitch = async (data) => {
    authStore.switchUser(data);
    // Перезагружаем данные POS для нового пользователя
    await Promise.all([
        authStore.loadTenant(),
        authStore.loadRestaurants()
    ]);
    navigationStore.updateContext({
        restaurantId: authStore.currentRestaurant?.id,
        permissions: authStore.user?.permissions || [],
        features: authStore.currentRestaurant?.features || [],
    });
    await loadInitialData();
    resetIdleTimer();
};

// Guard: prevents double-execution of post-login init
let _postLoginRunning = false;

// Methods
const handleLogin = async (userData) => {
    // Guard against double-call (watch + @login event may both trigger)
    if (_postLoginRunning) return;
    _postLoginRunning = true;

    try {
        // Load tenant and restaurants
        await Promise.all([
            authStore.loadTenant(),
            authStore.loadRestaurants()
        ]);

        // Инициализируем навигацию СРАЗУ после loadRestaurants (нужен currentRestaurant)
        navigationStore.init({
            restaurantId: authStore.currentRestaurant?.id,
            permissions: authStore.user?.permissions || [],
            features: authStore.currentRestaurant?.features || [],
        });

        await loadInitialData();
        await checkBar();

        startIdleService(() => authStore.lockScreen());
        initLogoutBroadcast(() => {
            authStore.handleUnauthorized();
            cleanupAfterLogout();
        });
        setupRealtimeEvents();
        startPolling(hasBar.value, () => isLoggedIn.value);
    } finally {
        _postLoginRunning = false;
    }
};

// Watch: реагируем на isLoggedIn напрямую, не полагаясь на @login emit из LoginScreen.
// LoginScreen демонтируется при isLoggedIn=true раньше, чем успевает emit('login'),
// поэтому watch — единственный надёжный способ запустить post-login инициализацию.
watch(isLoggedIn, (newVal) => {
    // Не запускаем при начальной загрузке (F5) — там onMounted сам обрабатывает restoreSession
    if (newVal && !navigationStore.initialized && !isInitializing.value) {
        handleLogin(authStore.user);
    }
});

function cleanupAfterLogout() {
    _postLoginRunning = false;
    cleanupAll();
}

const handleLogout = () => {
    notifyLogout();
    cleanupAfterLogout();
    authStore.logout();
};

const changeTab = (tabId) => {
    navigationStore.navigateTo(tabId);
};

const handleSwitchRestaurant = async (restaurantId) => {
    const result = await authStore.switchRestaurant(restaurantId);
    if (result.success) {
        // Update navigation context for new restaurant
        navigationStore.updateContext({
            restaurantId: restaurantId,
            permissions: authStore.user?.permissions || [],
            features: authStore.currentRestaurant?.features || [],
        });

        // Переподключаем WebSocket на новый ресторан
        realtimeStore.destroy();
        setupRealtimeEvents();

        // Reload all data for new restaurant
        await loadInitialData();
    }
};

const loadInitialData = async () => {
    await posStore.loadInitialData();
};

// ==========================================
// Real-time Events (Domain Composables)
// ==========================================
const setupRealtimeEvents = () => {
    const restaurantId = authStore.user?.restaurant_id;

    if (!restaurantId) {
        log.warn('No restaurant_id, skipping realtime connection');
        return;
    }

    // Initialize centralized real-time store (one WebSocket per app)
    realtimeStore.init(restaurantId, {
        channels: ['orders', 'kitchen', 'delivery', 'tables', 'reservations', 'bar', 'cash', 'global'],
    });

    // Domain composables (auto-cleanup on unmount via useRealtimeEvents)
    useRealtimeOrderEvents().setup();
    useRealtimeDeliveryEvents().setup();
    useRealtimeFinanceEvents().setup();
    useRealtimeBarEvents({ refreshBarCount }).setup();
    useRealtimeMiscEvents().setup();
};

// Слушатель для мгновенного обновления бара при подаче блюд
const handleBarStorageChange = (e) => {
    if (e.key === 'bar_refresh' && hasBar.value) {
        refreshBarCount();
    }
};

// Обработка 401 от API interceptor
const handleSessionExpired = () => {
    authStore.handleUnauthorized();
    cleanupAfterLogout();
};

// Restore saved tab
// Helper to remove payment overlay with fade out
const removePaymentOverlay = () => {
    const overlay = document.getElementById('payment-success-overlay');
    if (overlay) {
        // Fade out animation
        overlay.style.transition = 'opacity 0.3s ease-out';
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
};

onMounted(() => {
    // Connection status listeners
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Слушаем storage событие для обновления бара
    window.addEventListener('storage', handleBarStorageChange);

    // Слушаем 401 от API interceptor
    window.addEventListener('auth:session-expired', handleSessionExpired);

    // Обновление данных при возврате на вкладку браузера
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Check for existing session
    authStore.restoreSession().then(async restored => {
        // Remove payment overlay AFTER session check completes
        removePaymentOverlay();

        if (restored) {
            // Load tenant and restaurants
            await Promise.all([
                authStore.loadTenant(),
                authStore.loadRestaurants()
            ]);

            // Инициализируем навигацию СРАЗУ после loadRestaurants (нужен currentRestaurant)
            // Не ждём loadInitialData/checkBar — иначе Sidebar рендерится с пустым context
            navigationStore.init({
                restaurantId: authStore.currentRestaurant?.id,
                permissions: authStore.user?.permissions || [],
                features: authStore.currentRestaurant?.features || [],
            });

            await loadInitialData();
            await checkBar();

            startIdleService(() => authStore.lockScreen());
            initLogoutBroadcast(() => {
                authStore.handleUnauthorized();
                cleanupAfterLogout();
            });
            setupRealtimeEvents();
            startPolling(hasBar.value, () => isLoggedIn.value);
        }
        // Session check complete - show appropriate screen
        isInitializing.value = false;
    }).catch(() => {
        // Remove overlay even on error
        removePaymentOverlay();
        isInitializing.value = false;
    });
});

// Update time
const time = ref('');
let timeInterval = null;

onMounted(() => {
    // Обновляем время сразу (timezone будет установлен из store после loadInitialData)
    time.value = getCurrentTime();

    // Запускаем интервал для обновления времени
    timeInterval = setInterval(() => {
        time.value = getCurrentTime();
    }, 1000);
});

// Обновление данных при возврате на вкладку браузера
// (гарантирует свежие данные, даже если WebSocket-событие не дошло)
const handleVisibilityChange = throttle(async () => {
    if (document.hidden || !isLoggedIn.value) return;
    log.debug('Tab visible — refreshing stop list, orders, shift');
    const results = await Promise.allSettled([
        posStore.loadStopList(),
        posStore.loadActiveOrders(),
        posStore.loadCurrentShift(),
    ]);
    results.forEach((r, i) => {
        if (r.status === 'rejected') {
            log.warn(`Visibility refresh [${i}] failed:`, r.reason?.message || r.reason);
        }
    });
}, 5000);

// Очищаем интервал при размонтировании компонента
onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    cleanupAll();

    if (timeInterval) {
        clearInterval(timeInterval);
        timeInterval = null;
    }
    window.removeEventListener('storage', handleBarStorageChange);
    window.removeEventListener('auth:session-expired', handleSessionExpired);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});
</script>

<style scoped>
/* Loading screen animations */
.loading-screen {
    background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 100%);
}

.logo-container {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.logo-pulse {
    position: relative;
    z-index: 2;
    animation: logoPulse 2s ease-in-out infinite;
}

.logo-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 64px;
    height: 64px;
    border: 2px solid rgba(139, 92, 246, 0.3);
    border-radius: 50%;
    animation: ringExpand 2s ease-out infinite;
}

@keyframes logoPulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.05);
        opacity: 0.8;
    }
}

@keyframes ringExpand {
    0% {
        width: 64px;
        height: 64px;
        opacity: 0.6;
    }
    100% {
        width: 120px;
        height: 120px;
        opacity: 0;
    }
}

/* Loading dots */
.loading-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 50%;
    animation: dotBounce 1.4s ease-in-out infinite;
}

.loading-dots span:nth-child(1) {
    animation-delay: 0s;
}

.loading-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.loading-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes dotBounce {
    0%, 80%, 100% {
        transform: scale(0.6);
        opacity: 0.4;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Connection status banner transition */
.connection-banner-enter-active,
.connection-banner-leave-active {
    transition: all 0.3s ease;
}

.connection-banner-enter-from,
.connection-banner-leave-to {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    opacity: 0;
    overflow: hidden;
}

.connection-banner-enter-to,
.connection-banner-leave-from {
    max-height: 40px;
    opacity: 1;
}
</style>
