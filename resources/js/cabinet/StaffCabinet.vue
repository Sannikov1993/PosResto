<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Login Screen -->
        <LoginScreen v-if="!isAuthenticated" @login="handleLogin" />

        <!-- Main App -->
        <template v-else>
            <!-- Header -->
            <header class="bg-white shadow-sm sticky top-0 z-40">
                <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="/images/logo/poslab_icon.svg" alt="PosLab" class="w-8 h-8" />
                        <div>
                            <h1 class="font-semibold text-gray-900">{{ pageTitle }}</h1>
                            <p class="text-xs text-gray-500">{{ currentUser?.role_label }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Notification Bell -->
                        <button @click="currentTab = 'notifications'"
                                class="relative p-2 text-gray-500 hover:text-orange-500 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span v-if="unreadNotifications > 0"
                                  class="absolute top-0 right-0 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                {{ unreadNotifications > 9 ? '9+' : unreadNotifications }}
                            </span>
                        </button>
                        <!-- Clock In/Out Button -->
                        <button v-if="activeSession"
                                @click="handleClockOut"
                                :disabled="biometricLoading"
                                :class="['px-3 py-1.5 text-white text-sm rounded-lg transition flex items-center gap-1',
                                         biometricLoading ? 'bg-gray-400 cursor-wait' : 'bg-red-500 hover:bg-red-600']">
                            <template v-if="biometricLoading">
                                <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            </template>
                            <template v-else>
                                <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                На смене
                                <span v-if="currentUser?.require_biometric_clock" class="ml-1">👆</span>
                            </template>
                        </button>
                        <button v-else
                                @click="handleClockIn"
                                :disabled="biometricLoading"
                                :class="['px-3 py-1.5 text-white text-sm rounded-lg transition flex items-center gap-1',
                                         biometricLoading ? 'bg-gray-400 cursor-wait' : 'bg-green-500 hover:bg-green-600']">
                            <template v-if="biometricLoading">
                                <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            </template>
                            <template v-else>
                                Начать смену
                                <span v-if="currentUser?.require_biometric_clock">👆</span>
                            </template>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="max-w-4xl mx-auto px-4 py-4 pb-24">
                <DashboardTab v-if="currentTab === 'dashboard'" :data="dashboardData" @refresh="loadDashboard" />
                <ScheduleTab v-if="currentTab === 'schedule'" />
                <TimesheetTab v-if="currentTab === 'timesheet'" />
                <SalaryTab v-if="currentTab === 'salary'" />
                <StatsTab v-if="currentTab === 'stats'" :user="currentUser" />
                <ProfileTab v-if="currentTab === 'profile'" :user="currentUser" @logout="handleLogout" @updated="loadProfile" />
                <NotificationsTab v-if="currentTab === 'notifications'" @read="unreadNotifications--" />
                <AttendanceTab v-if="currentTab === 'attendance'" />
            </main>

            <!-- Bottom Navigation -->
            <nav class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-50">
                <div class="max-w-4xl mx-auto flex justify-around py-2">
                    <button v-for="tab in visibleTabs" :key="tab.id"
                            @click="currentTab = tab.id"
                            :class="['flex flex-col items-center py-2 px-3 rounded-lg transition',
                                     currentTab === tab.id ? 'text-orange-500' : 'text-gray-500']">
                        <span class="text-xl">{{ tab.icon }}</span>
                        <span class="text-xs mt-0.5">{{ tab.label }}</span>
                    </button>
                </div>
            </nav>

            <!-- Toast -->
            <div v-if="toast.show"
                 :class="['fixed top-20 left-4 right-4 max-w-md mx-auto p-4 rounded-xl text-center font-medium z-50 shadow-lg transition',
                          toast.type === 'success' ? 'bg-green-500 text-white' :
                          toast.type === 'error' ? 'bg-red-500 text-white' : 'bg-blue-500 text-white']">
                {{ toast.message }}
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import axios from 'axios';
import LoginScreen from './components/LoginScreen.vue';
import DashboardTab from './components/tabs/DashboardTab.vue';
import ScheduleTab from './components/tabs/ScheduleTab.vue';
import TimesheetTab from './components/tabs/TimesheetTab.vue';
import SalaryTab from './components/tabs/SalaryTab.vue';
import StatsTab from './components/tabs/StatsTab.vue';
import ProfileTab from './components/tabs/ProfileTab.vue';
import NotificationsTab from './components/tabs/NotificationsTab.vue';
import AttendanceTab from './components/tabs/AttendanceTab.vue';

// Auth
const isAuthenticated = ref(false);
const currentUser = ref(null);
const token = ref(null);

// Data
const dashboardData = ref(null);
const activeSession = ref(null);
const unreadNotifications = ref(0);

// Biometric
const biometricSupported = ref(window.PublicKeyCredential !== undefined);
const biometricLoading = ref(false);

// UI
const currentTab = ref('dashboard');
const loading = ref(false);

// Toast
const toast = ref({ show: false, message: '', type: 'info' });

// Tabs configuration
const allTabs = [
    { id: 'dashboard', label: 'Главная', icon: '🏠' },
    { id: 'attendance', label: 'Отметка', icon: '📍' },
    { id: 'schedule', label: 'Смены', icon: '📅' },
    { id: 'timesheet', label: 'Табель', icon: '⏱️' },
    { id: 'salary', label: 'Зарплата', icon: '💰' },
    { id: 'stats', label: 'Статистика', icon: '📊', roles: ['waiter', 'bartender', 'cashier'] },
    { id: 'profile', label: 'Профиль', icon: '👤' },
];

const visibleTabs = computed(() => {
    return allTabs.filter(tab => {
        if (!tab.roles) return true;
        return tab.roles.includes(currentUser.value?.role);
    });
});

const pageTitle = computed(() => {
    const tab = allTabs.find(t => t.id === currentTab.value);
    return tab?.label || 'Личный кабинет';
});

// Provide api helper to child components
const api = async (url, options = {}) => {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };
    if (token.value) {
        headers['Authorization'] = `Bearer ${token.value}`;
    }

    const response = await fetch(`/api${url}`, {
        ...options,
        headers: { ...headers, ...options.headers },
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || 'Ошибка запроса');
    }

    return data;
};

provide('api', api);
provide('showToast', showToast);
provide('currentUser', currentUser);

// Methods
function showToast(message, type = 'info') {
    toast.value = { show: true, message, type };
    setTimeout(() => toast.value.show = false, 3000);
}

async function handleLogin(user, authToken) {
    currentUser.value = user;
    token.value = authToken;
    isAuthenticated.value = true;

    localStorage.setItem('cabinet_user', JSON.stringify(user));
    localStorage.setItem('cabinet_token', authToken);

    await loadDashboard();
}

function handleLogout() {
    isAuthenticated.value = false;
    currentUser.value = null;
    token.value = null;
    localStorage.removeItem('cabinet_user');
    localStorage.removeItem('cabinet_token');
}

async function loadDashboard() {
    try {
        loading.value = true;
        const res = await api('/cabinet/dashboard');
        dashboardData.value = res.data;
        activeSession.value = res.data.active_session;
        unreadNotifications.value = res.data.unread_notifications || 0;
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    } finally {
        loading.value = false;
    }
}

async function loadProfile() {
    try {
        const res = await api('/cabinet/profile');
        currentUser.value = { ...currentUser.value, ...res.data };
    } catch (e) {
        console.error('Failed to load profile:', e);
    }
}

async function handleClockIn() {
    // Check if biometric is required
    if (currentUser.value?.require_biometric_clock && biometricSupported.value) {
        const verified = await verifyBiometricForClock('clock-in');
        if (!verified) return;
    }

    try {
        const res = await api('/cabinet/clock-in', { method: 'POST' });
        if (res.success) {
            activeSession.value = res.data;
            showToast('Смена начата', 'success');
            await loadDashboard();
        }
    } catch (e) {
        showToast(e.message || 'Ошибка', 'error');
    }
}

async function handleClockOut() {
    if (!confirm('Завершить смену?')) return;

    // Check if biometric is required
    if (currentUser.value?.require_biometric_clock && biometricSupported.value) {
        const verified = await verifyBiometricForClock('clock-out');
        if (!verified) return;
    }

    try {
        const res = await api('/cabinet/clock-out', { method: 'POST' });
        if (res.success) {
            activeSession.value = null;
            showToast('Смена завершена', 'success');
            await loadDashboard();
        }
    } catch (e) {
        showToast(e.message || 'Ошибка', 'error');
    }
}

async function verifyBiometricForClock(action) {
    biometricLoading.value = true;
    try {
        // Get authentication options from server
        const optionsRes = await api('/cabinet/biometric/auth-options');
        const options = optionsRes.data;

        // Convert base64 to ArrayBuffer
        options.challenge = base64ToArrayBuffer(options.challenge);
        if (options.allowCredentials) {
            options.allowCredentials = options.allowCredentials.map(cred => ({
                ...cred,
                id: base64ToArrayBuffer(cred.id),
            }));
        }

        // Request credential
        const assertion = await navigator.credentials.get({
            publicKey: options,
        });

        // Prepare response for server
        const response = {
            id: assertion.id,
            rawId: arrayBufferToBase64(assertion.rawId),
            type: assertion.type,
            response: {
                clientDataJSON: arrayBufferToBase64(assertion.response.clientDataJSON),
                authenticatorData: arrayBufferToBase64(assertion.response.authenticatorData),
                signature: arrayBufferToBase64(assertion.response.signature),
                userHandle: assertion.response.userHandle ? arrayBufferToBase64(assertion.response.userHandle) : null,
            },
        };

        // Verify with server and perform clock action
        const endpoint = action === 'clock-in' ? '/cabinet/clock-in-biometric' : '/cabinet/clock-out-biometric';
        const res = await api(endpoint, {
            method: 'POST',
            body: JSON.stringify({ credential: response }),
        });

        if (res.success) {
            if (action === 'clock-in') {
                activeSession.value = res.data;
                showToast('Смена начата (биометрия)', 'success');
            } else {
                activeSession.value = null;
                showToast('Смена завершена (биометрия)', 'success');
            }
            await loadDashboard();
            return false; // Already handled
        }
        return true;
    } catch (e) {
        console.error('Biometric verification error:', e);
        if (e.name === 'NotAllowedError') {
            showToast('Биометрия отменена', 'warning');
        } else {
            showToast(e.message || 'Ошибка биометрии', 'error');
        }
        return false;
    } finally {
        biometricLoading.value = false;
    }
}

function base64ToArrayBuffer(base64) {
    const binaryString = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Lifecycle
onMounted(async () => {
    // Check saved auth
    const savedUser = localStorage.getItem('cabinet_user');
    const savedToken = localStorage.getItem('cabinet_token');

    if (savedUser && savedToken) {
        currentUser.value = JSON.parse(savedUser);
        token.value = savedToken;
        isAuthenticated.value = true;
        await loadDashboard();
    }
});
</script>

<style>
/* Add any cabinet-specific styles here */
</style>
