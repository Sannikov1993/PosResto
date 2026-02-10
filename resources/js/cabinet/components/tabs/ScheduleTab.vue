<template>
    <div class="space-y-4">
        <!-- Week Navigation -->
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
            <button @click="prevWeek" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <div class="text-center">
                <div class="font-semibold text-gray-900">{{ weekLabel }}</div>
                <div class="text-sm text-gray-500">{{ monthLabel }}</div>
            </div>
            <button @click="nextWeek" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <!-- Week Days -->
        <div class="space-y-2">
            <div v-for="day in weekDays" :key="day.date"
                 :class="['bg-white rounded-xl shadow-sm overflow-hidden',
                          day.isToday ? 'ring-2 ring-orange-500' : '']">
                <!-- Day Header -->
                <div :class="['px-4 py-2 flex items-center justify-between',
                              day.isToday ? 'bg-orange-50' : 'bg-gray-50']">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold" :class="day.isToday ? 'text-orange-600' : 'text-gray-700'">
                            {{ day.dayName }}
                        </span>
                        <span class="text-sm text-gray-500">{{ day.dateFormatted }}</span>
                    </div>
                    <span v-if="day.isToday" class="text-xs bg-orange-500 text-white px-2 py-0.5 rounded-full">
                        Сегодня
                    </span>
                </div>

                <!-- Shifts -->
                <div class="p-4">
                    <template v-if="getShiftsForDate(day.date).length">
                        <div v-for="shift in getShiftsForDate(day.date)" :key="shift.id"
                             class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <div class="w-1 h-10 rounded-full bg-orange-500"></div>
                                <div>
                                    <div class="font-medium text-gray-900">
                                        {{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}
                                    </div>
                                    <div v-if="shift.position" class="text-sm text-gray-500">{{ shift.position }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-orange-500">{{ shift.work_hours }}ч</div>
                                <div v-if="shift.break_minutes" class="text-xs text-gray-400">
                                    перерыв {{ shift.break_minutes }} мин
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-center text-gray-400 py-2">
                        Выходной
                    </div>
                </div>
            </div>
        </div>

        <!-- Month Summary -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Итого за период</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-orange-500">{{ totalHours }}</div>
                    <div class="text-sm text-gray-500">часов</div>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-500">{{ totalShifts }}</div>
                    <div class="text-sm text-gray-500">смен</div>
                </div>
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

const api = inject('api');

const loading = ref(false);
const weekOffset = ref(0);
const shifts = ref<any[]>([]);

const weekStart = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() - d.getDay() + 1 + (weekOffset.value * 7));
    d.setHours(0, 0, 0, 0);
    return d;
});

const weekLabel = computed(() => {
    const start = weekStart.value;
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    return `${start.getDate()} - ${end.getDate()}`;
});

const monthLabel = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                   'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[weekStart.value.getMonth()]} ${weekStart.value.getFullYear()}`;
});

const weekDays = computed(() => {
    const days = [];
    const dayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const today = new Date().toISOString().split('T')[0];

    for (let i = 0; i < 7; i++) {
        const d = new Date(weekStart.value);
        d.setDate(d.getDate() + i);
        const dateStr = d.toISOString().split('T')[0];

        days.push({
            date: dateStr,
            dayName: dayNames[i],
            dateFormatted: `${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`,
            isToday: dateStr === today,
            isWeekend: i >= 5,
        });
    }
    return days;
});

const totalHours = computed(() => {
    return shifts.value.reduce((sum: any, s: any) => sum + (parseFloat(s.work_hours) || 0), 0);
});

const totalShifts = computed(() => shifts.value.length);

function getShiftsForDate(date: any) {
    return shifts.value.filter((s: any) => s.date === date);
}

function formatTime(time: any) {
    if (!time) return '';
    return time.substring(0, 5);
}

async function loadSchedule() {
    loading.value = true;
    try {
        const startDate = weekStart.value.toISOString().split('T')[0];
        const end = new Date(weekStart.value);
        end.setDate(end.getDate() + 6);
        const endDate = end.toISOString().split('T')[0];

        const res = await (api as any)(`/cabinet/schedule?start_date=${startDate}&end_date=${endDate}`);
        shifts.value = res.data?.shifts || [];
    } catch (e: any) {
        console.error('Failed to load schedule:', e);
    } finally {
        loading.value = false;
    }
}

function prevWeek() {
    weekOffset.value--;
    loadSchedule();
}

function nextWeek() {
    weekOffset.value++;
    loadSchedule();
}

onMounted(() => {
    loadSchedule();
});
</script>
