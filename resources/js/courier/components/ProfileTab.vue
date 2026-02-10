<template>
    <div>
        <!-- User card -->
        <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl font-bold text-purple-600">{{ store.userInitials }}</span>
                </div>
                <div class="flex-1">
                    <h2 class="font-semibold text-lg text-gray-800">{{ store.user?.name }}</h2>
                    <p class="text-gray-500 text-sm">{{ store.user?.phone || store.user?.email }}</p>
                </div>
            </div>
        </div>

        <!-- Status toggle -->
        <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-800">Статус</p>
                    <p class="text-sm text-gray-500">{{ store.courierStatus === 'available' ? 'Готов к работе' : 'Не в сети' }}</p>
                </div>
                <button @click="store.toggleStatus()"
                        :class="['relative w-14 h-8 rounded-full transition-colors', store.courierStatus === 'available' ? 'bg-green-500' : 'bg-gray-300']">
                    <span :class="['absolute top-1 w-6 h-6 bg-white rounded-full shadow transition-transform', store.courierStatus === 'available' ? 'right-1' : 'left-1']"></span>
                </button>
            </div>
        </div>

        <!-- Today stats -->
        <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Статистика за сегодня</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <div class="flex items-center justify-between p-4">
                    <span class="text-gray-600">Выполнено заказов</span>
                    <span class="font-semibold text-gray-800">{{ store.stats.todayOrders }}</span>
                </div>
                <div class="flex items-center justify-between p-4">
                    <span class="text-gray-600">Заработок</span>
                    <span class="font-semibold text-green-600">{{ store.formatMoney(store.stats.todayEarnings) }}</span>
                </div>
                <div class="flex items-center justify-between p-4">
                    <span class="text-gray-600">Среднее время доставки</span>
                    <span class="font-semibold text-gray-800">{{ store.stats.avgDeliveryTime }} мин</span>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Уведомления</h3>
                <span v-if="unreadCount > 0" class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                    {{ unreadCount }}
                </span>
            </div>
            <div class="divide-y divide-gray-100">
                <button @click="showNotifications = true" class="w-full flex items-center justify-between p-4 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">📬</span>
                        <span class="text-gray-700">Мои уведомления</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button @click="showNotificationSettings = true" class="w-full flex items-center justify-between p-4 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">⚙️</span>
                        <span class="text-gray-700">Настройки уведомлений</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Telegram -->
        <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">💬</span>
                <div class="flex-1">
                    <h4 class="font-medium text-gray-800">Telegram</h4>
                    <p class="text-sm text-gray-500">
                        {{ telegramConnected ? `@${telegramUsername}` : 'Не подключён' }}
                    </p>
                </div>
                <span :class="telegramConnected ? 'bg-green-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full"></span>
            </div>
            <button v-if="!telegramConnected" @click="connectTelegram" :disabled="loadingTelegram"
                    class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-sm font-medium transition disabled:opacity-50">
                {{ loadingTelegram ? 'Загрузка...' : 'Подключить Telegram' }}
            </button>
            <button v-else @click="disconnectTelegram" :disabled="loadingTelegram"
                    class="w-full py-2.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl text-sm font-medium transition disabled:opacity-50">
                {{ loadingTelegram ? 'Загрузка...' : 'Отключить' }}
            </button>
        </div>

        <!-- Settings -->
        <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Настройки</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <button @click="requestNotificationPermission" class="w-full flex items-center justify-between p-4 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="text-gray-700">Push-уведомления</span>
                    </div>
                    <span :class="['text-sm', store.notificationPermission === 'granted' ? 'text-green-600' : 'text-gray-400']">
                        {{ store.notificationPermission === 'granted' ? 'Включены' : 'Выключены' }}
                    </span>
                </button>
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-gray-700">Геолокация</span>
                    </div>
                    <span :class="['text-sm', store.geoEnabled ? 'text-green-600' : 'text-gray-400']">
                        {{ store.geoEnabled ? 'Активна' : 'Отключена' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Logout -->
        <button @click="$emit('logout')"
                class="w-full bg-white rounded-xl p-4 shadow-sm text-red-600 font-medium hover:bg-red-50 transition-colors">
            Выйти из аккаунта
        </button>

        <!-- Version -->
        <p class="text-center text-gray-400 text-xs mt-4">Версия 1.0.0</p>

        <!-- Notifications Modal -->
        <div v-if="showNotifications" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center">
            <div class="bg-white w-full max-w-lg max-h-[90vh] rounded-t-3xl sm:rounded-3xl overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-gray-800">Уведомления</h3>
                    <button @click="showNotifications = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div v-if="notifications.length === 0" class="text-center py-8 text-gray-500">
                        Нет уведомлений
                    </div>
                    <div v-for="n in notifications" :key="n.id"
                         :class="['p-4 rounded-xl border', n.read_at ? 'bg-gray-50 border-gray-100' : 'bg-purple-50 border-purple-200']"
                         @click="markAsRead(n)">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">{{ getNotificationIcon(n.type) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800">{{ n.title }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ n.message }}</p>
                                <p class="text-xs text-gray-400 mt-2">{{ formatDate(n.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="unreadCount > 0" class="p-4 border-t border-gray-100">
                    <button @click="markAllAsRead" class="w-full py-3 bg-purple-600 text-white rounded-xl font-medium">
                        Прочитать все
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Settings Modal -->
        <div v-if="showNotificationSettings" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center">
            <div class="bg-white w-full max-w-lg max-h-[90vh] rounded-t-3xl sm:rounded-3xl overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-gray-800">Настройки уведомлений</h3>
                    <button @click="showNotificationSettings = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <p class="text-sm text-gray-500 mb-4">Выберите, о чём вы хотите получать уведомления</p>

                    <div class="space-y-4">
                        <div v-for="(label, key) in notificationTypes" :key="key" class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="font-medium text-gray-800">{{ label }}</span>
                            </div>
                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].in_app"
                                           class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-gray-600">В приложении</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].telegram"
                                           class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-gray-600">Telegram</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].email"
                                           class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-gray-600">Email</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-100">
                    <button @click="saveNotificationSettings" :disabled="savingSettings"
                            class="w-full py-3 bg-purple-600 text-white rounded-xl font-medium disabled:opacity-50">
                        {{ savingSettings ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useCourierStore } from '../stores/courier';

const emit = defineEmits(['logout']);
const store = useCourierStore();

// Notifications state
const showNotifications = ref(false);
const showNotificationSettings = ref(false);
const notifications = ref<any[]>([]);
const unreadCount = ref(0);
const loadingTelegram = ref(false);
const telegramConnected = ref(false);
const telegramUsername = ref('');
const savingSettings = ref(false);

const notificationTypes = {
    shift_reminder: 'Напоминания о сменах',
    schedule_change: 'Изменения расписания',
    salary_paid: 'Выплата зарплаты',
    bonus_received: 'Премии',
    penalty_received: 'Штрафы',
    custom: 'Сообщения от руководства',
    system: 'Системные уведомления'
};

const notificationSettings = ref<Record<string, any>>({});

// Initialize default settings
Object.keys(notificationTypes).forEach((key: any) => {
    notificationSettings.value[key] = { in_app: true, telegram: true, email: false };
});

const formatDate = (dateStr: any) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Number(now) - Number(date);
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Только что';
    if (minutes < 60) return `${minutes} мин. назад`;
    if (hours < 24) return `${hours} ч. назад`;
    if (days < 7) return `${days} дн. назад`;
    return date.toLocaleDateString('ru-RU');
};

const getNotificationIcon = (type: any) => {
    const icons = {
        shift_reminder: '⏰',
        schedule_change: '📅',
        schedule_published: '📋',
        salary_paid: '💰',
        bonus_received: '🎁',
        penalty_received: '⚠️',
        shift_started: '🟢',
        shift_ended: '🔴',
        custom: '📢',
        system: '🔔'
    };
    return (icons as Record<string, any>)[type] || '🔔';
};

const loadNotifications = async () => {
    try {
        const response = await axios.get('/api/staff-notifications');
        if (response.data.success) {
            notifications.value = response.data.data;
            unreadCount.value = response.data.unread_count;
        }
    } catch (e: any) {
        console.error('Failed to load notifications:', e);
    }
};

const loadSettings = async () => {
    try {
        const response = await axios.get('/api/staff-notifications/settings');
        if (response.data.success) {
            const data = response.data.data;
            telegramConnected.value = data.telegram_connected;
            telegramUsername.value = data.telegram_username;
            if (data.settings) {
                notificationSettings.value = data.settings;
            }
        }
    } catch (e: any) {
        console.error('Failed to load settings:', e);
    }
};

const markAsRead = async (notification: any) => {
    if (notification.read_at) return;
    try {
        await axios.post(`/api/staff-notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (e: any) {
        console.error('Failed to mark as read:', e);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post('/api/staff-notifications/read-all');
        notifications.value.forEach((n: any) => n.read_at = new Date().toISOString());
        unreadCount.value = 0;
    } catch (e: any) {
        console.error('Failed to mark all as read:', e);
    }
};

const connectTelegram = async () => {
    loadingTelegram.value = true;
    try {
        const response = await axios.get('/api/staff-notifications/telegram-link');
        if (response.data.success && response.data.data.link) {
            window.open(response.data.data.link, '_blank');
        }
    } catch (e: any) {
        console.error('Failed to get Telegram link:', e);
    } finally {
        loadingTelegram.value = false;
    }
};

const disconnectTelegram = async () => {
    if (!confirm('Отключить Telegram? Вы перестанете получать уведомления.')) return;
    loadingTelegram.value = true;
    try {
        await axios.post('/api/staff-notifications/disconnect-telegram');
        telegramConnected.value = false;
        telegramUsername.value = '';
    } catch (e: any) {
        console.error('Failed to disconnect Telegram:', e);
    } finally {
        loadingTelegram.value = false;
    }
};

const saveNotificationSettings = async () => {
    savingSettings.value = true;
    try {
        await axios.put('/api/staff-notifications/settings', {
            settings: notificationSettings.value
        });
        showNotificationSettings.value = false;
    } catch (e: any) {
        console.error('Failed to save settings:', e);
    } finally {
        savingSettings.value = false;
    }
};

async function requestNotificationPermission() {
    if (!('Notification' in window)) return;

    try {
        const permission = await Notification.requestPermission();
        store.notificationPermission = permission;
    } catch (error: any) {
        console.warn('Notification permission error:', error);
    }
}

onMounted(() => {
    loadNotifications();
    loadSettings();
});
</script>
