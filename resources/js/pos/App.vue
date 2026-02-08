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
            <div class="flex-1 overflow-hidden">
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePosStore } from './stores/pos';
import { useAuthStore } from './stores/auth';
import { getCurrentTime, setTimezone } from '../utils/timezone';

// Components
import LoginScreen from './components/LoginScreen.vue';
import Sidebar from './components/Sidebar.vue';
import ToastContainer from './components/ui/ToastContainer.vue';
import LockScreen from './components/LockScreen.vue';

// Tabs
import CashTab from './components/tabs/CashTab.vue';
import OrdersTab from './components/tabs/OrdersTab.vue';
import DeliveryTab from './components/tabs/DeliveryTab.vue';
import CustomersTab from './components/tabs/CustomersTab.vue';
import WarehouseTab from './components/tabs/WarehouseTab.vue';
import StopListTab from './components/tabs/StopListTab.vue';
import WriteOffsTab from './components/tabs/WriteOffsTab.vue';
import SettingsTab from './components/tabs/SettingsTab.vue';

// Modals
import OrderModal from './components/modals/OrderModal.vue';
import PaymentModal from './components/modals/PaymentModal.vue';
import ReservationModal from './components/modals/ReservationModal.vue';
import OpenShiftModal from './components/modals/OpenShiftModal.vue';

// Bar Panel
import BarPanel from './components/BarPanel.vue';
import api from './api';

// Services — Device-Session модель
import { IdleService } from './services/IdleService';
import { LogoutBroadcast } from './services/LogoutBroadcast';

// Composables - Enterprise Real-time Architecture
import { useRealtimeStore } from '../shared/stores/realtime.js';
import { useRealtimeEvents } from '../shared/composables/useRealtimeEvents.js';
import { playSound } from '../shared/services/notificationSound.js';

// Enterprise Navigation Store
import { useNavigationStore } from '../shared/stores/navigation.js';

// Stores
const posStore = usePosStore();
const authStore = useAuthStore();

// Enterprise Real-time Store (singleton)
const realtimeStore = useRealtimeStore();

// Enterprise Navigation Store (singleton)
const navigationStore = useNavigationStore();

// State - activeTab теперь управляется через NavigationStore
const activeTab = computed(() => navigationStore.activeTab);
const sessionState = computed(() => authStore.sessionState);
// If coming from payment (overlay exists), skip loading state - overlay will cover
const hasPaymentOverlay = !!document.getElementById('payment-success-overlay');
const isInitializing = ref(!hasPaymentOverlay); // Skip if overlay present

// Bar state
const hasBar = ref(false);
const barPanelOpen = ref(false);
const barItemsCount = ref(0);

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
// Device-Session Services
// ==========================================
let idleService = null;
let logoutBroadcast = null;

function startIdleService() {
    if (idleService) idleService.stop();
    idleService = new IdleService({
        idleTimeout: 30 * 60 * 1000, // 30 минут
        onIdle: () => {
            authStore.lockScreen();
        },
    });
    idleService.start();
}

function stopIdleService() {
    if (idleService) {
        idleService.stop();
        idleService = null;
    }
}

function initLogoutBroadcast() {
    if (logoutBroadcast) logoutBroadcast.destroy();
    logoutBroadcast = new LogoutBroadcast();
    logoutBroadcast.onLogout(() => {
        authStore.handleUnauthorized();
        cleanupAfterLogout();
    });
}

// ==========================================
// Lock Screen Handlers
// ==========================================
const handleManualLock = () => {
    authStore.lockScreen();
};

const handleUnlock = () => {
    authStore.unlockScreen();
    // Перезапускаем IdleService
    if (idleService) idleService.resetTimer();
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
    // Перезапускаем IdleService
    if (idleService) idleService.resetTimer();
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

        // Запускаем IdleService
        startIdleService();

        // Инициализируем кросс-табовый логаут
        initLogoutBroadcast();

        // Подключаем SSE для real-time обновлений
        setupRealtimeEvents();

        // Fallback polling (с увеличенным интервалом, т.к. основной источник — SSE)
        if (!deliveryRefreshInterval) {
            deliveryRefreshInterval = setInterval(refreshDeliveryCount, 60000);
        }
        if (!barRefreshInterval && hasBar.value) {
            barRefreshInterval = setInterval(refreshBarCount, 30000);
        }
        if (!cashRefreshInterval) {
            cashRefreshInterval = setInterval(refreshCashData, 60000);
        }
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
    // Сбрасываем guard post-login инициализации
    _postLoginRunning = false;

    // Останавливаем IdleService
    stopIdleService();

    // Отключаем real-time (centralized store)
    realtimeStore.destroy();

    // Reset navigation state
    navigationStore.reset();

    // Clear intervals
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

const handleLogout = () => {
    // Уведомляем другие вкладки о логауте
    if (logoutBroadcast) logoutBroadcast.notifyLogout();

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
        // Reload all data for new restaurant
        await loadInitialData();
    }
};

const loadInitialData = async () => {
    await posStore.loadInitialData();
};

// ==========================================
// Notification Sounds (using shared service)
// ==========================================
const playNotificationSound = (type = 'ready') => {
    const soundMap = {
        ready: 'ready',
        new_order: 'newOrder',
        alert: 'alert',
    };
    playSound(soundMap[type] || 'beep');
};

// ==========================================
// Real-time Events (Enterprise Architecture)
// ==========================================
const setupRealtimeEvents = () => {
    // Получаем restaurant_id из auth store
    const restaurantId = authStore.currentRestaurant?.id || authStore.user?.restaurant_id;

    if (!restaurantId) {
        console.warn('[POS] No restaurant_id, skipping realtime connection');
        return;
    }

    // Initialize centralized real-time store (one WebSocket per app)
    realtimeStore.init(restaurantId, {
        channels: ['orders', 'kitchen', 'delivery', 'tables', 'reservations', 'bar', 'cash', 'global'],
    });

    // Use composable for event subscriptions (auto-cleanup on unmount)
    const { on } = useRealtimeEvents();

    // ===== Заказы =====
    on('new_order', async (data) => {
        console.log('[Realtime] new_order:', data);
        await posStore.loadActiveOrders();
        if (data.type === 'delivery') {
            await posStore.loadDeliveryOrders();
            window.$toast?.('Новый заказ на доставку', 'info');
            playNotificationSound('new_order');
        }
    });

    on('order_status', async (data) => {
        console.log('[Realtime] order_status:', data);
        await posStore.loadActiveOrders();
    });

    on('order_updated', async (data) => {
        console.log('[Realtime] order_updated:', data);
        await posStore.loadActiveOrders();
    });

    on('order_paid', async (data) => {
        console.log('[Realtime] order_paid:', data);
        await Promise.all([
            posStore.loadCurrentShift(),
            posStore.loadShifts(),
            posStore.loadPaidOrders(),
            posStore.loadActiveOrders()
        ]);
    });

    on('order_transferred', async (data) => {
        console.log('[Realtime] order_transferred:', data);
        await Promise.all([
            posStore.loadActiveOrders(),
            posStore.loadTables(),
        ]);
        const fromNum = data.from_table_id;
        const toNum = data.to_table_id;
        window.$toast?.(`Заказ перенесён со стола ${fromNum} на стол ${toNum}`, 'info');
    });

    // ===== Отмены =====
    on('cancellation_requested', async (data) => {
        console.log('[Realtime] cancellation_requested:', data);
        await posStore.loadPendingCancellations();
    });

    on('item_cancellation_requested', async (data) => {
        console.log('[Realtime] item_cancellation_requested:', data);
        await posStore.loadPendingCancellations();
    });

    // ===== Кухня =====
    on('kitchen_ready', async (data) => {
        console.log('[Realtime] kitchen_ready:', data);
        await posStore.loadActiveOrders();
        // Уведомление о готовности
        const tableNum = data.table_number || data.table_id || '';
        const orderNum = data.order_number || '';
        window.$toast?.(`Заказ готов${tableNum ? ` (стол ${tableNum})` : orderNum ? ` #${orderNum}` : ''}`, 'success');
        // Звуковое уведомление
        playNotificationSound('ready');
    });

    on('item_cancelled', async (data) => {
        console.log('[Realtime] item_cancelled:', data);
        await posStore.loadActiveOrders();
    });

    // ===== Доставка =====
    on('delivery_new', async (data) => {
        console.log('[Realtime] delivery_new:', data);
        await posStore.loadDeliveryOrders();
    });

    on('delivery_status', async (data) => {
        console.log('[Realtime] delivery_status:', data);
        await posStore.loadDeliveryOrders();
    });

    // courier_assigned - курьер назначен на заказ
    on('courier_assigned', async (data) => {
        console.log('[Realtime] courier_assigned:', data);
        await posStore.loadDeliveryOrders();
    });

    // delivery_problem_created - проблема с доставкой
    on('delivery_problem_created', async (data) => {
        console.log('[Realtime] delivery_problem_created:', data);
        await posStore.loadDeliveryOrders();
        window.$toast?.(`Проблема с доставкой: ${data.problem_type || ''}`, 'warning');
    });

    // delivery_problem_resolved - проблема решена
    on('delivery_problem_resolved', async (data) => {
        console.log('[Realtime] delivery_problem_resolved:', data);
        await posStore.loadDeliveryOrders();
    });

    // ===== Столы =====
    on('table_status', async (data) => {
        console.log('[Realtime] table_status:', data);
        await posStore.loadActiveOrders();
    });

    // ===== Бронирования =====
    on('reservation_new', async (data) => {
        console.log('[Realtime] reservation_new:', data);
        await posStore.loadReservations(posStore.floorDate);
        window.$toast?.(`Новая бронь: ${data.customer_name || 'Гость'}`, 'info');
    });

    on('reservation_confirmed', async (data) => {
        console.log('[Realtime] reservation_confirmed:', data);
        await posStore.loadReservations(posStore.floorDate);
    });

    on('reservation_cancelled', async (data) => {
        console.log('[Realtime] reservation_cancelled:', data);
        await posStore.loadReservations(posStore.floorDate);
    });

    on('reservation_seated', async (data) => {
        console.log('[Realtime] reservation_seated:', data);
        await posStore.loadReservations(posStore.floorDate);
        await posStore.loadActiveOrders();
    });

    // ===== Стоп-лист =====
    on('stop_list_changed', async (data) => {
        console.log('[Realtime] stop_list_changed:', data);
        await posStore.loadStopList();
        window.$toast?.('Стоп-лист обновлён', 'warning');
    });

    // ===== Бар =====
    on('bar_order_created', async (data) => {
        console.log('[Realtime] bar_order_created:', data);
        await refreshBarCount();
    });

    on('bar_order_updated', async (data) => {
        console.log('[Realtime] bar_order_updated:', data);
        await refreshBarCount();
    });

    on('bar_order_completed', async (data) => {
        console.log('[Realtime] bar_order_completed:', data);
        await refreshBarCount();
    });

    // ===== Кассовые операции =====
    on('cash_operation_created', async (data) => {
        console.log('[Realtime] cash_operation_created:', data);
        await posStore.loadCurrentShift();
        await posStore.loadShifts();
    });

    on('shift_opened', async (data) => {
        console.log('[Realtime] shift_opened:', data);
        await posStore.loadCurrentShift();
        await posStore.loadShifts();
        window.$toast?.('Смена открыта', 'success');
    });

    on('shift_closed', async (data) => {
        console.log('[Realtime] shift_closed:', data);
        await posStore.loadCurrentShift();
        await posStore.loadShifts();
        window.$toast?.('Смена закрыта', 'info');
    });

    // ===== Настройки =====
    on('settings_changed', async (data) => {
        console.log('[Realtime] settings_changed:', data);
        // Перезагружаем настройки (округление, timezone и т.д.)
        await posStore.loadInitialData();
    });
};

// Check if bar is configured
const checkBar = async () => {
    try {
        const res = await api.bar.check();
        hasBar.value = res?.has_bar === true;
        // Сразу загружаем счётчик если бар настроен
        if (hasBar.value) {
            await refreshBarCount();
        }
    } catch (e) {
        hasBar.value = false;
    }
};

// Обновление счётчика бара
let barRefreshInterval = null;

const refreshBarCount = async () => {
    if (!hasBar.value) return;
    try {
        const res = await api.bar.getOrders();
        if (res?.success && res?.counts) {
            barItemsCount.value = (res.counts.new || 0) + (res.counts.in_progress || 0);
        }
    } catch (e) {
        // ignore
    }
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

            // Запускаем IdleService
            startIdleService();

            // Инициализируем кросс-табовый логаут
            initLogoutBroadcast();

            // Подключаем SSE для real-time обновлений
            setupRealtimeEvents();

            // Fallback polling (увеличенные интервалы, основной источник — SSE)
            deliveryRefreshInterval = setInterval(refreshDeliveryCount, 60000);
            if (hasBar.value) {
                barRefreshInterval = setInterval(refreshBarCount, 30000);
            }
            cashRefreshInterval = setInterval(refreshCashData, 60000);
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

// Периодическое обновление счётчика доставки
let deliveryRefreshInterval = null;

const refreshDeliveryCount = async () => {
    if (isLoggedIn.value) {
        await posStore.loadDeliveryOrders();
    }
};

// Периодическое обновление данных кассы (смена, выручка)
let cashRefreshInterval = null;

const refreshCashData = async () => {
    if (isLoggedIn.value) {
        await Promise.all([
            posStore.loadCurrentShift(),
            posStore.loadPaidOrders()
        ]);
    }
};

// Обновление данных при возврате на вкладку браузера
// (гарантирует свежие данные, даже если WebSocket-событие не дошло)
const handleVisibilityChange = async () => {
    if (document.hidden || !isLoggedIn.value) return;
    console.log('[POS] Tab visible — refreshing stop list, orders, shift');
    const results = await Promise.allSettled([
        posStore.loadStopList(),
        posStore.loadActiveOrders(),
        posStore.loadCurrentShift(),
    ]);
    results.forEach((r, i) => {
        if (r.status === 'rejected') {
            console.warn(`[POS] Visibility refresh [${i}] failed:`, r.reason?.message || r.reason);
        }
    });
};

// Очищаем интервал при размонтировании компонента
onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    // Останавливаем сервисы
    stopIdleService();
    if (logoutBroadcast) {
        logoutBroadcast.destroy();
        logoutBroadcast = null;
    }

    // Отключаем real-time (centralized store)
    realtimeStore.destroy();

    if (timeInterval) {
        clearInterval(timeInterval);
        timeInterval = null;
    }
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
    window.removeEventListener('storage', handleBarStorageChange);
    window.removeEventListener('auth:session-expired', handleSessionExpired);
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
</style>
