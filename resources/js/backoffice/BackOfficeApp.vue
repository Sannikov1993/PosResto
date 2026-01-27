<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Login Screen -->
        <LoginScreen v-if="!store.isAuthenticated" @login="onLogin" />

        <!-- Main Application -->
        <div v-else class="min-h-screen flex">
            <!-- Sidebar -->
            <Sidebar />

            <!-- Main Content -->
            <main :class="['flex-1 transition-all duration-300', store.sidebarCollapsed ? 'ml-[70px]' : 'ml-[260px]']">
                <!-- Top Header -->
                <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
                    <div class="flex items-center">
                        <h1 class="text-lg font-semibold text-gray-900">{{ store.currentModuleName }}</h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <!-- Notifications -->
                        <button @click="showNotifications = !showNotifications" class="relative p-2 text-gray-500 hover:text-gray-700">
                            <span class="text-xl">🔔</span>
                            <span v-if="store.notifications.length" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <!-- Date -->
                        <span class="text-sm text-gray-500">{{ currentDate }}</span>
                        <!-- Logout -->
                        <button @click="handleLogout" class="text-sm text-gray-500 hover:text-red-500">Выйти</button>
                    </div>
                </header>

                <!-- Page Content -->
                <div class="p-6">
                    <DashboardTab v-if="store.currentModule === 'dashboard'" />
                    <MenuTab v-else-if="store.currentModule === 'menu'" />
                    <StaffTab v-else-if="store.currentModule === 'staff'" />
                    <AttendanceTab v-else-if="store.currentModule === 'attendance'" />
                    <HallTab v-else-if="store.currentModule === 'hall'" />
                    <CustomersTab v-else-if="store.currentModule === 'customers'" />
                    <InventoryTab v-else-if="store.currentModule === 'inventory'" />
                    <LoyaltyTab v-else-if="store.currentModule === 'loyalty'" />
                    <DeliveryTab v-else-if="store.currentModule === 'delivery'" />
                    <FinanceTab v-else-if="store.currentModule === 'finance'" />
                    <AnalyticsTab v-else-if="store.currentModule === 'analytics'" />
                    <SettingsTab v-else-if="store.currentModule === 'settings'" />

                    <!-- Placeholder for not yet implemented -->
                    <div v-else class="text-center py-20">
                        <p class="text-6xl mb-4">🚧</p>
                        <p class="text-gray-500">Модуль "{{ store.currentModuleName }}" в разработке</p>
                    </div>
                </div>
            </main>
        </div>

        <!-- Toast Notifications -->
        <ToastContainer />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from './stores/backoffice';
import { setTimezone, formatDateShort } from '../utils/timezone';
import LoginScreen from './components/LoginScreen.vue';
import Sidebar from './components/Sidebar.vue';
import ToastContainer from './components/ui/ToastContainer.vue';

// Tabs
import DashboardTab from './components/tabs/DashboardTab.vue';
import MenuTab from './components/tabs/MenuTab.vue';
import StaffTab from './components/tabs/StaffTab.vue';
import HallTab from './components/tabs/HallTab.vue';
import CustomersTab from './components/tabs/CustomersTab.vue';
import InventoryTab from './components/tabs/InventoryTab.vue';
import LoyaltyTab from './components/tabs/LoyaltyTab.vue';
import DeliveryTab from './components/tabs/DeliveryTab.vue';
import FinanceTab from './components/tabs/FinanceTab.vue';
import AnalyticsTab from './components/tabs/AnalyticsTab.vue';
import SettingsTab from './components/tabs/SettingsTab.vue';
import AttendanceTab from './components/tabs/AttendanceTab.vue';

const store = useBackofficeStore();

const showNotifications = ref(false);

const currentDate = computed(() => {
    return formatDateShort(new Date());
});

const onLogin = async () => {
    // Load initial data after login
    store.loadDashboard();
};

const handleLogout = () => {
    store.logout();
};

onMounted(async () => {
    // Load timezone from settings
    try {
        const response = await fetch('/api/settings/general');
        const data = await response.json();
        if (data.success && data.data?.timezone) {
            setTimezone(data.data.timezone);
        }
    } catch (e) {
        console.warn('[Backoffice] Failed to load timezone:', e);
    }

    // Check if user is already authenticated
    await store.checkAuth();
    if (store.isAuthenticated) {
        store.loadDashboard();
    }
});
</script>

<style>
/* Base styles */
* { font-family: 'Inter', sans-serif; }
[v-cloak] { display: none; }

/* Common classes */
.card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.btn-primary { background: #f97316; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500; transition: all 0.2s; }
.btn-primary:hover { background: #ea580c; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-danger { background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
.btn-danger:hover { background: #dc2626; }
.input { width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; transition: all 0.2s; }
.input:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-info { background: #dbeafe; color: #1e40af; }

/* Skeleton animation */
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}
.skeleton-text { height: 16px; margin-bottom: 8px; }
.skeleton-title { height: 24px; width: 60%; margin-bottom: 12px; }

/* Spinner */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #f97316;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
.spinner-sm { width: 20px; height: 20px; border-width: 2px; }

/* Modal backdrop */
.modal-backdrop { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; }
::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #aaa; }
</style>
