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

        <!-- Stats Summary -->
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ stats.total_hours || 0 }}</div>
                <div class="text-xs text-gray-500">Часов</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ stats.days_worked || 0 }}</div>
                <div class="text-xs text-gray-500">Дней</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-purple-600">{{ stats.avg_hours_per_day || 0 }}</div>
                <div class="text-xs text-gray-500">Среднее/день</div>
            </div>
        </div>

        <!-- Sessions List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                История смен
            </div>

            <div v-if="sessions.length" class="divide-y">
                <div v-for="session in sessions" :key="session.id"
                     class="p-4 flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900">{{ formatDate(session.clock_in) }}</div>
                        <div class="text-sm text-gray-500">
                            {{ formatTime(session.clock_in) }}
                            <span v-if="session.clock_out"> - {{ formatTime(session.clock_out) }}</span>
                            <span v-else class="text-green-500">(на смене)</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold" :class="session.clock_out ? 'text-gray-900' : 'text-green-500'">
                            {{ session.hours_worked ? session.hours_worked.toFixed(1) : '...' }}ч
                        </div>
                        <div v-if="session.status === 'corrected'" class="text-xs text-yellow-600">
                            Скорректировано
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-8 text-center text-gray-400">
                Нет записей за этот период
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

<script setup>
import { ref, computed, onMounted, inject } from 'vue';

const api = inject('api');

const loading = ref(false);
const monthOffset = ref(0);
const sessions = ref([]);
const stats = ref({});

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

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    return `${days[d.getDay()]}, ${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

async function loadTimesheet() {
    loading.value = true;
    try {
        const d = currentMonth.value;
        const startDate = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        const endDate = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0];

        const res = await api(`/cabinet/timesheet?start_date=${startDate}&end_date=${endDate}`);
        sessions.value = res.data?.sessions || [];
        stats.value = res.data?.stats || {};
    } catch (e) {
        console.error('Failed to load timesheet:', e);
    } finally {
        loading.value = false;
    }
}

function prevMonth() {
    monthOffset.value--;
    loadTimesheet();
}

function nextMonth() {
    monthOffset.value++;
    loadTimesheet();
}

onMounted(() => {
    loadTimesheet();
});
</script>
