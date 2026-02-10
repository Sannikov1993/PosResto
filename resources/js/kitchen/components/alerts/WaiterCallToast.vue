<template>
    <Transition name="slide-up">
        <div v-if="show" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
            <div class="bg-green-600 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4">
                <span class="text-3xl">📣</span>
                <div>
                    <p class="font-bold text-lg">Официант вызван!</p>
                    <p class="text-sm opacity-90">
                        {{ data.waiterName }} · Заказ #{{ data.orderNumber }}
                        <span v-if="data.tableName"> · Стол {{ data.tableName }}</span>
                    </p>
                </div>
                <span class="text-2xl">✅</span>
            </div>
        </div>
    </Transition>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
/**
 * Waiter Call Toast Component
 *
 * Success toast after calling waiter.
 */

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    data: {
        type: Object as PropType<Record<string, any>>,
        default: () => ({}),
        validator: (d) => !d || typeof d === 'object',
    },
});
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translate(-50%, 100%);
    opacity: 0;
}
</style>
