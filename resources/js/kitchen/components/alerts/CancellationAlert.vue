<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-red-600/95 flex items-center justify-center z-50"
        @click="$emit('dismiss')"
    >
        <div class="text-center text-white max-w-2xl">
            <p class="text-9xl mb-8">⛔</p>
            <p class="text-5xl font-bold mb-4">ОТМЕНА!</p>
            <p class="text-3xl font-bold mb-2">{{ data.item_name }}</p>
            <p class="text-2xl mb-4">×{{ data.quantity }}</p>
            <div class="bg-white/20 rounded-xl p-4 mb-4">
                <p class="text-xl">Заказ: {{ data.order_number }}</p>
                <p class="text-xl" v-if="data.table_number">Стол: {{ data.table_number }}</p>
            </div>
            <p class="text-2xl font-bold text-yellow-300">{{ data.reason_label }}</p>
            <p class="text-lg mt-2 opacity-75" v-if="data.reason_comment">{{ data.reason_comment }}</p>
            <p class="text-xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
/**
 * Cancellation Alert Component
 *
 * Full-screen alert for order cancellation.
 */

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    data: {
        type: Object as PropType<Record<string, any>>,
        default: () => ({}),
        validator: (d: any) => d && typeof d === 'object',
    },
});

defineEmits(['dismiss']);
</script>
