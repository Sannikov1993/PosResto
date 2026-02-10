<template>
    <div class="rounded-lg overflow-hidden shadow-lg border-l-4 border-l-green-500 ring-2 ring-green-500/30">
        <!-- ═══════════════════════════════════════════════════════════
             HEADER - Номер заказа, тип, время готовности
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-green-500/10 px-3 py-2 sm:px-4 sm:py-3 flex items-center justify-between">
            <!-- Left: Type badge + table/details -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Order number - compact -->
                <span class="text-sm sm:text-base font-bold text-green-400/70">
                    #{{ order.order_number }}
                </span>
                <!-- Order type badge - prominent -->
                <span class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-sm sm:text-base font-bold bg-green-500/20 text-green-300">
                    {{ getTypeIcon(order.type) }} {{ getTypeLabel(order.type) }}
                </span>
                <!-- Table number - very prominent if exists -->
                <span v-if="order.table" class="text-xl sm:text-2xl font-black text-green-400">
                    {{ order.table.number }}
                </span>
            </div>

            <!-- Right: Ready time -->
            <div class="text-right">
                <div class="text-xl sm:text-2xl font-bold tabular-nums text-green-400">
                    {{ getWaitTime(order.ready_at) }}
                </div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">готов</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             STATUS BANNER
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-green-500 px-4 py-2 sm:py-3 flex items-center justify-center gap-2">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="text-white font-bold text-base sm:text-lg uppercase tracking-wide">Ожидает выдачи</span>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ITEMS TABLE - Компактный список
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-800">
            <!-- Table Header -->
            <div class="grid grid-cols-[auto_1fr] gap-2 px-3 py-1.5 sm:px-4 sm:py-2 bg-gray-750 border-b border-gray-700 text-xs sm:text-sm text-gray-500 uppercase tracking-wide">
                <div class="w-8 sm:w-10 text-center">Кол</div>
                <div>Блюдо</div>
            </div>

            <!-- Items List -->
            <div class="divide-y divide-gray-700/50">
                <div v-for="(item, index) in order.items" :key="item.id"
                     :class="[
                         'grid grid-cols-[auto_1fr] gap-2 px-3 py-2 sm:px-4 sm:py-2.5 items-start',
                         Number(index) % 2 === 0 ? 'bg-gray-800' : 'bg-gray-800/50'
                     ]">
                    <!-- Quantity Badge -->
                    <div class="w-8 sm:w-10 flex justify-center">
                        <span class="inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-sm sm:text-base font-bold bg-green-500/20 text-green-400">
                            {{ item.quantity }}
                        </span>
                    </div>

                    <!-- Dish Info -->
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-base sm:text-lg">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                            <span class="font-semibold text-sm sm:text-base text-white">{{ item.name }}</span>
                            <span v-if="item.guest_number"
                                  class="text-xs bg-purple-500/30 text-purple-300 px-1.5 py-0.5 rounded font-medium">
                                Г{{ item.guest_number }}
                            </span>
                        </div>
                        <!-- Modifiers -->
                        <div v-if="item.modifiers?.length" class="mt-1 space-y-0.5">
                            <p v-for="mod in item.modifiers" :key="mod.option_id || mod.id"
                               class="text-xs sm:text-sm text-blue-400 pl-5">
                                + {{ mod.option_name || mod.name }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             WAITER INFO (if assigned)
             ═══════════════════════════════════════════════════════════ -->
        <div v-if="order.waiter" class="bg-gray-850 px-3 py-2 sm:px-4 sm:py-3 border-t border-gray-700 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-lg">
                👤
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Официант</div>
                <div class="font-semibold text-white truncate">{{ order.waiter.name }}</div>
            </div>
            <div v-if="waiterCalled" class="px-3 py-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg text-sm font-medium flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Вызван
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             FOOTER - Действия
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-850 px-3 py-2 sm:px-4 sm:py-3 border-t border-gray-700 space-y-2">
            <!-- Call Waiter Button -->
            <button @click="$emit('callWaiter', order)"
                    :disabled="waiterCalled"
                    :class="[
                        'w-full py-3 sm:py-4 rounded-lg font-bold text-base sm:text-lg transition-all flex items-center justify-center gap-2',
                        waiterCalled
                            ? 'bg-yellow-500/20 text-yellow-400 cursor-not-allowed'
                            : 'bg-yellow-500 hover:bg-yellow-400 text-gray-900 active:scale-[0.98]'
                    ]">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                {{ waiterCalled ? 'ОФИЦИАНТ ВЫЗВАН' : 'ВЫЗВАТЬ ОФИЦИАНТА' }}
            </button>

            <!-- Return Button -->
            <button @click="$emit('returnToCooking', order)"
                    class="w-full py-2.5 sm:py-3 rounded-lg bg-gray-700 hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white transition flex items-center justify-center gap-2 font-medium text-sm sm:text-base">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Вернуть в готовку
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, PropType } from 'vue';
import { getOrderTypeIcon, getOrderTypeLabel, getCategoryIcon, formatWaitTime } from '../utils/format.js';

const props = defineProps({
    order: {
        type: Object as PropType<Record<string, any>>,
        required: true,
        validator: (o: any) => o && typeof o.id !== 'undefined' && typeof o.order_number !== 'undefined',
    },
    waiterCalled: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['returnToCooking', 'callWaiter']);

const getTypeIcon = getOrderTypeIcon;
const getTypeLabel = getOrderTypeLabel;
const getWaitTime = formatWaitTime;
</script>

<style scoped>
.bg-gray-750 {
    background-color: rgb(38, 42, 51);
}
.bg-gray-850 {
    background-color: rgb(24, 27, 33);
}
</style>
