<template>
    <div class="h-full overflow-y-auto p-4">
        <!-- User Info -->
        <div class="bg-dark-800 rounded-2xl p-6 text-center mb-4">
            <div class="w-20 h-20 rounded-full bg-orange-500 mx-auto mb-4 flex items-center justify-center text-3xl font-bold">
                {{ initials }}
            </div>
            <h2 class="text-xl font-bold">{{ user?.name || 'Официант' }}</h2>
            <p class="text-gray-500">{{ user?.role || 'Сотрудник' }}</p>
        </div>

        <!-- Stats -->
        <div class="bg-dark-800 rounded-2xl p-4 mb-4">
            <h3 class="font-medium mb-4">Статистика за сегодня</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-orange-400">{{ stats.orders_count || 0 }}</p>
                    <p class="text-xs text-gray-500">Заказов</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-400">{{ formatMoney(stats.total_sales || 0) }} ₽</p>
                    <p class="text-xs text-gray-500">Продажи</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-blue-400">{{ stats.avg_check || 0 }} ₽</p>
                    <p class="text-xs text-gray-500">Средний чек</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-purple-400">{{ formatMoney(stats.tips || 0) }} ₽</p>
                    <p class="text-xs text-gray-500">Чаевые</p>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-dark-800 rounded-2xl overflow-hidden mb-4">
            <div class="p-4 border-b border-gray-700">
                <h3 class="font-medium flex items-center gap-2">
                    <span class="text-xl">🔔</span> Уведомления
                    <span v-if="unreadCount > 0" class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                        {{ unreadCount }}
                    </span>
                </h3>
            </div>
            <button @click="showNotifications = true" class="w-full p-4 text-left flex items-center gap-3 border-b border-gray-700">
                <span class="text-xl">📬</span>
                <span>Мои уведомления</span>
                <span class="ml-auto text-gray-500">→</span>
            </button>
            <button @click="showNotificationSettings = true" class="w-full p-4 text-left flex items-center gap-3">
                <span class="text-xl">⚙️</span>
                <span>Настройки уведомлений</span>
                <span class="ml-auto text-gray-500">→</span>
            </button>
        </div>

        <!-- Telegram Connection -->
        <div class="bg-dark-800 rounded-2xl p-4 mb-4">
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">💬</span>
                <div class="flex-1">
                    <h4 class="font-medium">Telegram</h4>
                    <p class="text-sm text-gray-500">
                        {{ telegramConnected ? `@${telegramUsername}` : 'Не подключён' }}
                    </p>
                </div>
                <span :class="telegramConnected ? 'bg-green-500' : 'bg-gray-600'" class="w-3 h-3 rounded-full"></span>
            </div>
            <button v-if="!telegramConnected" @click="connectTelegram" :disabled="loadingTelegram"
                    class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 rounded-xl text-sm font-medium transition disabled:opacity-50">
                {{ loadingTelegram ? 'Загрузка...' : 'Подключить Telegram' }}
            </button>
            <button v-else @click="disconnectTelegram" :disabled="loadingTelegram"
                    class="w-full py-2.5 bg-red-500/20 text-red-400 hover:bg-red-500/30 rounded-xl text-sm font-medium transition disabled:opacity-50">
                {{ loadingTelegram ? 'Загрузка...' : 'Отключить' }}
            </button>
        </div>

        <!-- Quick Actions -->
        <div class="bg-dark-800 rounded-2xl overflow-hidden mb-4">
            <button class="w-full p-4 text-left flex items-center gap-3 border-b border-gray-700">
                <span class="text-xl">📊</span>
                <span>История заказов</span>
                <span class="ml-auto text-gray-500">→</span>
            </button>
            <button class="w-full p-4 text-left flex items-center gap-3 border-b border-gray-700">
                <span class="text-xl">💰</span>
                <span>Мои чаевые</span>
                <span class="ml-auto text-gray-500">→</span>
            </button>
            <button class="w-full p-4 text-left flex items-center gap-3">
                <span class="text-xl">📈</span>
                <span>Аналитика</span>
                <span class="ml-auto text-gray-500">→</span>
            </button>
        </div>

        <!-- Logout -->
        <button @click="$emit('logout')"
                class="w-full py-4 bg-red-500/20 text-red-400 rounded-2xl font-medium">
            Выйти
        </button>

        <!-- Notifications Modal -->
        <div v-if="showNotifications" class="fixed inset-0 bg-black/80 z-50 flex items-end sm:items-center justify-center">
            <div class="bg-dark-900 w-full max-w-lg max-h-[90vh] rounded-t-3xl sm:rounded-3xl overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="font-bold text-lg">Уведомления</h3>
                    <button @click="showNotifications = false" class="text-gray-500 hover:text-white text-xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div v-if="notifications.length === 0" class="text-center py-8 text-gray-500">
                        Нет уведомлений
                    </div>
                    <div v-for="n in notifications" :key="n.id"
                         :class="['p-4 rounded-xl', n.read_at ? 'bg-dark-800' : 'bg-dark-700 border border-orange-500/30']"
                         @click="markAsRead(n)">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">{{ getNotificationIcon(n.type) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium">{{ n.title }}</p>
                                <p class="text-sm text-gray-400 mt-1">{{ n.message }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ formatDate(n.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="unreadCount > 0" class="p-4 border-t border-gray-800">
                    <button @click="markAllAsRead" class="w-full py-3 bg-orange-500 rounded-xl font-medium">
                        Прочитать все
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Settings Modal -->
        <div v-if="showNotificationSettings" class="fixed inset-0 bg-black/80 z-50 flex items-end sm:items-center justify-center">
            <div class="bg-dark-900 w-full max-w-lg max-h-[90vh] rounded-t-3xl sm:rounded-3xl overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="font-bold text-lg">Настройки уведомлений</h3>
                    <button @click="showNotificationSettings = false" class="text-gray-500 hover:text-white text-xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <p class="text-sm text-gray-500 mb-4">Выберите, о чём вы хотите получать уведомления</p>

                    <div class="space-y-4">
                        <div v-for="(label, key) in notificationTypes" :key="key" class="bg-dark-800 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="font-medium">{{ label }}</span>
                            </div>
                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].in_app"
                                           class="w-4 h-4 rounded bg-dark-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                    <span class="text-gray-400">В приложении</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].telegram"
                                           class="w-4 h-4 rounded bg-dark-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                    <span class="text-gray-400">Telegram</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationSettings[key].email"
                                           class="w-4 h-4 rounded bg-dark-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                    <span class="text-gray-400">Email</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-800">
                    <button @click="saveNotificationSettings" :disabled="savingSettings"
                            class="w-full py-3 bg-orange-500 rounded-xl font-medium disabled:opacity-50">
                        {{ savingSettings ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: { type: Object, default: null },
    stats: { type: Object, default: () => ({}) }
});

defineEmits(['logout']);

// Notifications state
const showNotifications = ref(false);
const showNotificationSettings = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const loadingTelegram = ref(false);
const telegramConnected = ref(false);
const telegramUsername = ref('');
const savingSettings = ref(false);

const notificationTypes = {
    shift_reminder: 'Напоминания о сменах',
    schedule_change: 'Изменения расписания',
    schedule_published: 'Публикация расписания',
    salary_paid: 'Выплата зарплаты',
    bonus_received: 'Премии',
    penalty_received: 'Штрафы',
    custom: 'Сообщения от руководства',
    system: 'Системные уведомления'
};

const notificationSettings = ref({});

// Initialize default settings
Object.keys(notificationTypes).forEach(key => {
    notificationSettings.value[key] = { in_app: true, telegram: true, email: false };
});

const initials = computed(() => {
    if (!props.user?.name) return '👤';
    const parts = props.user.name.split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return props.user.name.substring(0, 2).toUpperCase();
});

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Только что';
    if (minutes < 60) return `${minutes} мин. назад`;
    if (hours < 24) return `${hours} ч. назад`;
    if (days < 7) return `${days} дн. назад`;
    return date.toLocaleDateString('ru-RU');
};

const getNotificationIcon = (type) => {
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
    return icons[type] || '🔔';
};

const loadNotifications = async () => {
    try {
        const response = await axios.get('/api/staff-notifications');
        if (response.data.success) {
            notifications.value = response.data.data;
            unreadCount.value = response.data.unread_count;
        }
    } catch (e) {
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
    } catch (e) {
        console.error('Failed to load settings:', e);
    }
};

const markAsRead = async (notification) => {
    if (notification.read_at) return;
    try {
        await axios.post(`/api/staff-notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (e) {
        console.error('Failed to mark as read:', e);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post('/api/staff-notifications/read-all');
        notifications.value.forEach(n => n.read_at = new Date().toISOString());
        unreadCount.value = 0;
    } catch (e) {
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
    } catch (e) {
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
    } catch (e) {
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
    } catch (e) {
        console.error('Failed to save settings:', e);
    } finally {
        savingSettings.value = false;
    }
};

onMounted(() => {
    loadNotifications();
    loadSettings();
});
</script>
