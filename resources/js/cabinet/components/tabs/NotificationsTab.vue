<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Уведомления</h2>
            <button v-if="notifications.length"
                    @click="markAllRead"
                    class="text-sm text-orange-600 hover:text-orange-700">
                Прочитать все
            </button>
        </div>

        <!-- Notifications List -->
        <div class="space-y-3">
            <div v-for="notification in notifications" :key="notification.id"
                 @click="markAsRead(notification)"
                 :class="['bg-white rounded-xl shadow-sm p-4 cursor-pointer transition',
                          !notification.read_at ? 'border-l-4 border-orange-500' : 'opacity-75']">
                <div class="flex items-start gap-3">
                    <div class="text-2xl">{{ getTypeEmoji(notification.type) }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900">{{ notification.title }}</div>
                        <div class="text-sm text-gray-500 mt-0.5">{{ notification.message }}</div>
                        <div class="text-xs text-gray-400 mt-2">{{ formatTime(notification.created_at) }}</div>
                    </div>
                    <div v-if="!notification.read_at" class="w-2 h-2 bg-orange-500 rounded-full flex-shrink-0 mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && notifications.length === 0"
             class="text-center py-12">
            <div class="text-5xl mb-3">🔔</div>
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Нет уведомлений</h3>
            <p class="text-gray-500">Здесь будут отображаться ваши уведомления</p>
        </div>

        <!-- Load More -->
        <div v-if="hasMore" class="text-center">
            <button @click="loadMore" :disabled="loading"
                    class="px-4 py-2 text-orange-600 hover:text-orange-700 font-medium disabled:opacity-50">
                {{ loading ? 'Загрузка...' : 'Загрузить ещё' }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading && notifications.length === 0" class="space-y-3">
            <div v-for="i in 5" :key="i" class="bg-white rounded-xl shadow-sm p-4 animate-pulse">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-gray-200 rounded-full"></div>
                    <div class="flex-1">
                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, inject } from 'vue';

const emit = defineEmits(['read']);

const api = inject('api');
const showToast = inject('showToast');

const loading = ref(false);
const notifications = ref<any[]>([]);
const page = ref(1);
const hasMore = ref(false);

const typeEmojis = {
    schedule_published: '📅',
    shift_reminder: '⏰',
    salary_paid: '💰',
    bonus_received: '🎉',
    penalty_received: '⚠️',
    general: '📢',
};

function getTypeEmoji(type: any) {
    return (typeEmojis as Record<string, any>)[type] || '📢';
}

function formatTime(dateStr: any) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = Number(now) - Number(d);
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Только что';
    if (minutes < 60) return `${minutes} мин назад`;
    if (hours < 24) return `${hours} ч назад`;
    if (days < 7) return `${days} дн назад`;

    return `${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}`;
}

async function loadNotifications(append = false) {
    loading.value = true;
    try {
        const res = await (api as any)(`/cabinet/notifications?page=${page.value}&per_page=20`);
        const data = res.data?.data || [];

        if (append) {
            notifications.value.push(...data);
        } else {
            notifications.value = data;
        }

        hasMore.value = res.data?.next_page_url != null;
    } catch (e: any) {
        console.error('Failed to load notifications:', e);
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    page.value++;
    loadNotifications(true);
}

async function markAsRead(notification: any) {
    if (notification.read_at) return;

    try {
        await (api as any)(`/cabinet/notifications/${notification.id}/read`, { method: 'POST' });
        notification.read_at = new Date().toISOString();
        emit('read');
    } catch (e: any) {
        console.error('Failed to mark as read:', e);
    }
}

async function markAllRead() {
    try {
        await (api as any)('/cabinet/notifications/read-all', { method: 'POST' });
        notifications.value.forEach((n: any) => n.read_at = new Date().toISOString());
        (showToast as any)('Все уведомления прочитаны', 'success');
    } catch (e: any) {
        (showToast as any)('Ошибка', 'error');
    }
}

onMounted(() => {
    loadNotifications();
});
</script>
