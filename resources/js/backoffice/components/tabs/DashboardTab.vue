<template>
    <div>
        <!-- Live Indicator -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-sm font-medium text-green-700">Live</span>
                </div>
                <span class="text-sm text-gray-500">Обновлено: {{ formatTime(new Date()) }}</span>
            </div>
            <button @click="store.loadDashboard" :disabled="store.loading.dashboard"
                    class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50">
                <div v-if="store.loading.dashboard" class="spinner spinner-sm"></div>
                <span v-else>🔄</span>
                {{ store.loading.dashboard ? 'Загрузка...' : 'Обновить' }}
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="card p-6 border-l-4 border-l-blue-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Заказов сегодня</p>
                        <p class="text-3xl font-bold text-gray-900">{{ store.dashboard.todayOrders || 0 }}</p>
                    </div>
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center text-2xl">📦</div>
                </div>
            </div>
            <div class="card p-6 border-l-4 border-l-green-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Выручка сегодня</p>
                        <p class="text-3xl font-bold text-gray-900">{{ formatMoney(store.dashboard.todayRevenue || 0) }}</p>
                    </div>
                    <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center text-2xl">💰</div>
                </div>
            </div>
            <div class="card p-6 border-l-4 border-l-purple-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Средний чек</p>
                        <p class="text-3xl font-bold text-gray-900">{{ formatMoney(store.dashboard.avgCheck || 0) }}</p>
                    </div>
                    <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center text-2xl">🧾</div>
                </div>
            </div>
            <div class="card p-6 border-l-4 border-l-orange-500 hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">На смене</p>
                        <p class="text-3xl font-bold text-gray-900">{{ store.dashboard.activeStaff || 0 }}</p>
                    </div>
                    <div class="w-14 h-14 bg-orange-100 rounded-2xl flex items-center justify-center text-2xl">👥</div>
                </div>
            </div>
        </div>

        <!-- Charts placeholder -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Продажи за неделю</h3>
                <div class="h-64 flex items-center justify-center text-gray-400">📊 График продаж</div>
            </div>
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Топ блюд</h3>
                <div class="space-y-3">
                    <div v-for="i in 5" :key="i" class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-sm font-bold">{{ i }}</div>
                        <div class="flex-1">
                            <div class="h-4 bg-gray-100 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const formatMoney = (amount) => new Intl.NumberFormat('ru-RU').format(amount) + ' ₽';
const formatTime = (date) => date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

onMounted(() => {
    if (!store.dashboard.todayOrders) store.loadDashboard();
});
</script>
