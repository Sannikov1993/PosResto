<template>
    <template v-if="timeSlots.length > 0">
        <div v-for="slot in timeSlots" :key="slot.key" class="mb-3">
            <!-- Slot Header -->
            <div
                :class="[
                    'flex items-center gap-2 px-2 @[300px]:px-3 py-1.5 @[300px]:py-2 rounded-lg mb-2 text-xs @[300px]:text-sm font-medium',
                    slot.urgency === 'overdue' ? 'bg-red-500/30 text-red-300' :
                    slot.urgency === 'urgent' ? 'bg-red-500/20 text-red-400' :
                    slot.urgency === 'warning' ? 'bg-yellow-500/20 text-yellow-400' :
                    'bg-gray-700/50 text-gray-300'
                ]"
            >
                <span>⏰</span>
                <span>{{ slot.label }}</span>
                <span class="ml-auto opacity-70">({{ slot.orders.length }})</span>
            </div>

            <!-- Slot Orders -->
            <div
                class="space-y-2 pl-2 border-l-2"
                :class="[
                    slot.urgency === 'overdue' ? 'border-red-500' :
                    slot.urgency === 'urgent' ? 'border-red-400' :
                    slot.urgency === 'warning' ? 'border-yellow-400' :
                    'border-gray-600'
                ]"
            >
                <div
                    v-for="order in slot.orders"
                    :key="order.id"
                    :class="[
                        'bg-gray-800 rounded-lg @[350px]:rounded-xl p-2 @[300px]:p-3 cursor-pointer hover:bg-gray-750 transition',
                        slot.urgency === 'overdue' || slot.urgency === 'urgent' ? 'ring-1 ring-red-500/50' : ''
                    ]"
                    @click="$emit('start-cooking', order)"
                >
                    <div class="flex items-center justify-between mb-1.5 @[300px]:mb-2">
                        <div class="flex items-center gap-1.5 @[300px]:gap-2 flex-wrap">
                            <span class="text-base @[300px]:text-lg">{{ getUrgencyIndicator(order.scheduled_at) }}</span>
                            <span class="font-bold text-white text-sm @[300px]:text-base">#{{ order.order_number }}</span>
                            <span class="text-xs px-1.5 @[300px]:px-2 py-0.5 rounded bg-gray-700 text-gray-300">
                                {{ getOrderTypeIcon(order) }}
                            </span>
                            <span
                                v-if="order.type === 'preorder' && order.table"
                                class="text-xs px-1.5 @[300px]:px-2 py-0.5 rounded bg-purple-500/30 text-purple-300"
                            >
                                {{ order.table.name || order.table.number }}
                            </span>
                        </div>
                        <span :class="['text-xs @[300px]:text-sm font-medium whitespace-nowrap', getUrgencyClass(order.scheduled_at)]">
                            {{ formatTimeUntilOrder(order.scheduled_at) }}
                        </span>
                    </div>
                    <p class="text-xs @[300px]:text-sm text-gray-400 truncate">{{ getItemsSummary(order.items) }}</p>
                    <button
                        class="mt-1.5 @[300px]:mt-2 w-full py-2 @[350px]:py-2.5 bg-blue-600 hover:bg-blue-500 rounded-lg text-xs @[300px]:text-sm font-medium transition"
                        @click.stop="$emit('start-cooking', order)"
                    >
                        ВЗЯТЬ В РАБОТУ
                    </button>
                </div>
            </div>
        </div>

        <!-- Divider if both preorders and ASAP exist -->
        <div v-if="timeSlots.length > 0" class="border-t border-gray-700 my-3"></div>
    </template>
</template>

<script setup>
/**
 * Preorder Slots Component
 *
 * Displays preorders grouped by 30-minute time slots.
 */

import { getMinutesUntil } from '../utils/time.js';
import { formatTimeUntil, getItemsSummary, getOrderTypeIcon } from '../utils/format.js';

defineProps({
    timeSlots: {
        type: Array,
        default: () => [],
        validator: (arr) => Array.isArray(arr) && arr.every(s => s && typeof s.key === 'string' && Array.isArray(s.orders)),
    },
});

defineEmits(['start-cooking', 'show-dish-info']);

function getUrgencyIndicator(scheduledAt) {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return '⚪';
    if (mins < 0) return '🔴';
    if (mins <= 30) return '🔴';
    if (mins <= 60) return '🟡';
    return '🟢';
}

function getUrgencyClass(scheduledAt) {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return 'text-gray-400';
    if (mins < 0) return 'text-red-400';
    if (mins <= 30) return 'text-red-400';
    if (mins <= 60) return 'text-yellow-400';
    return 'text-green-400';
}

function formatTimeUntilOrder(scheduledAt) {
    const mins = getMinutesUntil(scheduledAt);
    return formatTimeUntil(mins);
}
</script>
