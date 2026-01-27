<template>
    <div class="h-full">
        <!-- Loading state while checking session -->
        <div v-if="isInitializing" class="h-full flex items-center justify-center bg-dark-950 loading-screen">
            <div class="text-center">
                <!-- Logo with pulse animation -->
                <div class="logo-container">
                    <img src="/images/logo/posresto_icon.svg" alt="PosResto" class="w-16 h-16 mx-auto logo-pulse" />
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
        <LoginScreen v-else-if="!isLoggedIn" @login="handleLogin" />

        <!-- Main App -->
        <div v-else class="h-full flex">
            <!-- Sidebar -->
            <Sidebar
                :user="user"
                :active-tab="activeTab"
                :current-shift="currentShift"
                :pending-cancellations-count="pendingCancellationsCount"
                :pending-delivery-count="pendingDeliveryCount"
                @change-tab="changeTab"
                @logout="handleLogout"
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

        <!-- Toast Notifications -->
        <ToastContainer />
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

// Bar Panel
import BarPanel from './components/BarPanel.vue';
import axios from 'axios';

// Stores
const posStore = usePosStore();
const authStore = useAuthStore();

// State
const activeTab = ref('cash');
// If coming from payment (overlay exists), skip loading state - overlay will cover
const hasPaymentOverlay = !!document.getElementById('payment-success-overlay');
const isInitializing = ref(!hasPaymentOverlay); // Skip if overlay present

// Bar state
const hasBar = ref(false);
const barPanelOpen = ref(false);
const barItemsCount = ref(0);

// Computed
const isLoggedIn = computed(() => authStore.isLoggedIn);
const user = computed(() => authStore.user);
const currentShift = computed(() => posStore.currentShift);
const pendingCancellationsCount = computed(() => posStore.pendingCancellationsCount);
const pendingDeliveryCount = computed(() => posStore.pendingDeliveryCount);

// Methods
const handleLogin = async (userData) => {
    await loadInitialData();
    await checkBar();
    // Start delivery count refresh (every 30 seconds)
    if (!deliveryRefreshInterval) {
        deliveryRefreshInterval = setInterval(refreshDeliveryCount, 30000);
    }
    // Start bar count refresh (every 15 seconds)
    if (!barRefreshInterval && hasBar.value) {
        barRefreshInterval = setInterval(refreshBarCount, 15000);
    }
};

const handleLogout = () => {
    // Clear delivery refresh interval
    if (deliveryRefreshInterval) {
        clearInterval(deliveryRefreshInterval);
        deliveryRefreshInterval = null;
    }
    // Clear bar refresh interval
    if (barRefreshInterval) {
        clearInterval(barRefreshInterval);
        barRefreshInterval = null;
    }
    authStore.logout();
};

const changeTab = (tabId) => {
    activeTab.value = tabId;
    localStorage.setItem('posresto_active_tab', tabId);
};

const loadInitialData = async () => {
    await posStore.loadInitialData();
};

// Check if bar is configured
const checkBar = async () => {
    try {
        const res = await axios.get('/api/bar/check');
        hasBar.value = res.data.has_bar === true;
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
        const res = await axios.get('/api/bar/orders');
        if (res.data.success && res.data.counts) {
            barItemsCount.value = (res.data.counts.new || 0) + (res.data.counts.in_progress || 0);
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
    // Check URL hash for direct navigation (e.g., #hall from payment redirect)
    const hash = window.location.hash;
    if (hash === '#hall' || hash === '#orders') {
        activeTab.value = 'orders';
        // Clear hash to avoid confusion on refresh
        history.replaceState(null, '', window.location.pathname);
    } else {
        const savedTab = localStorage.getItem('posresto_active_tab');
        const validTabs = ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'];
        if (savedTab && validTabs.includes(savedTab)) {
            activeTab.value = savedTab;
        }
    }

    // Слушаем storage событие для обновления бара
    window.addEventListener('storage', handleBarStorageChange);

    // Check for existing session
    authStore.restoreSession().then(async restored => {
        // Remove payment overlay AFTER session check completes
        removePaymentOverlay();

        if (restored) {
            await loadInitialData();
            await checkBar();
            // Start delivery count refresh (every 30 seconds)
            deliveryRefreshInterval = setInterval(refreshDeliveryCount, 30000);
            // Start bar count refresh (every 15 seconds)
            if (hasBar.value) {
                barRefreshInterval = setInterval(refreshBarCount, 15000);
            }
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

// Очищаем интервал при размонтировании компонента
onUnmounted(() => {
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
    window.removeEventListener('storage', handleBarStorageChange);
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
