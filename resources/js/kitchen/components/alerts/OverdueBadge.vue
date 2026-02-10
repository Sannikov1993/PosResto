<template>
    <div v-if="overdueOrders.length > 0" class="fixed bottom-6 right-6 z-40">
        <div
            :class="[
                'rounded-2xl p-4 shadow-2xl cursor-pointer transition-all transform hover:scale-105',
                hasAlertOrders ? 'bg-red-600 animate-pulse' :
                hasCriticalOrders ? 'bg-red-500' : 'bg-yellow-500'
            ]"
            @click="$emit('click')"
        >
            <div class="flex items-center gap-3 text-white">
                <span class="text-3xl">⚠️</span>
                <div>
                    <p class="font-bold text-lg">{{ overdueOrders.length }} просрочено</p>
                    <p class="text-sm opacity-80">до {{ maxCookingTime }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Overdue Badge Component
 *
 * Floating badge showing overdue order count.
 */

import { computed, PropType } from 'vue';
import { formatCookingTime } from '../../utils/format.js';

const props = defineProps({
    overdueOrders: {
        type: Array as PropType<any[]>,
        default: () => [],
        validator: (arr) => Array.isArray(arr) && arr.every((o: any) => typeof o.cookingMinutes === 'number'),
    },
});

defineEmits(['click']);

const hasAlertOrders = computed(() => props.overdueOrders.some((o: any) => o.isAlert));
const hasCriticalOrders = computed(() => props.overdueOrders.some((o: any) => o.isCritical));
const maxCookingTime = computed(() => {
    const max = Math.max(...props.overdueOrders.map((o: any) => o.cookingMinutes));
    return formatCookingTime(max);
});
</script>
