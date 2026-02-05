<template>
    <div v-if="!focusMode" ref="containerRef" class="relative">
        <div :class="[
            'flex items-center bg-gray-700/50 rounded-xl',
            compact ? 'gap-0.5 p-0.5' : 'gap-1 p-1'
        ]">
            <button
                @click="$emit('prev-day')"
                :disabled="isSelectedDateToday"
                :class="[
                    'rounded-lg transition flex items-center justify-center',
                    compact ? 'w-10 h-10' : 'p-2',
                    isSelectedDateToday
                        ? 'text-gray-600 cursor-not-allowed'
                        : 'hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white'
                ]"
            >
                <svg :class="compact ? 'w-4 h-4' : 'w-5 h-5'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button
                @click="$emit('toggle-calendar')"
                :class="[
                    'rounded-lg hover:bg-gray-600 active:bg-gray-500 text-white font-medium transition flex items-center gap-2',
                    compact ? 'px-3 py-2 text-sm' : 'px-4 py-2'
                ]"
            >
                <span>📅</span>
                <span>{{ displayDate }}</span>
            </button>
            <button
                @click="$emit('next-day')"
                :class="[
                    'rounded-lg hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white transition flex items-center justify-center',
                    compact ? 'w-10 h-10' : 'p-2'
                ]"
            >
                <svg :class="compact ? 'w-4 h-4' : 'w-5 h-5'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- Calendar Dropdown -->
        <CalendarDropdown
            v-if="showCalendar"
            :calendar-month-year="calendarMonthYear"
            :calendar-days="calendarDays"
            @prev-month="$emit('prev-month')"
            @next-month="$emit('next-month')"
            @select-date="$emit('select-date', $event)"
            @select-today="$emit('select-today')"
            @select-tomorrow="$emit('select-tomorrow')"
        />
    </div>
</template>

<script setup>
/**
 * Date Selector Component
 *
 * Date navigation with calendar dropdown.
 */

import { ref, watch, onBeforeUnmount } from 'vue';
import CalendarDropdown from './CalendarDropdown.vue';

const containerRef = ref(null);

const props = defineProps({
    displayDate: {
        type: String,
        required: true,
        validator: (v) => typeof v === 'string' && v.length > 0,
    },
    isSelectedDateToday: {
        type: Boolean,
        default: false,
    },
    showCalendar: {
        type: Boolean,
        default: false,
    },
    calendarMonthYear: {
        type: String,
        default: '',
    },
    calendarDays: {
        type: Array,
        default: () => [],
        validator: (arr) => Array.isArray(arr),
    },
    focusMode: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'prev-day',
    'next-day',
    'toggle-calendar',
    'close-calendar',
    'prev-month',
    'next-month',
    'select-date',
    'select-today',
    'select-tomorrow',
]);

// Click outside handler
function handleClickOutside(event) {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        emit('close-calendar');
    }
}

// Watch showCalendar to add/remove listener
watch(() => props.showCalendar, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', handleClickOutside, true);
    } else {
        document.removeEventListener('click', handleClickOutside, true);
    }
});

// Cleanup on unmount
onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside, true);
});
</script>
