<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-gradient-to-br from-red-700 via-red-600 to-orange-600 flex items-center justify-center z-50 animate-pulse-bg"
        @click="$emit('dismiss')"
    >
        <div class="text-center text-white max-w-2xl">
            <p class="text-9xl mb-6 animate-bounce">⏰</p>
            <p class="text-5xl font-bold mb-4 animate-pulse">ПРОСРОЧЕН!</p>
            <p class="text-6xl font-extrabold mb-4">#{{ data.order_number }}</p>
            <div class="bg-white/20 rounded-xl p-6 mb-6">
                <p class="text-3xl font-bold text-yellow-300">{{ formatCookingTime(data.cookingMinutes) }}</p>
                <p class="text-xl mt-2">в работе</p>
            </div>
            <div v-if="data.table" class="mb-4">
                <p class="text-2xl">🍽️ Стол {{ data.table.number || data.table.name }}</p>
            </div>
            <p class="text-lg opacity-75">{{ data.items?.length || 0 }} позиций</p>
            <p class="text-xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
        </div>
    </div>
</template>

<script setup>
/**
 * Overdue Alert Component
 *
 * Full-screen alert for overdue orders.
 */

import { formatCookingTime } from '../../utils/format.js';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    data: {
        type: Object,
        default: () => ({}),
        validator: (d) => !d || typeof d === 'object',
    },
});

defineEmits(['dismiss']);
</script>
