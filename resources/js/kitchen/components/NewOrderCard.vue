<template>
    <div :class="[
        'rounded-lg overflow-hidden shadow-lg transition-all duration-300',
        'border-l-4',
        urgencyBorderClass,
        order.isNew ? 'ring-2 ring-blue-400 ring-opacity-50' : ''
    ]">
        <!-- ═══════════════════════════════════════════════════════════
             HEADER - Номер заказа, тип, время
             ═══════════════════════════════════════════════════════════ -->
        <div :class="['px-3 py-2 sm:px-4 sm:py-3 flex items-center justify-between', urgencyHeaderClass]">
            <!-- Left: Type badge + table/details -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Order number - compact -->
                <span :class="['text-sm sm:text-base font-bold opacity-70', urgencyNumberClass]">
                    #{{ order.order_number }}
                </span>
                <!-- Order type badge - prominent -->
                <span :class="['px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-sm sm:text-base font-bold', typeBadgeClass]">
                    {{ getTypeIcon(order.type) }} {{ getTypeLabel(order.type) }}
                </span>
                <!-- Table number - very prominent if exists -->
                <span v-if="order.table" :class="['text-xl sm:text-2xl font-black', urgencyNumberClass]">
                    {{ order.table.number }}
                </span>
            </div>

            <!-- Right: Timer -->
            <div class="text-right">
                <div :class="['text-xl sm:text-2xl font-bold tabular-nums', waitTimeClass]">
                    {{ getWaitTime(order.created_at) }}
                </div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">ожидание</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ITEMS TABLE - Табличная структура позиций
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-800">
            <!-- Table Header -->
            <div class="grid grid-cols-[auto_1fr_auto] gap-2 px-3 py-1.5 sm:px-4 sm:py-2 bg-gray-750 border-b border-gray-700 text-xs sm:text-sm text-gray-500 uppercase tracking-wide">
                <div class="w-8 sm:w-10 text-center">Кол</div>
                <div>Блюдо</div>
                <div class="w-12 sm:w-16 text-center">Инфо</div>
            </div>

            <!-- Items List -->
            <div class="divide-y divide-gray-700/50">
                <div v-for="(item, index) in order.items" :key="item.id"
                     :class="[
                         'grid grid-cols-[auto_1fr_auto] gap-2 px-3 py-2 sm:px-4 sm:py-3 items-start',
                         Number(index) % 2 === 0 ? 'bg-gray-800' : 'bg-gray-800/50'
                     ]">
                    <!-- Quantity Badge -->
                    <div class="w-8 sm:w-10 flex justify-center">
                        <span :class="['inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-sm sm:text-base font-bold', quantityBadgeClass]">
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
                        <!-- Comment -->
                        <p v-if="item.comment" class="text-xs sm:text-sm text-yellow-400 mt-1 pl-5">
                            💬 {{ item.comment }}
                        </p>
                    </div>

                    <!-- Info Button -->
                    <div class="w-12 sm:w-16 flex justify-center">
                        <button @click.stop="$emit('showDishInfo', item)"
                                class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-gray-700 hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white flex items-center justify-center transition"
                                title="Рецепт">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            <div v-if="order.notes" class="px-3 py-2 sm:px-4 sm:py-3 bg-yellow-500/10 border-t border-yellow-500/30">
                <p class="text-sm sm:text-base text-yellow-400 font-medium">
                    📝 {{ order.notes }}
                </p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             FOOTER - Статистика + Кнопка действия
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-850 px-3 py-2 sm:px-4 sm:py-3 border-t border-gray-700">
            <!-- Stats Row -->
            <div class="flex items-center justify-between text-sm text-gray-400 mb-2 sm:mb-3">
                <div class="flex items-center gap-3 sm:gap-4">
                    <span>
                        <span class="text-white font-bold">{{ order.items.length }}</span> поз.
                    </span>
                    <span>
                        <span class="text-white font-bold">{{ totalQuantity }}</span> шт.
                    </span>
                </div>
                <span class="text-xs uppercase tracking-wide">{{ formatTime(order.created_at) }}</span>
            </div>

            <!-- Action Button -->
            <button @click="$emit('startCooking', order)"
                    :class="[
                        'w-full py-3 sm:py-4 rounded-lg font-bold text-base sm:text-lg transition-all',
                        'flex items-center justify-center gap-2',
                        'active:scale-[0.98] touch:active:scale-[0.98]',
                        urgencyButtonClass
                    ]">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
                ВЗЯТЬ В РАБОТУ
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, PropType } from 'vue';
import { getOrderTypeIcon, getOrderTypeLabel, getCategoryIcon, formatWaitTime, formatTimeOnly } from '../utils/format.js';

const props = defineProps({
    order: {
        type: Object as PropType<Record<string, any>>,
        required: true,
        validator: (o: any) => o && typeof o.id !== 'undefined' && typeof o.order_number !== 'undefined' && Array.isArray(o.items),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['startCooking', 'showDishInfo']);

const getTypeIcon = getOrderTypeIcon;
const getTypeLabel = getOrderTypeLabel;
const formatTime = formatTimeOnly;
const getWaitTime = formatWaitTime;

// Total quantity of all items
const totalQuantity = computed(() => {
    return props.order.items?.reduce((sum: any, item: any) => sum + (item.quantity || 1), 0) || 0;
});

// Wait time in minutes
const waitMinutes = computed(() => {
    if (!props.order.created_at) return 0;
    return Math.floor((new Date().getTime() - new Date(props.order.created_at).getTime()) / 60000);
});

// Urgency level
const urgencyLevel = computed(() => {
    const mins = waitMinutes.value;
    if (mins < 5) return 'normal';
    if (mins < 10) return 'warning';
    if (mins < 15) return 'urgent';
    return 'critical';
});

// Border color class
const urgencyBorderClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'border-l-yellow-500';
        case 'urgent': return 'border-l-orange-500';
        case 'critical': return 'border-l-red-500';
        default: return 'border-l-blue-500';
    }
});

// Header background class
const urgencyHeaderClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500/10';
        case 'urgent': return 'bg-orange-500/10';
        case 'critical': return 'bg-red-500/10';
        default: return 'bg-blue-500/10';
    }
});

// Order number color
const urgencyNumberClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'text-yellow-400';
        case 'urgent': return 'text-orange-400';
        case 'critical': return 'text-red-400';
        default: return 'text-blue-400';
    }
});

// Wait time color
const waitTimeClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'text-yellow-400';
        case 'urgent': return 'text-orange-400';
        case 'critical': return 'text-red-400 animate-pulse';
        default: return 'text-green-400';
    }
});

// Quantity badge class
const quantityBadgeClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500/20 text-yellow-400';
        case 'urgent': return 'bg-orange-500/20 text-orange-400';
        case 'critical': return 'bg-red-500/20 text-red-400';
        default: return 'bg-blue-500/20 text-blue-400';
    }
});

// Button class
const urgencyButtonClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500 hover:bg-yellow-400 text-gray-900';
        case 'urgent': return 'bg-orange-500 hover:bg-orange-400 text-white';
        case 'critical': return 'bg-red-500 hover:bg-red-400 text-white animate-pulse';
        default: return 'bg-blue-500 hover:bg-blue-400 text-white';
    }
});

// Type badge class
const typeBadgeClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500/20 text-yellow-300';
        case 'urgent': return 'bg-orange-500/20 text-orange-300';
        case 'critical': return 'bg-red-500/20 text-red-300';
        default: return 'bg-blue-500/20 text-blue-300';
    }
});
</script>

<style scoped>
.bg-gray-750 {
    background-color: rgb(38, 42, 51);
}
.bg-gray-850 {
    background-color: rgb(24, 27, 33);
}
</style>
