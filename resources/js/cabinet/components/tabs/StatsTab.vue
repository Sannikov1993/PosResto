<template>
    <div class="space-y-4">
        <!-- Period Selector -->
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
            <button @click="prevMonth" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <div class="text-center">
                <div class="font-semibold text-gray-900">{{ monthLabel }}</div>
            </div>
            <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <!-- Main Stats -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-3xl font-bold text-blue-600">{{ stats.orders?.count || 0 }}</div>
                <div class="text-sm text-gray-500">Заказов</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-3xl font-bold text-green-600">{{ formatMoney(stats.orders?.total || 0) }}</div>
                <div class="text-sm text-gray-500">Выручка</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-3xl font-bold text-purple-600">{{ formatMoney(stats.orders?.average || 0) }}</div>
                <div class="text-sm text-gray-500">Средний чек</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-3xl font-bold text-yellow-600">{{ formatMoney(stats.tips || 0) }}</div>
                <div class="text-sm text-gray-500">Чаевые</div>
            </div>
        </div>

        <!-- Work Stats -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Рабочее время</h3>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-xl font-bold text-gray-900">{{ stats.work?.total_hours || 0 }}</div>
                    <div class="text-xs text-gray-500">часов</div>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-900">{{ stats.work?.days_worked || 0 }}</div>
                    <div class="text-xs text-gray-500">дней</div>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-900">{{ stats.work?.avg_hours_per_day || 0 }}</div>
                    <div class="text-xs text-gray-500">среднее/день</div>
                </div>
            </div>
        </div>

        <!-- Performance -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Эффективность</h3>
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Заказов в час</span>
                        <span class="font-medium">{{ ordersPerHour }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full transition-all"
                             :style="{ width: Math.min(Number(ordersPerHour) * 20, 100) + '%' }"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Выручка в час</span>
                        <span class="font-medium">{{ formatMoney(revenuePerHour) }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 rounded-full transition-all"
                             :style="{ width: Math.min(revenuePerHour / 100, 100) + '%' }"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Чаевые в час</span>
                        <span class="font-medium">{{ formatMoney(tipsPerHour) }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-yellow-500 rounded-full transition-all"
                             :style="{ width: Math.min(tipsPerHour / 10, 100) + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Chart placeholder -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">По дням</h3>
            <div v-if="Object.keys(stats.orders_by_day || {}).length" class="space-y-2">
                <div v-for="(day, date) in stats.orders_by_day" :key="date"
                     class="flex items-center gap-3">
                    <div class="w-16 text-sm text-gray-500">{{ formatDate(date) }}</div>
                    <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full"
                             :style="{ width: getBarWidth(day.total) + '%' }"></div>
                    </div>
                    <div class="w-20 text-right text-sm font-medium">{{ formatMoney(day.total) }}</div>
                </div>
            </div>
            <div v-else class="text-center text-gray-400 py-4">
                Нет данных за период
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-4 shadow-lg">
                <div class="animate-spin w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, inject } from 'vue';

const props = defineProps({
    user: Object,
});

const api = inject('api');

const loading = ref(false);
const monthOffset = ref(0);
const stats = ref<Record<string, any>>({});

const currentMonth = computed(() => {
    const d = new Date();
    d.setMonth(d.getMonth() + monthOffset.value);
    return d;
});

const monthLabel = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                   'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[currentMonth.value.getMonth()]} ${currentMonth.value.getFullYear()}`;
});

const ordersPerHour = computed(() => {
    const hours = stats.value.work?.total_hours || 0;
    const orders = stats.value.orders?.count || 0;
    return hours > 0 ? (orders / hours).toFixed(1) : 0;
});

const revenuePerHour = computed(() => {
    const hours = stats.value.work?.total_hours || 0;
    const total = stats.value.orders?.total || 0;
    return hours > 0 ? Math.round(total / hours) : 0;
});

const tipsPerHour = computed(() => {
    const hours = stats.value.work?.total_hours || 0;
    const tips = stats.value.tips || 0;
    return hours > 0 ? Math.round(tips / hours) : 0;
});

const maxDayTotal = computed(() => {
    const days = stats.value.orders_by_day || {};
    return Math.max(...Object.values(days).map((d: any) => d.total || 0), 1);
});

function formatMoney(amount: any) {
    return new Intl.NumberFormat('ru-RU').format(amount || 0) + ' ₽';
}

function formatDate(dateStr: any) {
    const d = new Date(dateStr);
    return `${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function getBarWidth(total: any) {
    return Math.round((total / maxDayTotal.value) * 100);
}

async function loadStats() {
    loading.value = true;
    try {
        const d = currentMonth.value;
        const startDate = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        const endDate = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0];

        const res = await (api as any)(`/cabinet/stats?start_date=${startDate}&end_date=${endDate}`);
        stats.value = res.data || {};
    } catch (e: any) {
        console.error('Failed to load stats:', e);
    } finally {
        loading.value = false;
    }
}

function prevMonth() {
    monthOffset.value--;
    loadStats();
}

function nextMonth() {
    monthOffset.value++;
    loadStats();
}

onMounted(() => {
    loadStats();
});
</script>
