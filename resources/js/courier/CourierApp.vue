<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Login Screen -->
        <LoginScreen v-if="!isAuthenticated" @login="handleLogin" />

        <!-- Main App -->
        <div v-else-if="isAuthenticated" class="min-h-screen pb-20">
            <!-- Header -->
            <header class="bg-purple-600 text-white px-4 py-3 sticky top-0 z-40 shadow-lg safe-top">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="font-semibold text-lg">{{ store.headerTitle }}</h1>
                        <p class="text-purple-200 text-xs">{{ store.user?.name || 'Курьер' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <div :class="['w-2.5 h-2.5 rounded-full', store.isOnline ? 'bg-green-400' : 'bg-red-400']"></div>
                            <span class="text-xs text-purple-200">{{ store.isOnline ? 'Онлайн' : 'Оффлайн' }}</span>
                        </div>
                        <button @click="refreshData" :disabled="store.isLoading"
                                class="p-2 rounded-full hover:bg-purple-500 transition-colors">
                            <svg :class="['w-5 h-5', store.isLoading && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Tab: My Orders -->
            <div v-show="store.activeTab === 'orders'" class="p-4">
                <OrdersTab />
            </div>

            <!-- Tab: Available Orders -->
            <div v-show="store.activeTab === 'available'" class="p-4">
                <AvailableTab />
            </div>

            <!-- Tab: Profile -->
            <div v-show="store.activeTab === 'profile'" class="p-4">
                <ProfileTab @logout="handleLogout" />
            </div>

            <!-- Order Detail Modal -->
            <OrderModal v-if="store.selectedOrder" />

            <!-- Bottom Navigation -->
            <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 safe-bottom z-40">
                <div class="flex">
                    <button @click="store.activeTab = 'orders'"
                            :class="['flex-1 py-3 flex flex-col items-center gap-1 transition-colors relative', store.activeTab === 'orders' ? 'text-purple-600' : 'text-gray-400']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        <span class="text-xs">Мои заказы</span>
                        <span v-if="store.activeOrders.length" class="absolute top-2 ml-8 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                            {{ store.activeOrders.length }}
                        </span>
                    </button>
                    <button @click="store.activeTab = 'available'"
                            :class="['flex-1 py-3 flex flex-col items-center gap-1 transition-colors relative', store.activeTab === 'available' ? 'text-purple-600' : 'text-gray-400']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span class="text-xs">Доступные</span>
                        <span v-if="store.availableOrders.length" class="absolute top-2 ml-8 bg-green-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                            {{ store.availableOrders.length }}
                        </span>
                    </button>
                    <button @click="store.activeTab = 'profile'"
                            :class="['flex-1 py-3 flex flex-col items-center gap-1 transition-colors', store.activeTab === 'profile' ? 'text-purple-600' : 'text-gray-400']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-xs">Профиль</span>
                    </button>
                </div>
            </nav>
        </div>

        <!-- Toast -->
        <div v-if="store.toast"
             :class="['fixed top-20 left-4 right-4 z-50 p-4 rounded-xl shadow-lg text-white text-center transition-all',
                      store.toast.type === 'success' ? 'bg-green-600' : store.toast.type === 'error' ? 'bg-red-600' : 'bg-purple-600']">
            {{ store.toast.message }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useCourierStore } from './stores/courier';
import auth from '@/utils/auth';
import LoginScreen from './components/LoginScreen.vue';
import OrdersTab from './components/OrdersTab.vue';
import AvailableTab from './components/AvailableTab.vue';
import ProfileTab from './components/ProfileTab.vue';
import OrderModal from './components/OrderModal.vue';

const store = useCourierStore();

// Auth states
const isAuthenticated = ref(false);
const currentUser = ref<any>(null);

async function refreshData() {
    await store.loadData();
    store.showToast('Данные обновлены', 'success');
}

const handleLogin = async (userData: any) => {
    currentUser.value = userData.user;
    isAuthenticated.value = true;

    // Обновляем store для совместимости
    store.user = userData.user;
    (store as any).token = userData.token;
    store.courierId = userData.user.id;
    store.isAuthenticated = true;

    await store.loadData();
    store.startLocationTracking();
    store.connectSSE();
};

const handleLogout = async () => {
    await auth.logout(false); // Не удаляем device_token
    isAuthenticated.value = false;
    currentUser.value = null;
    store.logout();
};

onMounted(async () => {
    // Register service worker
    if ('serviceWorker' in navigator) {
        try {
            await navigator.serviceWorker.register('/courier-sw.js');
        } catch (error: any) {
            console.warn('Service Worker registration failed:', error);
        }
    }

    // Инициализируем auth utility
    (auth as any).init();

    // Пытаемся автовход по device_token
    try {
        const response = await auth.deviceLogin() as any;

        if (response.success) {
            currentUser.value = response.data.user;
            isAuthenticated.value = true;

            // Обновляем store
            store.user = response.data.user;
            (store as any).token = response.data.token;
            store.courierId = response.data.user.id;
            store.isAuthenticated = true;

            await store.loadData();
            store.startLocationTracking();
            store.connectSSE();
        }
    } catch (error: any) {
        // Токен невалиден или другая ошибка - показываем логин
        localStorage.removeItem('device_token');
    }

    // Online/offline events
    window.addEventListener('online', () => {
        store.isOnline = true;
        if (isAuthenticated.value) {
            store.loadData();
        }
    });
    window.addEventListener('offline', () => {
        store.isOnline = false;
    });
});

onBeforeUnmount(() => {
    store.stopLocationTracking();
    store.disconnectSSE();
});
</script>

<style>
.safe-top { padding-top: env(safe-area-inset-top, 0); }
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 16px); }
</style>
