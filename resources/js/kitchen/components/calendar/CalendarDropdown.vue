<template>
    <div class="absolute top-full left-0 mt-2 z-50">
        <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 p-4 w-72">
            <!-- Calendar Header -->
            <div class="flex items-center justify-between mb-3">
                <button @click="$emit('prev-month')" class="p-1 hover:bg-gray-700 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <span class="font-medium">{{ calendarMonthYear }}</span>
                <button @click="$emit('next-month')" class="p-1 hover:bg-gray-700 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Weekdays -->
            <div class="grid grid-cols-7 gap-1 mb-2 text-center text-xs text-gray-500">
                <span>Пн</span>
                <span>Вт</span>
                <span>Ср</span>
                <span>Чт</span>
                <span>Пт</span>
                <span>Сб</span>
                <span>Вс</span>
            </div>

            <!-- Days -->
            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="day in calendarDays"
                    :key="day.date || `empty-${day.day}`"
                    @click="day.date && !day.isPast && $emit('select-date', day.date)"
                    :disabled="!day.date || day.isPast"
                    :class="[
                        'h-8 w-8 rounded-lg text-sm transition relative',
                        !day.date ? 'text-gray-700 cursor-default' :
                        day.isPast ? 'text-gray-600 cursor-not-allowed' :
                        day.isSelected ? 'bg-accent text-white' :
                        day.isToday ? 'bg-gray-700 text-accent' :
                        'hover:bg-gray-700 text-gray-300'
                    ]"
                >
                    {{ day.day }}
                    <span
                        v-if="day.count > 0"
                        class="absolute -top-1 -right-1 min-w-4 h-4 flex items-center justify-center text-[10px] font-bold bg-orange-500 text-white rounded-full px-1"
                    >
                        {{ day.count > 99 ? '99+' : day.count }}
                    </span>
                </button>
            </div>

            <!-- Quick buttons -->
            <div class="flex gap-2 mt-3 pt-3 border-t border-gray-700">
                <button
                    @click="$emit('select-today')"
                    class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-700 hover:bg-gray-600 transition"
                >
                    Сегодня
                </button>
                <button
                    @click="$emit('select-tomorrow')"
                    class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-700 hover:bg-gray-600 transition"
                >
                    Завтра
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
/**
 * Calendar Dropdown Component
 *
 * Calendar picker dropdown with day selection.
 */

defineProps({
    calendarMonthYear: {
        type: String,
        required: true,
        validator: (v) => typeof v === 'string' && v.length > 0,
    },
    calendarDays: {
        type: Array as PropType<any[]>,
        required: true,
        validator: (arr) => Array.isArray(arr) && arr.every((d: any) => typeof d === 'object'),
    },
});

defineEmits([
    'prev-month',
    'next-month',
    'select-date',
    'select-today',
    'select-tomorrow',
]);
</script>
