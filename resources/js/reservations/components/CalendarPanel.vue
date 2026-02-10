<template>
    <div class="w-80 shrink-0">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <!-- Month Navigation -->
            <div class="flex items-center justify-between mb-4">
                <button @click="store.prevMonth()" class="p-2 hover:bg-gray-100 rounded-lg">←</button>
                <h3 class="font-bold text-lg">{{ store.monthName }} {{ store.currentYear }}</h3>
                <button @click="store.nextMonth()" class="p-2 hover:bg-gray-100 rounded-lg">→</button>
            </div>

            <!-- Weekdays -->
            <div class="grid grid-cols-7 gap-1 mb-2">
                <div v-for="day in ['Пн','Вт','Ср','Чт','Пт','Сб','Вс']" :key="day"
                     class="text-center text-xs text-gray-500 font-medium py-1">{{ day }}</div>
            </div>

            <!-- Days -->
            <div class="grid grid-cols-7 gap-1">
                <div v-for="n in store.firstDayOffset" :key="'empty-'+n"></div>
                <div v-for="day in store.calendarDays" :key="day.date"
                     @click="store.selectDate(day.date)"
                     :class="[
                         'aspect-square rounded-lg flex flex-col items-center justify-center cursor-pointer transition text-sm',
                         day.date === store.selectedDate ? 'bg-orange-500 text-white' :
                         day.isToday ? 'bg-orange-100 text-orange-600' :
                         day.isPast ? 'text-gray-300' : 'hover:bg-gray-100',
                         day.reservations_count > 0 && day.date !== store.selectedDate ? 'font-bold' : ''
                     ]">
                    <span>{{ day.day }}</span>
                    <span v-if="day.reservations_count > 0"
                          :class="['text-xs', day.date === store.selectedDate ? 'text-orange-100' : 'text-orange-500']">
                        {{ day.reservations_count }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-xl shadow-sm p-4 mt-4">
            <h4 class="font-semibold mb-3">На {{ store.formatDateShort(store.selectedDate) }}</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Всего броней:</span>
                    <span class="font-semibold">{{ store.selectedDateReservations.length }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Гостей:</span>
                    <span class="font-semibold">{{ store.totalGuestsForDate }}</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mt-4">
            <h4 class="font-semibold mb-3">Фильтр</h4>
            <div class="space-y-2">
                <button v-for="status in store.statuses" :key="status.value"
                        @click="store.toggleFilter(status.value)"
                        :class="[
                            'w-full text-left px-3 py-2 rounded-lg text-sm transition',
                            store.activeFilters.includes(status.value) ? status.activeClass : 'hover:bg-gray-100'
                        ]">
                    {{ status.icon }} {{ status.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useReservationsStore } from '../stores/reservations';
const store = useReservationsStore();
</script>
