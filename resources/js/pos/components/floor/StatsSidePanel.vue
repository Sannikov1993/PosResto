<template>
    <div class="stats-panel">
        <!-- Statistics Section -->
        <div class="stats-section">
            <!-- Yesterday -->
            <div class="stat-row">
                <span class="stat-label">Вчера</span>
                <span class="stat-orders">{{ yesterdayStats.orders_count }}</span>
                <span class="stat-amount">{{ formatMoney(yesterdayStats.total) }}<small>.{{ formatCents(yesterdayStats.total) }}</small></span>
            </div>

            <!-- Today -->
            <div class="stat-row today">
                <span class="stat-dot"></span>
                <span class="stat-label">Сегодня</span>
                <span class="stat-orders">
                    <span class="text-cyan-400">{{ todayStats.orders_count }}</span>
                    <span class="divider">|</span>
                    <span>{{ todayStats.reservations_count }}</span>
                </span>
                <span class="stat-amount">{{ formatMoney(todayStats.total) }}<small>.{{ formatCents(todayStats.total) }}</small></span>
            </div>

            <!-- Reservations -->
            <div class="stat-row">
                <span class="stat-label">Бронь</span>
                <span class="stat-orders reservation-count">{{ todayStats.pending_reservations }}</span>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="calendar-section">
            <!-- Month Navigation -->
            <div class="month-nav">
                <button @click="prevMonth" class="nav-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
                <span class="month-label">{{ monthNames[calendarMonth] }}, {{ calendarYear }}</span>
                <button @click="nextMonth" class="nav-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </button>
            </div>

            <!-- Weekdays -->
            <div class="weekdays">
                <span v-for="(day, idx) in weekDays" :key="day" :class="{ weekend: idx >= 5 }">{{ day }}</span>
            </div>

            <!-- Days Grid -->
            <div class="days-grid">
                <!-- Previous month days -->
                <div v-for="day in prevMonthDays" :key="'prev-' + day" class="day other-month">
                    <span class="day-num">{{ day }}</span>
                    <span v-if="getDayData('prev', day).count" class="day-count">{{ getDayData('prev', day).count }}</span>
                </div>

                <!-- Current month days -->
                <div
                    v-for="day in daysInMonth"
                    :key="day"
                    :class="getDayClasses(day)"
                    @click="selectDay(day)"
                >
                    <span class="day-num">{{ day }}</span>
                    <span v-if="getDayData('current', day).count" class="day-count">{{ getDayData('current', day).count }}</span>
                </div>

                <!-- Next month days -->
                <div v-for="day in nextMonthDays" :key="'next-' + day" class="day other-month">
                    <span class="day-num">{{ day }}</span>
                    <span v-if="getDayData('next', day).count" class="day-count">{{ getDayData('next', day).count }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('StatsSidePanel');

const props = defineProps({
    selectedDate: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['change']);

// State
const calendarMonth = ref(new Date().getMonth());
const calendarYear = ref(new Date().getFullYear());
const calendarData = ref<Record<string, any>>({});
const statsData = ref({
    yesterday: { orders_count: 0, total: 0 },
    today: { orders_count: 0, total: 0, reservations_count: 0, pending_reservations: 0 }
});

// Constants
const weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const monthNames = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];

// Computed
const todayDate = computed(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
});

const yesterdayStats = computed(() => statsData.value.yesterday || { orders_count: 0, total: 0 });
const todayStats = computed(() => statsData.value.today || { orders_count: 0, total: 0, reservations_count: 0, pending_reservations: 0 });

const daysInMonth = computed(() => {
    return new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate();
});

const firstDayOffset = computed(() => {
    const firstDay = new Date(calendarYear.value, calendarMonth.value, 1).getDay();
    return firstDay === 0 ? 6 : firstDay - 1;
});

const prevMonthDaysCount = computed(() => {
    return new Date(calendarYear.value, calendarMonth.value, 0).getDate();
});

const prevMonthDays = computed(() => {
    const days = [];
    for (let i = firstDayOffset.value - 1; i >= 0; i--) {
        days.push(prevMonthDaysCount.value - i);
    }
    return days;
});

const nextMonthDays = computed(() => {
    const totalCells = 42;
    const currentCells = firstDayOffset.value + daysInMonth.value;
    const remaining = totalCells - currentCells;
    const days = [];
    for (let i = 1; i <= remaining; i++) {
        days.push(i);
    }
    return days;
});

// Methods
const formatMoney = (n: any) => {
    if (!n) return '0';
    return Math.floor(n).toLocaleString('ru-RU');
};

const formatCents = (n: any) => {
    if (!n) return '00';
    const cents = Math.round((n % 1) * 100);
    return String(cents).padStart(2, '0');
};

const formatDateStr = (year: any, month: any, day: any) => {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const prevMonth = () => {
    if (calendarMonth.value === 0) {
        calendarMonth.value = 11;
        calendarYear.value--;
    } else {
        calendarMonth.value--;
    }
    loadCalendarData();
};

const nextMonth = () => {
    if (calendarMonth.value === 11) {
        calendarMonth.value = 0;
        calendarYear.value++;
    } else {
        calendarMonth.value++;
    }
    loadCalendarData();
};

const selectDay = (day: any) => {
    const dateStr = formatDateStr(calendarYear.value, calendarMonth.value, day);
    emit('change', dateStr);
};

const isTodayDay = (day: any) => {
    return (
        todayDate.value.getDate() === day &&
        todayDate.value.getMonth() === calendarMonth.value &&
        todayDate.value.getFullYear() === calendarYear.value
    );
};

const isSelectedDay = (day: any) => {
    const dateStr = formatDateStr(calendarYear.value, calendarMonth.value, day);
    return props.selectedDate === dateStr;
};

const isWeekend = (day: any) => {
    const dayIndex = (firstDayOffset.value + day - 1) % 7;
    return dayIndex === 5 || dayIndex === 6;
};

const getDayClasses = (day: any) => {
    const classes = ['day'];
    if (isTodayDay(day)) classes.push('today');
    if (isSelectedDay(day)) classes.push('selected');
    if (isWeekend(day)) classes.push('weekend');
    const data = getDayData('current', day);
    if (data.count > 0) classes.push('has-data');
    return classes;
};

const getDayData = (month: any, day: any) => {
    let year = calendarYear.value;
    let monthNum = calendarMonth.value;

    if (month === 'prev') {
        if (calendarMonth.value === 0) {
            year--;
            monthNum = 11;
        } else {
            monthNum--;
        }
    } else if (month === 'next') {
        if (calendarMonth.value === 11) {
            year++;
            monthNum = 0;
        } else {
            monthNum++;
        }
    }

    const dateStr = formatDateStr(year, monthNum, day);
    return calendarData.value[dateStr] || { count: 0, total: 0 };
};

const loadCalendarData = async () => {
    try {
        const response = await api.reservations.getCalendar(calendarYear.value, calendarMonth.value + 1);
        const data = {};
        if (response && response.days) {
            (response.days as any).forEach((day: any) => {
                const count = (day.orders_count || 0) + (day.reservations_count || 0);
                if (count > 0) {
                    (data as Record<string, any>)[day.date] = {
                        count: count,
                        orders: day.orders_count || 0,
                        reservations: day.reservations_count || 0,
                        total: day.total || 0
                    };
                }
            });
        }
        calendarData.value = data;
    } catch (e: any) {
        log.error('Failed to load calendar data:', e);
        calendarData.value = {};
    }
};

const loadStats = async () => {
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.dashboard.getBriefStats();
        statsData.value = response?.data || response || {} as any;
    } catch (e: any) {
        log.error('Failed to load stats:', e);
    }
};

// Watchers
watch(() => props.selectedDate, (newVal) => {
    const d = new Date(newVal);
    if (d.getMonth() !== calendarMonth.value || d.getFullYear() !== calendarYear.value) {
        calendarMonth.value = d.getMonth();
        calendarYear.value = d.getFullYear();
        loadCalendarData();
    }
});

// Lifecycle
onMounted(() => {
    const d = new Date(props.selectedDate);
    calendarMonth.value = d.getMonth();
    calendarYear.value = d.getFullYear();
    loadCalendarData();
    loadStats();
});
</script>

<style scoped>
.stats-panel {
    width: 220px;
    background: #1a2332;
    border-left: 1px solid rgba(255,255,255,0.06);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    flex-shrink: 0;
}

/* Statistics Section */
.stats-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stat-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-row.today {
    position: relative;
    padding-left: 12px;
}

.stat-dot {
    position: absolute;
    left: 0;
    width: 6px;
    height: 6px;
    background: #f97316;
    border-radius: 50%;
}

.stat-label {
    color: #94a3b8;
    font-size: 13px;
    min-width: 60px;
}

.stat-orders {
    color: #e2e8f0;
    font-size: 14px;
    min-width: 40px;
}

.stat-orders .divider {
    color: #475569;
    margin: 0 4px;
}

.stat-orders.reservation-count {
    color: #64748b;
}

.stat-amount {
    margin-left: auto;
    color: #e2e8f0;
    font-size: 14px;
    font-weight: 500;
}

.stat-amount small {
    color: #64748b;
    font-size: 11px;
}

/* Calendar Section */
.calendar-section {
    flex: 1;
}

.month-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.nav-btn {
    width: 24px;
    height: 24px;
    background: transparent;
    border: none;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.15s;
}

.nav-btn:hover {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.nav-btn svg {
    width: 16px;
    height: 16px;
}

.month-label {
    color: #22d3ee;
    font-size: 14px;
    font-weight: 500;
}

/* Weekdays */
.weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 4px;
}

.weekdays span {
    text-align: center;
    font-size: 10px;
    color: #475569;
    padding: 4px 0;
    font-weight: 500;
}

.weekdays span.weekend {
    color: #f97316;
}

/* Days Grid */
.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
    gap: 1px;
    position: relative;
}

.day:hover:not(.other-month) {
    background: rgba(59, 130, 246, 0.08);
}

.day-num {
    font-size: 12px;
    color: #cbd5e1;
    font-weight: 500;
    line-height: 1;
}

.day-count {
    font-size: 8px;
    color: #64748b;
    line-height: 1;
}

.day.weekend .day-num {
    color: #f97316;
}

.day.other-month {
    cursor: default;
}

.day.other-month .day-num {
    color: #334155;
}

.day.other-month .day-count {
    color: #334155;
}

.day.today {
    background: rgba(34, 211, 238, 0.15);
}

.day.today .day-num {
    color: #22d3ee;
    font-weight: 600;
}

.day.selected {
    background: #334155;
    border: 1px solid rgba(255,255,255,0.1);
}

.day.selected .day-num {
    color: white;
    font-weight: 600;
}

.day.selected .day-count {
    color: rgba(255,255,255,0.6);
}

.day.has-data:not(.other-month) .day-count {
    color: #94a3b8;
}
</style>
