<template>
    <div class="inline-calendar">
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
                    'h-8 w-8 rounded-lg text-xs font-medium transition-colors flex flex-col items-center justify-center relative',
                    day.isToday && !day.isSelected ? 'ring-1 ring-accent' : '',
                    day.isSelected ? 'bg-accent text-white' : '',
                    day.isCurrentMonth && !day.disabled && !day.isSelected ? 'text-gray-300 hover:bg-dark-700' : '',
                    !day.isCurrentMonth ? 'text-gray-700' : '',
                    day.disabled ? 'text-gray-700 cursor-not-allowed' : '',
                    day.hasReservations && !day.isSelected && !day.disabled ? 'bg-blue-500/10' : ''
                ]"
            >
                <span>{{ day.day }}</span>
                <span v-if="day.reservationCount > 0" :class="['text-[9px] leading-none', day.isSelected ? 'text-white/80' : 'text-blue-400']">
                    {{ day.reservationCount }}
                </span>
            </button>
        </div>

        <!-- Quick Select Buttons -->
        <div class="flex gap-2 mt-3 pt-3 border-t border-dark-700">
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
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { getLocalDateString } from '../../../utils/timezone';
import api from '../../api';

// Get today's date object in timezone (for calculations)
const getTodayInTimezone = () => {
    const todayStr = getLocalDateString();
    const [year, month, day] = todayStr.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    minDate: {
        type: String,
        default: ''
    },
    reservationsData: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

// State - initialize calendar to today in restaurant's timezone
const calendarDate = ref(getTodayInTimezone());
const calendarData = ref({});

// Helpers
const formatDateForInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Computed
const minDateObj = computed(() => {
    if (!props.minDate) return null;
    const d = new Date(props.minDate);
    d.setHours(0, 0, 0, 0);
    return d;
});

const calendarMonthYear = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[calendarDate.value.getMonth()]} ${calendarDate.value.getFullYear()}`;
});

const calendarDays = computed(() => {
    const year = calendarDate.value.getFullYear();
    const month = calendarDate.value.getMonth();
    const today = getTodayInTimezone();

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

        // Check if disabled (before minDate)
        let isDisabled = false;
        if (minDateObj.value && date < minDateObj.value) {
            isDisabled = true;
        }

        days.push({
            day: i,
            date: dateStr,
            isCurrentMonth: true,
            isToday: date.getTime() === today.getTime(),
            isSelected: props.modelValue === dateStr,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: isDisabled,
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

// Quick date checks (timezone-aware)
const isSelectedToday = computed(() => {
    return props.modelValue === getLocalDateString();
});

const isSelectedTomorrow = computed(() => {
    const today = getTodayInTimezone();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    return props.modelValue === formatDateForInput(tomorrow);
});

const isSelectedDayAfter = computed(() => {
    const today = getTodayInTimezone();
    const dayAfter = new Date(today);
    dayAfter.setDate(dayAfter.getDate() + 2);
    return props.modelValue === formatDateForInput(dayAfter);
});

// Methods
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
};

const selectQuickDate = (type) => {
    const date = getTodayInTimezone();
    if (type === 'tomorrow') {
        date.setDate(date.getDate() + 1);
    } else if (type === 'dayafter') {
        date.setDate(date.getDate() + 2);
    }
    const dateStr = formatDateForInput(date);

    // Check minDate
    if (minDateObj.value) {
        const selectedDate = new Date(dateStr);
        if (selectedDate < minDateObj.value) return;
    }

    emit('update:modelValue', dateStr);
    emit('change', dateStr);
};

const loadCalendarData = async () => {
    try {
        const year = calendarDate.value.getFullYear();
        const month = calendarDate.value.getMonth() + 1;
        const response = await api.reservations.getCalendar(year, month);

        // Interceptor бросит исключение при success: false
        const days = response?.data?.days || response?.days || [];
        const counts = {};
        days.forEach(day => {
            if (day.reservations_count > 0) {
                counts[day.date] = day.reservations_count;
            }
        });
        calendarData.value = counts;
    } catch (e) {
        console.error('Failed to load calendar data:', e);
        calendarData.value = {};
    }
};

// Watchers
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        const d = new Date(newVal);
        if (d.getMonth() !== calendarDate.value.getMonth() || d.getFullYear() !== calendarDate.value.getFullYear()) {
            calendarDate.value = d;
            loadCalendarData();
        }
    }
});

// Lifecycle
onMounted(() => {
    if (props.modelValue) {
        calendarDate.value = new Date(props.modelValue);
    }
    loadCalendarData();
});
</script>

<style scoped>
.inline-calendar {
    padding: 4px;
}
</style>
