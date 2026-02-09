<template>
    <div class="relative">
        <!-- Trigger Button -->
        <button
            @click="toggleCalendar"
            class="flex items-center justify-center gap-2 py-2 px-4 rounded-xl text-sm font-medium transition-all bg-dark-800 hover:bg-dark-700"
            :class="showCalendar ? 'ring-2 ring-accent' : ''"
        >
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-white">{{ displayDate }}</span>
            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Backdrop -->
        <div
            v-if="showCalendar"
            class="fixed inset-0 z-[9998]"
            @click="closeCalendar"
        ></div>

        <!-- Calendar Panel -->
        <Transition name="popup">
            <div v-if="showCalendar" class="absolute top-full right-0 mt-1 bg-dark-800 rounded-xl p-4 shadow-2xl z-[9999] border border-dark-700/50 w-[300px]" @click.stop>
                <!-- Calendar Header -->
                <div class="flex items-center justify-between mb-4">
                    <button
                        @click="prevMonth"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-dark-700 hover:text-white transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <span class="text-white font-semibold text-sm">{{ calendarMonthYear }}</span>
                    <button
                        @click="nextMonth"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-dark-700 hover:text-white transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Weekdays -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <div v-for="day in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="day" class="text-center text-xs text-gray-500 py-1">
                        {{ day }}
                    </div>
                </div>

                <!-- Days Grid -->
                <div class="grid grid-cols-7 gap-1">
                    <button
                        v-for="day in calendarDays"
                        :key="day.date"
                        @click="selectDate(day)"
                        :disabled="day.disabled"
                        :class="[
                            'h-9 w-9 rounded-lg text-xs font-medium transition-colors flex flex-col items-center justify-center relative',
                            day.isToday && !day.isSelected ? 'ring-1 ring-accent' : '',
                            day.isSelected ? 'bg-accent text-white' : '',
                            day.isCurrentMonth && !day.disabled && !day.isSelected && !day.isPast ? 'text-gray-300 hover:bg-dark-700' : '',
                            day.isCurrentMonth && day.isPast && !day.isSelected ? 'text-gray-500 hover:bg-dark-700' : '',
                            !day.isCurrentMonth ? 'text-gray-700' : '',
                            day.disabled ? 'text-gray-700 cursor-not-allowed' : '',
                            day.hasReservations && !day.isSelected && !day.disabled ? 'bg-blue-500/10' : ''
                        ]"
                    >
                        <span>{{ day.day }}</span>
                        <span v-if="day.reservationCount > 0" :class="['text-[11px] leading-none font-semibold', day.isSelected ? 'text-white/80' : 'text-blue-400']">
                            {{ day.reservationCount }}
                        </span>
                    </button>
                </div>

                <!-- Quick Select Buttons -->
                <div class="flex gap-2 mt-3 pt-3 border-t border-dark-700">
                    <button
                        @click="selectQuickDate('yesterday')"
                        :class="[
                            'flex-1 py-1.5 text-xs rounded-lg font-medium transition-colors',
                            isSelectedYesterday ? 'bg-accent text-white' : 'bg-dark-700 text-gray-300 hover:bg-gray-600'
                        ]"
                    >
                        Вчера
                    </button>
                    <button
                        @click="selectQuickDate('today')"
                        :class="[
                            'flex-1 py-1.5 text-xs rounded-lg font-medium transition-colors',
                            isSelectedToday ? 'bg-accent text-white' : 'bg-dark-700 text-gray-300 hover:bg-gray-600'
                        ]"
                    >
                        Сегодня
                    </button>
                    <button
                        @click="selectQuickDate('tomorrow')"
                        :class="[
                            'flex-1 py-1.5 text-xs rounded-lg font-medium transition-colors',
                            isSelectedTomorrow ? 'bg-accent text-white' : 'bg-dark-700 text-gray-300 hover:bg-gray-600'
                        ]"
                    >
                        Завтра
                    </button>
                    <button
                        @click="selectQuickDate('dayafter')"
                        :class="[
                            'flex-1 py-1.5 text-xs rounded-lg font-medium transition-colors',
                            isSelectedDayAfter ? 'bg-accent text-white' : 'bg-dark-700 text-gray-300 hover:bg-gray-600'
                        ]"
                    >
                        Послезавтра
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('ReservationCalendar');

const props = defineProps({
    modelValue: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const showCalendar = ref(false);
const calendarDate = ref(new Date());
const calendarData = ref({});

// Date formatting helper
const formatDateForInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Display date
const displayDate = computed(() => {
    if (!props.modelValue) return 'Выберите дату';

    const today = formatDateForInput(new Date());
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = formatDateForInput(tomorrow);

    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayStr = formatDateForInput(yesterday);

    if (props.modelValue === yesterdayStr) return 'Вчера';
    if (props.modelValue === today) return 'Сегодня';
    if (props.modelValue === tomorrowStr) return 'Завтра';

    const date = new Date(props.modelValue);
    const day = date.getDate();
    const months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${day} ${months[date.getMonth()]}`;
});

// Calendar computed
const calendarMonthYear = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[calendarDate.value.getMonth()]} ${calendarDate.value.getFullYear()}`;
});

const calendarDays = computed(() => {
    const year = calendarDate.value.getFullYear();
    const month = calendarDate.value.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Get the day of week for first day (Monday = 0)
    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days = [];

    // Previous month days
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = startDay - 1; i >= 0; i--) {
        const date = new Date(year, month - 1, prevMonthLastDay - i);
        days.push({
            day: prevMonthLastDay - i,
            date: formatDateForInput(date),
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: true,
            hasReservations: false,
            reservationCount: 0
        });
    }

    // Current month days
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const date = new Date(year, month, i);
        date.setHours(0, 0, 0, 0);
        const dateStr = formatDateForInput(date);
        const reservationCount = calendarData.value[dateStr] || 0;
        days.push({
            day: i,
            date: dateStr,
            isCurrentMonth: true,
            isToday: date.getTime() === today.getTime(),
            isSelected: props.modelValue === dateStr,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            isPast: date < today,
            disabled: false,
            hasReservations: reservationCount > 0,
            reservationCount: reservationCount
        });
    }

    // Next month days to fill the grid
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        const date = new Date(year, month + 1, i);
        days.push({
            day: i,
            date: formatDateForInput(date),
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: true,
            hasReservations: false,
            reservationCount: 0
        });
    }

    return days;
});

// Quick date checks
const isSelectedToday = computed(() => {
    return props.modelValue === formatDateForInput(new Date());
});

const isSelectedTomorrow = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return props.modelValue === formatDateForInput(tomorrow);
});

const isSelectedDayAfter = computed(() => {
    const dayAfter = new Date();
    dayAfter.setDate(dayAfter.getDate() + 2);
    return props.modelValue === formatDateForInput(dayAfter);
});

const isSelectedYesterday = computed(() => {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    return props.modelValue === formatDateForInput(yesterday);
});

// Methods
const toggleCalendar = () => {
    showCalendar.value = !showCalendar.value;
    if (showCalendar.value) {
        if (props.modelValue) {
            calendarDate.value = new Date(props.modelValue);
        }
        loadCalendarData();
    }
};

const closeCalendar = () => {
    showCalendar.value = false;
};

const prevMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() - 1);
    calendarDate.value = newDate;
    loadCalendarData();
};

const nextMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() + 1);
    calendarDate.value = newDate;
    loadCalendarData();
};

const selectDate = (day) => {
    if (day.disabled || !day.isCurrentMonth) return;
    emit('update:modelValue', day.date);
    emit('change', day.date);
    showCalendar.value = false;
};

const selectQuickDate = (type) => {
    const date = new Date();
    if (type === 'yesterday') {
        date.setDate(date.getDate() - 1);
    } else if (type === 'tomorrow') {
        date.setDate(date.getDate() + 1);
    } else if (type === 'dayafter') {
        date.setDate(date.getDate() + 2);
    }
    const dateStr = formatDateForInput(date);
    emit('update:modelValue', dateStr);
    emit('change', dateStr);
    showCalendar.value = false;
};

const loadCalendarData = async () => {
    try {
        const year = calendarDate.value.getFullYear();
        const month = calendarDate.value.getMonth() + 1;
        const response = await api.reservations.getCalendar(year, month);
        const data = {};
        if (response && response.days) {
            response.days.forEach(day => {
                if (day.reservations_count > 0) {
                    data[day.date] = day.reservations_count;
                }
            });
        }
        calendarData.value = data;
    } catch (e) {
        log.error('Failed to load calendar data:', e);
        // Keep previous data on error (don't reset to empty)
    }
};

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        const d = new Date(newVal);
        if (d.getMonth() !== calendarDate.value.getMonth() || d.getFullYear() !== calendarDate.value.getFullYear()) {
            calendarDate.value = d;
            loadCalendarData();
        }
    }
});

onMounted(() => {
    if (props.modelValue) {
        calendarDate.value = new Date(props.modelValue);
    }
    loadCalendarData();
});
</script>

<style scoped>
.popup-enter-active,
.popup-leave-active {
    transition: all 0.2s ease;
}
.popup-enter-from,
.popup-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
