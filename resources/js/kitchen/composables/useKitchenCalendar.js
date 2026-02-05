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

/**
 * Kitchen calendar composable
 * @returns {Object} Calendar composable
 */
export function useKitchenCalendar() {
    const ordersStore = useOrdersStore();
    const deviceStore = useDeviceStore();
    const uiStore = useUiStore();

    // Refs
    const calendarViewDate = ref(new Date());

    // Store refs
    const { selectedDate, orderCountsByDate, isSelectedDateToday } = storeToRefs(ordersStore);
    const { showCalendarPicker } = storeToRefs(uiStore);

    // ==================== Computed ====================

    /**
     * Month and year display string
     */
    const calendarMonthYear = computed(() => {
        return formatMonthYear(calendarViewDate.value);
    });

    /**
     * Weekday names for header
     */
    const weekdayNames = computed(() => WEEKDAY_NAMES);

    /**
     * Calendar days for current month view
     */
    const calendarDays = computed(() => {
        const year = calendarViewDate.value.getFullYear();
        const month = calendarViewDate.value.getMonth();
        const todayStr = getTodayString();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        // Get starting day (Monday = 0)
        let startDay = firstDay.getDay() - 1;
        if (startDay < 0) startDay = 6;

        const days = [];

        // Previous month padding
        for (let i = 0; i < startDay; i++) {
            days.push({ day: '', date: null, count: 0 });
        }

        // Current month days
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

    // ==================== Actions ====================

    /**
     * Toggle calendar picker visibility
     */
    function toggleCalendar() {
        uiStore.toggleCalendarPicker();

        if (showCalendarPicker.value) {
            // Sync calendar view with selected date
            const [year, month, day] = selectedDate.value.split('-').map(Number);
            calendarViewDate.value = new Date(year, month - 1, day);

            // Load order counts
            loadOrderCounts();
        }
    }

    /**
     * Close calendar picker
     */
    function closeCalendar() {
        uiStore.closeCalendarPicker();
    }

    /**
     * Go to previous month
     */
    function previousMonth() {
        const date = new Date(calendarViewDate.value);
        date.setMonth(date.getMonth() - 1);
        calendarViewDate.value = date;
        loadOrderCounts();
    }

    /**
     * Go to next month
     */
    function nextMonth() {
        const date = new Date(calendarViewDate.value);
        date.setMonth(date.getMonth() + 1);
        calendarViewDate.value = date;
        loadOrderCounts();
    }

    /**
     * Select a specific date
     * @param {string} dateStr - Date in YYYY-MM-DD format
     */
    function selectDate(dateStr) {
        ordersStore.setSelectedDate(dateStr);
        closeCalendar();
    }

    /**
     * Select today
     */
    function selectToday() {
        selectDate(getTodayString());
    }

    /**
     * Select tomorrow
     */
    function selectTomorrow() {
        selectDate(getTomorrowString());
    }

    /**
     * Load order counts for current calendar view month
     */
    async function loadOrderCounts() {
        const year = calendarViewDate.value.getFullYear();
        const month = calendarViewDate.value.getMonth();
        const startDate = getLocalDateString(new Date(year, month, 1));
        const endDate = getLocalDateString(new Date(year, month + 1, 0));

        await ordersStore.fetchOrderCounts(
            deviceStore.deviceId,
            startDate,
            endDate,
            deviceStore.stationSlug
        );
    }

    // Watch for calendar view date changes
    watch(calendarViewDate, () => {
        if (showCalendarPicker.value) {
            loadOrderCounts();
        }
    });

    return {
        // State
        selectedDate,
        calendarViewDate,
        showCalendarPicker,
        isSelectedDateToday,
        orderCountsByDate,

        // Computed
        calendarMonthYear,
        weekdayNames,
        calendarDays,

        // Actions
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
