<template>
    <div v-if="orders.length > 0">
        <!-- ASAP Header -->
        <div class="flex items-center gap-2 px-2 @[300px]:px-3 py-1.5 @[300px]:py-2 rounded-lg mb-2 text-xs @[300px]:text-sm font-medium bg-blue-500/20 text-blue-400">
            <span>⚡</span>
            <span>Ближайшие</span>
            <span class="ml-auto opacity-70">({{ orders.length }})</span>
        </div>

        <!-- ASAP Orders -->
        <div class="space-y-2 pl-2 border-l-2 border-blue-500">
            <NewOrderCard
                v-for="order in orders"
                :key="order.id"
                :order="order"
                :compact="compact"
                @start-cooking="$emit('start-cooking', order)"
                @show-dish-info="$emit('show-dish-info', $event)"
            />
        </div>
    </div>
</template>

<script setup>
/**
 * ASAP Orders Component
 *
 * Displays non-scheduled (ASAP) orders.
 */

import NewOrderCard from './NewOrderCard.vue';

defineProps({
    orders: {
        type: Array,
        default: () => [],
        validator: (arr) => Array.isArray(arr) && arr.every(o => o && typeof o.id !== 'undefined'),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['start-cooking', 'show-dish-info']);
</script>
