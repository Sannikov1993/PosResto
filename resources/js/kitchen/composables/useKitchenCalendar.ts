/**
 * Kitchen Calendar Composable
 *
 * Provides calendar functionality for date selection
 * with order counts display.
 *
 * @module kitchen/composables/useKitchenCalendar
 */

import { computed, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useOrdersStore } from '../stores/orders.js';
import { useDeviceStore } from '../stores/device.js';
import { useUiStore } from '../stores/ui.js';
import {
    getLocalDateString,
    getTodayString,
    getTomorrowString,
    isPastDate,
} from '../utils/time.js';
import { formatMonthYear, WEEKDAY_NAMES } from '../utils/format.js';
import type { CalendarDay } from '../types/index.js';

export function useKitchenCalendar() {
    const ordersStore = useOrdersStore();
    const deviceStore = useDeviceStore();
    const uiStore = useUiStore();

    const calendarViewDate = ref(new Date());

    const { selectedDate, orderCountsByDate, isSelectedDateToday } = storeToRefs(ordersStore);
    const { showCalendarPicker } = storeToRefs(uiStore);

    const calendarMonthYear = computed(() => {
        return formatMonthYear(calendarViewDate.value);
    });

    const weekdayNames = computed(() => WEEKDAY_NAMES);

    const calendarDays = computed((): CalendarDay[] => {
        const year = calendarViewDate.value.getFullYear();
        const month = calendarViewDate.value.getMonth();
        const todayStr = getTodayString();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        let startDay = firstDay.getDay() - 1;
        if (startDay < 0) startDay = 6;

        const days: CalendarDay[] = [];

        for (let i = 0; i < startDay; i++) {
            days.push({ day: '', date: null, count: 0 });
        }

        for (let d = 1; d <= lastDay.getDate(); d++) {
            const date = new Date(year, month, d);
            const dateStr = getLocalDateString(date);

            days.push({
                day: d,
                date: dateStr,
                isToday: dateStr === todayStr,
                isSelected: dateStr === selectedDate.value,
                isPast: isPastDate(dateStr),
                count: orderCountsByDate.value[dateStr] || 0,
            });
        }

        return days;
    });

    function toggleCalendar(): void {
        uiStore.toggleCalendarPicker();

        if (showCalendarPicker.value) {
            const [year, month] = selectedDate.value.split('-').map(Number);
            calendarViewDate.value = new Date(year, month - 1, 1);
            loadOrderCounts();
        }
    }

    function closeCalendar(): void {
        uiStore.closeCalendarPicker();
    }

    function previousMonth(): void {
        const date = new Date(calendarViewDate.value);
        date.setMonth(date.getMonth() - 1);
        calendarViewDate.value = date;
        loadOrderCounts();
    }

    function nextMonth(): void {
        const date = new Date(calendarViewDate.value);
        date.setMonth(date.getMonth() + 1);
        calendarViewDate.value = date;
        loadOrderCounts();
    }

    function selectDate(dateStr: string): void {
        ordersStore.setSelectedDate(dateStr);
        closeCalendar();
    }

    function selectToday(): void {
        selectDate(getTodayString());
    }

    function selectTomorrow(): void {
        selectDate(getTomorrowString());
    }

    async function loadOrderCounts(): Promise<void> {
        const year = calendarViewDate.value.getFullYear();
        const month = calendarViewDate.value.getMonth();
        const startDate = getLocalDateString(new Date(year, month, 1));
        const endDate = getLocalDateString(new Date(year, month + 1, 0));

        await ordersStore.fetchOrderCounts(
            deviceStore.deviceId!,
            startDate,
            endDate,
            deviceStore.stationSlug || undefined
        );
    }

    watch(calendarViewDate, () => {
        if (showCalendarPicker.value) {
            loadOrderCounts();
        }
    });

    return {
        selectedDate,
        calendarViewDate,
        showCalendarPicker,
        isSelectedDateToday,
        orderCountsByDate,
        calendarMonthYear,
        weekdayNames,
        calendarDays,
        toggleCalendar,
        closeCalendar,
        previousMonth,
        nextMonth,
        selectDate,
        selectToday,
        selectTomorrow,
        loadOrderCounts,
    };
}
